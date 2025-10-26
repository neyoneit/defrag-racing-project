<script>
    import MainLayout from '@/Layouts/MainLayout.vue'
    import { Link } from '@inertiajs/vue3';

    export default {
        layout: MainLayout,
    }
</script>

<script setup>
    import { Head, usePage } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        maps: Array,
        servers: Array,
        latestAnnouncement: Object,
        recentAnnouncements: Array,
        totalMaps: Number,
        activeServers: Number,
        activePlayers: Number
    });

    const page = usePage();
    const isAuthenticated = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen">
        <Head title="Home" />

        <!-- Hero Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-8">
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white mb-4 leading-tight">
                        Push Your Speed <span class="text-blue-500">To The Limit</span>
                    </h1>

                    <p class="text-base text-gray-400 mb-6 max-w-3xl mx-auto leading-relaxed">
                        Join the ultimate Quake 3 Defrag community. Master physics, dominate leaderboards,
                        and compete against the best speedrunners in the world.
                    </p>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-4xl mx-auto mb-8">
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-4 border border-white/10 text-center hover:border-blue-500/50 transition-all">
                            <div class="text-2xl md:text-3xl font-black text-blue-400 mb-1">{{ totalMaps }}</div>
                            <div class="text-gray-400 font-semibold text-xs">Total Maps</div>
                        </div>
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-4 border border-white/10 text-center hover:border-blue-500/50 transition-all">
                            <div class="text-2xl md:text-3xl font-black text-green-400 mb-1">{{ activeServers }}</div>
                            <div class="text-gray-400 font-semibold text-xs">Active Servers</div>
                        </div>
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-4 border border-white/10 text-center hover:border-blue-500/50 transition-all">
                            <div class="text-2xl md:text-3xl font-black text-purple-400 mb-1">{{ activePlayers }}</div>
                            <div class="text-gray-400 font-semibold text-xs">Players (30d)</div>
                        </div>
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-4 border border-white/10 text-center hover:border-blue-500/50 transition-all">
                            <div class="text-2xl md:text-3xl font-black text-orange-400 mb-1">1M+</div>
                            <div class="text-gray-400 font-semibold text-xs">Records Stored</div>
                        </div>
                    </div>

                    <!-- Latest News -->
                    <Link
                        v-if="latestAnnouncement"
                        :href="route('announcements')"
                        class="relative flex items-start gap-4 px-6 py-4 backdrop-blur-xl bg-white/5 border border-white/10 rounded-xl text-gray-300 hover:bg-white/10 hover:border-blue-500/50 transition-all group max-w-4xl mx-auto"
                    >
                        <div class="absolute -top-2 -right-2 px-3 py-1 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full text-xs font-black text-white shadow-lg">
                            NEW
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-500 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                        </svg>
                        <div class="flex-1">
                            <div class="text-xs uppercase font-bold tracking-wider mb-1 text-blue-400">
                                Latest News
                            </div>
                            <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors mb-2">{{ latestAnnouncement.title }}</h3>
                            <div class="text-sm text-gray-400 line-clamp-2" v-html="latestAnnouncement.text"></div>
                        </div>
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- How to Get Started Section -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 -mt-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl md:text-4xl font-black text-white mb-3">Get Started in Minutes</h2>
                <p class="text-gray-400 text-base">Follow these simple steps to join the Defrag racing community</p>
            </div>

            <!-- Steps Timeline -->
            <div class="space-y-8">
                <!-- Step 1: Register -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            1
                        </div>
                        <div class="hidden md:block w-1 h-full bg-blue-500/30 mt-4"></div>
                    </div>
                    <Link v-if="!isAuthenticated" :href="route('register')" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Create Your Account</h3>
                                <p class="text-gray-400">Register to track your records, compete on leaderboards, and join the global ranking system.</p>
                            </div>
                        </div>
                    </Link>
                    <div v-else class="flex-1 backdrop-blur-xl bg-green-500/10 rounded-xl border border-green-500/30 p-8">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-green-500/20 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-green-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2">Account Created ✓</h3>
                                <p class="text-gray-400">You're all set! Your records are being tracked automatically.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Download -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            2
                        </div>
                        <div class="hidden md:block w-1 h-full bg-blue-500/30 mt-4"></div>
                    </div>
                    <Link :href="route('bundles')" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Download the Game</h3>
                                <p class="text-gray-400">Get the complete Defrag bundle with Quake 3, maps, configs, and everything ready to play. One-click install.</p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Step 3: Join Discord -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            3
                        </div>
                        <div class="hidden md:block w-1 h-full bg-blue-500/30 mt-4"></div>
                    </div>
                    <a href="https://discord.gg/defrag" target="_blank" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Join Our Discord</h3>
                                <p class="text-gray-400">Connect with the community, get help, find teammates, and stay updated on events and tournaments.</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Step 4: Explore Maps -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            4
                        </div>
                        <div class="hidden md:block w-1 h-full bg-blue-500/30 mt-4"></div>
                    </div>
                    <Link :href="route('maps')" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Explore Maps & Records</h3>
                                <p class="text-gray-400">Browse hundreds of maps with filters by physics (CPM/VQ3), difficulty, and style. Study world records to improve.</p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Step 5: Customize Profile -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            5
                        </div>
                        <div class="hidden md:block w-1 h-full bg-blue-500/30 mt-4"></div>
                    </div>
                    <Link v-if="isAuthenticated" :href="route('profile.index', { userId: page.props.auth.user.mdd_id })" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Customize Your Profile</h3>
                                <p class="text-gray-400">Create a colorful Quake 3 nickname with color codes (^1Red ^2Green ^4Blue), upload background, and showcase your style.</p>
                                <div class="mt-3 inline-block px-4 py-2 bg-black/40 rounded font-mono text-sm">
                                    <span class="text-red-500">^1Pro</span><span class="text-white">^7</span><span class="text-blue-500">^4Player</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                    <div v-else class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 opacity-60">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-gray-500/20 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-gray-400 mb-2">Customize Your Profile</h3>
                                <p class="text-gray-500">Register to unlock profile customization with Quake 3 color codes and backgrounds.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 6: Join Servers -->
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-shrink-0 w-full md:w-20 flex md:flex-col items-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white font-black text-2xl">
                            6
                        </div>
                    </div>
                    <Link :href="route('servers')" class="flex-1 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 p-8 hover:border-blue-500/50 hover:bg-white/10 transition-all group">
                        <div class="flex items-start gap-6">
                            <div class="p-4 bg-blue-500/20 rounded-xl group-hover:bg-blue-500/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-black text-white mb-2 group-hover:text-blue-400 transition-colors">Connect to Servers & Play</h3>
                                <p class="text-gray-400">Find live servers with active players. Practice, compete, and automatically submit your records to the database.</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

        </div>
    </div>
</template>

