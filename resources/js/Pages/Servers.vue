<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import OnlinePlayer from '@/Components/OnlinePlayer.vue';
import { useClipboard } from '@/Composables/useClipboard';

const { copy } = useClipboard();
const copiedIP = ref(null);

const props = defineProps({
    servers: Array,
});

const copyServerIP = (serverIP) => {
    copy(serverIP);
    copiedIP.value = serverIP;
    setTimeout(() => {
        copiedIP.value = null;
    }, 2000);
};

const players = ref(0);
const interval = ref(null);
const isRotating = ref(false);

// Get layout from cookie or default to 'large'
const getLayoutFromCookie = () => {
    const cookies = document.cookie.split(';');
    const layoutCookie = cookies.find(c => c.trim().startsWith('servers_layout='));
    return layoutCookie ? layoutCookie.split('=')[1] : 'large';
};

const layout = ref(getLayoutFromCookie()); // 'large' or 'compact'

// Filters
const filters = ref({
    gametype: 'all', // all, run, ctf, freestyle, teamrun
    physics: 'all', // all, cpm, vq3
    hideEmpty: false,
});

const sorting = ref('popularity');
const sortingOrder = ref('desc'); // 'asc' or 'desc'

const startInterval = () => {
    if (interval.value == null) {
        interval.value = setInterval(updatePage, 30000);
    }
}

const stopInterval = () => {
    clearInterval(interval.value);
    interval.value = null;
}

const updatePage = () => {
    if (isRotating.value) {
        return;
    }

    isRotating.value = true;
    router.reload()

    setTimeout(() => {
        isRotating.value = false;
    }, 1500);
}

const countPlayers = () => {
    players.value = 0
    props.servers.forEach(element => {
        players.value += element.online_players.length
    });
}

onMounted(() => {
    countPlayers()
    startInterval()

    // Debug: Show all server types
    console.log('=== ALL SERVER TYPES ===');
    props.servers.forEach(server => {
        console.log(`${server.name} - type: ${server.type}`);
    });

    // Debug: Check rank data
    console.log('=== RANK DEBUG ===');
    const serversWithTime = props.servers.filter(s => s.mytime_time && s.mytime_time > 0);
    serversWithTime.forEach(server => {
        console.log(`${server.map}:`, {
            mytime_time: server.mytime_time,
            myrank_position: server.myrank_position,
            myrank_total: server.myrank_total
        });
    });
})

onUnmounted(() => {
    stopInterval()
})

const getServerName = (name) => {
    const colorRegex = /\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g;
    return name.replace(colorRegex, '');
}

const toggleLayout = () => {
    layout.value = layout.value === 'large' ? 'compact' : 'large';
    // Save to cookie (expires in 1 year)
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = `servers_layout=${layout.value}; expires=${expires.toUTCString()}; path=/`;
}

const toggleSort = (type) => {
    if (sorting.value === type) {
        sortingOrder.value = sortingOrder.value === 'desc' ? 'asc' : 'desc';
    } else {
        sorting.value = type;
        sortingOrder.value = 'desc';
    }
}

const filteredAndSortedServers = computed(() => {
    let result = [...props.servers];

    // Filter by gametype (using manually set 'type' field)
    if (filters.value.gametype !== 'all') {
        result = result.filter(server => {
            const serverType = server.type?.toLowerCase() || 'run';

            switch (filters.value.gametype) {
                case 'run':
                    return serverType === 'run';
                case 'ctf':
                    return serverType === 'ctf';
                case 'freestyle':
                    return serverType === 'freestyle';
                case 'teamrun':
                    return serverType === 'teamrun' || serverType === 'team';
                default:
                    return true;
            }
        });
    }

    // Filter by physics
    if (filters.value.physics !== 'all') {
        result = result.filter(server => {
            if (filters.value.physics === 'cpm') {
                return server.defrag.toLowerCase().includes('cpm');
            } else if (filters.value.physics === 'vq3') {
                return !server.defrag.toLowerCase().includes('cpm');
            }
            return true;
        });
    }

    // Hide empty servers
    if (filters.value.hideEmpty) {
        result = result.filter(server => server.online_players.length > 0);
    }


    // Sort
    result.sort((a, b) => {
        let comparison = 0;

        if (sorting.value === 'popularity') {
            comparison = b.online_players.length - a.online_players.length;
        } else if (sorting.value === 'alphabetically') {
            const nameA = getServerName(a.name).toLowerCase();
            const nameB = getServerName(b.name).toLowerCase();
            comparison = nameA.localeCompare(nameB);
        }

        return sortingOrder.value === 'desc' ? comparison : -comparison;
    });

    return result;
});

