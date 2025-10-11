<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ModelViewer from '@/Components/ModelViewer.vue';

const props = defineProps({
    model: Object,
});

const viewer3D = ref(null);
const viewerLoaded = ref(false);
const viewerError = ref(false);
const animationsReady = ref(false);
const soundsReady = ref(false);
const soundsEnabled = ref(true);
const soundVolume = ref(0.5);

// Get the path to the model file (scan for MD3 files in models/players/* subdirectory)
const modelFilePath = computed(() => {
    if (!props.model.file_path) return null;

    // Quake 3 models are typically in models/players/[modelname]/
    // We need to find the head.md3 file
    // The file_path is like: models/extracted/worm-1760208803
    // And the actual MD3 is at: models/extracted/worm-1760208803/models/players/worm/head.md3

    // For now, we'll construct the path by finding the subdirectory name
    // Extract the model slug from file_path and assume standard Q3 structure
    const parts = props.model.file_path.split('/');
    const modelSlug = parts[parts.length - 1]; // e.g., "worm-1760208803"
    const modelName = modelSlug.split('-')[0]; // e.g., "worm"

    return `/storage/${props.model.file_path}/models/players/${modelName}/head.md3`;
});

// Get the path to the skin file
const skinFilePath = computed(() => {
    if (!props.model.file_path) return null;

    const parts = props.model.file_path.split('/');
    const modelSlug = parts[parts.length - 1];
    const modelName = modelSlug.split('-')[0];

    // Use default skin for head
    return `/storage/${props.model.file_path}/models/players/${modelName}/head_default.skin`;
});

const onViewerLoaded = (model) => {
    viewerLoaded.value = true;
    console.log('3D Model loaded:', model);
};

const onViewerError = (error) => {
    viewerError.value = true;
    console.error('3D Model error:', error);
};

const onAnimationsReady = (animations) => {
    animationsReady.value = true;
    console.log('Animations ready:', animations);
};

const onSoundsReady = (sounds) => {
    soundsReady.value = true;
    console.log('Sounds ready:', sounds);
};

const playAnimation = (legsAnim, torsoAnim = null) => {
    if (viewer3D.value) {
        if (legsAnim) {
            viewer3D.value.playLegsAnimation(legsAnim);
        }
        if (torsoAnim) {
            viewer3D.value.playTorsoAnimation(torsoAnim);
        }
    }
};

const playSound = (soundName) => {
    if (viewer3D.value) {
        viewer3D.value.playSound(soundName);
    }
};

const toggleSounds = () => {
    soundsEnabled.value = !soundsEnabled.value;
    if (viewer3D.value) {
        viewer3D.value.setSoundsEnabled(soundsEnabled.value);
    }
};

const updateVolume = (event) => {
    const volume = parseFloat(event.target.value);
    soundVolume.value = volume;
    if (viewer3D.value) {
        viewer3D.value.setSoundVolume(volume);
    }
};

const downloadModel = () => {
    window.location.href = route('models.download', props.model.id);
};
</script>

