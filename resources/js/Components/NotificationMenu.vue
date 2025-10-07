<script setup>
    import { onMounted, onUnmounted, ref } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';

    let open = ref(false);

    const page = usePage()

    const notifications = ref(page.props.recordsNotifications)

    defineProps({
        ping: Boolean
    });

    const closeOnEscape = (e) => {
        if (open.value && e.key === 'Escape') {
            open.value = false;
        }
    };

    onMounted(() => document.addEventListener('keydown', closeOnEscape));
    onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

    const timeDiff = (time, bettertime) => {
        return Math.abs(bettertime - time)
    }

    const clearNotifications = () => {
        axios.post(route('notifications.clear')).then(() => {
            notifications.value = [];
        });
    }

</script>

<template>
    <div class="relative">
        <div @click="open = ! open">
            <button v-if="notifications.length == 0" class="p-2 rounded-lg hover:bg-white/5 transition-all relative">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
            </button>

            <button v-else class="p-2 rounded-lg hover:bg-white/5 transition-all relative">
                <div v-if="ping" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-yellow-400 animate-ping"></div>
                <div v-if="ping" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-yellow-400"></div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                </svg>
            </button>
        </div>

        <!-- Backdrop -->
        <div v-show="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <!-- Dropdown -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div v-show="open" class="absolute top-full mt-2 right-0 rounded-xl shadow-2xl z-50" style="width: 420px; max-width: 90vw;" @click.stop>
                <div class="rounded-xl bg-gray-950 backdrop-blur-xl border border-white/10 p-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 font-semibold text-white text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span>Records Notifications</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link :href="route('profile.show') + '#notifications-settings'" class="p-1.5 rounded-lg hover:bg-white/5 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 hover:text-white">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </Link>

                            <button v-if="notifications.length > 0" @click="clearNotifications" class="p-1.5 rounded-lg hover:bg-white/5 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-red-400 hover:text-red-300">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-white/10 mb-3"></div>

                    <!-- Notifications List -->
                    <div style="max-height: 450px;" class="defrag-scrollbar">
                        <div v-for="(recordNotification, index) in notifications" :key="index">
                            <div class="flex gap-3 text-gray-300 p-3 hover:bg-white/5 rounded-lg transition-all text-sm">
                                <div class="shrink-0 mt-0.5 text-orange-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${recordNotification.country}.png`" class="w-4 inline mr-1.5 mb-0.5">
                                    <span class="font-semibold text-white" v-html="q3tohtml(recordNotification.name)"></span>
                                    <span> broke your </span>
                                    <span class="inline-block text-white rounded-md text-xs px-1.5 py-0.5 uppercase font-semibold" :class="{'bg-green-600/20 text-green-400': recordNotification.physics.includes('cpm'), 'bg-blue-600/20 text-blue-400': !recordNotification.physics.includes('cpm')}">
                                        {{ recordNotification.physics }}
                                    </span>
                                    <span> time on </span>
                                    <Link class="text-blue-400 hover:text-blue-300 font-semibold" :href="route('maps.map', recordNotification.mapname)">{{ recordNotification.mapname }}</Link>
                                    <span> with time <span class="font-semibold text-white">{{ formatTime(recordNotification.time) }}</span></span>
                                    <span> (<span class="text-green-400">+{{ formatTime(timeDiff(recordNotification.my_time, recordNotification.time)) }}</span>)</span>
                                </div>
                            </div>
                            <div v-if="index < notifications.length - 1" class="border-t border-white/5 my-2"></div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="notifications.length === 0" class="text-gray-400 py-8">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div class="text-sm text-gray-500">No notifications</div>
                            </div>
                        </div>

                        <!-- View All Link -->
                        <div v-if="notifications.length > 0" class="border-t border-white/10 mt-3 pt-3">
                            <Link :href="route('notifications.index')" class="block text-center text-sm font-medium text-blue-400 hover:text-blue-300 transition-colors">
                                View all notifications
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
