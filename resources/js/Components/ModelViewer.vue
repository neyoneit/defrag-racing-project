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
    },
    thumbnailMode: {
        type: Boolean,
        default: false
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
const availableAnimations = ref({ legs: {}, torso: {}, both: {} });
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
    scene.background = new THREE.Color(0x000000); // Black background
    scene.fog = new THREE.Fog(0x000000, 50, 200);

    // Create camera
    camera = new THREE.PerspectiveCamera(
        60,
        canvas.value.clientWidth / canvas.value.clientHeight,
        0.1,
        1000
    );
    // Different camera positions for thumbnail vs detail view
    if (props.thumbnailMode) {
        camera.position.set(0, 15, 45);  // Much closer for thumbnail
        camera.lookAt(0, 10, 10);  // Look at model center
    } else {
        camera.position.set(0, 40, 40);  // Original position for detail view
        camera.lookAt(0, 30, 10);
    }

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

    // Grid removed - no floor

    // Add orbit controls
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.minDistance = 20;
    controls.maxDistance = 300;
    controls.autoRotate = props.autoRotate;
    controls.autoRotateSpeed = 2.0;
    controls.target.set(0, props.thumbnailMode ? 7 : 37, 0);
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

        // Q3 MD3 models coordinate system conversion:
        // Q3: +X = forward, +Z = up, +Y = left
        // Three.js: +X = right, +Y = up, +Z = backward (towards camera)
        // Rotate to stand upright and face camera
        model.rotation.x = -Math.PI / 2;  // Stand upright
        model.rotation.z = -Math.PI / 2;  // Turn to face camera

        // Force matrix update after rotation
        model.updateMatrixWorld(true);

        // Calculate scale after rotation
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = 50 / maxDim;
        model.scale.setScalar(scale);

        // Force matrix update after scale
        model.updateMatrixWorld(true);

        // Position model differently for thumbnail vs detail view
        model.position.set(0, props.thumbnailMode ? 0 : 30, 0);

        scene.add(model);

        // Initialize animation manager if animations are available
        if (props.loadFullPlayer && model.userData.animations) {
            animationManager = new MD3AnimationManager(model);
            availableAnimations.value = animationManager.getAvailableAnimations();

            // Initialize sound manager
            if (soundsEnabled.value) {
                try {
                    // Extract model name from path
                    const pathParts = props.modelPath.split('/');
                    const modelName = pathParts[pathParts.length - 2]; // Get second-to-last part (model name)
                    let soundPath;

                    // Check if this is a base Q3 model or user-uploaded model
                    if (props.modelPath.startsWith('/baseq3/')) {
                        // Base Q3 model: /baseq3/models/players/anarki/head.md3
                        // Sound path: /baseq3/sound/player/anarki/
                        soundPath = `/baseq3/sound/player/${modelName}`;
                    } else {
                        // User-uploaded model: /storage/models/extracted/worm-123/models/players/worm/head.md3
                        // Sound path: /storage/models/extracted/worm-123/sound/player/worm/
                        const extractedPath = props.modelPath.match(/^(\/storage\/models\/extracted\/[^\/]+)\//);
                        if (extractedPath) {
                            const basePath = extractedPath[1];
                            soundPath = `${basePath}/sound/player/${modelName}`;
                        }
                    }

                    if (soundPath) {
                        soundManager = new MD3SoundManager(soundPath, modelName);

                        // Initialize audio context on first user interaction
                        await soundManager.initialize();

                        // Load sounds
                        const loaded = await soundManager.loadSounds();
                        soundsLoaded.value = loaded;

                        if (loaded) {
                            emit('soundsReady', soundManager.getLoadedSounds());

                            // Set up sound callback for animations
                            animationManager.setSoundCallback((animationName) => {
                                soundManager.playAnimationSound(animationName);
                            });
                        }
                    }
                } catch (err) {
                    soundsEnabled.value = false;
                }
            }

            // Start idle animations automatically (LEGS_IDLE + TORSO_STAND)
            // This matches the default behavior on the model detail page
            animationManager.playLegsAnimation('LEGS_IDLE');
            animationManager.playTorsoAnimation('TORSO_STAND');
            animationManager.playing = true;

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
    const time = clock.getElapsedTime();

    // Update animation manager
    if (animationManager) {
        animationManager.update(deltaTime);
    }

    // Update shader animations (tcMod)
    updateShaderAnimations(time);

    if (controls) {
        controls.update();
    }

    if (renderer && scene && camera) {
        renderer.render(scene, camera);
    }
}

function updateShaderAnimations(time) {
    if (!model || !loader) return;

    // Use the shader material system's update method
    const shaderSystem = loader.shaderMaterialSystem;
    if (!shaderSystem) return;

    model.traverse((child) => {
        if (child.isMesh && child.material) {
            const materials = Array.isArray(child.material) ? child.material : [child.material];
            materials.forEach(material => {
                shaderSystem.updateMaterialAnimation(material, time);
            });
        }
    });
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

// Watch for skin changes (reload model with new skin)
watch(() => props.skinName, () => {
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

// Method to toggle wireframe mode
function setWireframe(enabled) {
    if (!model) {
        return;
    }

    // Only affect the model, not other scene objects like grid
    model.traverse((child) => {
        if (child.isMesh && child.material) {
            // Skip materials that shouldn't be affected (like grid helpers)
            if (child.isGridHelper || child.type === 'GridHelper') {
                return;
            }

            if (Array.isArray(child.material)) {
                child.material.forEach(mat => {
                    mat.wireframe = enabled;
                    mat.needsUpdate = true;
                });
            } else {
                child.material.wireframe = enabled;
                child.material.needsUpdate = true;
            }
        }
    });
}

// Method to set auto-rotate
function setAutoRotate(enabled) {
    if (controls) {
        controls.autoRotate = enabled;
    }
}

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
    playBothAnimation: (name) => {
        // Q3-style: BOTH animations play on BOTH legs and torso simultaneously
        animationManager?.playLegsAnimation(name);
        animationManager?.playTorsoAnimation(name);
    },
    stopAnimations: () => animationManager?.stop(),
    resetToIdle: () => animationManager?.resetToIdle(),
    getAvailableAnimations: () => availableAnimations.value,
    playSound: (name, options) => soundManager?.playSound(name, options),
    stopSound: (name) => soundManager?.stopSound(name),
    stopAllSounds: () => soundManager?.stopAllSounds(),
    setSoundVolume: (volume) => soundManager?.setVolume(volume),
    setSoundsEnabled: (enabled) => {
        soundsEnabled.value = enabled;
        soundManager?.setEnabled(enabled);
    },
    areSoundsLoaded: () => soundsLoaded.value,
    setWireframe: setWireframe,
    setAutoRotate: setAutoRotate
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
        <div v-if="showGrid" class="absolute bottom-4 left-4 bg-black/60 backdrop-blur-sm rounded-lg px-4 py-2 text-xs text-gray-300">
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
    overflow: hidden;
    border-radius: 1rem;
}

.model-canvas {
    width: 100%;
    height: 100%;
    display: block;
}
</style>
