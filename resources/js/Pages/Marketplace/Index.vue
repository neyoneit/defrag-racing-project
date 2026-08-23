<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import ListingCard from '@/Components/Marketplace/ListingCard.vue';
import CreateListingModal from '@/Components/Marketplace/CreateListingModal.vue';
import { t } from '@/utils/i18n';

const props = defineProps({
    listings: Object,
    counts: { type: Object, default: () => ({ requests: 0, offers: 0 }) },
    filters: Object,
    canPost: Boolean,
    workTypes: { type: Array, default: () => [] },
    openCreate: Boolean,
});

// Posting used to be its own page. /marketplace/listing/create still works and
// now lands here with the dialog already open.
const showCreate = ref(props.openCreate === true);

const activeTab = ref(props.filters?.tab || 'requests');
const search = ref(props.filters?.search || '');
const workTypeFilter = ref(props.filters?.work_type || '');
const statusFilter = ref(props.filters?.status || 'active');
const workTypeDropdownOpen = ref(false);
const statusDropdownOpen = ref(false);

// The types themselves come from the server already translated; only the
// "everything" row is ours.
const workTypeOptions = computed(() => [
    { value: '', label: t('All Types') },
    ...props.workTypes.map((wt) => ({ value: wt.value, label: wt.label })),
]);

// "Active" is the default and the only entry covering more than one state.
// A board led by cancelled work reads as a dead board, and every state stays
// reachable, so nothing is hidden - only put behind a choice.
const statuses = computed(() => [
    { value: 'active', label: t('Active') },
    { value: 'open', label: t('Open') },
    { value: 'in_progress', label: t('In Progress') },
    { value: 'completed', label: t('Completed') },
    { value: 'cancelled', label: t('Cancelled') },
    { value: 'all', label: t('All Statuses') },
]);

const selectedWorkTypeLabel = computed(() =>
    workTypeOptions.value.find(w => w.value === workTypeFilter.value)?.label || t('All Types'));

const selectedStatusLabel = computed(() =>
    statuses.value.find(s => s.value === statusFilter.value)?.label || t('Active'));

