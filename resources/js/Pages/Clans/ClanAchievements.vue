<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    statistics: Object,
});

const activeTab = ref('total_records');
const untouchableFilter = ref('all');

const setActiveTab = (tab) => {
    activeTab.value = tab;
};

const setUntouchableFilter = (filter) => {
    untouchableFilter.value = filter;
};

const filteredUntouchableRecords = () => {
    if (!props.statistics?.special_sections?.untouchable_records) return [];

    const records = props.statistics.special_sections.untouchable_records;

    if (untouchableFilter.value === 'solo') {
        return records.filter(r => !r.is_tied);
    } else if (untouchableFilter.value === 'tied') {
        return records.filter(r => r.is_tied);
    }

    return records;
};

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
            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-600/20 to-blue-800/20 border border-blue-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of records held by clan members across all maps and modes">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/0 to-blue-600/0 group-hover:from-blue-500/10 group-hover:to-blue-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.total_records) }}</div>
                        <div class="text-xs font-medium text-blue-300">Total Records</div>
                    </div>
                </div>
            </div>

            <!-- Top 1 Positions -->
            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-yellow-600/20 to-orange-800/20 border border-yellow-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of #1 world record positions held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/0 to-orange-600/0 group-hover:from-yellow-500/10 group-hover:to-orange-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-yellow-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top1_count) }}</div>
                        <div class="text-xs font-medium text-yellow-300">World Records</div>
                    </div>
                </div>
            </div>

            <!-- Top 3 Positions -->
            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-600/20 to-pink-800/20 border border-purple-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of top 3 podium finishes (1st, 2nd, or 3rd place) held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/0 to-pink-600/0 group-hover:from-purple-500/10 group-hover:to-pink-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-purple-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top3_count) }}</div>
                        <div class="text-xs font-medium text-purple-300">Podium Finishes</div>
                    </div>
                </div>
            </div>

            <!-- Longest Streak -->
            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-green-600/20 to-emerald-800/20 border border-green-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Longest consecutive days with at least one record set by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500/0 to-emerald-600/0 group-hover:from-green-500/10 group-hover:to-emerald-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-green-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ statistics.overview.longest_streak }}</div>
                        <div class="text-xs font-medium text-green-300">Day Streak</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hall of Fame -->
        <div class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-yellow-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                </svg>
                Hall of Fame
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <!-- Record King -->
                <div v-if="statistics.hall_of_fame.record_king" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-blue-600/10 to-blue-800/10 border border-blue-500/20 p-3 hover:border-blue-500/50 transition-all duration-300" title="Most total records set - the player who dominates the leaderboards with the highest number of times across all maps">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/powerups/quad.svg" class="w-8 h-8 flex-shrink-0" alt="Record King" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-blue-400 mb-1">Record King</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.record_king.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.record_king.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.record_king.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-blue-500/30 group-hover/link:ring-blue-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-blue-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.record_king.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-blue-400 flex-shrink-0">{{ formatNumber(statistics.hall_of_fame.record_king.value) }}</div>
                    </div>
                </div>

                <!-- Speed Demon -->
                <div v-if="statistics.hall_of_fame.speed_demon" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-yellow-600/10 to-orange-800/10 border border-yellow-500/20 p-3 hover:border-yellow-500/50 transition-all duration-300" title="Most world records held - the player with the most #1 positions, proving they're the fastest on the most maps">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/powerups/haste.svg" class="w-8 h-8 flex-shrink-0" alt="Speed Demon" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-yellow-400 mb-1">Speed Demon</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.speed_demon.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.speed_demon.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.speed_demon.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-yellow-500/30 group-hover/link:ring-yellow-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-yellow-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.speed_demon.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-yellow-400 flex-shrink-0">{{ formatNumber(statistics.hall_of_fame.speed_demon.value) }} WRs</div>
                    </div>
                </div>

                <!-- Hot Streak -->
                <div v-if="statistics.hall_of_fame.hot_streak" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-red-600/10 to-orange-800/10 border border-red-500/20 p-3 hover:border-red-500/50 transition-all duration-300" title="Longest consecutive days streak - the player who set records on the most consecutive days, showing incredible dedication and consistency">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/powerups/regen.svg" class="w-8 h-8 flex-shrink-0" alt="Hot Streak" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-red-400 mb-1">Hot Streak</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.hot_streak.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.hot_streak.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.hot_streak.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-red-500/30 group-hover/link:ring-red-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-red-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.hot_streak.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-red-400 flex-shrink-0">{{ statistics.hall_of_fame.hot_streak.value }} days</div>
                    </div>
                </div>

                <!-- Sharpshooter -->
                <div v-if="statistics.hall_of_fame.sharpshooter" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-purple-600/10 to-pink-800/10 border border-purple-500/20 p-3 hover:border-purple-500/50 transition-all duration-300" title="Most top 3 finishes - consistently landing on the podium across multiple maps, showing elite-level skill and precision">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/weapons/iconw_railgun.svg" class="w-8 h-8 flex-shrink-0" alt="Sharpshooter" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-purple-400 mb-1">Sharpshooter</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.sharpshooter.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.sharpshooter.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.sharpshooter.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-purple-500/30 group-hover/link:ring-purple-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-purple-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.sharpshooter.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-purple-400 flex-shrink-0">{{ formatNumber(statistics.hall_of_fame.sharpshooter.value) }} top3</div>
                    </div>
                </div>

                <!-- Most Active -->
                <div v-if="statistics.hall_of_fame.most_active" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-green-600/10 to-emerald-800/10 border border-green-500/20 p-3 hover:border-green-500/50 transition-all duration-300" title="Most records in last 30 days - the grinder who's been putting in the work recently, constantly improving and pushing for better times">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/weapons/iconw_rocket.svg" class="w-8 h-8 flex-shrink-0" alt="Most Active" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-green-400 mb-1">Most Active (30d)</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.most_active.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.most_active.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.most_active.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-green-500/30 group-hover/link:ring-green-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-green-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.most_active.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-green-400 flex-shrink-0">{{ formatNumber(statistics.hall_of_fame.most_active.value) }} records</div>
                    </div>
                </div>

                <!-- Map Explorer -->
                <div v-if="statistics.hall_of_fame.map_explorer" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-cyan-600/10 to-blue-800/10 border border-cyan-500/20 p-3 hover:border-cyan-500/50 transition-all duration-300" title="Most unique maps played - the adventurer who's explored the most different maps, never afraid to try new challenges and territories">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="/images/functions/teleporter.svg" class="w-8 h-8 flex-shrink-0" alt="Map Explorer" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-cyan-400 mb-1">Map Explorer</div>
                                <Link :href="`/profile/${statistics.hall_of_fame.map_explorer.user.id}`" class="flex items-center gap-2 group/link">
                                    <img
                                        :src="statistics.hall_of_fame.map_explorer.user.profile_photo_path ? `/storage/${statistics.hall_of_fame.map_explorer.user.profile_photo_path}` : '/images/null.jpg'"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-cyan-500/30 group-hover/link:ring-cyan-500/60 transition-all flex-shrink-0"
                                    />
                                    <div class="text-white text-sm font-bold group-hover/link:text-cyan-400 transition-colors truncate" v-html="q3tohtml(statistics.hall_of_fame.map_explorer.user.name)"></div>
                                </Link>
                            </div>
                        </div>
                        <div class="text-lg font-black text-cyan-400 flex-shrink-0">{{ formatNumber(statistics.hall_of_fame.map_explorer.value) }} maps</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboards -->
        <div class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
            <!-- Tabs -->
            <div class="flex border-b border-white/10 overflow-x-auto">
                <button
                    @click="setActiveTab('total_records')"
                    :class="activeTab === 'total_records' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-6 py-4 border-b-2 font-semibold transition-colors"
                >
                    Total Records
                </button>
                <button
                    @click="setActiveTab('top_positions')"
                    :class="activeTab === 'top_positions' ? 'border-yellow-500 text-yellow-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-6 py-4 border-b-2 font-semibold transition-colors"
                >
                    Top Positions
                </button>
                <button
                    @click="setActiveTab('activity')"
                    :class="activeTab === 'activity' ? 'border-green-500 text-green-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-6 py-4 border-b-2 font-semibold transition-colors"
                >
                    Activity (30d)
                </button>
                <button
                    @click="setActiveTab('map_diversity')"
                    :class="activeTab === 'map_diversity' ? 'border-cyan-500 text-cyan-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-6 py-4 border-b-2 font-semibold transition-colors"
                >
                    Map Diversity
                </button>
            </div>

            <!-- Leaderboard Content -->
            <div class="p-4">
                <!-- Total Records Leaderboard -->
                <div v-if="activeTab === 'total_records'" class="space-y-1">
                    <div
                        v-for="(player, index) in statistics.leaderboards.total_records"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-white/5 hover:bg-white/10 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': index === 0,
                                'bg-gray-400/20 text-gray-300': index === 1,
                                'bg-orange-600/20 text-orange-400': index === 2,
                                'bg-white/5 text-gray-500': index > 2
                            }"
                        >
                            {{ index + 1 }}
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
                    <div v-if="statistics.leaderboards.total_records.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- Top Positions Leaderboard -->
                <div v-if="activeTab === 'top_positions'" class="space-y-1">
                    <div
                        v-for="(player, index) in statistics.leaderboards.top_positions"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-white/5 hover:bg-white/10 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': index === 0,
                                'bg-gray-400/20 text-gray-300': index === 1,
                                'bg-orange-600/20 text-orange-400': index === 2,
                                'bg-white/5 text-gray-500': index > 2
                            }"
                        >
                            {{ index + 1 }}
                        </div>
                        <Link :href="`/profile/${player.id}`" class="flex items-center gap-2 flex-1 group">
                            <img
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                class="w-8 h-8 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-yellow-500/60 transition-all"
                            />
                            <div class="flex-1">
                                <div class="text-white text-sm font-semibold group-hover:text-yellow-400 transition-colors" v-html="q3tohtml(player.name)"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="text-center">
                                    <div class="text-xs text-gray-500">WRs</div>
                                    <div class="text-base font-bold text-yellow-400">{{ formatNumber(player.top1) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-500">Top3</div>
                                    <div class="text-base font-bold text-purple-400">{{ formatNumber(player.top3) }}</div>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <div v-if="statistics.leaderboards.top_positions.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>

                <!-- Activity Leaderboard -->
                <div v-if="activeTab === 'activity'" class="space-y-1">
                    <div
                        v-for="(player, index) in statistics.leaderboards.activity"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-white/5 hover:bg-white/10 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': index === 0,
                                'bg-gray-400/20 text-gray-300': index === 1,
                                'bg-orange-600/20 text-orange-400': index === 2,
                                'bg-white/5 text-gray-500': index > 2
                            }"
                        >
                            {{ index + 1 }}
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
                    <div v-if="statistics.leaderboards.activity.length === 0" class="text-center py-8 text-gray-500">
                        No recent activity
                    </div>
                </div>

                <!-- Map Diversity Leaderboard -->
                <div v-if="activeTab === 'map_diversity'" class="space-y-1">
                    <div
                        v-for="(player, index) in statistics.leaderboards.map_diversity"
                        :key="player.id"
                        class="flex items-center gap-3 p-2 bg-white/5 hover:bg-white/10 rounded-lg transition-all duration-300"
                    >
                        <div class="flex items-center justify-center w-6 h-6 rounded-full font-bold text-sm"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': index === 0,
                                'bg-gray-400/20 text-gray-300': index === 1,
                                'bg-orange-600/20 text-orange-400': index === 2,
                                'bg-white/5 text-gray-500': index > 2
                            }"
                        >
                            {{ index + 1 }}
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
                    <div v-if="statistics.leaderboards.map_diversity.length === 0" class="text-center py-8 text-gray-500">
                        No records yet
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Top 10 Density -->
            <div v-if="statistics.overview.top10_count !== undefined" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-indigo-600/20 to-indigo-800/20 border border-indigo-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Total number of top 10 leaderboard positions (1st through 10th place) held by clan members">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/0 to-indigo-600/0 group-hover:from-indigo-500/10 group-hover:to-indigo-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-indigo-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ formatNumber(statistics.overview.top10_count) }}</div>
                        <div class="text-xs font-medium text-indigo-300">Top 10 Positions</div>
                    </div>
                </div>
            </div>

            <!-- Average WR Age -->
            <div v-if="statistics.overview.avg_wr_age_days !== undefined" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-amber-600/20 to-amber-800/20 border border-amber-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Average age (in days) of all world records held by clan members - how fresh or old the records are">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-amber-600/0 group-hover:from-amber-500/10 group-hover:to-amber-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-amber-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ statistics.overview.avg_wr_age_days }}</div>
                        <div class="text-xs font-medium text-amber-300">Avg WR Age (days)</div>
                    </div>
                </div>
            </div>

            <!-- Completion Rate -->
            <div v-if="statistics.overview.completion_rate !== undefined" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-teal-600/20 to-teal-800/20 border border-teal-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Percentage of all available maps that clan members have set records on - shows map diversity and coverage">
                <div class="absolute inset-0 bg-gradient-to-br from-teal-500/0 to-teal-600/0 group-hover:from-teal-500/10 group-hover:to-teal-600/10 transition-all duration-500"></div>
                <div class="relative flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-teal-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                    <div>
                        <div class="text-2xl font-black text-white">{{ statistics.overview.completion_rate.toFixed(1) }}%</div>
                        <div class="text-xs font-medium text-teal-300">Map Coverage</div>
                    </div>
                </div>
            </div>

            <!-- Physics Split -->
            <div v-if="statistics.overview.vq3_count !== undefined && statistics.overview.cpm_count !== undefined" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-pink-600/20 to-rose-800/20 border border-pink-500/30 p-3 hover:scale-105 transition-transform duration-300" title="Distribution of records between VQ3 and CPM physics modes - shows which physics style the clan prefers">
                <div class="absolute inset-0 bg-gradient-to-br from-pink-500/0 to-rose-600/0 group-hover:from-pink-500/10 group-hover:to-rose-600/10 transition-all duration-500"></div>
                <div class="relative">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/vq3-icon.svg" class="w-4 h-4" alt="VQ3" />
                            <span class="text-xl font-black text-white">{{ formatNumber(statistics.overview.vq3_count) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/cpm-icon.svg" class="w-4 h-4" alt="CPM" />
                            <span class="text-xl font-black text-white">{{ formatNumber(statistics.overview.cpm_count) }}</span>
                        </div>
                    </div>
                    <div class="text-xs font-medium text-pink-300">Physics Preference</div>
                </div>
            </div>
        </div>

        <!-- Podium Sweeps -->
        <div v-if="statistics.special_sections && statistics.special_sections.podium_sweeps && statistics.special_sections.podium_sweeps.length > 0" class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <img src="/images/powerups/quad.svg" class="w-8 h-8" alt="Podium Sweeps" />
                Podium Sweeps
            </h2>
            <div class="text-sm text-gray-400 mb-4">Maps where multiple clan members secured top 3 positions</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="sweep in statistics.special_sections.podium_sweeps" :key="`${sweep.mapname}-${sweep.physics}-${sweep.mode}`" class="bg-white/5 hover:bg-white/10 rounded-xl p-4 transition-all">
                    <div class="font-bold text-white mb-1">{{ sweep.mapname }}</div>
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        <img :src="`/images/modes/${sweep.physics}-icon.svg`" class="w-4 h-4" :alt="sweep.physics" />
                        <span class="capitalize">{{ sweep.mode }}</span>
                    </div>
                    <div class="mt-2 text-yellow-400 font-semibold">{{ sweep.clan_members_in_top3 }} members in top 3</div>
                </div>
            </div>
        </div>

        <!-- Untouchable World Records -->
        <div v-if="statistics.special_sections && statistics.special_sections.untouchable_records && statistics.special_sections.untouchable_records.length > 0" class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
            <div class="p-6 pb-3">
                <h2 class="text-2xl font-black text-white mb-3 flex items-center gap-2">
                    <img src="/images/powerups/invis.svg" class="w-6 h-6" alt="Untouchable" />
                    Untouchable World Records
                </h2>
                <div class="text-xs text-gray-400 mb-3">Longest-held #1 positions - world records standing the test of time</div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-white/10 overflow-x-auto px-6">
                <button
                    @click="setUntouchableFilter('all')"
                    :class="untouchableFilter === 'all' ? 'border-green-500 text-green-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-4 py-3 border-b-2 font-semibold transition-colors text-sm"
                >
                    All Records
                </button>
                <button
                    @click="setUntouchableFilter('solo')"
                    :class="untouchableFilter === 'solo' ? 'border-yellow-500 text-yellow-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-4 py-3 border-b-2 font-semibold transition-colors text-sm"
                >
                    Solo WRs
                </button>
                <button
                    @click="setUntouchableFilter('tied')"
                    :class="untouchableFilter === 'tied' ? 'border-purple-500 text-purple-400' : 'border-transparent text-gray-400 hover:text-white'"
                    class="flex-shrink-0 px-4 py-3 border-b-2 font-semibold transition-colors text-sm"
                >
                    Tied WRs
                </button>
            </div>

            <div class="p-4">
                <div class="space-y-1">
                    <div v-for="record in filteredUntouchableRecords()" :key="record.id" class="flex items-center justify-between bg-white/5 hover:bg-white/10 rounded-lg p-2 transition-all">
                        <div class="flex items-center gap-2 flex-1">
                            <img :src="record.profile_photo_path ? `/storage/${record.profile_photo_path}` : '/images/null.jpg'" class="w-8 h-8 rounded-full object-cover" />
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-white text-sm truncate" v-html="q3tohtml(record.user_name)"></div>
                                <div class="flex items-center gap-1 text-xs text-gray-400">
                                    <span class="truncate">{{ record.mapname }}</span>
                                    <img :src="`/images/modes/${record.physics}-icon.svg`" class="w-3 h-3 flex-shrink-0" :alt="record.physics" />
                                    <span v-if="record.is_tied" class="text-purple-400 ml-1">(Tied)</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-black text-green-400">{{ record.days_held }}</div>
                                <div class="text-xs text-gray-500">days</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredUntouchableRecords().length === 0" class="text-center py-8 text-gray-500">
                        No records in this category
                    </div>
                </div>
            </div>
        </div>

        <!-- Contested Positions -->
        <div v-if="statistics.special_sections && statistics.special_sections.contested_positions && statistics.special_sections.contested_positions.length > 0" class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <img src="/images/weapons/iconw_gauntlet.svg" class="w-8 h-8" alt="Contested" />
                Contested Positions
            </h2>
            <div class="text-sm text-gray-400 mb-4">Closest world records - barely ahead of 2nd place</div>
            <div class="space-y-2">
                <div v-for="record in statistics.special_sections.contested_positions" :key="record.id" class="flex items-center justify-between bg-white/5 hover:bg-white/10 rounded-xl p-4 transition-all">
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

        <!-- Rival Clans -->
        <div v-if="statistics.special_sections && statistics.special_sections.rival_clans && statistics.special_sections.rival_clans.length > 0" class="bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 rounded-2xl p-8">
            <h2 class="text-3xl font-black text-white mb-6 flex items-center gap-3">
                <img src="/images/weapons/iconw_rocket.svg" class="w-8 h-8" alt="Rivals" />
                Rival Clans
            </h2>
            <div class="text-sm text-gray-400 mb-4">Top clans ranked by World Records and Top 3 finishes</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="rival in statistics.special_sections.rival_clans" :key="rival.id" :href="`/clans/${rival.id}`" class="group bg-white/5 hover:bg-white/10 rounded-xl p-4 transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <img :src="rival.image ? `/storage/${rival.image}` : '/images/null.jpg'" class="w-12 h-12 rounded-lg object-cover" />
                        <div class="flex-1">
                            <div class="font-bold text-white group-hover:text-yellow-400 transition-colors" v-html="q3tohtml(rival.name)"></div>
                            <div v-if="rival.tag" class="text-sm text-gray-500">{{ rival.tag }}</div>
                        </div>
                    </div>
                    <div class="space-y-1 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">World Records:</span>
                            <span class="font-bold text-yellow-400">{{ rival.total_wrs }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Top 3:</span>
                            <span class="font-bold text-purple-400">{{ rival.total_top3 }}</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
