@php
    // $record, $serverdemo, $history, $uploads come from the action's
    // modalContent(). Times are milliseconds everywhere in this project.
    $fmtTime = function ($ms) {
        if ($ms === null) return '—';
        $sec = $ms / 1000;
        $min = floor($sec / 60);
        return sprintf('%d:%06.3f', $min, $sec - $min * 60);
    };
    $fmtSize = function ($b) {
        if (! $b) return '—';
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' kB';
    };
@endphp

<div class="space-y-6 text-sm">

    <div class="rounded-lg border border-gray-700 p-4">
        <div class="font-semibold text-base mb-2">{{ $record->mapname }}</div>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-400">
            <div>Time <span class="text-gray-200 font-mono">{{ $fmtTime($record->time) }}</span></div>
            <div>Physics <span class="text-gray-200">{{ $record->physics }} / {{ $record->mode }}</span></div>
            <div>Player <span class="text-gray-200">{{ $record->name }}</span></div>
            <div>mdd id <span class="text-gray-200 font-mono">{{ $record->mdd_id }}</span></div>
            <div>Set <span class="text-gray-200">{{ $record->date_set }}</span></div>
        </div>
    </div>

    {{-- The unfakeable one: written by the server, not chosen by the player. --}}
    <div>
        <div class="font-semibold mb-2">Serverdemo of this exact run</div>
        @if ($serverdemo)
            <div class="flex items-center justify-between rounded-lg border border-green-800 bg-green-950/30 p-3">
                <div>
                    <div class="font-mono text-xs text-gray-300">{{ $serverdemo->filename }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $fmtSize($serverdemo->size) }} ·
                        recorded {{ $serverdemo->recorded_at?->format('Y-m-d H:i') ?? '—' }} ·
                        server {{ $serverdemo->rs_server_id ?? '—' }}
                        @unless ($serverdemo->on_contabo) · from the B2 mirror @endunless
                    </div>
                </div>
                <a href="{{ \App\Services\RecordEvidence::downloadUrl($serverdemo) }}"
                   class="shrink-0 rounded-md bg-primary-600 px-3 py-1.5 text-white text-xs font-medium hover:bg-primary-500">
                    Download
                </a>
            </div>
        @else
            <div class="rounded-lg border border-gray-700 p-3 text-gray-500">
                None. This record was not set on a server that uploads to us,
                so there is no server-side recording of it.
            </div>
        @endif
    </div>

    {{-- Uploaded by a person, so it is evidence of a different weight. --}}
    <div>
        <div class="font-semibold mb-2">Uploaded demos ({{ $uploads->count() }})</div>
        @forelse ($uploads as $upload)
            <div class="flex items-center justify-between rounded-lg border border-gray-700 p-3 mb-2">
                <div>
                    <div class="font-mono text-xs text-gray-300">{{ $upload->original_filename }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $fmtSize($upload->file_size) }} ·
                        uploaded {{ $upload->created_at?->format('Y-m-d H:i') }}
                        @if ($upload->player_name) · as {{ $upload->player_name }} @endif
                    </div>
                </div>
                <a href="{{ route('demos.download', $upload->id) }}" target="_blank"
                   class="shrink-0 rounded-md bg-gray-700 px-3 py-1.5 text-white text-xs font-medium hover:bg-gray-600">
                    Download
                </a>
            </div>
        @empty
            <div class="rounded-lg border border-gray-700 p-3 text-gray-500">
                Nobody uploaded a demo for this record.
            </div>
        @endforelse
    </div>

    {{-- What usually settles it: how the player got to this time. --}}
    <div>
        <div class="font-semibold mb-2">
            Same player, same map ({{ $history->count() }}{{ $history->count() === 50 ? '+' : '' }})
        </div>
        @if ($history->isEmpty())
            <div class="rounded-lg border border-gray-700 p-3 text-gray-500">
                No other runs of this player on this map were recorded on our servers.
            </div>
        @else
            <p class="text-xs text-gray-500 mb-2">
                Every finished run leaves a demo, so this is the player's history on
                this map as far as our servers saw it. Record time history from the
                MDD database does not exist yet - when it does, this becomes the
                evidence behind it rather than a substitute for it.
            </p>
            <div class="max-h-80 overflow-y-auto rounded-lg border border-gray-700 divide-y divide-gray-800">
                @foreach ($history as $demo)
                    <div class="flex items-center justify-between p-2">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-gray-200 w-24">{{ $fmtTime($demo->time_ms) }}</span>
                            <span class="text-xs text-gray-500">
                                {{ $demo->recorded_at?->format('Y-m-d H:i') ?? '—' }}
                            </span>
                        </div>
                        <a href="{{ \App\Services\RecordEvidence::downloadUrl($demo) }}"
                           class="text-xs text-primary-400 hover:text-primary-300">Download</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="text-xs text-gray-600">
        Download links expire in 5 minutes. Serverdemos are never public - they
        are only ever handed out here, to staff resolving a report.
    </p>
</div>
