<script setup>
    import { ref } from 'vue';
    import { useClipboard } from '@/Composables/useClipboard';

    const props = defineProps({
        text: { type: String, required: true },
        size: { type: String, default: 'sm' }, // 'xs', 'sm', 'md'
        label: { type: String, default: '' }, // e.g. 'Copy IP'
    });

    const { copy } = useClipboard();
    const copied = ref(false);
    let timeout = null;

    const doCopy = () => {
        copy(props.text);
        copied.value = true;
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => { copied.value = false; }, 1500);
    };

    const sizeClasses = {
        xs: 'gap-1 px-1 py-0.5',
        sm: 'gap-1 px-1.5 py-0.5',
        md: 'gap-1.5 px-2 py-1',
    };

    const iconSizes = {
        xs: 'w-3 h-3',
        sm: 'w-3.5 h-3.5',
        md: 'w-4 h-4',
    };

    const textSizes = {
        xs: 'text-[9px]',
        sm: 'text-[10px]',
        md: 'text-xs',
    };
</script>

<template>
    <button
        @click.prevent.stop="doCopy"
        class="copy-btn"
        :class="[sizeClasses[size], copied ? 'copy-btn-done' : '']"
        :title="copied ? 'Copied!' : 'Copy'"
    >
        <template v-if="copied">
            <svg :class="iconSizes[size]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <span :class="textSizes[size]" class="font-bold">Copied!</span>
        </template>
        <template v-else>
            <svg :class="iconSizes[size]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-8.25A2.25 2.25 0 0 1 7.5 18v-1.5" />
            </svg>
            <span v-if="label" :class="textSizes[size]" class="font-semibold">{{ label }}</span>
        </template>
    </button>
</template>

<style scoped>
.copy-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.25rem;
    color: #fff;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.4);
    flex-shrink: 0;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}
.copy-btn:hover {
    color: white;
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}
.copy-btn:active {
    transform: scale(0.9);
}
.copy-btn-done {
    color: #4ade80 !important;
    background: rgba(74, 222, 128, 0.15) !important;
    border-color: rgba(74, 222, 128, 0.4) !important;
    animation: copy-pop 0.3s ease;
}
@keyframes copy-pop {
    0% { transform: scale(0.8); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}
</style>
