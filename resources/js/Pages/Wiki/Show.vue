<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    page: Object,
    children: Array,
    navigation: Array,
    canEdit: Boolean,
    isStaff: Boolean,
});
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
                    <div class="bg-gray-800/60 border border-gray-700/50 rounded-xl p-4 sticky top-24">
                        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Pages</h3>
                        <nav class="space-y-1">
                            <template v-for="navPage in navigation" :key="navPage.id">
                                <Link
                                    :href="route('wiki.show', navPage.slug)"
                                    class="block px-3 py-1.5 text-sm rounded-lg transition"
                                    :class="navPage.slug === page.slug ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'"
                                >
                                    {{ navPage.title }}
                                </Link>
                                <template v-if="navPage.children && navPage.children.length > 0">
                                    <Link
                                        v-for="child in navPage.children"
                                        :key="child.id"
                                        :href="route('wiki.show', child.slug)"
                                        class="block pl-6 pr-3 py-1.5 text-sm rounded-lg transition"
                                        :class="child.slug === page.slug ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-700/50'"
                                    >
                                        {{ child.title }}
                                    </Link>
                                </template>
                            </template>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 min-w-0">
                    <div class="bg-gray-800/60 border border-gray-700/50 rounded-xl p-6 md:p-8">
                        <!-- Page info bar -->
                        <div class="flex items-center gap-4 text-xs text-gray-500 mb-6 pb-4 border-b border-gray-700/50">
                            <span v-if="page.is_locked" class="flex items-center gap-1 text-yellow-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Locked
                            </span>
                            <span v-if="page.updater">
                                Last edited by <span class="text-gray-400">{{ page.updater.name }}</span>
                            </span>
                            <span v-if="page.updated_at">
                                {{ new Date(page.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                            </span>
                        </div>

                        <!-- Rendered Markdown -->
                        <div class="wiki-content prose prose-invert prose-gray max-w-none" v-html="page.content"></div>

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
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.wiki-content h1 { @apply text-3xl font-bold text-gray-200 mt-8 mb-4; }
.wiki-content h2 { @apply text-2xl font-bold text-gray-200 mt-8 mb-3; }
.wiki-content h3 { @apply text-xl font-semibold text-gray-300 mt-6 mb-2; }
.wiki-content h4 { @apply text-lg font-semibold text-gray-300 mt-4 mb-2; }
.wiki-content p { @apply text-gray-400 leading-relaxed mb-4; }
.wiki-content ul { @apply list-disc list-inside text-gray-400 mb-4 space-y-1; }
.wiki-content ol { @apply list-decimal list-inside text-gray-400 mb-4 space-y-1; }
.wiki-content li { @apply text-gray-400; }
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
</style>
