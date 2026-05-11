<script setup>
    import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import axios from 'axios';
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

    const profanityList = ['nigger', 'nigga', 'faggot', 'retard', 'bitch', 'cunt'];
    const profanityRegex = new RegExp(`(${profanityList.join('|')})`, 'gi');
    const censorText = (text) => {
        if (!text) return text;
        return text.replace(profanityRegex, (match) => match[0] + '*'.repeat(match.length - 2) + match[match.length - 1]);
    };

    // Reactive nav active states (re-evaluate on every page change)
    const navActive = computed(() => {
        const url = page.url; // dependency for reactivity
        return {
            home: route().current('home'),
            servers: route().current('servers'),
            players: route().current('records') || route().current('clans.*'),
            rankings: route().current('ranking') || route().current('community'),
            mapsmodels: route().current('maps') || route().current('maps.stats') || route().current('models.*'),
            demos: route().current('demos.*') || route().current('youtube'),
            challenges: route().current('headhunter.*') || route().current('marketplace.*') || route().current('community.tasks'),
            tournaments: route().current('tournaments.*'),
            wiki: route().current('wiki.*'),
            bundles: route().current('bundles'),
            // Sub-items
            records: route().current('records'),
            clans: route().current('clans.*'),
            ranking: route().current('ranking'),
            community: route().current('community'),
            maps: route().current('maps'),
            mapsStats: route().current('maps.stats'),
            maplists: route().current('maplists.*'),
            models: route().current('models.*'),
            demosIndex: route().current('demos.*'),
            youtube: route().current('youtube'),
            headhunter: route().current('headhunter.*'),
            marketplace: route().current('marketplace.*'),
            communityTasks: route().current('community.tasks'),
        };
    });

    // NEW badge tracking - hide after user visits a section
    const seenSections = ref(JSON.parse(localStorage.getItem('seen_sections') || '[]'));

    const isNew = (section) => !seenSections.value.includes(section);

    const markSeen = (section) => {
        if (!seenSections.value.includes(section)) {
            seenSections.value.push(section);
            localStorage.setItem('seen_sections', JSON.stringify(seenSections.value));
        }
    };

    // Map route patterns to section names
    const routeSectionMap = {
        'ranking': 'ranking',
        'maplists': 'maplists',
        'clans': 'clans',
        'headhunter': 'headhunter',
        'models': 'models',
        'marketplace': 'marketplace',
        'demos': 'demos',
    };

    // Mark current section as seen on page load
    // Effects - apply body classes based on viewer's preferences
    const applyEffectsIntensity = () => {
        const user = page.props.auth?.user;
        const avatarOn = (user?.avatar_effects_intensity ?? 100) > 0;
        const nameOn = (user?.name_effects_intensity ?? 100) > 0;
        const avatarSpeed = user?.avatar_effects_speed ?? 100;
        const nameSpeed = user?.name_effects_speed ?? 100;

        document.body.classList.remove('avatar-fx-off', 'avatar-speed-off', 'avatar-speed-reduced', 'name-fx-off', 'name-speed-off', 'name-speed-reduced');

        if (!avatarOn) document.body.classList.add('avatar-fx-off');
        if (avatarSpeed === 0) document.body.classList.add('avatar-speed-off');
        else if (avatarSpeed <= 50) document.body.classList.add('avatar-speed-reduced');

        if (!nameOn) document.body.classList.add('name-fx-off');
        if (nameSpeed === 0) document.body.classList.add('name-speed-off');
        else if (nameSpeed <= 50) document.body.classList.add('name-speed-reduced');
    };

    const dotsCanvas = ref(null);
    let dotsAnimId = null;

    const initDots = () => {
        const canvas = dotsCanvas.value;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;

        const resize = () => {
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            ctx.scale(dpr, dpr);
        };
        resize();
        window.addEventListener('resize', resize);

        // Particles matching original blue dots style (#3B82F6, r~3, opacity 0.8)
        const particles = [];
        for (let i = 0; i < 30; i++) {
            particles.push({
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                vx: (Math.random() - 0.5) * 3,
                vy: (Math.random() - 0.5) * 2,
                r: Math.random() * 0.8 + 1,
                a: Math.random() * 0.4 + 0.4,
                color: [59, 130, 246],
            });
        }

        let lastFrame = 0;
        const fps = 20;
        const interval = 1000 / fps;

        const draw = (time) => {
            dotsAnimId = requestAnimationFrame(draw);
            if (time - lastFrame < interval) return;
            lastFrame = time;

            const w = window.innerWidth;
            const h = window.innerHeight;
            ctx.clearRect(0, 0, w, h);

            for (const p of particles) {
                p.x += p.vx;
                p.y += p.vy;
                if (p.x < -10) p.x = w + 10;
                if (p.x > w + 10) p.x = -10;
                if (p.y < -10) p.y = h + 10;
                if (p.y > h + 10) p.y = -10;

                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${p.color[0]},${p.color[1]},${p.color[2]},${p.a})`;
                ctx.fill();
            }
        };
        dotsAnimId = requestAnimationFrame(draw);
    };

    onMounted(() => {
        applyEffectsIntensity();
        initDots();

        const currentRoute = route().current() || '';
        for (const [prefix, section] of Object.entries(routeSectionMap)) {
            if (currentRoute.startsWith(prefix)) {
                markSeen(section);
                break;
            }
        }
        // Also handle /models which uses href not route name
        if (window.location.pathname.startsWith('/models')) markSeen('models');
        if (window.location.pathname.startsWith('/test-map-viewer')) markSeen('beta');
    });

    // Track navigation
    router.on('navigate', (event) => {
        const url = event.detail?.page?.url || '';
        for (const [prefix, section] of Object.entries(routeSectionMap)) {
            if (url.startsWith('/' + prefix)) {
                markSeen(section);
                break;
            }
        }
        if (url.startsWith('/models')) markSeen('models');
        if (url.startsWith('/test-map-viewer')) markSeen('beta');
    });

    const search = ref('');

    const maps = ref([]);
    const players = ref([]);
    const clans = ref([]);
    const bundles = ref([]);
    const models = ref([]);

    const showResultsSection = ref(false);
    const activeSearchTab = ref('maps');

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
                    if (maps.value?.data) {
                        maps.value.data = maps.value.data.map(m => ({ ...m, name: censorText(m.name), author: censorText(m.author) }));
                    }
                    players.value = response.data?.players
                    clans.value = response.data?.clans || []
                    bundles.value = response.data?.bundles || []
                    models.value = (response.data?.models || []).map(m => ({ ...m, name: censorText(m.name), author: censorText(m.author) }))
                    showResultsSection.value = true;
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

    const searchResultsDropdown = ref(null);

    const handleNavClick = (event) => {
        // Close search if clicking outside search input AND outside results dropdown
        if (showResultsSection.value && searchSection.value && !searchSection.value.contains(event.target)) {
            // Don't close if clicking inside the teleported results dropdown
            if (searchResultsDropdown.value && searchResultsDropdown.value.contains(event.target)) return;
            closeSearch();
        }
    };

    // Cycling notification preview for record notifications
    const dismissedRecordIds = ref(new Set());
    const notifications = computed(() => {
        const all = page.props.recordsNotifications || [];
        return all.filter(n => !dismissedRecordIds.value.has(n.id));
    });
    const currentNotificationIndex = ref(0);
    let notificationInterval;

    const currentNotification = computed(() => {
        if (notifications.value.length === 0) return null;
        return notifications.value[currentNotificationIndex.value % notifications.value.length];
    });

    const cycleNotification = () => {
        if (notifications.value.length > 0) {
            currentNotificationIndex.value = (currentNotificationIndex.value + 1) % notifications.value.length;
        }
    };

    const dismissRecordNotification = (navigate = false) => {
        const notification = currentNotification.value;
        if (!notification) return;

        dismissedRecordIds.value = new Set([...dismissedRecordIds.value, notification.id]);

        if (notifications.value.length > 0) {
            currentNotificationIndex.value = currentNotificationIndex.value % notifications.value.length;
        } else {
            currentNotificationIndex.value = 0;
        }

        axios.post(route('notifications.toggle', notification.id)).finally(() => {
            if (navigate) {
                router.visit(route('notifications.index', { highlight: notification.id }));
            }
        });
    };

    // Cycling notification preview for system notifications
    const dismissedSystemIds = ref(new Set());
    const systemNotifications = computed(() => {
        const all = page.props.systemNotifications || [];
        return all.filter(n => !dismissedSystemIds.value.has(n.id));
    });
    const currentSystemNotificationIndex = ref(0);
    let systemNotificationInterval;

    const currentSystemNotification = computed(() => {
        if (systemNotifications.value.length === 0) return null;
        return systemNotifications.value[currentSystemNotificationIndex.value % systemNotifications.value.length];
    });

    const cycleSystemNotification = () => {
        if (systemNotifications.value.length > 0) {
            currentSystemNotificationIndex.value = (currentSystemNotificationIndex.value + 1) % systemNotifications.value.length;
        }
    };

    const dismissSystemNotification = (navigate = false) => {
        const notification = currentSystemNotification.value;
        if (!notification) return;

        // Track dismissed ID immediately so it hides from UI
        dismissedSystemIds.value = new Set([...dismissedSystemIds.value, notification.id]);

        // Fix index if needed
        if (systemNotifications.value.length > 0) {
            currentSystemNotificationIndex.value = currentSystemNotificationIndex.value % systemNotifications.value.length;
        } else {
            currentSystemNotificationIndex.value = 0;
        }

        // Mark as read via API, then navigate if requested
        axios.post(route('notifications.system.toggle', notification.id)).finally(() => {
            if (navigate) {
                router.visit(route('notifications.system.index', { highlight: notification.id }));
            }
        });
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
        if (dotsAnimId) cancelAnimationFrame(dotsAnimId);
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

        <div class="min-h-screen flex flex-col bg-gray-900 bg-[url('/images/pattern.svg')] relative overflow-x-hidden">
            <canvas ref="dotsCanvas" style="position: fixed; inset: 0; pointer-events: none; z-index: 0;"></canvas>
            <!-- Modern Compact Header -->
            <nav class="bg-white/[0.025] border-b border-white/[0.04] sticky top-0 z-[200] shadow-[0_4px_30px_rgba(0,0,0,0.4)] backdrop-blur-md" @click="handleNavClick">
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
                                    <svg class="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 h-3.5 sm:h-4 w-3.5 sm:w-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <TextInput
                                        v-model="search"
                                        @focus="onSearchFocus"
                                        type="text"
                                        class="h-8 sm:h-9 pl-8 sm:pl-9 pr-7 sm:pr-8 w-full bg-white/[0.08] border-white/[0.12] text-xs sm:text-sm placeholder:text-gray-400 focus:bg-white/[0.12] focus:border-white/25 transition-all"
                                        placeholder="Search..."
                                        @input="performSearch"
                                    />
                                    <button v-if="search.length > 0" @click="search = ''; closeSearch();" class="absolute right-1.5 sm:right-2 top-1/2 -translate-y-1/2 p-0.5 text-gray-500 hover:text-white transition-colors">
                                        <svg class="h-3.5 sm:h-4 w-3.5 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Backdrop - positioned BELOW the nav (z-40) -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" class="fixed inset-0 z-[199]" @click="closeSearch"></div>
                                </Teleport>

                                <!-- Search Results Dropdown -->
                                <Teleport to="body">
                                    <div v-if="showResultsSection" ref="searchResultsDropdown" class="fixed rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.5)] bg-gray-800/90 backdrop-blur-xl border border-white/15 overflow-hidden z-[202] left-1/2 -translate-x-1/2 w-full max-w-5xl mx-auto px-4 lg:px-8"
                                         :style="{ top: searchDropdownPosition.top + 'px', maxHeight: '55vh' }"
                                         @click.stop>

                                    <div v-if="search.length == 0" class="text-center py-20 text-gray-500 text-sm">
                                        Start typing to search...
                                    </div>

                                    <div v-else class="flex flex-col h-full">
                                        <!-- Mobile Tabs (< lg) -->
                                        <div class="lg:hidden flex border-b border-white/10">
                                            <button @click="activeSearchTab = 'maps'" :class="['flex-1 py-2.5 text-xs font-bold transition-colors', activeSearchTab === 'maps' ? 'text-green-400 border-b-2 border-green-400' : 'text-gray-500 hover:text-gray-300']">
                                                MAPS<span v-if="maps.data?.length > 0" class="ml-1 opacity-60">({{ maps.data.length }})</span>
                                            </button>
                                            <button @click="activeSearchTab = 'players'" :class="['flex-1 py-2.5 text-xs font-bold transition-colors', activeSearchTab === 'players' ? 'text-purple-400 border-b-2 border-purple-400' : 'text-gray-500 hover:text-gray-300']">
                                                PLAYERS<span v-if="players?.length > 0" class="ml-1 opacity-60">({{ players.length }})</span>
                                            </button>
                                            <button @click="activeSearchTab = 'models'" :class="['flex-1 py-2.5 text-xs font-bold transition-colors', activeSearchTab === 'models' ? 'text-blue-400 border-b-2 border-blue-400' : 'text-gray-500 hover:text-gray-300']">
                                                MODELS<span v-if="models?.length > 0" class="ml-1 opacity-60">({{ models.length }})</span>
                                            </button>
                                        </div>

                                        <!-- Mobile Tab Content (< lg) -->
                                        <div class="lg:hidden p-4 overflow-y-auto defrag-scrollbar max-h-[55vh]">
                                            <!-- Maps -->
                                            <div v-if="activeSearchTab === 'maps'">
                                                <div v-if="maps.data?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No maps found</div>
                                            </div>
                                            <!-- Players -->
                                            <div v-if="activeSearchTab === 'players'">
                                                <div v-if="players?.length > 0" class="space-y-2" @click="closeSearch">
                                                    <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                                                </div>
                                                <div v-else class="text-xs text-gray-600 text-center py-8">No players found</div>
                                            </div>
                                            <!-- Models -->
                                            <div v-if="activeSearchTab === 'models'">
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

                                        <!-- Desktop Grid Layout (>= lg) -->
                                        <div ref="resultsSection" class="hidden lg:grid grid-cols-3 gap-4 p-6 overflow-y-auto defrag-scrollbar max-h-[55vh]">
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
                            <div v-if="$page.props.auth.user && currentNotification" class="hidden xl:flex items-center gap-1">
                                <button @click="dismissRecordNotification(true)" class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/20 rounded-l-lg transition-all hover:border-orange-500/40 cursor-pointer group">
                                    <div class="shrink-0 w-2 h-2 bg-orange-400 rounded-full animate-pulse"></div>
                                    <div class="flex items-center gap-1.5 min-w-0 text-xs">
                                        <span class="font-bold text-white truncate" v-html="q3tohtml(currentNotification.name || '')"></span>
                                        <span class="font-bold text-blue-400 truncate">{{ currentNotification.mapname }}</span>
                                        <span class="font-bold text-green-400">{{ formatTime(timeDiff(currentNotification.user_time || 0, currentNotification.time || 0)) }}</span>
                                    </div>
                                    <div class="shrink-0 flex items-center justify-center min-w-[20px] h-5 px-1.5 bg-orange-500 text-white text-xs font-bold rounded">
                                        {{ notifications.length }}
                                    </div>
                                </button>
                                <button
                                    @click.stop="dismissRecordNotification(false)"
                                    class="flex items-center px-1.5 py-1.5 bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/20 border-l-0 rounded-r-lg transition-all hover:bg-red-500/20 hover:border-red-500/30 text-gray-500 hover:text-red-400"
                                    title="Dismiss"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- System Notification Preview (visible on lg and above) -->
                            <div v-if="$page.props.auth.user && currentSystemNotification" class="hidden lg:flex items-center gap-1">
                                <button @click="dismissSystemNotification(true)" class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-l-lg transition-all hover:border-blue-500/40 cursor-pointer group">
                                    <div class="shrink-0 w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                                    <div class="flex items-center gap-1.5 min-w-0 text-xs">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-blue-400 shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                        </svg>
                                        <span class="text-gray-500">{{ currentSystemNotification.type?.startsWith('clan_') ? 'Clan:' : currentSystemNotification.type?.startsWith('tournament_') || currentSystemNotification.type?.startsWith('round_') ? 'Tournament:' : 'Announcement:' }}</span>
                                        <span class="font-bold text-blue-400 truncate" v-html="q3tohtml(currentSystemNotification.headline || '')"></span>
                                    </div>
                                    <div class="shrink-0 flex items-center justify-center min-w-[20px] h-5 px-1.5 bg-blue-500 text-white text-xs font-bold rounded">
                                        {{ systemNotifications.length }}
                                    </div>
                                </button>
                                <button
                                    @click.stop="dismissSystemNotification(false)"
                                    class="flex items-center px-1.5 py-1.5 bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 border-l-0 rounded-r-lg transition-all hover:bg-red-500/20 hover:border-red-500/30 text-gray-500 hover:text-red-400"
                                    title="Dismiss"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

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
                                <Dropdown align="right" width="56">
                                    <template #trigger>
                                        <button class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/10 transition-all group">
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
                                        <DropdownLink :href="route('profile.index', $page.props.auth.user.id)">
                                            My Profile
                                        </DropdownLink>
                                        <template v-if="$page.props.auth.user.email_verified_at">
                                            <DropdownLink :href="route('notifications.index')">
                                                Notification Center
                                            </DropdownLink>
                                            <DropdownLink :href="route('maplists.index') + '?user=' + $page.props.auth.user.id">
                                                Play Later
                                            </DropdownLink>
                                        </template>
                                        <div class="mx-3 border-t border-white/10 my-1" />
                                        <DropdownLink :href="route('settings.show')">
                                            Settings
                                        </DropdownLink>
                                        <div class="pl-7 pr-3 pb-2 -mt-1 space-y-px">
                                            <Link :href="route('settings.show')" class="block text-sm text-gray-400 hover:text-blue-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Profile</Link>
                                            <template v-if="$page.props.auth.user.email_verified_at">
                                                <Link :href="route('settings.show') + '?tab=creator'" class="block text-sm text-gray-500 hover:text-yellow-400 py-0.5 px-2 rounded hover:bg-white/5 transition-all ml-3 border-l border-white/10 pl-3">Creator</Link>
                                                <Link :href="route('settings.show') + '?tab=customize'" class="block text-sm text-gray-500 hover:text-purple-400 py-0.5 px-2 rounded hover:bg-white/5 transition-all ml-3 border-l border-white/10 pl-3">Customize</Link>
                                                <Link :href="route('settings.show') + '?tab=global-customize'" class="block text-sm text-gray-400 hover:text-teal-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Global Customize</Link>
                                                <Link :href="route('settings.show') + '?tab=marketplace'" class="block text-sm text-gray-400 hover:text-blue-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Marketplace</Link>
                                                <Link :href="route('settings.show') + '?tab=notifications'" class="block text-sm text-gray-400 hover:text-orange-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Notifications pref.</Link>
                                            </template>
                                            <Link :href="route('settings.show') + '?tab=security'" class="block text-sm text-gray-400 hover:text-red-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Security</Link>
                                            <Link :href="route('server-hosting.index')" class="block text-sm text-gray-400 hover:text-emerald-400 py-1 px-2 rounded hover:bg-white/5 transition-all">Server hosting</Link>
                                        </div>
                                        <a v-if="$page.props.auth.user.admin || $page.props.auth.user.is_moderator" href="/defraghq" target="_blank" class="block w-full px-4 py-2 text-sm leading-5 text-emerald-400 hover:bg-white/5 transition-all font-semibold">
                                            Admin Panel
                                        </a>
                                        <div class="mx-3 border-t border-white/10 my-1" />
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                <span class="text-red-400">Log Out</span>
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

                    <!-- Second Row: Navigation Links -->
                    <div class="flex items-center gap-1 h-12 -mx-4 md:-mx-6 lg:-mx-8 px-4 md:px-6 lg:px-8 border-t border-white/[0.08] overflow-x-auto overflow-y-hidden scrollbar-hide">

                        <!-- 0. Home - always visible -->
                        <NavLink :href="route('home')" :active="navActive.home">
                            Home
                        </NavLink>

                        <!-- 1. Servers - always visible -->
                        <NavLink :href="route('servers')" :active="navActive.servers">
                            Servers
                        </NavLink>

                        <!-- 2. Players - visible from md -->
                        <div class="hidden md:block">
                            <Dropdown align="left" width="48" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="navActive.players ? 'text-white bg-blue-600/30 border border-blue-500/40' : open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>Players</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('records')" :active="navActive.records">Records</DropdownLink>
                                    <DropdownLink :href="route('clans.index')" :active="navActive.clans">Clans</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- 3. Rankings - visible from lg -->
                        <div class="hidden lg:block">
                            <Dropdown align="left" width="56" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="navActive.rankings ? 'text-white bg-blue-600/30 border border-blue-500/40' : open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>Rankings</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('ranking')" :active="navActive.ranking">Player Ranking</DropdownLink>
                                    <DropdownLink :href="route('community')" :active="navActive.community">Community Leaderboard</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- 4. Maps & Models - visible from md -->
                        <div class="hidden md:block">
                            <Dropdown align="left" width="48" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="navActive.mapsmodels ? 'text-white bg-blue-600/30 border border-blue-500/40' : open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>Maps & Models</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('maps')" :active="navActive.maps">Maps</DropdownLink>
                                    <DropdownLink :href="route('maps.stats')" :active="navActive.mapsStats">Map Statistics</DropdownLink>
                                    <DropdownLink href="/models" :active="navActive.models">Models</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- 4b. Maplists - visible from xl -->
                        <Link :href="route('maplists.index')"
                            class="hidden xl:inline-flex items-center px-3 py-2 text-sm font-medium transition-all rounded-lg"
                            :class="navActive.maplists ? 'text-white bg-blue-600/30 border border-blue-500/40' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                            Maplists
                        </Link>

                        <!-- 5. Demos - visible from md -->
                        <div class="hidden md:block">
                            <Dropdown align="left" width="48" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="navActive.demos ? 'text-white bg-blue-600/30 border border-blue-500/40' : open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>Demos</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('demos.index')" :active="navActive.demosIndex">Upload & Browse</DropdownLink>
                                    <DropdownLink :href="route('youtube')" :active="navActive.youtube">Rendered Demos</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- 6. Challenges - visible from lg -->
                        <div class="hidden lg:block">
                            <Dropdown align="left" width="48" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="navActive.challenges ? 'text-white bg-blue-600/30 border border-blue-500/40' : open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>Challenges</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('headhunter.index')" :active="navActive.headhunter">Headhunter</DropdownLink>
                                    <DropdownLink :href="route('marketplace.index')" :active="navActive.marketplace">Marketplace</DropdownLink>
                                    <DropdownLink :href="route('community.tasks')" :active="navActive.communityTasks">Community Tasks</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- 7. Tournaments - visible from xl -->
                        <div class="hidden xl:inline-flex">
                            <NavLink :href="route('tournaments.index')" :active="navActive.tournaments">
                                Tournaments
                            </NavLink>
                        </div>

                        <!-- 8. Wiki - visible from xl -->
                        <div class="hidden xl:inline-flex">
                            <NavLink :href="route('wiki.index')" :active="navActive.wiki">
                                Wiki
                            </NavLink>
                        </div>

                        <!-- 9. Downloads - visible from xl -->
                        <div class="hidden xl:inline-flex">
                            <NavLink :href="route('bundles')" :active="navActive.bundles">
                                Downloads
                            </NavLink>
                        </div>

                        <!-- 9. Beta - visible from xl -->
                        <div class="hidden xl:inline-flex">
                            <NavLink href="/test-map-viewer.html?map=pornstar-cpmrun">
                                Beta
                            </NavLink>
                        </div>

                        <!-- More dropdown - visible below xl -->
                        <div class="xl:hidden">
                            <Dropdown align="left" width="56" :hoverable="true">
                                <template #trigger="{ open }">
                                    <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all rounded-lg"
                                        :class="open ? 'text-white bg-white/10 border border-white/10' : 'text-white hover:text-white hover:bg-white/10 border border-transparent'">
                                        <span>More</span>
                                        <svg class="h-3.5 w-3.5 opacity-70 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <!-- Items hidden below md (Players, Maps, Demos) -->
                                    <div class="md:hidden">
                                        <div class="px-3 py-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Players</div>
                                        <DropdownLink :href="route('records')" :active="navActive.records">Records</DropdownLink>
                                        <DropdownLink :href="route('clans.index')" :active="navActive.clans">Clans</DropdownLink>
                                        <div class="px-3 py-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wider mt-1">Maps & Models</div>
                                        <DropdownLink :href="route('maps')" :active="navActive.maps">Maps</DropdownLink>
                                        <DropdownLink :href="route('maps.stats')" :active="navActive.mapsStats">Map Statistics</DropdownLink>
                                        <DropdownLink href="/models" :active="navActive.models">Models</DropdownLink>
                                        <div class="px-3 py-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wider mt-1">Demos</div>
                                        <DropdownLink :href="route('demos.index')" :active="navActive.demosIndex">Upload & Browse</DropdownLink>
                                        <DropdownLink :href="route('youtube')" :active="navActive.youtube">Rendered Demos</DropdownLink>
                                        <div class="border-t border-white/10 my-1.5"></div>
                                    </div>
                                    <!-- Items hidden below lg (Rankings, Challenges) -->
                                    <div class="lg:hidden">
                                        <div class="px-3 py-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Rankings</div>
                                        <DropdownLink :href="route('ranking')" :active="navActive.ranking">Player Ranking</DropdownLink>
                                        <DropdownLink :href="route('community')" :active="navActive.community">Community Leaderboard</DropdownLink>
                                        <div class="px-3 py-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wider mt-1">Challenges</div>
                                        <DropdownLink :href="route('headhunter.index')" :active="navActive.headhunter">Headhunter</DropdownLink>
                                        <DropdownLink :href="route('marketplace.index')" :active="navActive.marketplace">Marketplace</DropdownLink>
                                        <DropdownLink :href="route('community.tasks')" :active="navActive.communityTasks">Community Tasks</DropdownLink>
                                        <div class="border-t border-white/10 my-1.5"></div>
                                    </div>
                                    <!-- Items always in More (hidden inline below xl) -->
                                    <DropdownLink :href="route('maplists.index')" :active="navActive.maplists">Maplists</DropdownLink>
                                    <DropdownLink :href="route('tournaments.index')" :active="navActive.tournaments">Tournaments</DropdownLink>
                                    <DropdownLink :href="route('wiki.index')" :active="navActive.wiki">Wiki</DropdownLink>
                                    <DropdownLink :href="route('bundles')" :active="navActive.bundles">Downloads</DropdownLink>
                                    <DropdownLink href="/test-map-viewer.html?map=pornstar-cpmrun">Beta</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                    </div>
                </div>
            </nav>

            <!-- Latest Announcement Banner (guests only) -->
            <div v-if="!$page.props.auth?.user && $page.props.globalLatestAnnouncement" class="bg-gradient-to-r from-blue-500/10 via-blue-500/5 to-blue-500/10 border-b border-blue-500/20">
                <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                    <Link href="/announcements" class="flex items-center justify-between gap-4 py-2 group">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                            </svg>
                            <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">Latest News</span>
                            <span class="text-sm text-gray-300 group-hover:text-white transition-colors truncate">{{ $page.props.globalLatestAnnouncement.title }}</span>
                        </div>
                        <svg class="w-4 h-4 text-blue-400/50 group-hover:translate-x-1 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Verify Email Banner (unverified users) -->
            <div v-if="$page.props.auth?.user && !$page.props.auth.user.email_verified_at" class="bg-gradient-to-r from-red-500/10 via-red-500/15 to-red-500/10 border-b border-red-500/20">
                <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                    <Link href="/email/verify" class="flex items-center justify-between gap-4 py-2.5 group">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-red-400 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <span class="text-sm font-bold text-red-400">Verify your email address</span>
                            <span class="text-xs text-gray-400 hidden sm:inline">to unlock all features including demo uploads, map tagging, and more</span>
                        </div>
                        <svg class="w-4 h-4 text-red-400/50 group-hover:translate-x-1 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Link Account Banner (unlinked users, only if email verified) -->
            <div v-if="$page.props.auth?.user && $page.props.auth.user.email_verified_at && !$page.props.auth.user.mdd_id" class="bg-gradient-to-r from-yellow-500/10 via-amber-500/15 to-yellow-500/10 border-b border-yellow-500/20">
                <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                    <Link href="/link-account" class="flex items-center justify-between gap-4 py-2.5 group">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            <span class="text-sm font-bold text-yellow-400">Link your Q3DF profile</span>
                            <span class="text-xs text-gray-400 hidden sm:inline">to unlock records, rankings, demo uploads, map tagging, and more</span>
                        </div>
                        <svg class="w-4 h-4 text-yellow-400/50 group-hover:translate-x-1 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="shadow">
                <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="pb-4 flex-grow">
                <slot />
            </main>

            <!-- Donation Progress Bar -->
            <div class="mt-4">
                <DonationProgressBar />
            </div>

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
