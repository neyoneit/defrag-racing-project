<script setup>
    import { ref } from 'vue';
    import { Head, Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';

    const props = defineProps({
        stats: Object,
        videos: Object,
    });

    const expandedVideo = ref(null);

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
</script>

<template>
    <div class="min-h-screen">
        <Head title="YouTube - Rendered Demos" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <h1 class="text-4xl md:text-5xl font-black text-white mb-6">YouTube</h1>

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

        <!-- Video Grid -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-2 pb-12">
            <div v-if="videos.data.length === 0" class="text-center py-20 text-gray-400">
                No rendered videos yet.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
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
                            <!-- Play button overlay -->
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
                                    Render: {{ formatDuration(video.render_duration_seconds) }}
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
