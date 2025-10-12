<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import ModelViewer from '@/Components/ModelViewer.vue';

const props = defineProps({
    models: Object,
    category: String,
});

const categories = [
    { value: 'all', label: 'All Models', icon: '🎨' },
    { value: 'player', label: 'Player Models', icon: '🏃' },
    { value: 'weapon', label: 'Weapon Models', icon: '🔫' },
    { value: 'shadow', label: 'Shadow Models', icon: '👤' },
];

const switchCategory = (category) => {
    router.visit(route('models.index', { category }), {
        preserveState: true,
        preserveScroll: true,
    });
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
    <div class="min-h-screen py-12">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h1 class="text-4xl font-black text-white mb-2">Quake 3 Models</h1>
                            <p class="text-gray-400">Browse and download custom player, weapon, and shadow models</p>
                        </div>
                        <div v-if="$page.props.auth.user" class="flex gap-3">
                            <!-- Bulk Upload (Admin Only) -->
                            <Link v-if="$page.props.auth.user.admin" :href="route('models.bulk-upload')"
                                  class="px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-bold rounded-xl hover:from-purple-600 hover:to-purple-700 transition-all shadow-lg hover:shadow-purple-500/50">
                                <span class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15M9 12l3 3m0 0l3-3m-3 3V2.25" />
                                    </svg>
                                    Bulk Upload
                                </span>
                            </Link>

                            <!-- Single Upload -->
                            <Link :href="route('models.create')"
                                  class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-blue-500/50">
                                <span class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Upload Model
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- Category Tabs -->
                    <div class="flex gap-2 flex-wrap">
                        <button v-for="cat in categories" :key="cat.value"
                                @click="switchCategory(cat.value)"
                                :class="[
                                    'px-4 py-2 rounded-lg font-semibold transition-all',
                                    category === cat.value
                                        ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                                        : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                                ]">
                            <span class="flex items-center gap-2">
                                <span>{{ cat.icon }}</span>
                                <span>{{ cat.label }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Models Grid -->
                <div v-if="models.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <Link v-for="model in models.data" :key="model.id"
                          :href="route('models.show', model.id)"
                          class="group backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 hover:border-white/20 transition-all duration-300 hover:shadow-2xl hover:shadow-blue-500/20 overflow-hidden">

                        <!-- Thumbnail - Always show 3D viewer if file_path exists -->
                        <div class="aspect-square bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center relative overflow-hidden">
                            <!-- 3D Model Viewer as thumbnail (preferred) -->
                            <ModelViewer
                                v-if="model.file_path"
                                :key="model.id"
                                :model-path="`${model.file_path.startsWith('models/basequake3') ? '/' : '/storage/'}${model.file_path}/models/players/${model.name}/head.md3`"
                                :skin-path="`${model.file_path.startsWith('models/basequake3') ? '/' : '/storage/'}${model.file_path}/models/players/${model.name}/head_default.skin`"
                                skin-name="default"
                                :auto-rotate="false"
                                :show-grid="false"
                                :enable-sounds="false"
                                :thumbnail-mode="true"
                                style="width: 100%; height: 100%; pointer-events: none;"
                            />
                            <!-- Fallback to emoji if no file_path -->
                            <div v-else class="text-6xl">
                                {{ model.category === 'player' ? '🏃' : model.category === 'weapon' ? '🔫' : '👤' }}
                            </div>

                            <!-- Category Badge -->
                            <div class="absolute top-3 right-3 px-3 py-1 bg-black/60 backdrop-blur-sm rounded-full text-xs font-bold text-white border border-white/20">
                                {{ model.category }}
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
                    <p class="text-gray-400 mb-6">Be the first to upload a {{ category === 'all' ? '' : category }} model!</p>
                    <Link v-if="$page.props.auth.user" :href="route('models.create')"
                          class="inline-block px-6 py-3 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 transition-all">
                        Upload Model
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="models.data.length > 0 && (models.prev_page_url || models.next_page_url)" class="mt-8 flex justify-center gap-2">
                    <Link v-if="models.prev_page_url" :href="models.prev_page_url"
                          class="px-4 py-2 bg-white/5 text-white rounded-lg hover:bg-white/10 transition-all">
                        Previous
                    </Link>
                    <span class="px-4 py-2 text-gray-400">
                        Page {{ models.current_page }} of {{ models.last_page }}
                    </span>
                    <Link v-if="models.next_page_url" :href="models.next_page_url"
                          class="px-4 py-2 bg-white/5 text-white rounded-lg hover:bg-white/10 transition-all">
                        Next
                    </Link>
                </div>
            </div>
        </div>
</template>
