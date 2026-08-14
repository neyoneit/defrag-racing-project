<?php

namespace App\Services\Comps;

use App\Models\Comp;
use App\Models\CompDemoReport;
use App\Models\CompRound;
use App\Models\CompRoundMap;
use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Keeps runs on a map comps is using out of the public site, and enters the
 * ones that belong in the round.
 *
 * **Why this is here and not in the launcher.** The launcher recognises a comps
 * map by reading the demo's filename, which is a convention: rename the file
 * and the guard is blind. This runs after the parser has read the demo itself,
 * so it decides on the map and physics that are actually in the file. Renaming
 * cannot get past it, and it covers every route at once - the web upload form,
 * the launcher, demome, an extracted archive - because they all end up in the
 * same place: a parsed `uploaded_demos` row.
 *
 * There is no window to slip through. The public demo browse and the map page
 * both list only demos the parser has finished with, so a demo is invisible
 * until exactly the moment this gets to look at it.
 *
 * **Map and physics both, for holding as well as for entering.** A VQ3 run on
 * the map being played in CPM cannot enter the round and is not competing with
 * anybody in it, so it is published like any other demo - neyo's call, 14 Aug
 * 2026. Only a run of the map in the physics it is being played in waits.
 *
 * The one exception is the ballot, where the physics is not decided yet: a
 * candidate map can win in either physics or in both, so demos of it wait
 * until the vote says which. See `adoptForRound`, which lets the ones in the
 * other physics out the moment that is known.
 */
class UploadGuard
{
    /** Parser outcome for a demo it read, but which carries a cvar note. */
    private const FLAGGED = 'failed-validity';

    /** Parser outcome for a demo it could not read at all. */
    private const UNREADABLE = 'failed';

    public function __construct(
        private SubmissionValidator $validator,
        private SubmissionIntake $intake,
    ) {
    }

    /**
     * Decide what happens to a demo the parser has just finished with.
     */
    public function apply(UploadedDemo $demo): void
    {
        // A demo somebody uploaded through comps already has its entry and its
        // hold; SubmissionValidator owns that one.
        if ($this->alreadyEntered($demo)) {
            return;
        }

        // A demo the parser could not read has no map, no physics and no time,
        // so it can never be entered - but it can still be a run on the map
        // being played, and unreadable demos are listed publicly like any
        // other. The filename is then the only thing left to go on. A weak
        // criterion, and better than publishing the one demo we know nothing
        // about.
        $mapName = $demo->map_name ?: $this->mapFromFilename($demo->original_filename);

        if (! $mapName) {
            return;
        }

        if ($round = $this->playedRoundFor($mapName)) {
            // The map is being played, but perhaps not in this demo's physics -
            // and a run in the other physics is not in this round and is not
            // competing with anybody in it. It goes public straight away.
            if (! $this->playedInThisPhysics($round, $mapName, $demo)) {
                return;
            }

            if (! $this->arrivedInsideTheWindow($demo, $round)) {
                return;
            }

            $this->holdUntil($demo, $round->ends_at);
            $this->enterIfItBelongs($demo, $round);

            return;
        }

        // A map on the open ballot might be next week's map, and a run made
        // while the ballot is open counts if it wins. Held only to the ballot's
        // own deadline: a map that loses releases its demos by that timestamp
        // simply passing, with nothing to clean up. A map that wins gets the
        // hold extended by `adoptForRound` when the ballot closes.
        if ($round = $this->ballotRoundFor($mapName)) {
            if (! $this->arrivedInsideTheWindow($demo, $round)) {
                return;
            }

            $this->holdUntil($demo, $round->voting_closes_at);
        }
    }

