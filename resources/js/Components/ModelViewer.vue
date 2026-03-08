<script setup>
// Weapon model viewer with uniform positioning (Y=20)
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
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
    modelId: {
        type: Number,
        default: null
    },
    skinPath: {
        type: String,
        default: null
    },
    skinPackBasePath: {
        type: String,
        default: null
    },
    loadFullPlayer: {
        type: Boolean,
        default: true
    },
    isWeapon: {
        type: Boolean,
        default: false
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
    backgroundColor: {
        type: String,
        default: 'black'
    },
    thumbnailMode: {
        type: Boolean,
        default: false
    },
    baseModelName: {
        type: String,
        default: null
    },
    baseModelFilePath: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['loaded', 'error', 'animationsReady', 'soundsReady']);

const canvas = ref(null);
let scene, camera, renderer, controls, animationFrameId;
let model = null;
let modelCenter = new THREE.Vector3(); // Geometric center for rotation pivot
let manualAutoRotate = false;
let animationManager = null;
let animationPaused = false;
let soundManager = null;
let clock = new THREE.Clock();
const loader = new MD3Loader();

// Light references
let ambientLight = null;
let directionalLight = null;
let backLight = null;

const loading = ref(true);
const error = ref(null);
const availableAnimations = ref({ legs: {}, torso: {}, both: {} });
const soundsEnabled = ref(props.enableSounds);
const soundsLoaded = ref(false);
const currentVolume = ref(0.5); // Store current volume for unmuting

// Check if weapon has hit sounds (all weapons except shotgun)
const hasHitSounds = computed(() => {
    if (!model || !model.userData || !model.userData.animation) return false;
    const weaponName = model.userData.animation.weaponName || '';
    const lowerName = weaponName.toLowerCase();
    // Shotgun is the only weapon without hit sounds
    return !(lowerName.includes('shotgun') || lowerName.includes('sg'));
});

// Weapon view animation state (from Q3 CG_CalculateWeaponPosition)
const weaponViewState = {
    bobCycle: 0,
    bobFracSin: 0,
    xySpeed: 0,
    landTime: 0,
    landChange: 0,
    kickTime: 0,
    kickAngles: new THREE.Vector3(0, 0, 0),
    basePosition: new THREE.Vector3(0, 0, 0),
    baseRotation: new THREE.Euler(0, 0, 0),
    simulatedVelocity: new THREE.Vector2(0, 0),
    targetVelocity: new THREE.Vector2(0, 0),
    isMoving: false
};

onMounted(() => {
    initScene();
    loadModel();
    animate();

    // Handle window resize
    window.addEventListener('resize', onWindowResize);

    // Add keyboard event listeners for weapon firing (Ctrl key)
    if (props.isWeapon) {
        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('keyup', handleKeyUp);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', onWindowResize);

    // Remove keyboard event listeners
    if (props.isWeapon) {
        window.removeEventListener('keydown', handleKeyDown);
        window.removeEventListener('keyup', handleKeyUp);
    }

    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }

    // Abort all pending fetch requests in the loader
    if (loader) {
        loader.abort();
    }

    // Clean up sound manager
    if (soundManager) {
        soundManager.dispose();
    }

    // Stop all weapon sounds
    if (model && model.userData && model.userData.animation && model.userData.animation.sounds) {
        model.userData.animation.sounds.forEach(sound => {
            if (sound && sound.isPlaying) {
                sound.stop();
            }
            // Disconnect audio nodes
            if (sound.source) {
                sound.source.disconnect();
            }
        });
        model.userData.animation.sounds = [];
    }

    // Dispose geometries, materials, and textures from scene
    if (scene) {
        scene.traverse((child) => {
            if (child.geometry) child.geometry.dispose();
            if (child.material) {
                const materials = Array.isArray(child.material) ? child.material : [child.material];
                materials.forEach(material => {
                    ['map', 'normalMap', 'roughnessMap', 'metalnessMap', 'aoMap', 'emissiveMap', 'alphaMap', 'envMap', 'lightMap', 'bumpMap', 'displacementMap', 'specularMap'].forEach(prop => {
                        if (material[prop]) material[prop].dispose();
                    });
                    material.dispose();
                });
            }
        });
        scene.clear();
    }

    // Dispose renderer and controls
    if (controls) controls.dispose();
    if (renderer) renderer.dispose();

    // Null all references so GC can reclaim WebGL context
    renderer = null;
    scene = null;
    camera = null;
    model = null;
    controls = null;
    animationManager = null;
    soundManager = null;
    ambientLight = null;
    directionalLight = null;
    backLight = null;
});