// The listings are part of the page now, so a tab or a filter is a plain
// visit. It used to render with none and fetch them on mount, which meant a
// tab switch - not a mount - left the board blank until somebody pressed F5.
const applyFilters = () => {
    router.get(route('marketplace.index'), {
        tab: activeTab.value,
        search: search.value || undefined,
        work_type: workTypeFilter.value || undefined,
        status: statusFilter.value !== 'active' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Typing used to send one whole page visit per keystroke.
let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

const switchTab = (tab) => {
    if (activeTab.value === tab) return;
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

const rows = computed(() => props.listings?.data ?? []);
const isFiltered = computed(() =>
    search.value !== '' || workTypeFilter.value !== '' || statusFilter.value !== 'active');

const clearFilters = () => {
    search.value = '';
    workTypeFilter.value = '';
    statusFilter.value = 'active';
    clearTimeout(searchTimer);
    applyFilters();
};

const tabs = computed(() => [
    { key: 'requests', label: t('Requests'), count: props.counts?.requests ?? 0 },
    { key: 'offers', label: t('Offers'), count: props.counts?.offers ?? 0 },
]);
</script>

<template>
    <div class="pb-8">
        <Head :title="$t('Marketplace')" />

        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <!-- Title and strapline on one line, the way the rest
                             of the site sets a page up. -->
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Marketplace') }}</h1>
                            <p class="text-gray-400 text-sm">{{ $t('Commission maps, models and more from the community') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('marketplace.creators')"
                            class="px-4 py-2.5 bg-gray-700/50 border border-white/10 text-gray-300 font-bold rounded-lg transition-colors hover:bg-gray-600/50 hover:text-white text-sm"
                        >
                            {{ $t('Creator Directory') }}
                        </Link>
                        <Link
                            v-if="$page.props.auth.user && !$page.props.isVerified"
                            href="/email/verify"
                            class="px-5 py-2.5 bg-red-600/80 hover:bg-red-500 text-white font-bold rounded-lg transition-colors text-sm"
                        >
                            {{ $t('Verify Email to Post') }}
                        </Link>
                        <button
                            v-else-if="$page.props.auth.user && canPost"
                            type="button"
                            @click="showCreate = true"
                            class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-colors shadow-lg text-sm"
                        >
                            {{ $t('Create Listing') }}
                        </button>
                        <Link
                            v-else-if="$page.props.auth.user"
                            :href="route('settings.show')"
                            class="px-5 py-2.5 bg-gray-700/50 border border-white/10 text-gray-400 font-bold rounded-lg transition-colors hover:bg-gray-600/50 hover:text-gray-300 flex items-center gap-2 text-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            {{ $t('Link Account to Post') }}
                        </Link>
                        <Link
                            v-else
                            :href="route('login')"
                            class="px-5 py-2.5 bg-gray-700/50 border border-white/10 text-gray-400 font-bold rounded-lg transition-colors hover:bg-gray-600/50 hover:text-gray-300 flex items-center gap-2 text-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            {{ $t('Log in to post') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
            <!-- Disclaimer -->
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-4">
                <div class="flex gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="text-sm text-yellow-300"
                         v-html="$t('<strong>Disclaimer:</strong> Defrag Racing is not responsible for marketplace transactions. All work, payments, and deliverables are agreements between the parties involved. Users who fail to honor agreements may be banned from future marketplace activity.')"></div>
                </div>
            </div>

            <!-- One panel: which board, how it is narrowed, and what is on it.
                 They used to be three boxes stacked down the page, which put
                 the count you were changing out of sight of the control you
                 changed it with. -->
            <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl overflow-hidden">
                <!-- Tabs. The count rides on the tab, so the board you are not
                     looking at still says what is waiting on it. -->
                <div class="flex items-center gap-1 px-3 pt-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        @click="switchTab(tab.key)"
                        :class="activeTab === tab.key
                            ? 'bg-blue-600 text-white'
                            : 'bg-white/[0.04] text-gray-400 hover:text-white hover:bg-white/10'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-sm transition-colors"
                    >
                        {{ tab.label }}
                        <span :class="activeTab === tab.key ? 'bg-black/25 text-white' : 'bg-black/30 text-gray-500'"
                              class="tabular-nums text-[11px] font-black rounded px-1.5 py-0.5">{{ tab.count }}</span>
                    </button>
                </div>

                <!-- Filters -->
                <div class="relative z-30 flex flex-wrap items-center gap-2 px-3 py-3 border-b border-white/5">
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('Search title and description...')"
                        class="flex-1 min-w-[14rem] bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                    />

                    <!-- Work type -->
                    <div class="relative">
                        <button
                            type="button"
                            @click="workTypeDropdownOpen = !workTypeDropdownOpen"
                            class="w-full min-w-[11rem] bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white text-left flex items-center justify-between gap-2 hover:border-white/20 transition-colors"
                        >
                            <span class="truncate">{{ selectedWorkTypeLabel }}</span>
                            <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform" :class="workTypeDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="workTypeDropdownOpen" @click="workTypeDropdownOpen = false" class="fixed inset-0 z-40"></div>
                        <div v-if="workTypeDropdownOpen" class="absolute top-full left-0 right-0 mt-1 max-h-72 overflow-y-auto bg-gray-900 border border-white/10 rounded-lg z-50 shadow-2xl">
                            <button
                                v-for="wt in workTypeOptions"
                                :key="wt.value"
                                type="button"
                                @click="selectWorkType(wt.value)"
                                :class="workTypeFilter === wt.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-3 py-2 text-left text-sm transition-colors"
                            >
                                {{ wt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="relative">
                        <button
                            type="button"
                            @click="statusDropdownOpen = !statusDropdownOpen"
                            class="w-full min-w-[10rem] bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white text-left flex items-center justify-between gap-2 hover:border-white/20 transition-colors"
                        >
                            <span class="truncate">{{ selectedStatusLabel }}</span>
                            <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform" :class="statusDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="statusDropdownOpen" @click="statusDropdownOpen = false" class="fixed inset-0 z-40"></div>
                        <div v-if="statusDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="s in statuses"
                                :key="s.value"
                                type="button"
                                @click="selectStatus(s.value)"
                                :class="statusFilter === s.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-3 py-2 text-left text-sm transition-colors"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                    </div>

                    <button
                        v-if="isFiltered"
                        type="button"
                        @click="clearFilters"
                        class="px-3 py-2 text-sm font-semibold text-gray-400 hover:text-white rounded-lg hover:bg-white/10 transition-colors"
                    >
                        {{ $t('Clear') }}
                    </button>
                </div>

                <!-- Listings -->
                <div v-if="rows.length" class="p-3 space-y-3">
                    <ListingCard
                        v-for="listing in rows"
                        :key="listing.id"
                        :listing="listing"
                    />
                </div>

                <!-- Nothing here. Which of the two reasons it is matters: an
                     empty board asks for a listing, a filter that matched
                     nothing asks to be widened. -->
                <div v-else class="px-6 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 text-gray-600 mx-auto mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <h3 class="text-lg font-bold text-white mb-1">{{ $t('No Listings Found') }}</h3>
                    <p v-if="isFiltered" class="text-gray-400 text-sm">{{ $t('Nothing matches these filters.') }}</p>
                    <p v-else class="text-gray-400 text-sm">
                        {{ activeTab === 'offers' ? $t('Be the first to create a service offer!') : $t('Be the first to create a commission request!') }}
                    </p>
                    <button
                        v-if="isFiltered"
                        type="button"
                        @click="clearFilters"
                        class="mt-4 px-4 py-2 text-sm font-bold text-blue-300 hover:text-blue-200 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 transition-colors"
                    >
                        {{ $t('Clear') }}
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="listings && listings.last_page > 1" class="mt-4 flex justify-center">
                <nav class="flex items-center gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in listings.links"
                        :key="link.label"
                        :href="link.url"
                        preserve-scroll
                        v-html="link.label"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold text-sm transition-colors',
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

        <CreateListingModal
            :show="showCreate"
            :work-types="workTypes"
            @close="showCreate = false"
        />
    </div>
</template>
