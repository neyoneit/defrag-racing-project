<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import OnlinePlayer from '@/Components/OnlinePlayer.vue';
import Popper from "vue3-popper";
import { useClipboard } from '@/Composables/useClipboard';

const { copy, copyState } = useClipboard();
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
                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2 text-blue-400 font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                <span>{{ players }} Players Online</span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-400 font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                </svg>
                                <span>{{ serverCount }} Active Servers</span>
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
                <div v-for="server in filteredAndSortedServers" :key="server.id" class="group relative backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 hover:border-white/20 overflow-hidden transition-all duration-300 hover:shadow-2xl hover:shadow-blue-500/20 bg-black">
                    <!-- Background Image - FIXED SIZE, never changes, keeps aspect ratio -->
                    <div class="absolute top-0 left-0 right-0 h-[450px] pointer-events-none bg-black overflow-hidden">
                        <div class="relative inline-block w-full">
                            <img :src="`/storage/${server.mapdata?.thumbnail}`" @error="$event.target.src='/images/unknown.jpg'" class="w-full object-contain object-top" style="max-height: 450px;" />
                            <!-- Fade positioned at bottom of actual image -->
                            <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black via-black/70 to-transparent"></div>
                        </div>
                    </div>

                    <div class="relative p-6 flex flex-col h-full">
                        <!-- Server Info Box -->
                        <div class="mb-4 p-4 bg-black/85 rounded-lg border border-white/20 backdrop-blur-sm">
                            <!-- Server Name with Flag, Physics, and Copy IP - Centered -->
                            <div class="flex items-center justify-center gap-2 mb-3">
                                <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-lg" :title="server.location" @error="$event.target.style.display='none'">
                                <h3 class="text-xl font-bold text-white" v-html="q3tohtml(server.name)"></h3>
                                <span class="bg-white/80 px-2 py-0.5 rounded text-[10px] font-bold text-black uppercase backdrop-blur-sm">
                                    {{ server.defrag }}
                                </span>
                                <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-white/80 hover:bg-white/90 border-white/40 text-black hover:text-blue-600'" class="px-2 py-0.5 border rounded text-xs font-bold transition-all backdrop-blur-sm">
                                    {{ copiedIP === server.ip + ':' + server.port ? 'Copied!' : 'Copy IP' }}
                                </button>
                            </div>

                            <!-- Map Info -->
                            <div class="space-y-1.5">
                                <div v-if="server.map" class="flex items-center gap-2 text-sm">
                                    <span class="text-gray-400">Map:</span>
                                    <a :href="`/maps/${server.map}`" class="font-bold text-white hover:text-blue-400 transition-colors">{{ server.map }}</a>
                                </div>
                                <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center gap-2 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-yellow-500 flex-shrink-0">
                                        <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="flex items-center gap-2 flex-1">
                                        <img v-if="server.besttime_country" :src="`/images/flags/${server.besttime_country}.png`" class="w-4 h-3 rounded shadow-md" @error="$event.target.style.display='none'">
                                        <span class="font-bold" v-html="q3tohtml(server.besttime_name)"></span>
                                    </div>
                                    <span class="font-bold text-yellow-400 font-mono">{{ formatTime(server.besttime_time) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Players List -->
                        <div v-if="server.online_players.length > 0" class="mb-4">
                            <div class="text-xs text-gray-300 font-bold uppercase tracking-wide mb-2 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">Players Online</div>
                            <div class="player-list-container bg-black/70 rounded-lg p-2 border border-white/10 backdrop-blur-sm">
                                <div class="space-y-1.5">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.name" :player="player" />
                                </div>
                            </div>
                        </div>
                        <div v-else class="mb-4 p-3 bg-black/70 rounded-lg border border-white/10 text-center backdrop-blur-sm">
                            <span class="text-sm text-gray-400 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">No players online</span>
                        </div>

                        <!-- Actions -->
                        <div class="mt-auto">
                            <a :href="`defrag://${server.ip}:${server.port}`" :class="server.defrag.toLowerCase().includes('cpm') ? 'border-purple-500/60 hover:border-purple-400 hover:bg-purple-500/20' : 'border-blue-500/60 hover:border-blue-400 hover:bg-blue-500/20'" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-black/70 border-2 rounded-lg text-white font-bold text-sm transition-all backdrop-blur-sm hover:scale-[1.02]">
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
                            <div class="flex items-center justify-between gap-3">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="flex-1 min-w-0 bg-black/80 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white truncate transition-colors mb-0.5" v-html="q3tohtml(server.name)">
                                        </h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <template v-if="server.map">
                                                <a :href="`/maps/${server.map}`" class="truncate hover:text-blue-400 transition-colors">{{ server.map }}</a>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Copy IP Button -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-black/80 hover:bg-black/90 border-white/40 hover:border-white/50 text-gray-200 hover:text-blue-400'" class="px-2 py-1 border rounded-lg transition-all backdrop-blur-sm text-xs font-bold">
                                        <template v-if="copiedIP === server.ip + ':' + server.port">
                                            Copied!
                                        </template>
                                        <template v-else>
                                            Copy IP
                                        </template>
                                    </button>
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
                            <div class="flex items-center justify-between gap-3">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="flex-1 min-w-0 bg-black/80 backdrop-blur-sm px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white truncate transition-colors mb-0.5" v-html="q3tohtml(server.name)">
                                        </h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <template v-if="server.map">
                                                <a :href="`/maps/${server.map}`" class="truncate hover:text-purple-400 transition-colors">{{ server.map }}</a>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Copy IP Button -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button @click="copyServerIP(server.ip + ':' + server.port)" :class="copiedIP === server.ip + ':' + server.port ? 'bg-green-600 border-green-400 text-white' : 'bg-black/80 hover:bg-black/90 border-white/40 hover:border-white/50 text-gray-200 hover:text-purple-400'" class="px-2 py-1 border rounded-lg transition-all backdrop-blur-sm text-xs font-bold">
                                        <template v-if="copiedIP === server.ip + ':' + server.port">
                                            Copied!
                                        </template>
                                        <template v-else>
                                            Copy IP
                                        </template>
                                    </button>
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
/* Player list hover-to-expand animation */
.player-list-container {
    max-height: 120px;
    overflow: hidden;
    position: relative;
    transition: max-height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.group:hover .player-list-container {
    max-height: 800px;
    transition: max-height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
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

.group:hover .player-list-container::after {
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
