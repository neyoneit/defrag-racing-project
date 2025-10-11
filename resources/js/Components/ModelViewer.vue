<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { MD3Loader } from '@/utils/MD3Loader.js';
import { MD3AnimationManager } from '@/utils/MD3AnimationManager.js';
import { MD3SoundManager } from '@/utils/MD3SoundManager.js';

const props = defineProps({
    modelPath: {
        type: String,
        required: true
    },
    skinPath: {
        type: String,
        default: null
    },
    loadFullPlayer: {
        type: Boolean,
        default: true
    },
    skinName: {
        type: String,
        default: 'default'
    },
    autoRotate: {
        type: Boolean,
        default: false
    },
    showGrid: {
        type: Boolean,
        default: true
    },
    enableSounds: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['loaded', 'error', 'animationsReady', 'soundsReady']);

const canvas = ref(null);
let scene, camera, renderer, controls, animationFrameId;
let model = null;
let animationManager = null;
let soundManager = null;
let clock = new THREE.Clock();
const loader = new MD3Loader();

const loading = ref(true);
const error = ref(null);
const availableAnimations = ref({ legs: [], torso: [] });
const soundsEnabled = ref(props.enableSounds);
const soundsLoaded = ref(false);

onMounted(() => {
    initScene();
    loadModel();
    animate();

    // Handle window resize
    window.addEventListener('resize', onWindowResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', onWindowResize);

    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }

    // Clean up sound manager
    if (soundManager) {
        soundManager.dispose();
    }

    // Clean up Three.js resources
    if (renderer) {
        renderer.dispose();
    }

    if (controls) {
        controls.dispose();
    }

    // Dispose geometries and materials
    if (model) {
        model.traverse((child) => {
            if (child.geometry) {
                child.geometry.dispose();
            }
            if (child.material) {
                if (Array.isArray(child.material)) {
                    child.material.forEach(material => material.dispose());
                } else {
                    child.material.dispose();
                }
            }
        });
    }
});

function initScene() {
    // Create scene
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a1a1a);
    scene.fog = new THREE.Fog(0x1a1a1a, 50, 200);

    // Create camera
    camera = new THREE.PerspectiveCamera(
        60,
        canvas.value.clientWidth / canvas.value.clientHeight,
        0.1,
        1000
    );
    camera.position.set(0, 50, 100);
    camera.lookAt(0, 30, 0);

    // Create renderer
    renderer = new THREE.WebGLRenderer({
        canvas: canvas.value,
        antialias: true,
        alpha: true
    });
    renderer.setSize(canvas.value.clientWidth, canvas.value.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    // Add lights
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(50, 100, 50);
    directionalLight.castShadow = true;
    directionalLight.shadow.mapSize.width = 2048;
    directionalLight.shadow.mapSize.height = 2048;
    scene.add(directionalLight);

    const backLight = new THREE.DirectionalLight(0x4488ff, 0.3);
    backLight.position.set(-50, 50, -50);
    scene.add(backLight);

    // Add grid helper
    if (props.showGrid) {
        const gridHelper = new THREE.GridHelper(200, 20, 0x444444, 0x222222);
        scene.add(gridHelper);
    }

    // Add orbit controls
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.minDistance = 20;
    controls.maxDistance = 300;
    controls.autoRotate = props.autoRotate;
    controls.autoRotateSpeed = 2.0;
    controls.target.set(0, 30, 0);
}

async function loadModel() {
    try {
        loading.value = true;
        error.value = null;

        if (props.loadFullPlayer) {
            // Extract base directory from modelPath
            // modelPath is like: /storage/models/extracted/worm-123/models/players/worm/head.md3
            // We want: /storage/models/extracted/worm-123/models/players/worm
            const baseDir = props.modelPath.substring(0, props.modelPath.lastIndexOf('/'));
            const modelName = baseDir.split('/').pop(); // Get "worm" from path

            // Load complete player model (head + upper + lower)
            model = await loader.loadPlayerModel(baseDir, modelName, props.skinName);
        } else {
            // Load single MD3 file with optional skin file
            model = await loader.load(props.modelPath, props.skinPath);
        }

        // Center the model
        const box = new THREE.Box3().setFromObject(model);
        const center = box.getCenter(new THREE.Vector3());
        model.position.x = -center.x;
        model.position.y = -center.y;
        model.position.z = -center.z;

        // Scale the model to fit the view
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = 50 / maxDim;
        model.scale.setScalar(scale);

        scene.add(model);

        // Initialize animation manager if animations are available
        if (props.loadFullPlayer && model.userData.animations) {
            animationManager = new MD3AnimationManager(model);
            availableAnimations.value = animationManager.getAvailableAnimations();

            // Initialize sound manager
            if (soundsEnabled.value) {
                try {
                    // Extract sound path from model path
                    // modelPath: /storage/models/extracted/worm-123/models/players/worm/head.md3
                    // soundPath: /storage/models/extracted/worm-123/sound/player/worm
                    const extractedPath = props.modelPath.match(/^(\/storage\/models\/extracted\/[^\/]+)\//);
                    if (extractedPath) {
                        const basePath = extractedPath[1];
                        const modelName = baseDir.split('/').pop();
                        const soundPath = `${basePath}/sound/player/${modelName}`;

                        soundManager = new MD3SoundManager(soundPath, modelName);

                        // Initialize audio context on first user interaction
                        await soundManager.initialize();

                        // Load sounds
                        const loaded = await soundManager.loadSounds();
                        soundsLoaded.value = loaded;

                        if (loaded) {
                            console.log('Sounds loaded successfully');
                            emit('soundsReady', soundManager.getLoadedSounds());

                            // Set up sound callback for animations
                            animationManager.setSoundCallback((animationName) => {
                                soundManager.playAnimationSound(animationName);
                            });
                        }
                    }
                } catch (err) {
                    console.warn('Failed to initialize sounds:', err);
                    soundsEnabled.value = false;
                }
            }

            // Start with idle animations by default
            if (availableAnimations.value.legs.includes('LEGS_IDLE')) {
                animationManager.playLegsAnimation('LEGS_IDLE');
            }
            if (availableAnimations.value.torso.includes('TORSO_STAND')) {
                animationManager.playTorsoAnimation('TORSO_STAND');
            }

            emit('animationsReady', availableAnimations.value);
        }

        loading.value = false;
        emit('loaded', model);
    } catch (err) {
        console.error('Failed to load model:', err);
        error.value = err.message || 'Failed to load model';
        loading.value = false;
        emit('error', err);
    }
}

function animate() {
    animationFrameId = requestAnimationFrame(animate);

    const deltaTime = clock.getDelta();

    // Update animation manager
    if (animationManager) {
        animationManager.update(deltaTime);
    }

    if (controls) {
        controls.update();
    }

    if (renderer && scene && camera) {
        renderer.render(scene, camera);
    }
}

function onWindowResize() {
    if (!canvas.value || !camera || !renderer) return;

    const width = canvas.value.clientWidth;
    const height = canvas.value.clientHeight;

    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
}

// Watch for model path changes
watch(() => props.modelPath, () => {
    if (model) {
        scene.remove(model);
        model.traverse((child) => {
            if (child.geometry) child.geometry.dispose();
            if (child.material) {
                if (Array.isArray(child.material)) {
                    child.material.forEach(m => m.dispose());
                } else {
                    child.material.dispose();
                }
            }
        });
    }
    loadModel();
});

// Expose methods for parent component
defineExpose({
    getModel: () => model,
    getScene: () => scene,
    getCamera: () => camera,
    getRenderer: () => renderer,
    getAnimationManager: () => animationManager,
    getSoundManager: () => soundManager,
    playLegsAnimation: (name) => animationManager?.playLegsAnimation(name),
    playTorsoAnimation: (name) => animationManager?.playTorsoAnimation(name),
    stopAnimations: () => animationManager?.stop(),
    getAvailableAnimations: () => availableAnimations.value,
    playSound: (name, options) => soundManager?.playSound(name, options),
    stopSound: (name) => soundManager?.stopSound(name),
    stopAllSounds: () => soundManager?.stopAllSounds(),
    setSoundVolume: (volume) => soundManager?.setVolume(volume),
    setSoundsEnabled: (enabled) => {
        soundsEnabled.value = enabled;
        soundManager?.setEnabled(enabled);
    },
    areSoundsLoaded: () => soundsLoaded.value
});
</script>

<template>
    <div class="model-viewer-container">
        <canvas ref="canvas" class="model-canvas"></canvas>

        <!-- Loading overlay -->
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent"></div>
                <p class="mt-4 text-white font-semibold">Loading 3D Model...</p>
            </div>
        </div>

        <!-- Error overlay -->
        <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="text-center max-w-md p-6">
                <div class="text-red-400 text-5xl mb-4">⚠️</div>
                <p class="text-white font-bold mb-2">Failed to load model</p>
                <p class="text-gray-400 text-sm">{{ error }}</p>
            </div>
        </div>

        <!-- Controls hint -->
        <div class="absolute bottom-4 left-4 bg-black/60 backdrop-blur-sm rounded-lg px-4 py-2 text-xs text-gray-300">
            <div class="flex items-center gap-2">
                <span>🖱️</span>
                <span>Left click: Rotate | Right click: Pan | Scroll: Zoom</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.model-viewer-container {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 400px;
    overflow: hidden;
    border-radius: 1rem;
}

.model-canvas {
    width: 100%;
    height: 100%;
    display: block;
}
</style>
