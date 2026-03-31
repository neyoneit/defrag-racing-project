<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
});
const emit = defineEmits(['update:modelValue']);

const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref(null);
let searchIndex = null;
let indexLoading = false;

function close() {
    emit('update:modelValue', false);
    query.value = '';
    selectedIndex.value = 0;
}

function navigate(slug) {
    close();
    router.visit(route('wiki.show', slug));
}

// --- Fuzzy matching ---
function fuzzyScore(needle, haystack) {
    needle = needle.toLowerCase();
    haystack = haystack.toLowerCase();

    // Exact substring match - highest score
    if (haystack.includes(needle)) {
        const pos = haystack.indexOf(needle);
        // Earlier match = better score. Title start match is best.
        return 100 - (pos * 0.5);
    }

    // Word-start matching (e.g. "ob" matches "Overbounce")
    const words = haystack.split(/[\s,_\-\/&]+/);
    for (const word of words) {
        if (word.startsWith(needle)) return 80;
    }

    // Fuzzy character sequence match
    let ni = 0;
    let consecutiveBonus = 0;
    let lastMatchIdx = -2;
    let score = 0;

    for (let hi = 0; hi < haystack.length && ni < needle.length; hi++) {
        if (haystack[hi] === needle[ni]) {
            score += 10;
            // Bonus for consecutive matches
            if (hi === lastMatchIdx + 1) {
                consecutiveBonus += 5;
                score += consecutiveBonus;
            } else {
                consecutiveBonus = 0;
            }
            // Bonus for matching at word boundaries
            if (hi === 0 || /[\s,_\-\/&]/.test(haystack[hi - 1])) {
                score += 8;
            }
            lastMatchIdx = hi;
            ni++;
        }
    }

    // All characters must be found in order
    if (ni < needle.length) return 0;

    // Penalize long haystacks (prefer shorter, more relevant matches)
    score -= haystack.length * 0.2;

    return Math.max(score, 1);
}

function searchPages(q) {
    if (!searchIndex || q.length < 1) return [];

    const results = [];
    for (const page of searchIndex) {
        // Score against title, keywords, and snippet
        const titleScore = fuzzyScore(q, page.title) * 3;
        const keywordScore = page.keywords ? fuzzyScore(q, page.keywords) * 2 : 0;
        const snippetScore = fuzzyScore(q, page.snippet) * 0.5;

        const bestScore = Math.max(titleScore, keywordScore, snippetScore);
        if (bestScore > 0) {
            results.push({
                ...page,
                score: bestScore,
                matchSource: titleScore >= keywordScore && titleScore >= snippetScore ? 'title'
                    : keywordScore >= snippetScore ? 'keywords' : 'content',
            });
        }
    }

    results.sort((a, b) => b.score - a.score);
    return results.slice(0, 20);
}

const results = computed(() => {
    if (query.value.length < 1) return [];
    return searchPages(query.value);
});

// Highlight fuzzy matches in text
function highlightMatch(text, q) {
    if (!q || !text) return text;
    q = q.toLowerCase();

    // Try exact substring highlight first
    const lowerText = text.toLowerCase();
    const idx = lowerText.indexOf(q);
    if (idx !== -1) {
        return text.substring(0, idx) +
            '<mark class="bg-yellow-500/30 text-yellow-200 rounded px-0.5">' +
            text.substring(idx, idx + q.length) +
            '</mark>' +
            text.substring(idx + q.length);
    }

    // Fuzzy highlight - mark each matched character
    let result = '';
    let ni = 0;
    for (let i = 0; i < text.length; i++) {
        if (ni < q.length && text[i].toLowerCase() === q[ni]) {
            result += '<mark class="bg-yellow-500/30 text-yellow-200 rounded-sm">' + text[i] + '</mark>';
            ni++;
        } else {
            result += text[i];
        }
    }
    return result;
}

// Fetch index on first open
async function ensureIndex() {
    if (searchIndex || indexLoading) return;
    indexLoading = true;
    try {
        const { data } = await axios.get(route('wiki.searchIndex'));
        searchIndex = data;
    } catch (e) {
        console.error('Failed to load search index', e);
    }
    indexLoading = false;
}

watch(() => props.modelValue, async (open) => {
    if (open) {
        ensureIndex();
        await nextTick();
        inputRef.value?.focus();
    }
});

watch(query, () => {
    selectedIndex.value = 0;
});

function onKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, results.value.length - 1);
        scrollToSelected();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
        scrollToSelected();
    } else if (e.key === 'Enter' && results.value.length > 0) {
        e.preventDefault();
        navigate(results.value[selectedIndex.value].slug);
    }
}

function scrollToSelected() {
    nextTick(() => {
        const el = document.querySelector('.search-result-active');
        el?.scrollIntoView({ block: 'nearest' });
    });
}

function onGlobalKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        emit('update:modelValue', !props.modelValue);
    }
    if (e.key === 'Escape' && props.modelValue) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onGlobalKeydown);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="search-overlay">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-[9999] flex items-start justify-center pt-[10vh] px-4"
                @click.self="close"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="close"></div>

                <!-- Search modal -->
                <div class="relative w-full max-w-2xl bg-gray-900/95 backdrop-blur-xl border border-white/15 rounded-2xl shadow-2xl shadow-black/50 overflow-hidden">
                    <!-- Search input -->
                    <div class="flex items-center gap-3 px-5 py-4 border-b border-white/10">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Search wiki pages..."
                            class="flex-1 bg-transparent text-gray-200 text-lg placeholder-gray-500 outline-none"
                            @keydown="onKeydown"
                        />
                        <kbd class="hidden sm:inline-flex items-center px-2 py-0.5 text-xs text-gray-500 bg-gray-800 border border-gray-700 rounded">
                            ESC
                        </kbd>
                    </div>

                    <!-- Results -->
                    <div class="max-h-[60vh] overflow-y-auto overscroll-contain search-results-scroll">
                        <!-- Loading index -->
                        <div v-if="indexLoading && query.length >= 1" class="px-5 py-8 text-center">
                            <div class="inline-block w-5 h-5 border-2 border-gray-600 border-t-blue-500 rounded-full animate-spin"></div>
                        </div>

                        <!-- No results -->
                        <div v-else-if="query.length >= 1 && results.length === 0" class="px-5 py-8 text-center text-gray-500">
                            No pages found for "<span class="text-gray-400">{{ query }}</span>"
                        </div>

                        <!-- Result list -->
                        <div v-else-if="results.length > 0" class="py-2">
                            <button
                                v-for="(result, idx) in results"
                                :key="result.id"
                                class="w-full text-left px-5 py-3 flex flex-col gap-1 transition-colors duration-75"
                                :class="[
                                    idx === selectedIndex
                                        ? 'bg-blue-600/20 search-result-active'
                                        : 'hover:bg-white/5'
                                ]"
                                @click="navigate(result.slug)"
                                @mouseenter="selectedIndex = idx"
                            >
                                <!-- Title + section -->
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="result.parent"
                                        class="text-xs text-gray-500 flex items-center gap-1"
                                    >
                                        {{ result.parent.title }}
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </span>
                                    <span
                                        class="text-sm font-semibold text-gray-200"
                                        v-html="highlightMatch(result.title, query)"
                                    ></span>
                                    <!-- Match source badge -->
                                    <span v-if="result.matchSource === 'keywords'" class="text-[10px] px-1.5 py-0.5 bg-blue-900/40 text-blue-400 rounded">keyword</span>
                                    <span v-if="result.matchSource === 'content'" class="text-[10px] px-1.5 py-0.5 bg-gray-800 text-gray-500 rounded">content</span>
                                </div>
                                <!-- Snippet -->
                                <p
                                    v-if="result.snippet"
                                    class="text-xs text-gray-500 line-clamp-2 leading-relaxed"
                                    v-html="result.matchSource === 'content' ? highlightMatch(result.snippet, query) : result.snippet.substring(0, 120) + '...'"
                                ></p>
                            </button>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="px-5 py-8 text-center text-gray-600 text-sm">
                            Type to search wiki pages, sections, and content
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center gap-4 px-5 py-2.5 border-t border-white/10 text-xs text-gray-600">
                        <span class="flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-gray-800 border border-gray-700 rounded text-gray-500">↑↓</kbd>
                            navigate
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-gray-800 border border-gray-700 rounded text-gray-500">↵</kbd>
                            open
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-gray-800 border border-gray-700 rounded text-gray-500">esc</kbd>
                            close
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.search-overlay-enter-active {
    transition: opacity 0.15s ease;
}
.search-overlay-leave-active {
    transition: opacity 0.1s ease;
}
.search-overlay-enter-from,
.search-overlay-leave-to {
    opacity: 0;
}

.search-results-scroll::-webkit-scrollbar {
    width: 4px;
}
.search-results-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.search-results-scroll::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.4);
    border-radius: 2px;
}
</style>