    /**
     * The ballot closed and the maps are known: take in the demos that were
     * held while people were voting.
     *
     * This is what makes "a run from the voting period counts" true. Those
     * demos were uploaded before the round existed as something to enter, so
     * nothing could have entered them at the time.
     */
    public function adoptForRound(CompRound $round): int
    {
        $adopted = 0;

        foreach ($round->maps as $roundMap) {
            if (! $roundMap->map) {
                continue;
            }

            $demos = UploadedDemo::withUnreleasedComps()
                ->whereNotNull('comps_hidden_until')
                ->whereRaw('LOWER(map_name) = ?', [mb_strtolower(trim($roundMap->map->name))])
                ->get();

            foreach ($demos as $demo) {
                // Held while the vote was open, when nobody could know which
                // physics this map would be played in. Now it is known, and a
                // run in the other one has nothing to wait for.
                if (! $this->playedInThisPhysics($round, $roundMap->map->name, $demo)) {
                    $this->release($demo);

                    continue;
                }

                $this->holdUntil($demo, $round->ends_at);

                if (! $this->alreadyEntered($demo) && $this->enterIfItBelongs($demo, $round)) {
                    $adopted++;
                }
            }
        }

        return $adopted;
    }

    /**
     * The refusal a flagged demo carries, naming the cvars that were noted.
     *
     * The note itself is not a cheating verdict - `client_finish` only says the
     * finish was not confirmed client-side - so the sentence says what was
     * seen, not what it means, and leaves the judgement to whoever the person
     * reports it to.
     */
    private function validityReason(UploadedDemo $demo): string
    {
        $flags = collect((array) $demo->validity)->keys()->implode(', ');

        return $flags === ''
            ? __('This demo did not pass the validity check, so it does not count in comps.')
            : __('This demo carries a validity note (:flags), so it does not count in comps.', ['flags' => $flags]);
    }

    /**
     * The round is over: let every demo held on its maps out at once.
     *
     * The entries are released by the scheduler through their submissions, but
     * the demos that were only HELD - somebody's old run, a run in the other
     * physics - have no submission to be released through. Their timestamp
     * would expire on its own, and only exactly when the round was said to end;
     * an admin moving the end time would publish them early. Releasing them
     * here makes the round's end the one moment that decides.
     */
    public function releaseForRound(CompRound $round): int
    {
        $released = 0;

        foreach ($round->maps as $roundMap) {
            if (! $roundMap->map) {
                continue;
            }

            $released += UploadedDemo::withUnreleasedComps()
                ->whereNotNull('comps_hidden_until')
                ->whereRaw('LOWER(map_name) = ?', [mb_strtolower(trim($roundMap->map->name))])
                ->update(['comps_hidden_until' => null]);
        }

        return $released;
    }

