<script setup>
    import { ref, computed, watch } from 'vue';
    import { Link } from '@inertiajs/vue3';

    // A player's freestyle and trick demos, split VQ3 and CPM the way the rest
    // of the site splits everything. A row is a map; it opens to show that
    // map's demos, unless there is only one, which is shown in the row itself.
    //
    // These demos belong to no record, so nothing else on a profile can reach
    // them: they are matched here by the nick on the demo.
    const props = defineProps({
        data: {
            type: Object,
            default: () => ({ total: 0, search: '', vq3: null, cpm: null }),
        },
        dateFormat: { type: String, default: 'ymd' },
        cpmFirst: { type: Boolean, default: false },
    });

    const emit = defineEmits(['page', 'search']);

    const open = ref(new Set());
    const search = ref(props.data?.search || '');
    let searchTimer = null;

    const columns = computed(() => {
        const vq3 = { key: 'vq3', label: 'VQ3', block: props.data?.vq3, accent: 'blue' };
        const cpm = { key: 'cpm', label: 'CPM', block: props.data?.cpm, accent: 'orange' };
        return props.cpmFirst ? [cpm, vq3] : [vq3, cpm];
    });

    const rowKey = (physics, map) => physics + ':' + map;
    const isOpen = (physics, map) => open.value.has(rowKey(physics, map));

    const toggle = (physics, map, count) => {
        if (count <= 1) return;

        const next = new Set(open.value);
        const key = rowKey(physics, map);
        next.has(key) ? next.delete(key) : next.add(key);
        open.value = next;
    };

    const goToPage = (physics, page, block) => {
        if (page < 1 || page > (block?.last_page || 1)) return;
        open.value = new Set();
        emit('page', { physics, page });
    };

    watch(search, (value) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            if (value.trim() === (props.data?.search || '')) return;
            open.value = new Set();
            emit('search', value.trim());
        }, 350);
    });

    watch(() => props.data?.search, (value) => {
        if ((value || '') !== search.value.trim()) search.value = value || '';
    });

    const fmtDate = (value) => {
        if (! value) return '-';

        const d = new Date(value);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yy = String(d.getFullYear()).slice(-2);
        const yyyy = String(d.getFullYear());

        if (props.dateFormat === 'dmY') return `${dd}/${mm}/${yyyy}`;
        if (props.dateFormat === 'Ymd') return `${yyyy}/${mm}/${dd}`;
        if (props.dateFormat === 'dmy') return `${dd}/${mm}/${yy}`;
        return `${yy}/${mm}/${dd}`;
    };
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-sm">
                <svg class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Filter by map name..."
                    class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-9 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-teal-400/40 transition-colors"
                />
                <button
                    v-if="search"
                    @click="search = ''"
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white transition-colors"
                    title="Clear"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="text-xs text-gray-500 sm:ml-auto sm:text-right max-w-md">
                No time on these - tricks, tutorials, runs that were never finished.
                <span class="text-gray-400">{{ data.total }} in total.</span>
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div
                v-for="column in columns"
                :key="column.key"
                class="rounded-xl border overflow-hidden flex flex-col"
                :class="column.accent === 'orange'
                    ? 'border-orange-500/20 bg-orange-500/[0.03]'
                    : 'border-blue-500/20 bg-blue-500/[0.03]'"
            >
                <div
                    class="flex items-center justify-between px-4 py-2.5 border-b"
                    :class="column.accent === 'orange'
                        ? 'border-orange-500/20 bg-orange-500/[0.06]'
                        : 'border-blue-500/20 bg-blue-500/[0.06]'"
                >
                    <span
                        class="text-sm font-black tracking-wider"
                        :class="column.accent === 'orange' ? 'text-orange-300' : 'text-blue-300'"
                    >{{ column.label }}</span>
                    <span class="text-xs text-gray-400 tabular-nums">
                        {{ column.block?.total || 0 }} on {{ column.block?.map_total || 0 }}
                        {{ (column.block?.map_total || 0) === 1 ? 'map' : 'maps' }}
                    </span>
                </div>

                <div v-if="!column.block || column.block.map_total === 0" class="px-4 py-10 text-center text-sm text-gray-600">
                    {{ data.search ? 'No map matches that.' : 'Nothing here.' }}
                </div>

                <div v-else class="flex-1">
                    <div
                        v-for="map in column.block.maps"
                        :key="map.map_name"
                        class="border-b border-white/5 last:border-0"
                    >
                        <div
                            @click="toggle(column.key, map.map_name, map.count)"
                            class="flex items-center gap-2 px-4 py-2.5 transition-colors"
                            :class="map.count > 1
                                ? 'cursor-pointer hover:bg-white/[0.04]'
                                : 'hover:bg-white/[0.02]'"
                        >
                            <svg
                                v-if="map.count > 1"
                                class="w-3.5 h-3.5 shrink-0 text-gray-500 transition-transform"
                                :class="isOpen(column.key, map.map_name) ? 'rotate-90' : ''"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                            </svg>
                            <span v-else class="w-3.5 shrink-0"></span>

                            <Link
                                :href="`/maps/${encodeURIComponent(map.map_name)}?freestyle`"
                                class="font-bold text-white hover:text-teal-300 transition-colors truncate"
                                @click.stop
                            >{{ map.map_name }}</Link>

                            <!-- One demo has nothing to expand into, so it says
                                 its name and offers its buttons right here. -->
                            <template v-if="map.count === 1 && map.demos && map.demos.length">
                                <span class="text-gray-600 shrink-0">/</span>
                                <span class="text-sm text-gray-400 truncate flex-1 min-w-0">{{ map.demos[0].label }}</span>
                                <a
                                    v-if="map.demos[0].video_url"
                                    :href="map.demos[0].video_url"
                                    target="_blank" rel="noopener"
                                    class="shrink-0 text-red-400 hover:text-red-300"
                                    title="Watch the render"
                                    @click.stop
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                                        <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#000"/>
                                    </svg>
                                </a>
                                <span v-if="map.demos[0].download_count" class="shrink-0 text-xs text-gray-600 tabular-nums">{{ map.demos[0].download_count }}</span>
                                <a
                                    :href="`/demos/${map.demos[0].id}/download`"
                                    class="shrink-0 text-gray-500 hover:text-teal-300"
                                    title="Download the demo"
                                    @click.stop
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </a>
                            </template>

                            <template v-else>
                                <svg v-if="map.videos > 0" class="w-4 h-4 shrink-0 text-red-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                                    <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#000"/>
                                </svg>
                                <span class="ml-auto shrink-0 text-sm font-bold text-teal-400 tabular-nums">{{ map.count }}</span>
                                <span class="shrink-0 text-xs text-gray-600 tabular-nums w-10 text-right hidden sm:inline">{{ map.downloads || '' }}</span>
                            </template>

                            <span class="shrink-0 text-xs text-gray-600 whitespace-nowrap hidden md:inline w-16 text-right">{{ fmtDate(map.latest) }}</span>
                        </div>

                        <div v-if="isOpen(column.key, map.map_name)" class="bg-black/30 px-4 py-2 border-t border-white/5">
                            <div
                                v-for="demo in map.demos"
                                :key="demo.id"
                                class="flex items-center gap-2 py-1.5 text-sm"
                            >
                                <span class="text-gray-300 truncate flex-1 min-w-0">{{ demo.label }}</span>
                                <a
                                    v-if="demo.video_url"
                                    :href="demo.video_url"
                                    target="_blank" rel="noopener"
                                    class="shrink-0 text-red-400 hover:text-red-300"
                                    title="Watch the render"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                                        <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#000"/>
                                    </svg>
                                </a>
                                <span class="shrink-0 text-xs text-gray-600 tabular-nums w-8 text-right">{{ demo.download_count || '' }}</span>
                                <a
                                    :href="`/demos/${demo.id}/download`"
                                    class="shrink-0 text-gray-500 hover:text-teal-300"
                                    title="Download the demo"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </a>
                                <span class="shrink-0 text-xs text-gray-600 whitespace-nowrap hidden md:inline w-16 text-right">{{ fmtDate(demo.record_date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="column.block && column.block.last_page > 1" class="flex items-center justify-between px-4 py-2.5 border-t border-white/5">
                    <button
                        @click="goToPage(column.key, column.block.page - 1, column.block)"
                        :disabled="column.block.page <= 1"
                        class="px-2.5 py-1 rounded text-xs bg-white/5 text-gray-300 hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-white/5"
                    >Previous</button>
                    <span class="text-xs text-gray-500">{{ column.block.page }} / {{ column.block.last_page }}</span>
                    <button
                        @click="goToPage(column.key, column.block.page + 1, column.block)"
                        :disabled="column.block.page >= column.block.last_page"
                        class="px-2.5 py-1 rounded text-xs bg-white/5 text-gray-300 hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-white/5"
                    >Next</button>
                </div>
            </div>
        </div>
    </div>
</template>
