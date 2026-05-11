<x-filament-panels::page>
    @php
        $c = $this->call;
        $statusColor = match (true) {
            $c->response_status >= 500 => 'text-red-500 bg-red-500/10 border-red-500/30',
            $c->response_status >= 400 => 'text-yellow-500 bg-yellow-500/10 border-yellow-500/30',
            $c->response_status >= 200 => 'text-green-500 bg-green-500/10 border-green-500/30',
            default                    => 'text-gray-500 bg-gray-500/10 border-gray-500/30',
        };
        $msColor = match (true) {
            $c->response_ms > 2000 => 'text-red-500',
            $c->response_ms > 500  => 'text-yellow-500',
            default                => 'text-green-500',
        };
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- When / Who --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-3">When &amp; who</div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Timestamp</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $c->created_at?->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Relative</dt>
                    <dd class="text-gray-600 dark:text-gray-300">{{ $c->created_at?->diffForHumans() }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">User</dt>
                    <dd>
                        <a href="/profile/{{ $c->user_id }}" target="_blank" class="text-blue-500 hover:underline">
                            {{ $c->user?->plain_name ?? "user #{$c->user_id}" }}
                        </a>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Auth</dt>
                    <dd>
                        @if ($c->token_id)
                            <span class="inline-block px-2 py-0.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-300">
                                token #{{ $c->token_id }} ({{ $c->token?->name ?? '?' }})
                            </span>
                        @else
                            <span class="inline-block px-2 py-0.5 rounded bg-gray-500/10 text-gray-600 dark:text-gray-300">session cookie</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">IP</dt>
                    <dd class="font-mono text-gray-700 dark:text-gray-200">{{ $c->ip ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Request --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-3">Request</div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Method</dt>
                    <dd>
                        <span class="inline-block px-2 py-0.5 rounded bg-gray-500/10 font-mono text-xs">{{ $c->method }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 mb-1">Route</dt>
                    <dd class="font-mono text-xs text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-800 rounded p-2 break-all">{{ $c->route }}</dd>
                </div>
                @if ($c->query_string)
                    <div>
                        <dt class="text-gray-500 mb-1">Query string</dt>
                        <dd class="font-mono text-xs text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-800 rounded p-2 break-all">?{{ $c->query_string }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 mb-1">Full URL (reconstructed)</dt>
                    <dd class="font-mono text-xs text-blue-500 hover:underline bg-gray-100 dark:bg-gray-800 rounded p-2 break-all">
                        <a href="{{ $c->route }}{{ $c->query_string ? '?' . $c->query_string : '' }}" target="_blank">{{ $c->route }}{{ $c->query_string ? '?' . $c->query_string : '' }}</a>
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Response --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-5 md:col-span-2">
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-3">Response</div>
            <div class="flex flex-wrap gap-3 items-center">
                <div class="px-4 py-2 rounded-lg border {{ $statusColor }}">
                    <div class="text-xs uppercase tracking-wide opacity-70">Status</div>
                    <div class="text-2xl font-bold font-mono">{{ $c->response_status }}</div>
                </div>
                <div class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Duration</div>
                    <div class="text-2xl font-bold font-mono {{ $msColor }}">{{ $c->response_ms }} ms</div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
