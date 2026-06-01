<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'servers' },
});

const STORAGE_KEYS = {
    servers: 'launcher_banner_dismissed_servers',
    demos: 'launcher_banner_dismissed_demos',
};

const dismissed = ref(true);

onMounted(() => {
    const key = STORAGE_KEYS[props.variant] || STORAGE_KEYS.servers;
    dismissed.value = localStorage.getItem(key) === '1';
});

const dismiss = () => {
    dismissed.value = true;
    const key = STORAGE_KEYS[props.variant] || STORAGE_KEYS.servers;
    localStorage.setItem(key, '1');
};

const copy = computed(() => {
    if (props.variant === 'demos') return {
        title: 'Never lose a demo again',
        text: 'The Defrag Racing Launcher watches your demos folder and uploads new runs to defrag.racing automatically.',
    };
    return {
        title: 'Make the Connect button work',
        text: 'The Defrag Racing Launcher opens defrag:// links in Q3 and lets you browse servers from your desktop.',
    };
});
</script>

<template>
    <div v-if="!dismissed"
         class="mb-4 rounded-xl border border-blue-500/30 bg-gradient-to-r from-blue-950/60 to-blue-900/20 px-4 py-3 flex items-start gap-3">
        <div class="shrink-0 mt-0.5 w-9 h-9 rounded-lg bg-blue-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white">{{ copy.title }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ copy.text }}</div>
        </div>
        <Link :href="route('launcher')"
              class="shrink-0 self-center px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors whitespace-nowrap">
            Get the launcher
        </Link>
        <button type="button"
                @click="dismiss"
                class="shrink-0 self-start p-1 rounded-lg hover:bg-white/10 text-gray-400 hover:text-gray-200 transition-colors"
                aria-label="Dismiss">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
</template>
