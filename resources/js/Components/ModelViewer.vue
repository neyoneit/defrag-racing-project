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

// Light references
let ambientLight = null;
let directionalLight = null;
let backLight = null;

const loading = ref(true);
const error = ref(null);
const availableAnimations = ref({ legs: {}, torso: {}, both: {} });
const soundsEnabled = ref(props.enableSounds);
const soundsLoaded = ref(false);

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
    controls.autoRotate = props.autoRotate;
    controls.autoRotateSpeed = 2.0;

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
            model = await loader.loadPlayerModel(baseDir, modelName, props.skinName, props.skinPackBasePath);
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

            model = await loader.loadWeaponModel(baseDir, weaponName);

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

        // For weapons: center the model at its bounding box center first
        // This ensures all weapons align consistently regardless of their internal pivot points
        if (props.isWeapon) {
            const centeredBox = new THREE.Box3().setFromObject(model);
            const center = centeredBox.getCenter(new THREE.Vector3());

            // Offset the model so its bounding box center is at the origin
            model.position.set(-center.x, -center.y, -center.z);
            model.updateMatrixWorld(true);

            // Now position all weapons at the same Y height (centered at their bounding box)
            model.position.set(0, props.thumbnailMode ? 10 : 20, 0);
        } else {
            // Player models: position higher for detail view
            model.position.set(0, props.thumbnailMode ? 0 : 30, 0);
        }

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
                        // For skin packs, try the skin pack's sound directory first, then fall back to base model
                        if (props.skinPackBasePath) {
                            // Skin pack: try /storage/models/extracted/scorn-myriane.../sound/player/major/
                            const skinPackPath = props.skinPackBasePath.match(/^(\/storage\/models\/extracted\/[^\/]+)\//);
                            if (skinPackPath) {
                                const basePath = skinPackPath[1];
                                soundPath = `${basePath}/sound/player/${modelName}`;
                            }
                        } else {
                            // Complete model: use its own sound directory
                            const extractedPath = props.modelPath.match(/^(\/storage\/models\/extracted\/[^\/]+)\//);
                            if (extractedPath) {
                                const basePath = extractedPath[1];
                                soundPath = `${basePath}/sound/player/${modelName}`;
                            }
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

// Weapon view animation - only recoil kick
function updateWeaponViewAnimation(time, deltaTime) {
    if (!model) return;

    // Store base position/rotation if not set
    if (weaponViewState.basePosition.length() === 0) {
        weaponViewState.basePosition.copy(model.position);
        weaponViewState.baseRotation.copy(model.rotation);
    }

    // Reset to base position/rotation
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

    // Update animation manager
    if (animationManager) {
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

    if (controls) {
        controls.update();

        // Log camera position and controls target for weapons (every 60 frames = ~1 second)
        if (props.isWeapon && !props.thumbnailMode && Math.floor(time * 60) % 60 === 0) {
            console.log('🎥 Camera:',
                `position(${camera.position.x.toFixed(1)}, ${camera.position.y.toFixed(1)}, ${camera.position.z.toFixed(1)})`,
                `target(${controls.target.x.toFixed(1)}, ${controls.target.y.toFixed(1)}, ${controls.target.z.toFixed(1)})`
            );
        }
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

// Expose methods for parent component
defineExpose({
    getModel: () => model,
    getScene: () => scene,
    getCamera: () => camera,
    getRenderer: () => renderer,
    getControls: () => controls,
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
    getLightSettings
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
