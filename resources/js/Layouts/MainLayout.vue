<script setup>
    import { ref, watch, nextTick } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
    import ApplicationMark from '@/Components/Laravel/ApplicationMark.vue';
    import Banner from '@/Components/Laravel/Banner.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import DropdownLink from '@/Components/Laravel/DropdownLink.vue';
    import NavLink from '@/Components/Laravel/NavLink.vue';
    import ResponsiveNavLink from '@/Components/Laravel/ResponsiveNavLink.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';

    import MapSearchItem from '@/Components/MapSearchItem.vue';
    import PlayerSearchItem from '@/Components/PlayerSearchItem.vue';
    import NotificationMenu from '@/Components/NotificationMenu.vue';
    import SystemNotificationMenu from '@/Components/SystemNotificationMenu.vue';

    import AlertBanner from '@/Components/Basic/AlertBanner.vue';

    import Footer from '@/Components/Footer.vue';

    defineProps({
        title: String,
    });

    const showingNavigationDropdown = ref(false);

    const search = ref('');

    const maps = ref([]);
    const players = ref([]);
    const demos = ref([]);
    const clans = ref([]);
    const bundles = ref([]);

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);
    const searchDropdownPosition = ref({ top: 0, right: 0 });

    const searchCategory = ref('maps');

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
            <nav class="backdrop-blur-xl bg-black/60 border-b border-white/5 sticky top-0 z-50 shadow-2xl">
                <div class="max-w-8xl mx-auto px-4 lg:px-8">
                    <div class="flex items-center justify-between h-14">
                        <!-- Left: Logo + Navigation -->
                        <div class="flex items-center gap-6">
                            <!-- Logo -->
                            <Link :href="route('home')" class="shrink-0 flex items-center">
                                <ApplicationMark class="block h-7 w-auto transition-transform hover:scale-105" />
                            </Link>

                            <!-- Progressive Navigation Links (hide from RIGHT) -->
                            <div class="flex items-center gap-1">
                                <NavLink href="/announcements" :active="route().current('announcements.*')" class="hidden md:block">
                                    News
                                </NavLink>
                                <NavLink :href="route('servers')" :active="route().current('servers')" class="hidden md:block">
                                    Servers
                                </NavLink>
                                <NavLink :href="route('ranking')" :active="route().current('ranking')" class="hidden lg:block">
                                    Ranking
                                </NavLink>
                                <NavLink :href="route('maps')" :active="route().current('maps')" class="hidden lg:block">
                                    Maps
                                </NavLink>
                                <NavLink href="/models" :active="route().current('models.*')" class="hidden xl:block">
                                    Models
                                </NavLink>

                                <!-- More Menu Dropdown -->
                                <Dropdown align="left" width="48" class="hidden lg:block">
                                    <template #trigger>
                                        <button class="px-3 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all flex items-center gap-1">
                                            More
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink href="/models" class="xl:hidden">
                                            Models
                                        </DropdownLink>
                                        <div class="border-t border-white/5 my-1 xl:hidden"></div>
                                        <DropdownLink :href="route('records')">
                                            Records
                                        </DropdownLink>
                                        <DropdownLink :href="route('demos.index')">
                                            Demos
                                        </DropdownLink>
                                        <DropdownLink :href="route('bundles')">
                                            Bundles
                                        </DropdownLink>
                                        <DropdownLink :href="route('clans.index')">
                                            Clans
                                        </DropdownLink>
                                        <DropdownLink :href="route('tournaments.index')">
                                            Tournaments
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Right: More Menu + Search + Notifications + Profile -->
                        <div class="flex items-center gap-2">
                            <!-- Mobile Menu Dropdown (shows all items on small screens) -->
                            <Dropdown align="right" width="48" class="lg:hidden">
                                <template #trigger>
                                    <button class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 transition-all">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <div class="px-4 py-2 text-xs text-gray-400 border-b border-white/5">
                                        Menu
                                    </div>
                                    <!-- Main items (hidden on smaller screens) -->
                                    <DropdownLink :href="route('maps')" class="lg:hidden">
                                        Maps
                                    </DropdownLink>
                                    <DropdownLink :href="route('ranking')" class="lg:hidden">
                                        Ranking
                                    </DropdownLink>
                                    <DropdownLink :href="route('servers')" class="md:hidden">
                                        Servers
                                    </DropdownLink>
                                    <DropdownLink href="/announcements" class="md:hidden">
                                        News
                                    </DropdownLink>
                                    <!-- Divider -->
                                    <div class="border-t border-white/5 my-1"></div>
                                    <!-- More items (always shown in mobile menu) -->
                                    <DropdownLink :href="route('records')">
                                        Records
                                    </DropdownLink>
                                    <DropdownLink :href="route('demos.index')">
                                        Demos
                                    </DropdownLink>
                                    <DropdownLink :href="route('bundles')">
                                        Bundles
                                    </DropdownLink>
                                    <DropdownLink :href="route('clans.index')">
                                        Clans
                                    </DropdownLink>
                                    <DropdownLink :href="route('tournaments.index')">
                                        Tournaments
                                    </DropdownLink>
                                    <div class="border-t border-white/5 my-1"></div>
                                    <DropdownLink href="/models">
                                        Models
                                    </DropdownLink>
                                </template>
                            </Dropdown>

                            <!-- Search Bar -->
                            <div ref="searchSection" class="relative">
                                <div class="relative">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <TextInput
                                        v-model="search"
                                        @focus="onSearchFocus"
                                        type="text"
                                        class="h-9 pl-9 pr-3 w-48 sm:w-56 md:w-64 lg:w-72 bg-white/5 border-white/10 text-sm placeholder:text-gray-500 focus:bg-white/10 focus:border-white/20 transition-all"
                                        placeholder="Search..."
                                        @input="performSearch"
                                    />
                                </div>

                                <!-- Backdrop -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed inset-0 z-[60] pointer-events-auto" @click="closeSearch"></div>
                                </Teleport>

                                <!-- Search Results Dropdown -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed rounded-xl shadow-2xl backdrop-blur-xl bg-gray-950 border border-white/10 flex overflow-hidden z-[70]"
                                         :style="{ top: searchDropdownPosition.top + 'px', right: searchDropdownPosition.right + 'px', width: '600px', maxWidth: '90vw', maxHeight: '600px' }"
                                         @click.stop>

                                    <!-- Left Sidebar - Filter Tabs -->
                                    <div class="flex flex-col border-r border-white/10 w-40 shrink-0">
                                        <button
                                            @click.stop="searchCategory = 'maps'"
                                            class="px-3 py-3 text-xs font-bold transition-all border-l-2 text-left whitespace-nowrap"
                                            :class="searchCategory == 'maps' ? 'border-green-500 text-green-400 bg-green-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Maps<span v-if="maps.data?.length > 0" class="ml-1.5 text-[10px] opacity-60">({{ maps.data.length }})</span>
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'players'"
                                            class="px-3 py-3 text-xs font-bold transition-all border-l-2 text-left whitespace-nowrap"
                                            :class="searchCategory == 'players' ? 'border-purple-500 text-purple-400 bg-purple-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Players<span v-if="players?.length > 0" class="ml-1.5 text-[10px] opacity-60">({{ players.length }})</span>
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'demos'"
                                            class="px-3 py-3 text-xs font-bold transition-all border-l-2 text-left whitespace-nowrap"
                                            :class="searchCategory == 'demos' ? 'border-orange-500 text-orange-400 bg-orange-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Demos<span v-if="demos?.length > 0" class="ml-1.5 text-[10px] opacity-60">({{ demos.length }})</span>
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'clans'"
                                            class="px-3 py-3 text-xs font-bold transition-all border-l-2 text-left whitespace-nowrap"
                                            :class="searchCategory == 'clans' ? 'border-cyan-500 text-cyan-400 bg-cyan-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Clans<span v-if="clans?.length > 0" class="ml-1.5 text-[10px] opacity-60">({{ clans.length }})</span>
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'bundles'"
                                            class="px-3 py-3 text-xs font-bold transition-all border-l-2 text-left whitespace-nowrap"
                                            :class="searchCategory == 'bundles' ? 'border-yellow-500 text-yellow-400 bg-yellow-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Bundles<span v-if="bundles?.length > 0" class="ml-1.5 text-[10px] opacity-60">({{ bundles.length }})</span>
                                        </button>
                                    </div>

                                    <!-- Right Side - Results -->
                                    <div class="flex-1 defrag-scrollbar p-3 overflow-y-auto">
                                        <div v-if="search.length == 0" class="text-center py-20 text-gray-500 text-sm">
                                            Start typing to search...
                                        </div>

                                        <div ref="resultsSection" v-else>
                                            <!-- Maps -->
                                            <div v-if="maps.data?.length > 0 && searchCategory == 'maps'" @click="closeSearch">
                                                <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                                            </div>

                                            <!-- Players -->
                                            <div v-if="players?.length > 0 && searchCategory == 'players'" @click="closeSearch">
                                                <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                                            </div>

                                            <!-- Demos -->
                                            <div v-if="demos?.length > 0 && searchCategory == 'demos'" @click="closeSearch">
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

                                            <!-- Clans -->
                                            <div v-if="clans?.length > 0 && searchCategory == 'clans'" @click="closeSearch">
                                                <Link v-for="clan in clans" :key="clan.id" :href="route('clans.show', clan.id)" class="group flex items-center gap-3 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-cyan-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-cyan-500/5">
                                                    <div class="shrink-0">
                                                        <div class="w-10 h-10 rounded-lg bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-base font-black text-white group-hover:text-cyan-400 transition-colors truncate">{{ clan.name }}</div>
                                                        <div class="text-xs text-gray-400 font-semibold mt-0.5">{{ clan.members_count }} members</div>
                                                    </div>
                                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-cyan-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Link>
                                            </div>

                                            <!-- Bundles -->
                                            <div v-if="bundles?.length > 0 && searchCategory == 'bundles'" @click="closeSearch">
                                                <div v-for="bundle in bundles" :key="bundle.id" class="group cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-yellow-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-yellow-500/5">
                                                    <div class="flex items-center gap-3">
                                                        <div class="shrink-0">
                                                            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 border border-yellow-500/30 flex items-center justify-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-base font-black text-white group-hover:text-yellow-400 transition-colors truncate">{{ bundle.name }}</div>
                                                            <div class="text-xs text-gray-400 font-semibold mt-0.5">{{ bundle.maps_count }} maps</div>
                                                        </div>
                                                        <svg class="w-5 h-5 text-gray-600 group-hover:text-yellow-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- No Results -->
                                            <div v-if="players?.length == 0 && maps.data?.length == 0 && demos?.length == 0 && clans?.length == 0 && bundles?.length == 0" class="text-center py-20">
                                                <p class="text-sm text-gray-400 mb-1">No results found</p>
                                                <p class="text-xs text-gray-600">Try different keywords</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </Teleport>
                            </div>

                            <!-- Notifications (Always visible) -->
                            <div v-if="$page.props.auth.user" class="flex items-center gap-1">
                                <NotificationMenu :ping="true" />
                                <SystemNotificationMenu :ping="true" />
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
