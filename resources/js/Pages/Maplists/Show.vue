<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MapCard from '@/Components/MapCard.vue';
import DialogModal from '@/Components/Laravel/DialogModal.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import axios from 'axios';

const props = defineProps({
    maplist: Object,
    is_liked: Boolean,
    is_favorited: Boolean,
    is_owner: Boolean,
    servers: {
        type: Array,
        default: () => []
    }
});

const liked = ref(props.is_liked);
const favorited = ref(props.is_favorited);
const likesCount = ref(props.maplist.likes_count);
const favoritesCount = ref(props.maplist.favorites_count);
const processing = ref(false);
const selectedServer = ref(null);
const isReordering = ref(false);
const maps = ref([...props.maplist.maps]);
const draggedIndex = ref(null);
const showDeleteModal = ref(false);
const deleteConfirmPhrase = ref('');
const deleting = ref(false);
const tags = ref([]);
const allTags = ref([]);
const showAllTags = ref(false);
const newTagInput = ref('');
const addingTag = ref(false);
const tagInputContainer = ref(null);
const tagInput = ref(null);
const isEditing = ref(false);
const editForm = ref({
    name: props.maplist.name,
    description: props.maplist.description || '',
    is_public: props.maplist.is_public
});
const saving = ref(false);
const showPublicConfirmation = ref(false);

const isPlayLater = computed(() => props.maplist.is_play_later);

const dropdownStyle = computed(() => {
    if (!tagInputContainer.value) return {};
    const rect = tagInputContainer.value.getBoundingClientRect();
    return {
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`
    };
});

const toggleLike = async () => {
    if (processing.value || isPlayLater.value) return;

    try {
        processing.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/like`);
        liked.value = response.data.is_liked;
        likesCount.value = response.data.likes_count;
    } catch (error) {
        console.error('Error toggling like:', error);
    } finally {
        processing.value = false;
    }
};

const toggleFavorite = async () => {
    if (processing.value || isPlayLater.value) return;

    try {
        processing.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/favorite`);
        favorited.value = response.data.is_favorited;
        favoritesCount.value = response.data.favorites_count;
    } catch (error) {
        console.error('Error toggling favorite:', error);
    } finally {
        processing.value = false;
    }
};

const openDeleteModal = () => {
    deleteConfirmPhrase.value = '';
    showDeleteModal.value = true;
};

const deleteMaplist = async () => {
    if (deleteConfirmPhrase.value !== 'delete my maplist') {
        alert('Please type "delete my maplist" to confirm deletion');
        return;
    }

    try {
        deleting.value = true;
        await axios.delete(`/api/maplists/${props.maplist.id}`);
        router.visit('/maplists');
    } catch (error) {
        console.error('Error deleting maplist:', error);
        if (error.response?.data?.error) {
            alert(error.response.data.error);
        } else {
            alert('Failed to delete maplist');
        }
    } finally {
        deleting.value = false;
        showDeleteModal.value = false;
    }
};

const removeMap = async (mapId) => {
    if (!confirm('Remove this map from the maplist?')) return;

    try {
        await axios.delete(`/api/maplists/${props.maplist.id}/maps/${mapId}`);
        router.reload();
    } catch (error) {
        console.error('Error removing map:', error);
        alert('Failed to remove map');
    }
};

const connectToServer = (map) => {
    if (!selectedServer.value) {
        alert('Please select a server first');
        return;
    }

    const server = selectedServer.value;
    const callvoteCommand = `callvote map ${map.name}`;

    // Copy callvote command to clipboard
    navigator.clipboard.writeText(callvoteCommand).then(() => {
        // Trigger defrag:// protocol to connect
        window.location.href = `defrag://${server.address}:${server.port}`;
    });
};

const toggleReordering = () => {
    if (isReordering.value) {
        // Cancel reordering - reset to original order
        maps.value = [...props.maplist.maps];
    }
    isReordering.value = !isReordering.value;
};

