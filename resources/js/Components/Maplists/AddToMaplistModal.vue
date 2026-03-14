<template>
    <DialogModal :show="show" @close="closeModal">
        <template #title>
            Add to Maplist
        </template>

        <template #content>
            <div class="space-y-4">
                <!-- Quick Actions: Play Later -->
                <div class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition cursor-pointer"
                     @click="addToPlayLater"
                     :class="{ 'opacity-50': addingToMaplist }">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <div class="font-semibold text-white">Play Later</div>
                                <div class="text-sm text-gray-400">Add to your Play Later list</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User's Maplists -->
                <div v-if="maplists.length > 0" class="space-y-2">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase">Your Maplists</h3>
                    <div v-for="maplist in maplists"
                         :key="maplist.id"
                         class="bg-gray-800 rounded-lg p-3 hover:bg-gray-700 transition cursor-pointer"
                         @click="addToMaplist(maplist.id)"
                         :class="{ 'opacity-50': addingToMaplist }">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-white">{{ maplist.name }}</div>
                                <div class="text-sm text-gray-400">{{ maplist.maps_count }} maps</div>
                            </div>
                            <div v-if="maplist.is_public" class="text-xs text-green-400">Public</div>
                            <div v-else class="text-xs text-gray-500">Private</div>
                        </div>
                    </div>
                </div>

                <!-- Create New Maplist -->
                <div class="border-t border-gray-700 pt-4">
                    <button
                        @click="showCreateForm = !showCreateForm"
                        class="w-full flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition"
                        type="button">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create New Maplist</span>
                    </button>

                    <!-- Create Form -->
                    <div v-if="showCreateForm" class="mt-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Maplist Name</label>
                            <input
                                v-model="newMaplistForm.name"
                                type="text"
                                class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g., Favorite Strafe Maps"
                                @keyup.enter="createAndAddToMaplist">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Description (Optional)</label>
                            <textarea
                                v-model="newMaplistForm.description"
                                class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                rows="2"
                                placeholder="Describe your maplist..."></textarea>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input
                                v-model="newMaplistForm.is_public"
                                type="checkbox"
                                id="is-public"
                                class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                            <label for="is-public" class="text-sm text-gray-300">Make this maplist public</label>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                @click="createAndAddToMaplist"
                                :disabled="!newMaplistForm.name || addingToMaplist"
                                class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-semibold py-2 px-4 rounded-lg transition">
                                Create & Add Map
                            </button>
                            <button
                                @click="showCreateForm = false"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div v-if="successMessage" class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-2 rounded-lg">
                    {{ successMessage }}
                </div>
                <div v-if="errorMessage" class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-2 rounded-lg">
                    {{ errorMessage }}
                </div>
            </div>
        </template>

        <template #footer>
            <button
                @click="closeModal"
                class="bg-gray-700 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-lg transition">
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
