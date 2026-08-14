<?php

namespace App\Filament\Pages;

use App\Models\CompDemoReport;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Services\Comps\UploadGuard;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * One round, seen player by player.
 *
 * The submissions table answers "what is in the standings". This answers the
 * question that actually comes up when somebody writes in: *what happened to
 * my demo*. So it starts from the person rather than from the entry, and it
 * shows the demos that produced no entry at all next to the ones that did -
 * a file the parser could not read, a run older than the round, a run in the
 * other physics. Those are invisible everywhere else precisely because they
 * are not entries, and they are what people ask about.
 *
 * Nothing here changes anything. It is a place to look before answering.
 */
class CompsRoundReview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Comps: round review';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.comps-round-review';

    public ?int $roundId = null;

    /** Hide the runs that are simply fine, which is most of them. */
    public bool $onlyProblems = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->roundId = CompRound::where('status', 'active')->latest('starts_at')->value('id')
            ?? CompRound::latest('starts_at')->value('id');
    }

    /** The rounds to choose from, newest first. */
    public function roundOptions(): array
    {
        return CompRound::with('comp')
            ->latest('starts_at')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (CompRound $r) => [$r->id => sprintf(
                '#%s %s · %s · %s',
                $r->comp?->number ?? '?',
                $r->category ?? '',
                $r->status,
                $r->starts_at?->format('d.m.Y') ?? '-'
            )])
            ->all();
    }

    public function round(): ?CompRound
    {
        return $this->roundId
            ? CompRound::with(['comp', 'maps.map'])->find($this->roundId)
            : null;
    }

    /**
     * Everything the round touched, grouped by the person it belongs to.
     *
     * Two sources, deliberately merged: the entries, and the demos of the
     * round's maps that never became entries. Reading only the first would
     * show a clean round every time somebody's demo silently failed to enter,
     * which is the exact case this page exists for.
     */
    public function players(): array
    {
        $round = $this->round();

        if (! $round) {
            return [];
        }

        $entries = CompSubmission::where('comp_round_id', $round->id)
            ->with(['user:id,name', 'demo' => fn ($q) => $q->withUnreleasedComps()])
            ->get();

        $enteredDemoIds = $entries->pluck('uploaded_demo_id')->filter()->all();

        $loose = $this->demosOfTheRoundsMaps($round)
            ->reject(fn (UploadedDemo $demo) => in_array($demo->id, $enteredDemoIds, true));

        $demoIds = array_merge($enteredDemoIds, $loose->pluck('id')->all());

        // Paired with a record: online through the demo's own record_id, and
        // offline through the record pointing back at the demo. Neither is a
        // verdict about the run - it is a nicety of the site - but a demo
        // nobody could pair is the one worth a second look.
        $offlinePaired = OfflineRecord::whereIn('demo_id', $demoIds)->pluck('demo_id')->all();

        $reports = CompDemoReport::whereIn('uploaded_demo_id', $demoIds)
            ->where('status', 'open')
            ->get()
            ->groupBy('uploaded_demo_id');

        $guard = app(UploadGuard::class);
        $rows = collect();

        foreach ($entries as $entry) {
            $demo = $entry->demo;

            $rows->push([
                'user' => $entry->user?->name ?? ('#' . $entry->user_id),
                'demo_id' => $entry->uploaded_demo_id,
                'filename' => $demo?->original_filename ?? '(the demo is gone)',
                'physics' => $entry->physics,
                'time' => $entry->time ?: null,
                'verdict' => match ($entry->status) {
                    'valid' => 'Counts',
                    'pending' => 'Being read',
                    default => 'Does not count',
                },
                'tone' => match ($entry->status) {
                    'valid' => 'success',
                    'pending' => 'gray',
                    default => 'danger',
                },
                'reason' => $entry->invalid_reason,
                'entered' => true,
                'auto' => (bool) $entry->auto_entered,
                'online' => (bool) $entry->is_online,
                'paired' => $entry->matched_record_id !== null
                    || $demo?->record_id !== null
                    || in_array($entry->uploaded_demo_id, $offlinePaired, true),
                'reports' => $reports->get($entry->uploaded_demo_id)?->count() ?? 0,
                'at' => $demo?->created_at,
            ]);
        }

        foreach ($loose as $demo) {
            $kind = $this->looseKind($guard, $round, $demo);

            $rows->push([
                'user' => $demo->user?->name ?? ('#' . $demo->user_id),
                'demo_id' => $demo->id,
                'filename' => $demo->original_filename,
                'physics' => $demo->physics,
                'time' => $demo->time_ms ?: null,
                'verdict' => match ($kind) {
                    'unreadable' => 'Could not be read',
                    'too_old' => 'Older than the round',
                    'other_physics' => 'The other physics',
                    'ballot' => 'Waiting on the vote',
                    default => 'Not entered',
                },
                'tone' => $kind === 'unreadable' ? 'danger' : 'warning',
                'reason' => $kind === 'not_entered' ? null : $guard->noticeText($kind),
                'entered' => false,
                'auto' => false,
                'online' => ! $demo->is_offline,
                'paired' => $demo->record_id !== null || in_array($demo->id, $offlinePaired, true),
                'reports' => $reports->get($demo->id)?->count() ?? 0,
                'at' => $demo->created_at,
            ]);
        }

        return $rows
            ->when($this->onlyProblems, fn (Collection $c) => $c->filter(
                fn (array $r) => $r['tone'] !== 'success' || ! $r['paired'] || $r['reports'] > 0
            ))
            ->groupBy('user')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $rows, string $user) => [
                'user' => $user,
                'rows' => $rows->sortBy('at')->values()->all(),
                'problems' => $rows->filter(fn (array $r) => $r['tone'] !== 'success')->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Every demo of the round's maps that arrived while the round was a thing,
     * entered or not.
     *
     * By upload time rather than by the hold, because a finished round has
     * released its holds and this page is most useful after the fact. The
     * filename is matched as well as the parsed map name: a demo the parser
     * could not read has no map name at all, and those are the ones somebody
     * is most likely to be asking about.
     */
    private function demosOfTheRoundsMaps(CompRound $round): Collection
    {
        $maps = $round->maps
            ->map(fn ($m) => mb_strtolower(trim((string) $m->map?->name)))
            ->filter()
            ->unique()
            ->values();

        if ($maps->isEmpty() || ! $round->voting_opens_at) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, $maps->count(), '?'));

        return UploadedDemo::withUnreleasedComps()
            ->where('created_at', '>=', $round->voting_opens_at)
            ->where('created_at', '<=', $round->ends_at ?? now())
            ->where(function ($q) use ($maps, $placeholders) {
                $q->whereRaw("LOWER(map_name) IN ($placeholders)", $maps->all());

                foreach ($maps as $name) {
                    $q->orWhere('original_filename', 'like', addcslashes($name, '%_\\') . '[%');
                }
            })
            ->with('user:id,name')
            ->limit(500)
            ->get();
    }

    /**
     * Why a demo did not enter.
     *
     * The guard answers this against the rounds as they stand right now, which
     * is the truth for the round being played and nonsense for one that ended
     * three weeks ago. So an old round only says "not entered", except for a
     * file the parser could not read - that one is a fact about the file and
     * stays true forever.
     */
    private function looseKind(UploadGuard $guard, CompRound $round, UploadedDemo $demo): string
    {
        if ($demo->status === 'failed') {
            return 'unreadable';
        }

        return $round->status === 'active' ? $guard->noticeKind($demo) : 'not_entered';
    }

    /** m:ss.mmm, the way every other comps screen prints a time. */
    public function formatTime(?int $ms): string
    {
        if (! $ms) {
            return '-';
        }

        return gmdate('i:s', intdiv($ms, 1000)) . '.' . str_pad((string) ($ms % 1000), 3, '0', STR_PAD_LEFT);
    }
}