<template>
    <Head :title="model.name" />
    <div class="min-h-screen py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Back Button -->
                <Link :href="route('models.index')" class="inline-flex items-center gap-2 text-gray-400 hover:text-white mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Models
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: 3D Viewer / Preview -->
                    <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-8">
                        <ModelViewer
                            v-if="modelFilePath"
                            ref="viewer3D"
                            :model-path="modelFilePath"
                            :skin-path="skinFilePath"
                            :auto-rotate="true"
                            :show-grid="true"
                            :enable-sounds="true"
                            @loaded="onViewerLoaded"
                            @error="onViewerError"
                            @animations-ready="onAnimationsReady"
                            @sounds-ready="onSoundsReady"
                            class="aspect-square rounded-xl"
                        />

                        <!-- Fallback if no model file path -->
                        <div v-else class="aspect-square bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-xl flex items-center justify-center relative">
                            <div v-if="model.thumbnail" class="w-full h-full rounded-xl overflow-hidden">
                                <img :src="`/storage/${model.thumbnail}`" :alt="model.name" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="text-center">
                                <div class="text-8xl mb-4">
                                    {{ model.category === 'player' ? '🏃' : model.category === 'weapon' ? '🔫' : '👤' }}
                                </div>
                                <p class="text-gray-400 text-sm">No 3D Model Available</p>
                            </div>
                        </div>

                        <!-- Animation Controls -->
                        <div v-if="animationsReady" class="mt-4">
                            <h4 class="text-sm font-bold text-gray-300 mb-2">Animations</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <button @click="playAnimation('LEGS_WALK')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>🚶</span>
                                        Walk
                                    </span>
                                </button>
                                <button @click="playAnimation('LEGS_RUN')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>🏃</span>
                                        Run
                                    </span>
                                </button>
                                <button @click="playAnimation('LEGS_JUMP')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>⬆️</span>
                                        Jump
                                    </span>
                                </button>
                                <button @click="playAnimation('LEGS_IDLECR')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>⬇️</span>
                                        Crouch
                                    </span>
                                </button>
                                <button @click="playAnimation('LEGS_IDLE', 'TORSO_GESTURE')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>👋</span>
                                        Gesture
                                    </span>
                                </button>
                                <button @click="playAnimation('LEGS_IDLE')" class="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-white rounded-lg font-semibold transition-colors">
                                    <span class="flex items-center justify-center gap-2">
                                        <span>🧍</span>
                                        Idle
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Sound Controls -->
                        <div v-if="soundsReady" class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-gray-300">Sounds</h4>
                                <button @click="toggleSounds" :class="[
                                    'px-3 py-1 rounded-lg text-xs font-semibold transition-all',
                                    soundsEnabled
                                        ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                        : 'bg-red-500/20 text-red-400 border border-red-500/30'
                                ]">
                                    {{ soundsEnabled ? '🔊 ON' : '🔇 OFF' }}
                                </button>
                            </div>

                            <!-- Volume Slider -->
                            <div class="mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400">Volume:</span>
                                    <input
                                        type="range"
                                        min="0"
                                        max="1"
                                        step="0.1"
                                        :value="soundVolume"
                                        @input="updateVolume"
                                        class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                        :disabled="!soundsEnabled"
                                    />
                                    <span class="text-xs text-gray-400 w-8">{{ Math.round(soundVolume * 100) }}%</span>
                                </div>
                            </div>

                            <!-- Sound Test Buttons -->
                            <div class="grid grid-cols-3 gap-2">
                                <button @click="playSound('jump1')" :disabled="!soundsEnabled" class="px-3 py-2 bg-purple-500/20 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                                    Jump
                                </button>
                                <button @click="playSound('taunt')" :disabled="!soundsEnabled" class="px-3 py-2 bg-purple-500/20 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                                    Taunt
                                </button>
                                <button @click="playSound('fall1')" :disabled="!soundsEnabled" class="px-3 py-2 bg-purple-500/20 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                                    Land
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Model Info -->
                    <div>
                        <!-- Title and Category -->
                        <div class="mb-6">
                            <div class="flex items-start justify-between mb-2">
                                <h1 class="text-4xl font-black text-white">{{ model.name }}</h1>
                                <span class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-full text-sm font-bold text-blue-400">
                                    {{ model.category }}
                                </span>
                            </div>
                            <p v-if="model.author" class="text-gray-400">
                                Created by <span class="text-white font-semibold">{{ model.author }}</span>
                                <span v-if="model.author_email" class="text-gray-500">
                                    ({{ model.author_email }})
                                </span>
                            </p>
                        </div>

                        <!-- Description -->
                        <div v-if="model.description" class="backdrop-blur-xl bg-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-3">Description</h3>
                            <p class="text-gray-300 whitespace-pre-line">{{ model.description }}</p>
                        </div>

                        <!-- Technical Details -->
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-4">Technical Details</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-if="model.poly_count">
                                    <div class="text-gray-400 text-sm mb-1">Polygon Count</div>
                                    <div class="text-white font-bold">{{ model.poly_count.toLocaleString() }}</div>
                                </div>
                                <div v-if="model.vert_count">
                                    <div class="text-gray-400 text-sm mb-1">Vertex Count</div>
                                    <div class="text-white font-bold">{{ model.vert_count.toLocaleString() }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm mb-1">Custom Sounds</div>
                                    <div class="text-white font-bold">{{ model.has_sounds ? 'Yes ✓' : 'No' }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm mb-1">CTF Skins</div>
                                    <div class="text-white font-bold">{{ model.has_ctf_skins ? 'Yes ✓' : 'No' }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm mb-1">Downloads</div>
                                    <div class="text-white font-bold">{{ model.downloads }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm mb-1">Uploaded</div>
                                    <div class="text-white font-bold">{{ new Date(model.created_at).toLocaleDateString() }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Uploader Info -->
                        <div class="backdrop-blur-xl bg-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-3">Uploaded By</h3>
                            <Link :href="route('profile.index', model.user.id)" class="flex items-center gap-3 hover:bg-white/5 rounded-lg p-2 -m-2 transition-all">
                                <img :src="model.user.profile_photo_path ? `/storage/${model.user.profile_photo_path}` : '/images/null.jpg'"
                                     :alt="model.user.name"
                                     class="w-12 h-12 rounded-full object-cover ring-2 ring-white/10">
                                <div>
                                    <div class="text-white font-bold" v-html="q3tohtml(model.user.name)"></div>
                                    <div class="text-gray-400 text-sm">View Profile →</div>
                                </div>
                            </Link>
                        </div>

                        <!-- Download Button -->
                        <button @click="downloadModel"
                                class="w-full px-6 py-4 bg-gradient-to-r from-green-500 to-green-600 text-white font-black rounded-xl hover:from-green-600 hover:to-green-700 transition-all shadow-lg hover:shadow-green-500/50 text-lg">
                            <span class="flex items-center justify-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Download Model
                            </span>
                        </button>
                        <p class="text-center text-gray-500 text-sm mt-2">
                            Extract to your Quake 3 baseq3 directory
                        </p>
                    </div>
                </div>
            </div>
        </div>
</template>