const saveOrder = async () => {
    try {
        const mapIds = maps.value.map(m => m.id);
        await axios.post(`/api/maplists/${props.maplist.id}/reorder`, { map_ids: mapIds });
        isReordering.value = false;
        router.reload();
    } catch (error) {
        console.error('Error saving order:', error);
        alert('Failed to save order');
    }
};

const onDragStart = (index) => {
    draggedIndex.value = index;
};

const onDragOver = (e, index) => {
    e.preventDefault();
    if (draggedIndex.value === null || draggedIndex.value === index) return;

    const newMaps = [...maps.value];
    const draggedItem = newMaps[draggedIndex.value];
    newMaps.splice(draggedIndex.value, 1);
    newMaps.splice(index, 0, draggedItem);
    maps.value = newMaps;
    draggedIndex.value = index;
};

const onDragEnd = () => {
    draggedIndex.value = null;
};

// Initialize tags from maplist data and fetch all tags
const initializeTags = async () => {
    if (props.maplist.tags) {
        tags.value = props.maplist.tags;
    }

    // Fetch all tags and sort them alphabetically
    try {
        const response = await axios.get('/api/tags');
        allTags.value = response.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        console.error('Error fetching tags:', error);
    }
};

initializeTags();

const addTag = async (tagName) => {
    if (!props.is_owner) {
        alert('Only the owner can add tags');
        return;
    }

    if (addingTag.value) return;

    try {
        addingTag.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/tags`, {
            tag_name: tagName
        });
        tags.value.push(response.data.tag);
        newTagInput.value = '';

        // Refresh all tags list
        const tagsResponse = await axios.get('/api/tags');
        allTags.value = tagsResponse.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        if (error.response?.data?.error) {
            alert(error.response.data.error);
        } else {
            alert('Failed to add tag');
        }
    } finally {
        addingTag.value = false;
    }
};

const removeTag = async (tagId) => {
    if (!props.is_owner) return;

    try {
        await axios.delete(`/api/maplists/${props.maplist.id}/tags/${tagId}`);
        tags.value = tags.value.filter(tag => tag.id !== tagId);

        // Refresh all tags list
        const tagsResponse = await axios.get('/api/tags');
        allTags.value = tagsResponse.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        console.error('Error removing tag:', error);
        alert('Failed to remove tag');
    }
};

const addCustomTag = () => {
    if (newTagInput.value.trim()) {
        addTag(newTagInput.value.trim());
    }
};

const handleTagInputBlur = () => {
    setTimeout(() => {
        showAllTags.value = false;
    }, 200);
};

const handlePublicClick = () => {
    // If already public or trying to make private, just toggle
    if (editForm.value.is_public || !props.maplist.is_public) {
        editForm.value.is_public = true;
        showPublicConfirmation.value = true;
    }
};

const confirmMakePublic = () => {
    editForm.value.is_public = true;
    showPublicConfirmation.value = false;
};

const cancelMakePublic = () => {
    editForm.value.is_public = props.maplist.is_public;
    showPublicConfirmation.value = false;
};

const startEditing = () => {
    editForm.value = {
        name: props.maplist.name,
        description: props.maplist.description || '',
        is_public: props.maplist.is_public
    };
    isEditing.value = true;
};

const cancelEditing = () => {
    isEditing.value = false;
    editForm.value = {
        name: props.maplist.name,
        description: props.maplist.description || '',
        is_public: props.maplist.is_public
    };
};

const saveEdits = async () => {
    if (!editForm.value.name.trim()) {
        alert('Maplist name is required');
        return;
    }

    try {
        saving.value = true;
        await axios.put(`/api/maplists/${props.maplist.id}`, {
            name: editForm.value.name,
            description: editForm.value.description,
            is_public: editForm.value.is_public
        });

        isEditing.value = false;
        router.reload({ only: ['maplist'] });
    } catch (error) {
        console.error('Error saving maplist:', error);
        if (error.response?.data?.message) {
            alert(error.response.data.message);
        } else {
            alert('Failed to save changes');
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen">
        <Head :title="maplist.name" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-20">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                    <Link href="/maplists" class="hover:text-white transition">Maplists</Link>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-white">{{ maplist.name }}</span>
                </div>

                <!-- Maplist Header Card -->
                <div class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-sm rounded-2xl border border-white/20 p-6 md:p-8 shadow-2xl">
                    <!-- Edit Mode -->
                    <div v-if="isEditing">
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">Name</label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="w-full px-5 py-3 bg-black/30 border border-white/20 rounded-xl text-white text-3xl font-black placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                placeholder="Maplist name..." />
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">Description</label>
                            <textarea
                                v-model="editForm.description"
                                rows="4"
                                class="w-full px-5 py-3 bg-black/30 border border-white/20 rounded-xl text-white text-lg placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                placeholder="Description (optional)..." />
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">Visibility</label>
                            <div class="flex gap-4">
                                <button
                                    @click="handlePublicClick"
                                    :class="[
                                        'flex-1 px-5 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2',
                                        editForm.is_public
                                            ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-2 border-green-400'
                                            : 'bg-black/30 text-gray-400 border-2 border-white/20 hover:border-green-400/50'
                                    ]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Public
                                </button>
                                <button
                                    @click="editForm.is_public = false"
                                    :class="[
                                        'flex-1 px-5 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2',
                                        !editForm.is_public
                                            ? 'bg-gradient-to-r from-gray-700 to-gray-600 text-white border-2 border-gray-400'
                                            : 'bg-black/30 text-gray-400 border-2 border-white/20 hover:border-gray-400/50'
                                    ]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Private
                                </button>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="saveEdits"
                                :disabled="saving"
                                class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 disabled:from-gray-700 disabled:to-gray-800 disabled:cursor-not-allowed text-white rounded-xl font-bold shadow-lg transition-all transform hover:scale-105">
                                {{ saving ? 'Saving...' : 'Save Changes' }}
                            </button>
                            <button
                                @click="cancelEditing"
                                :disabled="saving"
                                class="px-6 py-3 bg-white/10 hover:bg-white/20 disabled:cursor-not-allowed text-white rounded-xl font-bold transition-all border border-white/20">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- View Mode -->
                    <div v-else>
                        <!-- Title Row with Actions -->
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <!-- Title Left -->
                            <div class="flex flex-wrap items-center gap-3 flex-1 min-w-0">
                                <h1 class="text-5xl md:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white via-blue-100 to-purple-200 break-words">
                                    {{ maplist.name }}
                                </h1>
                                <div v-if="isPlayLater" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-500 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Play Later
                                </div>
                                <div v-else-if="maplist.is_public" class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-500 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Public
                                </div>
                                <div v-else class="px-4 py-2 bg-gradient-to-r from-gray-700 to-gray-600 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Private
                                </div>
                            </div>

                            <!-- Owner Actions (Edit, Delete, Reorder) - Right Side -->
                            <div v-if="is_owner && !isPlayLater" class="flex flex-col gap-2 flex-shrink-0">
                                <div class="flex gap-2">
                                    <button
                                        @click="startEditing"
                                        class="p-3 text-blue-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-xl transition-all border border-white/10 hover:border-blue-400/50"
                                        title="Edit maplist">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="openDeleteModal"
                                        class="p-3 text-red-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-xl transition-all border border-white/10 hover:border-red-400/50"
                                        title="Delete maplist">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                <!-- Reorder Button -->
                                <button
                                    v-if="!isReordering"
                                    @click="toggleReordering"
                                    class="px-4 py-2 bg-gradient-to-r from-gray-700 to-gray-600 hover:from-gray-600 hover:to-gray-500 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                    </svg>
                                    Reorder
                                </button>
                                <!-- Save/Cancel when reordering -->
                                <template v-else>
                                    <button
                                        @click="saveOrder"
                                        class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-400 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        Save
                                    </button>
                                    <button
                                        @click="toggleReordering"
                                        class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-sm font-bold transition-all border border-white/20">
                                        Cancel
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Stats Cards Row -->
                        <div class="flex flex-wrap gap-4 mb-6">
                            <div class="flex items-center gap-3 px-5 py-3 bg-gradient-to-br from-blue-600/20 to-blue-700/10 rounded-xl border border-blue-500/30 backdrop-blur-sm">
                                <div class="p-2 bg-blue-500/20 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-3xl font-black text-white">{{ maplist.maps?.length || 0 }}</div>
                                    <div class="text-xs text-blue-300 font-semibold uppercase tracking-wider">Maps</div>
                                </div>
                            </div>

                            <div v-if="!isPlayLater" class="flex items-center gap-3 px-5 py-3 bg-gradient-to-br from-red-600/20 to-red-700/10 rounded-xl border border-red-500/30 backdrop-blur-sm">
                                <div class="p-2 bg-red-500/20 rounded-lg">
                                    <svg class="w-6 h-6 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-3xl font-black text-white">{{ likesCount }}</div>
                                    <div class="text-xs text-red-300 font-semibold uppercase tracking-wider">Likes</div>
                                </div>
                            </div>

                            <div v-if="!isPlayLater" class="flex items-center gap-3 px-5 py-3 bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-xl border border-yellow-500/30 backdrop-blur-sm">
                                <div class="p-2 bg-yellow-500/20 rounded-lg">
                                    <svg class="w-6 h-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-3xl font-black text-white">{{ favoritesCount }}</div>
                                    <div class="text-xs text-yellow-300 font-semibold uppercase tracking-wider">Favorites</div>
                                </div>
                            </div>

                            <!-- Creator Info (only for non-Play Later) -->
                            <Link v-if="!isPlayLater" :href="`/profile/${maplist.user?.id}`" class="flex items-center gap-3 px-5 py-3 bg-gradient-to-br from-purple-600/20 to-purple-700/10 rounded-xl border border-purple-500/30 backdrop-blur-sm hover:border-purple-400/50 transition-all group">
                                <img
                                    v-if="maplist.user?.profile_photo_path"
                                    :src="`/storage/${maplist.user.profile_photo_path}`"
                                    :alt="maplist.user?.plain_name"
                                    class="w-10 h-10 rounded-full ring-2 ring-purple-400/20 group-hover:ring-purple-400/40 transition">
                                <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs text-purple-300 font-semibold uppercase tracking-wider">Created by</div>
                                    <div class="font-bold text-white group-hover:text-purple-300 transition">{{ maplist.user?.plain_name || maplist.user?.name || 'Unknown' }}</div>
                                </div>
                            </Link>
                        </div>

                        <!-- Description -->
                        <p v-if="maplist.description" class="text-gray-300 text-xl leading-relaxed mb-6 max-w-4xl">
                            {{ maplist.description }}
                        </p>

                        <!-- Tags Section (for owner to manage) -->
                        <div v-if="is_owner" class="mt-6 pt-6 border-t border-white/10">
                            <div class="mb-3">
                                <span class="text-gray-400 font-bold text-xs uppercase tracking-wider">Manage Tags</span>
                            </div>

                            <!-- Current tags on this maplist -->
                            <div v-if="tags.length > 0" class="mb-4">
                                <div class="text-xs text-gray-500 uppercase mb-2">Current Tags</div>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="tag in tags" :key="tag.id" class="px-3 py-1.5 bg-blue-600 text-white rounded-full text-sm font-semibold flex items-center gap-2">
                                        {{ tag.display_name }}
                                        <button
                                            @click="removeTag(tag.id)"
                                            class="hover:bg-blue-700 rounded-full w-5 h-5 flex items-center justify-center transition"
                                            title="Remove tag">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <!-- Custom tag input -->
                            <div class="relative" ref="tagInputContainer">
                                <div class="flex gap-2">
                                    <input
                                        ref="tagInput"
                                        v-model="newTagInput"
                                        @keyup.enter="addCustomTag"
                                        @focus="showAllTags = true"
                                        @blur="handleTagInputBlur"
                                        type="text"
                                        placeholder="Add custom tag..."
                                        class="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        :disabled="addingTag" />
                                    <button
                                        @click="addCustomTag"
                                        :disabled="!newTagInput.trim() || addingTag"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:text-gray-500 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                                        {{ addingTag ? 'Adding...' : 'Add' }}
                                    </button>
                                </div>

                                <!-- Available tags dropdown (shown only when showAllTags is true) -->
                                <Teleport to="body">
                                    <div
                                        v-if="showAllTags && allTags.length > 0"
                                        :style="dropdownStyle"
                                        class="fixed bg-gray-800 border border-gray-700 rounded-lg shadow-xl p-3 z-[99999]">
                                        <div class="text-xs text-gray-500 uppercase mb-2">Click to add tag</div>
                                        <div class="flex flex-wrap gap-1.5 max-h-64 overflow-y-auto">
                                            <button
                                                v-for="availableTag in allTags.filter(t => !tags.some(tag => tag.id === t.id))"
                                                :key="availableTag.id"
                                                @click="addTag(availableTag.display_name)"
                                                class="px-3 py-1.5 rounded-full text-sm font-semibold transition bg-gray-700 text-gray-300 hover:bg-gray-600">
                                                {{ availableTag.display_name }}
                                            </button>
                                        </div>
                                    </div>
                                </Teleport>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div v-if="!isPlayLater && !is_owner && maplist.is_public" class="flex flex-wrap gap-3 mt-6">
                    <!-- Social Actions (not for Play Later or owner) -->
                    <button
                        @click="toggleLike"
                        :disabled="processing"
                        :class="[
                            'px-6 py-3 rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg transform hover:scale-105',
                            liked ? 'bg-gradient-to-r from-red-600 to-red-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20 border border-white/20'
                        ]">
                        <svg class="w-5 h-5" :fill="liked ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 20 20">
                            <path stroke-width="2" fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                        </svg>
                        {{ liked ? 'Liked' : 'Like' }}
                    </button>
                    <button
                        @click="toggleFavorite"
                        :disabled="processing"
                        :class="[
                            'px-6 py-3 rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg transform hover:scale-105',
                            favorited ? 'bg-gradient-to-r from-yellow-600 to-yellow-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20 border border-white/20'
                        ]">
                        <svg class="w-5 h-5" :fill="favorited ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 20 20">
                            <path stroke-width="2" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                        {{ favorited ? 'Favorited' : 'Favorite' }}
                    </button>
                </div>

                <!-- Server Selection (for Play Later only) -->
                <div v-if="isPlayLater && servers && servers.length > 0" class="bg-white/5 border border-white/10 rounded-lg p-4 mb-4">
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Select Server to Play:</label>
                    <select
                        v-model="selectedServer"
                        class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option :value="null">Choose a server...</option>
                        <option v-for="server in servers" :key="server.id" :value="server">
                            {{ server.name }} ({{ server.players_current }}/{{ server.players_max }}) - {{ server.location }}
                        </option>
                    </select>
                    <p v-if="selectedServer" class="text-xs text-gray-400 mt-2">
                        Click the "Play" button on any map below to connect to {{ selectedServer.name }} and start a vote for that map
                    </p>
                </div>
            </div>
        </div>

        <!-- Maps Grid -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-6 -mt-8">
            <!-- Reordering Instructions -->
            <div v-if="isReordering" class="mb-4 p-4 bg-purple-600/20 border border-purple-500/50 rounded-lg text-purple-200 text-center">
                <p class="font-semibold">Drag and drop maps to reorder them</p>
            </div>

            <div v-if="maps && maps.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <div
                    v-for="(map, index) in maps"
                    :key="map.id"
                    class="relative"
                    :draggable="isReordering"
                    @dragstart="onDragStart(index)"
                    @dragover="onDragOver($event, index)"
                    @dragend="onDragEnd"
                    :class="{ 'cursor-move': isReordering, 'opacity-50': draggedIndex === index }">
                    <MapCard :map="map" />

                    <!-- Play Button (for Play Later with server selected) -->
                    <a
                        v-if="isPlayLater && selectedServer"
                        :href="`defrag://${selectedServer.address}:${selectedServer.port}`"
                        @click="() => navigator.clipboard.writeText(`cv map ${map.name}`)"
                        :class="selectedServer.defrag?.toLowerCase().includes('cpm') ? 'play-button-cpm' : 'play-button-vq3'"
                        class="play-button absolute top-2 right-2 text-white p-2.5 rounded-lg transition z-10 flex items-center justify-center"
                        :title="`Connect to server (cv map ${map.name} copied to clipboard)`">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                    </a>

                    <!-- Remove Button (for owner) -->
                    <button
                        v-if="is_owner"
                        @click="removeMap(map.id)"
                        class="absolute top-2 left-2 bg-red-600/90 hover:bg-red-700 text-white p-2 rounded-lg shadow-lg transition z-10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4 opacity-40">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                </svg>
                <p class="font-semibold text-lg mb-2">No maps in this maplist yet</p>
                <p class="text-sm mb-4" v-if="is_owner">Start adding maps to build your collection!</p>
                <Link v-if="is_owner" :href="'/maps'" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                    Browse Maps
                </Link>
            </div>
        </div>

        <!-- Make Public Confirmation Modal -->
        <DialogModal :show="showPublicConfirmation" @close="cancelMakePublic">
            <template #title>
                Make Maplist Public
            </template>

            <template #content>
                <div class="space-y-4">
                    <div class="bg-yellow-600/20 border border-yellow-500/50 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-bold text-yellow-300 mb-2">Warning: This action is permanent!</p>
                                <p class="text-sm text-yellow-200">
                                    Once you make this maplist public, it <span class="font-bold">cannot be made private again</span>. Everyone will be able to view and use this maplist.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="text-gray-300">
                        Are you sure you want to make "<span class="font-bold text-white">{{ maplist.name }}</span>" public?
                    </p>
                </div>
            </template>

            <template #footer>
                <button
                    @click="cancelMakePublic"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>

                <button
                    @click="confirmMakePublic"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                    Make Public
                </button>
            </template>
        </DialogModal>

        <!-- Delete Confirmation Modal -->
        <DialogModal :show="showDeleteModal" @close="showDeleteModal = false">
            <template #title>
                Delete Maplist
            </template>

            <template #content>
                <div class="space-y-4">
                    <p class="text-gray-300">
                        Are you sure you want to permanently delete "<span class="font-bold text-white">{{ maplist.name }}</span>"?
                    </p>

                    <p class="text-sm text-gray-400">
                        This action cannot be undone. All maps in this maplist will be removed.
                    </p>

                    <div class="bg-red-600/20 border border-red-500/50 rounded-lg p-4">
                        <p class="text-sm text-red-300 mb-3">
                            To confirm deletion, please type <span class="font-mono font-bold text-red-200">delete my maplist</span> below:
                        </p>
                        <TextInput
                            v-model="deleteConfirmPhrase"
                            type="text"
                            class="w-full font-mono"
                            placeholder="delete my maplist"
                            @keyup.enter="deleteMaplist"
                        />
                    </div>
                </div>
            </template>

            <template #footer>
                <button
                    @click="showDeleteModal = false"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>

                <button
                    @click="deleteMaplist"
                    :disabled="deleting || deleteConfirmPhrase !== 'delete my maplist'"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                    {{ deleting ? 'Deleting...' : 'Delete Maplist' }}
                </button>
            </template>
        </DialogModal>
    </div>
</template>

<style scoped>
/* Play Button - Glass Morphism Effect */
.play-button {
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.play-button::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.1) 0%,
        rgba(255, 255, 255, 0.05) 50%,
        transparent 50%
    );
    pointer-events: none;
}

.play-button::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.play-button:hover::after {
    left: 100%;
}

.play-button-vq3 {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.15));
    border: 2px solid rgba(96, 165, 250, 0.4);
}

.play-button-vq3:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(37, 99, 235, 0.25));
    border-color: rgba(96, 165, 250, 0.6);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.play-button-cpm {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.25), rgba(147, 51, 234, 0.15));
    border: 2px solid rgba(192, 132, 252, 0.4);
}

.play-button-cpm:hover {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(147, 51, 234, 0.25));
    border-color: rgba(192, 132, 252, 0.6);
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}
</style>
