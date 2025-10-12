<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ModelViewer from '@/Components/ModelViewer.vue';

const props = defineProps({
    model: Object,
});

// Check if we're in thumbnail generation mode
const isThumbnailMode = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('thumbnail') === '1';
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

// Skin selection
const availableSkins = computed(() => {
    // Ensure available_skins is always an array
    const skins = props.model.available_skins;

    // If it's a string (JSON), parse it
    if (typeof skins === 'string') {
        try {
            return JSON.parse(skins);
        } catch (e) {
            return ['default'];
        }
    }

    // If it's already an array, use it
    if (Array.isArray(skins)) {
        return skins;
    }

    // Fallback
    return ['default'];
});
const currentSkin = ref('default');

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
    // The file_path is like: models/extracted/model-1760208803
    // The model name is auto-detected and stored in props.model.name
    // And the actual MD3 is at: models/extracted/model-1760208803/models/players/{name}/head.md3
    const modelName = props.model.name;

    return `/storage/${props.model.file_path}/models/players/${modelName}/head.md3`;
});

// Get the path to the skin file
const skinFilePath = computed(() => {
    if (!props.model.file_path) return null;

    // Check if this is a base Q3 model
    if (props.model.file_path.startsWith('models/basequake3/players/')) {
        return `/${props.model.file_path}/head_${currentSkin.value}.skin`;
    }

    // User-uploaded models - use detected model name and selected skin
    const modelName = props.model.name;

    return `/storage/${props.model.file_path}/models/players/${modelName}/head_${currentSkin.value}.skin`;
});

const onViewerLoaded = (model) => {
    viewerLoaded.value = true;

    // Expose camera and scene to window for Puppeteer thumbnail generation
    if (isThumbnailMode.value && viewer3D.value) {
        window.camera = viewer3D.value.getCamera();
        window.scene = viewer3D.value.getScene();
    }
};

const onViewerError = (error) => {
    viewerError.value = true;
    console.error('3D Model error:', error);
};

const onAnimationsReady = (animations) => {
    availableAnimations.value = animations;
    animationsReady.value = true;

    // START IDLE ANIMATIONS IMMEDIATELY (Q3 default state)
    // This makes the model animate on page load with LEGS_IDLE + TORSO_STAND
    updateAnimations();
};

