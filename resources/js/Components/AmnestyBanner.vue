<script setup>
import { Link } from '@inertiajs/vue3';

// Sits in the header row of the pages that show times, next to the heading,
// the way Servers carries its launcher and rules links. Permanent and small:
// the point is that somebody looking at a leaderboard always has the way out
// in view, not that they are told about it once in a strip they close.
</script>

<template>
    <Link href="/amnesty"
        class="flex items-center gap-2 bg-gradient-to-r from-emerald-600/30 to-emerald-500/15 hover:from-emerald-600/40 hover:to-emerald-500/25 backdrop-blur-sm px-3 py-2 rounded-lg border border-emerald-400/40 hover:border-emerald-300/60 transition-colors text-sm">
        <svg class="w-5 h-5 text-emerald-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
        </svg>
        <span class="font-bold text-white whitespace-nowrap">Amnesty</span>
        <span class="hidden sm:inline text-emerald-200/80 font-semibold text-xs">take your own invalid times down, privately</span>
    </Link>
</template>
