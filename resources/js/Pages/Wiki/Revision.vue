<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, getCurrentInstance } from 'vue';
import HtmlDiff from 'htmldiff-js';
import { diffLines } from 'diff';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    page: Object,
    revision: Object,
    afterContent: String,
    navigation: Array,
    isStaff: Boolean,
});

// Auto-open diff if ?diff=1 in URL
const urlParams = new URLSearchParams(window.location.search);
const showDiff = ref(urlParams.get('diff') === '1' && !!props.afterContent);

// Detect if both HTML versions are in the same format (both TipTap-normalized)
// TipTap HTML never has inline style= on headings/paragraphs
function isTipTapFormat(html) {
    if (!html) return false;
    // Old inline-styled pages have style= on h2, p, etc.
    return !/<(h[1-6]|p)\s[^>]*style="/i.test(html);
}

const bothNormalized = computed(() => {
    return isTipTapFormat(props.revision.content) && isTipTapFormat(props.afterContent);
});

// HTML diff (clean, works when both sides are in same format)
const htmlDiffResult = computed(() => {
    if (!props.afterContent || !bothNormalized.value) return null;
    return HtmlDiff.execute(props.revision.content, props.afterContent);
});

// Text diff fallback (for mixed-format pages)
function htmlToText(html) {
    if (!html) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const lines = [];
    function walk(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent.replace(/[\u200B\u200C\u200D\uFEFF]/g, '').trim();
            if (text) lines.push(text);
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.tagName === 'TD' || node.tagName === 'TH') {
            const text = node.textContent.replace(/[\u200B\u200C\u200D\uFEFF]/g, '').trim();
            if (text) lines.push(text);
            return;
        }
        for (const child of node.childNodes) walk(child);
    }
    walk(doc.body);
    return lines.map(l => l.replace(/\s+/g, ' ').trim()).filter(l => l).join('\n');
}

const textDiffResult = computed(() => {
    if (!props.afterContent || bothNormalized.value) return null;
    const oldText = htmlToText(props.revision.content);
    const newText = htmlToText(props.afterContent);
    if (oldText === newText) return [];
    return diffLines(oldText, newText);
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
                    <div class="flex items-center gap-2">
                        <!-- Diff toggle -->
                        <button
                            v-if="afterContent"
                            @click="showDiff = !showDiff"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition flex items-center gap-2"
                            :class="showDiff
                                ? 'bg-purple-600/80 hover:bg-purple-600 text-white'
                                : 'bg-gray-700/60 hover:bg-gray-700 text-gray-300'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            {{ showDiff ? 'View Content' : 'Show Changes' }}
                        </button>
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

            <!-- Diff info bar -->
            <div v-if="showDiff && afterContent" class="bg-purple-900/20 border border-purple-700/30 rounded-xl px-6 py-3 mb-4 flex items-center gap-4 text-sm text-purple-300">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span v-if="bothNormalized">Showing what this edit changed. <span class="inline-flex items-center gap-2 ml-2"><span class="inline-block w-3 h-3 bg-green-500/20 border border-green-500/40 rounded-sm"></span> added <span class="inline-block w-3 h-3 bg-red-500/20 border border-red-500/40 rounded-sm"></span> removed</span></span>
                <span v-else class="text-orange-300">Text-only diff (page uses legacy formatting - use "Normalize HTML" on the Wiki page to enable rich diff)</span>
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

                <!-- Content / Diff -->
                <div class="flex-1 min-w-0">
                    <!-- Normal content view -->
                    <div v-if="!showDiff" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-6 md:p-8">
                        <div class="wiki-content prose prose-invert prose-gray max-w-none" v-html="revision.content"></div>
                    </div>

                    <!-- Diff view -->
                    <div v-else>
                        <div v-if="!afterContent" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-8 text-center text-gray-500">
                            This is the first revision - no previous version to compare against.
                        </div>

                        <!-- Rich HTML diff (when both versions are normalized) -->
                        <div v-else-if="bothNormalized && htmlDiffResult" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-6 md:p-8">
                            <div class="wiki-content wiki-diff prose prose-invert prose-gray max-w-none" v-html="htmlDiffResult"></div>
                        </div>

                        <!-- Text diff fallback (mixed formats) -->
                        <div v-else-if="textDiffResult" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
                            <div v-if="textDiffResult.length === 0" class="p-8 text-center text-gray-500">
                                No text changes detected - this edit only changed formatting.
                            </div>
                            <div v-else class="diff-view text-sm leading-relaxed">
                                <template v-for="(part, idx) in textDiffResult" :key="idx">
                                    <div
                                        v-for="(line, lineIdx) in part.value.split('\n').filter((l, i, arr) => i < arr.length - 1 || l.trim())"
                                        :key="idx + '-' + lineIdx"
                                        class="diff-line px-5 py-1"
                                        :class="{ 'diff-added': part.added, 'diff-removed': part.removed }"
                                    >
                                        <span class="diff-gutter select-none inline-block w-6 text-center mr-3 flex-shrink-0">{{ part.added ? '+' : part.removed ? '-' : '' }}</span>
                                        <span class="diff-text">{{ line || '\u00A0' }}</span>
                                    </div>
                                </template>
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
.wiki-content strong { @apply text-gray-200 font-semibold; }
.wiki-content em { @apply text-gray-300; }

/* Rich HTML diff styles */
.wiki-diff ins {
    background: rgba(34, 197, 94, 0.15);
    color: rgb(187, 247, 208);
    text-decoration: none;
    border-radius: 2px;
    padding: 0 2px;
}
.wiki-diff del {
    background: rgba(239, 68, 68, 0.15);
    color: rgb(254, 202, 202);
    text-decoration: line-through;
    text-decoration-color: rgba(239, 68, 68, 0.5);
    border-radius: 2px;
    padding: 0 2px;
}
.wiki-diff pre ins, .wiki-diff pre del { display: inline; }
.wiki-diff ins img { outline: 2px solid rgb(34, 197, 94); border-radius: 4px; }
.wiki-diff del img { outline: 2px solid rgb(239, 68, 68); border-radius: 4px; opacity: 0.5; }

/* Text diff styles (fallback) */
.diff-view {
    max-height: 75vh;
    overflow-y: auto;
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
}
.diff-line {
    display: flex;
    align-items: flex-start;
    border-left: 3px solid transparent;
    min-height: 1.75rem;
}
.diff-line:not(.diff-added):not(.diff-removed) {
    color: rgb(107, 114, 128);
}
.diff-added {
    background: rgba(34, 197, 94, 0.08);
    border-left-color: rgb(34, 197, 94);
    color: rgb(187, 247, 208);
}
.diff-removed {
    background: rgba(239, 68, 68, 0.08);
    border-left-color: rgb(239, 68, 68);
    color: rgb(254, 202, 202);
}
.diff-gutter {
    opacity: 0.7;
    font-weight: 700;
}
.diff-added .diff-gutter { color: rgb(34, 197, 94); }
.diff-removed .diff-gutter { color: rgb(239, 68, 68); }
.diff-text {
    white-space: pre-wrap;
    word-break: break-word;
}
.diff-view::-webkit-scrollbar { width: 6px; }
.diff-view::-webkit-scrollbar-track { background: transparent; }
.diff-view::-webkit-scrollbar-thumb { background: rgba(75, 85, 99, 0.4); border-radius: 3px; }
.diff-view::-webkit-scrollbar-thumb:hover { background: rgba(75, 85, 99, 0.7); }
</style>
