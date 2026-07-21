<x-filament-panels::page>
    <div class="space-y-6">

        <x-filament::section>
            <x-slot name="heading">Last generated</x-slot>
            <x-slot name="description">The core bundle is rebuilt daily and whenever you press Rebuild now.</x-slot>

            @if ($this->marker)
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Built</dt>
                        <dd class="text-base font-medium">
                            {{ \Illuminate\Support\Carbon::parse($this->marker['built_at'])->diffForHumans() }}
                            <span class="block text-xs text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($this->marker['built_at'])->toDayDateTimeString() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Engine build</dt>
                        <dd class="text-base font-medium">{{ $this->marker['engine'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">DeFRaG mod</dt>
                        <dd class="text-base font-medium">{{ $this->marker['mod'] ?? '-' }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Never built yet. Press <strong>Rebuild now</strong> to generate the first
                    <code>dfsv-core.tar</code>.
                </p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Archives on /www/downloads</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4">File</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Size</th>
                            <th class="py-2">Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->files as $file)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">
                                    <span class="font-mono">{{ $file['name'] }}</span>
                                    <span class="block text-xs text-gray-400">{{ $file['desc'] }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($file['exists'])
                                        <span class="text-success-600 dark:text-success-400">present</span>
                                    @else
                                        <span class="text-danger-600 dark:text-danger-400">missing</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $this->formatBytes($file['size']) }}</td>
                                <td class="py-2">
                                    @if ($file['exists'])
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener"
                                           class="text-primary-600 hover:underline dark:text-primary-400">download</a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div wire:poll.3s>
            @if ($this->status)
                <x-filament::section>
                    <x-slot name="heading">
                        Build status:
                        @if ($this->status === 'running')
                            <span class="text-warning-600 dark:text-warning-400">running...</span>
                        @elseif ($this->status === 'done')
                            <span class="text-success-600 dark:text-success-400">done</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400">error</span>
                        @endif
                    </x-slot>

                    <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ implode("\n", $this->log) }}</pre>
                </x-filament::section>
            @endif
        </div>

    </div>
</x-filament-panels::page>
