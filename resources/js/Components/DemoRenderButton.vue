<script setup>
    const props = defineProps({
        renderedVideo: Object,
        canRequestRender: Boolean,
        renderRequesting: Boolean,
        renderRequested: Boolean,
        renderError: String,
        borderColor: {
            type: String,
            default: 'border-blue-500/50'
        }
    });

    const emit = defineEmits(['confirm-render', 'toggle-youtube']);
</script>

<template>
    <button
        v-if="renderedVideo"
        @click.stop.prevent="emit('toggle-youtube')" @mousedown.stop.prevent
        class="inline-flex items-center gap-0 py-0.5 px-1.5 border-l transition-all bg-red-500/15 text-red-400 hover:bg-red-500/25 hover:text-red-300"
        :class="borderColor"
        title="Watch on YouTube"
    >
        <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
        <span class="max-w-0 group-hover:max-w-[60px] overflow-hidden transition-all duration-200 whitespace-nowrap">
            <span class="ml-1">YouTube</span>
        </span>
    </button>
    <button
        v-else-if="canRequestRender"
        @click.stop.prevent="emit('confirm-render')" @mousedown.stop.prevent
        :disabled="renderRequesting"
        class="inline-flex items-center gap-0 py-0.5 px-1.5 border-l transition-all"
        :class="[borderColor, renderRequested ? 'bg-green-500/15 text-green-400' : renderError ? 'bg-red-500/15 text-red-400' : 'bg-red-500/10 text-red-400 hover:bg-red-500/20 hover:text-red-300']"
        :title="renderError || 'Request YouTube render'"
    >
        <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
        <span class="max-w-0 group-hover:max-w-[60px] overflow-hidden transition-all duration-200 whitespace-nowrap" :class="{ '!max-w-[60px]': renderRequested || renderError }">
            <span class="ml-1">{{ renderRequesting ? '...' : renderRequested ? 'queued' : renderError ? 'error' : 'render' }}</span>
        </span>
    </button>
</template>