const serverCount = computed(() => filteredAndSortedServers.value.length);

// Helper functions for weapon/item/function icons and names (from MapView.vue)
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
</script>

<template>
    <div class="min-h-screen">
        <Head title="Servers" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-6">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">
                            Live Servers
                        </h1>
                        <div class="flex items-center gap-3 text-sm">
                            <div class="flex items-center gap-2 bg-blue-500/20 backdrop-blur-sm px-3 py-2 rounded-lg border border-blue-400/30">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-blue-400">
                                    <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                </svg>
                                <span class="font-bold text-blue-300">{{ players }}</span>
                                <span class="text-gray-300 font-semibold">Players Online</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-2 rounded-lg border border-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-gray-300">
                                    <path d="M4.5 3.75a3 3 0 0 0-3 3v.75h21v-.75a3 3 0 0 0-3-3h-15Z" />
                                    <path fill-rule="evenodd" d="M22.5 9.75h-21v7.5a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-7.5Zm-18 3.75a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Zm.75 2.25a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-bold text-white">{{ serverCount }}</span>
                                <span class="text-gray-300 font-semibold">Active Servers</span>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Toggle -->
                    <button @click="toggleLayout" class="group relative px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 rounded-xl transition-all duration-300">
                        <div class="flex items-center gap-2">
                            <svg v-if="layout === 'large'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span class="text-sm font-bold text-white">{{ layout === 'large' ? 'Large Cards' : 'Compact List' }}</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters & Controls -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-3">
            <div class="backdrop-blur-xl bg-white/5 rounded-2xl border border-white/10 p-4 shadow-2xl">
                <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3">
                    <!-- Gametype Filter -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wide whitespace-nowrap">Gametype:</label>
                        <div class="flex gap-2">
                            <button @click="filters.gametype = 'all'" :class="filters.gametype === 'all' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                All
                            </button>
                            <button @click="filters.gametype = 'run'" :class="filters.gametype === 'run' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                Run
                            </button>
                            <button @click="filters.gametype = 'ctf'" :class="filters.gametype === 'ctf' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                CTF
                            </button>
                            <button @click="filters.gametype = 'freestyle'" :class="filters.gametype === 'freestyle' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                Freestyle
                            </button>
                            <button @click="filters.gametype = 'teamrun'" :class="filters.gametype === 'teamrun' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                Teamrun
                            </button>
                        </div>
                    </div>

                    <!-- Physics Filter -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wide whitespace-nowrap">Physics:</label>
                        <div class="flex gap-2 flex-1">
                            <button @click="filters.physics = 'all'" :class="filters.physics === 'all' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                All
                            </button>
                            <button @click="filters.physics = 'cpm'" :class="filters.physics === 'cpm' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                CPM
                            </button>
                            <button @click="filters.physics = 'vq3'" :class="filters.physics === 'vq3' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-bold transition-all">
                                VQ3
                            </button>
                        </div>
                    </div>

                    <!-- Sort Options -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wide whitespace-nowrap">Sort:</label>
                        <div class="flex gap-2 flex-1">
                            <button @click="toggleSort('popularity')" :class="sorting === 'popularity' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center justify-center gap-1">
                                Popularity
                                <svg v-if="sorting === 'popularity'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" :class="sortingOrder === 'desc' ? 'rotate-0' : 'rotate-180'" class="w-3 h-3 transition-transform">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <button @click="toggleSort('alphabetically')" :class="sorting === 'alphabetically' ? 'bg-white/20 text-white border-white/30' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center justify-center gap-1">
                                A-Z
                                <svg v-if="sorting === 'alphabetically'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" :class="sortingOrder === 'desc' ? 'rotate-0' : 'rotate-180'" class="w-3 h-3 transition-transform">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wide whitespace-nowrap">Options:</label>
                        <button @click="filters.hideEmpty = !filters.hideEmpty" :class="filters.hideEmpty ? 'bg-red-600 text-white border-red-500' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                            {{ filters.hideEmpty ? 'Show All' : 'Hide Empty' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servers Grid/List -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12">
            <!-- Large Card Layout -->
            <div v-if="layout === 'large'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="server in filteredAndSortedServers" :key="server.id" class="group relative backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 hover:border-white/20 transition-all duration-300 hover:shadow-2xl hover:shadow-blue-500/20 overflow-hidden player-list-hover-group">
                    <!-- Background Image - FIXED SIZE, never changes, keeps aspect ratio -->
                    <div class="absolute top-0 left-0 right-0 h-[450px] pointer-events-none rounded-t-2xl">
                        <div class="relative inline-block w-full">
                            <img :src="`/storage/${server.mapdata?.thumbnail}`" @error="$event.target.src='/images/unknown.jpg'" class="w-full object-contain object-top" style="max-height: 450px;" />
                            <!-- Fade positioned at bottom of actual image -->
                            <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black via-black/70 to-transparent"></div>
                            <!-- Reverse fade right below the image - fades from black to transparent -->
                            <div class="absolute inset-x-0 top-full h-[160px] w-full bg-gradient-to-b from-black via-black/70 to-transparent"></div>
                        </div>
                    </div>

                    <div class="relative p-6 flex flex-col h-full">
                        <!-- Server Info Box -->
                        <div class="mb-4 p-4 bg-white/10 rounded-lg border border-white/20 backdrop-blur-sm">
                            <!-- Server Name with Flag and Copy IP - Left Aligned -->
                            <div class="flex items-center gap-2 mb-3">
                                <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-lg" :title="server.location" @error="$event.target.style.display='none'">
                                <h3 class="text-xl font-bold text-white flex-1" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.name)"></h3>
                                <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-white/10 hover:bg-white/15 border-white/30 hover:border-white/40 text-gray-200 hover:text-blue-400'" class="px-2 py-1 border rounded-lg transition-all backdrop-blur-sm text-xs font-bold">
                                    {{ copiedIP === server.ip + ':' + server.port ? 'Copied!' : 'Copy IP' }}
                                </button>
                            </div>

                            <!-- Map Info with hover group -->
                            <div class="space-y-1.5">
                                <div v-if="server.map" class="bg-white/5 backdrop-blur-sm rounded-lg px-3 py-2 border border-white/10 transition-all relative map-features-hover-group">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-300 text-base font-semibold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Map:</span>
                                            <a :href="`/maps/${server.map}`" class="font-bold text-white text-lg hover:text-blue-400 transition-colors map-name-highlight" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                        </div>
                                        <!-- Expand Indicator - only show if map has features -->
                                        <div v-if="(server.mapdata?.weapons && server.mapdata.weapons.length > 0) || (server.mapdata?.items && server.mapdata.items.length > 0) || (server.mapdata?.functions && server.mapdata.functions.length > 0)" class="map-expand-indicator">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Map Features: Weapons, Items, Functions - Expandable on hover -->
                                    <div v-if="(server.mapdata?.weapons && server.mapdata.weapons.length > 0) || (server.mapdata?.items && server.mapdata.items.length > 0) || (server.mapdata?.functions && server.mapdata.functions.length > 0)" class="map-features-container">
                                        <div class="flex flex-wrap gap-2 pt-2 border-t border-white/10 mt-2">
                                            <!-- Weapons -->
                                            <div v-if="server.mapdata.weapons && server.mapdata.weapons.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]">Weapons:</span>
                                                <div class="flex gap-1">
                                                    <img v-for="weapon in server.mapdata.weapons.split(',')" :key="weapon"
                                                         :src="getWeaponIcon(weapon)"
                                                         :alt="getWeaponName(weapon)"
                                                         :title="getWeaponName(weapon)"
                                                         class="w-7 h-7 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>

                                            <!-- Items -->
                                            <div v-if="server.mapdata.items && server.mapdata.items.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]">Items:</span>
                                                <div class="flex gap-1">
                                                    <img v-for="item in server.mapdata.items.split(',')" :key="item"
                                                         :src="getItemIcon(item)"
                                                         :alt="getItemName(item)"
                                                         :title="getItemName(item)"
                                                         class="w-7 h-7 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>

                                            <!-- Functions -->
                                            <div v-if="server.mapdata.functions && server.mapdata.functions.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]">Functions:</span>
                                                <div class="flex gap-1">
                                                    <img v-for="func in server.mapdata.functions.split(',')" :key="func"
                                                         :src="getFunctionIcon(func)"
                                                         :alt="getFunctionName(func)"
                                                         :title="getFunctionName(func)"
                                                         class="w-7 h-7 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center gap-2 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-yellow-500 flex-shrink-0">
                                        <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="flex items-center gap-2 flex-1">
                                        <img v-if="server.besttime_country" :src="`/images/flags/${server.besttime_country}.png`" class="w-4 h-3 rounded shadow-md" @error="$event.target.style.display='none'">
                                        <span class="font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></span>
                                    </div>
                                    <span class="font-bold text-yellow-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                </div>
                                <div v-if="server.mytime_time && server.mytime_time > 0" class="flex items-center gap-2 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" :class="server.defrag.toLowerCase().includes('cpm') ? 'text-purple-400' : 'text-blue-400'" class="w-4 h-4 flex-shrink-0">
                                        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-bold flex-1 text-white text-sm" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">My Time</span>
                                    <span v-if="server.myrank_position && server.myrank_total" class="text-sm text-gray-400 font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Rank {{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                    <span :class="server.defrag.toLowerCase().includes('cpm') ? 'text-purple-400' : 'text-blue-400'" class="font-bold font-mono text-sm" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Players List - Always expanded -->
                        <div v-if="server.online_players.length > 0" :class="(server.mytime_time && server.mytime_time > 0) ? 'mb-4 mt-1' : ((server.besttime_time && server.besttime_time > 0) ? 'mb-4 mt-[30px]' : 'mb-4 mt-[60px]')">
                            <div class="bg-white/10 rounded-lg p-2 border border-white/10 backdrop-blur-sm">
                                <div class="space-y-1.5">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.name" :player="player" />
                                </div>
                            </div>
                        </div>
                        <div v-else :class="(server.mytime_time && server.mytime_time > 0) ? 'mb-4 mt-1' : ((server.besttime_time && server.besttime_time > 0) ? 'mb-4 mt-[30px]' : 'mb-4 mt-[60px]')">
                            <div class="p-3 bg-white/10 rounded-lg border border-white/10 text-center backdrop-blur-sm">
                                <span class="text-sm text-gray-400 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">No players online</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-auto">
                            <a :href="`defrag://${server.ip}:${server.port}`" :class="server.defrag.toLowerCase().includes('cpm') ? 'connect-button-cpm' : 'connect-button-vq3'" class="connect-button w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-white font-bold text-sm transition-all hover:scale-[1.02]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>
                                Connect to Server
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact List Layout - Split by Physics -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- VQ3 Servers (Left) -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3 mb-4 px-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-500 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white">VQ3 Servers</h3>
                            <p class="text-sm text-gray-500">{{ filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm')).length }} servers</p>
                        </div>
                    </div>

                    <div v-for="server in filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm'))" :key="server.id" class="group relative overflow-hidden rounded-xl border border-white/10 hover:border-blue-500/50 transition-all duration-300">
                        <!-- Background Map Thumbnail -->
                        <div v-if="server.mapdata?.thumbnail" class="absolute inset-0 transition-all duration-500">
                            <img
                                :src="`/storage/${server.mapdata.thumbnail}`"
                                @error="$event.target.src='/images/unknown.jpg'"
                                class="w-full h-full object-cover scale-105 group-hover:scale-110 opacity-100 transition-all duration-500"
                                :alt="server.map"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/40 via-black/20 to-black/40 group-hover:from-black/30 group-hover:via-black/10 group-hover:to-black/30 transition-all duration-500"></div>
                        </div>

                        <div class="relative p-3">
                            <!-- Main compact row -->
                            <div class="flex items-center justify-between gap-2">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="inline-flex flex-col bg-white/10 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.name)"></h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <a v-if="server.map" :href="`/maps/${server.map}`" class="hover:text-blue-400 transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Middle: WR, My Time & Players -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <div class="flex flex-col gap-1">
                                        <!-- World Record -->
                                        <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10 backdrop-blur-sm px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-500 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-xs font-bold text-white truncate max-w-[60px]" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></span>
                                            </div>
                                            <span class="text-xs font-bold text-yellow-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                        </div>

                                        <!-- My Time -->
                                        <div v-if="server.mytime_time && server.mytime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10 backdrop-blur-sm px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-blue-400 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                                </svg>
                                                <span v-if="server.myrank_position && server.myrank_total" class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                                <span v-else class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Me</span>
                                            </div>
                                            <span class="text-xs font-bold text-blue-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                        </div>
                                    </div>

                                    <!-- Player Count -->
                                    <div v-if="server.online_players.length > 0" class="flex items-center gap-1 bg-white/10 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-blue-400">
                                            <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                        </svg>
                                        <span class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.online_players.length }}</span>
                                    </div>
                                </div>

                                <!-- Right: Play & Copy IP Button -->
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <a :href="`defrag://${server.ip}:${server.port}`" class="flex items-center justify-center gap-1 px-2 py-1 bg-white/10 border border-blue-500/60 hover:border-blue-400 hover:bg-blue-500/20 rounded-lg text-white transition-all backdrop-blur-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Play</span>
                                    </a>
                                    <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-white/10 hover:bg-white/15 border-white/30 hover:border-white/40 text-gray-200 hover:text-blue-400'" class="px-2 py-1 border rounded-lg transition-all backdrop-blur-sm text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">
                                        {{ copiedIP === server.ip + ':' + server.port ? 'Copied!' : 'Copy IP' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Players List - Expands on hover -->
                            <div v-if="server.online_players.length > 0" class="mt-0 max-h-0 group-hover:max-h-96 overflow-hidden transition-all duration-300">
                                <div class="pt-2 space-y-1">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.name" :player="player" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm')).length === 0" class="text-center py-8 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10">
                        <p class="text-gray-500 text-sm">No VQ3 servers found</p>
                    </div>
                </div>

                <!-- CPM Servers (Right) -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3 mb-4 px-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white">CPM Servers</h3>
                            <p class="text-sm text-gray-500">{{ filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm')).length }} servers</p>
                        </div>
                    </div>

                    <div v-for="server in filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm'))" :key="server.id" class="group relative overflow-hidden rounded-xl border border-white/10 hover:border-purple-500/50 transition-all duration-300">
                        <!-- Background Map Thumbnail -->
                        <div v-if="server.mapdata?.thumbnail" class="absolute inset-0 transition-all duration-500">
                            <img
                                :src="`/storage/${server.mapdata.thumbnail}`"
                                @error="$event.target.src='/images/unknown.jpg'"
                                class="w-full h-full object-cover scale-105 group-hover:scale-110 opacity-100 transition-all duration-500"
                                :alt="server.map"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/40 via-black/20 to-black/40 group-hover:from-black/30 group-hover:via-black/10 group-hover:to-black/30 transition-all duration-500"></div>
                        </div>

                        <div class="relative p-3">
                            <div class="flex items-center justify-between gap-2">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="inline-flex flex-col bg-white/10 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.name)"></h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <a v-if="server.map" :href="`/maps/${server.map}`" class="hover:text-purple-400 transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Middle: WR, My Time & Players -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <div class="flex flex-col gap-1">
                                        <!-- World Record -->
                                        <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10 backdrop-blur-sm px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-500 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-xs font-bold text-white truncate max-w-[60px]" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></span>
                                            </div>
                                            <span class="text-xs font-bold text-yellow-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                        </div>

                                        <!-- My Time -->
                                        <div v-if="server.mytime_time && server.mytime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10 backdrop-blur-sm px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-purple-400 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                                </svg>
                                                <span v-if="server.myrank_position && server.myrank_total" class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                                <span v-else class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Me</span>
                                            </div>
                                            <span class="text-xs font-bold text-purple-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                        </div>
                                    </div>

                                    <!-- Player Count -->
                                    <div v-if="server.online_players.length > 0" class="flex items-center gap-1 bg-white/10 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-purple-400">
                                            <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                        </svg>
                                        <span class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.online_players.length }}</span>
                                    </div>
                                </div>

                                <!-- Right: Play & Copy IP Button -->
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <a :href="`defrag://${server.ip}:${server.port}`" class="flex items-center justify-center gap-1 px-2 py-1 bg-white/10 border border-purple-500/60 hover:border-purple-400 hover:bg-purple-500/20 rounded-lg text-white transition-all backdrop-blur-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">Play</span>
                                    </a>
                                    <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-white/10 hover:bg-white/15 border-white/30 hover:border-white/40 text-gray-200 hover:text-purple-400'" class="px-2 py-1 border rounded-lg transition-all backdrop-blur-sm text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">
                                        {{ copiedIP === server.ip + ':' + server.port ? 'Copied!' : 'Copy IP' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Players List - Expands on hover -->
                            <div v-if="server.online_players.length > 0" class="mt-0 max-h-0 group-hover:max-h-96 overflow-hidden transition-all duration-300">
                                <div class="pt-2 space-y-1">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.name" :player="player" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm')).length === 0" class="text-center py-8 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10">
                        <p class="text-gray-500 text-sm">No CPM servers found</p>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredAndSortedServers.length === 0" class="text-center py-16">
                <div class="backdrop-blur-xl bg-white/5 rounded-2xl border border-white/10 p-12 max-w-md mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-500 mx-auto mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <h3 class="text-xl font-bold text-white mb-2">No servers found</h3>
                    <p class="text-gray-400">Try adjusting your filters</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Map features hover box styling */
.map-features-hover-group {
    cursor: pointer;
}

.map-features-hover-group:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(96, 165, 250, 0.3) !important;
}

/* Map name highlight on hover */
.map-name-highlight {
    position: relative;
    display: inline-block;
}

.map-features-hover-group:hover .map-name-highlight {
    color: rgb(96, 165, 250) !important;
    text-shadow: 0 0 10px rgba(96, 165, 250, 0.5), 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);
}

/* Expand indicator animation - Balanced */
.map-expand-indicator {
    position: relative;
    padding: 4px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 50%;
    backdrop-filter: blur(4px);
    color: rgba(255, 255, 255, 0.75);
    transition: all 0.3s ease;
    animation: subtlePulse 2.5s ease-in-out infinite;
    filter: drop-shadow(0 0 3px rgba(96, 165, 250, 0.2)) drop-shadow(0 1px 3px rgba(0, 0, 0, 0.7));
}

.map-expand-indicator::before {
    content: '';
    position: absolute;
    inset: -6px;
    background: radial-gradient(circle, rgba(96, 165, 250, 0.2), rgba(59, 130, 246, 0.1) 50%, transparent 70%);
    border-radius: 50%;
    opacity: 0;
    animation: subtleRing 2.5s ease-in-out infinite;
    z-index: -1;
}

.map-expand-indicator::after {
    content: '';
    position: absolute;
    inset: -3px;
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: 50%;
    opacity: 0;
    animation: subtleExpandRing 2.5s ease-in-out infinite;
}

.map-features-hover-group:hover .map-expand-indicator {
    animation: none;
    transform: rotate(180deg);
    background: rgba(96, 165, 250, 0.25);
    color: rgb(255, 255, 255);
    filter: drop-shadow(0 0 5px rgba(96, 165, 250, 0.35)) drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8));
}

