<script setup>
    import { router } from '@inertiajs/vue3';

    const props = defineProps({
        map: Object,
        isRoute: {
            type: Boolean,
            default: true
        },
        click: Function
    });

    const onClick = () => {
        if (props.isRoute) {
            // Manually encode the mapname to handle special characters like #
            const encodedMapname = encodeURIComponent(props.map.name);
            router.get(`/maps/${encodedMapname}`)
            return
        }

        props.click(props.map.name)
    }
</script>

<template>
    <div class="group cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-green-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-green-500/5" @click="onClick">
        <div class="flex items-center gap-4">
            <!-- Icon -->
            <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-green-500/20 ring-2 ring-white/10 group-hover:ring-green-500/50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-green-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
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
        </div>
    </div>
</template>