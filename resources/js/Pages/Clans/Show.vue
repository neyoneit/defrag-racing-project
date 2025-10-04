<script setup>
    import { Head, Link } from '@inertiajs/vue3';

    const props = defineProps({
        clan: Object,
        players: Array,
    });
</script>

<template>
    <div class="min-h-screen">
        <Head :title="clan.plain_name" />

        <!-- Hero Section with Background -->
        <div class="relative h-[400px] overflow-hidden">
            <!-- Background Image -->
            <div class="absolute inset-0">
                <div class="absolute inset-0">
                    <img
                        v-if="clan.background"
                        :src="`/storage/${clan.background}`"
                        class="w-full h-full object-cover"
                        alt="Clan Background"
                    />
                    <div v-else class="w-full h-full bg-gradient-to-br from-blue-900/20 via-blue-800/20 to-blue-700/20"></div>
                </div>
                <!-- Fade image to transparent at bottom -->
                <div v-if="clan.background" class="absolute inset-0 bg-gradient-to-b from-transparent from-0% via-transparent via-60% to-gray-900 to-100%"></div>
                <!-- Dark overlay on top -->
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/30 to-transparent"></div>
            </div>

            <!-- Clan Info -->
            <div class="relative h-full flex flex-col items-center justify-center px-4">
                <!-- Clan Logo with glow effect -->
                <div class="relative group mb-6">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-blue-600 blur-2xl opacity-50 group-hover:opacity-75 transition-opacity duration-500 rounded-full"></div>
                    <img
                        class="relative h-32 w-32 rounded-full object-cover border-4 border-white/20 shadow-2xl transition-transform duration-500 group-hover:scale-110"
                        :src="`/storage/${clan.image}`"
                        :alt="clan.plain_name"
                    />
                </div>

                <!-- Clan Name -->
                <h1 class="text-6xl font-black text-white mb-4 drop-shadow-2xl" v-html="q3tohtml(clan.name)"></h1>

                <!-- Stats Bar -->
                <div class="flex items-center gap-6 bg-white/10 backdrop-blur-xl rounded-full px-8 py-3 border border-white/20 shadow-2xl">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <span class="text-white font-bold">{{ players.length }}</span>
                        <span class="text-gray-300 text-sm">Members</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 mt-8 relative z-10 pb-20">
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <!-- Section Header -->
                <div class="bg-gradient-to-r from-blue-600/20 to-blue-800/20 border-b border-white/10 px-8 py-6">
                    <h2 class="text-3xl font-black text-white flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span>Roster</span>
                    </h2>
                </div>

                <!-- Members Grid -->
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Link
                            v-for="player in players"
                            :key="player.id"
                            :href="route('profile.index', player.id)"
                            class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 hover:border-blue-500/50 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-blue-500/20"
                        >
                            <!-- Card Background Glow -->
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-600/0 to-blue-700/0 group-hover:from-blue-600/10 group-hover:to-blue-700/10 transition-all duration-500"></div>

                            <!-- Content -->
                            <div class="relative p-6">
                                <!-- Player Header -->
                                <div class="flex items-center gap-4 mb-4">
                                    <!-- Avatar with ring -->
                                    <div class="relative">
                                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-blue-600 blur-lg opacity-0 group-hover:opacity-50 transition-opacity duration-500 rounded-full"></div>
                                        <img
                                            class="relative h-16 w-16 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-blue-500/60 transition-all duration-300"
                                            :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                            :alt="player.name"
                                        />
                                    </div>

                                    <!-- Player Name & Country -->
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-bold text-white group-hover:text-blue-400 transition-colors truncate mb-1" v-html="q3tohtml(player.name)"></div>
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="player.country"
                                                :src="`/images/flags/${player.country}.png`"
                                                class="w-5 h-4 opacity-80"
                                                onerror="this.src='/images/flags/_404.png'"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Member Note -->
                                <div v-if="player.pivot?.note" class="mt-4 pt-4 border-t border-white/10">
                                    <div class="text-sm text-gray-300 leading-relaxed prose prose-invert prose-sm max-w-none line-clamp-3" v-html="player.pivot.note"></div>
                                </div>

                                <!-- Config File Download -->
                                <div v-if="player.pivot?.config_file" class="mt-4 pt-4 border-t border-white/10" @click.prevent>
                                    <a
                                        :href="`/storage/${player.pivot.config_file}`"
                                        download
                                        class="flex items-center justify-center gap-2 px-3 py-2 bg-blue-600/20 hover:bg-blue-600/30 border border-blue-500/30 hover:border-blue-500/50 rounded-lg text-blue-400 hover:text-blue-300 transition-all duration-300"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        <span class="text-sm font-medium">Download Config</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                                </svg>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
