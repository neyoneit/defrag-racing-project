<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MaplistCard from '@/Components/Maplists/MaplistCard.vue';
import Pagination from '@/Components/Basic/Pagination.vue';
import DialogModal from '@/Components/Laravel/DialogModal.vue';
import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';

import axios from 'axios';

const page = usePage();

const props = defineProps({
    maplists: Object,
    myMaplists: {
        type: Array,
        default: () => []
    },
    myFavoriteMaplists: {
        type: Array,
        default: () => []
    },
    myLikedMaplists: {
        type: Array,
        default: () => []
    },
    myPlayLater: {
        type: Object,
        default: null
    },
    sort: {
        type: String,
        default: 'likes'
    },
    view: {
        type: String,
        default: 'public'
    },
    search: {
        type: String,
        default: ''
    },
    authors: {
        type: String,
        default: ''
    },
    availableAuthors: {
        type: Object,
        default: () => ({})
    },
});

const currentSort = ref(props.sort);
const currentView = ref(props.view);
const showCreateModal = ref(false);
const showCloseConfirm = ref(false);
const newMaplistName = ref('');
const newMaplistDescription = ref('');
const newMaplistMaps = ref('');
const creating = ref(false);
const validationErrors = ref([]);
const draftId = ref(null);
const autoSaveInterval = ref(null);
const hasUnsavedChanges = ref(false);
const drafts = ref([]);

// Search input
const searchQuery = ref(props.search || '');
const showMobileFilters = ref(false);
let searchDebounce = null;

// Author dropdown
const authorDropdownOpen = ref(false);
const authorSearchInput = ref('');
const selectedAuthors = ref(props.authors ? props.authors.split(',') : []);

const sortOptions = [
    { value: 'likes', label: 'Most Liked', icon: 'heart' },
    { value: 'favorites', label: 'Most Favorited', icon: 'star' },
    { value: 'most_views', label: 'Most Views', icon: 'eye' },
    { value: 'most_maps', label: 'Most Maps', icon: 'map' },
    { value: 'least_maps', label: 'Least Maps', icon: 'map-minus' },
    { value: 'newest', label: 'Newest', icon: 'clock' },
    { value: 'oldest', label: 'Oldest', icon: 'clock-old' },
];

const viewOptions = [
    { value: 'public', label: 'Public' },
    { value: 'mine', label: 'My Maplists' },
    { value: 'favorites', label: 'My Favourites' },
    { value: 'likes', label: 'My Likes' },
];

const filteredAuthors = computed(() => {
    if (!props.availableAuthors) return [];
    const entries = Object.entries(props.availableAuthors);
    if (!authorSearchInput.value) return entries;
    const q = authorSearchInput.value.toLowerCase();
    return entries.filter(([, data]) => data.name.toLowerCase().includes(q));
});

// Fetch drafts on mount if logged in
onMounted(async () => {
    if (page.props.auth.user) {
        try {
            const response = await axios.get('/api/maplists/drafts');
            drafts.value = response.data.drafts || [];
        } catch (error) {
            console.error('Error fetching drafts:', error);
        }
    }
});

const applyFilters = (updates = {}) => {
    const params = {
        sort: updates.sort !== undefined ? updates.sort : currentSort.value,
        view: updates.view !== undefined ? updates.view : currentView.value,
        search: updates.search !== undefined ? updates.search : searchQuery.value,
        authors: updates.authors !== undefined ? updates.authors : (selectedAuthors.value.length ? selectedAuthors.value.join(',') : null),
    };

    // Clean null/empty params
    Object.keys(params).forEach(key => {
        if (!params[key]) delete params[key];
    });

    router.get('/maplists', params, { preserveState: true, preserveScroll: true });
};

const changeSort = (newSort) => {
    currentSort.value = newSort;
    applyFilters({ sort: newSort });
};

const changeView = (newView) => {
    currentView.value = newView;
    applyFilters({ view: newView });
};

const onSearchInput = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters({ search: searchQuery.value || null });
    }, 400);
};

const toggleAuthor = (id) => {
    const idx = selectedAuthors.value.indexOf(id);
    if (idx >= 0) {
        selectedAuthors.value.splice(idx, 1);
    } else {
        selectedAuthors.value.push(id);
    }
    applyFilters({ authors: selectedAuthors.value.length ? selectedAuthors.value.join(',') : null });
};

const clearAuthors = () => {
    selectedAuthors.value = [];
    applyFilters({ authors: null });
};

// Close dropdowns when clicking outside
const handleClickOutside = (e) => {
    if (!e.target.closest('.author-dropdown')) authorDropdownOpen.value = false;
};
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));


