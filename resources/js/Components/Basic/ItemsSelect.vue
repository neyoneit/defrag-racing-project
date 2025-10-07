<script setup>
    import { ref, computed } from 'vue';

    const emit = defineEmits(['update:modelValue'])

    const props = defineProps({
        options: Array,
        values: Object,
        placeholder: String
    });

    const includeOptions = ref(props.values?.include ?? []);
    const excludeOptions = ref(props.values?.exclude ?? []);

    const getItemState = (code) => {
        if (includeOptions.value.includes(code)) return 'include';
        if (excludeOptions.value.includes(code)) return 'exclude';
        return 'neutral';
    }

    const toggleItem = (code) => {
        const state = getItemState(code);

        // Clear from both arrays first
        includeOptions.value = includeOptions.value.filter(c => c !== code);
        excludeOptions.value = excludeOptions.value.filter(c => c !== code);

        // Cycle: neutral -> include -> exclude -> neutral
        if (state === 'neutral') {
            includeOptions.value.push(code);
        } else if (state === 'include') {
            excludeOptions.value.push(code);
        }
        // If exclude, stays neutral (already cleared above)

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    };

    const includeAll = () => {
        excludeOptions.value = []
        includeOptions.value = props.options.map(item => item.code)
        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }

    const excludeAll = () => {
        includeOptions.value = []
        excludeOptions.value = props.options.map(item => item.code)
        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }

    const clearAll = () => {
        includeOptions.value = []
        excludeOptions.value = []
        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }

    const totalSelected = computed(() => includeOptions.value.length + excludeOptions.value.length);
</script>

<template>
    <div class="items-selector">
        <!-- Header with counts and bulk actions -->
        <div class="selector-header">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400">{{ totalSelected }} selected</span>
                <div class="flex items-center gap-1">
                    <span class="px-2 py-0.5 bg-green-500/20 text-green-400 text-xs font-bold rounded">{{ includeOptions.length }}</span>
                    <span class="px-2 py-0.5 bg-red-500/20 text-red-400 text-xs font-bold rounded">{{ excludeOptions.length }}</span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    @click="clearAll()"
                    title="Clear All"
                    class="bulk-btn bulk-neutral"
                    :class="{'active': totalSelected === 0}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <button
                    @click="includeAll()"
                    title="Include All"
                    class="bulk-btn bulk-include"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </button>
                <button
                    @click="excludeAll()"
                    title="Exclude All"
                    class="bulk-btn bulk-exclude"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Items grid -->
        <div class="items-grid">
            <button
                v-for="item in options"
                :key="item.code"
                @click="toggleItem(item.code)"
                class="item-tile"
                :class="{
                    'item-include': getItemState(item.code) === 'include',
                    'item-exclude': getItemState(item.code) === 'exclude'
                }"
                :title="item.name + ' (click to cycle: neutral → include → exclude)'"
            >
                <div :class="`sprite-items sprite-${item.code} item-sprite`"></div>
                <div class="item-indicator" v-if="getItemState(item.code) === 'include'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="item-indicator exclude-indicator" v-if="getItemState(item.code) === 'exclude'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>
        </div>
    </div>
</template>

<style scoped>
    .items-selector {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.75rem;
        padding: 0.75rem;
    }

    .selector-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .bulk-btn {
        padding: 0.375rem;
        border-radius: 0.375rem;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }

    .bulk-neutral {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.7);
    }

    .bulk-neutral:hover {
        background: rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.9);
    }

    .bulk-neutral.active {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .bulk-include {
        background: rgba(34, 197, 94, 0.2);
        color: rgba(134, 239, 172, 1);
    }

    .bulk-include:hover {
        background: rgba(34, 197, 94, 0.3);
    }

    .bulk-exclude {
        background: rgba(239, 68, 68, 0.2);
        color: rgba(252, 165, 165, 1);
    }

    .bulk-exclude:hover {
        background: rgba(239, 68, 68, 0.3);
    }

    .items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(48px, 1fr));
        gap: 0.5rem;
    }

    .item-tile {
        position: relative;
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .item-tile:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .item-tile.item-include {
        background: rgba(34, 197, 94, 0.2);
        border-color: rgba(34, 197, 94, 0.5);
    }

    .item-tile.item-include:hover {
        background: rgba(34, 197, 94, 0.3);
        border-color: rgba(34, 197, 94, 0.6);
    }

    .item-tile.item-exclude {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.5);
    }

    .item-tile.item-exclude:hover {
        background: rgba(239, 68, 68, 0.3);
        border-color: rgba(239, 68, 68, 0.6);
    }

    .item-sprite {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
    }

    .item-indicator {
        position: absolute;
        top: 2px;
        right: 2px;
        background: rgba(34, 197, 94, 0.9);
        color: white;
        border-radius: 9999px;
        padding: 2px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .exclude-indicator {
        background: rgba(239, 68, 68, 0.9);
    }
</style>
