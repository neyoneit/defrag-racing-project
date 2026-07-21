<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

// Self-referencing component: the tree nests up to three levels, so the node
// renders itself for its children.
defineOptions({ name: 'DownloadCategoryNode' });

const props = defineProps({
    node: Object,
    currentId: Number,
    openIds: Array,
    depth: { type: Number, default: 0 },
});

const isActive = computed(() => props.currentId === props.node.id);
const hasChildren = computed(() => props.node.children?.length > 0);

// A branch starts open when the selected category lives inside it, so landing
// on a subcategory reveals where you are instead of a collapsed tree.
const open = ref(props.openIds.includes(props.node.id));

watch(() => props.openIds, (ids) => {
    if (ids.includes(props.node.id)) {
        open.value = true;
    }
});

const url = computed(() => `/downloads/${props.node.id}/${props.node.slug}`);
</script>

<template>
    <div>
        <div
            class="group flex items-center border-l-2 transition-all"
            :class="isActive
                ? 'bg-cyan-500/10 border-cyan-400'
                : 'border-transparent hover:bg-cyan-500/5 hover:border-cyan-500/20'">

            <button
                v-if="hasChildren"
                @click="open = !open"
                class="flex-shrink-0 p-1 ml-1 text-gray-600 hover:text-cyan-300 transition-colors"
                :aria-label="open ? 'Collapse' : 'Expand'">
                <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-90' : ''"
                     fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
            <span v-else class="flex-shrink-0 w-5"></span>

            <Link
                :href="url"
                class="flex-1 flex items-center justify-between min-w-0 py-2 pr-3"
                :style="{ paddingLeft: `${depth * 10}px` }">
                <span class="flex items-center gap-1.5 min-w-0">
                    <svg v-if="node.is_locked" class="w-3 h-3 flex-shrink-0 text-amber-500/70"
                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span
                        class="text-sm truncate transition-colors"
                        :class="[
                            isActive ? 'text-cyan-300 font-medium' : 'text-gray-400 group-hover:text-white',
                            depth === 0 ? 'font-medium' : '',
                        ]">
                        {{ node.name }}
                    </span>
                </span>
                <span
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-2 flex-shrink-0"
                    :class="isActive ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-600'">
                    {{ node.count }}
                </span>
            </Link>
        </div>

        <div v-if="hasChildren && open">
            <DownloadCategoryNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :current-id="currentId"
                :open-ids="openIds"
                :depth="depth + 1" />
        </div>
    </div>
</template>
