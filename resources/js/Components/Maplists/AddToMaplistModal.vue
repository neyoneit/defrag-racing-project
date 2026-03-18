<template>
    <DialogModal :show="show" @close="closeModal" max-width="md">
        <template #title>
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                <span>Add to Maplist</span>
            </div>
        </template>

        <template #content>
            <div class="space-y-3">
                <!-- Quick Actions: Play Later -->
                <button
                    @click="addToPlayLater"
                    :disabled="addingToMaplist"
                    class="w-full text-left bg-white/5 rounded-lg p-3 hover:bg-white/10 transition border border-white/5 hover:border-blue-500/30 disabled:opacity-50">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm">Play Later</div>
                            <div class="text-xs text-gray-400">Add to your Play Later list</div>
                        </div>
                    </div>
                </button>

                <!-- User's Maplists -->
                <div v-if="maplists.length > 0" class="space-y-1.5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-1">Your Maplists</h3>
                    <div class="max-h-48 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                        <button
                            v-for="maplist in maplists"
                            :key="maplist.id"
                            @click="addToMaplist(maplist.id)"
                            :disabled="addingToMaplist"
                            class="w-full text-left bg-white/5 rounded-lg p-3 hover:bg-white/10 transition border border-white/5 hover:border-blue-500/30 disabled:opacity-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-white text-sm">{{ maplist.name }}</div>
                                    <div class="text-xs text-gray-400">{{ maplist.maps_count }} maps</div>
                                </div>
                                <span v-if="maplist.is_public" class="text-xs text-green-400/80 bg-green-500/10 px-2 py-0.5 rounded-full">Public</span>
                                <span v-else class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full">Private</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Create New Maplist -->
                <div class="border-t border-white/5 pt-3">
                    <button
                        @click="showCreateForm = !showCreateForm"
                        class="w-full flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-500 text-white font-medium py-2.5 px-4 rounded-lg transition text-sm"
                        type="button">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create New Maplist</span>
                    </button>

                    <!-- Create Form -->
                    <div v-if="showCreateForm" class="mt-3 space-y-3 bg-white/5 rounded-lg p-3 border border-white/5">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Maplist Name</label>
                            <input
                                v-model="newMaplistForm.name"
                                type="text"
                                class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500"
                                placeholder="e.g., Favorite Strafe Maps"
                                @keyup.enter="createAndAddToMaplist">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Description (Optional)</label>
                            <textarea
                                v-model="newMaplistForm.description"
                                class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500"
                                rows="2"
                                placeholder="Describe your maplist..."></textarea>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input
                                v-model="newMaplistForm.is_public"
                                type="checkbox"
                                id="is-public"
                                class="rounded bg-black/30 border-white/10 text-blue-600 focus:ring-blue-500">
                            <label for="is-public" class="text-sm text-gray-300">Make this maplist public</label>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                @click="createAndAddToMaplist"
                                :disabled="!newMaplistForm.name || addingToMaplist"
                                class="flex-1 bg-green-600 hover:bg-green-500 disabled:bg-gray-700 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                                Create & Add Map
                            </button>
                            <button
                                @click="showCreateForm = false"
                                class="px-4 py-2 bg-white/5 hover:bg-white/10 text-gray-300 rounded-lg transition text-sm border border-white/5">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div v-if="successMessage" class="bg-green-500/10 border border-green-500/30 text-green-300 px-4 py-2.5 rounded-lg text-sm">
                    {{ successMessage }}
                </div>
                <div v-if="errorMessage" class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-2.5 rounded-lg text-sm">
                    {{ errorMessage }}
                </div>
            </div>
        </template>

        <template #footer>
            <button
                @click="closeModal"
                class="bg-white/5 hover:bg-white/10 text-gray-300 font-medium py-2 px-6 rounded-lg transition text-sm border border-white/5">
                Close
            </button>
        </template>
    </DialogModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import DialogModal from '@/Components/Laravel/DialogModal.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    mapId: Number,
});

const emit = defineEmits(['close', 'added']);

const maplists = ref([]);
const showCreateForm = ref(false);
const addingToMaplist = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const newMaplistForm = ref({
    name: '',
    description: '',
    is_public: true,
});

// Fetch user's maplists when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchUserMaplists();
        resetMessages();
        showCreateForm.value = false;
    }
});

const fetchUserMaplists = async () => {
    try {
        const response = await axios.get('/api/maplists/user');
        // Filter out Play Later from the regular list since we show it separately
        maplists.value = response.data.filter(m => !m.is_play_later);
    } catch (error) {
        console.error('Error fetching maplists:', error);
    }
};

const addToPlayLater = async () => {
    try {
        addingToMaplist.value = true;
        resetMessages();

        const response = await axios.get('/api/maplists/user');
        const playLater = response.data.find(m => m.is_play_later);

        if (playLater) {
            await axios.post(`/api/maplists/${playLater.id}/maps`, {
                map_id: props.mapId
            });
            successMessage.value = 'Added to Play Later!';
            emit('added');
            setTimeout(() => {
                closeModal();
            }, 1500);
        }
    } catch (error) {
        if (error.response?.data?.error) {
            errorMessage.value = error.response.data.error;
        } else {
            errorMessage.value = 'Failed to add to Play Later';
        }
    } finally {
        addingToMaplist.value = false;
    }
};

const addToMaplist = async (maplistId) => {
    try {
        addingToMaplist.value = true;
        resetMessages();

        await axios.post(`/api/maplists/${maplistId}/maps`, {
            map_id: props.mapId
        });

        successMessage.value = 'Map added to maplist!';
        emit('added');
        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (error) {
        if (error.response?.data?.error) {
            errorMessage.value = error.response.data.error;
        } else {
            errorMessage.value = 'Failed to add map to maplist';
        }
    } finally {
        addingToMaplist.value = false;
    }
};

const createAndAddToMaplist = async () => {
    if (!newMaplistForm.value.name) return;

    try {
        addingToMaplist.value = true;
        resetMessages();

        // Create the maplist
        const createResponse = await axios.post('/api/maplists', {
            name: newMaplistForm.value.name,
            description: newMaplistForm.value.description,
            is_public: newMaplistForm.value.is_public,
        });

        // Add the map to the newly created maplist
        await axios.post(`/api/maplists/${createResponse.data.maplist.id}/maps`, {
            map_id: props.mapId
        });

        successMessage.value = 'Maplist created and map added!';
        emit('added');

        // Reset form
        newMaplistForm.value = {
            name: '',
            description: '',
            is_public: true,
        };
        showCreateForm.value = false;

        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (error) {
        if (error.response?.data?.error) {
            errorMessage.value = error.response.data.error;
        } else {
            errorMessage.value = 'Failed to create maplist';
        }
    } finally {
        addingToMaplist.value = false;
    }
};

const resetMessages = () => {
    successMessage.value = '';
    errorMessage.value = '';
};

const closeModal = () => {
    emit('close');
    resetMessages();
    showCreateForm.value = false;
};
</script>
