<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MaplistCard from '@/Components/Maplists/MaplistCard.vue';
import DialogModal from '@/Components/Laravel/DialogModal.vue';
import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
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
    }
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

const changeSort = (newSort) => {
    currentSort.value = newSort;
    router.get('/maplists', { sort: newSort, view: currentView.value }, { preserveState: true });
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
            console.log('Draft auto-saved');
        }
    } catch (error) {
        console.error('Error saving draft:', error);
    }
};

const startAutoSave = () => {
    // Save immediately when modal opens if there's content
    if (newMaplistName.value || newMaplistDescription.value || newMaplistMaps.value) {
        saveDraft();
    }

    // Then save every 60 seconds
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
    newMaplistMaps.value = '';  // Map names aren't stored in drafts yet
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

        // Parse map names from textarea (one per line)
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

        // Delete the draft if it exists
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
        console.log('Error response data:', error.response?.data);

        if (error.response?.data?.errors) {
            validationErrors.value = error.response.data.errors;
            console.log('Validation errors set to:', validationErrors.value);
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
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-64">
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
                    <div class="flex items-center gap-4">
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">
                            <template v-if="currentView === 'mine'">My Maplists</template>
                            <template v-else-if="currentView === 'favorites'">My Favourites</template>
                            <template v-else-if="currentView === 'likes'">My Likes</template>
                            <template v-else>Maplists</template>
                        </h1>
                        <span class="text-sm text-gray-400">{{ maplists.total }} total</span>
                    </div>
                    <!-- Create Maplist Button -->
                    <button v-if="page.props.auth.user" @click="openCreateModal" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Maplist
                    </button>
                </div>

                <!-- Description -->
                <p class="text-gray-300 text-lg mb-6 max-w-3xl">
                    <template v-if="currentView === 'mine'">Manage and organize your personal map collections.</template>
                    <template v-else-if="currentView === 'favorites'">Your favorited maplists from the community.</template>
                    <template v-else-if="currentView === 'likes'">Your liked maplists from the community.</template>
                    <template v-else>Discover community-curated collections of maps. Like playlists for your favorite maps!</template>
                </p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-6" style="margin-top: -18rem;">
            <!-- Quick Access Buttons (only on public view) -->
            <div v-if="page.props.auth.user && currentView === 'public'" class="mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Play Later Button -->
                    <Link v-if="myPlayLater" href="/maplists/play-later" class="backdrop-blur-xl bg-gradient-to-br from-green-600/20 to-green-800/20 border border-green-500/30 rounded-lg p-3 hover:from-green-600/30 hover:to-green-800/30 hover:border-green-500/50 transition-all group">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-base font-bold text-white">Play Later</h3>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <p class="text-lg font-black text-green-400">{{ myPlayLater.maps_count || 0 }}</p>
                                <p class="text-xs text-gray-400">maps</p>
                            </div>
                        </div>
                    </Link>

                    <!-- My Favourites Button -->
                    <Link v-if="myFavoriteMaplists.length > 0" href="/maplists?view=favorites" class="backdrop-blur-xl bg-gradient-to-br from-yellow-600/20 to-yellow-800/20 border border-yellow-500/30 rounded-lg p-3 hover:from-yellow-600/30 hover:to-yellow-800/30 hover:border-yellow-500/50 transition-all group">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <h3 class="text-base font-bold text-white">My Favourites</h3>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <p class="text-lg font-black text-yellow-400">{{ myFavoriteMaplists.length }}</p>
                                <p class="text-xs text-gray-400">lists</p>
                            </div>
                        </div>
                    </Link>

                    <!-- My Likes Button -->
                    <Link v-if="myLikedMaplists.length > 0" href="/maplists?view=likes" class="backdrop-blur-xl bg-gradient-to-br from-red-600/20 to-red-800/20 border border-red-500/30 rounded-lg p-3 hover:from-red-600/30 hover:to-red-800/30 hover:border-red-500/50 transition-all group">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                                <h3 class="text-base font-bold text-white">My Likes</h3>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <p class="text-lg font-black text-red-400">{{ myLikedMaplists.length }}</p>
                                <p class="text-xs text-gray-400">lists</p>
                            </div>
                        </div>
                    </Link>

                    <!-- My Maplists Button -->
                    <Link v-if="myMaplists.length > 0" href="/maplists?view=mine" class="backdrop-blur-xl bg-gradient-to-br from-blue-600/20 to-blue-800/20 border border-blue-500/30 rounded-lg p-3 hover:from-blue-600/30 hover:to-blue-800/30 hover:border-blue-500/50 transition-all group">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                </svg>
                                <h3 class="text-base font-bold text-white">My Maplists</h3>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <p class="text-lg font-black text-blue-400">{{ myMaplists.length }}</p>
                                <p class="text-xs text-gray-400">lists</p>
                            </div>
                        </div>
                    </Link>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div v-for="draft in drafts" :key="draft.id" class="backdrop-blur-xl bg-gray-800/90 border border-yellow-500/30 rounded-lg p-4 hover:bg-gray-700/90 transition-all">
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

            <!-- Maplists Section Title (only show on public view) -->
            <div v-if="maplists.data && maplists.data.length > 0 && currentView === 'public'" class="mb-6">
                <div class="backdrop-blur-xl rounded-lg p-4 border bg-gradient-to-r from-purple-600/20 to-purple-800/20 border-purple-500/30">
                    <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-purple-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                        Public Maplists
                    </h2>
                </div>
            </div>

            <!-- Sort Tabs (show for all views) -->
            <div class="mb-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="changeSort('likes')"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all backdrop-blur-xl',
                            currentSort === 'likes'
                                ? 'bg-red-600 text-white'
                                : 'bg-gray-800/90 text-gray-300 hover:bg-gray-700/90 border border-white/10'
                        ]">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                            </svg>
                            Most Liked
                        </div>
                    </button>
                    <button
                        @click="changeSort('favorites')"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all backdrop-blur-xl',
                            currentSort === 'favorites'
                                ? 'bg-yellow-600 text-white'
                                : 'bg-gray-800/90 text-gray-300 hover:bg-gray-700/90 border border-white/10'
                        ]">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            Most Favorited
                        </div>
                    </button>
                    <button
                        @click="changeSort('newest')"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all backdrop-blur-xl',
                            currentSort === 'newest'
                                ? 'bg-green-600 text-white'
                                : 'bg-gray-800/90 text-gray-300 hover:bg-gray-700/90 border border-white/10'
                        ]">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Newest
                        </div>
                    </button>
                    <button
                        @click="changeSort('oldest')"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all backdrop-blur-xl',
                            currentSort === 'oldest'
                                ? 'bg-purple-600 text-white'
                                : 'bg-gray-800/90 text-gray-300 hover:bg-gray-700/90 border border-white/10'
                        ]">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Oldest
                        </div>
                    </button>
                </div>
            </div>

            <!-- Maplists Grid -->
            <div v-if="maplists.data && maplists.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <MaplistCard v-for="maplist in maplists.data" :maplist="maplist" :key="maplist.id" />
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4 opacity-40">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                </svg>
                <p class="font-semibold text-lg mb-2">No maplists yet</p>
                <p class="text-sm mb-4">Be the first to create a maplist!</p>
                <Link :href="'/maps'" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                    Browse Maps
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="maplists.total > maplists.per_page" class="flex justify-center mt-8 gap-2">
                <Link
                    v-for="page in Array.from({ length: maplists.last_page }, (_, i) => i + 1)"
                    :key="page"
                    :href="`/maplists?page=${page}&sort=${currentSort}&view=${currentView}`"
                    :class="[
                        'px-4 py-2 rounded-lg font-semibold transition',
                        page === maplists.current_page
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
                    ]">
                    {{ page }}
                </Link>
            </div>
        </div>

        <!-- Create Maplist Modal -->
        <DialogModal :show="showCreateModal" @close="attemptCloseModal">
            <template #title>
                Create New Maplist
            </template>

            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="maplist_name" value="Maplist Name" />
                        <TextInput
                            id="maplist_name"
                            v-model="newMaplistName"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., My Favorite Maps"
                        />
                    </div>

                    <div>
                        <InputLabel for="maplist_description" value="Description (Optional)" />
                        <textarea
                            id="maplist_description"
                            v-model="newMaplistDescription"
                            rows="3"
                            class="mt-1 block w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:outline-none focus:border-purple-500/50 focus:bg-white/10 transition-all"
                            placeholder="Describe your maplist..."
                        ></textarea>
                    </div>

                    <div>
                        <InputLabel for="maplist_maps" value="Maps (Optional - One per line)" />
                        <textarea
                            id="maplist_maps"
                            v-model="newMaplistMaps"
                            rows="8"
                            class="mt-1 block w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:outline-none focus:border-purple-500/50 focus:bg-white/10 transition-all font-mono"
                            placeholder="skatepark&#10;temple&#10;rust3dm1&#10;..."
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-400">Enter map names, one per line. Exact names required.</p>
                    </div>

                    <!-- Validation Errors -->
                    <div v-if="validationErrors.length > 0" class="bg-red-600/20 border border-red-500/50 rounded-lg p-4">
                        <h4 class="font-bold text-red-400 mb-2">Cannot create maplist - Map issues found:</h4>
                        <div class="space-y-2">
                            <div v-for="(error, index) in validationErrors" :key="index" class="text-sm">
                                <p class="text-red-300 font-semibold">❌ {{ error.map_name }}</p>
                                <p class="text-gray-300 ml-4">{{ error.message }}</p>
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
                <button
                    @click="attemptCloseModal"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>

                <button
                    @click="createMaplist"
                    :disabled="creating"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg font-semibold transition">
                    {{ creating ? 'Creating...' : 'Create Maplist' }}
                </button>
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
