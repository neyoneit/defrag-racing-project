<script setup>
    import { Head, router, Link, usePage } from '@inertiajs/vue3';
    import Rating from '@/Components/Rating.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { watchEffect, ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        vq3Ratings: Object,
        cpmRatings: Object,
        myVq3Rating: Object,
        myCpmRating: Object,
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

    const sortByGametype = (gtValue) => {
        gametype.value = gtValue;

        router.reload({
            data: {
                gametype: gtValue,
            }
        })
    }

    const selectRankingType = (rt) => {
        rankingtype.value = rt;

        router.reload({
            data: {
                rankingtype: rt,
            }
        })
    }

    const selectCategory = (cat) => {
        category.value = cat;

        router.reload({
            data: {
                category: cat,
            }
        })
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

        router.reload()

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
        startInterval();
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
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
                    <!-- Left: Title -->
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Player Rankings</h1>
                    </div>

                    <!-- Right: Filters -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 w-full lg:w-auto">
                        <!-- Ranking Type Toggle (Separate Block) -->
                        <div class="flex sm:flex-col gap-2 backdrop-blur-xl bg-white/5 border border-white/10 rounded-xl p-2.5 w-full sm:w-auto">
                            <button
                                v-for="rt in rankingtypes"
                                :key="rt"
                                @click="selectRankingType(rt)"
                                :class="rankingtype === rt ? 'bg-blue-500/30 border-blue-400/50 text-white' : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'"
                                class="px-4 py-2 rounded-lg border text-sm font-semibold uppercase transition-all whitespace-nowrap flex-1 sm:flex-none">
                                {{ rt.replace('_', ' ') }}
                            </button>
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
                                <div v-for="group in gametypeGroups" :key="group.label" class="flex items-center gap-2 backdrop-blur-xl bg-white/5 border border-white/10 rounded-xl p-2">
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
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-8">
            <!-- My Ratings Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- My VQ3 Rating -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
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
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
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

            <!-- All Rankings Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- VQ3 Rankings -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" />
                            <h2 class="text-lg font-bold text-blue-400">VQ3 Rankings <span class="text-sm font-normal text-gray-400">({{ vq3Ratings.total }})</span></h2>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="vq3Ratings.total > 0">
                            <Rating v-for="rating in vq3Ratings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center py-16 text-gray-400">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">There are no VQ3 Ratings</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="vq3Ratings.total > vq3Ratings.per_page" class="border-t border-blue-500/20 bg-transparent p-4">
                        <Pagination pageName="vq3Page" :last_page="vq3Ratings.last_page" :current_page="vq3Ratings.current_page" :link="vq3Ratings.first_page_url" />
                    </div>
                </div>

                <!-- CPM Rankings -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" />
                            <h2 class="text-lg font-bold text-purple-400">CPM Rankings <span class="text-sm font-normal text-gray-400">({{ cpmRatings.total }})</span></h2>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="cpmRatings.total > 0">
                            <Rating v-for="rating in cpmRatings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center py-16 text-gray-400">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">There are no CPM Ratings</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="cpmRatings.total > cpmRatings.per_page" class="border-t border-purple-500/20 bg-transparent p-4">
                        <Pagination pageName="cpmPage" :last_page="cpmRatings.last_page" :current_page="cpmRatings.current_page" :link="cpmRatings.first_page_url" />
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
