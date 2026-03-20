<script setup>
    import { ref, shallowRef, onMounted, computed, onBeforeUnmount } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { markRaw } from 'vue';

    const props = defineProps({
        userId: [Number, String],
    });

    const modelsData = ref({
        models: [], total: 0, total_downloads: 0, total_views: 0,
        highlighted: null, timeline: {}, pinned: [],
    });
    const loading = ref(true);

    const fetchModels = async () => {
        loading.value = true;
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/models`);
            modelsData.value = await res.json();
        } catch (e) {
            console.error('Error loading models:', e);
        } finally {
            loading.value = false;
        }
    };

    onMounted(() => {
        fetchModels();
    });

    const formatNumber = (n) => {
        if (!n && n !== 0) return '0';
        return n.toLocaleString();
    };

    const playerModels = computed(() => modelsData.value.models.filter(m => m.category === 'player'));
    const weaponModels = computed(() => modelsData.value.models.filter(m => m.category === 'weapon'));

    // Group models by base_model
    const groupedByBase = computed(() => {
        const groups = {};
        for (const model of modelsData.value.models) {
            const base = model.base_model || model.name;
            if (!groups[base]) {
                groups[base] = [];
            }
            groups[base].push(model);
        }
        // Sort groups by total downloads desc
        return Object.entries(groups)
            .map(([base, models]) => ({
                base,
                models: models.sort((a, b) => b.downloads - a.downloads),
                totalDownloads: models.reduce((s, m) => s + m.downloads, 0),
                totalViews: models.reduce((s, m) => s + (m.views || 0), 0),
            }))
            .sort((a, b) => b.totalDownloads - a.totalDownloads);
    });

    // Timeline
    const timelineYears = computed(() => Object.keys(modelsData.value.timeline || {}).sort((a, b) => b - a));
    const timelineMax = computed(() => {
        let max = 1;
        for (const count of Object.values(modelsData.value.timeline || {})) {
            if (count > max) max = count;
        }
        return max;
    });

    // Pinned model viewer
    const ModelViewerComponent = shallowRef(null);
    const viewerLoaded = ref(false);

    // Lazy load ModelViewer component
    const loadModelViewer = async () => {
        if (ModelViewerComponent.value) return;
        const module = await import('@/Components/ModelViewer.vue');
        ModelViewerComponent.value = markRaw(module.default);
        viewerLoaded.value = true;
    };

    // Exact same logic as Models/Show.vue modelFilePath computed
    const getPinnedModelPath = (model) => {
        if (!model.file_path) return null;
        if (model.category === 'weapon') {
            const mainFile = model.main_file || `${model.base_model || model.name}.md3`;
            if (model.file_path.startsWith('baseq3/')) return `/${model.file_path}/${mainFile}`;
            return `/storage/${model.file_path}/${mainFile}`;
        }
        // Player model
        if (model.file_path.startsWith('baseq3/')) return `/${model.file_path}/head.md3`;
        if (model.file_path.toLowerCase().includes('/models/players/')) return `/storage/${model.file_path}/head.md3`;
        const modelName = model.base_model || model.name;
        return `/storage/${model.file_path}/models/players/${modelName}/head.md3`;
    };

    // Exact same logic as Models/Show.vue skinFilePath computed
    const getPinnedSkinPath = (model) => {
        if (model.category === 'weapon') return null;
        if (!model.file_path) return null;
        if (model.file_path.startsWith('baseq3/')) return `/${model.file_path}/head_default.skin`;
        const modelName = model.base_model || model.name;
        if (model.file_path.toLowerCase().includes('/models/players/')) return `/storage/${model.file_path}/head_default.skin`;
        return `/storage/${model.file_path}/models/players/${modelName}/head_default.skin`;
    };

    // Exact same logic as Models/Show.vue skinPackBasePath computed
    const getPinnedSkinPackBasePath = (model) => {
        if (model.model_type === 'complete') return null;
        if (!model.base_model_file_path) return null;
        if (model.file_path && !model.file_path.startsWith('baseq3/')) {
            if (model.file_path.toLowerCase().includes('/models/players/')) {
                return `/storage/${model.file_path}`;
            }
            const modelName = model.base_model || model.name;
            return `/storage/${model.file_path}/models/players/${modelName}`;
        }
        return null;
    };

    // Load viewer when pinned models exist
    onMounted(async () => {
        await fetchModels();
        if (modelsData.value.pinned?.length > 0) {
            await loadModelViewer();
        }
    });
</script>

<template>
    <div>
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
        </div>

        <!-- No Models State -->
        <div v-else-if="modelsData.total === 0" class="text-center py-20">
            <div class="text-6xl mb-4 opacity-50">&#x1F3AD;</div>
            <div class="text-xl font-bold text-gray-400">No models found for this creator</div>
            <div class="text-sm text-gray-500 mt-2">Claim your model author names in Settings to display your models here</div>
        </div>

        <!-- Main Content -->
        <div v-else>

            <!-- Pinned Models 3D Viewer -->
            <div v-if="modelsData.pinned?.length > 0 && viewerLoaded" class="mb-6">
                <div :class="modelsData.pinned.length === 1 ? 'grid grid-cols-1 max-w-2xl mx-auto' : 'grid grid-cols-1 md:grid-cols-2 gap-4'">
                    <div v-for="pinned in modelsData.pinned" :key="pinned.id"
                        class="bg-black/40 rounded-xl border border-white/5 overflow-hidden">
                        <div class="relative overflow-hidden" style="height: 400px;">
                            <component :is="ModelViewerComponent"
                                v-if="getPinnedModelPath(pinned)"
                                :model-path="getPinnedModelPath(pinned)"
                                :model-id="pinned.id"
                                :skin-path="getPinnedSkinPath(pinned)"
                                :skin-pack-base-path="getPinnedSkinPackBasePath(pinned)"
                                :base-model-name="pinned.base_model"
                                :base-model-file-path="pinned.base_model_file_path"
                                :load-full-player="pinned.category !== 'weapon'"
                                :is-weapon="pinned.category === 'weapon'"
                                :auto-rotate="true"
                                :show-grid="true"
                                :enable-sounds="false"
                                background-color="transparent"
                            />
                        </div>
                        <div class="px-4 py-2 border-t border-white/5 flex items-center justify-between">
                            <Link :href="route('models.show', pinned.id)" class="font-bold text-white hover:text-blue-400 transition">
                                {{ pinned.name }}
                            </Link>
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span>{{ formatNumber(pinned.downloads) }} downloads</span>
                                <span>{{ formatNumber(pinned.views || 0) }} views</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center">
                    <div class="text-2xl font-black text-blue-400">{{ modelsData.total }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Models</div>
                </div>
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center">
                    <div class="text-2xl font-black text-purple-400">{{ formatNumber(modelsData.total_downloads) }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Downloads</div>
                </div>
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center">
                    <div class="text-2xl font-black text-cyan-400">{{ formatNumber(modelsData.total_views) }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Views</div>
                </div>
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center">
                    <div class="text-2xl font-black text-emerald-400">{{ playerModels.length }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Player</div>
                </div>
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center">
                    <div class="text-2xl font-black text-orange-400">{{ weaponModels.length }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Weapon</div>
                </div>
            </div>

            <!-- Highlighted Model + Timeline -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
                <!-- Most Popular Model (3/5) -->
                <div v-if="modelsData.highlighted" class="lg:col-span-3 bg-gradient-to-br from-blue-500/5 via-purple-500/10 to-blue-500/5 border border-blue-500/20 rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute top-3 right-3">
                        <span class="text-xs font-black px-2 py-0.5 rounded bg-blue-500/20 border border-blue-500/30 text-blue-400">MOST POPULAR</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link :href="route('models.show', modelsData.highlighted.id)"
                            class="flex-shrink-0 w-24 h-24 rounded-lg bg-black/30 border border-white/10 overflow-hidden group">
                            <img v-if="modelsData.highlighted.idle_gif || modelsData.highlighted.thumbnail"
                                :src="`/storage/${modelsData.highlighted.idle_gif || modelsData.highlighted.thumbnail}`"
                                :alt="modelsData.highlighted.name"
                                class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                        </Link>
                        <div class="min-w-0">
                            <Link :href="route('models.show', modelsData.highlighted.id)"
                                class="text-lg font-black text-white hover:text-blue-400 transition block truncate">
                                {{ modelsData.highlighted.name }}
                            </Link>
                            <div class="text-xs text-gray-500 mt-0.5">
                                <span :class="modelsData.highlighted.category === 'player' ? 'text-emerald-400' : 'text-orange-400'" class="font-bold uppercase">{{ modelsData.highlighted.category }}</span>
                                <span v-if="modelsData.highlighted.base_model && modelsData.highlighted.base_model !== modelsData.highlighted.name" class="ml-2">
                                    based on <span class="text-gray-400">{{ modelsData.highlighted.base_model }}</span>
                                </span>
                            </div>
                            <div class="flex items-center gap-4 mt-2">
                                <div>
                                    <span class="text-xl font-black text-purple-400">{{ formatNumber(modelsData.highlighted.downloads) }}</span>
                                    <span class="text-xs text-gray-500 ml-1">downloads</span>
                                </div>
                                <div>
                                    <span class="text-xl font-black text-cyan-400">{{ formatNumber(modelsData.highlighted.views || 0) }}</span>
                                    <span class="text-xs text-gray-500 ml-1">views</span>
                                </div>
                            </div>
                            <div class="text-[10px] text-gray-600 mt-1">
                                Added {{ modelsData.highlighted.created_at ? new Date(modelsData.highlighted.created_at).toLocaleDateString() : 'unknown' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Models Timeline (2/5) -->
                <div class="lg:col-span-2 bg-black/40 rounded-xl p-4 border border-white/5">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider mb-3">Models Timeline</h3>
                    <div v-if="timelineYears.length > 0" class="space-y-1.5">
                        <div v-for="year in timelineYears" :key="year" class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 w-8 flex-shrink-0 text-right">{{ year }}</span>
                            <div class="flex-1 bg-gray-800/30 rounded-full h-5 overflow-hidden flex">
                                <div class="bg-gradient-to-r from-blue-600 to-purple-500 h-full flex items-center justify-center transition-all duration-500"
                                    :style="{ width: `${Math.max(8, (modelsData.timeline[year] / timelineMax) * 100)}%` }">
                                    <span class="text-[9px] font-black text-white whitespace-nowrap px-1">{{ modelsData.timeline[year] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-gray-600 text-center py-4">No timeline data</div>
                </div>
            </div>

            <!-- Models by Base Model -->
            <div v-if="groupedByBase.length > 0" class="space-y-4 mb-6">
                <div v-for="group in groupedByBase" :key="group.base" class="bg-black/40 rounded-xl border border-white/5 overflow-hidden">
                    <div class="flex items-center justify-between px-4 pt-3 pb-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-white uppercase tracking-wider">{{ group.base }}</h3>
                            <span class="text-[10px] text-gray-500">({{ group.models.length }} {{ group.models.length === 1 ? 'model' : 'models' }})</span>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] text-gray-500">
                            <span>{{ formatNumber(group.totalDownloads) }} dl</span>
                            <span>{{ formatNumber(group.totalViews) }} views</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 p-3 pt-0">
                        <Link v-for="model in group.models" :key="model.id"
                            :href="route('models.show', model.id)"
                            class="group bg-black/30 rounded-lg border border-white/5 hover:border-white/20 transition overflow-hidden">
                            <div class="aspect-square flex items-center justify-center overflow-hidden"
                                :class="model.category === 'weapon' ? 'bg-gradient-to-br from-orange-500/10 to-red-500/10' : 'bg-gradient-to-br from-blue-500/10 to-purple-500/10'">
                                <img v-if="model.idle_gif || model.thumbnail"
                                    :src="`/storage/${model.idle_gif || model.thumbnail}`"
                                    :alt="model.name"
                                    class="w-full h-full object-cover group-hover:scale-110 transition duration-300"
                                    loading="lazy">
                                <span v-else class="text-2xl">{{ model.category === 'player' ? '&#x1F3C3;' : '&#x1F52B;' }}</span>
                            </div>
                            <div class="px-1.5 py-1">
                                <div class="text-[10px] font-bold text-white truncate">{{ model.name }}</div>
                                <div class="flex items-center justify-between text-[9px] text-gray-600">
                                    <span>{{ formatNumber(model.downloads) }} dl</span>
                                    <span>{{ formatNumber(model.views || 0) }} views</span>
                                </div>
                                <div class="text-[8px] text-gray-700 mt-0.5">
                                    {{ model.created_at ? new Date(model.created_at).toLocaleDateString() : '' }}
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
