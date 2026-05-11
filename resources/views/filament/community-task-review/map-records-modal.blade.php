@php
    use App\Filament\Resources\UserResource;

    $demoTime = $demoTimeMs ?? 0;

    $fmtTime = function (?int $ms): string {
        if ($ms === null) return '—';
        $m = intdiv($ms, 60000);
        $s = intdiv($ms % 60000, 1000);
        $rest = $ms % 1000;
        return sprintf('%02d:%02d.%03d', $m, $s, $rest);
    };
@endphp

<div class="space-y-3 text-sm">
    {{-- Header strip. Avoids dark:-prefixed classes since they don't
         consistently fire inside Filament's teleported modal — instead
         we use translucent overlays (bg-black/20, ring-white/10) and
         opacity-based labels that inherit the panel's base text color. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs rounded-lg ring-1 ring-white/10 bg-black/20 p-3">
        <div>
            <div class="uppercase tracking-wide opacity-60 mb-0.5">Map</div>
            <div class="font-mono">{{ $mapName }}</div>
        </div>
        <div>
            <div class="uppercase tracking-wide opacity-60 mb-0.5">Physics / Gametype</div>
            <div class="font-mono">{{ strtoupper($physics ?? '?') }} / {{ $gametype ?? '?' }}</div>
        </div>
        <div>
            <div class="uppercase tracking-wide opacity-60 mb-0.5">Demo player</div>
            <div class="font-mono">{!! $demoPlayerHtml !!}</div>
        </div>
        <div>
            <div class="uppercase tracking-wide opacity-60 mb-0.5">Demo time</div>
            <div class="font-mono">{{ $fmtTime($demoTime) }}</div>
        </div>
    </div>

    @if ($records->isEmpty())
        <div class="rounded-lg ring-1 ring-yellow-500/30 bg-yellow-500/5 p-4 text-yellow-300 text-sm">
            No records found for <code>{{ $mapName }}</code> (gametype <code>{{ $gametype }}</code>).
            If the public map page does have records, the demo's physics/mode might not match — check the demo metadata.
        </div>
    @else
        <div class="text-xs opacity-70 mb-1">
            {{ $records->count() }} record(s) on this map. Rows closest to the demo time are highlighted yellow.
            Click a Record ID to copy it, then paste into <em>Assign</em>.
        </div>
        <div class="overflow-x-auto rounded-lg ring-1 ring-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide opacity-70">
                    <tr>
                        <th class="px-3 py-2 text-left">Rank</th>
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-right">Time</th>
                        <th class="px-3 py-2 text-right">Δ vs demo</th>
                        <th class="px-3 py-2 text-left">Date set</th>
                        <th class="px-3 py-2 text-center">Assets</th>
                        <th class="px-3 py-2 text-right">Record ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($records as $r)
                        @php
                            $diffMs    = $r->time - $demoTime;
                            $absMs     = abs($diffMs);
                            $threshold = max(200, (int) round($demoTime * 0.01));
                            $highlight = $absMs <= $threshold;
                            $diffSign  = $diffMs > 0 ? '+' : ($diffMs < 0 ? '−' : '');
                            $diffColor = $absMs <= $threshold
                                ? 'text-yellow-400 font-semibold'
                                : ($absMs <= max(1000, (int) round($demoTime * 0.05)) ? 'text-orange-400' : 'opacity-60');

                            $playerName = $r->user?->name ?? $r->name ?? '?';
                            $playerHtml = UserResource::q3tohtml($playerName);

                            $hasDemo  = $r->uploadedDemos && $r->uploadedDemos->count() > 0;
                            $hasVideo = $r->renderedVideos && $r->renderedVideos->count() > 0;
                        @endphp
                        <tr class="{{ $highlight ? 'bg-yellow-500/10' : '' }}">
                            <td class="px-3 py-1.5 font-mono text-xs">#{{ $r->rank ?? '—' }}</td>
                            <td class="px-3 py-1.5">
                                <div class="font-mono text-xs">{!! $playerHtml !!}</div>
                                <div class="text-[10px] opacity-50">mdd_id: {{ $r->mdd_id ?? '—' }}</div>
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono text-xs">{{ $fmtTime($r->time) }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-xs {{ $diffColor }}">{{ $diffSign }}{{ $fmtTime($absMs) }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs opacity-60">{{ optional($r->date_set)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-center text-xs">
                                @if ($hasDemo)<span class="inline-block px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-300 mr-1" title="Has demo">.dm</span>@endif
                                @if ($hasVideo)<span class="inline-block px-1.5 py-0.5 rounded bg-red-500/20 text-red-300" title="Has rendered video">▶</span>@endif
                                @if (!$hasDemo && !$hasVideo)<span class="opacity-50">—</span>@endif
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono text-xs">
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 select-all cursor-pointer" onclick="navigator.clipboard?.writeText('{{ $r->id }}')">{{ $r->id }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
