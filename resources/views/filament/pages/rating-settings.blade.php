<x-filament-panels::page>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Settings (2/3 width) --}}
        <div class="xl:col-span-2 space-y-4">
            {{-- Sticky save bar --}}
            <div class="sticky top-0 z-10 flex items-center gap-4 rounded-xl p-3" style="background: rgba(5,5,15,0.95); border: 2px solid #ea580c;">
                <button
                    wire:click="saveSettings"
                    style="background: #ea580c; color: #fff; padding: 12px 32px; font-size: 14px; font-weight: 800; border-radius: 8px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;"
                    onmouseover="this.style.background='#f97316'"
                    onmouseout="this.style.background='#ea580c'"
                >
                    SAVE SETTINGS
                </button>
                <span class="text-xs text-gray-500">Changes are saved to database and used by both PHP and Rust on next recalc.</span>
            </div>

            @php
                $groups = $this->getSettingGroups();
                $left = ['Logistic Curve (Score)', 'Player Penalty', 'Rank Multiplier'];
                $right = ['Hill Function (Map Multiplier)', 'Weighted Average', 'Map Eligibility'];
            @endphp
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    @foreach($left as $groupName)
                        @if(isset($groups[$groupName]))
                            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                                <h3 style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
                                    {{ $groupName }}
                                </h3>
                                @foreach($groups[$groupName] as $key => $label)
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <code style="width: 130px; font-size: 11px; color: #9ca3af; flex-shrink: 0;">{{ $key }}</code>
                                        <input type="text" wire:model.blur="{{ $key }}" onkeydown="if(!/[0-9.\-\b]|Tab|ArrowLeft|ArrowRight|Delete|Backspace/.test(event.key))event.preventDefault()" oninput="this.value=this.value.replace(/[^0-9.\-]/g,'')" style="background: #111; color: #fff; width: 80px; padding: 4px 8px; font-size: 13px; font-family: monospace; border: 1px solid #444; border-radius: 6px; flex-shrink: 0;" />
                                        <span style="font-size: 11px; color: #6b7280;">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    @foreach($right as $groupName)
                        @if(isset($groups[$groupName]))
                            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                                <h3 style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">{{ $groupName }}</h3>
                                @foreach($groups[$groupName] as $key => $label)
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <code style="width: 130px; font-size: 11px; color: #9ca3af; flex-shrink: 0;">{{ $key }}</code>
                                        <input type="text" wire:model.blur="{{ $key }}" onkeydown="if(!/[0-9.\-\b]|Tab|ArrowLeft|ArrowRight|Delete|Backspace/.test(event.key))event.preventDefault()" oninput="this.value=this.value.replace(/[^0-9.\-]/g,'')" style="background: #111; color: #fff; width: 80px; padding: 4px 8px; font-size: 13px; font-family: monospace; border: 1px solid #444; border-radius: 6px; flex-shrink: 0;" />
                                        <span style="font-size: 11px; color: #6b7280;">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Recalc Controls (1/3 width) --}}
        <div class="space-y-4" @if($this->getRecalcStatus() === 'running') wire:poll.3s @endif>
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4" style="display: flex; gap: 32px; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Physics</h3>
                    <div style="display: flex; gap: 16px;">
                        @foreach(['vq3', 'cpm'] as $p)
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" wire:model.live="selectedPhysics" value="{{ $p }}"
                                    class="rounded border-gray-600 bg-gray-950 text-orange-600 focus:ring-orange-500">
                                <span class="text-sm font-bold uppercase {{ $p === 'vq3' ? 'text-blue-400' : 'text-purple-400' }}">{{ strtoupper($p) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h3 style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Categories</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px 16px;">
                        @foreach(['overall', 'strafe', 'rocket', 'plasma', 'grenade', 'bfg', 'slick', 'tele', 'lg'] as $cat)
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" wire:model.live="selectedCategories" value="{{ $cat }}"
                                    class="rounded border-gray-600 bg-gray-950 text-orange-600 focus:ring-orange-500">
                                <span style="font-size: 12px;">{{ ucfirst($cat) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            @php
                $recalcStatus = $this->getRecalcStatus();
                $isRunning = $recalcStatus === 'running';
            @endphp

            <button
                wire:click="runRecalc"
                @if($isRunning) disabled @endif
                style="background: {{ $isRunning ? '#374151' : '#16a34a' }}; color: #fff; padding: 12px 32px; font-size: 14px; font-weight: 800; border-radius: 8px; border: none; cursor: {{ $isRunning ? 'not-allowed' : 'pointer' }}; text-transform: uppercase; letter-spacing: 1px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;"
                @if(!$isRunning) onmouseover="this.style.background='#22c55e'" onmouseout="this.style.background='#16a34a'" @endif
            >
                @if($isRunning)
                    <svg class="animate-spin" style="height:16px;width:16px;" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    RUNNING IN BACKGROUND...
                @else
                    RUN RECALCULATION
                @endif
            </button>

            <button
                wire:click="refreshCache"
                wire:loading.attr="disabled"
                wire:target="refreshCache"
                style="background: #2563eb; color: #fff; padding: 12px 32px; font-size: 14px; font-weight: 800; border-radius: 8px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;"
                onmouseover="this.style.background='#3b82f6'"
                onmouseout="this.style.background='#2563eb'"
            >
                <span wire:loading wire:target="refreshCache">
                    <svg class="animate-spin" style="height:16px;width:16px;" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </span>
                <span wire:loading.remove wire:target="refreshCache">REFRESH CACHE <span style="font-weight: 400; font-size: 11px; opacity: 0.6; text-transform: none; letter-spacing: 0;">- recalc does this automatically, shouldn't be needed</span></span>
                <span wire:loading wire:target="refreshCache">REFRESHING...</span>
            </button>

            {{-- Recalc Log (auto-polls when running) --}}
            @php $recalcLog = $this->getRecalcLog(); @endphp
            @if(!empty($recalcLog))
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 max-h-[500px] overflow-y-auto">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h3 style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: {{ $isRunning ? '#eab308' : ($recalcStatus === 'failed' ? '#ef4444' : '#22c55e') }};">
                            @if($isRunning)
                                <span class="inline-block animate-pulse">&#9679;</span> Running...
                            @elseif($recalcStatus === 'failed')
                                Failed
                            @else
                                Completed
                            @endif
                        </h3>
                        @if(!$isRunning)
                            <button wire:click="clearLog" style="font-size: 10px; color: #6b7280; background: none; border: none; cursor: pointer; text-decoration: underline;">Clear</button>
                        @endif
                    </div>
                    <div style="font-size: 11px; font-family: monospace; color: #9ca3af; line-height: 1.8;">
                        @foreach($recalcLog as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
