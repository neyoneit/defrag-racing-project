<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Head, router } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import MapCard from '@/Components/MapCard.vue';
    import MapFiltersSidebar from '@/Components/MapFiltersSidebar.vue';
    import axios from 'axios';

    const props = defineProps({
        maps: Object,
        queries: Object,
    });

    const tagsExpanded = ref(false);
    let tagsCollapseTimeout = null;
    const tagsWithUsage = ref([]);
    const selectedTags = ref([]);
    const tagSearch = ref('');

    const onTagsEnter = () => {
        if (tagsCollapseTimeout) {
            clearTimeout(tagsCollapseTimeout);
            tagsCollapseTimeout = null;
        }
        tagsExpanded.value = true;
    };

    const tagSearchInput = ref(null);

    const onTagsLeave = () => {
        tagsCollapseTimeout = setTimeout(() => {
            if (document.activeElement === tagSearchInput.value) return;
            tagsExpanded.value = false;
            tagSearch.value = '';
        }, 300);
    };

    const onTagSearchBlur = () => {
        setTimeout(() => {
            tagsExpanded.value = false;
            tagSearch.value = '';
        }, 100);
    };

    const fuzzyMatch = (text, query) => {
        const t = text.toLowerCase();
        const q = query.toLowerCase();
        // Exact substring match = best score
        if (t.includes(q)) return { match: true, score: 0 };
        // Fuzzy: all query chars must appear in order
        let ti = 0;
        let consecutive = 0;
        let maxConsecutive = 0;
        let matched = 0;
        for (let qi = 0; qi < q.length; qi++) {
            let found = false;
            while (ti < t.length) {
                if (t[ti] === q[qi]) {
                    matched++;
                    consecutive++;
                    maxConsecutive = Math.max(maxConsecutive, consecutive);
                    ti++;
                    found = true;
                    break;
                }
                consecutive = 0;
                ti++;
            }
            if (!found) return { match: false, score: Infinity };
        }
        // Lower score = better match. Prioritize consecutive matches and early matches.
        return { match: true, score: q.length - maxConsecutive + (t.length - matched) };
    };

    const filteredTagsForOverlay = computed(() => {
        if (!tagSearch.value) return tagsWithUsage.value;
        const q = tagSearch.value.trim();
        if (!q) return tagsWithUsage.value;

        const results = [];
        for (const tag of tagsWithUsage.value) {
            const nameMatch = fuzzyMatch(tag.name, q);
            const displayMatch = fuzzyMatch(tag.display_name, q);
            const best = nameMatch.score <= displayMatch.score ? nameMatch : displayMatch;
            if (best.match) {
                results.push({ tag, score: best.score });
            }
        }
        results.sort((a, b) => a.score - b.score);
        return results.map(r => r.tag);
    });

    const fetchTags = async () => {
        try {
            const response = await axios.get('/api/tags');
            tagsWithUsage.value = response.data.tags.filter(tag => tag.usage_count > 0);
        } catch (error) {
            console.error('Error fetching tags:', error);
        }
    };

    const toggleTag = (tagName) => {
        const index = selectedTags.value.indexOf(tagName);
        if (index > -1) {
            selectedTags.value.splice(index, 1);
        } else {
            selectedTags.value.push(tagName);
        }

        // Navigate to filtered maps
        const params = { ...props.queries };
        if (selectedTags.value.length > 0) {
            params.tags = selectedTags.value.join(',');
        } else {
            delete params.tags;
        }

        const hasFilters = Object.keys(params).length > 0;
        const url = hasFilters ? '/maps/filters' : '/maps';
        mapsLoaded.value = false;
        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            only: ['maps', 'queries'],
            onFinish: () => { mapsLoaded.value = true; }
        });
    };

    const clearAllTags = () => {
        selectedTags.value = [];
        const params = { ...props.queries };
        delete params.tags;

        const hasFilters = Object.keys(params).length > 0;
        const url = hasFilters ? '/maps/filters' : '/maps';
        mapsLoaded.value = false;
        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            only: ['maps', 'queries'],
            onFinish: () => { mapsLoaded.value = true; }
        });
    };

    const mapsLoaded = ref(false);

    const onSidebarSearch = (filters) => {
        // Clean empty values
        const params = {};
        for (const [key, val] of Object.entries(filters)) {
            if (val === '' || val === null || val === undefined) continue;
            if (Array.isArray(val) && val.length === 0) continue;
            if (typeof val === 'object' && !Array.isArray(val) && Object.keys(val).length === 0) continue;
            params[key] = val;
        }

        // Preserve active tags
        if (selectedTags.value.length > 0) {
            params.tags = selectedTags.value.join(',');
        }

        const hasFilters = Object.keys(params).length > 0;
        const url = hasFilters ? '/maps/filters' : '/maps';
        mapsLoaded.value = false;
        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            only: ['maps', 'queries'],
            onFinish: () => { mapsLoaded.value = true; }
        });
    };

    onMounted(() => {
        fetchTags();

        // Lazy load maps data
        if (!props.maps) {
            router.reload({
                only: ['maps'],
                onFinish: () => { mapsLoaded.value = true; }
            });
        } else {
            mapsLoaded.value = true;
        }

        // Initialize selected tags from query params
        if (props.queries?.tags) {
            selectedTags.value = props.queries.tags.split(',');
        }

        // Handle fragment redirects - if user typed /maps/#mapname, redirect to /maps/%23mapname
        const pathname = window.location.pathname.replace(/\/$/, ''); // Remove trailing slash
        if (window.location.hash && pathname === '/maps') {
            const fragment = window.location.hash.substring(1); // Remove the # prefix
            if (fragment) {
                const encodedMapname = encodeURIComponent('#' + fragment);
                console.log('Redirecting from fragment to encoded URL:', `/maps/${encodedMapname}`);
                router.get(`/maps/${encodedMapname}`);
            }
        }
    });
