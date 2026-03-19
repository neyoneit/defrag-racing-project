<script setup>
    import { ref, onMounted, onUnmounted } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';

    const props = defineProps({
        stats: Object,
        videos: Object,
        currentlyRendering: Object,
        pendingQueue: Array,
        pendingTotal: Number,
        demomeOnline: Boolean,
    });

    const expandedVideo = ref(null);

    // Auto-refresh live status every 15 seconds
    let refreshInterval = null;
    onMounted(() => {
        if (props.currentlyRendering || (props.pendingQueue && props.pendingQueue.length > 0)) {
            refreshInterval = setInterval(() => {
                router.reload({ only: ['currentlyRendering', 'pendingQueue', 'pendingTotal', 'demomeOnline', 'videos', 'stats'] });
            }, 15000);
        }
    });
    onUnmounted(() => {
        if (refreshInterval) clearInterval(refreshInterval);
    });

    const toggleVideo = (videoId) => {
        expandedVideo.value = expandedVideo.value === videoId ? null : videoId;
    };

    const formatTime = (timeMs) => {
        if (!timeMs) return '-';
        const minutes = Math.floor(timeMs / 60000);
        const seconds = Math.floor((timeMs % 60000) / 1000);
        const ms = timeMs % 1000;
        return `${minutes}:${String(seconds).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    };

    const formatDuration = (seconds) => {
        if (!seconds) return '-';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m ${s}s`;
    };

    const sourceLabel = (source) => {
        return { discord: 'Discord', web: 'Web', auto: 'Auto' }[source] || source;
    };

    const sourceColor = (source) => {
        return {
            discord: 'bg-indigo-500/20 text-indigo-300',
            web: 'bg-green-500/20 text-green-300',
            auto: 'bg-gray-500/20 text-gray-300',
        }[source] || 'bg-gray-500/20 text-gray-300';
    };

    const priorityLabel = (p) => {
        return { 1: 'WR', 2: 'Verified', 3: 'Normal' }[p] || '';
    };

    const timeSince = (dateStr) => {
        const now = new Date();
        const date = new Date(dateStr);
        const diffMs = now - date;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return 'just now';
        if (diffMin < 60) return `${diffMin}m ago`;
        const diffH = Math.floor(diffMin / 60);
        if (diffH < 24) return `${diffH}h ago`;
        return `${Math.floor(diffH / 24)}d ago`;
    };
</script>

