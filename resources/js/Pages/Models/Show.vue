<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
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
const availableAnimations = ref({ legs: {}, torso: {}, both: {} });
const availableSounds = ref([]);
const currentLegsAnim = ref(null);
const currentTorsoAnim = ref(null);
const showWireframe = ref(false);
const autoRotate = ref(false);
const animationSpeed = ref(1.0); // FPS multiplier
const manualFrame = ref(0);
const maxFrames = ref(10); // Will be updated based on animation

// Q3-style input state (mimics usercmd_t and playerState_t)
const forwardMove = ref(0);  // -127 to 127
const rightMove = ref(0);    // -127 to 127
const isCrouching = ref(false); // PMF_DUCKED
const isAttacking = ref(false); // BUTTON_ATTACK
const isGesturing = ref(false); // BUTTON_GESTURE

// Get the path to the model file (scan for MD3 files in models/players/* subdirectory)
const modelFilePath = computed(() => {
    if (!props.model.file_path) return null;

    // Check if this is a base Q3 model (starts with "models/basequake3/players/")
    if (props.model.file_path.startsWith('models/basequake3/players/')) {
        // Base models are in public, not storage
        // Path is like: models/basequake3/players/sarge
        return `/${props.model.file_path}/head.md3`;
    }

    // User-uploaded models (extracted from PK3)
    // The file_path is like: models/extracted/worm-1760208803
    // And the actual MD3 is at: models/extracted/worm-1760208803/models/players/worm/head.md3
    const parts = props.model.file_path.split('/');
    const modelSlug = parts[parts.length - 1]; // e.g., "worm-1760208803"
    const modelName = modelSlug.split('-')[0]; // e.g., "worm"

    return `/storage/${props.model.file_path}/models/players/${modelName}/head.md3`;
});

