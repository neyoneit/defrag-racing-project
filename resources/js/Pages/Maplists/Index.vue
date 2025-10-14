<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MaplistCard from '@/Components/Maplists/MaplistCard.vue';

const props = defineProps({
    maplists: Object,
    sort: {
        type: String,
        default: 'likes'
    }
});

const currentSort = ref(props.sort);

const changeSort = (newSort) => {
    currentSort.value = newSort;
    router.get('/maplists', { sort: newSort }, { preserveState: true });
};
</script>

<template>
    <div class="min-h-screen">
        <Head title="Maplists" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Maplists</h1>
                        <span class="text-sm text-gray-400">{{ maplists.total }} total</span>
                    </div>
                </div>

                <!-- Description -->
                <p class="text-gray-300 text-lg mb-6 max-w-3xl">
                    Discover community-curated collections of maps. Like playlists for your favorite maps!
                </p>

                <!-- Sort Tabs -->
                <div class="flex gap-2">
                    <button
                        @click="changeSort('likes')"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all',
                            currentSort === 'likes'
                                ? 'bg-red-600 text-white'
                                : 'bg-white/5 text-gray-300 hover:bg-white/10'
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
                            'px-4 py-2 rounded-lg font-semibold transition-all',
                            currentSort === 'favorites'
                                ? 'bg-yellow-600 text-white'
                                : 'bg-white/5 text-gray-300 hover:bg-white/10'
                        ]">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            Most Favorited
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-8 py-6">
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
                    :href="`/maplists?page=${page}&sort=${currentSort}`"
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
    </div>
</template>
