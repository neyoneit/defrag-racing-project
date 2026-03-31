<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { getCurrentInstance } from 'vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    page: Object,
    revision: Object,
    navigation: Array,
    isStaff: Boolean,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <div class="pb-4">
        <Head :title="'Revision #' + revision.id + ' - ' + page.title + ' - Wiki'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <Link :href="route('wiki.show', page.slug)" class="hover:text-gray-300 transition">{{ page.title }}</Link>
                    <span>/</span>
                    <Link :href="route('wiki.history', page.slug)" class="hover:text-gray-300 transition">History</Link>
                    <span>/</span>
                    <span class="text-gray-400">Revision #{{ revision.id }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">{{ revision.title }}</h1>
                    <Link
                        v-if="isStaff"
                        :href="route('wiki.revert', { slug: page.slug, revision: revision.id })"
                        method="post"
                        as="button"
                        class="px-4 py-2 bg-yellow-600/80 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg transition"
                    >
                        Revert to this version
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Revision info banner -->
            <div class="bg-yellow-900/20 border border-yellow-700/30 rounded-xl px-6 py-3 mb-4 flex items-center gap-4 text-sm text-yellow-400">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>
                    You are viewing an older revision by <Link v-if="revision.user" :href="'/profile/' + revision.user.id" class="font-bold hover:text-yellow-200 transition" v-html="q3tohtml(revision.user.name || revision.user.username)"></Link><strong v-else>System</strong>
                    from {{ formatDate(revision.created_at) }}.
                    <span v-if="revision.summary" class="text-yellow-500">- {{ revision.summary }}</span>
                </span>
                <Link :href="route('wiki.show', page.slug)" class="ml-auto text-yellow-300 hover:text-yellow-200 underline flex-shrink-0">View current</Link>
            </div>

            <div class="flex gap-6">
                <!-- Sidebar -->
                <div class="hidden lg:block w-64 flex-shrink-0">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-4 sticky top-24">
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
                                        class="block pl-6 pr-3 py-1.5 text-sm rounded-lg transition text-gray-500 hover:text-gray-300 hover:bg-gray-700/50"
                                    >
                                        {{ child.title }}
                                    </Link>
                                </template>
                            </template>
                        </nav>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-6 md:p-8">
                        <div class="wiki-content prose prose-invert prose-gray max-w-none" v-html="revision.content"></div>
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
.wiki-content strong { @apply text-gray-200 font-semibold; }
.wiki-content em { @apply text-gray-300; }
</style>
