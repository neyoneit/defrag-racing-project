<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { ref, computed } from 'vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import axios from 'axios';

    const props = defineProps({
        recordNotificationsPage: Object,
        systemNotificationsPage: Object,
        activeTab: {
            type: String,
            default: 'records'
        }
    });

    const activeSystemTab = ref('announcements');
    const activeRecordTab = ref('all');

    const timeDiff = (time, bettertime) => {
        return Math.abs(bettertime - time);
    };

    const totalCount = computed(() => {
        return (props.recordNotificationsPage?.length || 0) + (props.systemNotificationsPage?.total || 0);
    });

    const toggleNotificationRead = (notificationId) => {
        axios.post(route('notifications.toggle', notificationId)).then((response) => {
            const notification = props.recordNotificationsPage.find(n => n.id === notificationId);
            if (notification) {
                notification.read = response.data.read;
            }
        });
    };

    const markAllAsRead = () => {
        axios.post(route('notifications.clear')).then(() => {
            props.recordNotificationsPage.forEach(n => n.read = true);
        });
    };

    const markAllAsUnread = () => {
        axios.post(route('notifications.mark.unread')).then(() => {
            props.recordNotificationsPage.forEach(n => n.read = false);
        });
    };

    // System notifications functions
    const toggleSystemNotificationRead = (notificationId) => {
        axios.post(route('notifications.system.toggle', notificationId)).then((response) => {
            const notification = props.systemNotificationsPage.data.find(n => n.id === notificationId);
            if (notification) {
                notification.read = response.data.read;
            }
        });
    };

    const markAllSystemAsRead = () => {
        axios.post(route('notifications.system.clear')).then(() => {
            props.systemNotificationsPage.data.forEach(n => n.read = true);
        });
    };

    const markAllSystemAsUnread = () => {
        axios.post(route('notifications.system.mark.unread')).then(() => {
            props.systemNotificationsPage.data.forEach(n => n.read = false);
        });
    };

    // Filter record notifications by sub-tab category
    const filteredRecordNotifications = computed(() => {
        const notifications = props.recordNotificationsPage || [];

        if (activeRecordTab.value === 'beaten') {
            return notifications.filter(n => !n.worldrecord);
        } else if (activeRecordTab.value === 'worldrecords') {
            return notifications.filter(n => n.worldrecord);
        }

        return notifications;
    });

    // Count notifications for each record sub-tab
    const recordTabCounts = computed(() => {
        const notifications = props.recordNotificationsPage || [];
        return {
            all: notifications.length,
            beaten: notifications.filter(n => !n.worldrecord).length,
            worldrecords: notifications.filter(n => n.worldrecord).length
        };
    });

    // Filter system notifications by sub-tab category
    const filteredSystemNotifications = computed(() => {
        const notifications = props.systemNotificationsPage?.data || [];

        if (activeSystemTab.value === 'announcements') {
            return notifications.filter(n => n.type === 'announcement');
        } else if (activeSystemTab.value === 'clan') {
            return notifications.filter(n => ['clan_invite', 'clan_kick', 'clan_accept', 'clan_leave', 'clan_transfer'].includes(n.type));
        } else if (activeSystemTab.value === 'tournament') {
            return notifications.filter(n => ['tournament_start', 'round_start', 'round_end'].includes(n.type));
        }

        return notifications;
    });

    // Count notifications for each sub-tab
    const systemTabCounts = computed(() => {
        const notifications = props.systemNotificationsPage?.data || [];
        return {
            announcements: notifications.filter(n => n.type === 'announcement').length,
            clan: notifications.filter(n => ['clan_invite', 'clan_kick', 'clan_accept', 'clan_leave', 'clan_transfer'].includes(n.type)).length,
            tournament: notifications.filter(n => ['tournament_start', 'round_start', 'round_end'].includes(n.type)).length
        };
    });

    // Get icon and color for notification type
    const getNotificationTypeInfo = (type) => {
        const types = {
            'announcement': {
                label: 'Announcements',
                icon: 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46',
                bgColor: 'bg-blue-500/20',
                borderColor: 'border-blue-500/30',
                iconColor: 'text-blue-400'
            },
            'clan_invite': {
                label: 'Clan Invitations',
                icon: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
                bgColor: 'bg-green-500/20',
                borderColor: 'border-green-500/30',
                iconColor: 'text-green-400'
            },
            'clan_kick': {
                label: 'Clan Kicks',
                icon: 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
                bgColor: 'bg-red-500/20',
                borderColor: 'border-red-500/30',
                iconColor: 'text-red-400'
            },
            'clan_accept': {
                label: 'Clan Acceptances',
                icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                bgColor: 'bg-purple-500/20',
                borderColor: 'border-purple-500/30',
                iconColor: 'text-purple-400'
            },
            'clan_leave': {
                label: 'Clan Leaves',
                icon: 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75',
                bgColor: 'bg-orange-500/20',
                borderColor: 'border-orange-500/30',
                iconColor: 'text-orange-400'
            },
            'clan_transfer': {
                label: 'Clan Transfers',
                icon: 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
                bgColor: 'bg-yellow-500/20',
                borderColor: 'border-yellow-500/30',
                iconColor: 'text-yellow-400'
            },
            'tournament_start': {
                label: 'Tournament Starts',
                icon: 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0',
                bgColor: 'bg-pink-500/20',
                borderColor: 'border-pink-500/30',
                iconColor: 'text-pink-400'
            },
            'round_start': {
                label: 'Round Starts',
                icon: 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z',
                bgColor: 'bg-cyan-500/20',
                borderColor: 'border-cyan-500/30',
                iconColor: 'text-cyan-400'
            },
            'round_end': {
                label: 'Round Ends',
                icon: 'M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z',
                bgColor: 'bg-indigo-500/20',
                borderColor: 'border-indigo-500/30',
                iconColor: 'text-indigo-400'
            }
        };

        return types[type] || {
            label: 'Other Notifications',
            icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            bgColor: 'bg-gray-500/20',
            borderColor: 'border-gray-500/30',
            iconColor: 'text-gray-400'
        };
    };
