<script setup>
    import { Link, usePage } from '@inertiajs/vue3';

    const page = usePage()
    const notifications = page.props.recordsNotifications || []

    defineProps({
        ping: Boolean
    });
</script>

<template>
    <Link :href="route('notifications.index')">
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
    </Link>
</template>
