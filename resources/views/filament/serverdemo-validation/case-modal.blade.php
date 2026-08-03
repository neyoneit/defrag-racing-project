@php
    /**
     * One player, every report against them, and the thread the validators
     * keep. Demo links are minted here rather than stored: they are signed,
     * short-lived, and the endpoint re-checks entitlement on every hit.
     */
    $stages = [
        'assigned' => 'With one validator',
        'second_opinion' => 'Second opinion',
        'all_validators' => 'Open to all validators',
        'admin' => 'With the admin',
    ];

    $flags = $case->flags()->with(['record', 'demo'])->orderBy('created_at')->get();
@endphp

<div class="space-y-5 text-sm">
    <div class="grid grid-cols-3 gap-4">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Stage</div>
            <div class="text-gray-800 dark:text-gray-200">{{ $stages[$case->validation_stage] ?? $case->validation_stage }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Reported runs</div>
            <div class="text-gray-800 dark:text-gray-200">{{ $flags->count() }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Identity</div>
            <div class="text-gray-800 dark:text-gray-200">
                {{ $case->subject_mdd_id ? 'MDD #' . $case->subject_mdd_id : ($case->subject_user_id ? 'site account' : 'name only') }}
            </div>
        </div>
    </div>

    {{-- The evidence, run by run --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Runs in this case</div>

        <div class="space-y-2">
            @foreach ($flags as $flag)
                @php
                    $serverDemo = $flag->demo_id ? null : $flag->serverDemo();
                    $signedUrl = $serverDemo
                        ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
                            'defraghq.validation-demo',
                            now()->addMinutes(5),
                            ['flag' => $flag->id],
                        )
                        : null;
                @endphp

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-200">
                                @if ($flag->demo_id)
                                    {{ $flag->demo?->map_name ?? 'demo #' . $flag->demo_id }}
                                @else
                                    {{ $flag->record?->mapname ?? 'record is gone' }}
                                    <span class="text-gray-500 font-normal">{{ $flag->record?->physics }} {{ $flag->record?->mode }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ $flag->flag_type }} - {{ $flag->flag_count }} {{ $flag->flag_count === 1 ? 'report' : 'reports' }}
                            </div>
                            @if ($flag->note)
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 whitespace-pre-line">{{ $flag->note }}</div>
                            @endif
                        </div>

                        <div class="flex-shrink-0">
                            @if ($flag->demo_id && $flag->demo)
                                <a href="{{ url('/demos/' . $flag->demo->id . '/download') }}"
                                   class="px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold">
                                    Download
                                </a>
                            @elseif ($signedUrl)
                                <a href="{{ $signedUrl }}"
                                   class="px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold">
                                    Serverdemo
                                </a>
                            @else
                                <span class="text-xs text-yellow-600 dark:text-yellow-400">no demo</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-gray-500 mt-2">
            Links expire in 5 minutes and only work while this case is yours.
        </p>
    </div>

    {{-- The thread --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Internal notes</div>

        @if ($case->comments->isEmpty())
            <div class="text-gray-400 italic">Nothing yet.</div>
        @else
            <div class="space-y-3">
                @foreach ($case->comments as $comment)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                {{ $comment->user?->plain_name ?? $comment->user?->name ?? 'system' }}
                            </span>
                            <span class="text-xs text-gray-500">
                                @if ($comment->event)
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 uppercase tracking-wide mr-2">{{ str_replace('_', ' ', $comment->event) }}</span>
                                @endif
                                {{ $comment->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <div class="whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $comment->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