</script>

<template>
    <div class="min-h-screen">
        <Head title="Maps" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex gap-4">
                    <!-- Spacer matching sidebar width -->
                    <div class="hidden md:block flex-shrink-0 w-[300px]">
                        <div class="flex items-center gap-4">
                            <h1 class="text-4xl md:text-5xl font-black text-gray-300/90">Maps</h1>
                            <span v-if="maps" class="text-sm text-gray-400">{{ maps.total }} total</span>
                        </div>
                    </div>
                    <!-- Tags on title level, aligned with maps grid -->
                    <div class="flex-1 min-w-0">
                        <!-- Mobile title -->
                        <div class="md:hidden flex items-center gap-4 mb-2">
                            <h1 class="text-4xl md:text-5xl font-black text-gray-300/90">Maps</h1>
                            <span v-if="maps" class="text-sm text-gray-400">{{ maps.total }} total</span>
                        </div>
                        <!-- Tags bar (relative wrapper, expanded is absolute overlay) -->
                        <div v-if="tagsWithUsage.length > 0" class="relative" @mouseenter="onTagsEnter" @mouseleave="onTagsLeave">
                            <!-- Tag bar row with inline search -->
                            <div class="tags-bar relative">
                                <div class="flex items-center gap-2 min-h-[28px]">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    <button
                                        v-for="tag in tagsWithUsage.filter(t => selectedTags.includes(t.name))"
                                        :key="'sel-' + tag.id"
                                        @click="toggleTag(tag.name)"
                                        class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-600 text-white ring-1 ring-blue-400 transition-all flex-shrink-0">
                                        {{ tag.display_name }}
                                    </button>
                                    <!-- Input with ghost tags behind -->
                                    <div class="flex-1 min-w-0 relative">
                                        <!-- Ghost tags as background (hidden when typing) -->
                                        <div v-show="!tagSearch" class="absolute inset-0 flex items-center gap-1.5 overflow-hidden pointer-events-none select-none flex-nowrap opacity-40 pl-[10px]" aria-hidden="true">
                                            <span class="text-xs text-gray-400 flex-shrink-0 mr-1">{{ selectedTags.length > 0 ? `+${tagsWithUsage.length - selectedTags.length} more tags...` : `Filter ${tagsWithUsage.length} tags...` }}</span>
                                            <span
                                                v-for="tag in tagsWithUsage.filter(t => !selectedTags.includes(t.name)).slice(0, 20)"
                                                :key="'ghost-' + tag.id"
                                                class="px-2 py-0.5 rounded-full text-xs font-medium text-gray-500 bg-white/[0.04] flex-shrink-0 whitespace-nowrap">
                                                {{ tag.display_name }}
                                            </span>
                                        </div>
                                        <!-- Visible input full width -->
                                        <input
                                            ref="tagSearchInput"
                                            v-model="tagSearch"
                                            type="text"
                                            placeholder=""
                                            class="relative w-full bg-transparent border-none text-xs text-white focus:outline-none cursor-text"
                                            @focus="onTagsEnter"
                                            @blur="onTagSearchBlur"
                                        />
                                    </div>
                                </div>
                                <!-- Expand indicator centered at bottom -->
                                <div class="expand-tab" :class="{ 'expand-tab-active': tagsExpanded }">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Expanded overlay (CSS-only animation, no DOM mount/unmount) -->
                            <div class="tags-expanded-overlay" :class="{ 'tags-visible': tagsExpanded }">
                                <div class="flex flex-wrap gap-1.5 items-center">
                                    <button
                                        v-for="tag in filteredTagsForOverlay"
                                        :key="tag.id"
                                        @click="toggleTag(tag.name)"
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-xs font-semibold transition-all',
                                            selectedTags.includes(tag.name)
                                                ? 'bg-blue-600 text-white ring-1 ring-blue-400'
                                                : 'bg-white/10 text-gray-300 hover:bg-white/20'
                                        ]">
                                        {{ tag.display_name }}
                                        <span class="opacity-50 ml-0.5">({{ tag.usage_count }})</span>
                                    </button>
                                    <span v-if="filteredTagsForOverlay.length === 0" class="text-xs text-gray-500">No tags match</span>
                                    <button
                                        v-if="selectedTags.length > 0"
                                        @click="clearAllTags"
                                        class="px-2 py-0.5 rounded-full text-xs text-gray-400 hover:text-white hover:bg-white/10 transition flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-6" style="margin-top: -24rem;">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Sidebar Filters (side on md+, above maps on smaller) -->
                <div class="hidden md:block flex-shrink-0 w-[300px]">
                    <MapFiltersSidebar :queries="queries ?? {}" @search="onSidebarSearch" />
                </div>

                <!-- Main content -->
                <div class="flex-1 min-w-0">
                    <!-- Filters above maps on small screens -->
                    <div class="md:hidden mb-4">
                        <MapFiltersSidebar :queries="queries ?? {}" @search="onSidebarSearch" />
                    </div>
                    <!-- Loading skeleton -->
                    <div v-if="!mapsLoaded" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        <div v-for="i in 20" :key="i" class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden animate-pulse">
                            <div class="aspect-[4/3] bg-white/5"></div>
                            <div class="p-2 space-y-1.5">
                                <div class="h-4 bg-white/10 rounded w-3/4"></div>
                                <div class="h-3 bg-white/5 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Maps Grid -->
                    <div v-else-if="maps && maps.total > 0" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        <MapCard v-for="map in maps.data" :map="map" :key="map.id" />
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="maps" class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-3 opacity-40">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                        </svg>
                        <p class="font-semibold">No maps found</p>
                        <p class="text-sm">Adjust your filters</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="maps && maps.total > maps.per_page" class="flex justify-center mt-8">
                        <Pagination :current_page="maps.current_page" :last_page="maps.last_page" :link="maps.first_page_url" :only="['maps']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tags-bar {
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.375rem 0.75rem;
    cursor: text;
    transition: border-color 0.2s, background 0.2s;
}
.tags-bar:hover,
.tags-bar:focus-within {
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
}

.expand-tab {
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    padding: 0 0.5rem;
    line-height: 0;
    color: rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    z-index: 5;
}
.tags-bar:hover .expand-tab {
    color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.1);
}
.expand-tab-active {
    transform: translateX(-50%) rotate(180deg);
}
.tags-expanded-overlay {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 30;
    background: rgba(30, 35, 50, 0.92);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-top: 0.25rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);

    /* Hidden state */
    opacity: 0;
    max-height: 0;
    padding: 0 0.75rem;
    overflow: hidden;
    pointer-events: none;
    transform: translateY(-4px);
    transition: opacity 0.4s ease, max-height 0.4s ease, padding 0.4s ease, transform 0.4s ease;
}
.tags-expanded-overlay.tags-visible {
    opacity: 1;
    max-height: 500px;
    padding: 0.75rem;
    pointer-events: auto;
    transform: translateY(0);
    overflow-y: auto;
}
</style>