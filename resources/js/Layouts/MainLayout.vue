<script setup>
    import { ref } from 'vue';
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

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);

    const searchCategory = ref('all');

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

    const performSearch = () => {
        if (search.value.length == 0) {
            maps.value = [];
            players.value = [];
            return;
        }

        debounce()
    }
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
                                <NavLink href="/announcements" :active="route().current('announcements.*')" class="hidden sm:block">
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
                                    <DropdownLink href="/announcements" class="sm:hidden">
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
                                        class="h-9 pl-9 pr-3 w-40 sm:w-48 md:w-56 bg-white/5 border-white/10 text-sm placeholder:text-gray-500 focus:bg-white/10 focus:border-white/20 transition-all"
                                        placeholder="Search..."
                                        @input="performSearch"
                                    />
                                </div>

                                <!-- Search Results Dropdown -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed inset-0 flex items-start justify-center pt-20" style="z-index: 2000;" @click="showResultsSection = false">
                                        <div class="rounded-xl shadow-2xl bg-gray-900/95 backdrop-blur-xl border border-white/10" style="width: 550px; max-width: 90vw;" @click.stop>
                                    <!-- Filter Tabs -->
                                    <div class="flex border-b border-white/10">
                                        <button
                                            @click.stop="searchCategory = 'all'"
                                            class="flex-1 px-4 py-3 text-xs font-semibold transition-all border-b-2"
                                            :class="searchCategory == 'all' ? 'border-blue-500 text-blue-400 bg-blue-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            All
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'maps'"
                                            class="flex-1 px-4 py-3 text-xs font-semibold transition-all border-b-2"
                                            :class="searchCategory == 'maps' ? 'border-green-500 text-green-400 bg-green-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Maps
                                        </button>
                                        <button
                                            @click.stop="searchCategory = 'players'"
                                            class="flex-1 px-4 py-3 text-xs font-semibold transition-all border-b-2"
                                            :class="searchCategory == 'players' ? 'border-purple-500 text-purple-400 bg-purple-500/10' : 'border-transparent text-gray-400 hover:text-white hover:bg-white/5'"
                                        >
                                            Players
                                        </button>
                                    </div>

                                    <!-- Results -->
                                    <div class="defrag-scrollbar p-2" style="max-height: 450px; overflow-y: auto;">
                                        <div v-if="search.length == 0" class="text-center py-16 text-gray-500 text-sm">
                                            Start typing to search...
                                        </div>

                                        <div ref="resultsSection" v-else>
                                            <!-- Maps -->
                                            <div v-if="maps.data?.length > 0 && (searchCategory == 'all' || searchCategory == 'maps')">
                                                <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                                            </div>

                                            <!-- Players -->
                                            <div v-if="players?.length > 0 && (searchCategory == 'all' || searchCategory == 'players')" :class="{'mt-2': maps.data?.length > 0 && searchCategory == 'all'}">
                                                <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                                            </div>

                                            <!-- No Results -->
                                            <div v-if="players?.length == 0 && maps.data?.length == 0" class="text-center py-16">
                                                <p class="text-sm text-gray-400 mb-1">No results found</p>
                                                <p class="text-xs text-gray-600">Try different keywords</p>
                                            </div>
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
                                <Link :href="route('login')" class="hidden sm:block">
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
