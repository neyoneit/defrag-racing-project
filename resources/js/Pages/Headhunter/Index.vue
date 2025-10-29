<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    challenges: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const formatTime = (milliseconds) => {
    const seconds = Math.floor(milliseconds / 1000);
    const ms = milliseconds % 1000;
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;

    if (minutes > 0) {
        return `${minutes}:${String(secs).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    }
    return `${secs}.${String(ms).padStart(3, '0')}`;
};

const filterChallenges = () => {
    router.get(route('headhunter.index'), {
        search: search.value,
        status: statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusColor = (status) => {
    const colors = {
        'open': 'text-green-400 bg-green-500/20 border-green-500/30',
        'claimed': 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30',
        'completed': 'text-blue-400 bg-blue-500/20 border-blue-500/30',
        'disputed': 'text-red-400 bg-red-500/20 border-red-500/30',
        'closed': 'text-gray-400 bg-gray-500/20 border-gray-500/30',
    };
    return colors[status] || colors['open'];
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Headhunter" />

        <!-- Header Section with gradient fade -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Headhunter</h1>
                        <p class="text-gray-400">Community-created challenges with rewards</p>
                    </div>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('headhunter.create')"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                    >
                        Create Challenge
                    </Link>
                </div>
            </div>
        </div>

        <!-- Content Section -->

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Filters -->
            <div class="mb-6 backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input
                            v-model="search"
                            @input="filterChallenges"
                            type="text"
                            placeholder="Search by title or map..."
                            class="bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        />
                        <select
                            v-model="statusFilter"
                            @change="filterChallenges"
                            class="bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        >
                            <option value="">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="claimed">Claimed</option>
                            <option value="completed">Completed</option>
                            <option value="disputed">Disputed</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>

                <!-- Challenges List -->
                <div class="space-y-4">
                    <div
                        v-for="challenge in challenges.data"
                        :key="challenge.id"
                        class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl overflow-hidden hover:border-blue-500/50 transition-all duration-300"
                    >
                        <Link :href="route('headhunter.show', challenge.id)" class="block p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-xl font-bold text-white">{{ challenge.title }}</h3>
                                        <span :class="`px-2 py-1 text-xs font-semibold rounded-full border ${getStatusColor(challenge.status)}`">
                                            {{ challenge.status }}
                                        </span>
                                    </div>

                                    <p class="text-gray-400 mb-4 line-clamp-2">{{ challenge.description }}</p>

                                    <div class="flex flex-wrap items-center gap-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                            </svg>
                                            <span class="text-white font-semibold">{{ challenge.mapname }}</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <img :src="`/images/modes/${challenge.physics}-icon.svg`" class="w-5 h-5" :alt="challenge.physics" />
                                            <span class="text-gray-400 capitalize">{{ challenge.mode }}</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <span class="text-yellow-400 font-bold">{{ formatTime(challenge.target_time) }}</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                            </svg>
                                            <span class="text-gray-400">{{ challenge.participants_count }} participants</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div v-if="challenge.reward_amount" class="mb-2">
                                        <div class="text-2xl font-black text-green-400">{{ challenge.reward_currency }} {{ challenge.reward_amount }}</div>
                                        <div class="text-xs text-gray-500">Reward</div>
                                    </div>
                                    <div v-else-if="challenge.reward_description" class="mb-2">
                                        <div class="text-sm font-semibold text-blue-400">{{ challenge.reward_description }}</div>
                                        <div class="text-xs text-gray-500">Reward</div>
                                    </div>

                                    <div class="flex items-center justify-end gap-1.5 mt-3 text-sm text-gray-400">
                                        <span>by</span>
                                        <Link
                                            v-if="challenge.creator"
                                            :href="route('profile.index', challenge.creator.id)"
                                            @click.stop
                                            class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/creator"
                                        >
                                            <div class="overflow-visible flex-shrink-0">
                                                <div :class="'avatar-effect-' + (challenge.creator?.avatar_effect || 'none')" :style="`--effect-color: ${challenge.creator?.color || '#ffffff'}; --border-color: ${challenge.creator?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                                    <img
                                                        :src="challenge.creator?.profile_photo_path ? '/storage/' + challenge.creator?.profile_photo_path : '/images/null.jpg'"
                                                        class="h-5 w-5 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                                        :style="`border-color: ${challenge.creator?.avatar_border_color || '#6b7280'}`"
                                                        :alt="challenge.creator?.name"
                                                    />
                                                </div>
                                            </div>
                                            <span
                                                :class="'name-effect-' + (challenge.creator?.name_effect || 'none')"
                                                :style="`--effect-color: ${challenge.creator?.color || '#ffffff'}`"
                                                class="text-xs font-semibold text-gray-300 group-hover/creator:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                                v-html="q3tohtml(challenge.creator?.name)"
                                            ></span>
                                        </Link>
                                        <span v-else class="text-gray-500">Unknown</span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div v-if="challenges.data.length === 0" class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <h3 class="text-xl font-bold text-white mb-2">No Challenges Found</h3>
                        <p class="text-gray-400">Be the first to create a challenge!</p>
                    </div>
                </div>

            <!-- Pagination -->
            <div v-if="challenges.data.length > 0" class="mt-6 flex justify-center">
                <nav class="flex items-center gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in challenges.links"
                        :key="link.label"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-4 py-2 rounded-lg font-semibold transition-all',
                            link.active
                                ? 'bg-blue-600 text-white'
                                : link.url
                                ? 'bg-gray-800/50 text-gray-300 hover:bg-gray-700/50'
                                : 'bg-gray-800/30 text-gray-600 cursor-not-allowed'
                        ]"
                    />
                </nav>
            </div>
        </div>
    </div>
</template>
