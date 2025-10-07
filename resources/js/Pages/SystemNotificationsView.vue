<script setup>
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        notifications: Object,
    });
</script>

<template>
    <div class="min-h-screen">
        <Head title="System Notifications" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">System Notifications</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
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
                                <div class="w-10 h-10 rounded-lg bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0 text-sm text-gray-300">
                                <span>{{ notification.before }}</span>
                                <Link class="text-blue-400 hover:text-blue-300 font-bold transition-colors mx-1" :href="notification.url">
                                    {{ notification.headline }}
                                </Link>
                                <span>{{ notification.after }}</span>
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
                    <p class="text-gray-500">You don't have any system notifications yet.</p>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
