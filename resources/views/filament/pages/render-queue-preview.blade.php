<x-filament-panels::page>
    {{-- Stats bar --}}
    @php $stats = $this->getCurrentQueueStats(); @endphp
    <div style="display: flex; gap: 16px; margin-bottom: 16px;">
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 12px 20px; text-align: center;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase;">Pending</div>
            <div style="font-size: 24px; font-weight: 900; color: #f59e0b;">{{ $stats['pending'] }}</div>
        </div>
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 12px 20px; text-align: center;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase;">Rendering</div>
            <div style="font-size: 24px; font-weight: 900; color: #3b82f6;">{{ $stats['rendering'] }}</div>
        </div>
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 12px 20px; text-align: center;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase;">Rendered Today</div>
            <div style="font-size: 24px; font-weight: 900; color: #22c55e;">{{ $stats['completed_today'] }}</div>
        </div>
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 12px 20px; text-align: center;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase;">Published Today</div>
            <div style="font-size: 24px; font-weight: 900; color: #a855f7;">{{ $stats['published_today'] }}</div>
        </div>
    </div>

    {{-- Rotation info --}}
    <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 12px 16px; margin-bottom: 16px;">
        <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; margin-bottom: 6px;">Rotation pattern (per 12 items)</div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            @foreach($this->getRotationInfo() as $label => $count)
                <span style="font-size: 12px; color: #d1d5db;">{{ $count }}x <span style="color: #ea580c; font-weight: 700;">{{ $label }}</span></span>
            @endforeach
        </div>
    </div>

    {{-- Tier cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
        @foreach($poolCounts as $tier => $data)
            <div
                wire:click="loadPreview({{ $tier }})"
                class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5"
                style="padding: 12px 16px; cursor: pointer; {{ $selectedTier === $tier ? 'border: 2px solid #ea580c;' : '' }}"
            >
                <div style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; margin-bottom: 8px;">{{ $data['label'] }}</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                    <div>
                        <div style="font-size: 9px; color: #6b7280;">Available</div>
                        <div style="font-size: 16px; font-weight: 800; color: #d1d5db;">{{ number_format($data['available']) }}</div>
                    </div>
                    <div>
                        <div style="font-size: 9px; color: #6b7280;">Rendered</div>
                        <div style="font-size: 16px; font-weight: 800; color: #22c55e;">{{ number_format($data['rendered']) }}</div>
                    </div>
                    <div>
                        <div style="font-size: 9px; color: #6b7280;">Pending</div>
                        <div style="font-size: 16px; font-weight: 800; color: #f59e0b;">{{ number_format($data['pending']) }}</div>
                    </div>
                    <div>
                        <div style="font-size: 9px; color: #6b7280;">Unpublished</div>
                        <div style="font-size: 16px; font-weight: 800; color: #a855f7;">{{ number_format($data['unpublished']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Preview table --}}
    @if($selectedTier && count($preview) > 0)
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="font-size: 12px; font-weight: 800; color: #ea580c; text-transform: uppercase;">
                    {{ \App\Services\RenderQueueService::TIER_LABELS[$selectedTier] ?? '' }} - Top 50 candidates
                </h3>
            </div>
            <div style="max-height: 500px; overflow-y: auto;">
                <table style="width: 100%; font-size: 12px;">
                    <thead>
                        <tr style="color: #6b7280; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #374151;">
                            <th style="padding: 6px 8px; text-align: left;">Map</th>
                            <th style="padding: 6px 8px; text-align: left;">Player</th>
                            <th style="padding: 6px 8px; text-align: left;">Physics</th>
                            <th style="padding: 6px 8px; text-align: right;">Time</th>
                            <th style="padding: 6px 8px; text-align: right;">Rank</th>
                            <th style="padding: 6px 8px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview as $item)
                            <tr style="border-bottom: 1px solid #1f2937;">
                                <td style="padding: 6px 8px;">
                                    <a href="/maps/{{ $item['map_name'] }}" target="_blank" style="color: #93c5fd; text-decoration: none;">{{ $item['map_name'] }}</a>
                                </td>
                                <td style="padding: 6px 8px; color: #d1d5db;">{{ $item['player_name'] }}</td>
                                <td style="padding: 6px 8px;">
                                    <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; {{ $item['physics'] === 'cpm' ? 'background: #312e81; color: #a78bfa;' : 'background: #172554; color: #60a5fa;' }}">{{ strtoupper($item['physics']) }}</span>
                                </td>
                                <td style="padding: 6px 8px; text-align: right; font-family: monospace; color: #9ca3af;">{{ $item['time_fmt'] }}</td>
                                <td style="padding: 6px 8px; text-align: right; font-family: monospace; {{ $item['rank'] === 1 ? 'color: #fbbf24; font-weight: 700;' : 'color: #6b7280;' }}">
                                    {{ $item['rank'] ? '#' . $item['rank'] : '-' }}
                                </td>
                                <td style="padding: 6px 8px; text-align: center;">
                                    <button
                                        wire:click="queueDemo({{ $item['id'] }}, {{ $item['tier'] }})"
                                        style="background: #16a34a; color: #fff; padding: 3px 10px; font-size: 10px; font-weight: 700; border-radius: 4px; border: none; cursor: pointer;"
                                        onmouseover="this.style.background='#22c55e'"
                                        onmouseout="this.style.background='#16a34a'"
                                    >FORCE QUEUE</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($selectedTier)
        <div class="fi-section rounded-xl dark:bg-gray-900 dark:ring-white/10 ring-1 ring-gray-950/5" style="padding: 32px; text-align: center; color: #6b7280;">
            No candidates available for this tier.
        </div>
    @endif
</x-filament-panels::page>
