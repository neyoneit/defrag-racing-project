<x-filament-panels::page>
    <div class="flex items-center justify-between gap-3">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            @if($mapname)
                <span class="font-semibold">Map:</span> {{ $mapname }}
                @if($numPlayers !== null) &middot; <span class="font-semibold">Players:</span> {{ $numPlayers }} @endif
                &middot;
            @endif
            <span>{{ count($messages) }} messages{{ $showRemoved ? ' (incl. removed)' : '' }}</span>
        </div>

        <x-filament::button size="sm" color="gray" wire:click="toggleRemoved">
            {{ $showRemoved ? 'Hide removed' : 'Show removed' }}
        </x-filament::button>
    </div>

    {{-- Newest first; wire:poll refreshes every 5s so the latest is always at the top. --}}
    <div
        wire:poll.5s
        class="mt-2 overflow-y-auto rounded-xl border border-gray-700 bg-gray-950 p-3 font-mono text-sm leading-6"
        style="height: 70vh;"
    >
        @forelse($messages as $m)
            <div @class([
                'group flex items-start gap-2 rounded px-1.5 py-0.5 hover:bg-white/5',
                'opacity-40 line-through' => $m['trashed'],
            ])>
                <span class="shrink-0 select-none text-gray-600">{{ $m['time'] }}</span>

                @if($m['action'] !== 'message')
                    <span class="shrink-0 select-none rounded bg-gray-800 px-1 text-[10px] uppercase tracking-wide text-gray-400">
                        {{ str_replace('_', ' ', $m['action']) }}
                    </span>
                @endif

                @if($m['author_html'])
                    <span class="shrink-0 font-bold">{!! $m['author_html'] !!}</span>
                    @if($m['user'])
                        <a href="/profile/{{ $m['user']['id'] }}" target="_blank"
                           class="shrink-0 text-xs text-emerald-400 hover:underline" title="Matched profile">
                            [{{ $m['user']['name'] }}]
                        </a>
                    @endif
                    <span class="shrink-0 text-gray-600">:</span>
                @endif

                <span class="min-w-0 flex-1 break-words text-gray-100">{!! $m['content_html'] !!}</span>

                <span class="shrink-0 opacity-0 transition group-hover:opacity-100">
                    @if($m['trashed'])
                        <button wire:click="restore({{ $m['id'] }})" class="text-xs text-blue-400 hover:underline">restore</button>
                    @else
                        <button wire:click="remove({{ $m['id'] }})"
                                wire:confirm="Remove this message from chat (and the overlay)?"
                                class="text-xs text-red-400 hover:underline">remove</button>
                    @endif
                </span>
            </div>
        @empty
            <div class="p-6 text-center text-gray-500">No chat yet.</div>
        @endforelse
    </div>
</x-filament-panels::page>
