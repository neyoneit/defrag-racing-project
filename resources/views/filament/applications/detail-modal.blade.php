@php
    $servers = is_array($record->server_info) ? $record->server_info : [];
@endphp

<div class="space-y-5 text-sm">
    {{-- Message --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Message</div>
        <blockquote class="border-l-2 border-yellow-500/40 pl-3 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $record->message }}</blockquote>
    </div>

    {{-- Declared servers --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">
            Declared servers <span class="text-gray-400 normal-case">({{ count($servers) }})</span>
        </div>

        @if (empty($servers))
            <div class="text-gray-400 italic">No servers listed.</div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Gametype</th>
                            <th class="px-3 py-2 text-left">IP</th>
                            <th class="px-3 py-2 text-left">Port</th>
                            <th class="px-3 py-2 text-left">RCON</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($servers as $s)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ strtoupper($s['gametype'] ?? '?') }}</td>
                                <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-200">{{ $s['ip'] ?? '?' }}</td>
                                <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-200">{{ $s['port'] ?? '?' }}</td>
                                <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-200">{{ $s['rcon'] ?? '?' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Reviewer note (only if rejected/approved) --}}
    @if ($record->review_note)
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Reviewer note</div>
            <p class="whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $record->review_note }}</p>
        </div>
    @endif

    {{-- Audit footer --}}
    <div class="pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 grid grid-cols-2 gap-2">
        <div>Submitted: {{ $record->created_at?->format('Y-m-d H:i') }}</div>
        @if ($record->reviewed_at)
            <div>Reviewed: {{ $record->reviewed_at?->format('Y-m-d H:i') }}</div>
        @endif
    </div>
</div>
