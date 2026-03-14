<script setup>
    import moment from 'moment';
    import { computed } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { useClipboard } from '@/Composables/useClipboard';

    const { copy } = useClipboard();

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
        return moment(props.map.date_added).fromNow()
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
    <Link :href="getGametype === 'run' ? `/maps/${encodeURIComponent(map.name)}` : `/maps/${encodeURIComponent(map.name)}?gametype=ctf2`" class="block">
        <div class="backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 overflow-hidden hover:border-blue-500/50 transition-all group hover:transform hover:scale-105">
            <!-- Map Thumbnail -->
            <div class="relative h-40 bg-cover bg-center" :style="`background-image: url('/storage/${map.thumbnail}')`" onerror="this.style.backgroundImage='url(\'/images/unknown.jpg\')'">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>

                <!-- Physics Badge -->
                <div class="absolute top-2 right-2 px-3 py-1 rounded-full text-xs font-bold uppercase" :class="background">
                    {{ map.physics }}
                </div>

                <!-- Gametype Badge -->
                <div v-if="map.gametype && map.gametype !== 'defrag'" class="absolute top-2 left-2 px-3 py-1 rounded-full text-xs font-bold uppercase bg-orange-500/90 text-white">
                    {{ map.gametype }}
                </div>
            </div>

            <!-- Map Info -->
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-bold text-white truncate flex-1 group-hover:text-blue-400 transition-colors">{{ map.name }}</h3>
                    <div class="ml-2 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-gray-400 hover:text-green-500" @click.prevent="copy(map.name)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                        </svg>
                    </div>
                </div>

                <!-- Map Stats -->
                <div class="flex items-center gap-4 text-xs text-gray-400">
                    <div class="flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ date }}</span>
                    </div>
                    <div v-if="map.author" class="flex items-center gap-1 truncate">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span class="truncate">{{ map.author }}</span>
                    </div>
                </div>

                <!-- Weapons/Items -->
                <div v-if="weaponsList.length > 0 || itemsList.length > 0" class="flex flex-wrap gap-1 mt-3">
                    <div v-for="weapon in weaponsList.slice(0, 3)" :key="weapon" class="px-2 py-1 bg-white/5 rounded text-xs text-gray-400">
                        {{ weapon }}
                    </div>
                    <div v-if="weaponsList.length > 3" class="px-2 py-1 bg-white/5 rounded text-xs text-gray-400">
                        +{{ weaponsList.length - 3 }}
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template>