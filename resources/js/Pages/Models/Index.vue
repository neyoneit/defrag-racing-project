<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Pagination from '@/Components/Basic/Pagination.vue';

const props = defineProps({
    models: Object,
    category: String,
    sort: String,
    baseModel: String,
    authors: String,
    search: String,
    myUploads: Boolean,
    approvalStatus: String,
    perPage: { type: Number, default: 12 },
    load_times: Object,
    availableBaseModels: Object,
    availableAuthors: Object,
    hasUploads: { type: Boolean, default: false },
});

// Save current page URL to sessionStorage so "Back to models" returns here
function saveBackUrl() {
    const params = new URLSearchParams();
    if (props.category) params.set('category', props.category);
    if (props.sort) params.set('sort', props.sort);
    if (props.search) params.set('search', props.search);
    if (props.baseModel) params.set('base_model', props.baseModel);
    if (props.authors) params.set('authors', props.authors);
    if (props.myUploads) params.set('my_uploads', '1');
    if (props.models.current_page > 1) params.set('page', props.models.current_page);
    const query = params.toString();
    sessionStorage.setItem('models_back_url', '/models' + (query ? '?' + query : ''));
}

// Frontend timing metrics
const frontendTimings = ref({
    mount_to_ready: 0,
    total_page_load: 0,
    dom_content_loaded: 0,
});

// Calculate frontend load times
onMounted(() => {
    const mountTime = performance.now();
    frontendTimings.value.mount_to_ready = mountTime;

    // Use modern Navigation Timing API
    const navigationEntry = performance.getEntriesByType('navigation')[0];
    if (navigationEntry) {
        // DOM Content Loaded time
        frontendTimings.value.dom_content_loaded = navigationEntry.domContentLoadedEventEnd;

        // Total page load time
        frontendTimings.value.total_page_load = navigationEntry.loadEventEnd;
    }

    // If load event hasn't fired yet, wait for it
    if (document.readyState !== 'complete') {
        window.addEventListener('load', () => {
            const navEntry = performance.getEntriesByType('navigation')[0];
            if (navEntry) {
                frontendTimings.value.total_page_load = navEntry.loadEventEnd;
            }
        });
    }
});

const categories = [
    { value: 'all', label: 'All Models', icon: '🎨' },
    { value: 'player', label: 'Player Models', icon: '🏃' },
    { value: 'weapon', label: 'Weapon Models', icon: '🔫' },
    { value: 'shadow', label: 'Player Shadows', icon: '👤' },
];

const sortOptions = [
    { value: 'newest', label: 'Newest First' },
    { value: 'oldest', label: 'Oldest First' },
];

const gridCols = ref(4);
const searchQuery = ref(props.search || '');
let searchDebounce = null;
const onSearchInput = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters({ search: searchQuery.value || null });
    }, 400);
};

// Base model dropdown
const baseModelDropdownOpen = ref(false);
const baseModelSearchInput = ref(null);
const baseModelSearch = ref('');
const selectedBaseModels = ref(props.baseModel ? props.baseModel.split(',') : []);

const filteredBaseModels = computed(() => {
    if (!props.availableBaseModels) return [];
    const entries = Object.entries(props.availableBaseModels);
    if (!baseModelSearch.value) return entries;
    const q = baseModelSearch.value.toLowerCase();
    return entries.filter(([name]) => name.toLowerCase().includes(q));
});

const toggleBaseModel = (name) => {
    const idx = selectedBaseModels.value.indexOf(name);
    if (idx >= 0) {
        selectedBaseModels.value.splice(idx, 1);
    } else {
        selectedBaseModels.value.push(name);
    }
    applyFilters({ base_model: selectedBaseModels.value.length ? selectedBaseModels.value.join(',') : null });
};

const clearBaseModels = () => {
    selectedBaseModels.value = [];
    applyFilters({ base_model: null });
};

// Author dropdown
const authorDropdownOpen = ref(false);
const authorSearchInput = ref(null);
const authorSearch = ref('');
const selectedAuthors = ref(props.authors ? props.authors.split(',') : []);

const filteredAuthors = computed(() => {
    if (!props.availableAuthors) return [];
    const entries = Object.entries(props.availableAuthors);
    if (!authorSearch.value) return entries;
    const q = authorSearch.value.toLowerCase();
    return entries.filter(([name]) => name.toLowerCase().includes(q));
});

