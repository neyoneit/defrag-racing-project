<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, getCurrentInstance } from 'vue';
import WikiSearchOverlay from '@/Components/WikiSearchOverlay.vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    page: Object,
    children: Array,
    navigation: Array,
    siblings: Array,
    prevPage: Object,
    nextPage: Object,
    revisionCount: Number,
    canEdit: Boolean,
    isStaff: Boolean,
});

// If this is a sub-page, show siblings in sidebar. Otherwise show top-level nav.
const sidebarItems = computed(() => {
    if (props.page.parent_id && props.siblings && props.siblings.length > 0) {
        return { type: 'siblings', parent: props.page.parent, items: props.siblings };
    }
    return { type: 'navigation', parent: null, items: props.navigation };
});

// Auto-generate id attributes on headings so anchor links (#section) work
const processedContent = computed(() => {
    if (!props.page.content) return '';
    let html = props.page.content;

    // Add id attributes to h1-h4 headings based on their text content
    html = html.replace(/<(h[1-4])([^>]*)>(.*?)<\/\1>/gi, (match, tag, attrs, inner) => {
        // Skip if already has an id
        if (/\bid\s*=/.test(attrs)) return match;
        // Generate slug from text content (strip HTML tags)
        const text = inner.replace(/<[^>]+>/g, '').trim();
        const id = text.toLowerCase()
            .replace(/&amp;/g, '').replace(/&[^;]+;/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        return `<${tag}${attrs} id="${id}">${inner}</${tag}>`;
    });

    // Fix anchor links: remove target="_blank" from same-page hash links
    html = html.replace(/<a([^>]*href="#[^"]*"[^>]*)>/gi, (match, attrs) => {
        attrs = attrs.replace(/\s*target="_blank"/g, '');
        attrs = attrs.replace(/\s*rel="[^"]*"/g, '');
        return `<a${attrs}>`;
    });

    return html;
});

const sidebarCollapsed = ref(false);
const searchOpen = ref(false);
</script>

