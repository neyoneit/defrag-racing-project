<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// Where people look at times - the rankings and the record feed - is where
// somebody remembers the one of theirs that should not be there. The amnesty
// is useless if it is only findable from a submenu, so it comes to them.
//
// Dismissable and remembered, because a permanent strip above a page you visit
// daily stops being a message and becomes furniture.
const STORAGE_KEY = 'amnesty_banner_dismissed';

const dismissed = ref(localStorage.getItem(STORAGE_KEY) === '1');

const dismiss = () => {
    dismissed.value = true;
    localStorage.setItem(STORAGE_KEY, '1');
};
</script>

<template>
    <div v-if="!dismissed" class="relative z-20 border-y border-emerald-400/25 bg-emerald-500/[0.08]">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-2.5 flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>

            <p class="text-sm text-gray-200 min-w-0 flex-1">
                <span class="font-bold text-emerald-200">Standing on a time that should not count?</span>
                Take it down yourself. It is private, nobody is told, and nothing happens to your account.
            </p>

            <Link href="/amnesty"
                class="shrink-0 px-3 py-1.5 rounded-lg bg-emerald-500/80 hover:bg-emerald-500 text-white text-sm font-bold transition-colors">
                Amnesty
            </Link>

            <button type="button" @click="dismiss" title="Dismiss"
                class="shrink-0 p-1.5 rounded-lg text-gray-500 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>
