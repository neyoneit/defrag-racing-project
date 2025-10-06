<script setup>
    import { router } from '@inertiajs/vue3';
    import { useClipboard } from '@/Composables/useClipboard';
    
    const { copy } = useClipboard();

    const props = defineProps({
        map: Object,
        isRoute: {
            type: Boolean,
            default: true
        },
        click: Function
    });

    let weaponsList = props.map?.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map?.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map?.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const onClick = () => {
        if (props.isRoute) {
            router.get(route('maps.map', (props.map.name)))
            return
        }

        props.click(props.map.name)
    }
</script>

<template>
    <div class="group cursor-pointer rounded-lg hover:bg-white/5 p-2.5 transition-all border border-transparent hover:border-white/10" @click="onClick">
        <div class="flex gap-3">
            <!-- Thumbnail -->
            <div class="shrink-0">
                <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${map?.thumbnail}`" class="rounded-lg object-cover ring-1 ring-white/10 group-hover:ring-green-500/40 transition-all" style="width: 80px; height: 60px;">
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 flex items-center justify-between">
                <div class="min-w-0">
                    <!-- Map name -->
                    <div class="flex items-center gap-2 mb-1">
                        <div class="text-sm font-bold text-white group-hover:text-green-400 transition-colors truncate">{{ map.name }}</div>
                        <button class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-500 hover:text-green-400" @click.stop="copy(map?.name ?? mapname)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                            </svg>
                        </button>
                    </div>

                    <!-- Author -->
                    <div class="text-xs text-gray-500 truncate" style="max-width: 250px;">
                        <span>by </span><span :title="map?.author">{{ map?.author ?? 'Unknown' }}</span>
                    </div>
                </div>

                <!-- Items/Weapons -->
                <div class="shrink-0 flex items-center gap-1">
                    <div class="flex flex-wrap gap-0.5" v-if="weaponsList.length > 0">
                        <div v-for="weapon in weaponsList" :key="weapon" :title="weapon" :class="`sprite-items sprite-${weapon} w-4 h-4`"></div>
                    </div>
                    <div class="flex flex-wrap gap-0.5" v-if="itemsList.length > 0">
                        <div v-for="item in itemsList" :key="item" :title="item" :class="`sprite-items sprite-${item} w-4 h-4`"></div>
                    </div>
                    <div class="flex flex-wrap gap-0.5" v-if="functionsList.length > 0">
                        <div v-for="func in functionsList" :key="func" :title="func" :class="`sprite-items sprite-${func} w-4 h-4`"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>