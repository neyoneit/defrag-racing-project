<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Demome Status --}}
        <x-filament::section>
            <x-slot name="heading">Demome Status</x-slot>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Connection</span>
                    @if($isOnline)
                        <x-filament::badge color="success">Online</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">Offline</x-filament::badge>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Status</span>
                    <x-filament::badge :color="match($currentStatus) {
                        'idle' => 'gray',
                        'rendering' => 'warning',
                        'uploading' => 'info',
                        default => 'gray',
                    }">{{ ucfirst($currentStatus) }}</x-filament::badge>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Heartbeat</span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $lastHeartbeat }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Queue State</span>
                    @if($isPaused)
                        <x-filament::badge color="danger">Paused</x-filament::badge>
                    @else
                        <x-filament::badge color="success">Active</x-filament::badge>
                    @endif
                </div>

                @if($currentVideo)
                    <div class="pt-2 border-t dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Currently Rendering</span>
                        <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            {{ $currentVideo->map_name }} - {{ $currentVideo->player_name }}
                            ({{ $currentVideo->physics }})
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Statistics --}}
        <x-filament::section>
            <x-slot name="heading">Statistics</x-slot>

            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-primary-500">{{ $stats['completed_total'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Completed</div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-success-500">{{ $stats['completed_today'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Completed Today</div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-warning-500">{{ $stats['pending'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold text-danger-500">{{ $stats['failed'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg col-span-2">
                    <div class="text-2xl font-bold text-info-500">{{ $stats['total_render_hours'] }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Render Time</div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Queue Breakdown --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Pending Queue by Priority</x-slot>

        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $queue_by_priority['wr'] }}</div>
                <div class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">P1: World Records</div>
            </div>
            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $queue_by_priority['verified'] }}</div>
                <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">P2: Verified Records</div>
            </div>
            <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ $queue_by_priority['normal'] }}</div>
                <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">P3: Normal</div>
            </div>
        </div>
    </x-filament::section>

    {{-- API Token --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">API Token</x-slot>

        <div class="space-y-3">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Token</span>
                <div class="mt-1 flex items-center gap-3">
                    <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm font-mono text-gray-700 dark:text-gray-300 break-all select-all">{{ $apiToken ?: 'Not set' }}</code>
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                This token must match the <code>DEFRAG_API_TOKEN</code> in demome's <code>settings.py</code>. After regenerating, update demome and restart it.
            </p>
            <x-filament::button
                wire:click="regenerateToken"
                wire:confirm="Are you sure? This will invalidate the current token. Demome will stop working until you update its settings.py with the new token."
                color="warning"
                icon="heroicon-o-arrow-path"
            >
                Regenerate Token
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Actions --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Actions</x-slot>

        <div class="flex gap-4">
            <x-filament::button
                wire:click="togglePause"
                :color="$isPaused ? 'success' : 'danger'"
                icon="{{ $isPaused ? 'heroicon-o-play' : 'heroicon-o-pause' }}"
            >
                {{ $isPaused ? 'Unpause Queue' : 'Pause Queue' }}
            </x-filament::button>

            <x-filament::button
                wire:click="populateQueue"
                color="info"
                icon="heroicon-o-queue-list"
            >
                Populate Queue Now
            </x-filament::button>

            <x-filament::button
                wire:click="publishUnlisted"
                wire:confirm="This will mark {{ $unlisted_count }} unlisted auto-rendered videos for publishing. Demome will change them to public on next cycle. Continue?"
                color="success"
                icon="heroicon-o-eye"
                :disabled="$unlisted_count === 0"
            >
                Publish Unlisted ({{ $unlisted_count }})
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
