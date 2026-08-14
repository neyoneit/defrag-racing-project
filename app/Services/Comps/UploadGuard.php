<?php

namespace App\Services\Comps;

use App\Models\Comp;
use App\Models\CompRound;
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
 * **What gets held is decided by the map alone, not map plus physics.** A VQ3
 * run on the map CPM is playing this week still shows the route, and the route
 * is most of what a competitor would want to see.
 *
 * **What gets entered is decided by map and physics together**, because that is
 * what the round is scored on.
 */
class UploadGuard
{
    /** Parser outcome for a demo it read, but which carries a cvar note. */
    private const FLAGGED = 'failed-validity';

    public function __construct(private SubmissionValidator $validator)
    {
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

        if (! $demo->map_name) {
            return;
        }

        if ($round = $this->playedRoundFor($demo->map_name)) {
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
        if ($round = $this->ballotRoundFor($demo->map_name)) {
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
     * Enter the demo if it is a run of this round's map in its own physics, and
     * if it was made after the ballot opened. Returns whether an entry was made.
     */
    private function enterIfItBelongs(UploadedDemo $demo, CompRound $round): bool
    {
        $physics = $this->physicsOf($demo);

        if (! $physics) {
            return false;
        }

        $expected = $round->maps->firstWhere('physics', $physics);

        if (! $expected?->map || ! $this->sameMap($demo->map_name, $expected->map->name)) {
            return false;
        }

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

        // Updating the model itself, not through a query builder, so the global
        // scope that hides comps demos cannot exclude the row we are updating.
        $demo->update(['comps_hidden_until' => $until]);
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
