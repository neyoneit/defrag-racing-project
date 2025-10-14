<template>
    <div class="bg-gray-800 rounded-lg overflow-hidden hover:shadow-xl transition-all duration-300 border border-gray-700 hover:border-blue-500">
        <Link :href="`/maplists/${maplist.id}`" class="block">
            <!-- Thumbnail Preview (show first 4 map thumbnails if available) -->
            <div class="grid grid-cols-2 gap-1 bg-gray-900 h-48">
                <template v-if="maplist.maps && maplist.maps.length > 0">
                    <div v-for="(map, index) in maplist.maps.slice(0, 4)" :key="index" class="relative bg-gray-800">
                        <img
                            v-if="map.thumbnail"
                            :src="`/images/thumbs/${map.thumbnail}`"
                            :alt="map.name"
                            class="w-full h-full object-cover"
                            @error="(e) => e.target.style.display = 'none'">
                        <div v-if="!map.thumbnail" class="w-full h-full flex items-center justify-center text-gray-600">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                        </div>
                    </div>
                    <!-- Fill remaining slots if less than 4 maps -->
                    <div v-for="i in (4 - Math.min(maplist.maps.length, 4))" :key="`empty-${i}`" class="bg-gray-800 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </template>
                <template v-else>
                    <div v-for="i in 4" :key="i" class="bg-gray-800 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </div>
                </template>
            </div>

            <!-- Maplist Info -->
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <h3 class="text-lg font-bold text-white line-clamp-1">{{ maplist.name }}</h3>
                    <div v-if="maplist.is_play_later" class="ml-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <p v-if="maplist.description" class="text-sm text-gray-400 mb-3 line-clamp-2">
                    {{ maplist.description }}
                </p>

                <!-- Creator Info -->
                <div class="flex items-center space-x-2 mb-3">
                    <img
                        v-if="maplist.user?.profile_photo_path"
                        :src="maplist.user.profile_photo_path"
                        :alt="maplist.user?.plain_name || maplist.user?.name"
                        class="w-6 h-6 rounded-full">
                    <div v-else class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span class="text-sm text-gray-400" v-html="$q3tohtml(maplist.user?.name || 'Unknown')"></span>
                </div>

                <!-- Stats -->
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-1 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            <span>{{ maplist.maps_count || maplist.maps?.length || 0 }}</span>
                        </div>
                        <div class="flex items-center space-x-1 text-red-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ maplist.likes_count || 0 }}</span>
                        </div>
                        <div class="flex items-center space-x-1 text-yellow-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <span>{{ maplist.favorites_count || 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    maplist: {
        type: Object,
        required: true
    }
});
</script>
