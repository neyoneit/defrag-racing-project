<?php

namespace App\Services;

use App\Models\RecordFlag;
use App\Models\ServerdemoValidationCase;
use App\Models\ServerdemoValidationComment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Groups reports by player and walks the resulting case along the ladder.
 *
 * Two rules shape everything here.
 *
 * A report goes nowhere until an admin has cleared it as not-false. Without
 * that, one person with a grudge could push demos in front of moderators just
 * by reporting a lot of runs.
 *
 * And a cleared report joins the OPEN CASE for that player rather than
 * starting its own. Somebody who cheats does it on many maps; reviewing that
 * as many separate rows is more work and worse work, because the pattern
 * across the runs is usually the evidence.
 *
 * The ladder only ever widens - one validator, a second, everyone, the admin.
 * Each step means the last person said they were unsure, so handing it back
 * to them would loop forever.
 */
class ServerdemoValidationService
{
    /**
     * Everyone who may validate. Admins hold every permission implicitly, so
     * they are filtered back out: the admin is the last rung of the ladder,
     * not a candidate for the first.
     */
    public function validators(): Collection
    {
        return User::query()
            ->where('is_moderator', true)
            ->get()
            ->filter(fn (User $user) => $user->hasModeratorPermission('serverdemo_validation') && ! $user->isAdmin())
            ->values();
    }

    /**
     * Admin says this is not a false report. Only then does it join a case -
     * and only if enough different people reported it.
     */
    public function clear(RecordFlag $flag, User $admin, ?string $note = null): ?ServerdemoValidationCase
    {
        $flag->admin_cleared_at = now();
        $flag->admin_cleared_by = $admin->id;
        $flag->save();

        if (! $flag->hasEnoughReports()) {
            return null;
        }

        return $this->attachToCase($flag, $admin, $note);
    }

    /**
     * Put a cleared report into the open case for that player, opening one if
     * there is none. A case that has been closed is left alone - a new report
     * after a verdict deserves a fresh look, not a reopened argument.
     */
    public function attachToCase(RecordFlag $flag, ?User $actor = null, ?string $note = null): ?ServerdemoValidationCase
    {
        $subject = $this->subjectOf($flag);

        if ($subject === null) {
            return null;
        }

        $kind = $flag->demo_id
            ? ServerdemoValidationCase::KIND_PUBLIC_DEMO
            : ServerdemoValidationCase::KIND_SERVERDEMO;

        $case = ServerdemoValidationCase::query()
            ->open()
            ->where('kind', $kind)
            ->where(function ($query) use ($subject) {
                if ($subject['mdd_id']) {
                    $query->where('subject_mdd_id', $subject['mdd_id']);
                } elseif ($subject['user_id']) {
                    $query->where('subject_user_id', $subject['user_id']);
                } else {
                    $query->where('subject_name', $subject['name']);
                }
            })
            ->first();

        $isNew = $case === null;

        if ($isNew) {
            $case = ServerdemoValidationCase::create([
                'subject_mdd_id' => $subject['mdd_id'],
                'subject_user_id' => $subject['user_id'],
                'subject_name' => $subject['name'],
                'kind' => $kind,
            ]);
        }

        $flag->validation_case_id = $case->id;
        $flag->save();

        if ($isNew) {
            $this->log($case, $actor, 'opened', $note ?: 'Case opened.');
            $this->assignNext($case, $actor);
        } else {
            $this->log($case, $actor, 'added', 'Another cleared report was added to this case.');
        }

        return $case;
    }

    /**
     * Who a report is about. MDD id first - it comes from the game and
     * survives nickname changes.
     */
    private function subjectOf(RecordFlag $flag): ?array
    {
        if ($flag->demo_id && $flag->demo) {
            return [
                'mdd_id' => null,
                'user_id' => $flag->demo->user_id,
                'name' => $flag->demo->player_name ?: $flag->demo->q3df_login_name,
            ];
        }

        if ($flag->record) {
            return [
                'mdd_id' => $flag->record->mdd_id ?: null,
                'user_id' => $flag->record->user_id,
                'name' => $flag->record->name,
            ];
        }

        return null;
    }

    /**
     * Hand the case to a validator who has not held it yet.
     *
     * Random rather than a rotation: a predictable order tells a reported
     * player who is about to look at their runs.
     */
    public function assignNext(ServerdemoValidationCase $case, ?User $actor = null): ?User
    {
        $seen = collect($case->validators_seen ?? []);
        $candidate = $this->validators()
            ->reject(fn (User $user) => $seen->contains($user->id))
            ->shuffle()
            ->first();

        // Nobody left who has not already passed - that is what the
        // everyone-at-once stage is for.
        if (! $candidate) {
            $this->escalateToAll($case, $actor);
            return null;
        }

        $case->assigned_to_user_id = $candidate->id;
        $case->assigned_at = now();
        $case->validation_stage = $seen->isEmpty()
            ? 'assigned'
            : 'second_opinion';
        $case->validators_seen = $seen->push($candidate->id)->unique()->values()->all();
        $case->save();

        $this->log($case, $actor, 'assigned', "Assigned to {$candidate->name}.");

        return $candidate;
    }

    /**
     * The current validator is unsure and passes it on. The note is required:
     * the next person needs to know what has already been looked at.
     */
    public function handOver(ServerdemoValidationCase $case, User $from, string $note): ?User
    {
        $this->log($case, $from, 'handover', $note);

        return $this->assignNext($case, $from);
    }

    /** Open it to every validator at once; nobody owns it any more. */
    public function escalateToAll(ServerdemoValidationCase $case, ?User $actor = null, ?string $note = null): void
    {
        $case->assigned_to_user_id = null;
        $case->validation_stage = 'all_validators';
        $case->save();

        $this->log($case, $actor, 'escalated_all', $note ?: 'Opened to all validators - agree on a verdict here.');
    }

    /** Last rung: the validators could not agree and the admin decides. */
    public function callAdmin(ServerdemoValidationCase $case, User $actor, ?string $note = null): void
    {
        $case->assigned_to_user_id = null;
        $case->validation_stage = 'admin';
        $case->save();

        $this->log($case, $actor, 'called_admin', $note ?: 'Escalated to the admin.');
    }

    /**
     * Close the case. This does not touch the reports' own `status`, which
     * stays the admin's decision on each report.
     */
    public function close(ServerdemoValidationCase $case, User $actor, string $outcome, ?string $note = null): void
    {
        $case->validation_outcome = $outcome;
        $case->validation_closed_at = now();
        $case->assigned_to_user_id = null;
        $case->save();

        $this->log($case, $actor, 'closed', $note ?: "Case closed as {$outcome}.");
    }

    public function log(ServerdemoValidationCase $case, ?User $user, ?string $event, string $body): void
    {
        ServerdemoValidationComment::create([
            'validation_case_id' => $case->id,
            'user_id' => $user?->id,
            'body' => $body,
            'event' => $event,
        ]);
    }
}