<template>
    <div class="pb-4">
        <Head :title="page.title + ' - Wiki'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                            <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                            <span>/</span>
                            <template v-if="page.parent">
                                <Link :href="route('wiki.show', page.parent.slug)" class="hover:text-gray-300 transition">{{ page.parent.title }}</Link>
                                <span>/</span>
                            </template>
                            <span class="text-gray-400">{{ page.title }}</span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">{{ page.title }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="searchOpen = true"
                            class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span class="hidden sm:inline">Search</span>
                            <kbd class="hidden md:inline-flex items-center px-1.5 py-0.5 text-[10px] text-gray-500 bg-gray-800 border border-gray-700 rounded">Ctrl+K</kbd>
                        </button>
                        <Link
                            :href="route('wiki.history', page.slug)"
                            class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition"
                        >
                            History
                        </Link>
                        <Link
                            v-if="canEdit"
                            :href="route('wiki.edit', page.slug)"
                            class="px-4 py-2 bg-blue-600/80 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition"
                        >
                            Edit
                        </Link>
                        <Link
                            v-if="isStaff"
                            :href="route('wiki.toggleLock', page.slug)"
                            method="post"
                            as="button"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition"
                            :class="page.is_locked ? 'bg-yellow-600/80 hover:bg-yellow-600 text-white' : 'bg-gray-700/60 hover:bg-gray-700 text-gray-300'"
                        >
                            {{ page.is_locked ? 'Unlock' : 'Lock' }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="flex gap-6">
                <!-- Sidebar Navigation -->
                <div class="hidden lg:block w-64 flex-shrink-0">
                    <div class="sticky top-20 max-h-[calc(100vh-6rem)] flex flex-col bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
                        <!-- Sidebar header -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700/30">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                <template v-if="sidebarItems.type === 'siblings'">
                                    <Link :href="route('wiki.show', sidebarItems.parent.slug)" class="hover:text-gray-200 transition flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                        {{ sidebarItems.parent.title }}
                                    </Link>
                                </template>
                                <template v-else>Pages</template>
                            </h3>
                            <button
                                @click="sidebarCollapsed = !sidebarCollapsed"
                                class="text-gray-500 hover:text-gray-300 transition lg:hidden"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" v-if="!sidebarCollapsed"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" v-else/>
                                </svg>
                            </button>
                        </div>

                        <!-- Scrollable nav list -->
                        <nav class="overflow-y-auto overscroll-contain p-2 space-y-0.5 flex-1 sidebar-scroll">
                            <template v-if="sidebarItems.type === 'siblings'">
                                <!-- Sub-page siblings list -->
                                <Link
                                    v-for="sibling in sidebarItems.items"
                                    :key="sibling.id"
                                    :href="route('wiki.show', sibling.slug)"
                                    class="block px-3 py-1.5 text-sm rounded-lg transition"
                                    :class="sibling.slug === page.slug
                                        ? 'bg-blue-600/20 text-blue-400 font-medium'
                                        : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'"
                                >
                                    {{ sibling.title }}
                                </Link>
                            </template>
                            <template v-else>
                                <!-- Top-level navigation with collapsible children -->
                                <template v-for="navPage in navigation" :key="navPage.id">
                                    <Link
                                        :href="route('wiki.show', navPage.slug)"
                                        class="block px-3 py-1.5 text-sm rounded-lg transition"
                                        :class="navPage.slug === page.slug ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'"
                                    >
                                        <span class="flex items-center justify-between">
                                            {{ navPage.title }}
                                            <span v-if="navPage.children && navPage.children.length > 0" class="text-gray-600 text-xs">{{ navPage.children.length }}</span>
                                        </span>
                                    </Link>
                                    <!-- Show children only for the currently active parent -->
                                    <template v-if="navPage.children && navPage.children.length > 0 && navPage.slug === page.slug">
                                        <Link
                                            v-for="child in navPage.children"
                                            :key="child.id"
                                            :href="route('wiki.show', child.slug)"
                                            class="block pl-6 pr-3 py-1 text-xs rounded-lg transition text-gray-500 hover:text-gray-300 hover:bg-gray-700/50"
                                        >
                                            {{ child.title }}
                                        </Link>
                                    </template>
                                </template>
                            </template>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 min-w-0">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-6 md:p-8">
                        <!-- Page info bar -->
                        <div class="flex items-center gap-4 text-xs text-gray-500 mb-6 pb-4 border-b border-gray-700/50">
                            <span v-if="page.is_locked" class="flex items-center gap-1 text-yellow-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Locked
                            </span>
                            <span v-if="page.updater">
                                Last edited by <Link :href="'/profile/' + page.updater.id" class="text-gray-400 hover:text-blue-400 transition" v-html="q3tohtml(page.updater.name)"></Link>
                            </span>
                            <span v-if="page.updated_at">
                                {{ new Date(page.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                        </div>

                        <!-- Rendered Content -->
                        <div class="wiki-content prose prose-invert prose-gray max-w-none" v-html="processedContent"></div>

                        <!-- Child pages -->
                        <div v-if="children && children.length > 0" class="mt-8 pt-6 border-t border-gray-700/50">
                            <h3 class="text-lg font-bold text-gray-300 mb-3">Sub-pages</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <Link
                                    v-for="child in children"
                                    :key="child.id"
                                    :href="route('wiki.show', child.slug)"
                                    class="flex items-center gap-2 px-4 py-3 bg-gray-700/30 border border-gray-700/30 rounded-lg hover:bg-gray-700/50 hover:border-gray-600/50 transition text-gray-300 hover:text-white"
                                >
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    {{ child.title }}
                                </Link>
                            </div>
                        </div>

                        <!-- Prev / Next navigation for sub-pages -->
                        <div v-if="prevPage || nextPage" class="mt-8 pt-6 border-t border-gray-700/50 flex items-center justify-between gap-4">
                            <Link
                                v-if="prevPage"
                                :href="route('wiki.show', prevPage.slug)"
                                class="flex items-center gap-2 px-4 py-3 bg-gray-700/30 border border-gray-700/30 rounded-lg hover:bg-gray-700/50 hover:border-gray-600/50 transition text-gray-300 hover:text-white max-w-[48%]"
                            >
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <span class="truncate">{{ prevPage.title }}</span>
                            </Link>
                            <div v-else></div>
                            <Link
                                v-if="nextPage"
                                :href="route('wiki.show', nextPage.slug)"
                                class="flex items-center gap-2 px-4 py-3 bg-gray-700/30 border border-gray-700/30 rounded-lg hover:bg-gray-700/50 hover:border-gray-600/50 transition text-gray-300 hover:text-white max-w-[48%] ml-auto"
                            >
                                <span class="truncate">{{ nextPage.title }}</span>
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <WikiSearchOverlay v-model="searchOpen" />
    </div>
</template>

<style>
.wiki-content h1 { @apply text-3xl font-bold text-gray-200 mt-8 mb-4; }
.wiki-content h2 { @apply text-2xl font-bold text-gray-200 mt-8 mb-3; }
.wiki-content h3 { @apply text-xl font-semibold text-gray-300 mt-6 mb-2; }
.wiki-content h4 { @apply text-lg font-semibold text-gray-300 mt-4 mb-2; }
.wiki-content p { @apply text-gray-400 leading-relaxed mb-4; }
.wiki-content ul { @apply list-disc ml-6 text-gray-400 mb-4 space-y-1; }
.wiki-content ol { @apply list-decimal ml-6 text-gray-400 mb-4 space-y-1; }
.wiki-content li { @apply text-gray-400; }
.wiki-content li p { @apply mb-1; }
.wiki-content a { @apply text-blue-400 hover:text-blue-300 underline; }
.wiki-content code { @apply bg-gray-900/60 text-green-400 px-1.5 py-0.5 rounded text-sm; }
.wiki-content pre { @apply bg-gray-900/80 border border-gray-700/50 rounded-lg p-4 mb-4 overflow-x-auto; }
.wiki-content pre code { @apply bg-transparent p-0 text-gray-300; }
.wiki-content blockquote { @apply border-l-4 border-blue-500/50 pl-4 italic text-gray-500 mb-4; }
.wiki-content table { @apply w-full border-collapse mb-4; }
.wiki-content th { @apply bg-gray-700/40 border border-gray-700/50 px-3 py-2 text-left text-sm font-semibold text-gray-300; }
.wiki-content td { @apply border border-gray-700/50 px-3 py-2 text-sm text-gray-400; }
.wiki-content hr { @apply border-gray-700/50 my-6; }
.wiki-content img { @apply rounded-lg max-w-full; }
.wiki-content strong { @apply text-gray-200 font-semibold; }
.wiki-content em { @apply text-gray-300; }

.sidebar-scroll::-webkit-scrollbar {
    width: 4px;
}
.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.4);
    border-radius: 2px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(75, 85, 99, 0.7);
}
</style>
