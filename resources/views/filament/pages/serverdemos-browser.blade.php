<x-filament-panels::page>
    @php
        $creds = $this->credentials;
        $files = $this->files;
        $selected = $this->selectedUser;
        $fmtSize = function ($b) {
            if ($b === null) return '—';
            $u = ['B','KB','MB','GB']; $i = 0;
            while ($b >= 1024 && $i < count($u)-1) { $b /= 1024; $i++; }
            return number_format($b, $b < 10 && $i > 0 ? 2 : ($b < 100 && $i > 0 ? 1 : 0)) . ' ' . $u[$i];
        };
        $fmtTime = function ($ts) {
            if (!$ts) return '—';
            return date('Y-m-d H:i', $ts);
        };
        $fmtAgo = function ($ts) {
            if (!$ts) return 'never';
            $d = time() - $ts;
            if ($d < 60) return $d . 's ago';
            if ($d < 3600) return floor($d/60) . 'm ago';
            if ($d < 86400) return floor($d/3600) . 'h ago';
            return floor($d/86400) . 'd ago';
        };
        $fmtRunTime = function ($ms) {
            if ($ms === null) return '—';
            $totalSec = $ms / 1000;
            $m = floor($totalSec / 60);
            $s = $totalSec - $m * 60;
            return sprintf('%d:%06.3f', $m, $s);
        };
    @endphp

    <div style="display:grid;grid-template-columns:320px 1fr;gap:14px;align-items:start;">

        {{-- Sidebar: SFTP credentials --}}
        <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#18181b;border-bottom:1px solid #27272a;">
                <div style="font-weight:700;color:#fafafa;font-size:13px;">SFTP credentials</div>
                <button type="button" wire:click="refreshStats"
                    style="background:transparent;border:none;cursor:pointer;color:#9ca3af;padding:4px;border-radius:4px;"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'"
                    title="Refresh stats">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
            </div>

            <div>
                @foreach ($creds as $c)
                    @php $active = $c['sftp_username'] === $selected; @endphp
                    <button type="button" wire:click="selectUser(@js($c['sftp_username']))"
                        style="display:block;width:100%;text-align:left;background:{{ $active ? 'rgba(234,88,12,0.10)' : 'transparent' }};border:none;border-bottom:1px solid #1f1f23;cursor:pointer;padding:10px 12px;transition:background 120ms;"
                        onmouseover="this.style.background='rgba(234,88,12,0.06)'"
                        onmouseout="this.style.background='{{ $active ? 'rgba(234,88,12,0.10)' : 'transparent' }}'">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                            <span style="font-weight:600;color:#fafafa;font-size:13px;">{{ $c['sftp_username'] }}</span>
                            <span style="font-size:11px;color:#fb923c;font-weight:700;">{{ $c['count'] }}</span>
                        </div>
                        <div style="font-size:11px;color:#71717a;margin-top:2px;">
                            {{ $c['owner_name'] }}
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:#52525b;margin-top:4px;">
                            <span>{{ $fmtSize($c['bytes']) }}</span>
                            <span>{{ $fmtAgo($c['last']) }}</span>
                        </div>
                    </button>
                @endforeach

                @if (empty($creds))
                    <div style="padding:14px;color:#71717a;font-size:12px;text-align:center;">
                        No active SFTP credentials.
                    </div>
                @endif
            </div>
        </div>

        {{-- Main: file list for selected user --}}
        <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
            @if ($selected === '')
                <div style="padding:48px 20px;color:#71717a;font-size:13px;text-align:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:48px;height:48px;margin:0 auto 12px;color:#3f3f46;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5m7.5 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5m7.5 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125" />
                    </svg>
                    Select a credential from the sidebar to browse demos.
                </div>
            @else
                {{-- Header --}}
                <div style="padding:10px 12px;background:#18181b;border-bottom:1px solid #27272a;display:flex;align-items:center;gap:10px;">
                    <button type="button" wire:click="goRoot"
                        style="background:transparent;border:none;cursor:pointer;color:#fb923c;padding:4px 8px;border-radius:6px;font-size:12px;font-weight:600;"
                        onmouseover="this.style.background='rgba(234,88,12,0.15)'" onmouseout="this.style.background='transparent'"
                        title="Back">
                        ← all users
                    </button>
                    <div style="font-weight:700;color:#fafafa;font-size:13px;">{{ $selected }}</div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="search by filename..."
                        style="flex:1;background:#0f0f17;border:1px solid #27272a;border-radius:6px;padding:6px 10px;color:#fafafa;font-size:12px;outline:none;"
                        onfocus="this.style.borderColor='#fb923c'" onblur="this.style.borderColor='#27272a'">
                    <span style="color:#71717a;font-size:11px;">{{ count($files) }} file(s)</span>
                </div>

                {{-- Table --}}
                <div style="overflow:auto;max-height:75vh;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead style="position:sticky;top:0;background:#18181b;z-index:1;">
                            <tr>
                                @foreach ([['name','Filename'],['map','Map'],['time','Run time'],['size','Size'],['mtime','Uploaded']] as [$col, $label])
                                    @php $arrow = $this->sortBy === $col ? ($this->sortDir === 'asc' ? '↑' : '↓') : ''; @endphp
                                    <th style="padding:8px 10px;text-align:left;color:#a1a1aa;font-weight:600;border-bottom:1px solid #27272a;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;cursor:pointer;user-select:none;"
                                        wire:click="setSort(@js($col))">
                                        {{ $label }} <span style="color:#fb923c;">{{ $arrow }}</span>
                                    </th>
                                @endforeach
                                <th style="padding:8px 10px;text-align:right;color:#a1a1aa;font-weight:600;border-bottom:1px solid #27272a;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $f)
                                <tr style="border-bottom:1px solid #1f1f23;"
                                    onmouseover="this.style.backgroundColor='rgba(234,88,12,0.06)'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                    <td style="padding:8px 10px;color:#fafafa;font-family:ui-monospace,monospace;">{{ $f['name'] }}</td>
                                    <td style="padding:8px 10px;color:#9ca3af;">{{ $f['map'] ?? '—' }}</td>
                                    <td style="padding:8px 10px;color:#9ca3af;font-family:ui-monospace,monospace;">{{ $fmtRunTime($f['time']) }}</td>
                                    <td style="padding:8px 10px;color:#9ca3af;">{{ $fmtSize($f['size']) }}</td>
                                    <td style="padding:8px 10px;color:#9ca3af;">{{ $fmtTime($f['mtime']) }}</td>
                                    <td style="padding:8px 10px;text-align:right;">
                                        <button type="button" wire:click="downloadFile(@js($f['path']))"
                                            style="background:transparent;border:1px solid #27272a;cursor:pointer;color:#fb923c;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;"
                                            onmouseover="this.style.background='rgba(234,88,12,0.15)';this.style.borderColor='#fb923c'"
                                            onmouseout="this.style.background='transparent';this.style.borderColor='#27272a'">
                                            Download
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:48px;text-align:center;color:#71717a;font-size:13px;">
                                        {{ $this->search ? 'No demos matching your search.' : 'No demos uploaded yet for this user.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