const toggleAuthor = (name) => {
    const idx = selectedAuthors.value.indexOf(name);
    if (idx >= 0) {
        selectedAuthors.value.splice(idx, 1);
    } else {
        selectedAuthors.value.push(name);
    }
    applyFilters({ authors: selectedAuthors.value.length ? selectedAuthors.value.join(',') : null });
};

const clearAuthors = () => {
    selectedAuthors.value = [];
    applyFilters({ authors: null });
};

// Close dropdowns when clicking outside
const handleClickOutside = (e) => {
    if (!e.target.closest('.base-model-dropdown')) baseModelDropdownOpen.value = false;
    if (!e.target.closest('.author-dropdown')) authorDropdownOpen.value = false;
};
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

// GIF preview mode: idle (default), rotate, gesture
const previewMode = ref(localStorage.getItem('models_preview_mode') || 'idle');
function setPreviewMode(mode) {
    previewMode.value = mode;
    localStorage.setItem('models_preview_mode', mode);
}
function getPreviewGif(model) {
    if (previewMode.value === 'rotate') return model.rotate_gif || model.thumbnail;
    if (previewMode.value === 'gesture' && model.category === 'player') return model.gesture_gif || model.idle_gif || model.thumbnail;
    if (previewMode.value === 'preview') return model.thumbnail || model.idle_gif;
    // default: idle (also fallback for gesture on weapons)
    return model.idle_gif || model.thumbnail;
}

