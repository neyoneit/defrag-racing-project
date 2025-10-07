<script setup>
    import { ref, watch, computed } from 'vue';
    import { Head, router, Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { useClipboard } from '@/Composables/useClipboard';
    
    const { copy, copyState } = useClipboard();

    const props = defineProps({
        user: Object,
        vq3Records: Object,
        cpmRecords: Object,
        type: String,
        profile: Object,
        cpm_world_records: {
            type: Number,
            default: 0
        },
        vq3_world_records: {
            type: Number,
            default: 0
        },
        cpm_competitors: Object,
        vq3_competitors: Object,
        cpm_rivals: Object,
        vq3_rivals: Object,
        unplayed_maps: Object,
        total_maps: {
            type: Number,
            default: 0
        },
        hasProfile: Boolean,
        load_times: Object
    });

    const color = ref(props.user?.color ? props.user.color : '#ffffff');

    const options = ref({
        'latest': {
            label: 'Latest Records',
            icon: 'clock',
            color: 'text-blue-400'
        },
        'recentlybeaten': {
            label: 'Recently Beaten',
            icon: 'brokenheart',
            color: 'text-red-400'
        },
        'tiedranks': {
            label: 'Tied Ranks',
            icon: 'circle-equal',
            color: 'text-yellow-400'
        },
        'activityhistory': {
            label: 'Activity History',
            icon: 'activity-heartbeat',
            color: 'text-purple-400'
        },
        'untouchable': {
            label: 'Untouchable WRs',
            icon: 'trophy',
            color: 'text-yellow-400'
        },
        'bestranks': {
            label: 'Best Ranks',
            icon: 'fire',
            color: 'text-green-400'
        },
        'besttimes': {
            label: 'Best Times',
            icon: 'bolt',
            color: 'text-green-400'
        },
        'worstranks': {
            label: 'Worst Ranks',
            icon: 'trending-down',
            color: 'text-orange-400'
        },
        'worsttimes': {
            label: 'Worst Times',
            icon: 'hourglass',
            color: 'text-orange-400'
        },
    });

    const stats = [
        {
            label: 'Strafe Records',
            value: 'strafe_records',
            icon: 'strafe',
            color: 'text-orange-500',
            type: 'imgsvg'
        },
        {
            label: 'Fastcaps Records',
            value: 'ctf_records',
            icon: 'flag',
            color: 'text-cyan-500',
            type: 'svg'
        },
        {
            label: 'Machinegun Records',
            value: 'mg_records',
            icon: 'mg',
            type: 'item'
        },
        {
            label: 'Shotgun Records',
            value: 'sg_records',
            icon: 'sg',
            type: 'item'
        },
        {
            label: 'Grenade Records',
            value: 'grenade_records',
            icon: 'gl',
            type: 'item'
        },
        {
            label: 'Rocket Records',
            value: 'rocket_records',
            icon: 'rl',
            type: 'item'
        },
        {
            label: 'Lightning Records',
            value: 'lg_records',
            icon: 'lg',
            type: 'item'
        },
        {
            label: 'Railgun Records',
            value: 'rg_records',
            icon: 'rg',
            type: 'item'
        },
        {
            label: 'Plasma Records',
            value: 'plasma_records',
            icon: 'pg',
            type: 'item'
        },
        {
            label: 'BFG Records',
            value: 'bfg_records',
            icon: 'bfg',
            type: 'item'
        },
    ];

    const selectedOption = ref(props.type || 'latest');
    const loading = ref('');
    const selectedRival = ref(null);
    const beatableRecords = ref([]);
    const loadingBeatable = ref(false);

    // Direct Competitor Comparison
    const competitorSearch = ref('');
    const competitorSearchResults = ref([]);
    const showCompetitorDropdown = ref(false);
    const selectedCompetitor = ref(null);
    const vq3Comparison = ref([]);
    const cpmComparison = ref([]);
    const loadingComparison = ref(false);
    const vq3Page = ref(1);
    const cpmPage = ref(1);
    const perPage = 10;

    // Computed properties for pagination
    const vq3ComparisonPaginated = computed(() => {
        const start = (vq3Page.value - 1) * perPage;
        const end = start + perPage;
        return vq3Comparison.value.slice(start, end);
    });

    const cpmComparisonPaginated = computed(() => {
        const start = (cpmPage.value - 1) * perPage;
        const end = start + perPage;
        return cpmComparison.value.slice(start, end);
    });

    const vq3TotalPages = computed(() => Math.ceil(vq3Comparison.value.length / perPage));
    const cpmTotalPages = computed(() => Math.ceil(cpmComparison.value.length / perPage));

    const mode = ref('all');

    const selectOption = (option) => {
        if (loading.value === option) {
            return;
        }

        router.reload({
            data: {
                type: option,
                mode: mode.value,
                vq3_page: 1,
                cpm_page: 1
            },
            onStart: () => {
                loading.value = option;
            },
            onFinish: () => {
                loading.value = '';
            }
        })
    }

    const sortByMode = (newMode) => {
        mode.value = newMode;
        router.reload({
            data: {
                type: props.type,
                mode: newMode,
                vq3_page: 1,
                cpm_page: 1
            }
        });
    }

    const loadBeatableRecords = async (rivalMddId, rivalName) => {
        selectedRival.value = { id: rivalMddId, name: rivalName };
        loadingBeatable.value = true;

        try {
            const response = await fetch(`/api/profile/${props.user.id}/beatable-records/${rivalMddId}?physics=cpm`);
            const data = await response.json();
            beatableRecords.value = data;
        } catch (error) {
            console.error('Error loading beatable records:', error);
            beatableRecords.value = [];
        } finally {
            loadingBeatable.value = false;
        }
    }

    const closeBeatableRecords = () => {
        selectedRival.value = null;
        beatableRecords.value = [];
    }

    // Direct Competitor Comparison Methods
    let searchTimeout = null;
    const searchCompetitors = () => {
        if (searchTimeout) clearTimeout(searchTimeout);

        if (competitorSearch.value.length < 2) {
            competitorSearchResults.value = [];
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/api/search-players?q=${encodeURIComponent(competitorSearch.value)}`);
                const data = await response.json();
                competitorSearchResults.value = data;
            } catch (error) {
                console.error('Error searching players:', error);
                competitorSearchResults.value = [];
            }
        }, 300);
    };

    const selectCompetitor = async (player) => {
        selectedCompetitor.value = player;
        showCompetitorDropdown.value = false;
        competitorSearch.value = player.plain_name;
        loadingComparison.value = true;

        // Reset pagination
        vq3Page.value = 1;
        cpmPage.value = 1;

        // Use player.user.id if user exists, otherwise use player.id directly
        const rivalUserId = player.user?.id || player.id;

        try {
            // Fetch VQ3 comparison
            const vq3Response = await fetch(`/api/profile/${props.user.id}/compare/${rivalUserId}?physics=vq3`);
            vq3Comparison.value = await vq3Response.json();

            // Fetch CPM comparison
            const cpmResponse = await fetch(`/api/profile/${props.user.id}/compare/${rivalUserId}?physics=cpm`);
            cpmComparison.value = await cpmResponse.json();
        } catch (error) {
            console.error('Error loading comparison:', error);
            vq3Comparison.value = [];
            cpmComparison.value = [];
        } finally {
            loadingComparison.value = false;
        }
    };

    const clearCompetitor = () => {
        selectedCompetitor.value = null;
        competitorSearch.value = '';
        competitorSearchResults.value = [];
        vq3Comparison.value = [];
        cpmComparison.value = [];
        vq3Page.value = 1;
        cpmPage.value = 1;
    };

    watch(() => props.type, (newVal) => {
        if (options.value[newVal]) {
            selectedOption.value = newVal;
        } else {
            selectedOption.value = 'latest';
        }
    });

    // Map Completionist progress calculation
    const playedMapsCount = computed(() => {
        return props.total_maps - (props.unplayed_maps?.total || 0);
    });

    const completionPercentage = computed(() => {
        if (props.total_maps === 0) return 0;
        return ((playedMapsCount.value / props.total_maps) * 100).toFixed(3);
    });

</script>

<template>
    <div>
        <Head title="Profile" />

        <!-- Profile Header with Background -->
        <div class="relative h-[280px] overflow-hidden">
            <!-- Background Image -->
            <div v-if="user?.profile_background_path" class="absolute inset-0">
                <div class="absolute inset-0 flex justify-center">
                    <div class="relative w-full max-w-[1920px] h-full bg-top bg-no-repeat" :style="`background-image: url('/storage/${user.profile_background_path}'); background-size: 1920px auto;`">
                        <!-- Fade left edge of image -->
                        <div class="absolute left-0 top-0 bottom-0 w-80 bg-gradient-to-r from-gray-900 to-transparent"></div>
                        <!-- Fade right edge of image -->
                        <div class="absolute right-0 top-0 bottom-0 w-80 bg-gradient-to-l from-gray-900 to-transparent"></div>
                    </div>
                </div>
                <!-- Fade to transparent at bottom -->
                <div class="absolute inset-0 bg-gradient-to-b from-transparent from-0% via-transparent via-60% to-gray-900 to-100%"></div>
                <!-- Dark overlay on top -->
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/30 to-transparent"></div>
            </div>

            <!-- Profile Content - Compact Design -->
            <div class="relative h-full flex items-center justify-center px-4 pt-2">
                <div class="flex items-center gap-6">
                    <!-- Avatar with Ring -->
                    <div class="relative group flex-shrink-0">
                        <!-- Animated Ring -->
                        <div class="absolute -inset-2 bg-gradient-to-r from-purple-600 via-pink-600 to-blue-600 rounded-full opacity-75 group-hover:opacity-100 blur-lg transition duration-500 group-hover:duration-200 animate-pulse"></div>

                        <!-- Avatar -->
                        <div class="relative backdrop-blur-md bg-black/20 p-1.5 rounded-full">
                            <div :class="'avatar-effect-' + (user?.avatar_effect || 'none')" :style="`--effect-color: ${user?.color || '#ffffff'}; --border-color: ${user?.avatar_border_color || '#6b7280'}`">
                                <img class="h-24 w-24 rounded-full border-4 object-cover relative" :style="`border-color: ${user?.avatar_border_color || '#6b7280'}`" :src="user?.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" :alt="user?.name ?? profile.name">
                            </div>
                        </div>
                    </div>

                    <!-- Info Section -->
                    <div class="flex flex-col gap-2">
                        <!-- Country Flag + Name -->
                        <div class="flex items-center gap-3">
                            <div class="backdrop-blur-md bg-black/30 px-3 py-1.5 rounded-lg border border-white/20">
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${user?.country ?? profile.country}.png`" :title="user?.country ?? profile.country" class="w-8 h-5">
                            </div>
                            <div :class="'name-effect-' + (user?.name_effect || 'none')" :style="`--effect-color: ${user?.color || '#ffffff'}`" class="text-4xl font-black text-white drop-shadow-[0_0_30px_rgba(0,0,0,0.8)]" style="text-shadow: 0 0 40px rgba(0,0,0,0.9), 0 4px 20px rgba(0,0,0,0.8);" v-html="q3tohtml(user?.name ?? profile.name)"></div>
                            <!-- LIVE Badge -->
                            <a v-if="user?.is_live && user?.twitch_name" :href="`https://twitch.tv/${user.twitch_name}`" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg backdrop-blur-lg bg-red-600/90 border-2 border-red-400 hover:bg-red-500/90 hover:border-red-300 transition-all hover:scale-105 shadow-xl animate-pulse">
                                <div class="w-2 h-2 rounded-full bg-white animate-ping absolute"></div>
                                <div class="w-2 h-2 rounded-full bg-white"></div>
                                <span class="text-sm font-black text-white uppercase tracking-wider">LIVE</span>
                            </a>
                        </div>

                        <!-- Clan + Stats Row -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- Clan Badge -->
                            <Link v-if="user?.clan" :href="route('clans.show', user.clan.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg backdrop-blur-lg bg-blue-600/20 border border-blue-400/40 hover:border-blue-300/60 hover:bg-blue-600/30 transition-all hover:scale-105 shadow-xl group">
                                <div class="w-2 h-2 rounded-full bg-blue-400 animate-pulse shadow-lg shadow-blue-500/50"></div>
                                <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">Clan</span>
                                <span class="text-sm font-black text-white group-hover:text-blue-100 transition" v-html="q3tohtml(user.clan.name)"></span>
                            </Link>

                            <!-- CPM WR Count -->
                            <div class="px-3 py-1.5 rounded-lg backdrop-blur-lg bg-green-500/15 border border-green-400/30 shadow-xl">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <div class="text-xs text-green-300 font-semibold uppercase tracking-wider leading-none">CPM</div>
                                        <div class="text-lg font-black text-green-400 leading-tight">{{ cpm_world_records }}</div>
                                    </div>
                                    <svg class="w-4 h-4 text-green-400" viewBox="0 0 20 20" fill="currentColor"><use xlink:href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                            </div>

                            <!-- VQ3 WR Count -->
                            <div class="px-3 py-1.5 rounded-lg backdrop-blur-lg bg-blue-500/15 border border-blue-400/30 shadow-xl">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <div class="text-xs text-blue-300 font-semibold uppercase tracking-wider leading-none">VQ3</div>
                                        <div class="text-lg font-black text-blue-400 leading-tight">{{ vq3_world_records }}</div>
                                    </div>
                                    <svg class="w-4 h-4 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><use xlink:href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Social Links -->
                        <div class="flex items-center gap-2">
                            <!-- Twitch -->
                            <a v-if="user?.twitch_name" :href="`https://www.twitch.tv/` + user?.twitch_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg backdrop-blur-lg bg-purple-600/20 border border-purple-400/40 hover:border-purple-300/60 hover:bg-purple-600/30 transition-all hover:scale-110 shadow-xl flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-purple-300 transition group-hover:text-purple-200" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                    <g fill="currentColor">
                                        <path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" transform="translate(-85 -7239)"/>
                                    </g>
                                </svg>
                                <span class="text-xs font-bold text-purple-200 transition group-hover:text-white">TWITCH</span>
                            </a>

                            <!-- Discord -->
                            <div @click="copy(user?.discord_name)" v-if="user?.discord_name" class="group cursor-pointer relative px-3 py-1.5 rounded-lg backdrop-blur-lg bg-indigo-600/20 border border-indigo-400/40 hover:border-indigo-300/60 hover:bg-indigo-600/30 transition-all hover:scale-110 shadow-xl flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-300 transition group-hover:text-indigo-200" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z"/>
                                </svg>
                                <span class="text-xs font-bold text-indigo-200 transition group-hover:text-white" v-if="!copyState">#{{user?.discord_name}}</span>
                                <span class="text-xs font-bold text-white" v-else>COPIED!</span>
                            </div>

                            <!-- Twitter -->
                            <a v-if="user?.twitter_name" :href="`https://www.x.com/` + user?.twitter_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg backdrop-blur-lg bg-sky-600/20 border border-sky-400/40 hover:border-sky-300/60 hover:bg-sky-600/30 transition-all hover:scale-110 shadow-xl flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-300 transition group-hover:text-sky-200" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <g fill="currentColor">
                                        <path d="M10.29,7377 C17.837,7377 21.965,7370.84365 21.965,7365.50546 C21.965,7365.33021 21.965,7365.15595 21.953,7364.98267 C22.756,7364.41163 23.449,7363.70276 24,7362.8915 C23.252,7363.21837 22.457,7363.433 21.644,7363.52751 C22.5,7363.02244 23.141,7362.2289 23.448,7361.2926 C22.642,7361.76321 21.761,7362.095 20.842,7362.27321 C19.288,7360.64674 16.689,7360.56798 15.036,7362.09796 C13.971,7363.08447 13.518,7364.55538 13.849,7365.95835 C10.55,7365.79492 7.476,7364.261 5.392,7361.73762 C4.303,7363.58363 4.86,7365.94457 6.663,7367.12996 C6.01,7367.11125 5.371,7366.93797 4.8,7366.62489 L4.8,7366.67608 C4.801,7368.5989 6.178,7370.2549 8.092,7370.63591 C7.488,7370.79836 6.854,7370.82199 6.24,7370.70483 C6.777,7372.35099 8.318,7373.47829 10.073,7373.51078 C8.62,7374.63513 6.825,7375.24554 4.977,7375.24358 C4.651,7375.24259 4.325,7375.22388 4,7375.18549 C5.877,7376.37088 8.06,7377 10.29,7376.99705" transform="translate(-4 -7361)"/>
                                    </g>
                                </svg>
                                <span class="text-xs font-bold text-sky-200 transition group-hover:text-white">TWITTER</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EPIC REDESIGN - Main Content -->
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6">
            <!-- Stats Grid - Clean Text Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Performance Stats -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Performance</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Total Records</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Total number of records you have set across all maps
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_records || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_records || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">World Records</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Number of #1 ranked records you currently hold
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-yellow-400">{{ cpm_world_records }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ vq3_world_records }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Top 3 Positions</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records ranked between 1st and 3rd place
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_top3 || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_top3 || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Top 10 Positions</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records ranked between 1st and 10th place
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_top10 || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_top10 || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Average Rank</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Your average ranking position across all records (lower is better)
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_avg_rank || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_avg_rank || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Dominance</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Percentage of your records that are in top 10 positions
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_dominance || 0 }}%</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_dominance || 0 }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Features -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Map Features</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Unique Maps</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Number of different maps you have set records on
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_unique_maps || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_unique_maps || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Slick</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records on maps with slick (low friction) surfaces
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_slick || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_slick || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Jumppad</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records on maps featuring jump pads
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_jumppad || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_jumppad || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Teleporter</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records on maps featuring teleporters
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile.cpm_teleporter || 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile.vq3_teleporter || 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity & Misc -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Activity</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Best Streak</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Your longest consecutive days of setting records
                            </div>
                            <span class="text-sm font-bold text-white">{{ profile.longest_streak || 0 }} days</span>
                        </div>
                        <div v-if="profile.most_active_month" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Most Active</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                The month when you set the most records
                            </div>
                            <span class="text-sm font-bold text-white">{{ profile.most_active_month.month }}</span>
                        </div>
                        <div v-if="profile.first_record_date" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">First Record</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                When you set your very first record
                            </div>
                            <span class="text-sm font-bold text-white">{{ new Date(profile.first_record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' }) }}</span>
                        </div>
                        <div v-if="profile.weapon_specialist" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">Best Weapon</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                The weapon category where you have the most world records
                            </div>
                            <span class="text-sm font-bold text-white capitalize">{{ profile.weapon_specialist }}</span>
                        </div>
                    </div>
                </div>

                <!-- Record Types -->
                <div v-if="stats.filter(s => s.value !== 'world_records').some(s => (profile?.hasOwnProperty('cpm_' + s.value) ? profile['cpm_' + s.value] : 0) > 0 || (profile?.hasOwnProperty('vq3_' + s.value) ? profile['vq3_' + s.value] : 0) > 0)" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">Record Types</h3>
                    <div class="space-y-2">
                        <div v-for="stat in stats.filter(s => s.value !== 'world_records' && ((profile?.hasOwnProperty('cpm_' + s.value) ? profile['cpm_' + s.value] : 0) > 0 || (profile?.hasOwnProperty('vq3_' + s.value) ? profile['vq3_' + s.value] : 0) > 0))" :key="stat.value" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ stat.label.replace(' Records', '') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records set on {{ stat.label.toLowerCase() }} maps or with specific game modes
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-white">{{ profile?.hasOwnProperty('cpm_' + stat.value) ? profile['cpm_' + stat.value] : 0 }}</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-medium text-gray-400">{{ profile?.hasOwnProperty('vq3_' + stat.value) ? profile['vq3_' + stat.value] : 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Competitors & Rivals Section -->
            <div v-if="cpm_competitors || cpm_rivals" class="mb-6">
                <!-- Section Headers -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                            Similar Skill Level
                        </h3>
                        <p class="text-xs text-gray-400">Players with similar overall skill score. Score = (WRs × 100) + (Top3 × 50) + (Top10 × 20) + (1000/avg rank) + (records × 2). The number shows their score difference from yours.</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                            </svg>
                            Your Rivals
                        </h3>
                        <p class="text-xs text-gray-400">Players you compete with head-to-head on specific maps. The number shows how many times you've beaten each other across all maps.</p>
                    </div>
                </div>

                <!-- All 4 columns side by side -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Better Players -->
                    <div v-if="cpm_competitors?.better?.length > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                        <h4 class="text-xs font-bold text-green-400 uppercase tracking-wide mb-2">Better Players</h4>
                        <div class="space-y-2">
                            <button v-for="player in cpm_competitors.better" :key="player.id"
                                  @click="loadBeatableRecords(player.id, player.plain_name)"
                                  class="w-full flex items-center gap-3 p-2 rounded-lg bg-white/5 hover:bg-green-500/20 border border-white/10 hover:border-green-500/30 transition-all group cursor-pointer">
                                <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                     class="w-8 h-8 rounded-full"
                                     :alt="player.plain_name" />
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white group-hover:text-green-300 transition-colors" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                    <div class="text-xs text-gray-400">{{ player.wrs }} WRs • {{ player.total_records }} records</div>
                                </div>
                                <div class="text-xs font-bold text-green-400">+{{ Math.round(player.score - cpm_competitors.my_score) }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- Worse Players -->
                    <div v-if="cpm_competitors?.worse?.length > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                        <h4 class="text-xs font-bold text-orange-400 uppercase tracking-wide mb-2">Target Practice</h4>
                        <div class="space-y-2">
                            <button v-for="player in cpm_competitors.worse" :key="player.id"
                                  @click="loadBeatableRecords(player.id, player.plain_name)"
                                  class="w-full flex items-center gap-3 p-2 rounded-lg bg-white/5 hover:bg-orange-500/20 border border-white/10 hover:border-orange-500/30 transition-all group cursor-pointer">
                                <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                     class="w-8 h-8 rounded-full"
                                     :alt="player.plain_name" />
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white group-hover:text-orange-300 transition-colors" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                    <div class="text-xs text-gray-400">{{ player.wrs }} WRs • {{ player.total_records }} records</div>
                                </div>
                                <div class="text-xs font-bold text-orange-400">-{{ Math.round(cpm_competitors.my_score - player.score) }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- Players You Beat Most -->
                    <div v-if="cpm_rivals?.beaten?.length > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                        <h4 class="text-xs font-bold text-blue-400 uppercase tracking-wide mb-2">You Dominate</h4>
                        <div class="space-y-2">
                            <button v-for="rival in cpm_rivals.beaten" :key="rival.id"
                                  @click="loadBeatableRecords(rival.id, rival.plain_name)"
                                  class="w-full flex items-center gap-3 p-2 rounded-lg bg-white/5 hover:bg-blue-500/20 border border-white/10 hover:border-blue-500/30 transition-all group cursor-pointer">
                                <img :src="rival.user?.profile_photo_path ? `/storage/${rival.user.profile_photo_path}` : '/images/null.jpg'"
                                     class="w-8 h-8 rounded-full"
                                     :alt="rival.plain_name" />
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white group-hover:text-blue-300 transition-colors" v-html="q3tohtml(rival.name || rival.plain_name)"></div>
                                    <div class="text-xs text-gray-400">Beaten on {{ rival.maps_beaten }} maps</div>
                                </div>
                                <div class="text-xs font-bold text-blue-400">{{ rival.times_beaten }}×</div>
                            </button>
                        </div>
                    </div>

                    <!-- Players Who Beat You Most -->
                    <div v-if="cpm_rivals?.beaten_by?.length > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5">
                        <h4 class="text-xs font-bold text-red-400 uppercase tracking-wide mb-2">They Dominate You</h4>
                        <div class="space-y-2">
                            <button v-for="rival in cpm_rivals.beaten_by" :key="rival.id"
                                  @click="loadBeatableRecords(rival.id, rival.plain_name)"
                                  class="w-full flex items-center gap-3 p-2 rounded-lg bg-white/5 hover:bg-red-500/20 border border-white/10 hover:border-red-500/30 transition-all group cursor-pointer">
                                <img :src="rival.user?.profile_photo_path ? `/storage/${rival.user.profile_photo_path}` : '/images/null.jpg'"
                                     class="w-8 h-8 rounded-full"
                                     :alt="rival.plain_name" />
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white group-hover:text-red-300 transition-colors" v-html="q3tohtml(rival.name || rival.plain_name)"></div>
                                    <div class="text-xs text-gray-400">Beat you on {{ rival.maps_beaten }} maps</div>
                                </div>
                                <div class="text-xs font-bold text-red-400">{{ rival.times_beaten }}×</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Completionist List -->
            <div v-if="unplayed_maps && unplayed_maps.total > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5 mb-6">
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                            </svg>
                            Map Completionist
                        </h2>
                        <div class="text-right">
                            <div class="text-3xl font-black text-white">
                                {{ completionPercentage }}%
                            </div>
                            <div class="text-xs text-gray-400">{{ playedMapsCount }} / {{ total_maps }} maps</div>
                        </div>
                    </div>

                    <!-- Epic Progress Bar -->
                    <div class="relative">
                        <!-- Background track -->
                        <div class="h-8 bg-gray-800 rounded-full overflow-hidden border border-gray-600 shadow-inner">
                            <!-- Animated progress fill -->
                            <div
                                class="h-full relative overflow-hidden transition-all duration-1000 ease-out bg-blue-600"
                                :style="`width: ${completionPercentage}%`">

                                <!-- Animated moving stripes -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-blue-500/40 to-transparent bg-[length:200%_100%] animate-[shimmer_1.5s_ease-in-out_infinite]"></div>

                                <!-- Pulse effect -->
                                <div class="absolute inset-0 bg-white/10 animate-pulse"></div>

                                <!-- Sliding highlight -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent bg-[length:50%_100%] animate-[shimmer_2s_linear_infinite]"></div>
                            </div>

                            <!-- Particles/dots animation -->
                            <div class="absolute inset-y-0 left-0 right-0 pointer-events-none">
                                <div class="absolute top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-400 rounded-full blur-sm animate-[shimmer_3s_linear_infinite] opacity-50"></div>
                                <div class="absolute top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-blue-300 rounded-full blur-sm animate-[shimmer_2.5s_linear_infinite] opacity-40" style="animation-delay: 0.5s"></div>
                            </div>
                        </div>

                        <!-- Percentage label overlay -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xs font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                {{ unplayed_maps.total }} maps remaining
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Maps Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                    <Link v-for="map in unplayed_maps.data" :key="map.name"
                          :href="route('maps.map', map.name)"
                          class="group relative backdrop-blur-xl bg-white/5 rounded-lg p-3 shadow-lg border border-white/10 hover:border-yellow-500/50 transition-all hover:bg-yellow-500/10">
                        <div class="relative overflow-hidden rounded-md mb-2">
                            <img :src="`/storage/${map.thumbnail}`"
                                 onerror="this.src='/images/unknown.jpg'"
                                 class="w-full h-24 object-cover group-hover:scale-110 transition-transform duration-300"
                                 :alt="map.name" />
                        </div>
                        <div class="text-sm font-bold text-white group-hover:text-yellow-400 transition-colors truncate">{{ map.name }}</div>
                        <div class="text-xs text-gray-500 truncate">by {{ map.author || 'Unknown' }}</div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="unplayed_maps.last_page > 1" class="flex items-center justify-center gap-2">
                    <!-- Previous Button -->
                    <Link v-if="unplayed_maps.current_page > 1"
                          :href="route('profile.index', {id: user.id, unplayed_page: unplayed_maps.current_page - 1})"
                          preserve-scroll
                          class="px-3 py-1 rounded-lg text-sm font-medium transition-all bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10">
                        ‹ Prev
                    </Link>

                    <!-- Page Numbers -->
                    <template v-for="page in unplayed_maps.last_page" :key="page">
                        <Link v-if="page === 1 || page === unplayed_maps.last_page || (page >= unplayed_maps.current_page - 2 && page <= unplayed_maps.current_page + 2)"
                              :href="route('profile.index', {id: user.id, unplayed_page: page})"
                              preserve-scroll
                              class="px-3 py-1 rounded-lg text-sm font-medium transition-all"
                              :class="unplayed_maps.current_page === page
                                ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50'
                                : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10'">
                            {{ page }}
                        </Link>
                        <span v-else-if="page === unplayed_maps.current_page - 3 || page === unplayed_maps.current_page + 3"
                              class="px-2 text-gray-600">...</span>
                    </template>

                    <!-- Next Button -->
                    <Link v-if="unplayed_maps.current_page < unplayed_maps.last_page"
                          :href="route('profile.index', {id: user.id, unplayed_page: unplayed_maps.current_page + 1})"
                          preserve-scroll
                          class="px-3 py-1 rounded-lg text-sm font-medium transition-all bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10">
                        Next ›
                    </Link>
                </div>
            </div>

            <!-- Direct Competitor Comparison -->
            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5 mb-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    Direct Competitor Comparison
                </h2>

                <!-- Player Search -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-400 mb-2">Select player to compare against:</label>
                    <input
                        v-model="competitorSearch"
                        @input="searchCompetitors"
                        @focus="showCompetitorDropdown = true"
                        type="text"
                        placeholder="Search for a player..."
                        class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500/50 transition-colors"
                    />

                    <!-- Search Results Dropdown -->
                    <div v-if="showCompetitorDropdown && competitorSearchResults.length > 0" class="mt-2 bg-black/90 border border-white/20 rounded-lg max-h-60 overflow-y-auto">
                        <button
                            v-for="player in competitorSearchResults"
                            :key="player.id"
                            @click="selectCompetitor(player)"
                            class="w-full flex items-center gap-3 p-3 hover:bg-white/10 transition-colors text-left border-b border-white/5 last:border-b-0"
                        >
                            <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                 class="w-8 h-8 rounded-full"
                                 :alt="player.plain_name" />
                            <div class="flex-1">
                                <div class="text-sm font-bold text-white" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                <div class="text-xs text-gray-400">{{ player.country }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Comparison Results -->
                <div v-if="selectedCompetitor" class="space-y-6">
                    <!-- Selected Competitor Info -->
                    <div class="flex items-center gap-4 p-4 bg-white/5 rounded-lg border border-white/10">
                        <img :src="selectedCompetitor.user?.profile_photo_path ? `/storage/${selectedCompetitor.user.profile_photo_path}` : '/images/null.jpg'"
                             class="w-12 h-12 rounded-full"
                             :alt="selectedCompetitor.plain_name" />
                        <div class="flex-1">
                            <div class="text-lg font-bold text-white" v-html="q3tohtml(selectedCompetitor.name || selectedCompetitor.plain_name)"></div>
                            <div class="text-sm text-gray-400">Comparing records...</div>
                        </div>
                        <button @click="clearCompetitor" class="px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg text-sm font-bold transition-colors">
                            Clear
                        </button>
                    </div>

                    <!-- Explanation -->
                    <div class="text-sm text-gray-400 bg-white/5 border border-white/10 rounded-lg p-4">
                        <p>Maps are sorted by priority: <span class="text-red-400 font-bold">Behind</span> (easiest to beat), <span class="text-gray-400 font-bold">No Record</span> (opportunities), and <span class="text-green-400 font-bold">Ahead</span> (already winning).</p>
                    </div>

                    <!-- VQ3 and CPM Comparison Tables Side by Side -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- VQ3 Comparison Table -->
                        <div>
                            <h3 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                                <img src="/images/modes/vq3-icon.svg" class="w-6 h-6" alt="VQ3" />
                                🎯 Top {{ vq3Comparison.length }} Easiest VQ3 Maps to Beat Your Competitor!
                            </h3>
                            <div v-if="loadingComparison" class="text-center py-10">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                            </div>
                            <div v-else-if="vq3ComparisonPaginated.length > 0" class="space-y-4">
                                <div class="bg-white/5 rounded-lg border border-white/10 overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-white/5 border-b border-white/10">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Map</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Your Time</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Their Time</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Difference</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/5">
                                                <tr v-for="record in vq3ComparisonPaginated" :key="record.mapname" class="hover:bg-white/5 transition-colors">
                                                    <td class="px-4 py-3 text-sm font-medium text-white">{{ record.mapname }}</td>
                                                    <td class="px-4 py-3 text-sm text-white">{{ record.my_time ? formatTime(record.my_time) : '-' }}</td>
                                                    <td class="px-4 py-3 text-sm text-white">{{ formatTime(record.rival_time) }}</td>
                                                    <td class="px-4 py-3 text-sm" :class="record.status === 'ahead' ? 'text-green-400' : record.status === 'behind' ? 'text-red-400' : 'text-gray-400'">
                                                        {{ record.time_diff ? (record.status === 'ahead' ? '-' : '+') + formatTime(Math.abs(record.time_diff)) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span v-if="record.status === 'ahead'" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">Ahead</span>
                                                        <span v-else-if="record.status === 'behind'" class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs font-bold">Behind</span>
                                                        <span v-else class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs font-bold">No Record</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- VQ3 Pagination -->
                                <div v-if="vq3TotalPages > 1" class="flex items-center justify-center gap-2">
                                    <button @click="vq3Page = Math.max(1, vq3Page - 1)" :disabled="vq3Page === 1" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                        Previous
                                    </button>
                                    <span class="text-sm text-gray-400">Page {{ vq3Page }} of {{ vq3TotalPages }}</span>
                                    <button @click="vq3Page = Math.min(vq3TotalPages, vq3Page + 1)" :disabled="vq3Page === vq3TotalPages" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                        Next
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-center py-10 text-gray-500">
                                No VQ3 comparison data available
                            </div>
                        </div>

                        <!-- CPM Comparison Table -->
                        <div>
                            <h3 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                                <img src="/images/modes/cpm-icon.svg" class="w-6 h-6" alt="CPM" />
                                🎯 Top {{ cpmComparison.length }} Easiest CPM Maps to Beat Your Competitor!
                            </h3>
                        <div v-if="loadingComparison" class="text-center py-10">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                        </div>
                        <div v-else-if="cpmComparisonPaginated.length > 0" class="space-y-4">
                            <div class="bg-white/5 rounded-lg border border-white/10 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-white/5 border-b border-white/10">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Map</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Your Time</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Their Time</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Difference</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            <tr v-for="record in cpmComparisonPaginated" :key="record.mapname" class="hover:bg-white/5 transition-colors">
                                                <td class="px-4 py-3 text-sm font-medium text-white">{{ record.mapname }}</td>
                                                <td class="px-4 py-3 text-sm text-white">{{ record.my_time ? formatTime(record.my_time) : '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-white">{{ formatTime(record.rival_time) }}</td>
                                                <td class="px-4 py-3 text-sm" :class="record.status === 'ahead' ? 'text-green-400' : record.status === 'behind' ? 'text-red-400' : 'text-gray-400'">
                                                    {{ record.time_diff ? (record.status === 'ahead' ? '-' : '+') + formatTime(Math.abs(record.time_diff)) : '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span v-if="record.status === 'ahead'" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">Ahead</span>
                                                    <span v-else-if="record.status === 'behind'" class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs font-bold">Behind</span>
                                                    <span v-else class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs font-bold">No Record</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- CPM Pagination -->
                            <div v-if="cpmTotalPages > 1" class="flex items-center justify-center gap-2">
                                <button @click="cpmPage = Math.max(1, cpmPage - 1)" :disabled="cpmPage === 1" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                    Previous
                                </button>
                                <span class="text-sm text-gray-400">Page {{ cpmPage }} of {{ cpmTotalPages }}</span>
                                <button @click="cpmPage = Math.min(cpmTotalPages, cpmPage + 1)" :disabled="cpmPage === cpmTotalPages" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                    Next
                                </button>
                            </div>
                        </div>
                            <div v-else class="text-center py-10 text-gray-500">
                                No CPM comparison data available
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-5">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-700 fill-current opacity-50" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-gray-500">Search for a player to see head-to-head comparison</div>
                </div>
            </div>

            <!-- Records Container with Sidebar Tabs -->
            <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                <!-- Sidebar Tabs -->
                <div class="lg:col-span-2 flex">
                    <div class="backdrop-blur-xl bg-black/40 rounded-xl p-3 shadow-2xl border border-white/5 w-full flex flex-col">
                        <!-- Gamemode Filter (MOVED TO TOP) -->
                        <div class="mb-4">
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 px-1">Mode</h3>
                            <div class="space-y-2">
                                <!-- All Modes -->
                                <button @click="sortByMode('all')" :class="mode === 'all' ? 'bg-gradient-to-r from-white/30 to-white/20 text-white shadow-lg' : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'" class="w-full px-4 py-2.5 rounded-lg transition-all text-sm font-bold">
                                    ALL
                                </button>

                                <!-- Run Mode -->
                                <button @click="sortByMode('run')" :class="mode === 'run' ? 'bg-gradient-to-r from-green-600/80 to-green-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-green-600/20 text-gray-400 hover:text-white border border-green-500/20'" class="w-full px-4 py-2.5 rounded-lg transition-all text-sm font-bold">
                                    RUN
                                </button>

                                <!-- All CTF -->
                                <button @click="sortByMode('ctf')" :class="mode === 'ctf' ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="w-full px-4 py-2.5 rounded-lg transition-all text-sm font-bold">
                                    CTF
                                </button>

                                <!-- CTF 1-7 -->
                                <div class="grid grid-cols-7 gap-1">
                                    <button v-for="i in 7" :key="'ctf' + i" @click="sortByMode('ctf' + i)" :class="mode === 'ctf' + i ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="px-2 py-2 rounded transition-all text-xs font-bold">
                                        {{ i }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Buttons (MOVED BELOW MODES) -->
                        <div class="pt-4 border-t border-white/10">
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 px-1">Filters</h3>
                            <div class="space-y-2">
                                <button v-for="(data, option) in options" :key="option" @click="selectOption(option)"
                                     :class="selectedOption === option
                                        ? 'bg-white/10 text-white shadow-lg border border-white/20'
                                        : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'"
                                     class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all text-sm font-bold">
                                    <svg v-if="loading !== option" :class="selectedOption === option ? 'text-white' : data.color"
                                         class="w-6 h-6 fill-current stroke-current transition-transform">
                                        <use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                         class="w-6 h-6 animate-spin">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    <span class="text-left flex-1 leading-normal">{{ data.label }}</span>
                                </button>
                            </div>
                        </div>
                        <!-- Spacer to match table height -->
                        <div class="flex-1"></div>
                    </div>
                </div>

                <!-- Records Tables - Two Side by Side -->
                <div class="lg:col-span-8">
                    <div v-if="hasProfile && (vq3Records.total > 0 || cpmRecords.total > 0)" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- VQ3 Records -->
                        <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20 flex flex-col min-h-[800px]">
                            <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" />
                                    <h2 class="text-lg font-bold text-blue-400">VQ3 Records</h2>
                                </div>
                            </div>

                            <div v-if="vq3Records.total > 0" class="px-4 py-2 flex-1">
                                <Link v-for="record in vq3Records.data" :key="record.id" :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
                                    <!-- Background Map Thumbnail -->
                                    <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
                                        <img
                                            :src="`/storage/${record.map.thumbnail}`"
                                            class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-20 group-hover:opacity-100 transition-all duration-500"
                                            :alt="record.mapname"
                                            onerror="this.src='/images/unknown.jpg'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
                                    </div>

                                    <!-- Content -->
                                    <div class="relative flex items-center gap-2 sm:gap-3 w-full">
                                        <!-- Rank -->
                                        <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                                            <span v-if="record.rank === 1" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥇</span>
                                            <span v-else-if="record.rank === 2" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥈</span>
                                            <span v-else-if="record.rank === 3" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥉</span>
                                            <span v-else class="text-[10px] sm:text-xs font-bold tabular-nums text-gray-500 group-hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.rank }}</span>
                                        </div>

                                        <!-- Map Name -->
                                        <div class="flex-1">
                                            <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
                                            <div v-if="(type === 'recentlybeaten' || type === 'tiedranks') && (record.user_plain_name || record.mdd_plain_name)" class="text-[9px] text-gray-500 group-hover:text-gray-400 truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                                <span v-if="type === 'recentlybeaten'">by {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                <span v-else-if="type === 'tiedranks'">tied with {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                            </div>
                                        </div>

                                        <!-- Time -->
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
                                        </div>

                                        <!-- Date -->
                                        <div class="w-14 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[8px] sm:text-[10px] text-gray-500 group-hover:text-gray-300 font-mono transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]" :title="record.date_set">
                                                {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }}
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="text-center py-10 flex-1 flex items-center justify-center">
                                <div class="text-sm font-bold text-gray-600">No VQ3 Records</div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="vq3Records.total > vq3Records.per_page" class="border-t border-blue-500/20 bg-transparent p-4 mt-auto">
                                <Pagination :last_page="vq3Records.last_page" :current_page="vq3Records.current_page" :link="vq3Records.first_page_url" pageName="vq3_page" />
                            </div>
                        </div>

                        <!-- CPM Records -->
                        <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20 flex flex-col min-h-[800px]">
                            <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" />
                                    <h2 class="text-lg font-bold text-purple-400">CPM Records</h2>
                                </div>
                            </div>

                            <div v-if="cpmRecords.total > 0" class="px-4 py-2 flex-1">
                                <Link v-for="record in cpmRecords.data" :key="record.id" :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
                                    <!-- Background Map Thumbnail -->
                                    <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
                                        <img
                                            :src="`/storage/${record.map.thumbnail}`"
                                            class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-20 group-hover:opacity-100 transition-all duration-500"
                                            :alt="record.mapname"
                                            onerror="this.src='/images/unknown.jpg'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
                                    </div>

                                    <!-- Content -->
                                    <div class="relative flex items-center gap-2 sm:gap-3 w-full">
                                        <!-- Rank -->
                                        <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                                            <span v-if="record.rank === 1" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥇</span>
                                            <span v-else-if="record.rank === 2" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥈</span>
                                            <span v-else-if="record.rank === 3" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥉</span>
                                            <span v-else class="text-[10px] sm:text-xs font-bold tabular-nums text-gray-500 group-hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.rank }}</span>
                                        </div>

                                        <!-- Map Name -->
                                        <div class="flex-1">
                                            <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
                                            <div v-if="(type === 'recentlybeaten' || type === 'tiedranks') && (record.user_plain_name || record.mdd_plain_name)" class="text-[9px] text-gray-500 group-hover:text-gray-400 truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                                <span v-if="type === 'recentlybeaten'">by {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                <span v-else-if="type === 'tiedranks'">tied with {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                            </div>
                                        </div>

                                        <!-- Time -->
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
                                        </div>

                                        <!-- Date -->
                                        <div class="w-14 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[8px] sm:text-[10px] text-gray-500 group-hover:text-gray-300 font-mono transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]" :title="record.date_set">
                                                {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }}
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="text-center py-10 flex-1 flex items-center justify-center">
                                <div class="text-sm font-bold text-gray-600">No CPM Records</div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="cpmRecords.total > cpmRecords.per_page" class="border-t border-purple-500/20 bg-transparent p-4 mt-auto">
                                <Pagination :last_page="cpmRecords.last_page" :current_page="cpmRecords.current_page" :link="cpmRecords.first_page_url" pageName="cpm_page" />
                            </div>
                        </div>
                    </div>

                    <!-- No Records State -->
                    <div v-else class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">
                        <div class="text-center py-20">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-700 fill-current opacity-50" viewBox="0 0 20 20">
                                <use xlink:href="/images/svg/icons.svg#icon-trophy"></use>
                            </svg>
                            <div class="text-xl font-bold text-gray-600">No Records Yet</div>
                            <div class="text-sm text-gray-700 mt-2">This player hasn't set any records</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics Panel (only visible to admin neyoneit) -->
        <div v-if="load_times && $page.props.auth?.user?.username === 'neyoneit'" class="max-w-screen-2xl mx-auto px-4 mt-8 mb-8">
            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
                <h3 class="text-lg font-bold text-white mb-4">⚡ Performance Metrics (Backend Load Times)</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div v-for="(time, key) in load_times" :key="key" class="bg-white/5 rounded-lg p-3 border border-white/10">
                        <div class="text-xs text-gray-400 uppercase mb-1">{{ key.replace(/_/g, ' ') }}</div>
                        <div class="text-xl font-bold" :class="time > 1000 ? 'text-red-400' : time > 500 ? 'text-yellow-400' : 'text-green-400'">
                            {{ time }}ms
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    <p><span class="text-green-400">Green</span> = Fast (&lt;500ms) | <span class="text-yellow-400">Yellow</span> = Moderate (500-1000ms) | <span class="text-red-400">Red</span> = Slow (&gt;1000ms)</p>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>

<style scoped>
</style>