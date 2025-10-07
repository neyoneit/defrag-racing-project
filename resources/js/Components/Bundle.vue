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
    <div class="group relative overflow-hidden rounded-xl backdrop-blur-xl bg-white/5 border border-white/5 hover:border-white/20 transition-all duration-300 hover:scale-[1.01]">
        <div class="relative p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-xl font-black text-white mb-2 group-hover:text-gray-200 transition-colors">
                        {{ bundle.name }}
                    </h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ bundle.description }}</p>
                </div>

                <!-- Download Button -->
                <a
                    :href="getUrl"
                    class="flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 text-white font-bold transition-all duration-300 flex-shrink-0 group/btn"
                    title="Download">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover/btn:translate-y-0.5 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                    </svg>
                    <span>Download</span>
                </a>
            </div>
        </div>
    </div>
</template>
