<script setup>
    import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import ApplicationMark from '@/Components/Laravel/ApplicationMark.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import DropdownLink from '@/Components/Laravel/DropdownLink.vue';
    import NavLink from '@/Components/Laravel/NavLink.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    import MapSearchItem from '@/Components/MapSearchItem.vue';
    import PlayerSearchItem from '@/Components/PlayerSearchItem.vue';
    import NotificationMenu from '@/Components/NotificationMenu.vue';
    import SystemNotificationMenu from '@/Components/SystemNotificationMenu.vue';

    import AlertBanner from '@/Components/Basic/AlertBanner.vue';

    import Footer from '@/Components/Footer.vue';
    import DonationProgressBar from '@/Components/DonationProgressBar.vue';

    defineProps({
        title: String,
    });

    const page = usePage();

    const search = ref('');

    const maps = ref([]);
    const players = ref([]);
    const demos = ref([]);
    const clans = ref([]);
    const bundles = ref([]);
    const models = ref([]);

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);
    const searchDropdownPosition = ref({ top: 0, right: 0 });

    let timer;

    const debounce = () => {
        let searchingString = search.value;

        timer = setTimeout(() => {
            if (searchingString == search.value) {
                axios.post(route('search'), {
                    search: search.value
                }).then(response => {
                    maps.value = response.data?.maps
                    players.value = response.data?.players
                    demos.value = response.data?.demos || []
                    clans.value = response.data?.clans || []
                    bundles.value = response.data?.bundles || []
                    models.value = response.data?.models || []
                });
            }
        }, 250);
    }

    const logout = () => {
        router.post(route('logout'));
    };

    const onSearchFocus = () => {
        showResultsSection.value = true;
    }

    const closeSearch = () => {
        showResultsSection.value = false;
    }

    const performSearch = () => {
        if (search.value.length == 0) {
            maps.value = [];
            players.value = [];
            demos.value = [];
            clans.value = [];
            bundles.value = [];
            models.value = [];
            return;
        }

        debounce()
    }

    const updateSearchPosition = () => {
        if (searchSection.value && showResultsSection.value) {
            nextTick(() => {
                const rect = searchSection.value.getBoundingClientRect();
                searchDropdownPosition.value = {
                    top: rect.bottom + 8,
                    right: window.innerWidth - rect.right,
                };
            });
        }
    };

    watch(showResultsSection, (newVal) => {
        if (newVal) {
            updateSearchPosition();
            window.addEventListener('resize', updateSearchPosition);
            window.addEventListener('scroll', updateSearchPosition, true);
        } else {
            window.removeEventListener('resize', updateSearchPosition);
            window.removeEventListener('scroll', updateSearchPosition, true);
        }
    });

    const handleNavClick = (event) => {
        // Close search if clicking anywhere in nav except the search input itself
        if (showResultsSection.value && searchSection.value && !searchSection.value.contains(event.target)) {
            closeSearch();
        }
    };

    // Cycling notification preview for record notifications
    const notifications = computed(() => page.props.recordsNotifications || []);
    const currentNotificationIndex = ref(0);
    let notificationInterval;

    const currentNotification = computed(() => {
        if (notifications.value.length === 0) return null;
        return notifications.value[currentNotificationIndex.value];
    });

    const cycleNotification = () => {
        if (notifications.value.length > 0) {
            currentNotificationIndex.value = (currentNotificationIndex.value + 1) % notifications.value.length;
        }
    };

    // Cycling notification preview for system notifications
    const systemNotifications = computed(() => page.props.systemNotifications || []);
    const currentSystemNotificationIndex = ref(0);
    let systemNotificationInterval;

    const currentSystemNotification = computed(() => {
        if (systemNotifications.value.length === 0) return null;
        return systemNotifications.value[currentSystemNotificationIndex.value];
    });

    const cycleSystemNotification = () => {
        if (systemNotifications.value.length > 0) {
            currentSystemNotificationIndex.value = (currentSystemNotificationIndex.value + 1) % systemNotifications.value.length;
        }
    };

    onMounted(() => {
        if (notifications.value.length > 0) {
            notificationInterval = setInterval(cycleNotification, 5000);
        }
        if (systemNotifications.value.length > 0) {
            systemNotificationInterval = setInterval(cycleSystemNotification, 5000);
        }
    });

    onUnmounted(() => {
        if (notificationInterval) {
            clearInterval(notificationInterval);
        }
        if (systemNotificationInterval) {
            clearInterval(systemNotificationInterval);
        }
    });

    const timeDiff = (time, bettertime) => {
        return Math.abs(bettertime - time);
    };

    const formatTime = (milliseconds) => {
        const seconds = Math.floor(milliseconds / 1000);
        const ms = milliseconds % 1000;
        return `${seconds}.${String(ms).padStart(3, '0')}`;
    };
