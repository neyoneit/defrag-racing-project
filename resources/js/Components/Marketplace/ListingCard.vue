<script setup>
import { Link } from '@inertiajs/vue3';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    listing: Object,
});

const workTypeLabels = {
    map: 'Map',
    player_model: 'Player Model',
    weapon_model: 'Weapon Model',
    shadow_model: 'Shadow Model',
};

const workTypeColors = {
    map: 'text-emerald-400 bg-emerald-500/20 border-emerald-500/30',
    player_model: 'text-purple-400 bg-purple-500/20 border-purple-500/30',
    weapon_model: 'text-orange-400 bg-orange-500/20 border-orange-500/30',
    shadow_model: 'text-cyan-400 bg-cyan-500/20 border-cyan-500/30',
};

const timeAgo = (date) => {
    const now = new Date();
    const d = new Date(date);
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return d.toLocaleDateString();
};
</script>

<template>
    <Link
        :href="route('marketplace.show', listing.id)"
        class="block bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl overflow-hidden hover:border-blue-500/50 transition-all duration-300 p-5"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <h3 class="text-lg font-bold text-white truncate">{{ listing.title }}</h3>
                    <StatusBadge :status="listing.status" />
                </div>

                <p class="text-gray-400 text-sm mb-3 line-clamp-2">{{ listing.description }}</p>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span :class="`px-2 py-0.5 text-xs font-semibold rounded border ${workTypeColors[listing.work_type]}`">
                        {{ workTypeLabels[listing.work_type] }}
                    </span>

                    <div v-if="listing.budget" class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-green-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span class="text-green-400 font-semibold">{{ listing.budget }}</span>
                    </div>

                    <div v-if="listing.reviews_count > 0" class="flex items-center gap-1 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                        <span>{{ listing.reviews_count }}</span>
                    </div>
                </div>
            </div>

            <div class="text-right flex-shrink-0">
                <div class="flex items-center justify-end gap-1.5 text-sm text-gray-400">
                    <span>by</span>
                    <span v-if="listing.user" class="flex items-center gap-1.5">
                        <img
                            :src="listing.user?.profile_photo_path ? '/storage/' + listing.user.profile_photo_path : '/images/null.jpg'"
                            class="h-5 w-5 rounded-full object-cover border border-gray-600"
                            :alt="listing.user?.name"
                        />
                        <span class="text-xs font-semibold text-gray-300" v-html="q3tohtml(listing.user?.name)"></span>
                    </span>
                </div>
                <div class="text-xs text-gray-500 mt-1">{{ timeAgo(listing.created_at) }}</div>
            </div>
        </div>
    </Link>
</template>
