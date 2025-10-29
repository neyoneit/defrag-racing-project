<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    statistics: Object,
});

const activeTab = ref('total_records');
const leaderboardPage = ref(1);
const untouchedWrsPage = ref(1);
const itemsPerPage = 10;

const setActiveTab = (tab) => {
    activeTab.value = tab;
    leaderboardPage.value = 1; // Reset page when changing tabs
};

// Paginated leaderboard data
const paginatedLeaderboard = computed(() => {
    let data = [];
    if (activeTab.value === 'total_records') {
        data = props.statistics?.leaderboards?.total_records || [];
    } else if (activeTab.value === 'world_records') {
        data = props.statistics?.leaderboards?.top_positions || [];
    } else if (activeTab.value === 'top3') {
        data = props.statistics?.leaderboards?.top_positions || [];
    } else if (activeTab.value === 'activity') {
        data = props.statistics?.leaderboards?.activity || [];
    } else if (activeTab.value === 'map_diversity') {
        data = props.statistics?.leaderboards?.map_diversity || [];
    }

    const start = (leaderboardPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return {
        data: data.slice(start, end),
        total: data.length,
        totalPages: Math.ceil(data.length / itemsPerPage)
    };
});

// Paginated untouched WRs
const paginatedUntouchedWrs = computed(() => {
    const data = props.statistics?.special_sections?.untouchable_records || [];
    const start = (untouchedWrsPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return {
        data: data.slice(start, end),
        total: data.length,
        totalPages: Math.ceil(data.length / itemsPerPage)
    };
});

// Format large numbers
const formatNumber = (num) => {
    if (!num) return '0';
    return new Intl.NumberFormat().format(num);
};
</script>

<template>
    <div class="space-y-8">
        <!-- Featured Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Total Records -->
            <div class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-blue-600/20 to-blue-800/20 border border-blue-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of records held by clan members across all maps and modes">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/0 to-blue-600/0 group-hover:from-blue-500/10 group-hover:to-blue-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.total_records) }}</span>
                    <span class="text-xs font-medium text-blue-300 whitespace-nowrap">Total Records</span>
                </div>
            </div>

            <!-- Top 1 Positions -->
            <div class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-yellow-600/20 to-orange-800/20 border border-yellow-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of #1 world record positions held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/0 to-orange-600/0 group-hover:from-yellow-500/10 group-hover:to-orange-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-yellow-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top1_count) }}</span>
                    <span class="text-xs font-medium text-yellow-300 whitespace-nowrap">World Records</span>
                </div>
            </div>

            <!-- Top 3 Positions -->
            <div class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-purple-600/20 to-pink-800/20 border border-purple-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of top 3 podium finishes (1st, 2nd, or 3rd place) held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/0 to-pink-600/0 group-hover:from-purple-500/10 group-hover:to-pink-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-purple-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top3_count) }}</span>
                    <span class="text-xs font-medium text-purple-300 whitespace-nowrap">Podium Finishes</span>
                </div>
            </div>

            <!-- Longest Streak -->
            <div class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-green-600/20 to-emerald-800/20 border border-green-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Longest consecutive days with at least one record set by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500/0 to-emerald-600/0 group-hover:from-green-500/10 group-hover:to-emerald-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-green-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ statistics.overview.longest_streak }}</span>
                    <span class="text-xs font-medium text-green-300 whitespace-nowrap">Day Streak</span>
                </div>
            </div>
        </div>

        <!-- Hall of Fame -->
        <div class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-yellow-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                </svg>
                Hall of Fame
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- General -->
                <div class="bg-black/20 border border-white/10 rounded-lg p-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">General</h3>
                    <div class="space-y-2">
                        <div v-if="statistics.hall_of_fame.record_king" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Record King</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.record_king.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.record_king.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.speed_demon" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Speed Demon</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.speed_demon.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.speed_demon.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.sharpshooter" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Podium Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.sharpshooter.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.sharpshooter.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.hot_streak" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Streak Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.hot_streak.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.hot_streak.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.most_active" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Most Active</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.most_active.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.most_active.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.map_explorer" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Map Explorer</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.map_explorer.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.map_explorer.user.name)"></Link>
                        </div>
                    </div>
                </div>

                <!-- Physics & Weapons -->
                <div class="bg-black/20 border border-white/10 rounded-lg p-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Physics & Weapons</h3>
                    <div class="space-y-2">
                        <div v-if="statistics.hall_of_fame.vq3_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">VQ3 Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.vq3_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.vq3_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.cpm_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">CPM Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.cpm_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.cpm_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.rocket_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Rocket Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.rocket_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.rocket_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.plasma_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Plasma Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.plasma_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.plasma_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.grenade_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Grenade Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.grenade_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.grenade_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.bfg_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">BFG Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.bfg_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.bfg_master.user.name)"></Link>
                        </div>
                    </div>
                </div>

                <!-- Map Features -->
                <div class="bg-black/20 border border-white/10 rounded-lg p-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Map Features</h3>
                    <div class="space-y-2">
                        <div v-if="statistics.hall_of_fame.slick_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Slick Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.slick_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.slick_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.teleporter_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Teleporter Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.teleporter_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.teleporter_master.user.name)"></Link>
                        </div>
                        <div v-if="statistics.hall_of_fame.jumppad_master" class="flex justify-between items-center">
                            <span class="text-xs text-gray-400">Jumppad Master</span>
                            <Link :href="`/profile/${statistics.hall_of_fame.jumppad_master.user.id}`" class="text-sm font-bold text-white hover:text-blue-400 transition-colors" v-html="q3tohtml(statistics.hall_of_fame.jumppad_master.user.name)"></Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboards - Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column - Player Leaderboards -->
            <div class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-white/10 overflow-x-auto">
                    <button
                        @click="setActiveTab('total_records')"
                        :class="activeTab === 'total_records' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400 hover:text-white'"
                        class="flex-shrink-0 px-4 border-b-2 font-semibold transition-colors text-sm text-left h-[72px] flex flex-col justify-center"
                    >
                        <div>Total Records</div>
                        <div class="text-xs text-gray-300 font-normal mt-0.5">All records held</div>
                    </button>
                    <button
                        @click="setActiveTab('world_records')"
                        :class="activeTab === 'world_records' ? 'border-yellow-500 text-yellow-400' : 'border-transparent text-gray-400 hover:text-white'"
                        class="flex-shrink-0 px-4 border-b-2 font-semibold transition-colors text-sm text-left h-[72px] flex flex-col justify-center"
                    >
                        <div>World Records</div>
                        <div class="text-xs text-gray-300 font-normal mt-0.5">#1 positions</div>
                    </button>
                    <button
                        @click="setActiveTab('top3')"
                        :class="activeTab === 'top3' ? 'border-purple-500 text-purple-400' : 'border-transparent text-gray-400 hover:text-white'"
                        class="flex-shrink-0 px-4 border-b-2 font-semibold transition-colors text-sm text-left h-[72px] flex flex-col justify-center"
                    >
                        <div>Top 3</div>
                        <div class="text-xs text-gray-300 font-normal mt-0.5">Podium finishes</div>
                    </button>
                    <button
                        @click="setActiveTab('activity')"
                        :class="activeTab === 'activity' ? 'border-green-500 text-green-400' : 'border-transparent text-gray-400 hover:text-white'"
                        class="flex-shrink-0 px-4 border-b-2 font-semibold transition-colors text-sm text-left h-[72px] flex flex-col justify-center"
                    >
                        <div>Activity</div>
                        <div class="text-xs text-gray-300 font-normal mt-0.5">Last 30 days</div>
                    </button>
                    <button
                        @click="setActiveTab('map_diversity')"
                        :class="activeTab === 'map_diversity' ? 'border-cyan-500 text-cyan-400' : 'border-transparent text-gray-400 hover:text-white'"
                        class="flex-shrink-0 px-4 border-b-2 font-semibold transition-colors text-sm text-left h-[72px] flex flex-col justify-center"
                    >
                        <div>Map Diversity</div>
                        <div class="text-xs text-gray-300 font-normal mt-0.5">Unique maps</div>
                    </button>
                </div>

                <!-- Leaderboard Content -->
                <div class="p-4">
                <!-- Total Records Leaderboard -->
                <div v-if="activeTab === 'total_records'" class="space-y-1">
                    <div
                        v-for="(player, index) in paginatedLeaderboard.data"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': (leaderboardPage - 1) * itemsPerPage + index === 0,
                                'bg-gray-400/20 text-gray-300': (leaderboardPage - 1) * itemsPerPage + index === 1,
                                'bg-orange-600/20 text-orange-400': (leaderboardPage - 1) * itemsPerPage + index === 2,
                                'bg-black/20 text-gray-500': (leaderboardPage - 1) * itemsPerPage + index > 2
                            }"
                        >
                            {{ (leaderboardPage - 1) * itemsPerPage + index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-blue-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-blue-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="text-xl font-bold text-blue-400">{{ formatNumber(player.total) }}</div>
                        </Link>
                    </div>
                    <div v-if="paginatedLeaderboard.data.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- World Records Leaderboard -->
                <div v-if="activeTab === 'world_records'" class="space-y-1">
                    <div
                        v-for="(player, index) in paginatedLeaderboard.data"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': (leaderboardPage - 1) * itemsPerPage + index === 0,
                                'bg-gray-400/20 text-gray-300': (leaderboardPage - 1) * itemsPerPage + index === 1,
                                'bg-orange-600/20 text-orange-400': (leaderboardPage - 1) * itemsPerPage + index === 2,
                                'bg-black/20 text-gray-500': (leaderboardPage - 1) * itemsPerPage + index > 2
                            }"
                        >
                            {{ (leaderboardPage - 1) * itemsPerPage + index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-yellow-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-yellow-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="text-xl font-bold text-yellow-400">{{ formatNumber(player.top1) }}</div>
                        </Link>
                    </div>
                    <div v-if="paginatedLeaderboard.data.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- Top 3 Leaderboard -->
                <div v-if="activeTab === 'top3'" class="space-y-1">
                    <div
                        v-for="(player, index) in paginatedLeaderboard.data"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': (leaderboardPage - 1) * itemsPerPage + index === 0,
                                'bg-gray-400/20 text-gray-300': (leaderboardPage - 1) * itemsPerPage + index === 1,
                                'bg-orange-600/20 text-orange-400': (leaderboardPage - 1) * itemsPerPage + index === 2,
                                'bg-black/20 text-gray-500': (leaderboardPage - 1) * itemsPerPage + index > 2
                            }"
                        >
                            {{ (leaderboardPage - 1) * itemsPerPage + index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-purple-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-purple-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="text-xl font-bold text-purple-400">{{ formatNumber(player.top3) }}</div>
                        </Link>
                    </div>
                    <div v-if="paginatedLeaderboard.data.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- Activity Leaderboard -->
                <div v-if="activeTab === 'activity'" class="space-y-1">
                    <div
                        v-for="(player, index) in paginatedLeaderboard.data"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': (leaderboardPage - 1) * itemsPerPage + index === 0,
                                'bg-gray-400/20 text-gray-300': (leaderboardPage - 1) * itemsPerPage + index === 1,
                                'bg-orange-600/20 text-orange-400': (leaderboardPage - 1) * itemsPerPage + index === 2,
                                'bg-black/20 text-gray-500': (leaderboardPage - 1) * itemsPerPage + index > 2
                            }"
                        >
                            {{ (leaderboardPage - 1) * itemsPerPage + index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-green-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-green-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="text-xl font-bold text-green-400">{{ formatNumber(player.recent_count) }}</div>
                        </Link>
                    </div>
                    <div v-if="paginatedLeaderboard.data.length === 0" class="text-center py-8 text-gray-500">
                        No recent activity
                    </div>
                </div>

                <!-- Map Diversity Leaderboard -->
                <div v-if="activeTab === 'map_diversity'" class="space-y-1">
                    <div
                        v-for="(player, index) in paginatedLeaderboard.data"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': (leaderboardPage - 1) * itemsPerPage + index === 0,
                                'bg-gray-400/20 text-gray-300': (leaderboardPage - 1) * itemsPerPage + index === 1,
                                'bg-orange-600/20 text-orange-400': (leaderboardPage - 1) * itemsPerPage + index === 2,
                                'bg-black/20 text-gray-500': (leaderboardPage - 1) * itemsPerPage + index > 2
                            }"
                        >
                            {{ (leaderboardPage - 1) * itemsPerPage + index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-cyan-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-cyan-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="text-xl font-bold text-cyan-400">{{ formatNumber(player.unique_maps) }}</div>
                        </Link>
                    </div>
                    <div v-if="paginatedLeaderboard.data.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- Pagination for Left Table -->
                <div v-if="paginatedLeaderboard.totalPages > 1" class="flex items-center justify-center gap-2 mt-4 pt-4 border-t border-white/10">
                    <button
                        @click="leaderboardPage = Math.max(1, leaderboardPage - 1)"
                        :disabled="leaderboardPage === 1"
                        class="px-3 py-1 rounded bg-black/20 hover:bg-black/30 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm transition-all"
                    >
                        ← Prev
                    </button>
                    <span class="text-white text-sm">Page {{ leaderboardPage }} of {{ paginatedLeaderboard.totalPages }}</span>
                    <button
                        @click="leaderboardPage = Math.min(paginatedLeaderboard.totalPages, leaderboardPage + 1)"
                        :disabled="leaderboardPage === paginatedLeaderboard.totalPages"
                        class="px-3 py-1 rounded bg-black/20 hover:bg-black/30 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm transition-all"
                    >
                        Next →
                    </button>
                </div>

                </div>
            </div>

            <!-- Right Column - Untouched WRs -->
            <div class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl overflow-hidden">
                <!-- Header -->
                <div class="border-b border-white/10 px-4 py-3 bg-gradient-to-r from-purple-600/10 to-purple-800/10">
                    <h3 class="text-lg font-black text-white flex items-center gap-2">
                        <img src="/images/powerups/invis.svg" class="w-5 h-5" alt="Untouched" />
                        Untouched WRs
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">Longest-held world records</p>
                </div>

                <!-- Content -->
                <div class="p-4">
                    <div class="space-y-1">
                        <div
                            v-for="record in paginatedUntouchedWrs.data"
                            :key="record.id"
                            class="flex items-center gap-3 p-2 bg-black/20 hover:bg-black/30 rounded-lg transition-all duration-300"
                        >
                            <Link :href="`/profile/${record.user_id}`" class="flex items-center gap-2 flex-1 group">
                                <img
                                    :src="record.profile_photo_path ? `/storage/${record.profile_photo_path}` : '/images/null.jpg'"
                                    class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-purple-500/60 transition-all"
                                />
                                <div class="flex-1 min-w-0 flex items-center gap-2">
                                    <div class="text-white text-sm font-semibold group-hover:text-purple-400 transition-colors truncate" v-html="q3tohtml(record.user_name)"></div>
                                    <div class="flex items-center gap-1 text-xs text-gray-400 flex-shrink-0">
                                        <Link :href="`/maps/${record.mapname}`" class="truncate max-w-[100px] hover:text-blue-400 transition-colors" @click.stop>{{ record.mapname }}</Link>
                                        <img :src="`/images/modes/${record.physics}-icon.svg`" class="w-3 h-3 flex-shrink-0" :alt="record.physics" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 text-purple-400 flex-shrink-0">
                                    <span class="text-xl font-bold">{{ record.days_held }}</span>
                                    <span class="text-xs text-gray-500">days</span>
                                </div>
                            </Link>
                        </div>
                        <div v-if="paginatedUntouchedWrs.data.length === 0" class="text-center py-8 text-gray-500">
                            No untouched world records
                        </div>
                    </div>

                    <!-- Pagination for Untouched WRs -->
                    <div v-if="paginatedUntouchedWrs.totalPages > 1" class="flex items-center justify-center gap-2 mt-4 pt-4 border-t border-white/10">
                        <button
                            @click="untouchedWrsPage = Math.max(1, untouchedWrsPage - 1)"
                            :disabled="untouchedWrsPage === 1"
                            class="px-3 py-1 rounded bg-black/20 hover:bg-black/30 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm transition-all"
                        >
                            ← Prev
                        </button>
                        <span class="text-white text-sm">Page {{ untouchedWrsPage }} of {{ paginatedUntouchedWrs.totalPages }}</span>
                        <button
                            @click="untouchedWrsPage = Math.min(paginatedUntouchedWrs.totalPages, untouchedWrsPage + 1)"
                            :disabled="untouchedWrsPage === paginatedUntouchedWrs.totalPages"
                            class="px-3 py-1 rounded bg-black/20 hover:bg-black/30 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm transition-all"
                        >
                            Next →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Top 10 Density -->
            <div v-if="statistics.overview.top10_count !== undefined" class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-indigo-600/20 to-indigo-800/20 border border-indigo-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of top 10 leaderboard positions (1st through 10th place) held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/0 to-indigo-600/0 group-hover:from-indigo-500/10 group-hover:to-indigo-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-indigo-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top10_count) }}</span>
                    <span class="text-xs font-medium text-indigo-300 whitespace-nowrap">Top 10 Positions</span>
                </div>
            </div>

            <!-- Average WR Age -->
            <div v-if="statistics.overview.avg_wr_age_days !== undefined" class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-amber-600/20 to-amber-800/20 border border-amber-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Average age (in days) of all world records held by clan members - how fresh or old the records are">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-amber-600/0 group-hover:from-amber-500/10 group-hover:to-amber-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-amber-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ statistics.overview.avg_wr_age_days }}</span>
                    <span class="text-xs font-medium text-amber-300 whitespace-nowrap">Avg WR Age (days)</span>
                </div>
            </div>

            <!-- Completion Rate -->
            <div v-if="statistics.overview.completion_rate !== undefined" class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-teal-600/20 to-teal-800/20 border border-teal-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Percentage of all available maps that clan members have set records on - shows map diversity and coverage">
                <div class="absolute inset-0 bg-gradient-to-br from-teal-500/0 to-teal-600/0 group-hover:from-teal-500/10 group-hover:to-teal-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-teal-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                    <span class="text-2xl font-black text-white">{{ statistics.overview.completion_rate.toFixed(1) }}%</span>
                    <span class="text-xs font-medium text-teal-300 whitespace-nowrap">Map Coverage</span>
                </div>
            </div>

            <!-- Physics Split -->
            <div v-if="statistics.overview.vq3_count !== undefined && statistics.overview.cpm_count !== undefined" class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-gradient-to-br from-pink-600/20 to-rose-800/20 border border-pink-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Distribution of records between VQ3 and CPM physics modes - shows which physics style the clan prefers">
                <div class="absolute inset-0 bg-gradient-to-br from-pink-500/0 to-rose-600/0 group-hover:from-pink-500/10 group-hover:to-rose-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <img src="/images/modes/vq3-icon.svg" class="w-6 h-6" alt="VQ3" />
                    <span class="text-2xl font-black text-white">{{ Math.round((statistics.overview.vq3_count / statistics.overview.total_records) * 100) }}%</span>
                    <span class="text-gray-400">/</span>
                    <img src="/images/modes/cpm-icon.svg" class="w-6 h-6" alt="CPM" />
                    <span class="text-2xl font-black text-white">{{ Math.round((statistics.overview.cpm_count / statistics.overview.total_records) * 100) }}%</span>
                    <span class="text-xs font-medium text-pink-300 whitespace-nowrap ml-2">Physics Preference</span>
                </div>
            </div>
        </div>

        <!-- Podium Sweeps -->
        <div v-if="statistics.special_sections && statistics.special_sections.podium_sweeps && statistics.special_sections.podium_sweeps.length > 0" class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <img src="/images/powerups/quad.svg" class="w-8 h-8" alt="Podium Sweeps" />
                Podium Sweeps
            </h2>
            <div class="text-sm text-gray-400 mb-4">Maps where multiple clan members secured top 3 positions</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="sweep in statistics.special_sections.podium_sweeps" :key="`${sweep.mapname}-${sweep.physics}-${sweep.mode}`" class="bg-black/20 hover:bg-black/30 rounded-xl p-4 transition-all">
                    <div class="font-bold text-white mb-1">{{ sweep.mapname }}</div>
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        <img :src="`/images/modes/${sweep.physics}-icon.svg`" class="w-4 h-4" :alt="sweep.physics" />
                        <span class="capitalize">{{ sweep.mode }}</span>
                    </div>
                    <div class="mt-2 text-yellow-400 font-semibold">{{ sweep.clan_members_in_top3 }} members in top 3</div>
                </div>
            </div>
        </div>

        <!-- Contested Positions -->
        <div v-if="statistics.special_sections && statistics.special_sections.contested_positions && statistics.special_sections.contested_positions.length > 0" class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <img src="/images/weapons/iconw_gauntlet.svg" class="w-8 h-8" alt="Contested" />
                Contested Positions
            </h2>
            <div class="text-sm text-gray-400 mb-4">Closest world records - barely ahead of 2nd place</div>
            <div class="space-y-2">
                <div v-for="record in statistics.special_sections.contested_positions" :key="record.id" class="flex items-center justify-between bg-black/20 hover:bg-black/30 rounded-xl p-4 transition-all">
                    <div class="flex items-center gap-4 flex-1">
                        <img :src="record.profile_photo_path ? `/storage/${record.profile_photo_path}` : '/images/null.jpg'" class="w-10 h-10 rounded-full object-cover" />
                        <div class="flex-1">
                            <div class="font-bold text-white" v-html="q3tohtml(record.user_name)"></div>
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <span>{{ record.mapname }}</span>
                                <img :src="`/images/modes/${record.physics}-icon.svg`" class="w-4 h-4" :alt="record.physics" />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-black text-red-400">{{ record.margin }}</div>
                            <div class="text-xs text-gray-500">ms margin</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
