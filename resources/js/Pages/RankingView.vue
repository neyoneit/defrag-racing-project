<script setup>
    import { Head, router, Link, usePage } from '@inertiajs/vue3';
    import Rating from '@/Components/Rating.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { watchEffect, ref, computed, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        vq3Ratings: Object,
        cpmRatings: Object,
        myVq3Rating: Object,
        myCpmRating: Object,
        lastRecalculation: String,
    });

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');
    const gametypeGroups = [
        {
            label: 'Run',
            types: [{ value: 'run', label: 'Run' }]
        },
        {
            label: 'CTF',
            types: [
                { value: 'ctf1', label: 'CTF 1' },
                { value: 'ctf2', label: 'CTF 2' },
                { value: 'ctf3', label: 'CTF 3' },
                { value: 'ctf4', label: 'CTF 4' },
                { value: 'ctf5', label: 'CTF 5' },
                { value: 'ctf6', label: 'CTF 6' },
                { value: 'ctf7', label: 'CTF 7' },
            ]
        }
    ];

    const rankingtype = ref('active_players');
    const rankingtypes = [
        'active_players',
        'all_players',
    ];

    const category = ref('overall');
    const categories = [
        { value: 'overall', label: 'All', icon: '🏆', image: null, color: 'orange' },
        { value: 'strafe', label: 'Strafe', icon: '🏃', image: '/images/weapons/iconw_gauntlet.svg', color: 'sky' },
        { value: 'slick', label: 'Slick', icon: '🧊', image: '/images/functions/slick.svg', color: 'yellow' },
        { value: 'tele', label: 'Tele', icon: '🌀', image: '/images/functions/teleporter.svg', color: 'cyan' },
        { value: 'rocket', label: 'RL', icon: '🚀', image: '/images/weapons/iconw_rocket.svg', color: 'red' },
        { value: 'plasma', label: 'PG', icon: '⚡', image: '/images/weapons/iconw_plasma.svg', color: 'purple' },
        { value: 'grenade', label: 'GL', icon: '💣', image: '/images/weapons/iconw_grenade.svg', color: 'green' },
        { value: 'lg', label: 'LG', icon: '⚡', image: '/images/weapons/iconw_lightning.svg', color: 'white' },
        { value: 'bfg', label: 'BFG', icon: '💥', image: '/images/weapons/iconw_bfg.svg', color: 'blue' },
    ];

    const reloadRankings = () => {
        ratingsLoaded.value = false;
        router.reload({
            only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
            data: {
                gametype: gametype.value,
                rankingtype: rankingtype.value,
                category: category.value,
            },
            onFinish: () => {
                ratingsLoaded.value = true;
            }
        })
    }

    const sortByGametype = (gtValue) => {
        gametype.value = gtValue;
        reloadRankings();
    }

    const selectRankingType = (rt) => {
        rankingtype.value = rt;
        reloadRankings();
    }

    const isLoggedIn = computed(() => !!usePage().props.auth?.user);
    const showLoginPopup = ref(false);
    const guestLockedCategories = ['strafe', 'slick', 'rocket', 'plasma', 'grenade', 'lg', 'bfg', 'tele'];

    const selectCategory = (cat) => {
        if (!isLoggedIn.value && guestLockedCategories.includes(cat)) {
            showLoginPopup.value = true;
            return;
        }
        category.value = cat;
        reloadRankings();
    }

    watchEffect(() => {
        gametype.value = route().params['gametype'] ?? 'run';
        rankingtype.value = route().params['rankingtype'] ?? 'active_players';
        category.value = route().params['category'] ?? 'overall';
    });

    // ------------------------------------------------------
    const screenWidth = ref(window.innerWidth);
    const isRotating = ref(false);
    const interval = ref(null);
    const page = usePage();
    const cpmFirst = computed(() => page.props.physicsOrder === 'cpm_first');

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

        router.reload({
            only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
            data: {
                gametype: gametype.value,
                rankingtype: rankingtype.value,
                category: category.value,
            },
        })

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }

    const ratingsLoaded = ref(false);

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
        startInterval();

        // Lazy-load rating data (page renders immediately with header/filters)
        if (!props.vq3Ratings && !props.cpmRatings) {
            router.reload({
                only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
                onFinish: () => {
                    ratingsLoaded.value = true;
                }
            });
        } else {
            ratingsLoaded.value = true;
        }
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
        stopInterval();
    });
</script>

