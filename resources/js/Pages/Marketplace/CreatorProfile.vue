<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ReviewSection from '@/Components/Marketplace/ReviewSection.vue';

const props = defineProps({
    user: Object,
    profile: Object,
    avgRating: Number,
    reviewCount: Number,
    reviews: Array,
    listings: Array,
    featuredMaps: Array,
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

const workTypeLabels = {
    map: 'Map',
    player_model: 'Player Model',
    weapon_model: 'Weapon Model',
    shadow_model: 'Shadow Model',
};
</script>

<template>
    <div class="pb-4">
        <Head :title="(user.plain_name || user.name) + ' - Creator Profile'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('marketplace.creators')" class="text-gray-400 hover:text-white text-sm transition mb-4 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Creator Directory
                </Link>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="grid grid-cols-3 gap-6">
                <!-- Main -->
                <div class="col-span-2 space-y-4">
                    <!-- Bio -->
                    <div v-if="profile.bio" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <h3 class="text-sm font-bold text-white mb-3">About</h3>
                        <p class="text-gray-300 whitespace-pre-wrap">{{ profile.bio }}</p>
                    </div>

                    <!-- Featured Maps -->
                    <div v-if="featuredMaps && featuredMaps.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <h3 class="text-sm font-bold text-white mb-3">Featured Work</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div v-for="map in featuredMaps" :key="map.id" class="group">
                                <div class="aspect-video rounded-lg overflow-hidden bg-black/40 border border-white/5">
                                    <img
                                        :src="map.thumbnail ? `/images/thumbnails/${map.thumbnail}` : '/images/thumbnails/noimage.jpg'"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        :alt="map.name"
                                    />
                                </div>
                                <div class="mt-1">
                                    <div class="text-sm font-semibold text-white truncate">{{ map.name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ map.author }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Listings -->
                    <div v-if="listings && listings.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <h3 class="text-sm font-bold text-white mb-3">Recent Listings</h3>
                        <div class="space-y-2">
                            <Link
                                v-for="listing in listings"
                                :key="listing.id"
                                :href="route('marketplace.show', listing.id)"
                                class="block p-3 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 transition"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span :class="listing.listing_type === 'request' ? 'text-blue-400' : 'text-green-400'" class="text-xs font-semibold uppercase">
                                            {{ listing.listing_type }}
                                        </span>
                                        <span class="text-sm text-white font-semibold truncate">{{ listing.title }}</span>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ workTypeLabels[listing.work_type] }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <ReviewSection
                            :listing-id="0"
                            :reviews="reviews"
                            :can-review="false"
                            :user-review="null"
                        />
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Creator Card -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-4">
                            <Link :href="route('profile.index', user.id)">
                                <img
                                    :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'"
                                    class="h-14 w-14 rounded-full object-cover border-2 border-gray-600 hover:border-blue-500 transition"
                                />
                            </Link>
                            <div>
                                <Link :href="route('profile.index', user.id)" class="font-bold text-white hover:text-blue-300 transition" v-html="q3tohtml(user.name)"></Link>
                                <div v-if="user.country && user.country !== '_404'" class="flex items-center gap-1 mt-0.5">
                                    <img :src="`/images/flags/${user.country}.png`" class="h-3" />
                                </div>
                            </div>
                        </div>

                        <!-- Rating -->
                        <div v-if="avgRating" class="flex items-center gap-2 mb-4 p-2 bg-yellow-500/5 border border-yellow-500/20 rounded-lg">
                            <div class="flex items-center gap-0.5">
                                <svg v-for="i in 5" :key="i" class="w-4 h-4" :class="i <= Math.round(avgRating) ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-yellow-400">{{ avgRating }}</span>
                            <span class="text-xs text-gray-400">({{ reviewCount }} reviews)</span>
                        </div>

                        <!-- Status -->
                        <div class="mb-4">
                            <div v-if="profile.accepting_commissions" class="px-3 py-1.5 text-sm font-semibold rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 text-center">
                                Accepting Commissions
                            </div>
                            <div v-else class="px-3 py-1.5 text-sm font-semibold rounded-lg bg-gray-500/10 text-gray-400 border border-gray-500/20 text-center">
                                Not Available
                            </div>
                        </div>

                        <!-- Specialties -->
                        <div class="flex flex-wrap gap-1.5 mb-4">
                            <span
                                v-for="spec in (profile.specialties || [])"
                                :key="spec"
                                :class="specialtyColors[spec]"
                                class="px-2 py-0.5 text-xs font-medium rounded"
                            >
                                {{ specialtyLabels[spec] }}
                            </span>
                        </div>

                        <!-- Rates -->
                        <div class="space-y-2 text-sm">
                            <div v-if="profile.rate_maps" class="flex justify-between p-2 bg-black/20 rounded">
                                <span class="text-gray-400">Maps</span>
                                <span class="text-white font-semibold">{{ profile.rate_maps }}</span>
                            </div>
                            <div v-if="profile.rate_models" class="flex justify-between p-2 bg-black/20 rounded">
                                <span class="text-gray-400">Models</span>
                                <span class="text-white font-semibold">{{ profile.rate_models }}</span>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <div class="text-xs text-gray-400 mb-2">Contact via linked socials:</div>
                            <Link :href="route('profile.index', user.id)" class="text-sm text-blue-400 hover:text-blue-300 transition">
                                View full profile
                            </Link>
                        </div>
                    </div>

                    <!-- Portfolio Links -->
                    <div v-if="profile.portfolio_urls && profile.portfolio_urls.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Portfolio</h3>
                        <div class="space-y-2">
                            <a
                                v-for="(url, i) in profile.portfolio_urls"
                                :key="i"
                                :href="url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block text-sm text-blue-400 hover:text-blue-300 transition truncate"
                            >
                                {{ url }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
