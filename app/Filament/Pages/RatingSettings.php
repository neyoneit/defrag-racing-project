<?php

namespace App\Filament\Pages;

use App\Models\RatingSetting;
use App\Http\Controllers\RankingController;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RatingSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Rating Settings';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.rating-settings';

    // Individual setting properties
    public string $cfg_a = '';
    public string $cfg_b = '';
    public string $cfg_m = '';
    public string $cfg_v = '';
    public string $cfg_q = '';
    public string $cfg_d = '';
    public string $mult_l = '';
    public string $mult_n = '';
    public string $min_map_players = '';
    public string $min_top1_time = '';
    public string $max_tied_wr_players = '';
    public string $rank_exponent = '';
    public string $min_total_records = '';

    // Recalc controls
    public array $selectedPhysics = ['vq3', 'cpm'];
    public array $selectedCategories = ['overall'];
    public string $recalcOutput = '';

    private array $settingKeys = [
        'cfg_a', 'cfg_b', 'cfg_m', 'cfg_v', 'cfg_q', 'cfg_d',
        'mult_l', 'mult_n',
        'min_map_players', 'min_top1_time', 'max_tied_wr_players',
        'rank_exponent', 'min_total_records',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $all = RatingSetting::allAsArray();
        foreach ($this->settingKeys as $key) {
            $this->{$key} = $all[$key] ?? '';
        }
    }

    public function saveSettings(): void
    {
        foreach ($this->settingKeys as $key) {
            // Replace comma with dot, strip anything that's not digit/dot/minus
            $this->{$key} = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $this->{$key}));

            if (!is_numeric($this->{$key})) {
                Notification::make()
                    ->title("Invalid value for {$key}: must be a number")
                    ->danger()
                    ->send();
                return;
            }
        }

        foreach ($this->settingKeys as $key) {
            RatingSetting::set($key, $this->{$key});
        }

        Cache::forget('rating_settings');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function runRecalc(): void
    {
        if (empty($this->selectedPhysics) || empty($this->selectedCategories)) {
            Notification::make()
                ->title('Select at least one physics and one category')
                ->danger()
                ->send();
            return;
        }

        $this->recalcOutput = '';
        $output = '';

        foreach ($this->selectedPhysics as $physics) {
            foreach ($this->selectedCategories as $category) {
                Artisan::call('ratings:calculate', [
                    '--physics' => $physics,
                    '--category' => $category,
                ]);

                $cmdOutput = Artisan::output();
                $output .= "=== {$physics} / {$category} ===\n{$cmdOutput}\n";
            }
        }

        $this->recalcOutput = $output;

        Notification::make()
            ->title('Recalculation complete')
            ->success()
            ->send();
    }

    public function refreshCache(): void
    {
        $flushed = 0;
        $rankingTypes = ['active_players', 'all_players'];
        $categories = ['overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe', 'lg'];

        foreach (['vq3', 'cpm'] as $physics) {
            foreach ($categories as $category) {
                foreach ($rankingTypes as $rankingtype) {
                    for ($page = 1; $page <= 50; $page++) {
                        Cache::forget("ranking:{$physics}:run:{$rankingtype}:{$category}:{$page}");
                        $flushed++;
                    }
                }
            }
        }

        Cache::forget('ranking:last_recalculation');
        Cache::forget('rating_settings');

        // Rebuild first pages
        $controller = new RankingController();
        $rebuilt = 0;
        foreach (['vq3', 'cpm'] as $physics) {
            foreach ($categories as $category) {
                foreach ($rankingTypes as $rankingtype) {
                    for ($page = 1; $page <= RankingController::PREBUILT_PAGES; $page++) {
                        $cacheKey = "ranking:{$physics}:run:{$rankingtype}:{$category}:{$page}";
                        $data = $controller->fetchPageFromDb($physics, 'run', $rankingtype, $category, $page);
                        Cache::put($cacheKey, $data, RankingController::CACHE_TTL);
                        $rebuilt++;
                    }
                }
            }
        }

        Notification::make()
            ->title('Cache refreshed')
            ->body("Flushed {$flushed} keys, rebuilt {$rebuilt} pages.")
            ->success()
            ->send();
    }

    public function getSettingGroups(): array
    {
        return [
            'Logistic Curve (Score)' => [
                'cfg_a' => 'Amplitude (A)',
                'cfg_b' => 'Steepness (B)',
                'cfg_m' => 'Midpoint offset (M)',
                'cfg_v' => 'Asymmetry / Richards (V)',
                'cfg_q' => 'Q parameter',
            ],
            'Hill Function (Map Multiplier)' => [
                'mult_l' => 'Max multiplier (L)',
                'mult_n' => 'Steepness (n)',
            ],
            'Weighted Average' => [
                'cfg_d' => 'Exponential decay (lower = more equal weighting)',
            ],
            'Map Eligibility' => [
                'min_map_players' => 'Min players for map to be ranked',
                'min_top1_time' => 'Min WR time in ms (filter trivial maps)',
                'max_tied_wr_players' => 'Max players sharing WR (filter free WR)',
            ],
            'Player Penalty' => [
                'min_total_records' => 'Min records before penalty applies',
            ],
            'Rank Multiplier' => [
                'rank_exponent' => 'Exponent for rank-based multiplier',
            ],
        ];
    }
}
