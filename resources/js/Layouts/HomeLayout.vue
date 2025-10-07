<script setup>
    import { ref, onMounted, onUnmounted } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
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

    defineProps({
        title: String,
    });

    const search = ref('');

    const maps = ref([]);
    const players = ref([]);
    const demos = ref([]);
    const clans = ref([]);
    const bundles = ref([]);

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);

    const searchCategory = ref('maps');

    const screenWidth = ref(window.innerWidth);

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

    const onSearchBlur = (event) => {
        if (! showResultsSection.value) {
            return;
        }

        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

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

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }


    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>

<template>
    <div @click="onSearchBlur">
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

        <div class="min-h-screen bg-gray-900 bg-[url('/images/pattern.svg')]" style="z-index: 10;">

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
                                <NavLink :href="route('announcements')" :active="route().current('announcements')" class="hidden md:block">
                                    News
                                </NavLink>
                                <NavLink :href="route('servers')" :active="route().current('servers')" class="hidden md:block">
                                    Servers
                                </NavLink>
                                <NavLink :href="route('maps')" :active="route().current('maps')" class="hidden md:block">
                                    Maps
                                </NavLink>
                                <NavLink :href="route('ranking')" :active="route().current('ranking')" class="hidden lg:block">
                                    Ranking
                                </NavLink>
                                <NavLink :href="route('records')" :active="route().current('records')" class="hidden lg:block">
                                    Records
                                </NavLink>
                                <NavLink :href="route('demos.index')" :active="route().current('demos.*')" class="hidden xl:block">
                                    Demos
                                </NavLink>
                                <NavLink :href="route('bundles')" :active="route().current('bundles')" class="hidden xl:block">
                                    Bundles
                                </NavLink>
                                <NavLink :href="route('clans.index')" :active="route().current('clans.index')" class="hidden min-[1400px]:block">
                                    Clans
                                </NavLink>
                                <NavLink :href="route('tournaments.index')" :active="route().current('tournaments.index')" class="hidden min-[1280px]:block">
                                    Tournaments
                                </NavLink>
                            </div>
                        </div>

                        <!-- Right: More Menu + Search + Notifications + Profile -->
                        <div class="flex items-center gap-2">
                            <!-- More Menu Dropdown (shows hidden items) -->
                            <Dropdown align="right" width="48" class="min-[1400px]:hidden">
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
                                    <!-- Show items hidden on current breakpoint -->
                                    <DropdownLink :href="route('tournaments.index')" class="min-[1280px]:hidden">
                                        Tournaments
                                    </DropdownLink>
                                    <DropdownLink :href="route('clans.index')" class="min-[1400px]:hidden">
                                        Clans
                                    </DropdownLink>
                                    <DropdownLink :href="route('bundles')" class="xl:hidden">
                                        Bundles
                                    </DropdownLink>
                                    <DropdownLink :href="route('demos.index')" class="xl:hidden">
                                        Demos
                                    </DropdownLink>
                                    <DropdownLink :href="route('records')" class="lg:hidden">
                                        Records
                                    </DropdownLink>
                                    <DropdownLink :href="route('ranking')" class="lg:hidden">
                                        Ranking
                                    </DropdownLink>
                                    <DropdownLink :href="route('maps')" class="md:hidden">
                                        Maps
                                    </DropdownLink>
                                    <DropdownLink :href="route('servers')" class="md:hidden">
                                        Servers
                                    </DropdownLink>
                                    <DropdownLink :href="route('announcements')" class="md:hidden">
                                        News
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
                                <div v-if="showResultsSection" class="fixed inset-0 z-40" @click="closeSearch"></div>

                                <!-- Search Results Dropdown -->
                                <div v-if="showResultsSection" class="absolute top-full mt-2 right-0 rounded-xl shadow-2xl backdrop-blur-xl bg-gray-950 border border-white/10 flex overflow-hidden z-50" style="width: 600px; max-height: 600px;" @click.stop>

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
                                                <Link v-for="demo in demos" :key="demo.id" :href="route('demos.index')" class="group flex items-center gap-4 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-orange-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-orange-500/5">
                                                    <!-- Icon -->
                                                    <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-orange-500/20 ring-2 ring-white/10 group-hover:ring-orange-500/50 transition-all">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-orange-400">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />
                                                        </svg>
                                                    </div>

                                                    <!-- Demo Info -->
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-base font-black text-white group-hover:text-orange-400 transition-colors truncate mb-0.5">{{ demo.filename }}</div>
                                                        <div class="text-xs text-gray-400 font-semibold">Uploaded {{ new Date(demo.created_at).toLocaleDateString() }}</div>
                                                    </div>

                                                    <!-- Arrow -->
                                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-orange-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Link>
                                            </div>

                                            <!-- Clans -->
                                            <div v-if="clans?.length > 0 && searchCategory == 'clans'" @click="closeSearch">
                                                <Link v-for="clan in clans" :key="clan.id" :href="route('clans.show', clan.id)" class="group flex items-center gap-4 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-cyan-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-cyan-500/5">
                                                    <!-- Icon -->
                                                    <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-cyan-500/20 ring-2 ring-white/10 group-hover:ring-cyan-500/50 transition-all">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-cyan-400">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                                        </svg>
                                                    </div>

                                                    <!-- Clan Info -->
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-base font-black text-white group-hover:text-cyan-400 transition-colors truncate mb-0.5">{{ clan.name }}</div>
                                                        <div class="text-xs text-gray-400 font-semibold">{{ clan.members_count }} members</div>
                                                    </div>

                                                    <!-- Arrow -->
                                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-cyan-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Link>
                                            </div>

                                            <!-- Bundles -->
                                            <div v-if="bundles?.length > 0 && searchCategory == 'bundles'" @click="closeSearch">
                                                <Link v-for="bundle in bundles" :key="bundle.id" :href="route('bundles', bundle.id)" class="group flex items-center gap-4 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-yellow-500/30 backdrop-blur-sm hover:shadow-lg hover:shadow-yellow-500/5">
                                                    <!-- Icon -->
                                                    <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-yellow-500/20 ring-2 ring-white/10 group-hover:ring-yellow-500/50 transition-all">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-yellow-400">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                        </svg>
                                                    </div>

                                                    <!-- Bundle Info -->
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-base font-black text-white group-hover:text-yellow-400 transition-colors truncate mb-0.5">{{ bundle.name }}</div>
                                                        <div class="text-xs text-gray-400 font-semibold">{{ bundle.maps_count }} maps</div>
                                                    </div>

                                                    <!-- Arrow -->
                                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-yellow-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Link>
                                            </div>

                                            <!-- No Results -->
                                            <div v-if="players?.length == 0 && maps.data?.length == 0 && demos?.length == 0 && clans?.length == 0 && bundles?.length == 0" class="text-center py-20">
                                                <p class="text-sm text-gray-400 mb-1">No results found</p>
                                                <p class="text-xs text-gray-600">Try different keywords</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
        background-color: #111827;
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