const applyFilters = (updates) => {
    router.visit(route('models.index', {
        category: updates.category !== undefined ? updates.category : props.category,
        sort: updates.sort !== undefined ? updates.sort : props.sort,
        base_model: updates.base_model !== undefined ? updates.base_model : props.baseModel,
        authors: updates.authors !== undefined ? updates.authors : props.authors,
        search: updates.search !== undefined ? updates.search : searchQuery.value,
        my_uploads: updates.my_uploads !== undefined ? updates.my_uploads : props.myUploads,
        approval_status: updates.approval_status !== undefined ? updates.approval_status : props.approvalStatus,
        per_page: updates.per_page !== undefined ? updates.per_page : props.perPage,
    }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const switchCategory = (category) => {
    selectedBaseModels.value = [];
    selectedAuthors.value = [];
    searchQuery.value = '';
    applyFilters({ category, base_model: null, authors: null, search: null });
};
const changeSort = (newSort) => applyFilters({ sort: newSort });

const toggleMyUploads = () => {
    applyFilters({
        my_uploads: !props.myUploads,
        approval_status: !props.myUploads ? 'pending' : null
    });
};

const changeApprovalStatus = (status) => applyFilters({ approval_status: status });

const getApprovalStatusLabel = (status) => {
    const labels = {
        'pending': 'Pending Approval',
        'approved': 'Approved',
        'rejected': 'Rejected'
    };
    return labels[status] || status;
};

const getApprovalStatusBadgeClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
        'approved': 'bg-green-500/20 text-green-400 border border-green-500/30',
        'rejected': 'bg-red-500/20 text-red-400 border border-red-500/30'
    };
    return classes[status] || 'bg-gray-500/20 text-gray-400 border border-gray-500/30';
};

const getSkinName = (model) => {
    let skins = model.available_skins;
    if (typeof skins === 'string') {
        try { skins = JSON.parse(skins); } catch (e) { return ''; }
    }
    if (Array.isArray(skins) && skins[0] && skins[0] !== 'default') {
        return '/' + skins[0];
    }
    return '';
};

const getModelTypeLabel = (type) => {
    const labels = {
        'complete': 'Complete Model',
        'skin': 'Skin Pack',
        'sound': 'Sound Pack',
        'mixed': 'Mixed Pack'
    };
    return labels[type] || type;
};

const getModelTypeBadgeClass = (type) => {
    const classes = {
        'complete': 'bg-green-500/20 text-green-400 border border-green-500/30',
        'skin': 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
        'sound': 'bg-purple-500/20 text-purple-400 border border-purple-500/30',
        'mixed': 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
    };
    return classes[type] || 'bg-gray-500/20 text-gray-400 border border-gray-500/30';
};
</script>

<template>
    <Head title="Models" />
    <!-- Models Index Page -->
    <div class="min-h-screen">
        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-28">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-4xl font-black text-white mb-2">Quake 3 Models</h1>
                        <p class="text-gray-400">Browse and download custom player and weapon models</p>
                    </div>
                    <div class="flex items-center gap-3">
                    <div class="relative group">
                        <Link v-if="$page.props.auth.user"
                              :href="route('models.create')"
                              class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-blue-500/50 inline-block">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Upload Model
                            </span>
                        </Link>
                        <button v-else
                                disabled
                                class="px-6 py-3 bg-white/5 text-gray-500 font-bold rounded-xl cursor-not-allowed shadow-lg">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Upload Model
                            </span>
                        </button>
                        <!-- Tooltip for non-logged-in users -->
                        <div v-if="!$page.props.auth.user" class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">
                            To upload a model, please login
                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content with Sidebar -->
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative z-10" style="margin-top: -6rem;">
            <div class="flex gap-6">
                <!-- Left Sidebar - Filters -->
                <aside class="w-64 flex-shrink-0">
                    <div class="sticky top-6 space-y-2">
                        <!-- My Uploads Filter -->
                        <div v-if="$page.props.auth.user && hasUploads" class="bg-black/40 rounded-xl p-3 border border-white/5">
                            <h3 class="text-sm font-bold mb-3 flex items-center gap-2" :class="myUploads ? 'text-purple-400' : 'text-white'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                                My Uploads
                                <span v-if="myUploads" class="font-normal text-xs text-gray-500">(click to deselect)</span>
                            </h3>
                            <div class="flex gap-1">
                                <button
                                    @click="applyFilters({ my_uploads: !(myUploads && approvalStatus === 'pending'), approval_status: myUploads && approvalStatus === 'pending' ? null : 'pending' })"
                                    :class="[
                                        'flex-1 px-2 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                        myUploads && approvalStatus === 'pending'
                                            ? 'bg-yellow-500/30 text-yellow-300 border border-yellow-500/50'
                                            : 'text-gray-500 hover:bg-white/5 hover:text-white'
                                    ]">
                                    Pending
                                </button>
                                <button
                                    @click="applyFilters({ my_uploads: !(myUploads && approvalStatus === 'approved'), approval_status: myUploads && approvalStatus === 'approved' ? null : 'approved' })"
                                    :class="[
                                        'flex-1 px-2 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                        myUploads && approvalStatus === 'approved'
                                            ? 'bg-green-500/30 text-green-300 border border-green-500/50'
                                            : 'text-gray-500 hover:bg-white/5 hover:text-white'
                                    ]">
                                    Approved
                                </button>
                                <button
                                    @click="applyFilters({ my_uploads: !(myUploads && approvalStatus === 'rejected'), approval_status: myUploads && approvalStatus === 'rejected' ? null : 'rejected' })"
                                    :class="[
                                        'flex-1 px-2 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                        myUploads && approvalStatus === 'rejected'
                                            ? 'bg-red-500/30 text-red-300 border border-red-500/50'
                                            : 'text-gray-500 hover:bg-white/5 hover:text-white'
                                    ]">
                                    Rejected
                                </button>
                            </div>
                        </div>

                        <!-- Search Filter -->
                        <div class="bg-black/40 rounded-xl p-4 border border-white/5">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                                Search
                            </h3>
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Name, author, base model..."
                                class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                        </div>

                        <!-- Category Filter -->
                        <div class="bg-black/40 rounded-xl p-3 border border-white/5">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                                Category
                            </h3>
                            <div class="space-y-1">
                                <button v-for="cat in categories" :key="cat.value"
                                        @click="switchCategory(cat.value)"
                                        :class="[
                                            'w-full text-left px-3 py-1.5 rounded-lg font-medium transition-all text-sm',
                                            category === cat.value
                                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                    {{ cat.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Base Model Filter -->
                        <div class="bg-black/40 rounded-xl p-4 border border-white/5 base-model-dropdown">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                                Base Model
                                <span v-if="selectedBaseModels.length" class="ml-auto text-xs bg-blue-500/30 text-blue-300 px-1.5 py-0.5 rounded-full">{{ selectedBaseModels.length }}</span>
                            </h3>
                            <input
                                ref="baseModelSearchInput"
                                v-model="baseModelSearch"
                                type="text"
                                :placeholder="selectedBaseModels.length ? selectedBaseModels.join(', ') : 'Search base models...'"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 transition-colors"
                                :class="{ 'placeholder-white': selectedBaseModels.length }"
                                @focus="baseModelDropdownOpen = true"
                                @click.stop
                            />
                            <!-- Dropdown panel -->
                            <div v-if="baseModelDropdownOpen" class="mt-1 bg-gray-900/95 border border-white/10 rounded-lg shadow-xl overflow-hidden flex flex-col resize-y" style="min-height: 120px; height: 240px;">
                                <div class="overflow-y-auto flex-1">
                                    <label
                                        v-for="[name, count] in filteredBaseModels"
                                        :key="name"
                                        class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5 cursor-pointer text-sm"
                                        @click.stop
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedBaseModels.includes(name)"
                                            @change="toggleBaseModel(name)"
                                            class="rounded bg-white/10 border-white/20 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0"
                                        />
                                        <span class="text-gray-300 truncate flex-1">{{ name }}</span>
                                        <span class="text-gray-600 text-xs">{{ count }}</span>
                                    </label>
                                    <div v-if="filteredBaseModels.length === 0" class="px-3 py-4 text-center text-gray-500 text-sm">No results</div>
                                </div>
                                <button v-if="selectedBaseModels.length" @click.stop="clearBaseModels" class="w-full px-3 py-2 text-xs text-red-400 hover:bg-red-500/10 border-t border-white/10 transition-colors">
                                    Clear selection
                                </button>
                            </div>
                        </div>

                        <!-- Author Filter -->
                        <div class="bg-black/40 rounded-xl p-4 border border-white/5 author-dropdown">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                Author
                                <span v-if="selectedAuthors.length" class="ml-auto text-xs bg-blue-500/30 text-blue-300 px-1.5 py-0.5 rounded-full">{{ selectedAuthors.length }}</span>
                            </h3>
                            <input
                                ref="authorSearchInput"
                                v-model="authorSearch"
                                type="text"
                                :placeholder="selectedAuthors.length ? selectedAuthors.join(', ') : 'Search authors...'"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 transition-colors"
                                :class="{ 'placeholder-white': selectedAuthors.length }"
                                @focus="authorDropdownOpen = true"
                                @click.stop
                            />
                            <!-- Dropdown panel -->
                            <div v-if="authorDropdownOpen" class="mt-1 bg-gray-900/95 border border-white/10 rounded-lg shadow-xl overflow-hidden flex flex-col resize-y" style="min-height: 120px; height: 240px;">
                                <div class="overflow-y-auto flex-1">
                                    <label
                                        v-for="[name, count] in filteredAuthors"
                                        :key="name"
                                        class="flex items-center gap-2 px-3 py-1.5 hover:bg-white/5 cursor-pointer text-sm"
                                        @click.stop
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedAuthors.includes(name)"
                                            @change="toggleAuthor(name)"
                                            class="rounded bg-white/10 border-white/20 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0"
                                        />
                                        <span class="text-gray-300 truncate flex-1">{{ name }}</span>
                                        <span class="text-gray-600 text-xs">{{ count }}</span>
                                    </label>
                                    <div v-if="filteredAuthors.length === 0" class="px-3 py-4 text-center text-gray-500 text-sm">No results</div>
                                </div>
                                <button v-if="selectedAuthors.length" @click.stop="clearAuthors" class="w-full px-3 py-2 text-xs text-red-400 hover:bg-red-500/10 border-t border-white/10 transition-colors">
                                    Clear selection
                                </button>
                            </div>
                        </div>

                        <!-- Sort, Preview & Display -->
                        <div class="bg-black/40 rounded-xl p-3 border border-white/5 space-y-2.5">
                            <div>
                                <div class="text-xs font-bold text-gray-400 mb-1.5 flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.577 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.577-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Preview
                                </div>
                                <div class="flex gap-1">
                                    <button
                                        v-for="mode in [{ value: 'idle', label: 'Idle' }, { value: 'rotate', label: 'Rotate' }, ...(category === 'player' || category === 'all' ? [{ value: 'gesture', label: 'Gesture' }] : []), { value: 'preview', label: 'Thumb' }]"
                                        :key="mode.value"
                                        @click="setPreviewMode(mode.value)"
                                        :class="[
                                            'flex-1 px-1 py-1.5 rounded-lg font-medium transition-all text-xs text-center',
                                            previewMode === mode.value
                                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                        {{ mode.label }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-gray-400 mb-1 flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                    </svg>
                                    Sort
                                </div>
                                <div class="flex gap-1">
                                    <button
                                        v-for="option in sortOptions"
                                        :key="option.value"
                                        @click="changeSort(option.value)"
                                        :class="[
                                            'flex-1 px-2 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                            (sort || 'newest') === option.value
                                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <div class="text-xs font-bold text-gray-400 mb-1 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        Per Page
                                    </div>
                                    <div class="flex gap-1">
                                        <button
                                            v-for="n in [12, 24, 48]"
                                            :key="n"
                                            @click="applyFilters({ per_page: n })"
                                            :class="[
                                                'flex-1 px-1 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                                (perPage || 12) === n
                                                    ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                    : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                            ]">
                                            {{ n }}
                                        </button>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs font-bold text-gray-400 mb-1 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z" />
                                        </svg>
                                        Grid
                                    </div>
                                    <div class="flex gap-1">
                                        <button
                                            v-for="n in [3, 4, 6]"
                                            :key="n"
                                            @click="gridCols = n"
                                            :class="[
                                                'flex-1 px-1 py-1 rounded-lg font-medium transition-all text-xs text-center',
                                                gridCols === n
                                                    ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                    : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                            ]">
                                            {{ n }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </aside>

                <!-- Main Content Area -->
                <main class="flex-1">
                    <!-- Active Filters -->
                    <div v-if="selectedBaseModels.length || selectedAuthors.length || searchQuery" class="mb-6 flex flex-wrap gap-2">
                        <div v-for="bm in selectedBaseModels" :key="'bm-'+bm" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/20 border border-blue-500/30 rounded-lg text-sm text-blue-300">
                            <span class="text-xs text-blue-400">Base:</span> {{ bm }}
                            <button @click="toggleBaseModel(bm)" class="text-blue-400 hover:text-blue-200 ml-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div v-for="a in selectedAuthors" :key="'au-'+a" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-500/20 border border-purple-500/30 rounded-lg text-sm text-purple-300">
                            <span class="text-xs text-purple-400">Author:</span> {{ a }}
                            <button @click="toggleAuthor(a)" class="text-purple-400 hover:text-purple-200 ml-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Models Grid -->
                    <div v-if="models.data.length > 0" class="grid" :class="{
                        'grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-6': gridCols === 3,
                        'grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4': gridCols === 4,
                        'grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3': gridCols === 6,
                    }">
                        <Link v-for="(model, index) in models.data" :key="model.id"
                              :href="model.is_nsfw && !$page.props.auth.user ? route('login') : route('models.show', model.id)"
                              @click="saveBackUrl()"
                              class="group bg-black/40 rounded-xl border border-white/5 hover:border-white/20 transition-all duration-300 shadow-2xl hover:shadow-blue-500/20 overflow-hidden">

                            <!-- Thumbnail -->
                            <div class="aspect-square bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center relative overflow-hidden">
                                <!-- Pre-rendered GIF thumbnail -->
                                <img
                                    v-if="getPreviewGif(model)"
                                    :src="`/storage/${getPreviewGif(model)}`"
                                    :alt="model.name"
                                    :class="['w-full h-full object-cover', model.is_nsfw && !$page.props.auth.user?.nsfw_confirmed ? 'blur-xl scale-110' : '']"
                                    :loading="index < 8 ? 'eager' : 'lazy'"
                                    decoding="async"
                                    fetchpriority="auto"
                                />
                                <!-- Fallback to emoji if no thumbnail -->
                                <div v-else class="text-6xl">
                                    {{ model.category === 'player' ? '🏃' : model.category === 'weapon' ? '🔫' : '👤' }}
                                </div>

                                <!-- NSFW overlay -->
                                <div v-if="model.is_nsfw && !$page.props.auth.user?.nsfw_confirmed" class="absolute inset-0 flex items-center justify-center bg-black/40">
                                    <span class="px-4 py-2 bg-red-600/80 rounded-lg text-sm font-black text-white border border-red-500/50">
                                        NSFW
                                    </span>
                                </div>

                                <!-- Badges -->
                                <div class="absolute top-3 right-3 flex flex-col gap-2 items-end">
                                    <!-- NSFW Badge -->
                                    <div v-if="model.is_nsfw" class="px-3 py-1 bg-red-600/80 rounded-full text-xs font-bold text-white border border-red-500/40">
                                        NSFW
                                    </div>

                                    <!-- Category Badge (only in All Models view) -->
                                    <div v-if="!category || category === 'all'" class="px-3 py-1 bg-black/60 rounded-full text-xs font-bold text-white border border-white/20">
                                        {{ model.category }}
                                    </div>

                                    <!-- Approval Status Badge (only for My Uploads) -->
                                    <div v-if="myUploads" :class="getApprovalStatusBadgeClass(model.approval_status)" class="px-3 py-1 rounded-full text-xs font-bold">
                                        {{ getApprovalStatusLabel(model.approval_status) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Info -->
                            <div :class="gridCols >= 6 ? 'p-2' : 'p-4'">
                                <h3 :class="[gridCols >= 6 ? 'text-sm' : 'text-lg', 'font-black text-white mb-1 group-hover:text-blue-400 transition-colors truncate']">
                                    {{ model.name }}
                                </h3>

                                <div class="text-sm text-gray-400" :class="gridCols < 6 ? 'mb-3' : 'mb-1'">
                                    <p v-if="model.author" :class="[gridCols >= 6 ? 'text-xs' : '', 'truncate']" :title="model.author">by <span v-html="q3tohtml(model.author)"></span></p>
                                    <p v-if="model.base_model && gridCols <= 3" class="text-xs text-gray-500 font-mono">/model {{ model.base_model }}{{ getSkinName(model) }}</p>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20">
                        <div class="text-6xl mb-4">📦</div>
                        <h3 class="text-xl font-bold text-white mb-2">No models found</h3>
                        <p class="text-gray-400 mb-6">
                            <template v-if="myUploads">You haven't uploaded any models yet.</template>
                            <template v-else>Be the first to upload a {{ category === 'all' ? '' : category }} model!</template>
                        </p>
                        <Link v-if="$page.props.auth.user" :href="route('models.create')"
                              class="inline-block px-6 py-3 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 transition-all">
                            Upload Model
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div v-if="models.data.length > 0 && models.last_page > 1" class="mt-8">
                        <Pagination
                            :current_page="models.current_page"
                            :last_page="models.last_page"
                            :link="models.first_page_url"
                            :only="['models']"
                        />
                    </div>

                    <!-- Performance Metrics Panel (only visible to admin neyoneit) -->
                    <div v-if="load_times && $page.props.auth?.user?.username === 'neyoneit'" class="mt-8">
                        <div class="bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
                            <h3 class="text-lg font-bold text-white mb-4">⚡ Performance Metrics</h3>

                            <!-- Backend Timings -->
                            <div class="mb-6">
                                <h4 class="text-sm font-semibold text-blue-400 mb-3">🖥️ Backend (Server)</h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    <div v-for="(time, key) in load_times" :key="key" class="bg-white/5 rounded-lg p-3 border border-white/10">
                                        <div class="text-xs text-gray-400 uppercase mb-1">{{ key.replace(/_/g, ' ') }}</div>
                                        <div class="text-xl font-bold" :class="typeof time === 'number' ? (time > 1000 ? 'text-red-400' : time > 500 ? 'text-yellow-400' : 'text-green-400') : 'text-blue-400'">
                                            {{ typeof time === 'number' ? time + 'ms' : time }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Frontend Timings -->
                            <div>
                                <h4 class="text-sm font-semibold text-purple-400 mb-3">🌐 Frontend (Browser)</h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <div v-for="(time, key) in { frontend_mount: frontendTimings.mount_to_ready, dom_content_loaded: frontendTimings.dom_content_loaded, total_page_load: frontendTimings.total_page_load }" :key="key" class="bg-white/5 rounded-lg p-3 border border-white/10">
                                        <div class="text-xs text-gray-400 uppercase mb-1">{{ key.replace(/_/g, ' ') }}</div>
                                        <div class="text-xl font-bold" :class="time > 1000 ? 'text-red-400' : time > 500 ? 'text-yellow-400' : 'text-green-400'">
                                            {{ Math.round(time) }}ms
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 text-sm text-gray-500">
                                <p><span class="text-green-400">Green</span> = Fast (&lt;500ms) | <span class="text-yellow-400">Yellow</span> = Moderate (500-1000ms) | <span class="text-red-400">Red</span> = Slow (&gt;1000ms)</p>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
