<x-filament-panels::page>
    <div class="flex items-center justify-between gap-3 mb-2">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Newest first &middot; {{ $total }} sessions logged
        </div>
        <div class="text-xs text-gray-500">auto-refreshes every 10s</div>
    </div>

    {{-- Scrollable spectate feed (newest first), polled live. --}}
    <div
        wire:poll.10s
        class="overflow-y-auto rounded-xl border border-gray-700 bg-gray-950 divide-y divide-white/5"
        style="height: 72vh;"
    >
        @forelse($rows as $r)
            <div class="flex items-center gap-3 px-3 py-2.5 hover:bg-white/5">
                {{-- Map thumbnail --}}
                <img
                    src="{{ $r['map_thumb'] ? '/storage/' . $r['map_thumb'] : '/images/unknown.jpg' }}"
                    onerror="this.onerror=null;this.src='/images/unknown.jpg'"
                    class="w-20 h-11 rounded object-cover shrink-0 bg-gray-900"
                    alt=""
                >

                {{-- Player + map --}}
                <div class="min-w-0 flex-1">
                    <div class="font-mono font-semibold truncate text-sm">{!! $r['player_html'] !!}</div>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-500">
                        <span class="truncate">{{ $r['mapname'] ?: 'unknown map' }}</span>
                        @if($r['user'])
                            <a href="/profile/{{ $r['user']['id'] }}" target="_blank"
                               class="text-emerald-400 hover:underline shrink-0">[{{ $r['user']['name'] }}]</a>
                        @else
                            <span class="text-gray-600 shrink-0">unmatched</span>
                        @endif
                    </div>
                </div>

                {{-- Time + duration --}}
                <div class="text-right shrink-0">
                    <div class="font-mono text-sm text-gray-300 whitespace-nowrap">
                        {{ $r['from'] }}
                        <span class="text-gray-600">&rarr;</span>
                        @if($r['live'])
                            <span class="inline-flex items-center gap-1 text-red-400 font-bold">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                </span>
                                LIVE
                            </span>
                        @else
                            {{ $r['to'] }}
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $r['date'] }} &middot; <span class="text-gray-400 font-semibold">{{ $r['duration'] }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 text-center text-gray-500">
                Nothing logged yet. The bot's spectate history appears here once it starts watching players.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