.map-features-hover-group:hover .map-expand-indicator::before,
.map-features-hover-group:hover .map-expand-indicator::after {
    animation: none;
    opacity: 0;
}

@keyframes subtlePulse {
    0%, 100% {
        transform: translateY(0) scale(1);
        background: rgba(0, 0, 0, 0.25);
    }
    50% {
        transform: translateY(2px) scale(1.03);
        background: rgba(96, 165, 250, 0.15);
    }
}

@keyframes subtleRing {
    0%, 100% {
        opacity: 0;
        transform: scale(0.85);
    }
    50% {
        opacity: 0.35;
        transform: scale(1.25);
    }
}

@keyframes subtleExpandRing {
    0% {
        opacity: 0.3;
        transform: scale(1);
    }
    100% {
        opacity: 0;
        transform: scale(1.6);
    }
}

/* Map features expand animation */
.map-features-container {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
}

.map-features-hover-group:hover .map-features-container {
    max-height: 200px;
    opacity: 1;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
}

/* Map feature icon shadows */
.map-feature-icon {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8)) drop-shadow(0 0 2px rgba(0, 0, 0, 0.6));
}

.map-feature-icon:hover {
    filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.9)) drop-shadow(0 0 4px rgba(0, 0, 0, 0.7));
}

/* Player list hover-to-expand animation */
.player-list-container {
    max-height: 70px;
    overflow: hidden;
    position: relative;
    opacity: 1;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
}

