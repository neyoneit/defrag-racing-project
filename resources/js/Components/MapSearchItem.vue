<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        map: Object,
        isRoute: {
            type: Boolean,
            default: true
        },
        click: Function
    });

    // When `isRoute` is true (header search) we render a real <a href>
    // via Inertia's <Link>. That makes middle-click / Ctrl-click /
    // right-click "Open in new tab" work the same as it does for
    // PlayerSearchItem and ModelSearchItem — the previous implementation
    // used a programmatic router.get() inside @click on a <div>, which
    // browsers can't middle-click open.
    //
    // When `isRoute` is false the parent passes its own click handler
    // (e.g. the picker on the maplist editor), so we keep that path.
    const mapHref = computed(() => `/maps/${encodeURIComponent(props.map.name)}`);

    const onPickerClick = () => {
        if (!props.isRoute) props.click(props.map.name);
    };
</script>

<template>
    <Link
        v-if="isRoute"
        :href="mapHref"
        class="group flex items-center gap-4 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-green-500/30 hover:shadow-lg hover:shadow-green-500/5"
    >
        <!-- Thumbnail -->
        <div class="shrink-0 w-12 h-12 rounded-lg ring-2 ring-white/10 group-hover:ring-green-500/50 transition-all overflow-hidden">
            <img v-if="map.thumbnail" :src="'/storage/' + map.thumbnail" :alt="map.name" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center bg-green-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-green-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
            </div>
        </div>

        <!-- Map Info -->
        <div class="flex-1 min-w-0">
            <div class="text-base font-black text-white group-hover:text-green-400 transition-colors truncate mb-0.5">{{ map.name }}</div>
            <div class="text-xs text-gray-400 font-semibold truncate">
                <span class="text-gray-500">by</span> {{ map?.author ?? 'Unknown' }}
            </div>
        </div>

        <!-- Arrow -->
        <svg class="w-5 h-5 text-gray-600 group-hover:text-green-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </Link>

    <div
        v-else
        class="group cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-green-500/30 hover:shadow-lg hover:shadow-green-500/5"
        @click="onPickerClick"
    >
        <div class="flex items-center gap-4">
            <div class="shrink-0 w-12 h-12 rounded-lg ring-2 ring-white/10 group-hover:ring-green-500/50 transition-all overflow-hidden">
                <img v-if="map.thumbnail" :src="'/storage/' + map.thumbnail" :alt="map.name" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-green-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-green-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-base font-black text-white group-hover:text-green-400 transition-colors truncate mb-0.5">{{ map.name }}</div>
                <div class="text-xs text-gray-400 font-semibold truncate">
                    <span class="text-gray-500">by</span> {{ map?.author ?? 'Unknown' }}
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-600 group-hover:text-green-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </div>
</template>
