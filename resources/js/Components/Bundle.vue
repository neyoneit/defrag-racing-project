<script setup>
    import { computed } from 'vue';

    const props = defineProps({
        bundle: Object
    });

    const getUrl = computed(() => {
        if (props.bundle.file) {
            return '/storage/' + props.bundle.file
        }

        return props.bundle.url
    })
</script>

<template>
    <a :href="getUrl"
        class="group block p-4 rounded-xl bg-black/40 backdrop-blur-sm border border-white/5 hover:border-cyan-500/30 hover:bg-gray-900/60 transition-all">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-cyan-500/15 border border-cyan-500/20 flex items-center justify-center flex-shrink-0 group-hover:bg-cyan-500/25 transition mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-white group-hover:text-cyan-300 transition">{{ bundle.name }}</div>
                <p v-if="bundle.description" class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">{{ bundle.description }}</p>
            </div>
            <div class="flex-shrink-0 self-center flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/20 group-hover:bg-cyan-500/25 group-hover:border-cyan-500/40 transition">
                <svg class="w-4 h-4 text-cyan-400 group-hover:translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0 0l-6-6m6 6l6-6" /></svg>
                <span class="text-xs font-bold text-cyan-400">Download</span>
            </div>
        </div>
    </a>
</template>