const onSoundsReady = (sounds) => {
    soundsReady.value = true;
    availableSounds.value = sounds;
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


// Define one-shot animations (play once, then return to idle)
const oneShotAnimations = {
    legs: ['LEGS_JUMP', 'LEGS_JUMPB', 'LEGS_LAND', 'LEGS_LANDB'],
    torso: ['TORSO_GESTURE', 'TORSO_DROP', 'TORSO_RAISE'],
    both: ['BOTH_DEATH1', 'BOTH_DEATH2', 'BOTH_DEATH3', 'BOTH_DEAD1', 'BOTH_DEAD2', 'BOTH_DEAD3']
};

// Generic function to play a one-shot animation
const playOneShotAnimation = (animName, animType) => {
    if (!viewer3D.value) return;
    const animMgr = viewer3D.value.getAnimationManager();
    if (!animMgr) return;

    // Get the animation data
    let anim = null;
    if (animType === 'legs') {
        anim = availableAnimations.value.legs[animName];
        if (anim) {
            // Enable no-loop mode for legs
            animMgr.noLoopLegs = true;
            viewer3D.value.playLegsAnimation(animName);
            currentLegsAnim.value = animName;
        }
    } else if (animType === 'torso') {
        anim = availableAnimations.value.torso[animName];
        if (anim) {
            // Enable no-loop mode for torso
            animMgr.noLoopTorso = true;
            viewer3D.value.playTorsoAnimation(animName);
            currentTorsoAnim.value = animName;
        }
    } else if (animType === 'both') {
        anim = availableAnimations.value.both[animName];
        if (anim) {
            // Enable no-loop mode for both legs and torso
            animMgr.noLoopLegs = true;
            animMgr.noLoopTorso = true;
            viewer3D.value.playBothAnimation(animName);
            currentLegsAnim.value = animName;
            currentTorsoAnim.value = animName;
        }
    }

    if (!anim) return;

    // Start playing
    animMgr.playing = true;

    // Trigger sound
    triggerAnimationSound(animName, animType);

    // Calculate animation duration (account for animation speed)
    const duration = (anim.numFrames / anim.fps / animationSpeed.value) * 1000; // Convert to milliseconds

    // Animations that need to hold on last frame for 2 seconds
    const needsHoldDuration = animName.includes('DEATH') || animName.includes('DEAD');
    const holdDuration = needsHoldDuration ? 2000 : 0; // 2 seconds hold time

    // After animation completes (+ hold duration for dead anims), return to idle
    setTimeout(() => {
        // Disable no-loop mode
        animMgr.noLoopLegs = false;
        animMgr.noLoopTorso = false;

        // Reset last animation tracker so sound can play again on repeat clicks
        if (animType === 'legs') {
            lastLegsAnim.value = null;
        } else if (animType === 'torso') {
            lastTorsoAnim.value = null;
        } else if (animType === 'both') {
            lastLegsAnim.value = null;
            lastTorsoAnim.value = null;
        }

        // Return to idle animations
        updateAnimations(); // Returns to LEGS_IDLE / TORSO_STAND
    }, duration + holdDuration);
};


const playSound = (soundName) => {
    if (viewer3D.value && soundsEnabled.value) {
        viewer3D.value.playSound(soundName);
    }
};

// Q3-STYLE ANIMATION-TO-SOUND MAPPING
// Based on ioq3 cg_event.c event handling
const animationSoundMap = {
    // Jump sounds (EV_JUMP)
    'LEGS_JUMP': 'jump1',
    'LEGS_JUMPB': 'jump1',

    // Death sounds (EV_DEATH1/2/3)
    'BOTH_DEATH1': 'death1',
    'BOTH_DEATH2': 'death2',
    'BOTH_DEATH3': 'death3',

    // Taunt/Gesture sound (EV_TAUNT)
    'TORSO_GESTURE': 'taunt',

    // Note: Footsteps, pain, and land sounds are handled differently in Q3
    // They're triggered by game events (bobCycle, damage, velocity), not animations
    // For now, we only map direct animation-to-sound relationships
};

// Track last played animation to avoid repeating sounds
const lastLegsAnim = ref(null);
const lastTorsoAnim = ref(null);

// Play sound when animation changes (Q3-style event triggering)
const triggerAnimationSound = (animName, animType) => {
    if (!soundsEnabled.value) return;

    // Check if this is a new animation (not repeating)
    if (animType === 'legs' && lastLegsAnim.value === animName) return;
    if (animType === 'torso' && lastTorsoAnim.value === animName) return;

    // Update last animation tracker
    if (animType === 'legs') lastLegsAnim.value = animName;
    if (animType === 'torso') lastTorsoAnim.value = animName;

    // Look up sound for this animation
    const soundName = animationSoundMap[animName];
    if (soundName) {
        playSound(soundName);
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

const downloadModel = () => {
    window.location.href = route('models.download', props.model.id);
};

// Change skin
const changeSkin = (skinName) => {
    currentSkin.value = skinName;
    // The skinFilePath computed property will update automatically
    // ModelViewer watches skinPath and will reload textures
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
    <div :class="isThumbnailMode ? 'w-screen h-screen' : 'min-h-screen py-12'" :style="isThumbnailMode ? 'width: 100vw; height: 100vh; margin: 0; padding: 0;' : ''">
            <div :class="isThumbnailMode ? 'w-full h-full' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                <!-- Back Button -->
                <Link v-if="!isThumbnailMode" :href="route('models.index')" class="inline-flex items-center gap-2 text-gray-400 hover:text-white mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Models
                </Link>

                <div :class="isThumbnailMode ? 'w-full h-full' : 'grid grid-cols-1 lg:grid-cols-2 gap-8'" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                    <!-- Left Column: 3D Viewer / Preview -->
                    <div :class="isThumbnailMode ? 'w-full h-full' : ''" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                        <!-- 3D Viewer Card -->
                        <div :class="[isThumbnailMode ? 'w-full h-full' : 'backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-8 mb-8']" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                            <!-- Viewer Controls -->
                            <div v-if="viewerLoaded && !isThumbnailMode" class="flex gap-2 mb-4">
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

                            <!-- Skin Selector -->
                            <div v-if="viewerLoaded && availableSkins.length > 1 && !isThumbnailMode" class="flex gap-2 mb-4">
                                <span class="text-xs text-gray-400 self-center">Skin:</span>
                                <button
                                    v-for="skin in availableSkins"
                                    :key="skin"
                                    @click="changeSkin(skin)"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-semibold transition-all capitalize',
                                        currentSkin === skin
                                            ? 'bg-orange-500/30 text-orange-300 border border-orange-500/50'
                                            : 'bg-gray-500/20 text-gray-400 border border-gray-500/30 hover:bg-gray-500/30'
                                    ]">
                                    {{ skin }}
                                </button>
                            </div>

                            <ModelViewer
                                v-if="modelFilePath"
                                ref="viewer3D"
                                :model-path="modelFilePath"
                                :skin-path="skinFilePath"
                                :skin-name="currentSkin"
                                :auto-rotate="autoRotate"
                                :show-grid="!isThumbnailMode"
                                :enable-sounds="true"
                                @loaded="onViewerLoaded"
                                @error="onViewerError"
                                @animations-ready="onAnimationsReady"
                                @sounds-ready="onSoundsReady"
                                :class="isThumbnailMode ? '' : 'rounded-xl'"
                                :style="isThumbnailMode ? 'width: 100%; height: 100%;' : 'height: 800px;'"
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
                        <div v-if="animationsReady && !isThumbnailMode" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-6 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-bold text-gray-300">Controls</h4>

                                <!-- Current Animation State -->
                                <div class="flex gap-2">
                                    <span v-if="currentLegsAnim" class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs font-semibold border border-blue-500/30">
                                        {{ currentLegsAnim.replace('LEGS_', '') }}
                                    </span>
                                    <span v-if="currentTorsoAnim" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-semibold border border-green-500/30">
                                        {{ currentTorsoAnim.replace('TORSO_', '') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Animation Speed -->
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-400 w-16">Speed:</span>
                                <input
                                    type="range"
                                    min="0.1"
                                    max="3.0"
                                    step="0.1"
                                    :value="animationSpeed"
                                    @input="updateAnimationSpeed"
                                    class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                />
                                <span class="text-xs text-gray-400 w-12 text-right">{{ animationSpeed.toFixed(1) }}x</span>
                            </div>
                        </div>

                        <!-- Sound Controls Card -->
                        <div v-if="soundsReady && !isThumbnailMode" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-6 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-bold text-gray-300">Sounds</h4>
                                <button @click="toggleSounds" :class="[
                                    'px-3 py-1 rounded text-xs font-semibold transition-all',
                                    soundsEnabled
                                        ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                        : 'bg-red-500/20 text-red-400 border border-red-500/30'
                                ]">
                                    {{ soundsEnabled ? '🔊' : '🔇' }}
                                </button>
                            </div>

                            <!-- Volume Slider -->
                            <div class="mb-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-400 w-16">Volume:</span>
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
                                    <span class="text-xs text-gray-400 w-12 text-right">{{ Math.round(soundVolume * 100) }}%</span>
                                </div>
                            </div>

                            <!-- Sound Test Buttons -->
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="soundName in availableSounds"
                                    :key="soundName"
                                    @click="playSound(soundName)"
                                    :disabled="!soundsEnabled"
                                    class="px-2 py-1.5 bg-purple-500/20 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded text-xs font-semibold transition-colors">
                                    {{ soundName }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Model Info -->
                    <div v-if="!isThumbnailMode">
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

                        <!-- ANIMATION BUTTONS -->
                        <div v-if="animationsReady" class="backdrop-blur-xl bg-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-4">Animations</h3>

                            <!-- LEGS ANIMATIONS -->
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-blue-400 mb-2 uppercase">Legs ({{ Object.keys(availableAnimations.legs || {}).length }})</h4>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.legs"
                                        :key="animName"
                                        @click="oneShotAnimations.legs.includes(animName) ? playOneShotAnimation(animName, 'legs') : (viewer3D.playLegsAnimation(animName), currentLegsAnim = animName, viewer3D.getAnimationManager().playing = true, triggerAnimationSound(animName, 'legs'))"
                                        :class="[
                                            'px-2 py-1.5 rounded text-xs font-mono transition-colors border text-left',
                                            currentLegsAnim === animName
                                                ? 'bg-blue-600/70 border-blue-500/70 text-white'
                                                : 'bg-blue-600/20 border-blue-500/30 text-blue-300 hover:bg-blue-600/40'
                                        ]">
                                        <div class="font-bold text-xs">{{ animName.replace('LEGS_', '') }}</div>
                                        <div class="text-[10px] opacity-60">{{ animData.numFrames }}f</div>
                                    </button>
                                </div>
                            </div>

                            <!-- TORSO ANIMATIONS -->
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-green-400 mb-2 uppercase">Torso ({{ Object.keys(availableAnimations.torso || {}).length }})</h4>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.torso"
                                        :key="animName"
                                        @click="oneShotAnimations.torso.includes(animName) ? playOneShotAnimation(animName, 'torso') : (viewer3D.playTorsoAnimation(animName), currentTorsoAnim = animName, viewer3D.getAnimationManager().playing = true, triggerAnimationSound(animName, 'torso'))"
                                        :class="[
                                            'px-2 py-1.5 rounded text-xs font-mono transition-colors border text-left',
                                            currentTorsoAnim === animName
                                                ? 'bg-green-600/70 border-green-500/70 text-white'
                                                : 'bg-green-600/20 border-green-500/30 text-green-300 hover:bg-green-600/40'
                                        ]">
                                        <div class="font-bold text-xs">{{ animName.replace('TORSO_', '') }}</div>
                                        <div class="text-[10px] opacity-60">{{ animData.numFrames }}f</div>
                                    </button>
                                </div>
                            </div>

                            <!-- BOTH ANIMATIONS -->
                            <div v-if="availableAnimations.both && Object.keys(availableAnimations.both || {}).length > 0">
                                <h4 class="text-xs font-bold text-purple-400 mb-2 uppercase">Both ({{ Object.keys(availableAnimations.both || {}).length }})</h4>
                                <div class="grid grid-cols-3 gap-1.5 mb-3">
                                    <button
                                        v-for="(animData, animName) in availableAnimations.both"
                                        :key="animName"
                                        @click="playOneShotAnimation(animName, 'both')"
                                        class="px-2 py-1.5 rounded text-xs font-mono bg-purple-600/20 border border-purple-500/30 text-purple-300 hover:bg-purple-600/40 transition-colors text-left">
                                        <div class="font-bold text-xs">{{ animName.replace('BOTH_', '') }}</div>
                                        <div class="text-[10px] opacity-60">{{ animData.numFrames }}f</div>
                                    </button>
                                </div>
                                <div class="text-xs text-gray-400 italic">
                                    One-shot animations hold 2s, then return to idle
                                </div>
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