.player-list-hover-group:hover .player-list-container {
    max-height: 1200px;
    opacity: 1;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
}

/* Show More Indicator at bottom of player list - Enhanced */
.show-more-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.95));
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 8px;
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.show-more-content {
    position: relative;
    padding: 4px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 50%;
    backdrop-filter: blur(4px);
    color: rgba(255, 255, 255, 0.75);
    animation: subtlePulseDown 2.5s ease-in-out infinite;
    filter: drop-shadow(0 0 3px rgba(96, 165, 250, 0.2)) drop-shadow(0 1px 3px rgba(0, 0, 0, 0.7));
}

.show-more-content::before {
    content: '';
    position: absolute;
    inset: -6px;
    background: radial-gradient(circle, rgba(96, 165, 250, 0.2), rgba(59, 130, 246, 0.1) 50%, transparent 70%);
    border-radius: 50%;
    opacity: 0;
    animation: subtleRingDown 2.5s ease-in-out infinite;
    z-index: -1;
}

.show-more-content::after {
    content: '';
    position: absolute;
    inset: -3px;
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: 50%;
    opacity: 0;
    animation: subtleExpandRingDown 2.5s ease-in-out infinite;
}

@keyframes subtlePulseDown {
    0%, 100% {
        transform: translateY(0) scale(1);
        background: rgba(0, 0, 0, 0.25);
    }
    50% {
        transform: translateY(2px) scale(1.03);
        background: rgba(96, 165, 250, 0.15);
    }
}