    /**
     * The demos of this person's that comps is holding without having entered
     * them, each with a sentence saying why.
     *
     * Without this the site simply swallows them. A run the parser could not
     * read, a run made before the ballot opened, a run in the other physics:
     * each one vanishes from the public list and none of them produce an entry,
     * so the person is left watching for something that is never going to
     * appear, with nothing to read and nobody to ask. Every one of them gets a
     * reason and a date instead.
     *
     * Only the caller's own demos, which is what makes it safe to be this
     * talkative: it says a map is being held, and the person already knows,
     * because it is their file.
     */
    public function noticesFor(int $userId, int $limit = 20): array
    {
        $demos = UploadedDemo::withUnreleasedComps()
            ->where('user_id', $userId)
            ->whereNotNull('comps_hidden_until')
            ->where('comps_hidden_until', '>', now())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($demos->isEmpty()) {
            return [];
        }

        $entered = CompSubmission::whereIn('uploaded_demo_id', $demos->pluck('id'))
            ->pluck('uploaded_demo_id')
            ->all();

        // Whether they have already asked about it, so the page can say so
        // instead of offering the same button again.
        $asked = CompDemoReport::whereIn('uploaded_demo_id', $demos->pluck('id'))
            ->where('reported_by', $userId)
            ->pluck('uploaded_demo_id')
            ->all();

        return $demos
            ->reject(fn (UploadedDemo $demo) => in_array($demo->id, $entered, true))
            ->map(function (UploadedDemo $demo) use ($asked) {
                $kind = $this->noticeKind($demo);

                return [
                    'id' => $demo->id,
                    'filename' => $demo->original_filename,
                    'kind' => $kind,
                    'note' => $this->noticeText($kind),
                    'appears_at' => $demo->comps_hidden_until?->toIso8601String(),
                    'reported' => in_array($demo->id, $asked, true),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Why a held demo did not become an entry.
     *
     * Read from the demo and the rounds as they stand right now rather than
     * stored when the guard ran, because the answer moves: a map on the ballot
     * that wins turns "waiting for the vote" into an entry, and a stored note
     * would still be saying the old thing.
     */
    public function noticeKind(UploadedDemo $demo): string
    {
        if ($demo->status === self::UNREADABLE) {
            return 'unreadable';
        }

        $mapName = $demo->map_name ?: $this->mapFromFilename($demo->original_filename);

        if ($mapName && $round = $this->playedRoundFor($mapName)) {
            if (! $this->roundMapFor($demo, $round)) {
                return 'other_physics';
            }

            if (! $this->madeInsideTheWindow($demo, $round)) {
                return 'too_old';
            }

            if ($this->intake->userRejectionReason($demo->user)) {
                return 'not_linked';
            }

            return 'held';
        }

        if ($mapName && $this->ballotRoundFor($mapName)) {
            return 'ballot';
        }

        return 'held';
    }

    /**
     * The sentence the person reads. Finished and translated here, the same way
     * `invalid_reason` is, so the launcher can print it without knowing a
     * single comps rule.
     */
    public function noticeText(string $kind): string
    {
        return match ($kind) {
            'unreadable' => __('This demo could not be read, so it does not count in comps. Please tell an admin about it.'),
            'too_old' => __('This run is older than the comps round, so it does not count. It appears on the site once the round is over.'),
            'other_physics' => __('This is a run on a map the round is being played on, in the other physics. It appears on the site once the round is over.'),
            'not_linked' => __('Your account has no Q3DF.org profile linked, so this run is not in comps. It appears on the site once the round is over.'),
            'ballot' => __('This map is still being voted on. If it wins, this run enters the round when voting closes.'),
            default => __('This demo is on a map comps is using, so it appears on the site once the round is over.'),
        };
    }

    /**
     * Enter the demo if it is a run of this round's map in its own physics, and
     * if it was made after the ballot opened. Returns whether an entry was made.
     */
    private function enterIfItBelongs(UploadedDemo $demo, CompRound $round): bool
    {
        // Asked again here, not only at the top of apply(). One demo is one
        // entry, and the cheapest way to keep that true is to ask immediately
        // before writing rather than trusting a check made further up.
        if ($this->alreadyEntered($demo)) {
            return false;
        }

        $expected = $this->roundMapFor($demo, $round);

        if (! $expected) {
            return false;
        }

        // Entering needs a linked Q3DF.org profile, and this route enters
        // people without asking them - so it has to ask the same question the
        // upload form does, or the rule would hold on the form and not here,
        // which is the same as not holding. The demo stays hidden either way:
        // that is about the route being competed on, not about who uploaded.
        if ($this->intake->userRejectionReason($demo->user)) {
            Log::info("[comps] demo {$demo->id} not entered into round {$round->id}: uploader cannot enter");

            return false;
        }

        $physics = $expected->physics;

        // A demo carrying a validity note does not score, but it does get an
        // entry: refusing quietly would leave somebody staring at a run that
        // never appeared with nothing to read and nobody to ask. It lands as a
        // visible refusal with the cvar named, which is also what makes it
        // reportable to an admin.
        $flagged = $demo->status === self::FLAGGED;

        if (! $flagged && ! in_array($demo->status, SubmissionValidator::PARSED, true)) {
            return false;
        }

        if (! $this->madeInsideTheWindow($demo, $round)) {
            Log::info("[comps] demo {$demo->id} not entered into round {$round->id}: older than the ballot");

            return false;
        }

        $submission = CompSubmission::create([
            'comp_round_id' => $round->id,
            'user_id' => $demo->user_id,
            'mdd_id' => $demo->user?->mdd_id,
            'physics' => $flagged ? $physics : null,
            'time' => $flagged ? (int) ($demo->time_ms ?? 0) : 0,
            'uploaded_demo_id' => $demo->id,
            'is_highlight' => false,
            'status' => $flagged ? 'invalid' : 'pending',
            'invalid_reason' => $flagged ? $this->validityReason($demo) : null,
            // The person did not choose this, the site did. Same flag the
            // launcher's guesses carry, and it means the same thing: if this
            // turns out not to be a run of the map, undo it rather than leave
            // a rejection somebody has to understand.
            'auto_entered' => true,
        ]);

        if (! $flagged) {
            // The parse is already done, so there is nothing to wait for -
            // settle it now instead of leaving a pending row nobody will come
            // back to.
            $this->validator->settle($submission, $demo);
        }

        Log::info("[comps] demo {$demo->id} entered into round {$round->id} automatically"
            . ($flagged ? ' (invalid: validity note)' : ''));

        return true;
    }

    /**
     * Was the demo UPLOADED after this round's ballot opened?
     *
     * The parser is not the only thing that moves a demo's status. A mass
     * re-parse touches demos that have been on the site for years, and without
     * this the guard would hide them and enter them into a round they have
     * nothing to do with. Nothing uploaded before the ballot opened can be a
     * run made after it, so this costs nothing real.
     */
    private function arrivedInsideTheWindow(UploadedDemo $demo, CompRound $round): bool
    {
        return ! $round->voting_opens_at
            || ! $demo->created_at
            || $demo->created_at->greaterThanOrEqualTo($round->voting_opens_at);
    }

    /**
     * Was the run made after the ballot for this round opened?
     *
     * Two independent dates, and the EARLIEST one decides. A fresh file holding
     * an old run is an old run; there is no case where a run made this week
     * leaves a file dated last year.
     *
     * Neither date present means we accept it. The hash already stops a demo
     * that is on the site from being uploaded again, and refusing everything we
     * cannot date would refuse most online runs, which carry no date at all.
     */
    private function madeInsideTheWindow(UploadedDemo $demo, CompRound $round): bool
    {
        $opens = $round->voting_opens_at;

        if (! $opens) {
            return true;
        }

        // The upload time counts as a third date. It cannot make a demo look
        // newer than it is - a run cannot be uploaded before it was made - and
        // it is the only date an old demo with no metadata has at all.
        $dates = collect([$demo->record_date, $demo->client_file_mtime, $demo->created_at])
            ->filter()
            ->map(fn ($d) => $d instanceof Carbon ? $d : Carbon::parse($d));

        if ($dates->isEmpty()) {
            return true;
        }

        return $dates->min()->greaterThanOrEqualTo($opens);
    }

    /**
     * Hide the demo until `$until`, unless it is already hidden for longer.
     *
     * Never shortens an existing hold: a demo held for the round being played
     * must not have that cut back to a ballot deadline by a later pass.
     */
    private function holdUntil(UploadedDemo $demo, ?Carbon $until): void
    {
        if (! $until) {
            return;
        }

        if ($demo->comps_hidden_until && $demo->comps_hidden_until->greaterThanOrEqualTo($until)) {
            return;
        }

        // The model itself, not a query builder, so the global scope that hides
        // comps demos cannot exclude the row being updated.
        //
        // **Quietly**, and this is load-bearing. An ordinary save fires the
        // updated event, and the observer that lands back here decides whether
        // to act by asking `wasChanged('status')`. During the outer event the
        // original attributes have not been synced yet, so that inner save
        // reports the OUTER save's changes - status among them - and the guard
        // runs a second time, in the middle of its own first pass. It entered
        // the same demo twice before the first pass reached its own check.
        $demo->comps_hidden_until = $until;
        $demo->saveQuietly();
    }

    private function alreadyEntered(UploadedDemo $demo): bool
    {
        return CompSubmission::where('uploaded_demo_id', $demo->id)->exists();
    }

    /**
     * The round being played on this map, in any physics. Deliberately not
     * filtered by the demo's own physics - see the class docblock.
     */
    private function playedRoundFor(string $mapName): ?CompRound
    {
        return CompRound::where('status', 'active')
            ->where('ends_at', '>', now())
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->whereHas('maps.map', fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($mapName))]))
            ->with('maps.map')
            ->latest('starts_at')
            ->first();
    }

    private function ballotRoundFor(string $mapName): ?CompRound
    {
        return CompRound::where('status', 'voting')
            ->where('voting_closes_at', '>', now())
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->whereHas('candidates.map', fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($mapName))]))
            ->latest('starts_at')
            ->first();
    }

    /**
     * Is this round playing THIS map in THIS demo's physics?
     *
     * What decides whether a demo waits. A VQ3 run on the map being played in
     * CPM answers no: it cannot enter the round, nobody in the round is racing
     * it, and holding it back would only punish somebody for playing the other
     * half of the site.
     *
     * The physics comes from the demo when the parser could read it and from
     * the filename when it could not. Neither available means yes: that is the
     * demo we know least about, on the map being competed on, and it is also
     * the rarest.
     */
    private function playedInThisPhysics(CompRound $round, string $mapName, UploadedDemo $demo): bool
    {
        $physics = $this->physicsOf($demo) ?: $this->physicsFromFilename($demo->original_filename);

        if (! $physics) {
            return true;
        }

        $expected = $round->maps->firstWhere('physics', $physics);

        return $expected?->map !== null && $this->sameMap($mapName, $expected->map->name);
    }

    /** Let a demo out of a hold before its timestamp says so. */
    private function release(UploadedDemo $demo): void
    {
        if ($demo->comps_hidden_until === null) {
            return;
        }

        $demo->comps_hidden_until = null;

        // Quietly, for the same reason holdUntil is - see the note there.
        $demo->saveQuietly();
    }

    /**
     * The round map this demo is a run of, map and physics both, or null. What
     * decides an entry, as opposed to what decides a hold, which is the map on
     * its own.
     */
    private function roundMapFor(UploadedDemo $demo, CompRound $round): ?CompRoundMap
    {
        $physics = $this->physicsOf($demo);

        if (! $physics) {
            return null;
        }

        $expected = $round->maps->firstWhere('physics', $physics);

        return $expected?->map && $this->sameMap($demo->map_name, $expected->map->name)
            ? $expected
            : null;
    }

    /**
     * The map a filename claims, by the convention every defrag demo follows:
     * `mapname[physics]time(player).dm_68`.
     *
     * Only ever used for a demo the parser could not read. Everywhere else the
     * map comes out of the file itself, which is the whole point of deciding
     * here rather than in the launcher - a filename is a claim, and renaming a
     * file is not hard.
     */
    private function mapFromFilename(?string $filename): ?string
    {
        if (! $filename) {
            return null;
        }

        $basename = pathinfo($filename, PATHINFO_BASENAME);
        $map = trim(strtok($basename, '['));

        // No bracket means the file does not follow the convention, and the
        // whole filename is not a map name.
        return $map !== '' && $map !== $basename ? $map : null;
    }

    /**
     * The physics a filename claims: `map[df.cpm]12.345(nick).dm_68` says cpm.
     * The bracket carries the gametype first, and a fastcap adds a third part
     * (`[fc.cpm.3]`) that is not part of the physics.
     *
     * Same use as mapFromFilename - a demo the parser could not read - and the
     * same standing: a claim, not a fact.
     */
    private function physicsFromFilename(?string $filename): ?string
    {
        if (! $filename || ! preg_match('/\[[^.\]]+\.([^.\]]+)/', $filename, $m)) {
            return null;
        }

        $physics = strtolower(trim($m[1]));

        return in_array($physics, BallotResolver::PHYSICS, true) ? $physics : null;
    }

    /**
     * The parser writes physics with the mode attached - `CPM`, `VQ3.TR` - so
     * only the leading word is taken. Same reading SubmissionValidator does.
     */
    private function physicsOf(UploadedDemo $demo): ?string
    {
        $base = strtolower(strtok(trim((string) $demo->physics), '.'));

        return in_array($base, BallotResolver::PHYSICS, true) ? $base : null;
    }

    /**
     * Case-insensitive: `maps.name` carries capitals on a few hundred maps
     * where everything the parser writes is lowercase.
     */
    private function sameMap(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}
