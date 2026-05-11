<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    @if ($entries->isEmpty())
        <div class="p-6 text-center text-gray-500">No calls recorded.</div>
    @else
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2 text-left">When</th>
                    <th class="px-3 py-2 text-left">Query</th>
                    <th class="px-3 py-2 text-right">Status</th>
                    <th class="px-3 py-2 text-right">ms</th>
                    <th class="px-3 py-2 text-left">IP</th>
                    <th class="px-3 py-2 text-left">Auth</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($entries as $e)
                    @php
                        $statusColor = match (true) {
                            $e->response_status >= 500 => 'text-red-500',
                            $e->response_status >= 400 => 'text-yellow-500',
                            $e->response_status >= 200 => 'text-green-500',
                            default                    => 'text-gray-500',
                        };
                        $msColor = match (true) {
                            $e->response_ms > 2000 => 'text-red-500',
                            $e->response_ms > 500  => 'text-yellow-500',
                            default                => 'text-gray-400',
                        };
                        $detailUrl = '/defraghq/api-call-logs/calls/' . $e->id;
                    @endphp
                    <tr class="hover:bg-blue-500/5 cursor-pointer transition-colors" onclick="window.location.href='{{ $detailUrl }}'">
                        <td class="px-3 py-1.5 whitespace-nowrap font-mono text-xs text-gray-600 dark:text-gray-300">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-700 dark:text-gray-200 break-all">{{ $e->query_string ? '?' . $e->query_string : '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-mono text-xs {{ $statusColor }}">{{ $e->response_status }}</td>
                        <td class="px-3 py-1.5 text-right font-mono text-xs {{ $msColor }}">{{ $e->response_ms }}</td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-500">{{ $e->ip }}</td>
                        <td class="px-3 py-1.5 text-xs">
                            @if ($e->token_id)
                                <span class="inline-block px-2 py-0.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-300">token #{{ $e->token_id }}</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded bg-gray-500/10 text-gray-600 dark:text-gray-300">session</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-3 py-2 text-xs text-gray-500 bg-gray-50 dark:bg-gray-800/30">
            Click any row to open the full call detail.
        </div>
    @endif
</div>
