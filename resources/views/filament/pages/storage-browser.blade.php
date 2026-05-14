<x-filament-panels::page>
    @php
        $listing = $this->listing;
        $crumbs = $this->breadcrumbs;
        $atRoot = $this->currentPath === '';
        $rowBase = 'background:transparent;transition:background-color 120ms ease;';
        $rowHover = "this.style.backgroundColor='rgba(234,88,12,0.10)'";
        $rowOut = "this.style.backgroundColor='transparent'";
        $iconBtnBase = 'background:transparent;border:none;cursor:pointer;padding:6px 8px;border-radius:6px;color:#9ca3af;transition:all 120ms;';
        $iconBtnHover = "this.style.backgroundColor='rgba(255,255,255,0.08)';this.style.color='#fff'";
        $iconBtnOut = "this.style.backgroundColor='transparent';this.style.color='#9ca3af'";
        $deleteBtnBase = 'background:transparent;border:none;cursor:pointer;padding:6px 8px;border-radius:6px;color:#ef4444;transition:all 120ms;';
        $deleteBtnHover = "this.style.backgroundColor='rgba(239,68,68,0.15)';this.style.color='#fca5a5'";
        $deleteBtnOut = "this.style.backgroundColor='transparent';this.style.color='#ef4444'";
        $crumbBtn = 'background:transparent;border:none;cursor:pointer;padding:4px 8px;border-radius:6px;color:#fb923c;font-weight:600;font-size:13px;transition:background-color 120ms;';
    @endphp

    {{-- Breadcrumb --}}
    <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;padding:10px 14px;">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:13px;">
            <button
                type="button"
                wire:click="goRoot"
                style="background:transparent;border:none;cursor:pointer;padding:4px 8px;border-radius:6px;color:#fb923c;font-weight:700;display:flex;align-items:center;gap:4px;transition:background-color 120ms;"
                onmouseover="this.style.backgroundColor='rgba(234,88,12,0.15)'"
                onmouseout="this.style.backgroundColor='transparent'"
                title="Go to root"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                <span>/</span>
            </button>

            @foreach ($crumbs as $i => $crumb)
                <span style="color:#52525b;">/</span>
                @if ($i === count($crumbs) - 1)
                    <span style="color:#fafafa;font-weight:700;padding:4px 8px;">{{ $crumb['name'] }}</span>
                @else
                    <button
                        type="button"
                        wire:click="goTo(@js($crumb['path']))"
                        style="{{ $crumbBtn }}"
                        onmouseover="this.style.backgroundColor='rgba(234,88,12,0.15)'"
                        onmouseout="this.style.backgroundColor='transparent'"
                    >{{ $crumb['name'] }}</button>
                @endif
            @endforeach

            <span style="margin-left:auto;color:#71717a;font-size:11px;">
                {{ count($listing['dirs']) }} folder(s) &middot; {{ count($listing['files']) }} file(s)
            </span>
        </div>
    </div>

    {{-- Listing --}}
    <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;overflow:hidden;margin-top:14px;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#18181b;">
                    <th style="text-align:left;padding:10px 14px;width:32px;"></th>
                    <th style="text-align:left;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Name</th>
                    <th style="text-align:right;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;width:120px;">Size</th>
                    <th style="text-align:right;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;width:170px;">Modified</th>
                    <th style="text-align:right;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @if (! $atRoot)
                    <tr style="{{ $rowBase }}border-top:1px solid #27272a;"
                        onmouseover="{{ $rowHover }}"
                        onmouseout="{{ $rowOut }}">
                        <td style="padding:8px 14px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;color:#71717a;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                            </svg>
                        </td>
                        <td style="padding:8px 14px;">
                            <button
                                type="button"
                                wire:click="goUp"
                                style="background:transparent;border:none;cursor:pointer;padding:0;color:#fb923c;font-weight:600;font-size:14px;text-decoration:none;"
                                onmouseover="this.style.textDecoration='underline'"
                                onmouseout="this.style.textDecoration='none'"
                            >..</button>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                @endif

                @forelse ($listing['dirs'] as $dir)
                    <tr style="{{ $rowBase }}border-top:1px solid #27272a;"
                        onmouseover="{{ $rowHover }}"
                        onmouseout="{{ $rowOut }}">
                        <td style="padding:8px 14px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;color:#fb923c;">
                                <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                            </svg>
                        </td>
                        <td style="padding:8px 14px;">
                            <button
                                type="button"
                                wire:click="enterDir(@js($dir['name']))"
                                style="background:transparent;border:none;cursor:pointer;padding:0;color:#fb923c;font-weight:600;font-size:14px;text-align:left;"
                                onmouseover="this.style.textDecoration='underline'"
                                onmouseout="this.style.textDecoration='none'"
                            >{{ $dir['name'] }}</button>
                        </td>
                        <td style="padding:8px 14px;text-align:right;color:#52525b;">&mdash;</td>
                        <td style="padding:8px 14px;text-align:right;color:#52525b;">&mdash;</td>
                        <td style="padding:8px 14px;">
                            <div style="display:flex;justify-content:flex-end;gap:4px;">
                                <button type="button"
                                    wire:click="mountAction('rename', @js(['oldName' => $dir['name']]))"
                                    style="{{ $iconBtnBase }}"
                                    onmouseover="{{ $iconBtnHover }}"
                                    onmouseout="{{ $iconBtnOut }}"
                                    title="Rename">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </button>
                                <button type="button"
                                    wire:click="mountAction('delete', @js(['name' => $dir['name'], 'type' => 'dir']))"
                                    style="{{ $deleteBtnBase }}"
                                    onmouseover="{{ $deleteBtnHover }}"
                                    onmouseout="{{ $deleteBtnOut }}"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    @if (empty($listing['files']))
                        <tr>
                            <td colspan="5" style="padding:32px 14px;text-align:center;color:#71717a;border-top:1px solid #27272a;">
                                Empty folder. Use <strong style="color:#fb923c;">Upload files</strong> or <strong style="color:#fb923c;">New folder</strong> above.
                            </td>
                        </tr>
                    @endif
                @endforelse

                @foreach ($listing['files'] as $file)
                    <tr style="{{ $rowBase }}border-top:1px solid #27272a;"
                        onmouseover="{{ $rowHover }}"
                        onmouseout="{{ $rowOut }}">
                        <td style="padding:8px 14px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;color:#22d3ee;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </td>
                        <td style="padding:8px 14px;">
                            <span style="color:#fafafa;font-weight:500;font-size:14px;">{{ $file['name'] }}</span>
                        </td>
                        <td style="padding:8px 14px;text-align:right;color:#a1a1aa;font-variant-numeric:tabular-nums;font-size:12px;">
                            {{ $this->formatBytes($file['size']) }}
                        </td>
                        <td style="padding:8px 14px;text-align:right;color:#71717a;font-variant-numeric:tabular-nums;font-size:11px;">
                            @if ($file['mtime'])
                                {{ \Carbon\Carbon::createFromTimestamp($file['mtime'])->format('Y-m-d H:i') }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td style="padding:8px 14px;">
                            <div style="display:flex;justify-content:flex-end;gap:4px;">
                                <button type="button"
                                    wire:click="downloadFile(@js($file['name']))"
                                    style="{{ $iconBtnBase }}"
                                    onmouseover="{{ $iconBtnHover }}"
                                    onmouseout="{{ $iconBtnOut }}"
                                    title="Download">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </button>
                                <button type="button"
                                    wire:click="mountAction('rename', @js(['oldName' => $file['name']]))"
                                    style="{{ $iconBtnBase }}"
                                    onmouseover="{{ $iconBtnHover }}"
                                    onmouseout="{{ $iconBtnOut }}"
                                    title="Rename">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </button>
                                <button type="button"
                                    wire:click="mountAction('delete', @js(['name' => $file['name'], 'type' => 'file']))"
                                    style="{{ $deleteBtnBase }}"
                                    onmouseover="{{ $deleteBtnHover }}"
                                    onmouseout="{{ $deleteBtnOut }}"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