<template>
    <div class="min-h-screen">
        <Head title="Ranking" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
                    <!-- Left: Title + Info -->
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Player Rankings</h1>
                        <div class="flex items-center gap-3 text-gray-500">
                            <div class="relative group">
                                <button class="flex items-center gap-1.5 text-xs hover:text-gray-300 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <span class="text-[11px] font-semibold underline decoration-dotted decoration-gray-500 underline-offset-2">How it works</span>
                                </button>
                                <div class="absolute left-0 top-full mt-2 w-80 bg-gray-900/95 border border-white/10 rounded-xl px-4 py-3 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 shadow-2xl">
                                    <p class="text-xs text-gray-400 leading-relaxed">
                                        Each record is scored using a <span class="text-gray-300 font-semibold">logistic curve</span> based on how close the time is to the world record.
                                        Scores are then <span class="text-gray-300 font-semibold">weighted exponentially</span> -your best maps count the most, weaker ones are diminished.
                                        The final rating is a weighted average of all your map scores. Players with fewer than <span class="text-gray-300 font-semibold">10 records</span> receive a proportional penalty.
                                        Maps with fewer than <span class="text-gray-300 font-semibold">5 players</span> or very short top times are excluded.
                                    </p>
                                </div>
                            </div>
                            <div v-if="lastRecalculation" class="flex items-center gap-1.5 text-[11px]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                </svg>
                                <span>{{ new Date(lastRecalculation).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }} {{ new Date(lastRecalculation).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Filters -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 w-full lg:w-auto">
                        <!-- Ranking Type Toggle (Separate Block) -->
                        <div class="bg-white/5 border border-white/10 rounded-xl p-2.5 w-full sm:w-auto sm:min-w-[270px]">
                            <div class="flex sm:flex-col gap-2">
                                <button
                                    v-for="rt in rankingtypes"
                                    :key="rt"
                                    @click="selectRankingType(rt)"
                                    :class="rankingtype === rt ? 'bg-blue-500/30 border-blue-400/50 text-white' : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'"
                                    class="px-4 py-2 rounded-lg border text-sm font-semibold uppercase transition-all whitespace-nowrap flex-1 sm:flex-none"
                                    :title="rt === 'active_players' ? 'Players who set a record in this physics within the last 3 calendar months' : 'All players who have ever set a record'">
                                    {{ rt.replace('_', ' ') }}
                                </button>
                            </div>
                            <div class="text-xs mt-2 text-gray-400 relative group/hint cursor-help flex items-center justify-center gap-1">
                                <svg class="w-3 h-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="underline decoration-dotted decoration-gray-500 underline-offset-2">{{ rankingtype === 'active_players' ? 'Recently active players only' : 'All time rankings' }}</span>
                                <div class="absolute top-full mt-1 left-1/2 -translate-x-1/2 hidden group-hover/hint:block z-20 w-56 p-2 bg-black/95 border border-white/20 rounded-lg text-xs text-gray-300 text-center shadow-lg">
                                    {{ rankingtype === 'active_players' ? 'Players who set a record in this physics within the last 3 calendar months' : 'All players who have ever set a record in this physics' }}
                                </div>
                            </div>
                        </div>

                        <!-- Categories & Gametypes -->
                        <div class="space-y-2 w-full sm:w-auto">
                            <!-- Row 1: Categories -->
                            <div class="flex items-center gap-2 flex-wrap justify-start sm:justify-end">
                                <button
                                    v-for="cat in categories"
                                    :key="cat.value"
                                    @click="selectCategory(cat.value)"
                                    :class="[
                                        category === cat.value && cat.color === 'orange' ? 'bg-orange-500/30 border-orange-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'purple' ? 'bg-purple-500/30 border-purple-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'sky' ? 'bg-sky-500/30 border-sky-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'yellow' ? 'bg-yellow-500/30 border-yellow-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'cyan' ? 'bg-cyan-500/30 border-cyan-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'red' ? 'bg-red-500/30 border-red-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'blue' ? 'bg-blue-500/30 border-blue-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'green' ? 'bg-green-500/30 border-green-400/50 text-white' : '',
                                        category === cat.value && cat.color === 'white' ? 'bg-white/30 border-white/50 text-white' : '',
                                        category !== cat.value ? 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10' : '',
                                        !isLoggedIn && guestLockedCategories.includes(cat.value) ? 'opacity-40' : '',
                                        'px-2 py-1.5 rounded-lg border text-xs font-medium transition-all flex items-center gap-1.5'
                                    ]"
                                    :title="cat.label">
                                    <img v-if="cat.image" :src="cat.image" class="w-4 h-4" :alt="cat.label" />
                                    <span v-else class="text-sm">{{ cat.icon }}</span>
                                    <span>{{ cat.label }}</span>
                                </button>
                            </div>

                            <!-- Row 2: Gametypes -->
                            <div class="flex items-center gap-2 sm:gap-3 justify-start sm:justify-end flex-wrap">
                                <div v-for="group in gametypeGroups" :key="group.label" class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-xl p-2">
                                    <button
                                        v-for="gt in group.types"
                                        :key="gt.value"
                                        @click="sortByGametype(gt.value)"
                                        :class="gametype === gt.value ? 'bg-gray-500/30 border-gray-400/50 text-white' : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'"
                                        class="px-3 py-1.5 rounded-lg border text-xs font-semibold uppercase transition-all">
                                        {{ gt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8" style="margin-top: -22rem;">
            <!-- My Ratings Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- My VQ3 Rating -->
                <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <h2 class="text-sm font-bold text-blue-400">My VQ3 Rating</h2>
                        </div>
                    </div>
                    <div class="px-2 py-1" style="min-height: 60px; display: flex; align-items: center;">
                        <div v-if="myVq3Rating" class="w-full">
                            <Rating :rating="myVq3Rating" :rank="rankingtype === 'active_players' ? myVq3Rating.active_players_rank : myVq3Rating.all_players_rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400 text-sm w-full">
                            <div v-if="page.props?.auth?.user">You have no VQ3 rating in this category</div>
                            <div v-else>You need to be logged in to see your rating</div>
                        </div>
                    </div>
                </div>

                <!-- My CPM Rating -->
                <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <h2 class="text-sm font-bold text-purple-400">My CPM Rating</h2>
                        </div>
                    </div>
                    <div class="px-2 py-1" style="min-height: 60px; display: flex; align-items: center;">
                        <div v-if="myCpmRating" class="w-full">
                            <Rating :rating="myCpmRating" :rank="rankingtype === 'active_players' ? myCpmRating.active_players_rank : myCpmRating.all_players_rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400 text-sm w-full">
                            <div v-if="page.props?.auth?.user">You have no CPM rating in this category</div>
                            <div v-else>You need to be logged in to see your rating</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading skeleton while ratings load -->
            <div v-if="!ratingsLoaded" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="i in 2" :key="i" class="bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/10 animate-pulse">
                    <div class="bg-white/5 border-b border-white/10 px-4 py-3">
                        <div class="h-6 bg-white/10 rounded w-40"></div>
                    </div>
                    <div class="px-4 py-2 space-y-3">
                        <div v-for="j in 10" :key="j" class="h-10 bg-white/5 rounded"></div>
                    </div>
                </div>
            </div>

            <!-- All Rankings Section -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- VQ3 Rankings -->
                <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-blue-400">VQ3 Rankings <span v-if="vq3Ratings" class="text-sm font-normal text-gray-400">({{ vq3Ratings.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/profile?tab=customize" class="text-xs text-gray-500 hover:text-blue-400 transition-colors underline decoration-dotted underline-offset-2">
                                Swap VQ3/CPM sides
                            </Link>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="vq3Ratings && vq3Ratings.total > 0">
                            <Rating v-for="rating in vq3Ratings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400" style="min-height: 490px;">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">{{ rankingtype === 'active_players' ? 'No active VQ3 players in the last 3 months' : 'There are no VQ3 Ratings' }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="vq3Ratings && vq3Ratings.total > vq3Ratings.per_page" class="border-t border-blue-500/20 p-4">
                        <Pagination pageName="vq3Page" :last_page="vq3Ratings.last_page" :current_page="vq3Ratings.current_page" :link="vq3Ratings.first_page_url" :only="['vq3Ratings', 'cpmRatings']" />
                    </div>
                </div>

                <!-- CPM Rankings -->
                <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-purple-400">CPM Rankings <span v-if="cpmRatings" class="text-sm font-normal text-gray-400">({{ cpmRatings.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/profile?tab=customize" class="text-xs text-gray-500 hover:text-purple-400 transition-colors underline decoration-dotted underline-offset-2">
                                Swap VQ3/CPM sides
                            </Link>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="cpmRatings && cpmRatings.total > 0">
                            <Rating v-for="rating in cpmRatings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400" style="min-height: 490px;">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">{{ rankingtype === 'active_players' ? 'No active CPM players in the last 3 months' : 'There are no CPM Ratings' }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="cpmRatings && cpmRatings.total > cpmRatings.per_page" class="border-t border-purple-500/20 p-4">
                        <Pagination pageName="cpmPage" :last_page="cpmRatings.last_page" :current_page="cpmRatings.current_page" :link="cpmRatings.first_page_url" :only="['vq3Ratings', 'cpmRatings']" />
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>

    <!-- Login Required Popup -->
    <Teleport to="body">
        <div v-if="showLoginPopup" class="fixed inset-0 z-[9999] flex items-center justify-center" @click.self="showLoginPopup = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <button @click="showLoginPopup = false" class="absolute top-3 right-3 text-gray-500 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">Login Required</h3>
                </div>
                <p class="text-gray-300 text-sm mb-5">Category filters are available only for logged-in users. Please login or register to access detailed rankings.</p>
                <div class="flex gap-3">
                    <a href="/login" class="flex-1 text-center px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition-colors">Login</a>
                    <a href="/register" class="flex-1 text-center px-4 py-2 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-lg transition-colors">Register</a>
                </div>
            </div>
        </div>
    </Teleport>
</template>
