<script setup>
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import MapCardLine from '@/Components/MapCardLine.vue';
    import MapRecord from '@/Components/MapRecord.vue';
    import MapRecordSmall from '@/Components/MapRecordSmall.vue';
    import MapCardLineSmall from '@/Components/MapCardLineSmall.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ToggleButton from '@/Components/Basic/ToggleButton.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, watch, ref, onMounted, onUnmounted, computed } from 'vue';

    const props = defineProps({
        map: Object,
        cpmRecords: Object,
        vq3Records: Object,
        cpmOldRecords: Object,
        vq3OldRecords: Object,
        my_cpm_record: Object,
        my_vq3_record: Object,
        gametypeStats: Object,
        servers: Array,
        showOldtop: {
            type: Boolean,
            default: false
        }
    });

    const page = usePage();

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');

    const gametypes = [
        'run',
        'ctf1',
        'ctf2',
        'ctf3',
        'ctf4',
        'ctf5',
        'ctf6',
        'ctf7'
    ];

    const screenWidth = ref(window.innerWidth);

    // Initialize oldtop state from props (server/URL state is source of truth)
    const showOldtopLocal = ref(props.showOldtop);

    // Mobile physics toggle - 'both', 'VQ3', or 'CPM'
    const mobilePhysics = ref('both');

    const sortByDate = () => {
        if (column.value === 'date_set') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        }

        column.value = 'date_set';

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByTime = () => {
        // Toggle between sorting by time (fastest) and date (when set)
        if (column.value === 'time') {
            // Switch to date sorting
            column.value = 'date_set';
            order.value = 'DESC'; // Newest first by default
        } else {
            // Switch to time sorting
            column.value = 'time';
            order.value = 'ASC'; // Fastest first by default
        }

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByGametype = (gt) => {
        gametype.value = gt;

        router.reload({
            data: {
                gametype: gt,
                sort: 'time',
                order: 'ASC'
            }
        })
    }

    watchEffect(() => {
        order.value = route().params['order'] ?? 'ASC';
        column.value = route().params['sort'] ?? 'time';
        gametype.value = route().params['gametype'] ?? 'run';
    });

    // Watch for changes to showOldtop prop and sync with local state
    watch(() => props.showOldtop, (newValue) => {
        showOldtopLocal.value = newValue;
        localStorage.setItem('mapview_show_oldtop', newValue ? '1' : '0');
    }, { immediate: true });

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    };

    const onChangeOldtop = (value) => {
        showOldtopLocal.value = value;
        localStorage.setItem('mapview_show_oldtop', value ? '1' : '0');
        router.reload({
            data: {
                showOldtop: value
            }
        })
    }

    const getVq3Records = computed(() => {
        if (! props.showOldtop ) {
            return props.vq3Records
        }

        let oldtop_data = props.vq3OldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.vq3Records.total, props.vq3OldRecords.total),
            data: [...props.vq3Records.data, ...oldtop_data],
            first_page_url: props.vq3Records.first_page_url,
            current_page: props.vq3Records.current_page,
            last_page: (props.vq3Records.total > props.vq3OldRecords.total) ? props.vq3Records.last_page : props.vq3OldRecords.last_page,
            per_page: props.vq3Records.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    const getCpmRecords = computed(() => {
        if (! props.showOldtop ) {
            return props.cpmRecords
        }

        let oldtop_data = props.cpmOldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.cpmRecords.total, props.cpmOldRecords.total),
            data: [...props.cpmRecords.data, ...oldtop_data],
            first_page_url: props.cpmRecords.first_page_url,
            current_page: props.cpmRecords.current_page,
            last_page: (props.cpmRecords.total > props.cpmOldRecords.total) ? props.cpmRecords.last_page : props.cpmOldRecords.last_page,
            per_page: props.cpmRecords.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    // Helper functions for weapon/item/function icons and names
    const getWeaponIcon = (abbr) => {
        const icons = {
            'gauntlet': '/images/weapons/iconw_gauntlet.svg',
            'gt': '/images/weapons/iconw_gauntlet.svg',
            'mg': '/images/weapons/iconw_machinegun.svg',
            'sg': '/images/weapons/iconw_shotgun.svg',
            'gl': '/images/weapons/iconw_grenade.svg',
            'rl': '/images/weapons/iconw_rocket.svg',
            'lg': '/images/weapons/iconw_lightning.svg',
            'rg': '/images/weapons/iconw_railgun.svg',
            'pg': '/images/weapons/iconw_plasma.svg',
            'bfg': '/images/weapons/iconw_bfg.svg',
            'grapple': '/images/weapons/iconw_grapple.svg',
            'hook': '/images/weapons/iconw_grapple.svg',
            'gh': '/images/weapons/iconw_grapple.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/weapons/iconw_gauntlet.svg';
    };

    const getWeaponName = (abbr) => {
        const weapons = {
            'gauntlet': 'Gauntlet',
            'gt': 'Gauntlet',
            'mg': 'Machine Gun',
            'sg': 'Shotgun',
            'gl': 'Grenade Launcher',
            'rl': 'Rocket Launcher',
            'lg': 'Lightning Gun',
            'rg': 'Rail Gun',
            'pg': 'Plasma Gun',
            'bfg': 'BFG',
            'grapple': 'Grappling Hook',
            'hook': 'Grappling Hook',
            'gh': 'Grappling Hook'
        };
        return weapons[abbr.toLowerCase().trim()] || abbr.toUpperCase();
    };

    const getItemIcon = (abbr) => {
        const icons = {
            // Powerups
            'enviro': '/images/powerups/envirosuit.svg',
            'haste': '/images/powerups/haste.svg',
            'quad': '/images/powerups/quad.svg',
            'regen': '/images/powerups/regen.svg',
            'invis': '/images/powerups/invis.svg',
            'flight': '/images/powerups/flight.svg',
            // Health
            'health': '/images/items/iconh_yellow.svg',
            'smallhealth': '/images/items/iconh_green.svg',
            'bighealth': '/images/items/iconh_red.svg',
            'mega': '/images/items/iconh_mega.svg',
            'medkit': '/images/items/medkit.svg',
            // Armor
            'shard': '/images/items/iconr_shard.svg',
            'ya': '/images/items/iconr_yellow.svg',
            'ra': '/images/items/iconr_red.svg',
            // CTF
            'flag': '/images/items/iconf_blu2.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/items/iconh_yellow.svg';
    };

    const getItemName = (abbr) => {
        const items = {
            // Powerups
            'enviro': 'Battle Suit',
            'haste': 'Haste',
            'quad': 'Quad Damage',
            'regen': 'Regeneration',
            'invis': 'Invisibility',
            'flight': 'Flight',
            // Health
            'health': 'Health (+25)',
            'smallhealth': 'Small Health (+5)',
            'bighealth': 'Large Health (+50)',
            'mega': 'Mega Health (+100)',
            'medkit': 'Medkit',
            // Armor
            'shard': 'Armor Shard (+5)',
            'ya': 'Yellow Armor (+50)',
            'ra': 'Red Armor (+100)',
            // CTF
            'flag': 'Flag'
        };
        return items[abbr.toLowerCase().trim()] || abbr;
    };

    const getFunctionIcon = (abbr) => {
        const icons = {
            'tele': '/images/functions/tele.svg',
            'teleporter': '/images/functions/teleporter.svg',
            'slick': '/images/functions/slick.svg',
            'timer': '/images/functions/timer.svg',
            'fog': '/images/functions/fog.svg',
            'water': '/images/functions/water.svg',
            'lava': '/images/functions/lava.svg',
            'moving': '/images/functions/moving.svg',
            'door': '/images/functions/door.svg',
            'button': '/images/functions/button.svg',
            'push': '/images/functions/push.svg',
            'break': '/images/functions/break.svg',
            'slime': '/images/functions/slime.svg',
            'shootergl': '/images/functions/shootergl.svg',
            'shooterpg': '/images/functions/shooterpg.svg',
            'shooterrl': '/images/functions/shooterrl.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/functions/timer.svg';
    };

    const getFunctionName = (abbr) => {
        const functions = {
            'tele': 'Teleporter',
            'teleporter': 'Teleporter',
            'slick': 'Slick Surface',
            'timer': 'Timer',
            'fog': 'Fog',
            'water': 'Water',
            'lava': 'Lava',
            'moving': 'Moving Platforms',
            'door': 'Doors',
            'button': 'Buttons',
            'push': 'Push Trigger',
            'break': 'Breakable',
            'slime': 'Slime',
            'shootergl': 'Grenade Shooter',
            'shooterpg': 'Plasma Shooter',
            'shooterrl': 'Rocket Shooter'
        };
        return functions[abbr.toLowerCase().trim()] || abbr;
    };

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);

        // Initialize toggle state - always trust the server/URL state
        showOldtopLocal.value = props.showOldtop;

        // Sync localStorage with the current state from URL/server
        localStorage.setItem('mapview_show_oldtop', props.showOldtop ? '1' : '0');
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>

<template>
    <div>
        <Head :title="map.name" />

        <!-- Extended Background with Map Image -->
        <div class="relative pb-10 min-h-[1200px]">
            <!-- Map thumbnail background - responsive height -->
            <div class="absolute top-0 left-0 right-0 bottom-0 max-h-[1200px] pointer-events-none overflow-hidden flex justify-center">
                <div v-if="map.thumbnail" class="relative w-full max-w-[1920px] h-full bg-top bg-no-repeat blur-sm" :style="`background-image: url('/storage/${map.thumbnail}'); background-size: 1920px auto;`">
                    <!-- Fade effect on the actual image edges -->
                    <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(17, 24, 39, 0) 0%, rgba(17, 24, 39, 0) 92%, rgba(17, 24, 39, 0.5) 97%, rgba(17, 24, 39, 1) 100%), linear-gradient(to right, rgba(17, 24, 39, 1) 0%, rgba(17, 24, 39, 0) 10%, rgba(17, 24, 39, 0) 90%, rgba(17, 24, 39, 1) 100%);"></div>
                </div>
            </div>
            <!-- Dark overlay for readability on top portion only -->
            <div class="absolute top-0 left-0 right-0 h-[600px] bg-gradient-to-b from-black/50 via-black/60 via-40% to-transparent pointer-events-none"></div>

            <!-- Hero Content (compact) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pt-10 pb-6">
                <div class="w-full max-w-4xl mx-auto backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 shadow-2xl">
                    <!-- Map Title -->
                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-2 text-center">{{ map.name }}</h1>
                    <p class="text-gray-300 text-center mb-4">
                        <Link v-if="map.author" :href="`/maps?author=${encodeURIComponent(map.author)}`" class="text-blue-400 hover:text-blue-300 font-semibold underline decoration-blue-400/50 hover:decoration-blue-300 transition-colors">{{ map.author }}</Link>
                        <span v-if="map.date_added" class="text-gray-400"> • {{ new Date(map.date_added).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    </p>

                    <!-- Download & Servers -->
                    <div class="flex flex-wrap gap-2 justify-center mb-4">
                        <!-- Download Button -->
                        <a
                            :href="`/maps/${map.name}/download`"
                            class="flex items-center gap-1.5 bg-gray-600/80 hover:bg-gray-600 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                            </svg>
                            Download Map
                        </a>

                        <!-- Active Servers -->
                        <div v-if="servers && servers.length > 0" class="flex flex-wrap gap-2">
                            <a
                                v-for="server in servers"
                                :key="server.id"
                                v-show="server.online_players && server.online_players.length > 0"
                                :href="`defrag://${server.ip}:${server.port}`"
                                class="flex items-center gap-1.5 bg-orange-500/80 hover:bg-orange-500 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md"
                                :title="`Connect to ${server.plain_name || server.name}`"
                            >
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                                </svg>
                                <span class="whitespace-nowrap">Join</span>
                                <span class="truncate max-w-[100px]">{{ server.plain_name || server.name }}</span>
                                <span class="bg-white/20 px-1.5 py-0.5 rounded text-[10px] whitespace-nowrap">{{ server.online_players.length }} playing</span>
                            </a>
                        </div>
                    </div>

                    <!-- Map Features: Weapons, Items, Functions -->
                    <div v-if="(map.weapons && map.weapons.length > 0) || (map.items && map.items.length > 0) || (map.functions && map.functions.length > 0)" class="flex flex-wrap gap-3 justify-center mb-4 pt-3 border-t border-white/10">
                        <!-- Weapons -->
                        <div v-if="map.weapons && map.weapons.length > 0" class="flex items-center gap-2">
                            <span class="text-gray-400 font-medium text-xs">Weapons:</span>
                            <div class="flex gap-1.5">
                                <img v-for="weapon in map.weapons.split(',')" :key="weapon"
                                     :src="getWeaponIcon(weapon)"
                                     :alt="getWeaponName(weapon)"
                                     :title="getWeaponName(weapon)"
                                     class="w-8 h-8 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>

                        <!-- Items -->
                        <div v-if="map.items && map.items.length > 0" class="flex items-center gap-2">
                            <span class="text-gray-400 font-medium text-xs">Items:</span>
                            <div class="flex gap-1.5">
                                <img v-for="item in map.items.split(',')" :key="item"
                                     :src="getItemIcon(item)"
                                     :alt="getItemName(item)"
                                     :title="getItemName(item)"
                                     class="w-8 h-8 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>

                        <!-- Functions -->
                        <div v-if="map.functions && map.functions.length > 0" class="flex items-center gap-2">
                            <span class="text-gray-400 font-medium text-xs">Functions:</span>
                            <div class="flex gap-1.5">
                                <img v-for="func in map.functions.split(',')" :key="func"
                                     :src="getFunctionIcon(func)"
                                     :alt="getFunctionName(func)"
                                     :title="getFunctionName(func)"
                                     class="w-8 h-8 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>
                    </div>

                    <!-- Gametype Tabs (only show if more than one gametype has records) -->
                    <div v-if="Object.values(gametypeStats).filter(count => count > 0).length > 1" class="flex gap-2 flex-wrap justify-center mb-4">
                        <button
                            v-for="gt in gametypes"
                            :key="gt"
                            v-show="gametypeStats[gt] && gametypeStats[gt] > 0"
                            @click="sortByGametype(gt)"
                            :class="[
                                'px-4 py-2 rounded-lg font-bold text-sm transition-all backdrop-blur-sm',
                                gametype === gt
                                    ? 'bg-blue-500/80 text-white shadow-lg ring-2 ring-blue-400'
                                    : 'bg-white/10 text-gray-300 hover:bg-white/20'
                            ]"
                        >
                            <span class="uppercase">{{ gt }}</span>
                            <span class="ml-2 text-xs opacity-75">({{ gametypeStats[gt] }})</span>
                        </button>
                    </div>

                    <!-- Physics & Controls -->
                    <div class="flex flex-wrap gap-4 justify-center items-center text-sm">
                        <button
                            @click="sortByTime"
                            class="flex items-center gap-2 bg-white/10 backdrop-blur-sm hover:bg-white/20 rounded-lg px-4 py-2 text-gray-300 transition-all"
                            :title="column === 'time' ? 'Currently sorting by fastest time' : 'Currently sorting by date set'"
                        >
                            <img v-if="column === 'time'" src="/images/powerups/haste.svg" class="w-5 h-5" alt="Fastest" />
                            <span v-if="column === 'time'">Fastest</span>
                            <span v-else>📅 Newest</span>
                            <svg v-if="order === 'ASC'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                            </svg>
                        </button>
                        <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-gray-300">Old Top:</span>
                            <ToggleButton :options="{ isActive: showOldtopLocal }" @setIsActive="onChangeOldtop" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboards Section (on top of background) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <!-- Mobile Physics Toggle (only on small screens) -->
                <div class="md:hidden flex gap-2 justify-center mb-4">
                    <button
                        @click="mobilePhysics = mobilePhysics === 'VQ3' ? 'both' : 'VQ3'"
                        :class="[
                            'px-4 py-2 rounded-lg font-bold text-sm transition-all backdrop-blur-sm flex items-center gap-2',
                            mobilePhysics === 'VQ3'
                                ? 'bg-blue-500/80 text-white shadow-lg ring-2 ring-blue-400'
                                : 'bg-white/10 text-gray-300'
                        ]"
                    >
                        <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" />
                        <span>VQ3</span>
                    </button>
                    <button
                        @click="mobilePhysics = mobilePhysics === 'CPM' ? 'both' : 'CPM'"
                        :class="[
                            'px-4 py-2 rounded-lg font-bold text-sm transition-all backdrop-blur-sm flex items-center gap-2',
                            mobilePhysics === 'CPM'
                                ? 'bg-purple-500/80 text-white shadow-lg ring-2 ring-purple-400'
                                : 'bg-white/10 text-gray-300'
                        ]"
                    >
                        <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" />
                        <span>CPM</span>
                    </button>
                </div>

                <div class="md:flex gap-4 justify-center">
                    <!-- VQ3 Leaderboard -->
                    <div v-show="mobilePhysics === 'both' || mobilePhysics === 'VQ3'" class="flex-1 backdrop-blur-xl bg-grayop-700/80 rounded-xl overflow-hidden shadow-xl border border-gray-700/50">
                    <!-- VQ3 Header -->
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" />
                                <h2 class="text-lg font-bold text-blue-400">VQ3 Records</h2>
                            </div>
                            <div class="text-right min-w-[80px]">
                                <div class="text-[10px] text-gray-400">Your Best</div>
                                <div v-if="my_vq3_record" class="text-sm font-bold text-blue-400 tabular-nums">
                                    {{ formatTime(my_vq3_record.time) }}
                                    <span class="text-xs text-gray-500">#{{ my_vq3_record.rank }}</span>
                                </div>
                                <div v-else class="text-sm text-gray-500">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- VQ3 Records List -->
                    <div class="p-3">
                        <div v-if="getVq3Records.total > 0">
                            <div class="flex-grow" v-if="screenWidth > 640">
                                <MapRecord v-for="record in getVq3Records.data" physics="VQ3" :oldtop="record.oldtop" :key="record.id" :record="record" />
                            </div>

                            <div class="flex-grow" v-else>
                                <MapRecordSmall v-for="record in getVq3Records.data" physics="VQ3" :oldtop="record.oldtop" :key="record.id" :record="record" />
                            </div>
                        </div>

                        <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                            <div>
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div>There are no VQ3 Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- VQ3 Pagination -->
                    <div class="border-t border-white/5 bg-transparent p-3" v-if="getVq3Records.total > getVq3Records.per_page">
                        <Pagination pageName="vq3Page" :last_page="getVq3Records.last_page" :current_page="getVq3Records.current_page" :link="getVq3Records.first_page_url" />
                    </div>
                </div>

                <!-- CPM Leaderboard -->
                <div v-show="mobilePhysics === 'both' || mobilePhysics === 'CPM'" class="flex-1 backdrop-blur-xl bg-grayop-700/80 rounded-xl overflow-hidden shadow-xl border border-gray-700/50 mt-5 md:mt-0">
                    <!-- CPM Header -->
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" />
                                <h2 class="text-lg font-bold text-purple-400">CPM Records</h2>
                            </div>
                            <div class="text-right min-w-[80px]">
                                <div class="text-[10px] text-gray-400">Your Best</div>
                                <div v-if="my_cpm_record" class="text-sm font-bold text-purple-400 tabular-nums">
                                    {{ formatTime(my_cpm_record.time) }}
                                    <span class="text-xs text-gray-500">#{{ my_cpm_record.rank }}</span>
                                </div>
                                <div v-else class="text-sm text-gray-500">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- CPM Records List -->
                    <div class="p-3">
                        <div v-if="getCpmRecords.total > 0">
                            <div class="flex-grow" v-if="screenWidth > 640">
                                <MapRecord v-for="record in getCpmRecords.data" physics="CPM" :key="record.id" :record="record" />
                            </div>

                            <div class="flex-grow" v-else>
                                <MapRecordSmall v-for="record in getCpmRecords.data" physics="CPM" :oldtop="record.oldtop" :key="record.id" :record="record" />
                            </div>
                        </div>

                        <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                            <div>
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div>There are no CPM Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- CPM Pagination -->
                    <div class="border-t border-white/5 bg-transparent p-3" v-if="getCpmRecords.total > getCpmRecords.per_page">
                        <Pagination pageName="cpmPage" :last_page="getCpmRecords.last_page" :current_page="getCpmRecords.current_page" :link="getCpmRecords.first_page_url" />
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</template>
