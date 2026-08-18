<?php

namespace App\Services\Comps;

use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a parsed upload is actually an entry, and which physics it
 * belongs to.
 *
 * An entry is created the moment somebody uploads, because the parse happens
 * on a queue and making them wait on a spinner to find out whether the file
 * was accepted would be worse. It sits pending until the parser has read the
 * demo, and this turns that reading into a verdict.
 *
 * **Nobody is asked which physics they ran.** The demo says so, and the parser
 * already reads it out - asking the uploader only adds a question they can get
 * wrong about their own file, and then a rejection that reads as our mistake.
 * So the physics comes out of the demo and the map is checked against whatever
 * this round is being played on in that physics.
 *
 * The checks are deliberately narrow. This is not anti-cheat - that is what
 * reporting an entry is for, and what a human looking at the demo is for. All
 * this asks is: is this a run of the map this round is being played on, with a
 * time in it.
 */
class SubmissionValidator
{
    /** Parser outcomes that mean it read the file successfully. */
    public const PARSED = ['processed', 'assigned', 'fallback-assigned'];

    /**
     * Outcomes that are not outcomes yet: the parser has the file and has not
     * finished with it.
     *
     * Load-bearing. `ProcessDemoJob` sets `processing` before it reads a byte,
     * and that write is a status change, so the observer lands here with an
     * entry that is still waiting and a demo that looks unreadable only
     * because nothing has read it yet. Without this every entry made through
     * the comps form was refused with "The demo could not be read" seconds
     * after it was made, and `settleFor` only ever revisits `pending` rows -
     * so the real verdict, which arrived moments later, found nothing left to
     * correct. 12 entries in the first week, every single manual one.
     */
    private const IN_FLIGHT = ['uploaded', 'processing'];

    /**
     * The parser read the demo, and noted a cvar that was not what it should
     * be - `pmove_fixed 0`, a timescale, an unconfirmed finish.
     *
     * NOT an unreadable file, however much the name suggests it. The rest of
     * the site is explicit about this: the map page lists these demos beside
     * the clean ones because "it deviated on some cvar" is not "it is
     * unusable". Comps left the status out of PARSED and it fell through to
     * the last branch, so a run whose time, map, physics and offending cvar
     * were all known came back as "The demo could not be read." - to the one
     * person who could have fixed their config if anybody had said which cvar.
     */
    public const FLAGGED = 'failed-validity';

    /**
     * Settle every pending entry hanging off a finished upload.
     */
    public function settleFor(UploadedDemo $demo): void
    {
        $pending = CompSubmission::where('uploaded_demo_id', $demo->id)
            ->where('status', 'pending')
            ->with('round.maps.map')
            ->get();

        foreach ($pending as $submission) {
            $this->settle($submission, $demo);
        }
    }

    public function settle(CompSubmission $submission, UploadedDemo $demo): void
    {
        // Still being read. Say nothing and stay pending; the parser's own
        // finish will bring us back here with something to judge.
        if (in_array($demo->status, self::IN_FLIGHT, true)) {
            return;
        }

        // A flagged demo goes on through the checks below rather than being
        // refused here: its map, physics and time are all known, so it can be
        // told exactly what was wrong with it, and the note is only the last
        // of the reasons it does not count.
        $flagged = $demo->status === self::FLAGGED;

        if (! $flagged && ! in_array($demo->status, self::PARSED, true)) {
            $this->reject($submission, __('The demo could not be read.'), releasable: true);

            return;
        }

        $round = $submission->round;

        if (! $round) {
            return;
        }

        $physics = $this->physicsOf($demo);

        if (! $physics) {
            $this->reject($submission, __('The physics could not be read from this demo.'), releasable: true);

            return;
        }

        $expected = $round->maps->firstWhere('physics', $physics);

        if (! $expected || ! $expected->map) {
            // The round has no map for this physics yet, which can only happen
            // if something uploaded before the ballot closed. Leave it pending
            // rather than reject: it will settle when the map is known.
            return;
        }

        if (! $this->sameMap($demo->map_name, $expected->map->name)) {
            // Say which map this week's run was supposed to be on, in the
            // physics the demo turned out to be - people get the map right and
            // the physics wrong far more often than the other way round, and a
            // bare "wrong map" would send them hunting for the wrong mistake.
            $this->reject($submission, __('This demo is :physics on :map. The :physics map this round is :expected.', [
                'physics' => strtoupper($physics),
                'map' => $demo->map_name ?: '?',
                'expected' => $expected->map->name,
            ]), releasable: true);

            return;
        }

        // A real run of this round's map, and it still does not count. Said
        // with the cvar named, and never released: an entry somebody can read
        // and report beats a demo that quietly went public.
        if ($flagged) {
            $this->reject($submission, $this->validityReason($demo));

            return;
        }

        if (! $demo->time_ms) {
            $this->reject($submission, __('No finish time was found in this demo.'));

            return;
        }

        $submission->update([
            'status' => 'valid',
            'invalid_reason' => null,
            'physics' => $physics,
            'time' => (int) $demo->time_ms,
            // Whether it was run online is the gametype, not whether we managed
            // to pair it with a record: an online run on a server nobody
            // scrapes is still an online run. `mdf`, `mfs`, `mfc` are the
            // online ones - see UploadedDemo::getIsOnlineAttribute.
            'is_online' => (bool) $demo->is_online,
            // Pairing with a scraped record is a bonus and nothing more. It
            // changes nothing about the entry and is only worth showing.
            'matched_record_id' => $demo->record_id,
        ]);

        Log::info("[comps] submission {$submission->id} accepted: {$physics} {$demo->time_ms}ms");
    }