<template>
    <div class="min-h-screen">
        <Head title="YouTube - Rendered Demos" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex items-center gap-3 mb-6">
                    <h1 class="text-4xl md:text-5xl font-black text-white">YouTube</h1>
                    <span v-if="demomeOnline" class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-500/10 border border-green-500/30">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="text-xs font-medium text-green-400">Renderer Online</span>
                    </span>
                    <span v-else class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-500/10 border border-gray-500/30">
                        <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                        <span class="text-xs font-medium text-gray-500">Renderer Offline</span>
                    </span>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 max-w-xl">
                    <div class="bg-white/5 border border-white/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-white">{{ stats.total_renders }}</div>
                        <div class="text-xs text-gray-400 mt-1">Videos Rendered</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-white">{{ stats.total_render_hours }}h</div>
                        <div class="text-xs text-gray-400 mt-1">Render Time</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-white">{{ stats.total_maps }}</div>
                        <div class="text-xs text-gray-400 mt-1">Unique Maps</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-2 pb-12">

            <!-- Live Render Status -->
            <div v-if="currentlyRendering || (pendingQueue && pendingQueue.length > 0)" class="mb-8">
                <!-- Currently Rendering -->
                <div v-if="currentlyRendering" class="bg-gradient-to-r from-red-500/10 to-orange-500/10 border border-red-500/30 rounded-xl p-5 mb-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                        <span class="text-sm font-bold text-white uppercase tracking-wide">Currently Rendering</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <Link :href="`/maps/${currentlyRendering.map_name}`" class="text-lg font-bold text-white hover:text-blue-400 transition-colors">
                                {{ currentlyRendering.map_name }}
                            </Link>
                            <div class="text-sm text-gray-400 mt-0.5">
                                {{ currentlyRendering.player_name }}
                                <span class="uppercase ml-1">{{ currentlyRendering.physics }}</span>
                                <span class="font-mono ml-2">{{ formatTime(currentlyRendering.time_ms) }}</span>
                                <span v-if="currentlyRendering.record?.rank" class="ml-2 text-gray-500">#{{ currentlyRendering.record.rank }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span :class="sourceColor(currentlyRendering.source)" class="text-xs px-2 py-0.5 rounded font-medium">
                                {{ sourceLabel(currentlyRendering.source) }}
                            </span>
                            <div v-if="currentlyRendering.requested_by" class="text-xs text-gray-500 mt-1">
                                by {{ currentlyRendering.requested_by }}
                            </div>
                        </div>
                    </div>
                    <!-- Progress bar (indeterminate for now) -->
                    <div class="mt-4 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-red-500 to-orange-500 rounded-full animate-progress-indeterminate"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1.5">Rendering in progress...</div>
                </div>

                <!-- Pending Queue -->
                <div v-if="pendingQueue && pendingQueue.length > 0" class="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between">
                        <span class="text-sm font-bold text-white">Queue</span>
                        <span class="text-xs text-gray-500">{{ pendingTotal }} pending</span>
                    </div>
                    <div class="divide-y divide-white/5">
                        <div v-for="(item, i) in pendingQueue" :key="item.id" class="px-4 py-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-gray-500 w-5 text-right">{{ i + 1 }}</span>
                                <div>
                                    <span class="text-sm font-medium text-gray-200">{{ item.map_name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ item.player_name }}</span>
                                    <span class="text-xs text-gray-600 uppercase ml-1">{{ item.physics }}</span>
                                    <span class="text-xs text-gray-500 font-mono ml-1">{{ formatTime(item.time_ms) }}</span>
                                    <span v-if="item.record?.rank" class="text-xs text-gray-600 ml-1">#{{ item.record.rank }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="item.priority < 3" class="text-xs px-1.5 py-0.5 rounded font-medium"
                                    :class="item.priority === 1 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-blue-500/20 text-blue-400'">
                                    {{ priorityLabel(item.priority) }}
                                </span>
                                <span :class="sourceColor(item.source)" class="text-xs px-1.5 py-0.5 rounded font-medium">
                                    {{ sourceLabel(item.source) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Video Grid -->
            <div v-if="videos.data.length === 0 && !currentlyRendering && (!pendingQueue || pendingQueue.length === 0)" class="text-center py-20 text-gray-400">
                No rendered videos yet.
            </div>

            <div v-if="videos.data.length > 0">
                <h2 v-if="currentlyRendering || (pendingQueue && pendingQueue.length > 0)" class="text-lg font-bold text-white mb-4">Completed</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div
                        v-for="video in videos.data"
                        :key="video.id"
                        class="bg-white/5 border border-white/10 rounded-lg overflow-hidden hover:border-white/20 transition-all"
                    >
                        <!-- Thumbnail / Embedded Player -->
                        <div class="relative aspect-video bg-black cursor-pointer" @click="toggleVideo(video.id)">
                            <template v-if="expandedVideo === video.id">
                                <iframe
                                    :src="`https://www.youtube.com/embed/${video.youtube_video_id}?autoplay=1`"
                                    class="absolute inset-0 w-full h-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                            <template v-else>
                                <img
                                    :src="`https://img.youtube.com/vi/${video.youtube_video_id}/mqdefault.jpg`"
                                    :alt="video.map_name"
                                    class="absolute inset-0 w-full h-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/10 transition-colors">
                                    <svg class="w-12 h-12 text-white/80" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </template>
                        </div>

                        <!-- Video Info -->
                        <div class="p-3">
                            <div class="flex items-center justify-between mb-1">
                                <Link
                                    :href="`/maps/${video.map_name}`"
                                    class="text-sm font-semibold text-white hover:text-blue-400 transition-colors truncate"
                                >
                                    {{ video.map_name }}
                                </Link>
                                <span :class="sourceColor(video.source)" class="text-xs px-1.5 py-0.5 rounded font-medium ml-2 shrink-0">
                                    {{ sourceLabel(video.source) }}
                                </span>
                            </div>

                            <div class="text-xs text-gray-400 space-y-0.5">
                                <div class="flex items-center justify-between">
                                    <span>{{ video.player_name }}</span>
                                    <span class="font-mono">{{ formatTime(video.time_ms) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="uppercase">{{ video.physics }}</span>
                                    <span v-if="video.render_duration_seconds" class="text-gray-500">
                                        Render time: {{ formatDuration(video.render_duration_seconds) }}
                                    </span>
                                </div>
                                <div v-if="video.requested_by" class="text-gray-500 truncate">
                                    by {{ video.requested_by }}
                                </div>
                            </div>

                            <a
                                :href="video.youtube_url"
                                target="_blank"
                                rel="noopener"
                                class="mt-2 flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 transition-colors"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                                    <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/>
                                </svg>
                                Watch on YouTube
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="videos.last_page > 1" class="mt-8">
                <Pagination
                    :last_page="videos.last_page"
                    :current_page="videos.current_page"
                    link="/youtube"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes progress-indeterminate {
    0% { transform: translateX(-100%); width: 40%; }
    50% { transform: translateX(50%); width: 60%; }
    100% { transform: translateX(250%); width: 40%; }
}
.animate-progress-indeterminate {
    animation: progress-indeterminate 2s ease-in-out infinite;
}
</style>
