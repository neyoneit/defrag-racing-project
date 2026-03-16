<?php

namespace App\Filament\Resources\BotVisitResource\Widgets;

use App\Models\BotVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class BotHitsChart extends ChartWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?string $maxHeight = '300px';
    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'hours';

    public function getHeading(): string
    {
        return match ($this->filter) {
            'minutes' => 'Bot Hits (Last 60 Minutes)',
            'hours' => 'Bot Hits (Last 24 Hours)',
            'days' => 'Bot Hits (Last 14 Days)',
            default => 'Bot Hits',
        };
    }

    protected function getFilters(): ?array
    {
        return [
            'minutes' => 'Last 60 minutes',
            'hours' => 'Last 24 hours',
            'days' => 'Last 14 days',
        ];
    }

    protected function getData(): array
    {
        return match ($this->filter) {
            'minutes' => $this->getMinuteData(),
            'hours' => $this->getHourData(),
            default => $this->getDayData(),
        };
    }

    private function getMinuteData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 59; $i >= 0; $i--) {
            $from = now()->subMinutes($i + 1);
            $to = now()->subMinutes($i);
            $labels[] = $to->format('H:i');
            $data[] = BotVisit::where('updated_at', '>=', $from)
                ->where('updated_at', '<', $to)
                ->sum('hits');
        }

        return $this->buildChart($labels, $data);
    }

    private function getHourData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 23; $i >= 0; $i--) {
            $from = now()->subHours($i + 1);
            $to = now()->subHours($i);
            $labels[] = $to->format('H:00');
            $data[] = BotVisit::where('updated_at', '>=', $from)
                ->where('updated_at', '<', $to)
                ->sum('hits');
        }

        return $this->buildChart($labels, $data);
    }

    private function getDayData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $hits = BotVisit::whereIn('date', $days)
            ->selectRaw('date, SUM(hits) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        return $this->buildChart(
            $days->map(fn ($d) => Carbon::parse($d)->format('d M'))->toArray(),
            $days->map(fn ($d) => $hits[$d] ?? 0)->values()->toArray(),
        );
    }

    private function buildChart(array $labels, array $data): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Bot hits',
                    'data' => $data,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
