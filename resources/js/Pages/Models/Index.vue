<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    models: Object,
    category: String,
    sort: String,
    baseModel: String,
    search: String,
    myUploads: Boolean,
    approvalStatus: String,
    load_times: Object,
});

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
];

const sortOptions = [
    { value: 'newest', label: 'Newest First' },
    { value: 'oldest', label: 'Oldest First' },
];

const searchQuery = ref(props.search || '');

const applyFilters = (updates) => {
    router.visit(route('models.index', {
        category: updates.category !== undefined ? updates.category : props.category,
        sort: updates.sort !== undefined ? updates.sort : props.sort,
        base_model: updates.base_model !== undefined ? updates.base_model : props.baseModel,
        search: updates.search !== undefined ? updates.search : searchQuery.value,
        my_uploads: updates.my_uploads !== undefined ? updates.my_uploads : props.myUploads,
        approval_status: updates.approval_status !== undefined ? updates.approval_status : props.approvalStatus,
    }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const switchCategory = (category) => applyFilters({ category });
const changeSort = (newSort) => applyFilters({ sort: newSort });
const clearBaseModelFilter = () => applyFilters({ base_model: null });

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
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-4xl font-black text-white mb-2">Quake 3 Models</h1>
                        <p class="text-gray-400">Browse and download custom player and weapon models</p>
                    </div>
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

        <!-- Main Content with Sidebar -->
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6" style="margin-top: -22rem;">
            <div class="flex gap-6">
                <!-- Left Sidebar - Filters -->
                <aside class="w-64 flex-shrink-0">
                    <div class="sticky top-6 space-y-4">
                        <!-- Category Filter -->
                        <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 border border-white/5">
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
                                            'w-full text-left px-3 py-2 rounded-lg font-medium transition-all text-sm',
                                            category === cat.value
                                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                    <span class="flex items-center gap-2">
                                        <span>{{ cat.icon }}</span>
                                        <span>{{ cat.label }}</span>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- My Uploads Filter -->
                        <div v-if="$page.props.auth.user" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 border border-white/5">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                My Uploads
                            </h3>
                            <button
                                @click="toggleMyUploads()"
                                :class="[
                                    'w-full px-3 py-2 rounded-lg font-medium transition-all text-sm',
                                    myUploads
                                        ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/30'
                                        : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                                ]">
                                {{ myUploads ? 'Viewing My Uploads' : 'Show My Uploads' }}
                            </button>

                            <!-- Approval Status Sub-Filter -->
                            <div v-if="myUploads" class="mt-3 pt-3 border-t border-white/10">
                                <h4 class="text-xs font-semibold text-gray-400 mb-2">Status Filter</h4>
                                <div class="space-y-1">
                                    <button
                                        @click="changeApprovalStatus('pending')"
                                        :class="[
                                            'w-full text-left px-2 py-1.5 rounded text-xs font-medium transition-all',
                                            approvalStatus === 'pending'
                                                ? 'bg-yellow-500/30 text-yellow-300 border border-yellow-500/50'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                        Pending
                                    </button>
                                    <button
                                        @click="changeApprovalStatus('approved')"
                                        :class="[
                                            'w-full text-left px-2 py-1.5 rounded text-xs font-medium transition-all',
                                            approvalStatus === 'approved'
                                                ? 'bg-green-500/30 text-green-300 border border-green-500/50'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                        Approved
                                    </button>
                                    <button
                                        @click="changeApprovalStatus('rejected')"
                                        :class="[
                                            'w-full text-left px-2 py-1.5 rounded text-xs font-medium transition-all',
                                            approvalStatus === 'rejected'
                                                ? 'bg-red-500/30 text-red-300 border border-red-500/50'
                                                : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                        ]">
                                        Rejected
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sort Options -->
                        <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 border border-white/5">
                            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                </svg>
                                Sort By
                            </h3>
                            <div class="space-y-1">
                                <button
                                    v-for="option in sortOptions"
                                    :key="option.value"
                                    @click="changeSort(option.value)"
                                    :class="[
                                        'w-full text-left px-3 py-2 rounded-lg font-medium transition-all text-sm',
                                        (sort || 'newest') === option.value
                                            ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                            : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                    ]">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Main Content Area -->
                <main class="flex-1">
                    <!-- Base Model Filter Badge -->
                    <div v-if="baseModel" class="mb-6">
                        <div class="inline-flex items-center gap-3 px-4 py-3 bg-blue-500/20 border border-blue-500/30 rounded-xl">
                            <span class="text-sm text-blue-300">
                                Showing models based on: <span class="font-bold text-blue-200">{{ baseModel }}</span>
                            </span>
                            <button @click="clearBaseModelFilter" class="text-blue-400 hover:text-blue-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Models Grid -->
                    <div v-if="models.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Link v-for="(model, index) in models.data" :key="model.id"
                              :href="route('models.show', model.id)"
                              class="group backdrop-blur-xl bg-black/40 rounded-xl border border-white/5 hover:border-white/20 transition-all duration-300 shadow-2xl hover:shadow-blue-500/20 overflow-hidden">

                            <!-- Thumbnail -->
                            <div class="aspect-square bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center relative overflow-hidden">
                                <!-- Pre-rendered GIF thumbnail (instant load!) -->
                                <img
                                    v-if="model.thumbnail"
                                    :src="`/storage/${model.thumbnail}`"
                                    :alt="model.name"
                                    class="w-full h-full object-cover"
                                    :loading="index < 8 ? 'eager' : 'lazy'"
                                    decoding="async"
                                    fetchpriority="auto"
                                />
                                <!-- Fallback to emoji if no thumbnail -->
                                <div v-else class="text-6xl">
                                    {{ model.category === 'player' ? '🏃' : model.category === 'weapon' ? '🔫' : '👤' }}
                                </div>

                                <!-- Badges -->
                                <div class="absolute top-3 right-3 flex flex-col gap-2 items-end">
                                    <!-- Category Badge -->
                                    <div class="px-3 py-1 bg-black/60 backdrop-blur-sm rounded-full text-xs font-bold text-white border border-white/20">
                                        {{ model.category }}
                                    </div>

                                    <!-- Approval Status Badge (only for My Uploads) -->
                                    <div v-if="myUploads" :class="getApprovalStatusBadgeClass(model.approval_status)" class="px-3 py-1 backdrop-blur-sm rounded-full text-xs font-bold">
                                        {{ getApprovalStatusLabel(model.approval_status) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="p-4">
                                <h3 class="text-lg font-black text-white mb-1 group-hover:text-blue-400 transition-colors truncate">
                                    {{ model.name }}
                                </h3>

                                <div class="text-sm text-gray-400 mb-3">
                                    <p v-if="model.author">by {{ model.author }}</p>
                                    <p v-if="model.base_model" class="text-xs text-gray-500">based on {{ model.base_model }}</p>
                                    <div v-if="model.model_type" class="mt-2">
                                        <span :class="getModelTypeBadgeClass(model.model_type)" class="text-xs px-2 py-1 rounded font-semibold">
                                            {{ getModelTypeLabel(model.model_type) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                    <span v-if="model.poly_count" class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                        </svg>
                                        {{ model.poly_count }} poly
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        {{ model.downloads }}
                                    </span>
                                    <span v-if="model.has_sounds" title="Has custom sounds">🔊</span>
                                    <span v-if="model.has_ctf_skins" title="Has CTF skins">🏁</span>
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
                    <div v-if="models.data.length > 0 && (models.links.length > 3)" class="mt-8 flex justify-center gap-2 flex-wrap">
                        <template v-for="link in models.links" :key="link.label">
                            <Link v-if="link.url"
                                  :href="link.url"
                                  :class="[
                                      'px-4 py-2 rounded-lg font-medium transition-all',
                                      link.active
                                          ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                          : 'bg-white/5 text-gray-300 hover:bg-white/10 hover:text-white'
                                  ]"
                                  v-html="link.label">
                            </Link>
                            <span v-else
                                  class="px-4 py-2 rounded-lg font-medium transition-all bg-white/5 text-gray-600 cursor-not-allowed"
                                  v-html="link.label">
                            </span>
                        </template>
                    </div>

                    <!-- Performance Metrics Panel (only visible to admin neyoneit) -->
                    <div v-if="load_times && $page.props.auth?.user?.username === 'neyoneit'" class="mt-8">
                        <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
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