// Get the path to the skin file
const skinFilePath = computed(() => {
    if (!props.model.file_path) return null;

    // Check if this is a base Q3 model
    if (props.model.file_path.startsWith('models/basequake3/players/')) {
        return `/${props.model.file_path}/head_default.skin`;
    }

    // User-uploaded models
    const parts = props.model.file_path.split('/');
    const modelSlug = parts[parts.length - 1];
    const modelName = modelSlug.split('-')[0];

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
    console.log('🎬 Animations ready:', animations);
    console.log('🦵 LEGS:', Object.entries(animations.legs || {}).map(([k, v]) => `${k}: f${v.firstFrame}-${v.firstFrame + v.numFrames - 1}`));
    console.log('💪 TORSO:', Object.entries(animations.torso || {}).map(([k, v]) => `${k}: f${v.firstFrame}-${v.firstFrame + v.numFrames - 1}`));
    console.log('🔀 BOTH:', Object.keys(animations.both || {}));

    availableAnimations.value = animations;
    animationsReady.value = true;
};

const onSoundsReady = (sounds) => {
    soundsReady.value = true;
    availableSounds.value = sounds;
    console.log('Sounds ready:', sounds);
};

// Q3 ANIMATION SYSTEM: Animations ALWAYS play, they just switch between states
// This function is called every time input state changes (like Q3's PmoveSingle)
const updateAnimations = () => {
    if (!viewer3D.value) return;

    const animMgr = viewer3D.value.getAnimationManager();
    if (!animMgr) return;

    // Animations ALWAYS play in Q3!
    animMgr.playing = true;

    // === LEGS ANIMATION (PM_Footsteps logic) ===
    let legsAnim = null;

    // PM_Footsteps line 1345-1355: If not trying to move
    if (forwardMove.value === 0 && rightMove.value === 0) {
        // No movement = IDLE
        if (isCrouching.value) {
            legsAnim = 'LEGS_IDLECR';  // Crouched idle
        } else {
            legsAnim = 'LEGS_IDLE';     // Standing idle
        }
    } else {
        // PM_Footsteps line 1356+: Moving
        // Determine PMF_BACKWARDS_RUN flag (bg_pmove.c line 1920-1925)
        let backwardsRun = false;
        if (forwardMove.value < 0) {
            backwardsRun = true;
        } else if (forwardMove.value > 0 || (forwardMove.value === 0 && rightMove.value !== 0)) {
            backwardsRun = false;
        }

        if (isCrouching.value) {
            // PM_Footsteps line 1361-1368: Crouched movement
            if (backwardsRun) {
                legsAnim = 'LEGS_BACKCR';   // Q3: PM_ContinueLegsAnim(LEGS_BACKCR)
            } else {
                legsAnim = 'LEGS_WALKCR';   // Q3: PM_ContinueLegsAnim(LEGS_WALKCR)
            }
        } else {
            // PM_Footsteps line 1380-1398: Normal movement
            // Note: Q3 checks BUTTON_WALKING here, we don't have that button
            // Always assume NOT walking (running)
            if (backwardsRun) {
                legsAnim = 'LEGS_BACK';     // Q3: PM_ContinueLegsAnim(LEGS_BACK)
            } else {
                legsAnim = 'LEGS_RUN';      // Q3: PM_ContinueLegsAnim(LEGS_RUN)
            }
        }
    }

    // TORSO ANIMATION (always runs)
    let torsoAnim = 'TORSO_STAND';

    if (isGesturing.value) {
        torsoAnim = 'TORSO_GESTURE';  // Q3: PM_StartTorsoAnim(TORSO_GESTURE)
    } else if (isAttacking.value) {
        torsoAnim = 'TORSO_ATTACK';   // Q3: PM_StartTorsoAnim(TORSO_ATTACK)
    } else {
        torsoAnim = 'TORSO_STAND';    // Q3: PM_ContinueTorsoAnim(TORSO_STAND)
    }

    // Apply animations
    if (legsAnim) {
        viewer3D.value.playLegsAnimation(legsAnim);
        currentLegsAnim.value = legsAnim;

        if (animMgr.currentLegsAnim) {
            maxFrames.value = animMgr.currentLegsAnim.numFrames;
            manualFrame.value = 0;
        }
    }

    viewer3D.value.playTorsoAnimation(torsoAnim);
    currentTorsoAnim.value = torsoAnim;
};

// Input handlers - Q3-style HOLD buttons (mousedown = start, mouseup = stop)
const startMovement = (forward, right) => {
    forwardMove.value = forward;
    rightMove.value = right;
    updateAnimations();
};

const stopMovement = () => {
    forwardMove.value = 0;
    rightMove.value = 0;
    updateAnimations();
};

// HOLD-based action handlers (like Q3!)
const startCrouch = () => {
    isCrouching.value = true;
    updateAnimations();
};

const stopCrouch = () => {
    isCrouching.value = false;
    updateAnimations();
};

const startJump = () => {
    if (!viewer3D.value) return;
    const jumpAnim = forwardMove.value < 0 ? 'LEGS_JUMPB' : 'LEGS_JUMP';
    viewer3D.value.playLegsAnimation(jumpAnim);
    currentLegsAnim.value = jumpAnim;
};

const stopJump = () => {
    updateAnimations();
};

const startAttack = () => {
    isAttacking.value = true;
    updateAnimations();
};

const stopAttack = () => {
    isAttacking.value = false;
    updateAnimations();
};

const startGesture = () => {
    isGesturing.value = true;
    updateAnimations();
};

const stopGesture = () => {
    isGesturing.value = false;
    updateAnimations();
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

const updateAnimationSpeed = (event) => {
    const speed = parseFloat(event.target.value);
    animationSpeed.value = speed;
    if (viewer3D.value && viewer3D.value.getAnimationManager()) {
        viewer3D.value.getAnimationManager().setSpeed(speed);
    }
};

const updateManualFrame = (event) => {
    const frame = parseInt(event.target.value);
    manualFrame.value = frame;
    if (viewer3D.value && viewer3D.value.getAnimationManager()) {
        viewer3D.value.getAnimationManager().setManualFrame(frame);
    }
};

const downloadModel = () => {
    window.location.href = route('models.download', props.model.id);
};

// Watch for autoRotate changes
watch(autoRotate, (newValue) => {
    if (viewer3D.value) {
        viewer3D.value.setAutoRotate(newValue);
    }
});

// Watch for showWireframe changes
watch(showWireframe, (newValue) => {
    if (viewer3D.value) {
        viewer3D.value.setWireframe(newValue);
    }
});
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
                    <div>
                        <!-- 3D Viewer Card -->
                        <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-8 mb-8">
                            <!-- Viewer Controls -->
                            <div v-if="viewerLoaded" class="flex gap-2 mb-4">
                                <button @click="autoRotate = !autoRotate" :class="[
                                    'px-3 py-1 rounded-lg text-xs font-semibold transition-all',
                                    autoRotate
                                        ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                                        : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
                                ]">
                                    {{ autoRotate ? '🔄 Auto-Rotate ON' : '🔄 Auto-Rotate OFF' }}
                                </button>
                                <button @click="showWireframe = !showWireframe" :class="[
                                    'px-3 py-1 rounded-lg text-xs font-semibold transition-all',
                                    showWireframe
                                        ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                                        : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
                                ]">
                                    {{ showWireframe ? '📐 Wireframe ON' : '📐 Wireframe OFF' }}
                                </button>
                            </div>

                            <ModelViewer
                                v-if="modelFilePath"
                                ref="viewer3D"
                                :model-path="modelFilePath"
                                :skin-path="skinFilePath"
                                :auto-rotate="autoRotate"
                                :show-grid="true"
                                :enable-sounds="true"
                                @loaded="onViewerLoaded"
                                @error="onViewerError"
                                @animations-ready="onAnimationsReady"
                                @sounds-ready="onSoundsReady"
                                class="rounded-xl"
                                style="height: 900px;"
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
                        </div>

                        <!-- Animation Controls Card -->
                        <div v-if="animationsReady" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-8 mb-8">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-bold text-gray-300">Player Animations</h4>

                                <!-- Current Animation State - Fixed Position -->
                                <div class="flex gap-2 min-w-[200px] justify-end">
                                    <span v-if="currentLegsAnim" class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded-lg text-xs font-semibold border border-blue-500/30">
                                        {{ currentLegsAnim.replace('LEGS_', '') }}
                                    </span>
                                    <span v-if="currentTorsoAnim" class="px-2 py-1 bg-green-500/20 text-green-400 rounded-lg text-xs font-semibold border border-green-500/30">
                                        {{ currentTorsoAnim.replace('TORSO_', '') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Animation Speed Slider -->
                            <div class="mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400">Speed:</span>
                                    <input
                                        type="range"
                                        min="0.1"
                                        max="3.0"
                                        step="0.1"
                                        :value="animationSpeed"
                                        @input="updateAnimationSpeed"
                                        class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <span class="text-xs text-gray-400 w-12">{{ animationSpeed.toFixed(1) }}x</span>
                                </div>
                            </div>

                            <!-- Manual Frame Slider -->
                            <div class="mb-4 p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-yellow-400 font-semibold">Frame:</span>
                                    <input
                                        type="range"
                                        min="0"
                                        :max="maxFrames - 1"
                                        step="1"
                                        :value="manualFrame"
                                        @input="updateManualFrame"
                                        class="flex-1 h-2 bg-yellow-500/20 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <span class="text-xs text-yellow-400 w-16 font-mono">{{ manualFrame }}/{{ maxFrames - 1 }}</span>
                                </div>
                                <div class="text-xs text-yellow-400/70 mt-1">🔍 Debug: Manually step through frames</div>
                            </div>

                            <!-- Q3-Style Input Simulator -->
                            <div>
                                <h5 class="text-xs font-bold text-blue-400 mb-2">Q3 INPUT SIMULATOR</h5>

                                <!-- Movement Controls -->
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <div class="col-span-3 text-xs text-gray-400 mb-1">Movement (HOLD to move, like Q3!)</div>
                                    <div></div>
                                    <button @mousedown="startMovement(127, 0)" @mouseup="stopMovement" @mouseleave="stopMovement" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        forwardMove === 127 ? 'bg-blue-600/70 border-blue-500/70' : 'bg-blue-600/30 hover:bg-blue-600/50 border-blue-500/30'
                                    ]" class="text-white">
                                        ⬆️ Forward (W)
                                    </button>
                                    <div></div>

                                    <button @mousedown="startMovement(0, -127)" @mouseup="stopMovement" @mouseleave="stopMovement" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        rightMove === -127 ? 'bg-blue-600/70 border-blue-500/70' : 'bg-blue-600/30 hover:bg-blue-600/50 border-blue-500/30'
                                    ]" class="text-white">
                                        ⬅️ Left (A)
                                    </button>
                                    <button @mousedown="startMovement(-127, 0)" @mouseup="stopMovement" @mouseleave="stopMovement" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        forwardMove === -127 ? 'bg-blue-600/70 border-blue-500/70' : 'bg-blue-600/30 hover:bg-blue-600/50 border-blue-500/30'
                                    ]" class="text-white">
                                        ⬇️ Back (S)
                                    </button>
                                    <button @mousedown="startMovement(0, 127)" @mouseup="stopMovement" @mouseleave="stopMovement" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        rightMove === 127 ? 'bg-blue-600/70 border-blue-500/70' : 'bg-blue-600/30 hover:bg-blue-600/50 border-blue-500/30'
                                    ]" class="text-white">
                                        ➡️ Right (D)
                                    </button>
                                </div>

                                <!-- Action Controls -->
                                <div class="grid grid-cols-4 gap-2">
                                    <div class="col-span-4 text-xs text-gray-400 mb-1">Actions (HOLD!)</div>
                                    <button @mousedown="startCrouch" @mouseup="stopCrouch" @mouseleave="stopCrouch" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        isCrouching ? 'bg-yellow-600/50 border-yellow-500/50' : 'bg-yellow-600/30 hover:bg-yellow-600/50 border-yellow-500/30'
                                    ]" class="text-white">
                                        🦆 Crouch
                                    </button>
                                    <button @mousedown="startJump" @mouseup="stopJump" @mouseleave="stopJump" class="px-4 py-3 bg-green-600/30 hover:bg-green-600/50 text-white rounded-lg text-sm font-semibold transition-colors border border-green-500/30">
                                        ⬆️ Jump
                                    </button>
                                    <button @mousedown="startAttack" @mouseup="stopAttack" @mouseleave="stopAttack" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        isAttacking ? 'bg-red-600/70 border-red-500/70' : 'bg-red-600/30 hover:bg-red-600/50 border-red-500/30'
                                    ]" class="text-white">
                                        ⚔️ Attack
                                    </button>
                                    <button @mousedown="startGesture" @mouseup="stopGesture" @mouseleave="stopGesture" :class="[
                                        'px-4 py-3 rounded-lg text-sm font-semibold transition-colors border',
                                        isGesturing ? 'bg-purple-600/70 border-purple-500/70' : 'bg-purple-600/30 hover:bg-purple-600/50 border-purple-500/30'
                                    ]" class="text-white">
                                        👋 Gesture
                                    </button>
                                </div>

                                <div class="text-xs text-gray-400 mt-2">
                                    💡 Q3-Style: Model always animates! Press buttons to change animation, release to return to idle (LEGS_IDLE + TORSO_STAND).
                                </div>
                            </div>
                        </div>

                        <!-- Sound Controls Card -->
                        <div v-if="soundsReady" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-8">
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
                                <button
                                    v-for="soundName in availableSounds"
                                    :key="soundName"
                                    @click="playSound(soundName)"
                                    :disabled="!soundsEnabled"
                                    class="px-3 py-2 bg-purple-500/20 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                                    {{ soundName }}
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

                        <!-- RAW ANIMATION BUTTONS -->
                        <div v-if="animationsReady" class="backdrop-blur-xl bg-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-3">🔍 ALL ANIMATIONS (Raw Test)</h3>

                            <!-- LEGS ANIMATIONS -->
                            <div class="mb-4">
                                <h4 class="text-sm font-bold text-blue-400 mb-2">LEGS (Lower.md3) - {{ Object.keys(availableAnimations.legs || {}).length }} animations</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.legs"
                                        :key="animName"
                                        @mousedown="viewer3D.playLegsAnimation(animName); currentLegsAnim = animName"
                                        @mouseup="viewer3D.getAnimationManager().playing = false"
                                        @mouseleave="viewer3D.getAnimationManager().playing = false"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-xs font-mono transition-colors border text-left',
                                            currentLegsAnim === animName
                                                ? 'bg-blue-600/70 border-blue-500/70 text-white'
                                                : 'bg-blue-600/20 border-blue-500/30 text-blue-300 hover:bg-blue-600/40'
                                        ]">
                                        <div class="font-bold">{{ animName }}</div>
                                        <div class="text-xs opacity-60">f{{ animData.firstFrame }}-{{ animData.firstFrame + animData.numFrames - 1 }} ({{ animData.numFrames }})</div>
                                    </button>
                                </div>
                            </div>

                            <!-- TORSO ANIMATIONS -->
                            <div class="mb-4">
                                <h4 class="text-sm font-bold text-green-400 mb-2">TORSO (Upper.md3) - {{ Object.keys(availableAnimations.torso || {}).length }} animations</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.torso"
                                        :key="animName"
                                        @mousedown="viewer3D.playTorsoAnimation(animName); currentTorsoAnim = animName"
                                        @mouseup="viewer3D.getAnimationManager().playing = false"
                                        @mouseleave="viewer3D.getAnimationManager().playing = false"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-xs font-mono transition-colors border text-left',
                                            currentTorsoAnim === animName
                                                ? 'bg-green-600/70 border-green-500/70 text-white'
                                                : 'bg-green-600/20 border-green-500/30 text-green-300 hover:bg-green-600/40'
                                        ]">
                                        <div class="font-bold">{{ animName }}</div>
                                        <div class="text-xs opacity-60">f{{ animData.firstFrame }}-{{ animData.firstFrame + animData.numFrames - 1 }} ({{ animData.numFrames }})</div>
                                    </button>
                                </div>
                            </div>

                            <!-- BOTH ANIMATIONS (death animations - affect both legs and torso) -->
                            <div v-if="availableAnimations.both && Object.keys(availableAnimations.both || {}).length > 0" class="mb-4">
                                <h4 class="text-sm font-bold text-purple-400 mb-2">BOTH (Death animations) - {{ Object.keys(availableAnimations.both || {}).length }} animations</h4>
                                <div class="text-xs text-gray-400 mb-2">💀 BOTH animations play on BOTH legs AND torso simultaneously (Q3-style death animations)</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.both"
                                        :key="animName"
                                        @mousedown="viewer3D.playBothAnimation(animName)"
                                        @mouseup="viewer3D.getAnimationManager().playing = false"
                                        @mouseleave="viewer3D.getAnimationManager().playing = false"
                                        class="px-3 py-2 rounded-lg text-xs font-mono bg-purple-600/20 border border-purple-500/30 text-purple-300 hover:bg-purple-600/40 transition-colors text-left">
                                        <div class="font-bold">{{ animName }}</div>
                                        <div class="text-xs opacity-60">f{{ animData.firstFrame }}-{{ animData.firstFrame + animData.numFrames - 1 }} ({{ animData.numFrames }})</div>
                                    </button>
                                </div>
                            </div>

                            <div class="text-xs text-yellow-400 bg-yellow-500/10 border border-yellow-500/30 rounded p-2">
                                💡 HOLD button to play animation, RELEASE to stop. Check if animations are paired correctly!
                            </div>
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