// Check if any filters are active
const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedAuthors.value.length > 0;
});

// Get author name by id
const getAuthorName = (id) => {
    const author = props.availableAuthors?.[id];
    return author ? author.name : id;
};

// Watch for changes to mark as unsaved
watch([newMaplistName, newMaplistDescription, newMaplistMaps], () => {
    if (showCreateModal.value) {
        hasUnsavedChanges.value = true;
    }
});

// Auto-save draft every minute
const saveDraft = async () => {
    if (!hasUnsavedChanges.value) return;

    try {
        const response = await axios.post('/api/maplists/save-draft', {
            id: draftId.value,
            name: newMaplistName.value || 'Untitled Draft',
            description: newMaplistDescription.value,
            map_names: newMaplistMaps.value,
        });

        if (response.data.maplist) {
            draftId.value = response.data.maplist.id;
            hasUnsavedChanges.value = false;
        }
    } catch (error) {
        console.error('Error saving draft:', error);
    }
};

const startAutoSave = () => {
    if (newMaplistName.value || newMaplistDescription.value || newMaplistMaps.value) {
        saveDraft();
    }
    autoSaveInterval.value = setInterval(saveDraft, 60000);
};

const stopAutoSave = () => {
    if (autoSaveInterval.value) {
        clearInterval(autoSaveInterval.value);
        autoSaveInterval.value = null;
    }
};

const openCreateModal = () => {
    newMaplistName.value = '';
    newMaplistDescription.value = '';
    newMaplistMaps.value = '';
    validationErrors.value = [];
    draftId.value = null;
    hasUnsavedChanges.value = false;
    showCreateModal.value = true;
    startAutoSave();
};

const attemptCloseModal = () => {
    if (hasUnsavedChanges.value && (newMaplistName.value || newMaplistDescription.value || newMaplistMaps.value)) {
        showCloseConfirm.value = true;
    } else {
        closeModal();
    }
};

const closeModal = () => {
    stopAutoSave();
    showCreateModal.value = false;
    showCloseConfirm.value = false;
    hasUnsavedChanges.value = false;
};

const confirmClose = () => {
    showCloseConfirm.value = false;
    closeModal();
};

onUnmounted(() => {
    stopAutoSave();
});

const loadDraft = (draft) => {
    newMaplistName.value = draft.name || '';
    newMaplistDescription.value = draft.description || '';
    newMaplistMaps.value = '';
    draftId.value = draft.id;
    validationErrors.value = [];
    hasUnsavedChanges.value = false;
    showCreateModal.value = true;
    startAutoSave();
};

const deleteDraftItem = async (id) => {
    if (!confirm('Are you sure you want to delete this draft?')) return;

    try {
        await axios.delete(`/api/maplists/draft/${id}`);
        drafts.value = drafts.value.filter(d => d.id !== id);
    } catch (error) {
        console.error('Error deleting draft:', error);
        alert('Failed to delete draft');
    }
};

