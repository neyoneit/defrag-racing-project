<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    creator: Object,
});

const specialtyLabels = {
    map: 'Mapping',
    player_model: 'Player Models',
    weapon_model: 'Weapon Models',
    shadow_model: 'Shadow Models',
};

const specialtyColors = {
    map: 'text-emerald-400 bg-emerald-500/15',
    player_model: 'text-purple-400 bg-purple-500/15',
    weapon_model: 'text-orange-400 bg-orange-500/15',
    shadow_model: 'text-cyan-400 bg-cyan-500/15',
};
</script>

<template>
    <Link
        :href="route('marketplace.creator', creator.user_id)"
        class="block bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl overflow-hidden hover:border-blue-500/50 transition-all duration-300 p-5"
    >
        <!-- Header -->
        <div class="flex items-center gap-3 mb-3">
            <img
                :src="creator.user?.profile_photo_path ? '/storage/' + creator.user.profile_photo_path : '/images/null.jpg'"
                class="h-10 w-10 rounded-full object-cover border-2 border-gray-600"
                :alt="creator.user?.name"
            />
            <div class="flex-1 min-w-0">
                <div class="font-bold text-white text-sm truncate" v-html="q3tohtml(creator.user?.name)"></div>
                <div v-if="creator.user?.country && creator.user.country !== '_404'" class="flex items-center gap-1">
                    <img :src="`/images/flags/${creator.user.country}.png`" class="h-3" :alt="creator.user.country" />
                </div>
            </div>
            <div v-if="creator.accepting_commissions" class="px-2 py-0.5 text-xs font-semibold rounded bg-green-500/20 text-green-400 border border-green-500/30">
                Available
            </div>
            <div v-else class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-500/20 text-gray-400 border border-gray-500/30">
                Busy
            </div>
        </div>

        <!-- Rating -->
        <div v-if="creator.avg_rating" class="flex items-center gap-2 mb-3">
            <div class="flex items-center gap-0.5">
                <svg v-for="i in 5" :key="i" class="w-4 h-4" :class="i <= Math.round(creator.avg_rating) ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
            </div>
            <span class="text-sm text-gray-400">{{ creator.avg_rating }} ({{ creator.review_count }})</span>
        </div>

        <!-- Specialties -->
        <div class="flex flex-wrap gap-1.5 mb-3">
            <span
                v-for="spec in (creator.specialties || [])"
                :key="spec"
                :class="specialtyColors[spec]"
                class="px-2 py-0.5 text-xs font-medium rounded"
            >
                {{ specialtyLabels[spec] }}
            </span>
        </div>

        <!-- Rates -->
        <div class="space-y-1 text-sm mb-3">
            <div v-if="creator.rate_maps" class="flex items-center justify-between">
                <span class="text-gray-400">Maps:</span>
                <span class="text-white font-semibold">{{ creator.rate_maps }}</span>
            </div>
            <div v-if="creator.rate_models" class="flex items-center justify-between">
                <span class="text-gray-400">Models:</span>
                <span class="text-white font-semibold">{{ creator.rate_models }}</span>
            </div>
        </div>

        <!-- Featured Maps -->
        <div v-if="creator.featured_maps && creator.featured_maps.length > 0" class="flex gap-1.5 overflow-hidden">
            <div
                v-for="map in creator.featured_maps.slice(0, 3)"
                :key="map.id"
                class="w-16 h-10 rounded overflow-hidden flex-shrink-0"
            >
                <img
                    :src="map.thumbnail ? `/images/thumbnails/${map.thumbnail}` : '/images/thumbnails/noimage.jpg'"
                    class="w-full h-full object-cover"
                    :alt="map.name"
                    :title="map.name"
                />
            </div>
            <div v-if="creator.featured_maps.length > 3" class="w-16 h-10 rounded bg-black/40 border border-white/10 flex items-center justify-center text-xs text-gray-400 flex-shrink-0">
                +{{ creator.featured_maps.length - 3 }}
            </div>
        </div>
    </Link>
</template>
