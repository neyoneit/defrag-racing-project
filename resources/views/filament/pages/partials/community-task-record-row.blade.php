{{--
    One candidate record, with the Assign button on the row itself.

    The time difference is the column that decides most of these, so it is
    coloured rather than left as one more number to compare by eye, and the
    alias badge sits next to the name because a name that matches beats a time
    that is close.
--}}
@php

    $fmt = fn (?int $ms) => \App\Filament\Pages\CommunityTaskReview::time($ms);

    $diff = $r['time_diff'] ?? 0;

    $diffTone = $diff <= 100
        ? 'text-green-400'
        : ($diff <= 1000 ? 'text-amber-400' : 'opacity-60');

    $isCurrent = ($this->current['id'] ?? null) === $r['id'];
@endphp

<div class="flex items-center gap-3 rounded-lg px-3 py-2 ring-1 {{ $isCurrent ? 'bg-primary-500/5 ring-primary-500/40' : ($highlight ? 'bg-gray-500/5 ring-gray-500/20' : 'ring-transparent hover:bg-gray-500/5') }}">
    <span class="w-10 shrink-0 text-right font-mono text-xs opacity-50">#{{ $r['rank'] }}</span>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <span class="truncate font-mono text-sm">{!! \App\Filament\Resources\UserResource::q3tohtml($r['player_name'] ?? '') !!}</span>

            @if (! empty($r['alias_match_type']))
                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 {{ $r['alias_match_type'] === 'exact' ? 'bg-green-500/15 text-green-400 ring-green-500/30' : 'bg-blue-500/15 text-blue-400 ring-blue-500/30' }}"
                      title="Known alias: {{ $r['matched_alias'] }}">
                    {{ $r['alias_match_type'] === 'exact' ? 'same name' : 'similar name' }}
                </span>
            @endif

            @if ($isCurrent)
                <span class="shrink-0 rounded bg-primary-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-400 ring-1 ring-primary-500/30">
                    assigned now
                </span>
            @endif
        </div>
    </div>

    <span class="shrink-0 font-mono text-sm">{{ $fmt($r['time']) }}</span>

    <span class="w-24 shrink-0 text-right font-mono text-xs {{ $diffTone }}">
        {{ $diff === 0 ? 'exact' : $fmt($diff) }}
    </span>

    <div class="flex w-28 shrink-0 items-center justify-end gap-1.5 text-xs">
        @if (! empty($r['demo_id']))
            <span class="rounded bg-gray-500/10 px-1.5 py-0.5 opacity-70" title="This record already has a demo">demo</span>
        @endif
        @if (! empty($r['youtube_url']))
            <a href="{{ $r['youtube_url'] }}" target="_blank" rel="noopener"
               class="rounded bg-red-500/10 px-1.5 py-0.5 text-red-400 hover:bg-red-500/20" title="Watch the render">video</a>
        @endif
    </div>

    @if ($isCurrent)
        <span class="w-20 shrink-0 text-right text-xs opacity-40">on it</span>
    @else
        <button wire:click="assign({{ $r['id'] }})"
                wire:confirm="Assign this demo to {{ addslashes(strip_tags(preg_replace('/\^[0-9]/', '', $r['player_name'] ?? ''))) }} at {{ $fmt($r['time']) }}?"
                class="fi-btn w-20 shrink-0 rounded-lg bg-primary-500/10 px-2 py-1 text-xs font-semibold text-primary-400 hover:bg-primary-500/20">
            Assign
        </button>
    @endif
</div>
