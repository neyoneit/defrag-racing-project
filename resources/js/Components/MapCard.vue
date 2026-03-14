<script setup>
    import moment from 'moment';
    import { computed, ref } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';
    import { useClipboard } from '@/Composables/useClipboard';
    import AddToMaplistModal from '@/Components/Maplists/AddToMaplistModal.vue';

    const { copy, copyState } = useClipboard();
    const page = usePage();
    const showMaplistModal = ref(false);

    const props = defineProps({
        map: Object,
        transparent: {
            type: Boolean,
            default: true
        }
    });

    let weaponsList = props.map.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const background = computed(() => {
        const physics = props.map.physics.toLowerCase()
        const bgs = {
            cpm: 'cpm-badge',
            vq3: 'vq3-badge',
            all: 'bg-blackop-80'
        }

        if (physics in bgs) {
            return bgs[physics]
        }

        return bgs['all']
    });

    const date = computed(() => {
        const time = props.map.created_at.split('T')[1].split('.')[0];

        const result = props.map.date_added.split(' ')[0] + ' ' + time;

        return moment(result).format('DD.MM.YY');
    });

    const getGametype = computed(() => {
        let gametype = props.map?.gametype;

        if (!gametype) {
            return 'run'
        }

        return (gametype == 'fastcaps') ? 'ctf2' : 'run';
    });

    const getRouteData = computed(() => {
        let data ={
            mapname: props.map?.name
        }

        if (getGametype.value !== 'run') {
            data.gametype = 'ctf2';
        }

        return data;
    });
</script>

<template>
    <Link :href="getGametype === 'run' ? `/maps/${encodeURIComponent(map.name)}` : `/maps/${encodeURIComponent(map.name)}?gametype=ctf2`" class="block group">
        <div class="backdrop-blur-xl bg-black/40 border border-white/5 rounded-xl overflow-hidden hover:border-white/20 transition-all shadow-2xl hover:shadow-blue-500/20">
            <!-- Map Thumbnail -->
            <div class="relative w-full aspect-video bg-cover bg-center" :style="`background-image: url('/storage/${map.thumbnail}')`">
                <!-- Physics Badge -->
                <div class="absolute top-2 right-2">
                    <div :class="`px-2 py-0.5 rounded text-[11px] font-bold uppercase text-white ${background}`">
                        {{ map.physics }}
                    </div>
                </div>

                <!-- Items Overlay - Bottom Right -->
                <div class="absolute bottom-2 right-2 flex flex-col gap-0.5">
                    <div v-if="weaponsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 backdrop-blur-sm rounded px-1 py-0.5">
                        <div v-for="weapon in weaponsList" :key="weapon" :title="weapon" :class="`sprite-items sprite-${weapon} w-3 h-3`"></div>
                    </div>
                    <div v-if="itemsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 backdrop-blur-sm rounded px-1 py-0.5">
                        <div v-for="item in itemsList" :key="item" :title="item" :class="`sprite-items sprite-${item} w-3 h-3`"></div>
                    </div>
                    <div v-if="functionsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 backdrop-blur-sm rounded px-1 py-0.5">
                        <div v-for="func in functionsList" :key="func" :title="func" :class="`sprite-items sprite-${func} w-3 h-3`"></div>
                    </div>
                </div>
            </div>

            <!-- Map Info -->
            <div class="p-2.5">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <h3 class="text-sm font-bold text-blue-400 group-hover:text-blue-300 transition-colors truncate">
                        {{ map.name }}
                    </h3>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <!-- Copy Button -->
                        <Popper :closeDelay="300" hover style="z-index: 100;">
                            <button
                                @click.prevent="copy(map.name)"
                                class="p-0.5 text-gray-400 hover:text-green-400 rounded transition-colors"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                                </svg>
                            </button>
                            <template #content>
                                <div class="px-2 py-1 rounded bg-black/90 text-xs">
                                    <span v-if="!copyState" class="text-gray-300">Copy</span>
                                    <span v-else class="text-green-400">Copied!</span>
                                </div>
                            </template>
                        </Popper>

                        <!-- Add to Maplist Button (only if logged in) -->
                        <button
                            v-if="page.props.auth.user"
                            @click.prevent.stop="showMaplistModal = true"
                            class="p-0.5 text-gray-400 hover:text-purple-400 rounded transition-colors"
                            title="Add to Maplist"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>

                        <!-- Download Button -->
                        <a
                            @click.stop
                            target="_blank"
                            :href="'https://dl.defrag.racing/downloads/maps/' + map?.pk3?.split('/').pop()"
                            class="p-0.5 text-gray-400 hover:text-blue-400 rounded transition-colors"
                            title="Download"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-400">
                    <Link @click.stop :href="route('maps.filters', {author: map?.author ?? 'unknown'})" class="truncate hover:text-blue-400 transition-colors">
                        {{ map.author }}
                    </Link>
                    <span class="text-[10px] whitespace-nowrap ml-2">{{ date }}</span>
                </div>
            </div>
        </div>
    </Link>

    <!-- Add to Maplist Modal -->
    <AddToMaplistModal
        :show="showMaplistModal"
        :map-id="map.id"
        @close="showMaplistModal = false"
    />
</template>