@keyframes subtleRingDown {
    0%, 100% {
        opacity: 0;
        transform: scale(0.85);
    }
    50% {
        opacity: 0.35;
        transform: scale(1.25);
    }
}

@keyframes subtleExpandRingDown {
    0% {
        opacity: 0.3;
        transform: scale(1);
    }
    100% {
        opacity: 0;
        transform: scale(1.6);
    }
}

.player-list-hover-group:hover .show-more-indicator {
    opacity: 0;
}

/* Connect to Server Button - Fixed Glass Texture */
.connect-button {
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.connect-button::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.15) 0%,
        rgba(255, 255, 255, 0.05) 25%,
        transparent 50%,
        rgba(0, 0, 0, 0.05) 75%,
        rgba(0, 0, 0, 0.1) 100%
    );
    pointer-events: none;
}

.connect-button::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.connect-button:hover::after {
    left: 100%;
}

.connect-button-vq3 {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.15));
    border: 2px solid rgba(96, 165, 250, 0.4);
}

.connect-button-vq3:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(37, 99, 235, 0.25));
    border-color: rgba(96, 165, 250, 0.6);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.connect-button-cpm {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.25), rgba(147, 51, 234, 0.15));
    border: 2px solid rgba(192, 132, 252, 0.4);
}

.connect-button-cpm:hover {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(147, 51, 234, 0.25));
    border-color: rgba(192, 132, 252, 0.6);
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

/* Fade gradient at bottom when collapsed */
.player-list-container::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.9));
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.player-list-hover-group:hover .player-list-container::after {
    opacity: 0;
}

/* Scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