</script>

<template>
    <div class="min-h-screen">
        <Head title="Notifications" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Notifications</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span class="text-sm font-semibold">{{ totalCount }} total notifications</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-8">
            <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">

                <!-- Modern Tab Navigation -->
                <div class="flex border-b border-white/10">
                    <Link
                        :href="route('notifications.index')"
                        class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                        :class="activeTab === 'records'
                            ? 'text-orange-400 bg-orange-500/10'
                            : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    >
                        <div class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                            </svg>
                            <span>Record Notifications</span>
                            <span v-if="recordNotificationsPage?.length" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                  :class="activeTab === 'records' ? 'bg-orange-500/30 text-orange-300' : 'bg-white/10 text-gray-400'">
                                {{ recordNotificationsPage?.length }}
                            </span>
                        </div>
                        <div v-if="activeTab === 'records'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-orange-400 to-transparent"></div>
                    </Link>

                    <Link
                        :href="route('notifications.system.index')"
                        class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                        :class="activeTab === 'system'
                            ? 'text-blue-400 bg-blue-500/10'
                            : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    >
                        <div class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                            </svg>
                            <span>System Notifications</span>
                            <span v-if="systemNotificationsPage?.total" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                  :class="activeTab === 'system' ? 'bg-blue-500/30 text-blue-300' : 'bg-white/10 text-gray-400'">
                                {{ systemNotificationsPage?.total }}
                            </span>
                        </div>
                        <div v-if="activeTab === 'system'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-blue-400 to-transparent"></div>
                    </Link>
                </div>

                <!-- Record Notifications Content -->
                <div v-if="activeTab === 'records'">
                    <!-- Sub-tabs -->
                    <div class="flex border-b border-white/10 bg-black/20">
                        <button
                            @click="activeRecordTab = 'all'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeRecordTab === 'all'
                                ? 'text-orange-400 bg-orange-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                                </svg>
                                <span>All Records</span>
                                <span v-if="recordTabCounts.all" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeRecordTab === 'all' ? 'bg-orange-500/30 text-orange-300' : 'bg-white/10 text-gray-400'">
                                    {{ recordTabCounts.all }}
                                </span>
                            </div>
                            <div v-if="activeRecordTab === 'all'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-orange-400 to-transparent"></div>
                        </button>

                        <button
                            @click="activeRecordTab = 'beaten'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeRecordTab === 'beaten'
                                ? 'text-blue-400 bg-blue-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                </svg>
                                <span>Beaten Records</span>
                                <span v-if="recordTabCounts.beaten" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeRecordTab === 'beaten' ? 'bg-blue-500/30 text-blue-300' : 'bg-white/10 text-gray-400'">
                                    {{ recordTabCounts.beaten }}
                                </span>
                            </div>
                            <div v-if="activeRecordTab === 'beaten'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-blue-400 to-transparent"></div>
                        </button>

                        <button
                            @click="activeRecordTab = 'worldrecords'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeRecordTab === 'worldrecords'
                                ? 'text-yellow-400 bg-yellow-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                </svg>
                                <span>Beaten World Records</span>
                                <span v-if="recordTabCounts.worldrecords" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeRecordTab === 'worldrecords' ? 'bg-yellow-500/30 text-yellow-300' : 'bg-white/10 text-gray-400'">
                                    {{ recordTabCounts.worldrecords }}
                                </span>
                            </div>
                            <div v-if="activeRecordTab === 'worldrecords'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-yellow-400 to-transparent"></div>
                        </button>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="flex items-center justify-end gap-2 p-4 bg-black/20 border-b border-white/10">
                        <button @click="markAllAsRead" class="px-4 py-2 rounded-lg bg-green-500/20 hover:bg-green-500/30 border border-green-500/30 text-green-400 font-semibold text-sm transition-all flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Mark All as Read
                        </button>
                        <button @click="markAllAsUnread" class="px-4 py-2 rounded-lg bg-yellow-500/20 hover:bg-yellow-500/30 border border-yellow-500/30 text-yellow-400 font-semibold text-sm transition-all flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                            </svg>
                            Mark All as Unread
                        </button>
                    </div>

                    <!-- Filtered Notifications -->
                    <div class="divide-y divide-white/5">
                        <div v-for="notification in filteredRecordNotifications" :key="notification.id" class="group p-2 hover:bg-white/5 transition-all" :class="{'opacity-50': notification.read}">
                            <div class="flex items-center gap-3">
                                <!-- Icon -->
                                <div class="shrink-0">
                                    <div class="w-8 h-8 rounded-lg bg-orange-500/20 border border-orange-500/30 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-orange-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Content - Single Line -->
                                <div class="flex-1 min-w-0 text-sm text-gray-300 flex items-center gap-2 flex-wrap">
                                    <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${notification.country}.png`" class="w-5 h-4 rounded-sm shadow-sm shrink-0">
                                    <span class="font-bold text-white" v-html="q3tohtml(notification.name)"></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-bold uppercase shrink-0" :class="{'bg-purple-500/30 text-purple-300': notification.physics.includes('cpm'), 'bg-blue-500/30 text-blue-300': !notification.physics.includes('cpm')}">
                                        {{ notification.physics }}
                                    </span>
                                    <span class="text-gray-500">broke your time on</span>
                                    <Link class="text-blue-400 hover:text-blue-300 font-semibold transition-colors" :href="route('maps.map', notification.mapname)">{{ notification.mapname }}</Link>
                                    <span class="text-gray-500">with</span>
                                    <span class="font-bold text-white">{{ formatTime(notification.time) }}</span>
                                    <span class="text-green-400 font-semibold">(+{{ formatTime(timeDiff(notification.my_time, notification.time)) }})</span>
                                </div>

                                <!-- Read/Unread Toggle -->
                                <button @click="toggleNotificationRead(notification.id)" class="shrink-0 p-2 rounded-lg hover:bg-white/5 transition-all" :title="notification.read ? 'Mark as unread' : 'Mark as read'">
                                    <svg v-if="!notification.read" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredRecordNotifications.length === 0" class="p-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto text-gray-600 mb-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="text-xl font-bold text-gray-400 mb-2">No notifications</h3>
                        <p class="text-gray-500">No notifications in this category.</p>
                    </div>
                </div>

                <!-- System Notifications Content -->
                <div v-if="activeTab === 'system'">
                    <!-- Sub-tabs -->
                    <div class="flex border-b border-white/10 bg-black/20">
                        <button
                            @click="activeSystemTab = 'announcements'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeSystemTab === 'announcements'
                                ? 'text-blue-400 bg-blue-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                </svg>
                                <span>Announcements</span>
                                <span v-if="systemTabCounts.announcements" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeSystemTab === 'announcements' ? 'bg-blue-500/30 text-blue-300' : 'bg-white/10 text-gray-400'">
                                    {{ systemTabCounts.announcements }}
                                </span>
                            </div>
                            <div v-if="activeSystemTab === 'announcements'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-blue-400 to-transparent"></div>
                        </button>

                        <button
                            @click="activeSystemTab = 'clan'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeSystemTab === 'clan'
                                ? 'text-green-400 bg-green-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                                <span>Clan</span>
                                <span v-if="systemTabCounts.clan" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeSystemTab === 'clan' ? 'bg-green-500/30 text-green-300' : 'bg-white/10 text-gray-400'">
                                    {{ systemTabCounts.clan }}
                                </span>
                            </div>
                            <div v-if="activeSystemTab === 'clan'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-green-400 to-transparent"></div>
                        </button>

                        <button
                            @click="activeSystemTab = 'tournament'"
                            class="flex-1 px-6 py-4 text-center font-semibold transition-all relative group"
                            :class="activeSystemTab === 'tournament'
                                ? 'text-pink-400 bg-pink-500/10'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                </svg>
                                <span>Tournament</span>
                                <span v-if="systemTabCounts.tournament" class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="activeSystemTab === 'tournament' ? 'bg-pink-500/30 text-pink-300' : 'bg-white/10 text-gray-400'">
                                    {{ systemTabCounts.tournament }}
                                </span>
                            </div>
                            <div v-if="activeSystemTab === 'tournament'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-pink-400 to-transparent"></div>
                        </button>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="flex items-center justify-end gap-2 p-4 bg-black/20 border-b border-white/10">
                        <button @click="markAllSystemAsRead" class="px-4 py-2 rounded-lg bg-green-500/20 hover:bg-green-500/30 border border-green-500/30 text-green-400 font-semibold text-sm transition-all flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Mark All as Read
                        </button>
                        <button @click="markAllSystemAsUnread" class="px-4 py-2 rounded-lg bg-yellow-500/20 hover:bg-yellow-500/30 border border-yellow-500/30 text-yellow-400 font-semibold text-sm transition-all flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                            </svg>
                            Mark All as Unread
                        </button>
                    </div>

                    <!-- Filtered Notifications -->
                    <div class="divide-y divide-white/5">
                        <div v-for="notification in filteredSystemNotifications" :key="notification.id" class="group p-4 hover:bg-white/5 transition-all" :class="{'opacity-50': notification.read}">
                            <div class="flex items-center gap-4">
                                <!-- Icon -->
                                <div class="shrink-0">
                                    <div :class="[getNotificationTypeInfo(notification.type).bgColor, getNotificationTypeInfo(notification.type).borderColor, 'w-10 h-10 rounded-lg border flex items-center justify-center']">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" :class="['w-5 h-5', getNotificationTypeInfo(notification.type).iconColor]">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="getNotificationTypeInfo(notification.type).icon" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 text-sm text-gray-300">
                                    <span v-if="notification.type === 'announcement'">Announcement: </span>
                                    <span v-else>{{ notification.before }} </span>
                                    <Link class="text-blue-400 hover:text-blue-300 font-bold transition-colors" :href="notification.url" v-html="q3tohtml(notification.headline)"></Link>
                                    <span v-if="notification.type !== 'announcement'"> {{ notification.after }}</span>
                                </div>

                                <!-- Read/Unread Toggle -->
                                <button @click="toggleSystemNotificationRead(notification.id)" class="shrink-0 p-2 rounded-lg hover:bg-white/5 transition-all" :title="notification.read ? 'Mark as unread' : 'Mark as read'">
                                    <svg v-if="!notification.read" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="flex justify-center p-6 border-t border-white/5" v-if="systemNotificationsPage?.total > systemNotificationsPage?.per_page">
                        <Pagination :current_page="systemNotificationsPage?.current_page" :last_page="systemNotificationsPage?.last_page" :link="systemNotificationsPage?.first_page_url" />
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredSystemNotifications.length === 0" class="p-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto text-gray-600 mb-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="text-xl font-bold text-gray-400 mb-2">No notifications</h3>
                        <p class="text-gray-500">No notifications in this category.</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
