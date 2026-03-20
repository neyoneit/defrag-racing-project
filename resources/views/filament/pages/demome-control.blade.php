<x-filament-panels::page>
    {{-- Top row: Status + Stats + Queue --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Demome Status --}}
        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Connection</span>
                    @if($isOnline)
                        <x-filament::badge color="success">Online</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">Offline</x-filament::badge>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Status</span>
                    <x-filament::badge :color="match($currentStatus) {
                        'idle' => 'gray',
                        'rendering' => 'warning',
                        'uploading' => 'info',
                        default => 'gray',
                    }">{{ ucfirst($currentStatus) }}</x-filament::badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Heartbeat</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ $lastHeartbeat }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Queue</span>
                    @if($isPaused)
                        <x-filament::badge color="danger">Paused</x-filament::badge>
                    @else
                        <x-filament::badge color="success">Active</x-filament::badge>
                    @endif
                </div>
                @if($currentVideo)
                    <div class="pt-2 border-t dark:border-gray-700 text-gray-700 dark:text-gray-300">
                        <span class="text-gray-500 dark:text-gray-400">Rendering:</span>
                        {{ $currentVideo->map_name }} - {{ $currentVideo->player_name }}
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Statistics --}}
        <x-filament::section>
            <x-slot name="heading">Statistics</x-slot>
            <div class="grid grid-cols-2 gap-2 text-center">
                <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xl font-bold text-primary-500">{{ $stats['completed_total'] }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xl font-bold text-success-500">{{ $stats['completed_today'] }}</div>
                    <div class="text-xs text-gray-500">Today</div>
                </div>
                <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xl font-bold text-warning-500">{{ $stats['pending'] }}</div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
                <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xl font-bold text-danger-500">{{ $stats['failed'] }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
            </div>
            <div class="mt-2 text-center p-2 bg-gray-50 dark:bg-gray-800 rounded">
                <span class="text-sm font-bold text-info-500">{{ $stats['total_render_hours'] }}h</span>
                <span class="text-xs text-gray-500 ml-1">render time</span>
            </div>
        </x-filament::section>

        {{-- Queue by Priority --}}
        <x-filament::section>
            <x-slot name="heading">Queue Breakdown</x-slot>
            <div class="space-y-2">
                <div class="flex items-center justify-between p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">
                    <span class="text-sm text-yellow-700 dark:text-yellow-300">P1: World Records</span>
                    <span class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $queue_by_priority['wr'] }}</span>
                </div>
                <div class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                    <span class="text-sm text-blue-700 dark:text-blue-300">P2: Verified</span>
                    <span class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ $queue_by_priority['verified'] }}</span>
                </div>
                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-700 dark:text-gray-300">P3: Normal</span>
                    <span class="text-xl font-bold text-gray-600 dark:text-gray-400">{{ $queue_by_priority['normal'] }}</span>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- API Token (compact) --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">API Token</x-slot>
        <div class="flex items-center gap-4">
            <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono text-gray-700 dark:text-gray-300 break-all select-all">{{ $apiToken ?: 'Not set' }}</code>
            {{ $this->regenerateTokenAction }}
        </div>
    </x-filament::section>

    {{-- Unlisted Videos --}}
    @if($unlisted_videos->count() > 0)
    <x-filament::section class="mt-4">
        <x-slot name="heading">Unlisted Videos ({{ $unlisted_count }})</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Map</th>
                        <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Player</th>
                        <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Physics</th>
                        <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">YouTube</th>
                        <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Created</th>
                        <th class="text-right py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach($unlisted_videos as $video)
                    <tr>
                        <td class="py-2 px-3 text-gray-700 dark:text-gray-300">{{ $video->map_name }}</td>
                        <td class="py-2 px-3 text-gray-700 dark:text-gray-300">{!! \App\Filament\Resources\UserResource::q3tohtml($video->player_name) !!}</td>
                        <td class="py-2 px-3"><x-filament::badge>{{ strtoupper($video->physics) }}</x-filament::badge></td>
                        <td class="py-2 px-3">
                            @if($video->youtube_url)
                                <a href="{{ $video->youtube_url }}" target="_blank" class="text-blue-500 hover:text-blue-400">View</a>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $video->created_at->diffForHumans() }}</td>
                        <td class="py-2 px-3 text-right">
                            <x-filament::button
                                wire:click="publishSingle({{ $video->id }})"
                                size="xs"
                                color="success"
                                icon="heroicon-o-eye"
                            >
                                Publish
                            </x-filament::button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @endif

    {{-- Actions --}}
    <x-filament::section class="mt-4">
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
