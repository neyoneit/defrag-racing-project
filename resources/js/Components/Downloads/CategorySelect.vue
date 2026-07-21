<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

/**
 * Category picker for the upload form.
 *
 * A native <select> is drawn by the OS, so the dark styling never reaches its
 * popup and it comes out light grey. This renders its own panel instead, and
 * a flat list of 40 paths needs a filter and grouping to be usable anyway.
 */
const props = defineProps({
    modelValue: [Number, String],
    categories: Array,
    hasError: Boolean,
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const root = ref(null);
const searchInput = ref(null);
const activeIndex = ref(0);

const selected = computed(() => props.categories.find((c) => c.id === props.modelValue) ?? null);

const matches = computed(() => {
    const q = search.value.trim().toLowerCase();

    if (!q) return props.categories;

    return props.categories.filter((c) => c.path.toLowerCase().includes(q));
});

// Grouped under their top-level branch, so "Music" reads as part of "Sounds
// and music" rather than floating on its own.
const groups = computed(() => {
    const out = [];

    for (const c of matches.value) {
        const rootName = c.path.split(' / ')[0];
        let group = out.find((g) => g.name === rootName);

        if (!group) {
            group = { name: rootName, items: [] };
            out.push(group);
        }

        group.items.push(c);
    }

    return out;
});

// Flattened again for arrow-key movement, in the same order as rendered.
const flat = computed(() => groups.value.flatMap((g) => g.items));

const pick = (category) => {
    emit('update:modelValue', category.id);
    open.value = false;
    search.value = '';
};

const toggle = async () => {
    open.value = !open.value;

    if (open.value) {
        activeIndex.value = Math.max(0, flat.value.findIndex((c) => c.id === props.modelValue));
        await nextTick();
        searchInput.value?.focus();
    }
};

const move = (step) => {
    if (!flat.value.length) return;
    activeIndex.value = (activeIndex.value + step + flat.value.length) % flat.value.length;
};

const onEnter = () => {
    const item = flat.value[activeIndex.value];
    if (item) pick(item);
};

watch(search, () => { activeIndex.value = 0; });

const onClickOutside = (e) => {
    if (open.value && root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            @click="toggle"
            @keydown.down.prevent="!open ? toggle() : move(1)"
            class="w-full flex items-center justify-between gap-2 bg-black/40 border rounded-lg px-3 py-2 text-sm text-left transition-colors"
            :class="[
                hasError ? 'border-red-500/50' : 'border-white/10 hover:border-white/20',
                open ? 'border-cyan-500/50' : '',
            ]">
            <span v-if="selected" class="truncate">
                <span class="text-gray-600">{{ selected.path.split(' / ').slice(0, -1).join(' / ') }}</span>
                <span v-if="selected.depth > 0" class="text-gray-700"> / </span>
                <span class="text-white">{{ selected.name }}</span>
            </span>
            <span v-else class="text-gray-600">Pick a category...</span>

            <svg class="w-4 h-4 flex-shrink-0 text-gray-600 transition-transform" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div v-if="open"
             class="absolute z-50 mt-1 w-full bg-gray-950/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-2xl overflow-hidden">

            <div class="p-2 border-b border-white/5">
                <input
                    ref="searchInput"
                    v-model="search"
                    type="text"
                    placeholder="Filter..."
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="onEnter"
                    @keydown.esc="open = false"
                    class="w-full bg-black/50 border border-white/10 rounded-lg px-2.5 py-1.5 text-sm text-white placeholder-gray-600 focus:border-cyan-500/50 focus:ring-0 transition-colors" />
            </div>

            <div class="max-h-72 overflow-y-auto py-1">
                <template v-for="group in groups" :key="group.name">
                    <div class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-wider text-gray-600 font-bold">
                        {{ group.name }}
                    </div>

                    <button
                        v-for="item in group.items"
                        :key="item.id"
                        type="button"
                        @click="pick(item)"
                        @mouseenter="activeIndex = flat.indexOf(item)"
                        class="w-full text-left px-3 py-1.5 text-sm transition-colors flex items-center justify-between gap-2"
                        :class="[
                            item.id === modelValue ? 'text-cyan-300' : 'text-gray-400',
                            flat[activeIndex]?.id === item.id ? 'bg-cyan-500/10' : '',
                        ]"
                        :style="{ paddingLeft: `${12 + item.depth * 14}px` }">
                        <span class="truncate">{{ item.name }}</span>
                        <svg v-if="item.id === modelValue" class="w-3.5 h-3.5 flex-shrink-0"
                             fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </button>
                </template>

                <p v-if="!flat.length" class="px-3 py-4 text-sm text-gray-600 text-center">
                    Nothing matches "{{ search }}".
                </p>
            </div>
        </div>
    </div>
</template>
