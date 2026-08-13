<?php

namespace App\Filament\Pages;

use App\Models\CompCandidate;
use App\Models\CompRound;
use App\Models\Map;
use App\Models\SiteSetting;
use App\Services\Comps\CandidateSelector;
use App\Services\Comps\CompScheduler;
use App\Services\Comps\CompSettings;
use App\Services\Comps\MapClassifier;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The one screen comps is run from: the schedule, the rules, the ballot that
 * is open, and the map that is being played.
 *
 * It is deliberately not a form over a table. Almost everything here happens
 * on its own - the point of comps is that nobody has to remember to start it -
 * so what an admin needs is to see what the machinery decided and be able to
 * overrule one map when the draw produces something silly.
 */
class CompsControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Comps: control';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.comps-control';

    public bool $enabled = false;

    public string $timezone = 'Europe/Prague';

    public int $startDow = 0;

    public string $startTime = '20:00';

    public int $votingLeadHours = 24;

    public int $poolSize = 5;

    public int $prizeEur = 5;

    public int $prizeFundedWeeks = 5;

    public bool $betaNotice = true;

    /** Candidate being swapped, and what it is being swapped for. */
    public ?int $swapCandidateId = null;

    public string $swapSearch = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $s = app(CompSettings::class);

        $this->enabled = $s->weeklyEnabled();
        $this->timezone = $s->timezone()->getName();
        $this->startDow = $s->startDayOfWeek();
        $this->startTime = $s->startTime();
        $this->votingLeadHours = $s->votingLeadHours();
        $this->poolSize = $s->poolSize();
        $this->prizeEur = $s->prizeEur();
        $this->prizeFundedWeeks = $s->prizeFundedWeeks();
        $this->betaNotice = $s->betaNotice();
    }

    public function saveSettings(): void
    {
        $this->validate([
            'timezone' => ['required', 'timezone'],
            'startDow' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'votingLeadHours' => ['required', 'integer', 'between:0,168'],
            'poolSize' => ['required', 'integer', 'between:2,20'],
            'prizeEur' => ['required', 'integer', 'between:0,10000'],
            'prizeFundedWeeks' => ['required', 'integer', 'between:0,520'],
        ]);

        SiteSetting::set(CompSettings::KEY_ENABLED, $this->enabled ? '1' : '0');
        SiteSetting::set(CompSettings::KEY_TIMEZONE, $this->timezone);
        SiteSetting::set(CompSettings::KEY_START_DOW, (string) $this->startDow);
        SiteSetting::set(CompSettings::KEY_START_TIME, $this->startTime);
        SiteSetting::set(CompSettings::KEY_VOTING_LEAD_HOURS, (string) $this->votingLeadHours);
        SiteSetting::set(CompSettings::KEY_POOL_SIZE, (string) $this->poolSize);
        SiteSetting::set(CompSettings::KEY_PRIZE_EUR, (string) $this->prizeEur);
        SiteSetting::set(CompSettings::KEY_PRIZE_FUNDED_WEEKS, (string) $this->prizeFundedWeeks);
        SiteSetting::set(CompSettings::KEY_BETA_NOTICE, $this->betaNotice ? '1' : '0');

        Notification::make()
            ->title('Saved')
            ->body('Times change the next round to be created, not one already scheduled.')
            ->success()
            ->send();
    }

    /** Run the scheduler by hand rather than waiting for the next minute. */
    public function runTick(): void
    {
        $done = app(CompScheduler::class)->tick();

        Notification::make()
            ->title($done ? 'Scheduler ran' : 'Nothing was due')
            ->body($done ? implode("\n", $done) : null)
            ->success()
            ->send();
    }

    /**
     * Replace one map on an open ballot. Redrawn from the same pool, so it
     * still obeys the category, the never-repeat rule and the record filter -
     * an admin swapping a map should not be able to smuggle in something the
     * rules would have refused.
     */
    public function redrawCandidate(int $candidateId): void
    {
        $candidate = CompCandidate::with('round')->find($candidateId);

        if (! $candidate || ! $candidate->round?->isVoting()) {
            Notification::make()->title('That ballot is closed')->danger()->send();

            return;
        }

        $round = $candidate->round;
        $onBallot = $round->candidates()->pluck('map_id')->all();

        $pool = collect(app(CandidateSelector::class)->eligible($round->category, $round->weapon))
            ->reject(fn ($m) => in_array($m['id'], $onBallot, true));

        if ($pool->isEmpty()) {
            Notification::make()->title('Nothing left to draw from')->danger()->send();

            return;
        }

        $replacement = $pool->random();

        $candidate->update([
            'map_id' => $replacement['id'],
            'blocked_physics' => $replacement['blocked_physics'] ?? null,
            'votes_cpm' => 0,
            'votes_vq3' => 0,
        ]);

        // The votes belonged to the map that just left, not to its slot.
        $candidate->votes()->delete();

        Notification::make()
            ->title("Swapped in {$replacement['name']}")
            ->body('Votes cast for the old map were removed with it.')
            ->success()
            ->send();
    }

    public function currentRounds(): array
    {
        return [
            'playing' => CompRound::where('status', 'active')
                ->with(['comp', 'maps.map'])
                ->first(),
            'voting' => CompRound::whereIn('status', ['voting', 'locked'])
                ->with(['comp', 'candidates.map', 'maps.map'])
                ->orderBy('starts_at')
                ->first(),
        ];
    }

    /** What the next few weeks will be, so the rotation is visible ahead. */
    public function upcomingCategories(int $weeks = 6): array
    {
        $selector = app(CandidateSelector::class);
        $latest = (int) \App\Models\Comp::weekly()->max('number');

        $out = [];

        for ($i = 1; $i <= $weeks; $i++) {
            $number = $latest + $i;
            $out[] = [
                'number' => $number,
                'category' => $selector->categoryForWeekly($number),
            ];
        }

        return $out;
    }

    /** Pool sizes, so an empty category is visible before it bites. */
    public function poolSizes(): array
    {
        $selector = app(CandidateSelector::class);

        $out = [];

        foreach ([MapClassifier::STRAFE, MapClassifier::WEAPON, MapClassifier::COMBO] as $category) {
            $out[$category] = count($selector->eligible($category));
        }

        return $out;
    }

    public function weekdays(): array
    {
        return [
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
        ];
    }
}
