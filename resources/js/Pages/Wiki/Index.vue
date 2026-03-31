<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import WikiSearchOverlay from '@/Components/WikiSearchOverlay.vue';

const props = defineProps({
    pages: Array,
    isStaff: Boolean,
});

const searchOpen = ref(false);
const expandedCategories = ref({});
const reordering = ref(false);
const localPages = ref([...props.pages]);

// Start with all categories expanded
props.pages?.forEach(page => {
    if (page.children && page.children.length > 0) {
        expandedCategories.value[page.id] = true;
    }
});

function toggleCategory(id) {
    expandedCategories.value[id] = !expandedCategories.value[id];
}

function moveUp(index) {
    if (index === 0) return;
    const arr = localPages.value;
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    localPages.value = [...arr];
}

function moveDown(index) {
    if (index >= localPages.value.length - 1) return;
    const arr = localPages.value;
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    localPages.value = [...arr];
}

function saveOrder() {
    const order = localPages.value.map(p => p.id);
    router.post(route('wiki.reorder'), { order }, {
        preserveScroll: true,
        onSuccess: () => {
            reordering.value = false;
        },
    });
}

function cancelReorder() {
    localPages.value = [...props.pages];
    reordering.value = false;
}

const displayPages = () => reordering.value ? localPages.value : props.pages;

</script>

<template>
    <div class="pb-4">
        <Head title="Wiki" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Wiki</h1>
                        <p class="text-gray-400">Community knowledge base for Quake III DeFRaG</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('wiki.globalHistory')"
                            class="px-4 py-2.5 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Recent Changes
                        </Link>
                        <template v-if="isStaff">
                            <button
                                v-if="!reordering"
                                @click="reordering = true; localPages = [...pages]"
                                class="px-4 py-2.5 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition"
                            >
                                Reorder
                            </button>
                            <template v-else>
                                <button
                                    @click="cancelReorder"
                                    class="px-4 py-2.5 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="saveOrder"
                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                >
                                    Save Order
                                </button>
                            </template>
                        </template>
                        <Link
                            v-if="$page.props.auth.user && ($page.props.auth.user.admin || $page.props.auth.user.is_moderator)"
                            :href="route('wiki.create')"
                            class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                        >
                            New Page
                        </Link>
                    </div>
                </div>

                <!-- Search bar -->
                <button
                    @click="searchOpen = true"
                    class="w-full flex items-center gap-3 px-5 py-3.5 bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl text-gray-500 hover:border-gray-600/50 hover:bg-gray-800/60 transition-all duration-200 group"
                >
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="text-sm">Search wiki pages, sections, and content...</span>
                    <kbd class="ml-auto hidden sm:inline-flex items-center px-2 py-0.5 text-xs text-gray-600 bg-gray-800/80 border border-gray-700 rounded">Ctrl+K</kbd>
                </button>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Categories -->
            <div class="space-y-3">
                <template v-for="(page, idx) in displayPages()" :key="page.id">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
                        <!-- Category header -->
                        <div
                            class="flex items-center gap-3 px-5 py-4"
                            :class="!reordering && page.children && page.children.length > 0 ? 'cursor-pointer hover:bg-white/5 transition' : ''"
                            @click="!reordering && page.children && page.children.length > 0 ? toggleCategory(page.id) : null"
                        >
                            <!-- Reorder arrows (staff, reorder mode) -->
                            <div v-if="reordering" class="flex flex-col gap-0.5 flex-shrink-0">
                                <button
                                    @click.stop="moveUp(idx)"
                                    :disabled="idx === 0"
                                    class="text-gray-400 hover:text-white disabled:text-gray-700 transition p-0.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button
                                    @click.stop="moveDown(idx)"
                                    :disabled="idx >= displayPages().length - 1"
                                    class="text-gray-400 hover:text-white disabled:text-gray-700 transition p-0.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>

                            <!-- Expand/collapse arrow -->
                            <template v-if="!reordering">
                                <button
                                    v-if="page.children && page.children.length > 0"
                                    class="text-gray-500 flex-shrink-0 transition-transform duration-200"
                                    :class="expandedCategories[page.id] ? 'rotate-90' : ''"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                <div v-else class="w-4 flex-shrink-0"></div>
                            </template>

                            <Link
                                :href="route('wiki.show', page.slug)"
                                class="text-lg font-bold text-gray-200 hover:text-white transition"
                                @click.stop
                            >
                                {{ page.title }}
                            </Link>

                            <span
                                v-if="page.children && page.children.length > 0"
                                class="ml-auto text-xs text-gray-600"
                            >
                                {{ page.children.length }} {{ page.children.length === 1 ? 'page' : 'pages' }}
                            </span>
                        </div>

                        <!-- Children list (sorted alphabetically) -->
                        <Transition name="expand">
                            <div
                                v-if="!reordering && page.children && page.children.length > 0 && expandedCategories[page.id]"
                                class="border-t border-white/5"
                            >
                                <Link
                                    v-for="child in [...page.children].sort((a, b) => a.title.localeCompare(b.title))"
                                    :key="child.id"
                                    :href="route('wiki.show', child.slug)"
                                    class="flex items-center gap-3 px-5 py-2.5 pl-12 text-sm text-gray-400 hover:text-gray-200 hover:bg-white/5 transition group"
                                >
                                    <svg class="w-3.5 h-3.5 text-gray-600 group-hover:text-gray-400 transition flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    {{ child.title }}
                                </Link>
                            </div>
                        </Transition>
                    </div>
                </template>

                <div v-if="!pages || pages.length === 0" class="text-center py-20">
                    <p class="text-gray-500 text-lg">No wiki pages yet.</p>
                </div>
            </div>
        </div>

        <WikiSearchOverlay v-model="searchOpen" />
    </div>
</template>

<style scoped>
.expand-enter-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.expand-leave-active {
    transition: all 0.15s ease;
    overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
}
.expand-enter-to,
.expand-leave-from {
    opacity: 1;
    max-height: 500px;
}
</style>
