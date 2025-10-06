<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        player: Object
    });
</script>

<template>
    <Link class="group flex items-center gap-3 cursor-pointer rounded-lg hover:bg-white/5 p-2.5 transition-all border border-transparent hover:border-white/10" :href="route(player.mdd ? 'profile.mdd' : 'profile.index', player.id)">
        <!-- Avatar -->
        <img class="h-10 w-10 rounded-full object-cover ring-2 ring-white/10 group-hover:ring-purple-500/40 transition-all" :src="player.profile_photo_path ? '/storage/' + player.profile_photo_path : '/images/null.jpg'" :alt="player.name">

        <!-- Player Info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <img :src="`/images/flags/${player.country}.png`" class="w-5 h-4 shrink-0" onerror="this.src='/images/flags/_404.png'">
                <span class="text-sm font-bold text-white group-hover:text-purple-400 transition-colors truncate" v-html="q3tohtml(player.name)"></span>
            </div>
        </div>
    </Link>
</template>