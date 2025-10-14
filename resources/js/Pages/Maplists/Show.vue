<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MapCard from '@/Components/MapCard.vue';
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

const isPlayLater = computed(() => props.maplist.is_play_later);

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

const deleteMaplist = async () => {
    if (!confirm('Are you sure you want to delete this maplist?')) return;

    try {
        await axios.delete(`/api/maplists/${props.maplist.id}`);
        router.visit('/maplists');
    } catch (error) {
        console.error('Error deleting maplist:', error);
        alert('Failed to delete maplist');
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
    const connectCommand = `connect ${server.address}:${server.port};callvote map "${map.name}"`;

    // Copy to clipboard
    navigator.clipboard.writeText(connectCommand).then(() => {
        alert(`Command copied to clipboard!\n${connectCommand}`);
    });
};
</script>

<template>
    <div class="min-h-screen">
        <Head :title="maplist.name" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-4">
                    <Link href="/maplists" class="hover:text-white transition">Maplists</Link>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-white">{{ maplist.name }}</span>
                </div>

                <!-- Maplist Header -->
                <div class="flex items-start justify-between mb-6">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <h1 class="text-4xl md:text-5xl font-black text-white">{{ maplist.name }}</h1>
                            <div v-if="isPlayLater" class="px-3 py-1 bg-blue-600 rounded-full text-sm font-semibold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Play Later
                            </div>
                            <div v-else-if="!maplist.is_public" class="px-3 py-1 bg-gray-700 rounded-full text-sm font-semibold">
                                Private
                            </div>
                        </div>

                        <p v-if="maplist.description" class="text-gray-300 text-lg mb-4 max-w-3xl">
                            {{ maplist.description }}
                        </p>

                        <!-- Creator Info (only for non-Play Later) -->
                        <Link v-if="!isPlayLater" :href="`/profile/${maplist.user?.id}`" class="flex items-center gap-2 text-gray-400 hover:text-white transition mb-4 w-fit">
                            <img
                                v-if="maplist.user?.profile_photo_path"
                                :src="maplist.user.profile_photo_path"
                                :alt="maplist.user?.plain_name"
                                class="w-8 h-8 rounded-full">
                            <div v-else class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span class="font-semibold" v-html="$q3tohtml(maplist.user?.name || 'Unknown')"></span>
                        </Link>

                        <!-- Stats -->
                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                <span class="font-semibold">{{ maplist.maps?.length || 0 }} maps</span>
                            </div>
                            <div v-if="!isPlayLater" class="flex items-center gap-2 text-red-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-semibold">{{ likesCount }}</span>
                            </div>
                            <div v-if="!isPlayLater" class="flex items-center gap-2 text-yellow-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <span class="font-semibold">{{ favoritesCount }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <!-- Social Actions (not for Play Later or owner) -->
                        <template v-if="!isPlayLater && !is_owner && maplist.is_public">
                            <button
                                @click="toggleLike"
                                :disabled="processing"
                                :class="[
                                    'px-4 py-2 rounded-lg font-semibold transition flex items-center gap-2',
                                    liked ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
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
                                    'px-4 py-2 rounded-lg font-semibold transition flex items-center gap-2',
                                    favorited ? 'bg-yellow-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
                                ]">
                                <svg class="w-5 h-5" :fill="favorited ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 20 20">
                                    <path stroke-width="2" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                {{ favorited ? 'Favorited' : 'Favorite' }}
                            </button>
                        </template>

                        <!-- Owner Actions -->
                        <button
                            v-if="is_owner && !isPlayLater"
                            @click="deleteMaplist"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                            Delete Maplist
                        </button>
                    </div>
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
            <div v-if="maplist.maps && maplist.maps.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <div v-for="map in maplist.maps" :key="map.id" class="relative">
                    <MapCard :map="map" :mapname="map.name" />

                    <!-- Play Button (for Play Later with server selected) -->
                    <button
                        v-if="isPlayLater && selectedServer"
                        @click="connectToServer(map)"
                        class="absolute top-2 right-2 bg-green-600 hover:bg-green-700 text-white p-2 rounded-lg shadow-lg transition z-10">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                        </svg>
                    </button>

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
    </div>
</template>
