<x-filament-panels::page>
    @php
        $categories = $this->categories;
        $selectedCat = $this->selectedCategory;

        $primaryBtn = 'background:#ea580c;color:#fff;border:none;padding:8px 14px;font-size:12px;font-weight:700;border-radius:6px;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px;transition:background-color 120ms;';
        $primaryBtnHover = "this.style.backgroundColor='#f97316'";
        $primaryBtnOut = "this.style.backgroundColor='#ea580c'";

        $secondaryBtn = 'background:transparent;color:#a1a1aa;border:1px solid #3f3f46;padding:8px 14px;font-size:12px;font-weight:600;border-radius:6px;cursor:pointer;transition:all 120ms;';
        $secondaryBtnHover = "this.style.backgroundColor='#27272a';this.style.color='#fafafa'";
        $secondaryBtnOut = "this.style.backgroundColor='transparent';this.style.color='#a1a1aa'";

        $iconBtnBase = 'background:transparent;border:none;cursor:pointer;padding:6px 8px;border-radius:6px;color:#9ca3af;transition:all 120ms;';
        $iconBtnHover = "this.style.backgroundColor='rgba(255,255,255,0.08)';this.style.color='#fff'";
        $iconBtnOut = "this.style.backgroundColor='transparent';this.style.color='#9ca3af'";

        $deleteBtnBase = 'background:transparent;border:none;cursor:pointer;padding:6px 8px;border-radius:6px;color:#ef4444;transition:all 120ms;';
        $deleteBtnHover = "this.style.backgroundColor='rgba(239,68,68,0.15)';this.style.color='#fca5a5'";
        $deleteBtnOut = "this.style.backgroundColor='transparent';this.style.color='#ef4444'";

        $inputStyle = 'background:#0a0a0f;color:#fafafa;border:1px solid #3f3f46;border-radius:6px;padding:8px 12px;font-size:14px;width:100%;box-sizing:border-box;';
    @endphp

    <div style="display:grid;grid-template-columns:280px 1fr;gap:14px;">
        {{-- ============= LEFT SIDEBAR: CATEGORIES ============= --}}
        <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;overflow:hidden;align-self:start;">
            <div style="padding:12px 14px;border-bottom:1px solid #27272a;display:flex;align-items:center;justify-content:space-between;">
                <h3 style="color:#fb923c;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:0;">Categories</h3>
                <button type="button"
                    wire:click="openCategoryForm"
                    style="background:rgba(234,88,12,0.15);color:#fb923c;border:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:18px;line-height:1;font-weight:700;"
                    onmouseover="this.style.backgroundColor='rgba(234,88,12,0.3)'"
                    onmouseout="this.style.backgroundColor='rgba(234,88,12,0.15)'"
                    title="New category">+</button>
            </div>
            <div
                x-data
                x-init="
                    new Sortable($el, {
                        handle: '.cat-drag-handle',
                        animation: 150,
                        ghostClass: 'sortable-ghost-cat',
                        onEnd: () => {
                            const ids = Array.from($el.querySelectorAll('[data-cat-id]')).map(el => el.dataset.catId);
                            $wire.call('reorderCategories', ids);
                        }
                    });
                "
            >
                @forelse ($categories as $cat)
                    @php $isActive = $selectedCat && $selectedCat->id === $cat->id; @endphp
                    <div data-cat-id="{{ $cat->id }}"
                        style="display:flex;align-items:center;border-left:3px solid {{ $isActive ? '#ea580c' : 'transparent' }};background:{{ $isActive ? 'rgba(234,88,12,0.10)' : 'transparent' }};transition:background-color 120ms;"
                        onmouseover="if(!{{ $isActive ? 'true' : 'false' }})this.style.backgroundColor='rgba(255,255,255,0.04)'"
                        onmouseout="if(!{{ $isActive ? 'true' : 'false' }})this.style.backgroundColor='transparent'">
                        <span class="cat-drag-handle" style="cursor:grab;padding:0 6px 0 8px;color:#52525b;user-select:none;font-size:14px;line-height:1;" title="Drag to reorder">⋮⋮</span>
                        <button type="button"
                            wire:click="selectCategory({{ $cat->id }})"
                            style="background:transparent;border:none;cursor:pointer;flex:1;text-align:left;padding:10px 14px;color:{{ $isActive ? '#fb923c' : '#e4e4e7' }};font-size:13px;font-weight:{{ $isActive ? '700' : '500' }};display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0;">
                            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cat->name }}</span>
                            <span style="background:{{ $isActive ? 'rgba(234,88,12,0.25)' : 'rgba(255,255,255,0.06)' }};color:{{ $isActive ? '#fb923c' : '#71717a' }};font-size:10px;font-weight:700;padding:2px 6px;border-radius:8px;flex-shrink:0;">{{ $cat->bundles_count }}</span>
                        </button>
                        <div style="display:flex;gap:2px;padding:0 8px;">
                            <button type="button"
                                wire:click="openCategoryForm({{ $cat->id }})"
                                style="{{ $iconBtnBase }}padding:4px 6px;"
                                onmouseover="{{ $iconBtnHover }}"
                                onmouseout="{{ $iconBtnOut }}"
                                title="Edit category">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <button type="button"
                                wire:click="deleteCategory({{ $cat->id }})"
                                wire:confirm="Delete category '{{ $cat->name }}'?"
                                style="{{ $deleteBtnBase }}padding:4px 6px;"
                                onmouseover="{{ $deleteBtnHover }}"
                                onmouseout="{{ $deleteBtnOut }}"
                                title="Delete category">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div style="padding:20px 14px;text-align:center;color:#71717a;font-size:12px;">
                        No categories yet.<br>Click <strong style="color:#fb923c;">+</strong> to create one.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ============= RIGHT MAIN: BUNDLES ============= --}}
        <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
            <div style="padding:12px 14px;border-bottom:1px solid #27272a;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <h2 style="color:#fafafa;font-size:16px;font-weight:800;margin:0;">{{ $selectedCat?->name ?? 'No category selected' }}</h2>
                    @if($selectedCat)
                        <p style="color:#71717a;font-size:11px;margin:2px 0 0 0;">{{ $selectedCat->bundles->count() }} bundle(s)</p>
                    @endif
                </div>
                @if($selectedCat)
                    <button type="button"
                        wire:click="openBundleForm"
                        style="{{ $primaryBtn }}"
                        onmouseover="{{ $primaryBtnHover }}"
                        onmouseout="{{ $primaryBtnOut }}">
                        + New Bundle
                    </button>
                @endif
            </div>

            @if($selectedCat)
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#18181b;">
                            <th style="width:24px;"></th>
                            <th style="text-align:left;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Name</th>
                            <th style="text-align:left;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">URL</th>
                            <th style="text-align:right;padding:10px 14px;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        x-data
                        x-init="
                            new Sortable($el, {
                                handle: '.bundle-drag-handle',
                                animation: 150,
                                ghostClass: 'sortable-ghost-bundle',
                                onEnd: () => {
                                    const ids = Array.from($el.querySelectorAll('tr[data-bundle-id]')).map(el => el.dataset.bundleId);
                                    $wire.call('reorderBundles', ids);
                                }
                            });
                        "
                    >
                        @forelse($selectedCat->bundles as $bundle)
                            <tr data-bundle-id="{{ $bundle->id }}" style="border-top:1px solid #27272a;transition:background-color 120ms;"
                                onmouseover="this.style.backgroundColor='rgba(234,88,12,0.08)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                <td style="padding:10px 14px;width:24px;">
                                    <span class="bundle-drag-handle" style="cursor:grab;color:#52525b;user-select:none;font-size:14px;" title="Drag to reorder">⋮⋮</span>
                                </td>
                                <td style="padding:10px 14px;">
                                    <div style="color:#fafafa;font-weight:600;font-size:14px;">{{ $bundle->name }}</div>
                                    @if($bundle->description)
                                        <div style="color:#71717a;font-size:11px;margin-top:2px;line-height:1.4;max-width:400px;">{{ Str::limit($bundle->description, 100) }}</div>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;color:#22d3ee;font-size:11px;font-family:monospace;word-break:break-all;max-width:300px;">
                                    {{ $bundle->url ?: ($bundle->file ? '/storage/' . $bundle->file : '—') }}
                                </td>
                                <td style="padding:10px 14px;">
                                    <div style="display:flex;justify-content:flex-end;gap:4px;">
                                        <button type="button"
                                            wire:click="openBundleForm({{ $bundle->id }})"
                                            style="{{ $iconBtnBase }}"
                                            onmouseover="{{ $iconBtnHover }}"
                                            onmouseout="{{ $iconBtnOut }}"
                                            title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                            wire:click="deleteBundle({{ $bundle->id }})"
                                            wire:confirm="Delete bundle '{{ $bundle->name }}'?"
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
                            <tr>
                                <td colspan="4" style="padding:32px 14px;text-align:center;color:#71717a;border-top:1px solid #27272a;">
                                    No bundles in this category. Click <strong style="color:#fb923c;">+ New Bundle</strong> to add one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <div style="padding:48px;text-align:center;color:#71717a;">
                    Select a category from the sidebar, or create one.
                </div>
            @endif
        </div>
    </div>

    {{-- ============= CATEGORY FORM MODAL ============= --}}
    @if($categoryFormOpen)
        <div wire:click.self="closeCategoryForm" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:50;display:flex;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;padding:20px;width:100%;max-width:420px;">
                <h2 style="color:#fb923c;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:0 0 16px 0;">
                    {{ $editingCategoryId ? 'Edit category' : 'New category' }}
                </h2>
                <div style="margin-bottom:16px;">
                    <label style="display:block;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Name</label>
                    <input type="text" wire:model="categoryForm.name" wire:keydown.enter="saveCategory" autofocus style="{{ $inputStyle }}" />
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="closeCategoryForm" style="{{ $secondaryBtn }}" onmouseover="{{ $secondaryBtnHover }}" onmouseout="{{ $secondaryBtnOut }}">Cancel</button>
                    <button type="button" wire:click="saveCategory" style="{{ $primaryBtn }}" onmouseover="{{ $primaryBtnHover }}" onmouseout="{{ $primaryBtnOut }}">Save</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============= BUNDLE FORM MODAL ============= --}}
    @if($bundleFormOpen)
        <div wire:click.self="closeBundleForm" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:50;display:flex;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;padding:20px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;">
                <h2 style="color:#fb923c;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:0 0 16px 0;">
                    {{ $editingBundleId ? 'Edit bundle' : 'New bundle' }} <span style="color:#71717a;font-weight:500;text-transform:none;letter-spacing:0;">in {{ $selectedCat?->name }}</span>
                </h2>

                <div style="margin-bottom:14px;">
                    <label style="display:block;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Name</label>
                    <input type="text" wire:model="bundleForm.name" style="{{ $inputStyle }}" />
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Description</label>
                    <textarea wire:model="bundleForm.description" rows="3" style="{{ $inputStyle }}resize:vertical;font-family:inherit;"></textarea>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;color:#a1a1aa;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Download URL</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" wire:model="bundleForm.url" placeholder="https://dl.defrag.racing/downloads/..." style="{{ $inputStyle }}font-family:monospace;font-size:12px;" />
                        <button type="button"
                            wire:click="openPicker"
                            style="background:#1f2937;color:#22d3ee;border:1px solid #22d3ee;padding:8px 14px;font-size:12px;font-weight:700;border-radius:6px;cursor:pointer;white-space:nowrap;flex-shrink:0;transition:all 120ms;"
                            onmouseover="this.style.backgroundColor='rgba(34,211,238,0.15)'"
                            onmouseout="this.style.backgroundColor='#1f2937'">
                            Browse storage
                        </button>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="closeBundleForm" style="{{ $secondaryBtn }}" onmouseover="{{ $secondaryBtnHover }}" onmouseout="{{ $secondaryBtnOut }}">Cancel</button>
                    <button type="button" wire:click="saveBundle" style="{{ $primaryBtn }}" onmouseover="{{ $primaryBtnHover }}" onmouseout="{{ $primaryBtnOut }}">Save bundle</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============= STORAGE PICKER MODAL ============= --}}
    @if($pickerOpen)
        @php
            $pl = $this->pickerListing;
            $pcrumbs = $this->pickerBreadcrumbs;
        @endphp
        <div wire:click.self="closePicker" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#0f0f17;border:1px solid #27272a;border-radius:12px;padding:0;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
                <div style="padding:14px 16px;border-bottom:1px solid #27272a;display:flex;align-items:center;justify-content:space-between;">
                    <h2 style="color:#22d3ee;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:0;">Pick file from storage VPS</h2>
                    <button type="button" wire:click="closePicker" style="background:transparent;border:none;cursor:pointer;color:#a1a1aa;font-size:20px;line-height:1;padding:4px 8px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#a1a1aa'">×</button>
                </div>

                {{-- Breadcrumb + Upload --}}
                <div style="padding:10px 16px;border-bottom:1px solid #27272a;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:4px;font-size:13px;flex-wrap:wrap;">
                        <button type="button" wire:click="pickerGoRoot" style="background:transparent;border:none;cursor:pointer;color:#fb923c;font-weight:700;padding:4px 8px;border-radius:6px;" onmouseover="this.style.backgroundColor='rgba(234,88,12,0.15)'" onmouseout="this.style.backgroundColor='transparent'">/</button>
                        @foreach($pcrumbs as $i => $crumb)
                            <span style="color:#52525b;">/</span>
                            @if($i === count($pcrumbs)-1)
                                <span style="color:#fafafa;font-weight:700;padding:4px 8px;">{{ $crumb['name'] }}</span>
                            @else
                                <button type="button" wire:click="pickerGoTo(@js($crumb['path']))" style="background:transparent;border:none;cursor:pointer;color:#fb923c;font-weight:600;padding:4px 8px;border-radius:6px;" onmouseover="this.style.backgroundColor='rgba(234,88,12,0.15)'" onmouseout="this.style.backgroundColor='transparent'">{{ $crumb['name'] }}</button>
                            @endif
                        @endforeach
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <label style="background:#1f2937;color:#22d3ee;border:1px solid #22d3ee;padding:6px 12px;font-size:11px;font-weight:700;border-radius:6px;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;">
                            <input type="file" wire:model="pickerUploadFile" style="display:none;" />
                            {{ $pickerUploadFile ? $pickerUploadFile->getClientOriginalName() : 'Choose file' }}
                        </label>
                        @if($pickerUploadFile)
                            <button type="button" wire:click="pickerUploadHere" style="{{ $primaryBtn }}padding:6px 12px;font-size:11px;" onmouseover="{{ $primaryBtnHover }}" onmouseout="{{ $primaryBtnOut }}">Upload here</button>
                        @endif
                    </div>
                </div>

                {{-- Listing --}}
                <div style="overflow-y:auto;flex:1;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <tbody>
                            @if($pickerPath !== '')
                                <tr style="border-top:1px solid #27272a;transition:background-color 120ms;cursor:pointer;"
                                    onmouseover="this.style.backgroundColor='rgba(234,88,12,0.08)'"
                                    onmouseout="this.style.backgroundColor='transparent'"
                                    wire:click="pickerGoUp">
                                    <td style="padding:8px 16px;width:32px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;color:#71717a;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                        </svg>
                                    </td>
                                    <td style="padding:8px 16px;color:#fb923c;font-weight:600;">..</td>
                                    <td></td>
                                </tr>
                            @endif

                            @foreach($pl['dirs'] as $dir)
                                <tr style="border-top:1px solid #27272a;transition:background-color 120ms;cursor:pointer;"
                                    onmouseover="this.style.backgroundColor='rgba(234,88,12,0.08)'"
                                    onmouseout="this.style.backgroundColor='transparent'"
                                    wire:click="pickerEnterDir(@js($dir['name']))">
                                    <td style="padding:8px 16px;width:32px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;color:#fb923c;">
                                            <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                        </svg>
                                    </td>
                                    <td style="padding:8px 16px;color:#fb923c;font-weight:600;">{{ $dir['name'] }}</td>
                                    <td></td>
                                </tr>
                            @endforeach

                            @foreach($pl['files'] as $file)
                                <tr style="border-top:1px solid #27272a;transition:background-color 120ms;cursor:pointer;"
                                    onmouseover="this.style.backgroundColor='rgba(34,211,238,0.10)'"
                                    onmouseout="this.style.backgroundColor='transparent'"
                                    wire:click="pickerSelectFile(@js($file['path']))">
                                    <td style="padding:8px 16px;width:32px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;color:#22d3ee;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </td>
                                    <td style="padding:8px 16px;color:#fafafa;font-weight:500;">{{ $file['name'] }}</td>
                                    <td style="padding:8px 16px;color:#71717a;font-size:11px;text-align:right;font-variant-numeric:tabular-nums;">{{ $this->formatBytes($file['size']) }}</td>
                                </tr>
                            @endforeach

                            @if(empty($pl['dirs']) && empty($pl['files']) && $pickerPath === '')
                                <tr>
                                    <td colspan="3" style="padding:32px 16px;text-align:center;color:#71717a;">Empty.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div style="padding:10px 16px;border-top:1px solid #27272a;color:#71717a;font-size:11px;">
                    Click on a <strong style="color:#22d3ee;">file</strong> to use its URL. Click on a <strong style="color:#fb923c;">folder</strong> to navigate.
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