    /**
     * What each noted cvar should have been.
     *
     * Mirrors the parser's own rules (DemoProcessor/bin/demo.py,
     * `_check_validity`). Kept here so the refusal can say what the value
     * should be rather than only that it was wrong - a person who is told
     * "pmove_fixed" has to go and find out what pmove_fixed is supposed to be,
     * and most will not.
     */
    private const EXPECTED = [
        'sv_cheats' => '0',
        'timescale' => '1',
        'g_speed' => '320',
        'g_gravity' => '800',
        'handicap' => '100',
        'g_knockback' => '1000',
        'df_mp_interferenceoff' => '3',
        'sv_fps' => '125',
        'com_maxfps' => '125',
        'pmove_msec' => '8',
        'g_killWallbug' => '1',
    ];

    /**
     * The refusal a flagged demo carries, naming the cvars that were noted.
     *
     * The note itself is not a cheating verdict - `client_finish` only says the
     * finish was not confirmed client-side - so the sentence says what was
     * seen, not what it means, and leaves the judgement to whoever the person
     * reports it to.
     *
     * Lives here rather than on UploadGuard because both routes refuse the same
     * demo for the same reason, and two wordings for one verdict is how the
     * automatic path came to tell the truth while the form did not.
     */
    public function validityReason(UploadedDemo $demo): string
    {
        $notes = [];

        foreach ((array) $demo->validity as $key => $value) {
            $notes[] = $this->explainNote((string) $key, (string) $value);
        }

        if (! $notes) {
            return __('This demo did not pass the validity check, so it does not count in comps.');
        }

        return __('This demo does not count in comps. :notes', ['notes' => implode(' ', $notes)]);
    }

    /**
     * One noted cvar, in a sentence somebody can act on.
     *
     * Says the value that was in the demo and the value it should have been,
     * because the whole point is that the person can go and fix their config.
     * `pmove_fixed` gets a longer sentence than the rest: it is the only rule
     * with two legal answers, and being told to set it to 1 while running the
     * dfcomp ruleset - which deliberately keeps it at 0 - would be wrong.
     */
    private function explainNote(string $key, string $value): string
    {
        if ($key === 'client_finish') {
            return __('The finish was not confirmed inside the demo, so the time cannot be read from it.');
        }

        if ($key === 'tool_assisted') {
            return __('The run was recorded with tool assistance.');
        }

        if ($key === 'pmove_fixed') {
            return __('pmove_fixed was :actual. It has to be 1, or g_synchronousClients has to be 1 instead - one of the two has to hold the physics step steady, or the framerate decides how far you jump.', ['actual' => $value]);
        }

        if (isset(self::EXPECTED[$key])) {
            return __(':cvar was :actual, and it has to be :expected.', [
                'cvar' => $key,
                'actual' => $value,
                'expected' => self::EXPECTED[$key],
            ]);
        }

        return __(':cvar was :actual, which is not what comps expects.', [
            'cvar' => $key,
            'actual' => $value,
        ]);
    }

    /**
     * The physics the demo was run in. The parser records it with the mode
     * attached - `cpm.1`, `vq3.7` for fastcaps - so only the leading word is
     * taken.
     */
    private function physicsOf(UploadedDemo $demo): ?string
    {
        $base = strtolower(strtok(trim((string) $demo->physics), '.'));

        return in_array($base, BallotResolver::PHYSICS, true) ? $base : null;
    }

    /**
     * An entry the launcher chose is undone rather than rejected.
     *
     * The launcher spots a comps map by reading the demo's filename, which is a
     * convention and not a promise. When the parse disagrees, the honest thing
     * is to leave no trace of the guess: drop the entry and release the demo,
     * so it becomes the ordinary upload it would have been if the launcher had
     * never routed it here.
     *
     * Only the verdicts that mean "we cannot confirm this is a run of this
     * round's map" qualify. A run on the RIGHT map that simply has no finish
     * time in it is a real attempt at this round and stays visible as one -
     * releasing that would publish somebody's failed comps run while everyone
     * else's is still hidden.
     *
     * A person who chose to enter is never treated this way. They picked the
     * file, and a rejection they can see beats a demo that quietly went public.
     */
    private function reject(CompSubmission $submission, string $reason, bool $releasable = false): void
    {
        if ($releasable && $submission->auto_entered) {
            // Not through `$submission->demo`: that relation runs into the
            // global scope which hides exactly the demo we are holding, so it
            // answers null and the release would silently not happen.
            $demo = UploadedDemo::withUnreleasedComps()->find($submission->uploaded_demo_id);

            if ($demo) {
                UploadedDemo::withUnreleasedComps()
                    ->whereKey($demo->id)
                    ->update(['comps_hidden_until' => null]);

                // A query-builder update fires no model events, so the map's
                // Demos Top would keep the run hidden for another hour after
                // it was let out.
                UploadedDemo::forgetDemosTop($demo->map_name);
            }

            $submission->delete();

            Log::info("[comps] auto entry {$submission->id} withdrawn, demo released: {$reason}");

            return;
        }

        $submission->update([
            'status' => 'invalid',
            'invalid_reason' => $reason,
        ]);
    }

    /**
     * Map names are compared case-insensitively. `maps.name` carries capitals
     * on a few hundred maps (6RL, AFTCTF1) where everything downstream is
     * lowercase, and an exact string comparison quietly rejects a perfectly
     * good run on one of them.
     */
    private function sameMap(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}
