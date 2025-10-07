<script setup>
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        notifications: Object,
    });

    const timeDiff = (time, bettertime) => {
        return Math.abs(bettertime - time)
    }
</script>

<template>
    <div class="min-h-screen">
        <Head title="Notifications" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Record Notifications</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span class="text-sm font-semibold">{{ notifications.total }} notifications</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-8">
            <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">
                <!-- Notifications List -->
                <div class="divide-y divide-white/5">
                    <div v-for="notification in notifications.data" :key="notification.id" class="group p-4 hover:bg-white/5 transition-all">
                        <div class="flex items-start gap-4">
                            <!-- Icon -->
                            <div class="shrink-0 mt-1">
                                <div class="w-10 h-10 rounded-lg bg-orange-500/20 border border-orange-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-orange-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0 text-sm text-gray-300">
                                <div class="flex items-center gap-2 mb-1">
                                    <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${notification.country}.png`" class="w-5 h-4 rounded-sm shadow-sm">
                                    <span class="font-black text-white" v-html="q3tohtml(notification.name)"></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-bold uppercase" :class="{'bg-purple-500/30 text-purple-300': notification.physics.includes('cpm'), 'bg-blue-500/30 text-blue-300': !notification.physics.includes('cpm')}">
                                        {{ notification.physics }}
                                    </span>
                                </div>

                                <div class="text-gray-400">
                                    broke your time on
                                    <Link class="text-blue-400 hover:text-blue-300 font-semibold transition-colors" :href="route('maps.map', notification.mapname)">{{ notification.mapname }}</Link>
                                    with
                                    <span class="font-bold text-white">{{ formatTime(notification.time) }}</span>
                                    <span class="text-green-400 font-semibold">(+{{ formatTime(timeDiff(notification.my_time, notification.time)) }})</span>
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    {{ timeSince(notification.date_set) }} ago
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex justify-center p-6 border-t border-white/5" v-if="notifications.total > notifications.per_page">
                    <Pagination :current_page="notifications.current_page" :last_page="notifications.last_page" :link="notifications.first_page_url" />
                </div>

                <!-- Empty State -->
                <div v-if="notifications.data.length === 0" class="p-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto text-gray-600 mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No notifications</h3>
                    <p class="text-gray-500">You don't have any record notifications yet.</p>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
