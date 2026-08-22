{{--
    One undecided demo, everything about it on one screen.

    Reading order is the order the question gets answered in: what the demo
    says about itself, what the site thinks now, what the voters thought, then
    the candidates. Assigning is a button on the candidate's own row - the old
    screen made you read a record id out of one dialog and type it into
    another.
--}}
<x-filament-panels::page>
    @php

        $fmt = fn (?int $ms) => \App\Filament\Pages\CommunityTaskReview::time($ms);

        $voteTone = [
            'assign' => 'bg-green-500/10 text-green-400 ring-green-500/30',
            'correct' => 'bg-green-500/10 text-green-400 ring-green-500/30',
            'better_match' => 'bg-blue-500/10 text-blue-400 ring-blue-500/30',
            'not_sure' => 'bg-amber-500/10 text-amber-400 ring-amber-500/30',
            'no_match' => 'bg-red-500/10 text-red-400 ring-red-500/30',
            'unassign' => 'bg-red-500/10 text-red-400 ring-red-500/30',
        ];

        // Anything within a tenth of a second is the same run as far as the
        // eye is concerned, and that is what makes a row worth looking at
        // first. Beyond a second it is almost certainly a different run.
        $near = 100;
        $maybe = 1000;
    @endphp

    @if (! $this->demo)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500">
                No demo picked, or it has been deleted. Open one from the
                <a class="text-primary-500 underline" href="{{ \App\Filament\Resources\CommunityTaskReviewResource::getUrl() }}">review queue</a>.
            </p>
        </div>
    @else
        <div class="space-y-4">

            {{-- ===================== THE DEMO ===================== --}}
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="grid flex-1 grid-cols-2 gap-4 text-xs md:grid-cols-5">
                        <div>
                            <div class="mb-0.5 uppercase tracking-wide opacity-60">Map</div>
                            <div class="font-mono text-sm">{{ $this->demo->map_name ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="mb-0.5 uppercase tracking-wide opacity-60">Physics</div>
                            <div class="font-mono text-sm">{{ strtoupper($this->demo->physics ?: '?') }}</div>
                        </div>
                        <div>
                            <div class="mb-0.5 uppercase tracking-wide opacity-60">Demo time</div>
                            <div class="font-mono text-sm font-bold">{{ $fmt($this->demo->time_ms) }}</div>
                        </div>
                        <div>
                            <div class="mb-0.5 uppercase tracking-wide opacity-60">Name in the demo</div>
                            <div class="font-mono text-sm">{!! $this->demo->player_name ? \App\Filament\Resources\UserResource::q3tohtml($this->demo->player_name) : '-' !!}</div>
                        </div>
                        <div>
                            <div class="mb-0.5 uppercase tracking-wide opacity-60">Uploaded by</div>
                            <div class="font-mono text-sm">{!! $this->demo->user ? \App\Filament\Resources\UserResource::q3tohtml($this->demo->user->name) : '-' !!}</div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('demos.download', $this->demo->id) }}"
                           class="fi-btn rounded-lg bg-gray-500/10 px-3 py-1.5 text-xs font-semibold hover:bg-gray-500/20">
                            Download demo
                        </a>
                        @if ($this->queueRemaining)
                            <span class="rounded-lg bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-400 ring-1 ring-amber-500/30">
                                {{ $this->queueRemaining }} waiting
                            </span>
                        @endif
                        @if ($this->nextDemoId)
                            <a href="{{ static::getUrl() }}?demo={{ $this->nextDemoId }}"
                               class="fi-btn rounded-lg bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-400 hover:bg-primary-500/20">
                                Next &rarr;
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-3 truncate border-t border-gray-950/5 pt-3 font-mono text-xs opacity-60 dark:border-white/10">
                    {{ $this->demo->original_filename }}
                </div>
            </div>

            {{-- ============ WHERE IT SITS NOW, AND WHO SAID WHAT ============ --}}
            <div class="grid gap-4 lg:grid-cols-2">

                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-3 text-sm font-semibold">Assigned to</h3>

                    @if ($this->current)
                        <div class="rounded-lg bg-gray-500/5 p-3 ring-1 ring-gray-500/20">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-mono text-sm">
                                        <span class="opacity-60">#{{ $this->current['rank'] }}</span>
                                        {!! \App\Filament\Resources\UserResource::q3tohtml($this->current['player_name'] ?? '') !!}
                                    </div>
                                    <div class="mt-0.5 font-mono text-xs opacity-60">
                                        {{ $fmt($this->current['time']) }}
                                        <span class="{{ $this->current['time_diff'] <= $near ? 'text-green-400' : ($this->current['time_diff'] <= $maybe ? 'text-amber-400' : 'text-red-400') }}">
                                            ({{ $fmt($this->current['time_diff']) }} away)
                                        </span>
                                    </div>
                                </div>
                                <span class="shrink-0 font-mono text-xs opacity-40">record #{{ $this->current['id'] }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-sm opacity-60">Not on any record. It shows on the map page as a run of its own.</p>
                    @endif
                </div>

                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-3 text-sm font-semibold">
                        Votes
                        @if ($this->voteCounts)
                            <span class="ml-1 font-normal opacity-60">
                                @foreach ($this->voteCounts as $type => $count){{ $count }}&times; {{ str_replace('_', ' ', $type) }}@if (! $loop->last), @endif @endforeach
                            </span>
                        @endif
                    </h3>

                    @forelse ($this->votes as $vote)
                        <div class="flex items-center justify-between gap-2 border-b border-gray-950/5 py-1.5 text-xs last:border-0 dark:border-white/10">
                            <div class="min-w-0 truncate font-mono">{!! $vote['user'] !!}</div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($vote['record_label'])
                                    <span class="font-mono opacity-60">&rarr; {!! \App\Filament\Resources\UserResource::q3tohtml($vote['record_label']) !!}</span>
                                @endif
                                <span class="rounded px-1.5 py-0.5 ring-1 {{ $voteTone[$vote['type']] ?? 'bg-gray-500/10 ring-gray-500/30' }}">
                                    {{ str_replace('_', ' ', $vote['type']) }}
                                </span>
                                <span class="opacity-40">{{ $vote['when'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm opacity-60">Nobody has voted on this one.</p>
                    @endforelse
                </div>
            </div>

            {{-- ===================== THE DECISION ===================== --}}
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold">Records on this map</h3>
                        <p class="mt-0.5 text-xs opacity-60">
                            Sorted by how close the time is to the demo. Green is within
                            {{ $near }} ms, amber within a second. A name badge means the
                            record holder has gone by the name written in the demo.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="mb-1 block text-xs opacity-60">Note (optional)</label>
                            <input type="text" wire:model="adminNotes" placeholder="why"
                                   class="w-56 rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800" />
                        </div>
                        @if ($this->current)
                            <button wire:click="markCorrect" wire:confirm="Keep this demo where it is and close the flag?"
                                    class="fi-btn rounded-lg bg-green-500/10 px-3 py-2 text-xs font-semibold text-green-400 hover:bg-green-500/20">
                                Already right
                            </button>
                            <button wire:click="unassign" wire:confirm="Take this demo off its record?"
                                    class="fi-btn rounded-lg bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-400 hover:bg-red-500/20">
                                Unassign
                            </button>
                        @endif
                        <button wire:click="dismiss" wire:confirm="Close this flag without deciding?"
                                class="fi-btn rounded-lg bg-gray-500/10 px-3 py-2 text-xs font-semibold hover:bg-gray-500/20">
                            Cannot tell
                        </button>
                    </div>
                </div>

                @if (! $this->records)
                    <div class="rounded-lg bg-amber-500/5 p-4 text-sm text-amber-400 ring-1 ring-amber-500/30">
                        No records on <code>{{ $this->demo->map_name }}</code> in this physics, so there is
                        nothing to assign it to. If the map page does show records, the demo's physics or
                        mode does not match them.
                    </div>
                @else
                    @php
                        // Closest first, and the closest few are shown with the
                        // rest folded away: on a busy map a hundred rows is the
                        // same wall of numbers the old modal was.
                        $closestIds = collect($this->closest)->pluck('id')->all();
                        $rest = collect($this->records)
                            ->reject(fn ($r) => in_array($r['id'], $closestIds))
                            ->sortBy('time_diff')
                            ->values();
                    @endphp

                    <div class="space-y-1.5">
                        @foreach ($this->closest as $r)
                            @include('filament.pages.partials.community-task-record-row', ['r' => $r, 'highlight' => true])
                        @endforeach
                    </div>

                    @if ($rest->isNotEmpty())
                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs opacity-60 hover:opacity-100">
                                {{ $rest->count() }} more record(s) on this map
                            </summary>
                            <div class="mt-2 max-h-96 space-y-1.5 overflow-y-auto pr-1">
                                @foreach ($rest as $r)
                                    @include('filament.pages.partials.community-task-record-row', ['r' => $r, 'highlight' => false])
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
