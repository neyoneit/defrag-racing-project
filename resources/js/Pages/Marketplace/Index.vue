<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import ListingCard from '@/Components/Marketplace/ListingCard.vue';

const props = defineProps({
    listings: Object,
    filters: Object,
    canPost: Boolean,
    recordCount: Number,
});

const listingsLoaded = ref(false);

onMounted(() => {
    if (!props.listings) {
        const start = Date.now();
        router.reload({
            only: ['listings'],
            onFinish: () => {
                const remaining = 400 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { listingsLoaded.value = true; }, remaining);
                } else {
                    listingsLoaded.value = true;
                }
            }
        });
    } else {
        listingsLoaded.value = true;
    }
});

const activeTab = ref(props.filters?.tab || 'requests');
const search = ref(props.filters?.search || '');
const workTypeFilter = ref(props.filters?.work_type || '');
const statusFilter = ref(props.filters?.status || '');
const workTypeDropdownOpen = ref(false);
const statusDropdownOpen = ref(false);

const workTypes = [
    { value: '', label: 'All Types' },
    { value: 'map', label: 'Map' },
    { value: 'player_model', label: 'Player Model' },
    { value: 'weapon_model', label: 'Weapon Model' },
    { value: 'shadow_model', label: 'Shadow Model' },
];

const statuses = [
    { value: '', label: 'All Statuses' },
    { value: 'open', label: 'Open' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const selectedWorkTypeLabel = () => workTypes.find(w => w.value === workTypeFilter.value)?.label || 'All Types';
const selectedStatusLabel = () => statuses.find(s => s.value === statusFilter.value)?.label || 'All Statuses';

const applyFilters = () => {
    router.get(route('marketplace.index'), {
        tab: activeTab.value,
        search: search.value || undefined,
        work_type: workTypeFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const switchTab = (tab) => {
    activeTab.value = tab;
    applyFilters();
};

const selectWorkType = (value) => {
    workTypeFilter.value = value;
    workTypeDropdownOpen.value = false;
    applyFilters();
};

const selectStatus = (value) => {
    statusFilter.value = value;
    statusDropdownOpen.value = false;
    applyFilters();
};
</script>

<template>
    <div class="pb-4">
        <Head title="Marketplace" />

        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Marketplace</h1>
                        <p class="text-gray-400">Commission maps, models and more from the community</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('marketplace.creators')"
                            class="px-4 py-3 bg-gray-700/50 border border-white/10 text-gray-300 font-bold rounded-lg transition-all duration-300 hover:bg-gray-600/50 hover:text-white text-sm"
                        >
                            Creator Directory
                        </Link>
                        <Link
                            v-if="$page.props.auth.user && canPost"
                            :href="route('marketplace.create')"
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                        >
                            Create Listing
                        </Link>
                        <div
                            v-else-if="$page.props.auth.user && !canPost"
                            class="px-4 py-3 bg-gray-700/30 border border-white/5 text-gray-500 font-bold rounded-lg text-sm cursor-not-allowed"
                            :title="`You need at least 50 records to post. You have ${recordCount}.`"
                        >
                            50 records required
                        </div>
                        <Link
                            v-else
                            :href="route('login')"
                            class="px-6 py-3 bg-gray-700/50 border border-white/10 text-gray-400 font-bold rounded-lg transition-all duration-300 hover:bg-gray-600/50 hover:text-gray-300 flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            Log in to post
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Disclaimer Banner -->
            <div class="bg-yellow-500/10 backdrop-blur-sm border border-yellow-500/30 rounded-xl p-4 mb-6">
                <div class="flex gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="text-sm text-yellow-300">
                        <strong>Disclaimer:</strong> Defrag Racing is not responsible for marketplace transactions. All work, payments, and deliverables are agreements between the parties involved. Users who fail to honor agreements may be banned from future marketplace activity.
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 mb-4">
                <button
                    @click="switchTab('requests')"
                    :class="activeTab === 'requests' ? 'bg-blue-600 text-white' : 'bg-gray-800/50 text-gray-400 hover:text-white hover:bg-gray-700/50'"
                    class="px-5 py-2.5 rounded-lg font-semibold text-sm transition-all"
                >
                    Requests
                </button>
                <button
                    @click="switchTab('offers')"
                    :class="activeTab === 'offers' ? 'bg-blue-600 text-white' : 'bg-gray-800/50 text-gray-400 hover:text-white hover:bg-gray-700/50'"
                    class="px-5 py-2.5 rounded-lg font-semibold text-sm transition-all"
                >
                    Offers
                </button>
            </div>

            <!-- Filters -->
            <div class="mb-6 bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4 relative z-20">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input
                        v-model="search"
                        @input="applyFilters"
                        type="text"
                        placeholder="Search by title..."
                        class="bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                    />

                    <!-- Work Type Dropdown -->
                    <div class="relative">
                        <button
                            @click="workTypeDropdownOpen = !workTypeDropdownOpen"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors"
                        >
                            <span>{{ selectedWorkTypeLabel() }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="workTypeDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="workTypeDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="wt in workTypes"
                                :key="wt.value"
                                @click="selectWorkType(wt.value)"
                                :class="workTypeFilter === wt.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                            >
                                {{ wt.label }}
                            </button>
                        </div>
                        <div v-if="workTypeDropdownOpen" @click="workTypeDropdownOpen = false" class="fixed inset-0 z-40"></div>
                    </div>

                    <!-- Status Dropdown -->
                    <div class="relative">
                        <button
                            @click="statusDropdownOpen = !statusDropdownOpen"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors"
                        >
                            <span>{{ selectedStatusLabel() }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="statusDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="statusDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="s in statuses"
                                :key="s.value"
                                @click="selectStatus(s.value)"
                                :class="statusFilter === s.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <div v-if="statusDropdownOpen" @click="statusDropdownOpen = false" class="fixed inset-0 z-40"></div>
                    </div>
                </div>
            </div>

            <!-- Loading skeleton -->
            <div v-if="!listingsLoaded" class="space-y-4">
                <div v-for="i in 6" :key="i" class="bg-black/40 rounded-xl p-5 border border-white/10 animate-pulse">
                    <div class="h-5 bg-white/10 rounded w-2/3 mb-3"></div>
                    <div class="h-4 bg-white/5 rounded w-full mb-2"></div>
                    <div class="h-4 bg-white/5 rounded w-1/2"></div>
                </div>
            </div>

            <!-- Listings -->
            <div v-else-if="listings" class="space-y-4">
                <ListingCard
                    v-if="listings"
                    v-for="listing in listings.data"
                    :key="listing.id"
                    :listing="listing"
                />

                <!-- Empty State -->
                <div v-if="listings && listings.data.length === 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <h3 class="text-xl font-bold text-white mb-2">No Listings Found</h3>
                    <p class="text-gray-400">Be the first to create a {{ activeTab === 'offers' ? 'service offer' : 'commission request' }}!</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="listings && listings.data.length > 0" class="mt-6 flex justify-center">
                <nav class="flex items-center gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in listings.links"
                        :key="link.label"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all',
                            link.active
                                ? 'bg-blue-600 text-white'
                                : link.url
                                ? 'bg-gray-800/50 text-gray-300 hover:bg-gray-700/50'
                                : 'bg-gray-800/30 text-gray-600 cursor-not-allowed'
                        ]"
                    />
                </nav>
            </div>
        </div>
    </div>
</template>