const createMaplist = async () => {
    if (!newMaplistName.value.trim()) {
        alert('Please enter a maplist name');
        return;
    }

    validationErrors.value = [];

    try {
        creating.value = true;

        const mapNames = newMaplistMaps.value
            .split('\n')
            .map(name => name.trim())
            .filter(name => name.length > 0);

        const response = await axios.post('/api/maplists/create-with-maps', {
            name: newMaplistName.value,
            description: newMaplistDescription.value,
            is_public: true,
            map_names: mapNames
        });

        if (draftId.value) {
            try {
                await axios.delete(`/api/maplists/draft/${draftId.value}`);
            } catch (err) {
                console.error('Error deleting draft:', err);
            }
        }

        stopAutoSave();
        showCreateModal.value = false;
        hasUnsavedChanges.value = false;
        router.visit(`/maplists/${response.data.maplist.id}`);
    } catch (error) {
        console.error('Error creating maplist:', error);

        if (error.response?.data?.errors) {
            validationErrors.value = error.response.data.errors;
        } else if (error.response?.data?.error) {
            alert(error.response.data.error);
        } else {
            alert('Failed to create maplist');
        }
    } finally {
        creating.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen">
        <Head title="Maplists" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-28">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <!-- Breadcrumb (only show on filtered views) -->
                <div v-if="currentView !== 'public'" class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                    <Link href="/maplists" class="hover:text-white transition">Maplists</Link>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-white">
                        <template v-if="currentView === 'mine'">My Maplists</template>
                        <template v-else-if="currentView === 'favorites'">My Favourites</template>
                        <template v-else-if="currentView === 'likes'">My Likes</template>
                    </span>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">
                            <template v-if="currentView === 'mine'">My Maplists</template>
                            <template v-else-if="currentView === 'favorites'">My Favourites</template>
                            <template v-else-if="currentView === 'likes'">My Likes</template>
                            <template v-else>Maplists</template>
                            <span v-if="maplists?.total != null" class="text-lg font-semibold text-gray-500 align-middle ml-2">{{ maplists.total }} found</span>
                        </h1>
                        <p class="text-gray-400">
                            <template v-if="currentView === 'mine'">Manage and organize your personal map collections.</template>
                            <template v-else-if="currentView === 'favorites'">Your favorited maplists from the community.</template>
                            <template v-else-if="currentView === 'likes'">Your liked maplists from the community.</template>
                            <template v-else>Discover community-curated collections of maps. Like playlists for your favorite maps!</template>
                        </p>
                    </div>
                    <!-- Create Maplist Button -->
                    <button v-if="page.props.auth.user" @click="openCreateModal" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Maplist
                    </button>
                    <a v-else :href="route('login')" class="px-4 py-2 bg-gray-700/50 border border-white/10 text-gray-400 rounded-lg font-semibold transition-all flex items-center gap-2 hover:bg-gray-600/50 hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        Log in to create maplists
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content with Sidebar -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-6 -mt-24 relative z-10">
            <div class="lg:flex lg:gap-6">
                <!-- Left Sidebar - Filters -->
                <!-- Mobile filter toggle -->
                <button @click="showMobileFilters = !showMobileFilters"
                    class="lg:hidden w-full flex items-center justify-between px-4 py-3 mb-4 bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 text-sm font-bold text-gray-300 hover:text-white transition">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
                        Filters
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="showMobileFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>

                <div :class="showMobileFilters ? 'block mb-4' : 'hidden'" class="lg:block lg:w-56 lg:mb-0 lg:sticky lg:top-[120px] lg:self-start" style="height: fit-content; max-height: calc(100vh - 136px); overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.06) transparent;">
                    <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] divide-y divide-white/5">
                        <!-- Search -->
                        <div class="px-3 py-2.5">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Search maplists..."
                                class="w-full bg-white/5 border border-white/10 rounded-md px-2.5 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                        </div>

                        <!-- Sort Options -->
                        <div class="px-3 py-2.5">
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Sort</div>
                            <div class="space-y-0.5">
                                <button
                                    v-for="option in sortOptions"
                                    :key="option.value"
                                    @click="changeSort(option.value)"
                                    :class="[
                                        'w-full text-left px-2.5 py-1.5 rounded-md font-medium transition-all text-xs',
                                        currentSort === option.value
                                            ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                            : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                    ]">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <!-- View Filter (only for logged in users) -->
                        <div v-if="page.props.auth.user" class="px-3 py-2.5">
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">View</div>
                            <div class="space-y-0.5">
                                <button
                                    v-for="option in viewOptions"
                                    :key="option.value"
                                    @click="changeView(option.value)"
                                    :class="[
                                        'w-full text-left px-2.5 py-1.5 rounded-md font-medium transition-all text-xs',
                                        currentView === option.value
                                            ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                            : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                    ]">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Author Filter -->
                        <div class="px-3 py-2.5 author-dropdown">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Author</div>
                                <span v-if="selectedAuthors.length" class="text-[10px] bg-blue-500/30 text-blue-300 px-1.5 py-0.5 rounded-full font-bold">{{ selectedAuthors.length }}</span>
                            </div>
                            <button
                                @click.stop="authorDropdownOpen = !authorDropdownOpen"
                                class="w-full flex items-center justify-between px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-gray-400 hover:border-white/20 transition-colors"
                            >
                                <span v-if="selectedAuthors.length" class="text-white truncate">{{ selectedAuthors.map(id => getAuthorName(id)).join(', ') }}</span>
                                <span v-else>Select authors...</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0 transition-transform" :class="{ 'rotate-180': authorDropdownOpen }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <!-- Dropdown panel -->
                            <div v-if="authorDropdownOpen" class="mt-1.5 bg-gray-900/95 border border-white/10 rounded-lg shadow-xl max-h-48 overflow-hidden flex flex-col">
                                <input
                                    v-model="authorSearchInput"
                                    type="text"
                                    placeholder="Filter..."
                                    class="w-full bg-transparent border-b border-white/10 px-2.5 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none"
                                    @click.stop
                                />
                                <div class="overflow-y-auto flex-1">
                                    <label
                                        v-for="[id, data] in filteredAuthors"
                                        :key="id"
                                        class="flex items-center gap-2 px-2.5 py-1 hover:bg-white/5 cursor-pointer text-xs"
                                        @click.stop
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedAuthors.includes(id)"
                                            @change="toggleAuthor(id)"
                                            class="rounded bg-white/10 border-white/20 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0 w-3.5 h-3.5"
                                        />
                                        <span class="text-gray-300 truncate flex-1">{{ data.name }}</span>
                                        <span class="text-gray-600 text-[10px]">{{ data.count }}</span>
                                    </label>
                                    <div v-if="filteredAuthors.length === 0" class="px-3 py-3 text-center text-gray-500 text-xs">No results</div>
                                </div>
                                <button v-if="selectedAuthors.length" @click.stop="clearAuthors" class="w-full px-3 py-1.5 text-[11px] text-red-400 hover:bg-red-500/10 border-t border-white/10 transition-colors">
                                    Clear selection
                                </button>
                            </div>
                        </div>

                        <!-- Quick Access (logged in, public view only) -->
                        <div v-if="page.props.auth.user && currentView === 'public' && myPlayLater" class="px-3 py-2.5">
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Quick Access</div>
                            <div class="space-y-0.5">
                                <Link href="/maplists/play-later" class="flex items-center justify-between px-2.5 py-1.5 rounded-md bg-white/5 hover:bg-white/10 transition text-xs">
                                    <span class="text-gray-300 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Play Later
                                    </span>
                                    <span class="text-green-400 font-bold text-[10px]">{{ myPlayLater.maps_count || 0 }}</span>
                                </Link>
                                <Link v-if="myMaplists.length > 0" href="/maplists?view=mine" class="flex items-center justify-between px-2.5 py-1.5 rounded-md bg-white/5 hover:bg-white/10 transition text-xs">
                                    <span class="text-gray-300 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                        </svg>
                                        My Maplists
                                    </span>
                                    <span class="text-blue-400 font-bold text-[10px]">{{ myMaplists.length }}</span>
                                </Link>
                                <Link v-if="myFavoriteMaplists.length > 0" href="/maplists?view=favorites" class="flex items-center justify-between px-2.5 py-1.5 rounded-md bg-white/5 hover:bg-white/10 transition text-xs">
                                    <span class="text-gray-300 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        Favourites
                                    </span>
                                    <span class="text-yellow-400 font-bold text-[10px]">{{ myFavoriteMaplists.length }}</span>
                                </Link>
                                <Link v-if="myLikedMaplists.length > 0" href="/maplists?view=likes" class="flex items-center justify-between px-2.5 py-1.5 rounded-md bg-white/5 hover:bg-white/10 transition text-xs">
                                    <span class="text-gray-300 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                        </svg>
                                        Likes
                                    </span>
                                    <span class="text-red-400 font-bold text-[10px]">{{ myLikedMaplists.length }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <main class="flex-1">
                    <!-- Active Filters -->
                    <div v-if="hasActiveFilters" class="mb-6 flex flex-wrap gap-2">
                        <div v-if="searchQuery" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/20 border border-blue-500/30 rounded-lg text-sm text-blue-300">
                            <span class="text-xs text-blue-400">Search:</span> {{ searchQuery }}
                            <button @click="searchQuery = ''; applyFilters({ search: null })" class="text-blue-400 hover:text-blue-200 ml-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div v-for="authorId in selectedAuthors" :key="'au-'+authorId" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-500/20 border border-purple-500/30 rounded-lg text-sm text-purple-300">
                            <span class="text-xs text-purple-400">Author:</span> {{ getAuthorName(authorId) }}
                            <button @click="toggleAuthor(authorId)" class="text-purple-400 hover:text-purple-200 ml-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Drafts Section (only show if user has drafts) -->
                    <div v-if="page.props.auth.user && drafts.length > 0" class="mb-8">
                        <h2 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-yellow-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            Your Drafts
                            <span class="text-sm font-normal text-gray-400">({{ drafts.length }})</span>
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="draft in drafts" :key="draft.id" class="bg-gray-800/90 border border-yellow-500/30 rounded-lg p-4 hover:bg-gray-700/90 transition-all">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-bold text-white">{{ draft.name }}</h3>
                                    <span class="px-2 py-0.5 bg-yellow-600/20 text-yellow-400 text-xs rounded-full">Draft</span>
                                </div>
                                <p v-if="draft.description" class="text-sm text-gray-400 mb-4 line-clamp-2">{{ draft.description }}</p>
                                <div class="flex gap-2">
                                    <button @click="loadDraft(draft)" class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg font-semibold transition">
                                        Continue Editing
                                    </button>
                                    <button @click="deleteDraftItem(draft.id)" class="px-3 py-2 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-sm rounded-lg font-semibold transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Last edited: {{ new Date(draft.updated_at).toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Maplists Grid -->
                    <div v-if="maplists.data && maplists.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <MaplistCard v-for="maplist in maplists.data" :maplist="maplist" :key="maplist.id" />
                    </div>


                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4 opacity-40">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                        <p class="font-semibold text-lg mb-2">No maplists found</p>
                        <p class="text-sm mb-4">
                            <template v-if="hasActiveFilters">Try adjusting your search or filters.</template>
                            <template v-else>Be the first to create a maplist!</template>
                        </p>
                        <Link v-if="!hasActiveFilters" :href="'/maps'" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                            Browse Maps
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div v-if="maplists.last_page > 1" class="flex justify-center mt-8">
                        <Pagination :current_page="maplists.current_page" :last_page="maplists.last_page" :link="maplists.first_page_url" />
                    </div>
                </main>
            </div>
        </div>

        <!-- Create Maplist Modal -->
        <DialogModal :show="showCreateModal" @close="attemptCloseModal" max-width="lg">
            <template #title>
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Create New Maplist</span>
                </div>
            </template>

            <template #content>
                <div class="space-y-4">
                    <div>
                        <label for="maplist_name" class="block text-xs font-medium text-gray-400 mb-1">Maplist Name</label>
                        <input
                            id="maplist_name"
                            v-model="newMaplistName"
                            type="text"
                            class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500"
                            placeholder="e.g., My Favorite Maps"
                        />
                    </div>

                    <div>
                        <label for="maplist_description" class="block text-xs font-medium text-gray-400 mb-1">Description (Optional)</label>
                        <textarea
                            id="maplist_description"
                            v-model="newMaplistDescription"
                            rows="3"
                            class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500"
                            placeholder="Describe your maplist..."
                        ></textarea>
                    </div>

                    <div>
                        <label for="maplist_maps" class="block text-xs font-medium text-gray-400 mb-1">Maps (Optional - One per line)</label>
                        <textarea
                            id="maplist_maps"
                            v-model="newMaplistMaps"
                            rows="6"
                            class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 font-mono"
                            placeholder="skatepark&#10;temple&#10;rust3dm1&#10;..."
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500">Enter map names, one per line. Exact names required.</p>
                    </div>

                    <!-- Validation Errors -->
                    <div v-if="validationErrors.length > 0" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                        <h4 class="font-medium text-red-400 mb-2 text-sm">Cannot create maplist - Map issues found:</h4>
                        <div class="space-y-2">
                            <div v-for="(error, index) in validationErrors" :key="index" class="text-sm">
                                <p class="text-red-300 font-medium">{{ error.map_name }}</p>
                                <p class="text-gray-400 ml-4 text-xs">{{ error.message }}</p>
                                <div v-if="error.suggestions && error.suggestions.length > 0" class="ml-4 mt-1">
                                    <p class="text-yellow-400 text-xs">Did you mean:</p>
                                    <ul class="list-disc list-inside text-gray-400 text-xs">
                                        <li v-for="suggestion in error.suggestions" :key="suggestion">{{ suggestion }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="attemptCloseModal"
                        class="px-4 py-2 bg-white/5 hover:bg-white/10 text-gray-300 rounded-lg font-medium transition text-sm border border-white/5">
                        Cancel
                    </button>

                    <button
                        @click="createMaplist"
                        :disabled="creating"
                        class="px-5 py-2 bg-green-600 hover:bg-green-500 disabled:bg-gray-700 disabled:cursor-not-allowed text-white rounded-lg font-medium transition text-sm">
                        {{ creating ? 'Creating...' : 'Create Maplist' }}
                    </button>
                </div>
            </template>
        </DialogModal>

        <!-- Confirmation Modal for Closing -->
        <ConfirmationModal :show="showCloseConfirm" @close="showCloseConfirm = false">
            <template #title>
                Exit Maplist Creation?
            </template>

            <template #content>
                Are you sure you want to exit maplist creation? Your progress has been auto-saved as a draft and you can continue editing it later.
            </template>

            <template #footer>
                <button
                    @click="showCloseConfirm = false"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    Continue Editing
                </button>

                <button
                    @click="confirmClose"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                    Exit
                </button>
            </template>
        </ConfirmationModal>
    </div>
</template>