</script>

<template>
    <div>
        <Head :title="title">
            <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
            <link rel="manifest" href="/site.webmanifest">

            <!-- Google tag (gtag.js) -->
            <component :is="'script'" async src="https://www.googletagmanager.com/gtag/js?id=G-FMC55XYK1K">

            </component>
            <component :is="'script'">
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', 'G-FMC55XYK1K');
            </component>
        </Head>

        <div v-if="$page.props.danger">
            <AlertBanner styling="danger" :random="$page.props.dangerRandom">
                {{ $page.props.danger }}
            </AlertBanner>
        </div>

        <div v-if="$page.props.success">
            <AlertBanner styling="success" :random="$page.props.successRandom">
                {{ $page.props.success }}
            </AlertBanner>
        </div>

        <div class="min-h-screen bg-gray-900 bg-[url('/images/pattern.svg')]">
            <!-- Modern Compact Header -->
            <nav class="backdrop-blur-xl bg-black/60 border-b border-white/5 sticky top-0 z-50 shadow-2xl" @click="handleNavClick">
                <div class="max-w-8xl mx-auto px-4 lg:px-8">
                    <!-- First Row: Logo + Search + Profile -->
                    <div class="flex items-center justify-between gap-4 h-14 border-b border-white/5">
                        <!-- Left: Logo + Search -->
                        <div class="flex items-center gap-2 sm:gap-4 lg:gap-6 flex-1 min-w-0">
                            <Link :href="route('home')" class="shrink-0 flex items-center">
                                <ApplicationMark class="block h-6 sm:h-7 w-auto transition-transform hover:scale-105" />
                            </Link>

                            <!-- Search Bar (visible on all screens with SAME sizing) -->
                            <div ref="searchSection" class="relative flex-1 max-w-[180px] sm:max-w-xs md:max-w-xs lg:max-w-xs xl:max-w-xs 2xl:max-w-xs">
                                <div class="relative">
                                    <svg class="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 h-3.5 sm:h-4 w-3.5 sm:w-4 text-gray-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <TextInput
                                        v-model="search"
                                        @focus="onSearchFocus"
                                        type="text"
                                        class="h-8 sm:h-9 pl-8 sm:pl-9 pr-2 sm:pr-3 w-full bg-white/5 border-white/10 text-xs sm:text-sm placeholder:text-gray-500 focus:bg-white/10 focus:border-white/20 transition-all"
                                        placeholder="Search..."
                                        @input="performSearch"
                                    />
                                </div>

                                <!-- Backdrop - positioned BELOW the nav (z-40) -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed inset-0 z-40" @click="closeSearch"></div>
                                </Teleport>

                                <!-- Search Results Dropdown -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed rounded-xl shadow-2xl backdrop-blur-xl bg-gray-950 border border-white/10 overflow-hidden z-[60] left-1/2 -translate-x-1/2 w-full max-w-8xl mx-auto px-4 lg:px-8"
                                         :style="{ top: searchDropdownPosition.top + 'px', maxHeight: '70vh' }"
                                         @click.stop>

                                    <div v-if="search.length == 0" class="text-center py-20 text-gray-500 text-sm">
                                        Start typing to search...
                                    </div>

                                    <div v-else class="flex flex-col h-full">
                                        <!-- Grid Layout - All results side by side in 1 row, 4 columns -->
                                        <div ref="resultsSection" class="grid grid-cols-4 auto-rows-fr gap-4 p-6 overflow-y-auto defrag-scrollbar max-h-[70vh]">
                                            <!-- Maps Column -->
                                            <div class="flex flex-col">
                                                <div class="text-xs font-bold text-green-400 mb-3 pb-2 border-b border-green-500/30 flex items-center justify-between">
                                                    <span>MAPS</span>
                                                    <span v-if="maps.data?.length > 0" class="text-[10px] opacity-60">({{ maps.data.length }})</span>
                                                </div>
                                                <div v-if="maps.data?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No maps found</div>
                                            </div>

                                            <!-- Players Column -->
                                            <div class="flex flex-col">
                                                <div class="text-xs font-bold text-purple-400 mb-3 pb-2 border-b border-purple-500/30 flex items-center justify-between">
                                                    <span>PLAYERS</span>
                                                    <span v-if="players?.length > 0" class="text-[10px] opacity-60">({{ players.length }})</span>
                                                </div>
                                                <div v-if="players?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No players found</div>
                                            </div>

                                            <!-- Demos Column -->
                                            <div class="flex flex-col">
                                                <div class="text-xs font-bold text-orange-400 mb-3 pb-2 border-b border-orange-500/30 flex items-center justify-between">
                                                    <span>DEMOS</span>
                                                    <span v-if="demos?.length > 0" class="text-[10px] opacity-60">({{ demos.length }})</span>
                                                </div>
                                                <div v-if="demos?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <div v-for="demo in demos" :key="demo.id" class="group cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-orange-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-orange-500/5">
                                                        <div class="flex items-center gap-3">
                                                            <div class="shrink-0">
                                                                <div class="w-10 h-10 rounded-lg bg-orange-500/20 border border-orange-500/30 flex items-center justify-center">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-orange-400">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-base font-black text-white group-hover:text-orange-400 transition-colors truncate">{{ demo.filename }}</div>
                                                                <div class="text-xs text-gray-400 font-semibold mt-0.5">Uploaded {{ new Date(demo.created_at).toLocaleDateString() }}</div>
                                                            </div>
                                                            <svg class="w-5 h-5 text-gray-600 group-hover:text-orange-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No demos found</div>
                                            </div>

                                            <!-- Models Column -->
                                            <div class="flex flex-col">
                                                <div class="text-xs font-bold text-blue-400 mb-3 pb-2 border-b border-blue-500/30 flex items-center justify-between">
                                                    <span>MODELS</span>
                                                    <span v-if="models?.length > 0" class="text-[10px] opacity-60">({{ models.length }})</span>
                                                </div>
                                                <div v-if="models?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <Link v-for="model in models" :key="model.id" :href="`/models/${model.id}`" class="group flex items-center gap-2 cursor-pointer rounded-lg hover:bg-white/5 p-2 transition-all border border-transparent hover:border-blue-500/30">
                                                        <div class="shrink-0">
                                                            <div v-if="model.head_icon" class="w-8 h-8 rounded-lg overflow-hidden border border-blue-500/30">
                                                                <img :src="`/storage/${model.head_icon}`" :alt="model.name" class="w-full h-full object-cover" />
                                                            </div>
                                                            <div v-else class="w-8 h-8 rounded-lg bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-sm font-bold text-white group-hover:text-blue-400 transition-colors truncate">{{ model.name }}</div>
                                                            <div class="text-xs text-gray-400">{{ model.author || 'Unknown' }}</div>
                                                        </div>
                                                    </Link>
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No models found</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </Teleport>
                            </div>
                        </div>

                        <!-- Right: Notifications + Profile -->
                        <div class="flex items-center gap-3">
                            <!-- Record Notification Preview (visible on xl, hides to icon below) -->
                            <Link v-if="$page.props.auth.user && currentNotification" :href="route('notifications.index')" class="hidden xl:flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/20 rounded-lg transition-all hover:border-orange-500/40 cursor-pointer group">
                                <div class="shrink-0 w-2 h-2 bg-orange-400 rounded-full animate-pulse"></div>
                                <div class="flex items-center gap-1.5 min-w-0 text-xs">
                                    <span class="font-bold text-white truncate" v-html="q3tohtml(currentNotification.name || '')"></span>
                                    <span class="font-bold text-blue-400 truncate">{{ currentNotification.mapname }}</span>
                                    <span class="font-bold text-green-400">{{ formatTime(timeDiff(currentNotification.user_time || 0, currentNotification.time || 0)) }}</span>
                                </div>
                                <div class="shrink-0 flex items-center justify-center min-w-[20px] h-5 px-1.5 bg-orange-500 text-white text-xs font-bold rounded">
                                    {{ notifications.length }}
                                </div>
                            </Link>

                            <!-- System Notification Preview (visible on lg and above) -->
                            <Link v-if="$page.props.auth.user && currentSystemNotification" :href="route('notifications.system.index')" class="hidden lg:flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-lg transition-all hover:border-blue-500/40 cursor-pointer group">
                                <div class="shrink-0 w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                                <div class="flex items-center gap-1.5 min-w-0 text-xs">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-blue-400 shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                    </svg>
                                    <span class="text-gray-500">Announcement:</span>
                                    <span class="font-bold text-blue-400 truncate" v-html="q3tohtml(currentSystemNotification.headline || '')"></span>
                                </div>
                                <div class="shrink-0 flex items-center justify-center min-w-[20px] h-5 px-1.5 bg-blue-500 text-white text-xs font-bold rounded">
                                    {{ systemNotifications.length }}
                                </div>
                            </Link>

                            <!-- Notification Icons (show when previews are hidden) -->
                            <div v-if="$page.props.auth.user" class="flex items-center gap-1">
                                <!-- Record notification icon - shows below xl -->
                                <div class="xl:hidden">
                                    <NotificationMenu :ping="true" />
                                </div>
                                <!-- System notification icon - shows below lg -->
                                <div class="lg:hidden">
                                    <SystemNotificationMenu :ping="true" />
                                </div>
                            </div>

                            <!-- Profile Dropdown (Avatar always visible) -->
                            <div v-if="$page.props.auth.user">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/5 transition-all group">
                                            <!-- Avatar always visible -->
                                            <img class="h-7 w-7 rounded-full object-cover ring-2 ring-white/10 group-hover:ring-white/20 transition-all" :src="$page.props.auth.user.profile_photo_path ? '/storage/' + $page.props.auth.user.profile_photo_path : '/images/null.jpg'" :alt="$page.props.auth.user.name">
                                            <!-- Name and arrow hidden on small screens -->
                                            <div class="hidden md:block text-sm font-medium text-gray-300 group-hover:text-white transition-colors" v-html="q3tohtml($page.props.auth.user.name)"></div>
                                            <svg class="hidden md:block h-4 w-4 text-gray-500 group-hover:text-gray-300 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-2 text-xs text-gray-400 border-b border-white/5">
                                            Manage Account
                                        </div>
                                        <DropdownLink :href="route('profile.index', $page.props.auth.user.id)">
                                            My Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('profile.show')">
                                            Settings
                                        </DropdownLink>
                                        <div class="border-t border-white/5" />
                                        <div class="px-4 py-2 text-xs text-gray-400">
                                            My Maplists
                                        </div>
                                        <DropdownLink :href="route('maplists.index') + '?user=' + $page.props.auth.user.id">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Play Later
                                            </div>
                                        </DropdownLink>
                                        <DropdownLink :href="route('maplists.index') + '?user=' + $page.props.auth.user.id">
                                            All My Maplists
                                        </DropdownLink>
                                        <div class="border-t border-white/5" />
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Auth Buttons (Guest) -->
                            <div v-else class="flex items-center gap-2">
                                <Link :href="route('login')">
                                    <button class="px-3 py-1.5 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all">
                                        Login
                                    </button>
                                </Link>
                                <Link :href="route('register')">
                                    <button class="px-3 py-1.5 text-sm font-medium bg-white/10 hover:bg-white/15 text-white rounded-lg transition-all">
                                        Register
                                    </button>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Second Row: Navigation Links - ALWAYS VISIBLE -->
                    <div class="flex items-center gap-1 h-12 px-2">
                        <NavLink href="/announcements" :active="route().current('announcements.*')">
                            News
                        </NavLink>
                        <NavLink :href="route('records')" :active="route().current('records')">
                            Records
                        </NavLink>
                        <NavLink :href="route('maps')" :active="route().current('maps')">
                            Maps
                        </NavLink>
                        <NavLink :href="route('servers')" :active="route().current('servers')">
                            Servers
                        </NavLink>
                        <NavLink :href="route('tournaments.index')" :active="route().current('tournaments.*')">
                            Tournaments
                        </NavLink>
                        <NavLink :href="route('ranking')" :active="route().current('ranking')" class="hidden sm:inline-flex">
                            Ranking
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                        </NavLink>
                        <NavLink :href="route('maplists.index')" :active="route().current('maplists.*')" class="hidden md:inline-flex">
                            Maplists
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                        </NavLink>
                        <NavLink :href="route('bundles')" :active="route().current('bundles')" class="hidden md:inline-flex">
                            Bundles
                        </NavLink>
                        <NavLink :href="route('clans.index')" :active="route().current('clans.*')" class="hidden lg:inline-flex">
                            Clans
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                        </NavLink>
                        <NavLink href="/models" :active="route().current('models.*')" class="hidden lg:inline-flex">
                            Models
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                        </NavLink>
                        <NavLink :href="route('demos.index')" :active="route().current('demos.*')" class="hidden xl:inline-flex">
                            Demos
                        </NavLink>

                        <!-- Beta - VISIBLE AT XL -->
                        <NavLink href="/test-map-viewer.html?map=pornstar-cpmrun" :active="false" class="hidden xl:inline-flex">
                            Beta
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                        </NavLink>

                        <!-- More Dropdown (hidden at xl when all items are visible) -->
                        <div class="xl:hidden">
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all">
                                    <span>More</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <!-- Items hidden below xl -->
                                <div class="xl:hidden">
                                    <DropdownLink :href="route('demos.index')">
                                        Demos
                                    </DropdownLink>
                                    <DropdownLink href="/test-map-viewer.html?map=pornstar-cpmrun">
                                        Beta
                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                                    </DropdownLink>
                                </div>

                                <!-- Items hidden below lg -->
                                <div class="lg:hidden">
                                    <DropdownLink href="/models">
                                        Models
                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                                    </DropdownLink>
                                    <DropdownLink :href="route('clans.index')">
                                        Clans
                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                                    </DropdownLink>
                                </div>

                                <!-- Items hidden below md -->
                                <div class="md:hidden">
                                    <DropdownLink :href="route('bundles')">
                                        Bundles
                                    </DropdownLink>
                                    <DropdownLink :href="route('maplists.index')">
                                        Maplists
                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                                    </DropdownLink>
                                </div>

                                <!-- Items hidden below sm -->
                                <div class="sm:hidden">
                                    <DropdownLink :href="route('ranking')">
                                        Ranking
                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400 rounded">NEW</span>
                                    </DropdownLink>
                                </div>
                            </template>
                        </Dropdown>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="shadow">
                <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <!-- Donation Progress Bar -->
            <DonationProgressBar />

            <Footer />
        </div>
    </div>
</template>

<style>
    .main-background {
        background-color: #10151e;
        background-repeat: no-repeat;
        background-attachment: fixed;
        background-position: center;
        background-size: cover;
        justify-content: center;
    }

    .popper {
        padding: 0 !important;
    }

    .spinner_V8m1 {
        transform-origin: center;
        animation: spinner_zKoa 2s linear infinite;
    }

    .spinner_V8m1 circle {
        stroke-linecap: round;
        animation: spinner_YpZS 1.5s ease-in-out infinite;
    }

    @keyframes spinner_zKoa {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes spinner_YpZS {
        0% {
            stroke-dasharray: 0 150;
            stroke-dashoffset: 0;
        }
        47.5% {
            stroke-dasharray: 42 150;
            stroke-dashoffset: -16;
        }
        95%,
        100% {
            stroke-dasharray: 42 150;
            stroke-dashoffset: -59;
        }
    }
</style>
