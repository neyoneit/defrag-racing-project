<script setup>
    import { ref, watch, computed } from 'vue';
    import { Head, router, Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { useClipboard } from '@/Composables/useClipboard';
    
    const { copy, copyState } = useClipboard();

    const props = defineProps({
        user: Object,
        records: Object,
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
        hasProfile: Boolean
    });

    const color = ref(props.user?.color ? props.user.color : '#ffffff');

    const options = ref({
        'latest': {
            label: 'Latest Records',
            icon: 'backintime',
            color: 'text-gray-400'
        },
        'recentlybeaten': {
            label: 'Recently Beaten',
            icon: 'retweet',
            color: 'text-gray-400'
        },
        'tiedranks': {
            label: 'Tied Ranks',
            icon: 'circle-equal',
            color: 'text-gray-400'
        },
        'activityhistory': {
            label: 'Activity History',
            icon: 'linegraph',
            color: 'text-gray-400'
        },
        'bestranks': {
            label: 'Best Ranks',
            icon: 'trophy',
            color: 'text-green-400'
        },
        'besttimes': {
            label: 'Best Times',
            icon: 'stopwatch',
            color: 'text-green-400'
        },
        'worstranks': {
            label: 'Worst Ranks',
            icon: 'trash',
            color: 'text-red-400'
        },
        'worsttimes': {
            label: 'Worst Times',
            icon: 'hourglass',
            color: 'text-red-400'
        },
    });

    const stats = [
        {
            label: 'World Records',
            value: 'world_records',
            icon: 'trophy',
            color: 'text-yellow-500',
            type: 'svg',
            wr: true
        },
        {
            label: 'Strafe Records',
            value: 'strafe_records',
            icon: 'strafe',
            color: 'text-orange-500',
            type: 'imgsvg'
        },
        {
            label: 'Rocket Records',
            value: 'rocket_records',
            icon: 'rl',
            type: 'item'
        },
        {
            label: 'Grenade Records',
            value: 'grenade_records',
            icon: 'gl',
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
        {
            label: 'Fastcaps Records',
            value: 'ctf_records',
            icon: 'flag',
            color: 'text-cyan-500',
            type: 'svg'
        },
        {
            label: 'Total Records',
            value: 'total_records',
            icon: 'infinity',
            color: 'text-gray-500',
            type: 'svg'
        },
    ];

    const selectedOption = ref(props.type || 'latest');
    const loading = ref('');

    const selectOption = (option) => {
        if (loading.value === option) {
            return;
        }

        router.reload({
            data: {
                type: option,
                page: 1
            },
            onStart: () => {
                loading.value = option;
            },
            onFinish: () => {
                loading.value = '';
            }
        })
    }

    watch(() => props.type, (newVal) => {
        if (options.value[newVal]) {
            selectedOption.value = newVal;
        } else {
            selectedOption.value = 'latest';
        }
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
            <!-- Stats Grid - COMPACT EPIC DESIGN -->
            <div class="grid grid-cols-4 md:grid-cols-8 gap-2 mb-6">
                <div v-for="stat in stats" :key="stat.value"
                     class="group relative overflow-hidden rounded-xl backdrop-blur-2xl bg-gradient-to-br from-black/60 via-black/40 to-black/30 border border-white/10 hover:border-white/30 p-3 transition-all hover:scale-110 hover:z-10 shadow-2xl">
                    <!-- Icon -->
                    <div class="flex justify-center mb-2">
                        <div class="relative">
                            <svg v-if="stat.type == 'svg'" :class="`fill-current w-8 h-8 transition-all group-hover:scale-125 ` + stat.color" viewBox="0 0 20 20">
                                <use :xlink:href="`/images/svg/icons.svg#icon-` + stat.icon"></use>
                            </svg>
                            <img v-if="stat.type == 'imgsvg'" class="w-8 h-8 transition-all group-hover:scale-125" src="/images/svg/strafe.svg">
                            <div v-if="stat.type == 'item'" :class="`sprite-items sprite-${stat.icon} w-7 h-7 transition-all group-hover:scale-125`"></div>
                            <!-- Glow ring -->
                            <div class="absolute inset-0 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-blue-500 blur-lg opacity-0 group-hover:opacity-50 transition-opacity -z-10"></div>
                        </div>
                    </div>

                    <!-- CPM/VQ3 Values -->
                    <div class="text-center space-y-0.5">
                        <div class="flex items-center justify-center gap-1">
                            <img src="/images/modes/cpm-icon.svg" class="w-2.5 h-2.5 opacity-70" alt="CPM" />
                            <span class="text-xs font-black text-green-400 tabular-nums">{{ stat.wr ? cpm_world_records : (profile?.hasOwnProperty('cpm_' + stat.value) ? profile['cpm_' + stat.value] : 0) }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-1">
                            <img src="/images/modes/vq3-icon.svg" class="w-2.5 h-2.5 opacity-70" alt="VQ3" />
                            <span class="text-xs font-black text-blue-400 tabular-nums">{{ stat.wr ? vq3_world_records : (profile ? profile['vq3_' + stat.value] : 0) }}</span>
                        </div>
                    </div>

                    <!-- Label -->
                    <div class="mt-2 text-[9px] font-bold text-gray-500 text-center uppercase tracking-wider leading-tight group-hover:text-white transition-colors">{{ stat.label.replace(' Records', '') }}</div>

                    <!-- Epic hover glow -->
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-600/0 via-pink-600/0 to-blue-600/0 group-hover:from-purple-600/20 group-hover:via-pink-600/10 group-hover:to-blue-600/20 transition-all rounded-xl"></div>
                </div>
            </div>

            <!-- Records Container with Sidebar Tabs -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Sidebar Tabs -->
                <div class="lg:col-span-1">
                    <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 sticky top-4">
                        <div class="space-y-2">
                            <button v-for="(data, option) in options" :key="option" @click="selectOption(option)"
                                 :class="selectedOption === option
                                    ? 'bg-gradient-to-r from-purple-600 via-pink-600 to-blue-600 text-white shadow-lg'
                                    : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'"
                                 class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all text-xs font-bold">
                                <svg v-if="loading !== option" :class="selectedOption === option ? 'text-white' : data.color"
                                     class="w-5 h-5 fill-current transition-transform" viewBox="0 0 20 20">
                                    <use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use>
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                     class="w-5 h-5 animate-spin">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span class="text-left flex-1">{{ data.label }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="lg:col-span-3">
                    <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">

                <!-- Records List - Inline Record Component (copied from RecordsView) -->
                <div v-if="hasProfile && records.total > 0" class="px-4 py-2">
                    <Link v-for="record in records.data" :key="record.id" :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
                        <!-- Background Map Thumbnail (always visible, blurred) - extends to edges -->
                        <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
                            <img
                                :src="`/storage/${record.map.thumbnail}`"
                                class="w-full h-full object-cover scale-110 blur-md group-hover:blur-none group-hover:scale-105 opacity-20 group-hover:opacity-100 transition-all duration-500"
                                :alt="record.mapname"
                                onerror="this.src='/images/unknown.jpg'"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
                        </div>

                        <!-- Content (relative to background) -->
                        <div class="relative flex items-center gap-2 sm:gap-3 w-full">
                            <!-- Rank -->
                            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                                <span v-if="record.rank === 1" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥇</span>
                                <span v-else-if="record.rank === 2" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥈</span>
                                <span v-else-if="record.rank === 3" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥉</span>
                                <span v-else class="text-[10px] sm:text-xs font-bold tabular-nums text-gray-500 group-hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.rank }}</span>
                            </div>

                            <!-- Map Name -->
                            <div class="w-28 sm:w-40 flex-shrink-0">
                                <div class="text-xs sm:text-sm font-bold text-blue-400 group-hover:text-blue-200 transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
                            </div>

                            <!-- Spacer for layout (player info removed) -->
                            <div class="flex-1"></div>

                            <!-- Time -->
                            <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                                <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
                            </div>

                            <!-- Physics Icon -->
                            <div class="w-5 sm:w-10 flex-shrink-0 text-center">
                                <img v-if="record.physics.includes('cpm')" src="/images/modes/cpm-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="CPM" />
                                <img v-else src="/images/modes/vq3-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="VQ3" />
                            </div>

                            <!-- Date -->
                            <div class="w-8 sm:w-16 flex-shrink-0 text-right">
                                <div class="text-[8px] sm:text-[10px] text-gray-500 group-hover:text-gray-300 font-mono transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]" :title="record.date_set">
                                    {{ new Date(record.date_set).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit' }) }}
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-else class="text-center py-20">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-700 fill-current opacity-50" viewBox="0 0 20 20">
                        <use xlink:href="/images/svg/icons.svg#icon-trophy"></use>
                    </svg>
                    <div class="text-xl font-bold text-gray-600">No Records Yet</div>
                    <div class="text-sm text-gray-700 mt-2">This player hasn't set any records</div>
                </div>

                        <!-- Pagination -->
                        <div v-if="hasProfile && records.total > records.per_page" class="border-t border-white/5 bg-transparent p-6">
                            <Pagination :last_page="records.last_page" :current_page="records.current_page" :link="records.first_page_url" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>

<style scoped>
</style>