function initScene() {
    // Create scene
    scene = new THREE.Scene();
    scene.background = new THREE.Color(props.backgroundColor === 'white' ? 0xffffff : 0x000000);
    // Fog disabled - was causing models and projectiles to darken when zooming out
    // scene.fog = new THREE.Fog(0x000000, 50, 200);

    // Create camera
    camera = new THREE.PerspectiveCamera(
        60,
        canvas.value.clientWidth / canvas.value.clientHeight,
        0.1,
        1000
    );
    // Different camera positions based on model type
    if (props.isWeapon) {
        // Weapon models: closer view, angled from above-right
        if (props.thumbnailMode) {
            camera.position.set(15, 20, 35);  // Closer for thumbnail
            camera.lookAt(0, 10, 0);
        } else {
            camera.position.set(-43.7, 38.2, -15.0);  // Detail view for weapons
            camera.lookAt(2.2, 21.7, 12.1);  // Look at weapon
        }
    } else if (props.thumbnailMode) {
        // Player model thumbnail
        camera.position.set(0, 15, 45);
        camera.lookAt(0, 10, 10);
    } else {
        // Player model detail view
        camera.position.set(0, 40, 40);
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
    // Q3 overbright bits: lighting result is multiplied by 2, giving punchier colors
    renderer.toneMapping = THREE.LinearToneMapping;
    renderer.toneMappingExposure = 1.6;

    // Add lights
    ambientLight = new THREE.AmbientLight(0xffffff, 0.9);
    scene.add(ambientLight);

    directionalLight = new THREE.DirectionalLight(0xffffff, 0.65);
    directionalLight.position.set(50, 100, 50);
    directionalLight.castShadow = true;
    directionalLight.shadow.mapSize.width = 2048;
    directionalLight.shadow.mapSize.height = 2048;
    scene.add(directionalLight);

    backLight = new THREE.DirectionalLight(0x4488ff, 0.20);
    backLight.position.set(-50, 50, -50);
    scene.add(backLight);

    // Grid removed - no floor

    // Add orbit controls
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.minDistance = 1; // Allow zooming very close (was 20)
    controls.maxDistance = 300;
    controls.autoRotate = false; // We handle rotation manually around modelCenter
    manualAutoRotate = props.autoRotate;

    // Set control target based on model type
    if (props.isWeapon) {
        controls.target.set(props.thumbnailMode ? 0 : 2.2, props.thumbnailMode ? 10 : 21.7, props.thumbnailMode ? 0 : 12.1);
    } else {
        controls.target.set(0, props.thumbnailMode ? 7 : 37, 0);
    }
}

async function loadModel() {
    try {
        loading.value = true;
        error.value = null;

        // Reset weapon view state for fresh load
        weaponViewState.basePosition.set(0, 0, 0);
        weaponViewState.baseRotation.set(0, 0, 0);
        manualAutoRotate = false;
        modelCenter.set(0, 0, 0);

        // Stop and clean up any existing weapon sounds before loading new model
        if (model && model.userData && model.userData.animation && model.userData.animation.sounds) {
            model.userData.animation.sounds.forEach(sound => {
                if (sound && sound.isPlaying) {
                    sound.stop();
                }
                if (sound.source) {
                    sound.source.disconnect();
                }
            });
            model.userData.animation.sounds = [];
        }

        if (props.loadFullPlayer) {
            // Extract base directory from modelPath
            // modelPath is like: /storage/models/extracted/worm-123/models/players/worm/head.md3
            // We want: /storage/models/extracted/worm-123/models/players/worm
            const baseDir = props.modelPath.substring(0, props.modelPath.lastIndexOf('/'));
            const modelName = baseDir.split('/').pop(); // Get "worm" from path

            // Load complete player model (head + upper + lower)
            // Pass skinPackBasePath for skin/mixed packs that override base model skins
            // Pass baseModelName so loader knows what base model to load first
            // Pass modelId so loader can fetch ALL shader files from the pk3
            model = await loader.loadPlayerModel(baseDir, modelName, props.skinName, props.skinPackBasePath, props.baseModelName, props.modelId, props.baseModelFilePath);
        } else if (props.isWeapon) {
            // Load composite weapon model (main + hand + barrel + flash)
            // For weapons, load shaders from models.shader before loading the model
            if (props.modelPath.includes('/baseq3/')) {
                // Load base Q3 shaders (models.shader contains weapon shaders)
                await loader.loadShadersForModel('/baseq3/scripts/', 'models');
            }

            // Extract base directory and weapon name
            // modelPath is like: /baseq3/models/weapons2/machinegun/machinegun.md3
            // We want: /baseq3/models/weapons2/machinegun/ and weaponName: machinegun
            const baseDir = props.modelPath.substring(0, props.modelPath.lastIndexOf('/') + 1);
            const fileName = props.modelPath.split('/').pop(); // Get "machinegun.md3"
            const weaponName = fileName.replace('.md3', ''); // Get "machinegun"

            model = await loader.loadWeaponModel(baseDir, weaponName, props.modelId);

            // Set scene and camera reference for projectile spawning
            if (model.userData && model.userData.animation) {
                model.userData.animation.scene = scene;
                model.userData.animation.camera = camera;

                // Load weapon sounds
                if (model.userData.animation.soundPaths && model.userData.animation.soundPaths.length > 0) {
                    try {
                        const audioLoader = new THREE.AudioLoader();
                        const listener = new THREE.AudioListener();
                        camera.add(listener); // Add listener to camera

                        for (const soundPath of model.userData.animation.soundPaths) {
                            try {
                                const audio = new THREE.Audio(listener);
                                await new Promise((resolve) => {
                                    audioLoader.load(
                                        soundPath,
                                        (buffer) => {
                                            audio.setBuffer(buffer);
                                            audio.setVolume(0.5);
                                            model.userData.animation.sounds.push(audio);
                                            console.log(`🔊 Loaded weapon sound: ${soundPath}`);
                                            resolve();
                                        },
                                        undefined,
                                        (err) => {
                                            console.log(`⚠️ Could not load sound ${soundPath}:`, err);
                                            resolve(); // Don't fail if sound doesn't load
                                        }
                                    );
                                });
                            } catch (e) {
                                console.log(`⚠️ Error loading sound ${soundPath}:`, e);
                            }
                        }

                        console.log(`🔊 Loaded ${model.userData.animation.sounds.length} weapon sounds`);

                        // Emit soundsReady event for weapons
                        if (model.userData.animation.sounds.length > 0) {
                            // Get weapon sound names for display
                            const soundNames = model.userData.animation.soundPaths.map(path => {
                                const parts = path.split('/');
                                return parts[parts.length - 1].replace('.wav', '');
                            });
                            emit('soundsReady', soundNames);
                        }

                        // Start idle sound for lightning gun
                        const weaponName = model.userData.animation?.weaponName || '';
                        const lowerName = weaponName.toLowerCase();
                        const isLightningGun = lowerName.includes('lightning') || lowerName.includes('lg');

                        if (isLightningGun && model.userData.animation.sounds.length >= 3) {
                            const idleSound = model.userData.animation.sounds[0]; // fsthum.wav
                            if (idleSound) {
                                idleSound.setLoop(true);
                                idleSound.play();
                                console.log('🔊 Started lightning gun idle sound');
                            }
                        }
                    } catch (e) {
                        console.log('⚠️ Failed to load weapon sounds:', e);
                    }
                }
            }
        } else {
            // Load single MD3 file (item, etc.) with optional skin file
            model = await loader.load(props.modelPath, props.skinPath);
        }

        // Q3 MD3 models coordinate system conversion:
        // Q3: +X = forward, +Z = up, +Y = left
        // Three.js: +X = right, +Y = up, +Z = backward (towards camera)
        // Rotate to stand upright
        model.rotation.x = -Math.PI / 2;  // Stand upright (Q3 Z-up → Three.js Y-up)
        if (props.isWeapon) {
            model.rotation.z = 0;  // Weapons: barrel points right when viewed from front
        } else {
            model.rotation.z = -Math.PI / 2;  // Players: face camera
        }

        // Force matrix update after rotation
        model.updateMatrixWorld(true);

        // Calculate scale after rotation (mesh-only bbox to match fitToView)
        const box = new THREE.Box3();
        model.traverse((child) => {
            if (child.isMesh && child.geometry) {
                const meshBox = new THREE.Box3().setFromObject(child);
                if (!meshBox.isEmpty()) box.union(meshBox);
            }
        });
        if (box.isEmpty()) box.setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = 50 / maxDim;
        model.scale.setScalar(scale);

        // Force matrix update after scale
        model.updateMatrixWorld(true);

        // For weapons: position and lock basePosition for weapon view animation
        if (props.isWeapon) {
            model.position.set(0, props.thumbnailMode ? 10 : 20, 0);
            // Lock weapon view state so animate loop doesn't overwrite position
            weaponViewState.basePosition.copy(model.position);
            weaponViewState.baseRotation.copy(model.rotation);
        } else {
            // Player models: fitToView handles camera positioning
            model.position.set(0, 0, 0);
        }

        scene.add(model);

        // Initialize animation manager if animations are available
        if (props.loadFullPlayer && model.userData.animations) {
            animationManager = new MD3AnimationManager(model);
            availableAnimations.value = animationManager.getAvailableAnimations();

            // Initialize sound manager
            // UNIFIED LOGIC: Load from base model first, then override with PK3
            if (soundsEnabled.value) {
                try {
                    // Extract model name from path
                    const pathParts = props.modelPath.split('/');
                    const modelName = pathParts[pathParts.length - 2]; // Get second-to-last part (model name)

                    // List of base Q3 models from pak0-pak8.pk3
                    const baseQ3Models = [
                        'sarge', 'grunt', 'major', 'visor', 'slash', 'biker', 'tankjr',
                        'orbb', 'crash', 'razor', 'doom', 'klesk', 'anarki', 'xaero',
                        'mynx', 'hunter', 'bones', 'sorlag', 'lucy', 'keel', 'uriel',
                        'bitterman'
                    ];
                    const isBaseQ3Model = baseQ3Models.includes(modelName.toLowerCase());
                    const baseModelIsBaseQ3 = props.baseModelName && baseQ3Models.includes(props.baseModelName.toLowerCase());

                    let pk3SoundPath;
                    let baseModelSoundPath = null;

                    if (props.modelPath.startsWith('/baseq3/')) {
                        // Pure base Q3 model: /baseq3/models/players/anarki/head.md3
                        // Only load from baseq3 (no base model, no PK3 override)
                        pk3SoundPath = `/baseq3/sound/player/${modelName}`;
                        console.log(`🔊 Loading base Q3 model sounds: ${pk3SoundPath}`);
                    } else {
                        // Custom model from PK3
                        const extractedPath = props.modelPath.match(/^(\/storage\/models\/extracted\/[^\/]+)\//);
                        if (extractedPath) {
                            const basePath = extractedPath[1];
                            pk3SoundPath = `${basePath}/sound/player/${modelName}`;
                        }

                        // If this model has a base model, load base model sounds FIRST
                        if (props.baseModelName) {
                            if (baseModelIsBaseQ3) {
                                // Base model is from baseq3 - load those sounds first
                                baseModelSoundPath = `/baseq3/sound/player/${props.baseModelName}`;
                                console.log(`🔊 Will load base model sounds from: ${baseModelSoundPath}, then override with PK3: ${pk3SoundPath}`);
                            } else {
                                // Base model is custom (from another PK3) - would need to know its path
                                // For now, just load from PK3
                                console.log(`🔊 Loading sounds from PK3: ${pk3SoundPath}`);
                            }
                        } else {
                            // Complete model - load only from PK3
                            console.log(`🔊 Loading complete model sounds from PK3: ${pk3SoundPath}`);
                        }
                    }

                    if (pk3SoundPath) {
                        soundManager = new MD3SoundManager(pk3SoundPath, modelName, baseModelSoundPath, loader);

                        // Initialize audio context on first user interaction
                        await soundManager.initialize();

                        // Load sounds (will load from base model first, then override with PK3)
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

        // Auto-fit camera to show entire model
        fitToView(props.thumbnailMode ? 1.05 : 1.03);

        loading.value = false;
        emit('loaded', model);
    } catch (err) {
        console.error('Failed to load model:', err);
        error.value = err.message || 'Failed to load model';
        loading.value = false;
        emit('error', err);
    }
}

// Weapon view animation - only recoil kick
function updateWeaponViewAnimation(time, deltaTime) {
    if (!model) return;

    // Reset to base position/rotation (set during loadModel)
    model.position.copy(weaponViewState.basePosition);
    model.rotation.copy(weaponViewState.baseRotation);

    // === RECOIL KICK ===
    // Add weapon kick when firing (decays over time)
    const kickDelta = time * 1000 - weaponViewState.kickTime;
    if (kickDelta < 100) {
        const kickAmount = 1.0 - (kickDelta / 100);
        model.rotation.x += weaponViewState.kickAngles.x * kickAmount;
        model.rotation.y += weaponViewState.kickAngles.y * kickAmount;
        model.rotation.z += weaponViewState.kickAngles.z * kickAmount;

        // Kick position (pull back)
        model.position.z -= 5 * kickAmount;
    }
}

function animate() {
    animationFrameId = requestAnimationFrame(animate);

    const deltaTime = clock.getDelta();
    const time = clock.getElapsedTime();

    // Update animation manager (skip when external code is controlling animation directly)
    if (animationManager && !animationPaused) {
        animationManager.update(deltaTime);
    }

    // Update weapon view animations (Q3-style bobbing, sway, recoil)
    if (props.isWeapon && model) {
        updateWeaponViewAnimation(time, deltaTime);
    }

    // Update weapon animations (barrel spin, muzzle flash)
    if (model && model.userData && model.userData.updateAnimation) {
        model.userData.updateAnimation(deltaTime);
    }

    // Update shader animations (tcMod)
    updateShaderAnimations(time);

    // Manual auto-rotate: orbit camera around model's origin (0,0,0) in XZ plane
    // Model origin is the natural rotation center for Q3 models (not bbox center which
    // can be offset by asymmetric geometry like weapons/swords)
    if (manualAutoRotate && controls && camera && model) {
        const rotateSpeed = 2.0;
        const angle = rotateSpeed * deltaTime;
        const pivotX = model.position.x; // always 0
        const pivotZ = model.position.z; // always 0
        const cosA = Math.cos(angle);
        const sinA = Math.sin(angle);

        const dx = camera.position.x - pivotX;
        const dz = camera.position.z - pivotZ;
        camera.position.x = pivotX + dx * cosA - dz * sinA;
        camera.position.z = pivotZ + dx * sinA + dz * cosA;

        const tx = controls.target.x - pivotX;
        const tz = controls.target.z - pivotZ;
        controls.target.x = pivotX + tx * cosA - tz * sinA;
        controls.target.z = pivotZ + tx * sinA + tz * cosA;
    }

    if (controls) {
        controls.update();

        // Camera logging disabled
        // if (props.isWeapon && !props.thumbnailMode && Math.floor(time * 60) % 60 === 0) {
        //     console.log('🎥 Camera:',
        //         `position(${camera.position.x.toFixed(1)}, ${camera.position.y.toFixed(1)}, ${camera.position.z.toFixed(1)})`,
        //         `target(${controls.target.x.toFixed(1)}, ${controls.target.y.toFixed(1)}, ${controls.target.z.toFixed(1)})`
        //     );
        // }
    }

    if (renderer && scene && camera) {
        renderer.render(scene, camera);
    }
}

function updateShaderAnimations(time) {
    if (!model || !loader || !scene) return;

    // Use the shader material system's update method
    const shaderSystem = loader.shaderMaterialSystem;
    if (!shaderSystem) return;

    model.traverse((child) => {
        if (child.isMesh && child.material) {
            const materials = Array.isArray(child.material) ? child.material : [child.material];
            materials.forEach(material => {
                // Update animation time
                shaderSystem.updateMaterialAnimation(material, time);

                // Debug: Log shader uniform updates for materials with scroll (disabled to reduce spam)
                // if (material.uniforms && material.uniforms.scrollSpeed0 &&
                //     (material.uniforms.scrollSpeed0.value.x !== 0 || material.uniforms.scrollSpeed0.value.y !== 0)) {
                //     if (Math.floor(time * 10) % 30 === 0) { // Log every 3 seconds
                //         console.log(`🔄 Shader animation update for ${child.name}:`,
                //             `time=${time.toFixed(2)}`,
                //             `scroll=(${material.uniforms.scrollSpeed0.value.x}, ${material.uniforms.scrollSpeed0.value.y})`
                //         );
                //     }
                // }

                // Update lighting from scene lights
                shaderSystem.updateLightingFromScene(material, scene);
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

// Watch for skin changes (reload model with new skin)
watch(() => props.backgroundColor, (newBg) => {
    if (scene) {
        scene.background = new THREE.Color(newBg === 'white' ? 0xffffff : 0x000000);
    }
});

watch(() => props.skinName, (newSkin, oldSkin) => {
    if (!scene || !renderer) return;
    if (newSkin && newSkin !== oldSkin) {
        if (model) {
            scene.remove(model);
            model.traverse((child) => {
                if (child.geometry) child.geometry.dispose();
                if (child.material) {
                    const mats = Array.isArray(child.material) ? child.material : [child.material];
                    mats.forEach(m => {
                        ['map', 'normalMap', 'roughnessMap', 'metalnessMap', 'aoMap', 'emissiveMap', 'alphaMap', 'envMap'].forEach(prop => {
                            if (m[prop]) m[prop].dispose();
                        });
                        m.dispose();
                    });
                }
            });
            model = null;
        }
        loader.skinData = null;
        loadModel();
    }
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

// Method to set auto-rotate (rotates around model's geometric center)
function setAutoRotate(enabled) {
    manualAutoRotate = enabled;
    // Don't use controls.autoRotate - we handle rotation manually around modelCenter
    if (controls) {
        controls.autoRotate = false;
    }
}

// Light control methods
function setAmbientLightIntensity(intensity) {
    if (ambientLight) {
        ambientLight.intensity = intensity;
    }
}

function setAmbientLightColor(color) {
    if (ambientLight) {
        ambientLight.color.set(color);
    }
}

function setDirectionalLightIntensity(intensity) {
    if (directionalLight) {
        directionalLight.intensity = intensity;
    }
}

function setDirectionalLightColor(color) {
    if (directionalLight) {
        directionalLight.color.set(color);
    }
}

function setDirectionalLightPosition(x, y, z) {
    if (directionalLight) {
        directionalLight.position.set(x, y, z);
    }
}

function setBackLightIntensity(intensity) {
    if (backLight) {
        backLight.intensity = intensity;
    }
}

function setBackLightColor(color) {
    if (backLight) {
        backLight.color.set(color);
    }
}

function setBackLightPosition(x, y, z) {
    if (backLight) {
        backLight.position.set(x, y, z);
    }
}

function getLightSettings() {
    return {
        ambient: {
            intensity: ambientLight?.intensity || 0.6,
            color: '#' + (ambientLight?.color.getHexString() || 'ffffff')
        },
        directional: {
            intensity: directionalLight?.intensity || 0.8,
            color: '#' + (directionalLight?.color.getHexString() || 'ffffff'),
            position: {
                x: directionalLight?.position.x || 50,
                y: directionalLight?.position.y || 100,
                z: directionalLight?.position.z || 50
            }
        },
        back: {
            intensity: backLight?.intensity || 0.3,
            color: '#' + (backLight?.color.getHexString() || '4488ff'),
            position: {
                x: backLight?.position.x || -50,
                y: backLight?.position.y || 50,
                z: backLight?.position.z || -50
            }
        }
    };
}

// Weapon firing controls
let firingInterval = null;

// Get authentic Q3 fire rate based on weapon name
function getWeaponFireRate(weaponName) {
    const lowerName = weaponName.toLowerCase();

    // Fire rates from Quake 3 engine (bg_pmove.c)
    // IMPORTANT: Check 'rail' BEFORE 'lg' because "railgun" contains "lg"!
    if (lowerName.includes('rail') || lowerName.includes('rg')) {
        return 1500; // 0.67 shots/second
    } else if (lowerName.includes('lightning') || lowerName.includes('lg')) {
        return 50; // 20 shots/second
    } else if (lowerName.includes('machine') || lowerName.includes('mg')) {
        return 100; // 10 shots/second
    } else if (lowerName.includes('plasma') || lowerName.includes('pg')) {
        return 100; // 10 shots/second
    } else if (lowerName.includes('shotgun') || lowerName.includes('sg')) {
        return 1000; // 1 shot/second
    } else if (lowerName.includes('grenade') || lowerName.includes('gl')) {
        return 800; // 1.25 shots/second
    } else if (lowerName.includes('rocket') || lowerName.includes('rl')) {
        return 800; // 1.25 shots/second
    } else if (lowerName.includes('bfg')) {
        return 200; // 5 shots/second
    } else if (lowerName.includes('grapple') || lowerName.includes('hook')) {
        return 400; // 2.5 shots/second
    } else if (lowerName.includes('gauntlet') || lowerName.includes('melee')) {
        return 400; // 2.5 shots/second
    }

    return 100; // Default: 10 shots/second
}

function startFiring() {
    if (!model || !model.userData || !model.userData.fire) return;

    // Prevent starting a new firing interval if already firing
    if (firingInterval) return;

    // Fire immediately (first fire - pass true for isFirstFire)
    model.userData.fire(true);

    // Trigger weapon kick/recoil
    triggerWeaponKick();

    // Get weapon-specific fire rate
    const weaponName = model.userData.animation?.weaponName || '';
    const fireRate = getWeaponFireRate(weaponName);

    console.log(`🔫 Weapon ${weaponName} fire rate: ${fireRate}ms (${(1000/fireRate).toFixed(1)} shots/sec)`);

    // Continue firing while button is held (automatic fire)
    firingInterval = setInterval(() => {
        if (model && model.userData && model.userData.fire) {
            model.userData.fire(false); // Not first fire - pass false
            triggerWeaponKick();
        }
    }, fireRate);
}

// Start firing WITH hit sounds (for gauntlet and lightning gun)
function startFiringWithHit() {
    if (!model || !model.userData || !model.userData.fireWithHit) return;

    // Prevent starting a new firing interval if already firing
    if (firingInterval) return;

    // Fire immediately with hit sounds (first fire - pass true for isFirstFire)
    model.userData.fireWithHit(true);

    // Trigger weapon kick/recoil
    triggerWeaponKick();

    // Get weapon-specific fire rate
    const weaponName = model.userData.animation?.weaponName || '';
    const fireRate = getWeaponFireRate(weaponName);

    console.log(`🔫 Weapon ${weaponName} fire rate WITH HIT: ${fireRate}ms (${(1000/fireRate).toFixed(1)} shots/sec)`);

    // Continue firing while button is held (automatic fire with hit sounds)
    firingInterval = setInterval(() => {
        if (model && model.userData && model.userData.fireWithHit) {
            model.userData.fireWithHit(false); // Not first fire - pass false
            triggerWeaponKick();
        }
    }, fireRate);
}

// Trigger weapon recoil kick
function triggerWeaponKick() {
    if (!props.isWeapon || !model) return;

    // Set kick angles based on weapon type
    const weaponName = model.userData.animation?.weaponName || '';
    const lowerName = weaponName.toLowerCase();

    // Different weapons have different kick amounts
    if (lowerName.includes('rocket') || lowerName.includes('rl')) {
        weaponViewState.kickAngles.set(-0.15, 0, 0); // Strong upward kick
    } else if (lowerName.includes('shotgun') || lowerName.includes('sg')) {
        weaponViewState.kickAngles.set(-0.12, 0, 0); // Medium-strong upward kick
    } else if (lowerName.includes('grenade') || lowerName.includes('gl')) {
        weaponViewState.kickAngles.set(-0.08, 0, 0); // Medium upward kick
    } else if (lowerName.includes('plasma') || lowerName.includes('pg')) {
        weaponViewState.kickAngles.set(-0.04, 0, 0); // Light upward kick
    } else if (lowerName.includes('machine') || lowerName.includes('mg')) {
        weaponViewState.kickAngles.set(-0.03, Math.random() * 0.02 - 0.01, 0); // Very light kick with horizontal spread
    } else if (lowerName.includes('lightning') || lowerName.includes('lg')) {
        weaponViewState.kickAngles.set(-0.02, 0, 0); // Minimal kick
    } else if (lowerName.includes('rail') || lowerName.includes('rg')) {
        weaponViewState.kickAngles.set(-0.10, 0, 0); // Strong kick
    } else {
        weaponViewState.kickAngles.set(-0.05, 0, 0); // Default kick
    }

    // Set kick time
    weaponViewState.kickTime = clock.getElapsedTime() * 1000;
}

function stopFiring() {
    if (firingInterval) {
        clearInterval(firingInterval);
        firingInterval = null;
    }

    // Stop barrel spinning
    if (model && model.userData && model.userData.stopFiring) {
        model.userData.stopFiring();
    }
}

// Keyboard event handlers for firing with Ctrl key
function handleKeyDown(event) {
    // Only fire for weapons, and only if Ctrl is pressed
    if (!props.isWeapon) return;

    // Ignore keyboard repeat events to prevent rapid firing
    if (event.repeat) return;

    if (event.ctrlKey && !firingInterval) {
        event.preventDefault();
        startFiring();
    }
}

function handleKeyUp(event) {
    // Stop firing when Ctrl is released
    if (!props.isWeapon) return;

    if (!event.ctrlKey) {
        stopFiring();
    }
}

// Fit camera to show entire model at maximum zoom using screen-space projection.
// Instead of estimating from bounding box, we project actual vertices to measure
// real screen coverage, then adjust camera distance to fill viewport uniformly.
function fitToView(paddingFactor = 1.1) {
    if (!model || !camera || !controls || !renderer) return;

    // Ensure correct aspect ratio
    const renderSize = new THREE.Vector2();
    renderer.getSize(renderSize);
    if (renderSize.x > 0 && renderSize.y > 0) {
        camera.aspect = renderSize.x / renderSize.y;
        camera.updateProjectionMatrix();
    }

    // Tick animation so we measure the actual animated pose
    if (animationManager && animationManager.playing) {
        animationManager.update(1 / 60);
    }
    model.updateMatrixWorld(true);

    // Step 1: Rough bbox for initial camera placement (mesh-only to exclude tags)
    const box = new THREE.Box3();
    model.traverse((child) => {
        if (child.isMesh && child.geometry) {
            const meshBox = new THREE.Box3().setFromObject(child);
            if (!meshBox.isEmpty()) box.union(meshBox);
        }
    });
    if (box.isEmpty()) box.setFromObject(model);
    const center = box.getCenter(new THREE.Vector3());
    modelCenter.copy(center); // Store for rotation pivot
    const size = box.getSize(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    const fov = camera.fov * (Math.PI / 180);
    const initialDist = (maxDim / 2) / Math.tan(fov / 2) * 2;

    // Position camera: use model origin for XZ (correct rotation pivot), bbox center Y
    camera.position.set(model.position.x, center.y, model.position.z + initialDist);
    controls.target.set(model.position.x, center.y, model.position.z);
    controls.update();
    camera.updateMatrixWorld();
    camera.updateProjectionMatrix();

    // Step 2: Iterative NDC projection to converge on perfect fit
    // Each iteration: project vertices, measure NDC bounds, adjust camera
    const targetExtent = 2 / paddingFactor;
    const tempVec = new THREE.Vector3();

    for (let iter = 0; iter < 3; iter++) {
        camera.updateMatrixWorld();
        camera.updateProjectionMatrix();

        let minX = Infinity, maxX = -Infinity;
        let minY = Infinity, maxY = -Infinity;

        model.traverse((child) => {
            if (child.isMesh && child.geometry && child.geometry.attributes.position) {
                const positions = child.geometry.attributes.position;
                const morphAttrs = child.geometry.morphAttributes.position;
                const influences = child.morphTargetInfluences;
                const hasMorph = morphAttrs && influences && morphAttrs.length > 0;
                child.updateWorldMatrix(true, false);
                for (let i = 0; i < positions.count; i++) {
                    tempVec.fromBufferAttribute(positions, i);
                    if (hasMorph) {
                        const bx = tempVec.x, by = tempVec.y, bz = tempVec.z;
                        for (let m = 0; m < influences.length; m++) {
                            if (influences[m] > 0 && morphAttrs[m]) {
                                tempVec.x += influences[m] * (morphAttrs[m].getX(i) - bx);
                                tempVec.y += influences[m] * (morphAttrs[m].getY(i) - by);
                                tempVec.z += influences[m] * (morphAttrs[m].getZ(i) - bz);
                            }
                        }
                    }
                    tempVec.applyMatrix4(child.matrixWorld);
                    tempVec.project(camera);
                    if (tempVec.z >= -1 && tempVec.z <= 1) {
                        minX = Math.min(minX, tempVec.x);
                        maxX = Math.max(maxX, tempVec.x);
                        minY = Math.min(minY, tempVec.y);
                        maxY = Math.max(maxY, tempVec.y);
                    }
                }
            }
        });

        if (minX === Infinity) return;

        const ndcWidth = maxX - minX;
        const ndcHeight = maxY - minY;
        const ndcExtent = Math.max(ndcWidth, ndcHeight);
        const ndcCenterX = (minX + maxX) / 2;
        const ndcCenterY = (minY + maxY) / 2;

        // Current camera distance to target
        const currentDist = camera.position.distanceTo(controls.target);

        // Adjust distance: larger ndcExtent → move further, smaller → move closer
        const zoomRatio = ndcExtent / targetExtent;
        const newDist = currentDist * zoomRatio;

        // Convert NDC center offset to world offset at current distance
        const worldHalfH = currentDist * Math.tan(fov / 2);
        const worldHalfW = worldHalfH * camera.aspect;
        const offsetX = ndcCenterX * worldHalfW;
        const offsetY = ndcCenterY * worldHalfH;

        // Only shift target Y (vertical centering), keep XZ at model origin for correct rotation pivot
        const newTargetX = controls.target.x; // don't shift XZ - keep at model origin
        const newTargetY = controls.target.y + offsetY;
        const newTargetZ = controls.target.z;

        camera.position.set(newTargetX, newTargetY, newTargetZ + newDist);
        controls.target.set(newTargetX, newTargetY, newTargetZ);
        controls.update();
    }
}

// Expose methods for parent component
defineExpose({
    getModel: () => model,
    getScene: () => scene,
    getCamera: () => camera,
    getRenderer: () => renderer,
    getControls: () => controls,
    getModelCenter: () => modelCenter,
    getPendingTextures: () => loader.pendingTextures,
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
    pauseAnimationLoop: () => { animationPaused = true; },
    resumeAnimationLoop: () => { animationPaused = false; },
    updateShaderAnimations: (time) => updateShaderAnimations(time),
    getAvailableAnimations: () => availableAnimations.value,
    playSound: (name, options) => {
        // For player models, use soundManager
        if (soundManager) {
            return soundManager.playSound(name, options);
        }
        // For weapons, play the specific sound by name
        if (model && model.userData && model.userData.animation && model.userData.animation.soundPaths) {
            const soundIndex = model.userData.animation.soundPaths.findIndex(path => path.includes(name));
            if (soundIndex >= 0 && model.userData.animation.sounds[soundIndex]) {
                const sound = model.userData.animation.sounds[soundIndex];
                if (sound.isPlaying) {
                    sound.stop();
                }
                sound.play();
            }
        }
    },
    stopSound: (name) => {
        if (soundManager) {
            return soundManager.stopSound(name);
        }
        // For weapons, stop the specific sound by name
        if (model && model.userData && model.userData.animation && model.userData.animation.soundPaths) {
            const soundIndex = model.userData.animation.soundPaths.findIndex(path => path.includes(name));
            if (soundIndex >= 0 && model.userData.animation.sounds[soundIndex]) {
                model.userData.animation.sounds[soundIndex].stop();
            }
        }
    },
    stopAllSounds: () => {
        if (soundManager) {
            soundManager.stopAllSounds();
        }
        // For weapons, stop all weapon sounds
        if (model && model.userData && model.userData.animation && model.userData.animation.sounds) {
            model.userData.animation.sounds.forEach(sound => {
                if (sound && sound.isPlaying) {
                    sound.stop();
                }
            });
        }
    },
    setSoundVolume: (volume) => {
        // Store the volume so we can restore it on unmute
        currentVolume.value = volume;

        // For player models, use soundManager
        if (soundManager) {
            soundManager.setVolume(volume);
        }
        // For weapons, set volume directly on weapon sounds
        if (model && model.userData && model.userData.animation && model.userData.animation.sounds) {
            model.userData.animation.sounds.forEach(sound => {
                if (sound && sound.setVolume) {
                    sound.setVolume(volume);
                }
            });
        }
    },
    setSoundsEnabled: (enabled) => {
        soundsEnabled.value = enabled;
        soundManager?.setEnabled(enabled);

        // For weapons, mute/unmute all weapon sounds
        if (model && model.userData && model.userData.animation && model.userData.animation.sounds) {
            model.userData.animation.sounds.forEach(sound => {
                if (sound) {
                    if (enabled) {
                        // Unmute - restore volume using stored currentVolume
                        if (sound.setVolume) {
                            sound.setVolume(currentVolume.value);
                        }
                    } else {
                        // Mute - set volume to 0
                        if (sound.setVolume) {
                            sound.setVolume(0);
                        }
                    }
                }
            });
        }
    },
    areSoundsLoaded: () => soundsLoaded.value,
    setWireframe: setWireframe,
    setAutoRotate: setAutoRotate,
    // Light controls
    setAmbientLightIntensity,
    setAmbientLightColor,
    setDirectionalLightIntensity,
    setDirectionalLightColor,
    setDirectionalLightPosition,
    setBackLightIntensity,
    setBackLightColor,
    setBackLightPosition,
    getLightSettings,
    fitToView
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

        <!-- Weapon firing controls -->
        <div v-if="isWeapon && !loading && !error" class="absolute bottom-4 right-4 flex gap-2">
            <button
                @mousedown.prevent="startFiring"
                @mouseup.prevent="stopFiring"
                @mouseleave="stopFiring"
                @touchstart.prevent="startFiring"
                @touchend.prevent="stopFiring"
                @contextmenu.prevent
                class="bg-red-600/80 hover:bg-red-500 backdrop-blur-sm rounded-lg px-6 py-3 text-white font-bold shadow-lg transition-all hover:shadow-red-500/50 active:scale-95 select-none"
            >
                🔫 FIRE
            </button>
            <!-- Fire + Hit button (only for gauntlet and lightning gun) -->
            <button
                v-if="hasHitSounds"
                @mousedown.prevent="startFiringWithHit"
                @mouseup.prevent="stopFiring"
                @mouseleave="stopFiring"
                @touchstart.prevent="startFiringWithHit"
                @touchend.prevent="stopFiring"
                @contextmenu.prevent
                class="bg-orange-600/80 hover:bg-orange-500 backdrop-blur-sm rounded-lg px-6 py-3 text-white font-bold shadow-lg transition-all hover:shadow-orange-500/50 active:scale-95 select-none"
            >
                💥 FIRE + HIT
            </button>
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
