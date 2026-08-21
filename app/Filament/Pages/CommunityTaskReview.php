<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use App\Models\CommunityTaskVote;
use App\Models\Record;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Services\DemoAssignmentContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * One screen for one undecided demo.
 *
 * These are the demos the public Community Tasks flow could NOT settle -
 * somebody voted "not sure", or two people disagreed. They are the hard ones
 * by definition, and the admin had less to go on than the players did: a table
 * with one row per VOTE rather than per demo, a modal listing the map's
 * records, a second modal listing the votes, and an Assign box asking for a
 * record id as a number. The id was in the first modal, which had to be closed
 * to open the third. Deciding meant copying a number between two dialogs.
 *
 * So it is a page, and everything needed to answer the question is on it at
 * once: what the demo says about itself, what the site currently thinks, who
 * voted and how, and every record on the map with its distance from the demo's
 * time and whether its holder has ever gone by the demo's player name. Picking
 * one is a click on its row.
 *
 * The candidate list comes from DemoAssignmentContext, the same object that
 * builds the public task, so an admin never sees less than a player did.
 */
class CommunityTaskReview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Review demo';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 11;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.community-task-review';

    public ?int $demoId = null;

    public ?UploadedDemo $demo = null;

    public array $records = [];

    public array $closest = [];

    public array $votes = [];

    public array $voteCounts = [];

    public ?array $current = null;

    public int $queueRemaining = 0;

    public ?int $nextDemoId = null;

    public string $adminNotes = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->is_moderator;
    }

    public function mount(): void
    {
        $this->demoId = (int) request()->query('demo');

        $this->load();
    }

    public function getTitle(): string
    {
        return $this->demo
            ? "Demo #{$this->demo->id} on {$this->demo->map_name}"
            : 'Review demo';
    }

    /**
     * Everything the page shows, rebuilt from the database.
     *
     * Called on mount and after every action rather than patching the arrays
     * in place: an assignment changes the demo, the votes and the queue at
     * once, and a screen that updates two of those three is worse than one
     * that reloads.
     */
    private function load(): void
    {
        $this->demo = UploadedDemo::with(['user', 'record', 'suggestedUser', 'assignedUser'])
            ->find($this->demoId);

        $this->records = [];
        $this->closest = [];
        $this->votes = [];
        $this->voteCounts = [];
        $this->current = null;

        if (! $this->demo) {
            return;
        }

        $context = app(DemoAssignmentContext::class)->for($this->demo);

        if ($context) {
            $this->records = $context['records'];
            $this->closest = $context['closest'];
        }

        $this->votes = CommunityTaskVote::where('demo_id', $this->demo->id)
            ->with(['user:id,name', 'selectedRecord:id,rank,name,time'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (CommunityTaskVote $v) => [
                'id' => $v->id,
                'user' => $v->user ? UserResource::q3tohtml($v->user->name) : 'Unknown',
                'type' => $v->vote_type,
                'task' => $v->task_type,
                'record_id' => $v->selected_record_id,
                'record_label' => $v->selectedRecord
                    ? "#{$v->selectedRecord->rank} " . $v->selectedRecord->name
                    : null,
                'status' => $v->consensus_status,
                'when' => $v->created_at?->diffForHumans(),
            ])
            ->toArray();

        $this->voteCounts = CommunityTaskVote::where('demo_id', $this->demo->id)
            ->select('vote_type', DB::raw('count(*) as cnt'))
            ->groupBy('vote_type')
            ->pluck('cnt', 'vote_type')
            ->toArray();

        // What the site believes right now, so "is this already right?" is a
        // question the page answers rather than one the admin has to go and
        // look up. It is also the single most common answer: most of these
        // are flagged because somebody was unsure, not because it was wrong.
        if ($this->demo->record_id && $this->demo->record) {
            $r = $this->demo->record;

            $this->current = [
                'id' => $r->id,
                'rank' => $r->rank,
                'player_name' => $r->user?->name ?? $r->name,
                'time' => $r->time,
                'time_diff' => abs($r->time - (int) $this->demo->time_ms),
            ];
        }

        $queue = $this->queue();

        $this->queueRemaining = count($queue);
        $this->nextDemoId = collect($queue)->firstWhere(fn ($id) => $id !== $this->demo->id);
    }

    /** Demo ids still waiting on a human, oldest flag first. */
    private function queue(): array
    {
        return CommunityTaskVote::where('consensus_status', 'needs_review')
            ->orderBy('created_at')
            ->pluck('demo_id')
            ->unique()
            ->values()
            ->all();
    }

    public function assign(int $recordId): void
    {
        $record = Record::find($recordId);

        if (! $this->demo || ! $record) {
            Notification::make()->title('Demo or record is gone')->danger()->send();

            return;
        }

        // An offline record is this demo's own standalone row on the map page.
        // Once it belongs to a real record that row is a duplicate of it.
        if ($this->demo->offlineRecord) {
            $this->demo->offlineRecord->delete();
        }

        $this->demo->update([
            'record_id' => $record->id,
            'status' => 'assigned',
            'manually_assigned' => true,
        ]);

        RenderedVideo::where('demo_id', $this->demo->id)->update(['record_id' => $record->id]);

        $this->settleVotes('resolved', $this->adminNotes ?: "Assigned to record #{$record->id} by admin");

        Notification::make()
            ->title('Assigned')
            ->body("Demo #{$this->demo->id} now belongs to record #{$record->id}.")
            ->success()
            ->send();

        $this->load();
    }

    /** The demo is where it belongs; the flag was the doubt, not a mistake. */
    public function markCorrect(): void
    {
        $this->settleVotes('archived', $this->adminNotes ?: 'Confirmed correct by admin');

        Notification::make()->title('Kept as it is')->success()->send();

        $this->load();
    }

    /**
     * Take the demo off whatever it is on. For a run that was assigned to the
     * wrong record and has no right one on the board - the alternative was
     * leaving it wrong because there was nothing to move it to.
     */
    public function unassign(): void
    {
        if (! $this->demo) {
            return;
        }

        $this->demo->update([
            'record_id' => null,
            'status' => 'processed',
            'manually_assigned' => false,
        ]);

        RenderedVideo::where('demo_id', $this->demo->id)->update(['record_id' => null]);

        $this->settleVotes('resolved', $this->adminNotes ?: 'Unassigned by admin');

        Notification::make()->title('Unassigned')->success()->send();

        $this->load();
    }

    /** Not answerable, and not worth holding the queue open for. */
    public function dismiss(): void
    {
        $this->settleVotes('archived', $this->adminNotes ?: 'Dismissed by admin');

        Notification::make()->title('Dismissed')->info()->send();

        $this->load();
    }

    private function settleVotes(string $status, string $note): void
    {
        CommunityTaskVote::where('demo_id', $this->demo->id)
            ->where(fn ($q) => $q->where('consensus_status', 'needs_review')->orWhereNull('consensus_status'))
            ->update([
                'consensus_status' => $status,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'admin_notes' => $note,
            ]);

        $this->adminNotes = '';
    }

    /** Format milliseconds the way the rest of the site does. */
    public static function time(?int $ms): string
    {
        if (! $ms) {
            return '-';
        }

        return sprintf('%02d:%02d.%03d', intdiv($ms, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
    }
}
