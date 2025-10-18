<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import ModelViewer from '@/Components/ModelViewer.vue';

const props = defineProps({
    model: Object,
    baseModelData: Object,
    load_times: Object,
});

// Frontend timing metrics
const frontendTimings = ref({
    mount_to_ready: 0,
    total_page_load: 0,
    dom_content_loaded: 0,
    model_load_start: 0,
    model_load_time: 0
});

// Calculate frontend load times
onMounted(() => {
    const mountTime = performance.now();
    frontendTimings.value.mount_to_ready = mountTime;
    frontendTimings.value.model_load_start = performance.now();

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

// Light controls
const lightSettings = ref({
    ambient: { intensity: 0.6, color: '#ffffff' },
    directional: { intensity: 0.8, color: '#ffffff', position: { x: 50, y: 100, z: 50 } },
    back: { intensity: 0.3, color: '#4488ff', position: { x: -50, y: 50, z: -50 } }
});
const showLightControls = ref(false);

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

// Initialize currentSkin with the first available skin
const currentSkin = ref(availableSkins.value[0] || 'default');

// Q3-style input state (mimics usercmd_t and playerState_t)
const forwardMove = ref(0);  // -127 to 127
const rightMove = ref(0);    // -127 to 127
const isCrouching = ref(false); // PMF_DUCKED
const isAttacking = ref(false); // BUTTON_ATTACK
const isGesturing = ref(false); // BUTTON_GESTURE

// Check if this is a weapon model (not a player model)
const isWeaponModel = computed(() => props.model.category === 'weapon');

// Get the path to the model file (MD3 files for the 3D geometry)
const modelFilePath = computed(() => {
    // Weapon models: single MD3 file (e.g., /baseq3/models/weapons2/bfg/bfg.md3)
    if (isWeaponModel.value) {
        if (!props.model.file_path) return null;

        // Use main_file if available (for weapons with non-standard filenames like shells -> m_shell.md3)
        const mainFile = props.model.main_file || `${props.model.base_model || props.model.name}.md3`;

        if (props.model.file_path.startsWith('baseq3/')) {
            return `/${props.model.file_path}/${mainFile}`;
        }

        return `/storage/${props.model.file_path}/${mainFile}`;
    }

    // Player models: composite model (head/upper/lower)
    // For skin/mixed packs, use the base model's file_path (where the MD3 files are)
    if (props.baseModelData && props.baseModelData.file_path) {
        const basePath = props.baseModelData.file_path;
        const modelName = props.baseModelData.name;

        if (basePath.startsWith('baseq3/')) {
            return `/${basePath}/head.md3`;
        }

        return `/storage/${basePath}/models/players/${modelName}/head.md3`;
    }

    // For complete models, use the model's own file_path
    if (!props.model.file_path) return null;

    if (props.model.file_path.startsWith('baseq3/')) {
        return `/${props.model.file_path}/head.md3`;
    }

    const modelName = props.model.base_model || props.model.name;
    return `/storage/${props.model.file_path}/models/players/${modelName}/head.md3`;
});

// Get the path to the skin file
const skinFilePath = computed(() => {
    // Weapons don't use skin files - textures are referenced directly in MD3 or shaders
    if (isWeaponModel.value) return null;

    if (!props.model.file_path) return null;

    // Check if this is a base Q3 model
    if (props.model.file_path.startsWith('baseq3/')) {
        return `/${props.model.file_path}/head_${currentSkin.value}.skin`;
    }

    // User-uploaded models - use base_model for folder path (where the actual files are)
    // For complete models: base_model == name
    // For skin/mixed packs: base_model is the folder with the actual files
    const modelName = props.model.base_model || props.model.name;

    return `/storage/${props.model.file_path}/models/players/${modelName}/head_${currentSkin.value}.skin`;
});

// Get the base path for skin pack files (for skin/mixed packs that override base model skins)
const skinPackBasePath = computed(() => {
    // Only needed if this is a skin/mixed pack (has baseModelData)
    if (!props.baseModelData) return null;

    // Return the skin pack's extracted directory path
    if (props.model.file_path && !props.model.file_path.startsWith('baseq3/')) {
        const modelName = props.model.base_model || props.model.name;
        return `/storage/${props.model.file_path}/models/players/${modelName}`;
    }

    return null;
});

const onViewerLoaded = () => {
    viewerLoaded.value = true;

    // Record model load time
    frontendTimings.value.model_load_time = performance.now() - frontendTimings.value.model_load_start;

    // Get initial light settings
    if (viewer3D.value) {
        lightSettings.value = viewer3D.value.getLightSettings();
    }

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

// Check if this is a base Q3 model (cannot be downloaded)
const isBaseQ3Model = computed(() => {
    return props.model.file_path && props.model.file_path.startsWith('baseq3/');
});

const downloadModel = () => {
    if (isBaseQ3Model.value) {
        showErrorNotification('Base Q3 models cannot be downloaded. They are included with Quake 3.');
        return;
    }
    window.location.href = route('models.download', props.model.id);
};

const goBack = () => {
    window.history.back();
};

// GIF Generation (browser-based)
const isGeneratingGif = ref(false);
const gifProgress = ref('');

// Notification state
const showNotification = ref(false);
const notificationMessage = ref('');
const notificationType = ref('success'); // 'success' or 'error'

const showSuccessNotification = (message) => {
    notificationMessage.value = message;
    notificationType.value = 'success';
    showNotification.value = true;
    setTimeout(() => {
        showNotification.value = false;
    }, 3000);
};

const showErrorNotification = (message) => {
    notificationMessage.value = message;
    notificationType.value = 'error';
    showNotification.value = true;
    setTimeout(() => {
        showNotification.value = false;
    }, 5000);
};

// SEPARATE HEAD ICON GENERATOR (no GIF, just PNG)
const isGeneratingHeadIcon = ref(false);
const headIconProgress = ref('');

// Function to log current camera position - call from console after manually positioning
const logCameraPosition = () => {
    if (!viewer3D.value) return;
    const camera = viewer3D.value.getCamera();
    const controls = viewer3D.value.getControls();
    console.log('=== CURRENT CAMERA POSITION ===');
    console.log('Camera Position:', { x: camera.position.x, y: camera.position.y, z: camera.position.z });
    console.log('Target Position:', { x: controls.target.x, y: controls.target.y, z: controls.target.z });
    console.log('================================');
};

// Expose to window so you can call it from console
if (typeof window !== 'undefined') {
    window.logCameraPosition = logCameraPosition;
}

const generateHeadIcon = async () => {
    if (!viewer3D.value) {
        showErrorNotification('3D model is not loaded yet');
        return;
    }

    isGeneratingHeadIcon.value = true;
    headIconProgress.value = 'Focusing on head...';

    try {
        const THREE = await import('three');

        const model = viewer3D.value.getModel();
        const camera = viewer3D.value.getCamera();
        const controls = viewer3D.value.getControls();
        const renderer = viewer3D.value.getRenderer();
        const scene = viewer3D.value.getScene();

        if (!model) {
            throw new Error('Model not loaded yet');
        }

        // Save original camera position and controls target
        const originalPosition = camera.position.clone();
        const originalTarget = controls.target.clone();

        // Find the head mesh (highest Y position = topmost part)
        console.log('=== FINDING HEAD MESH ===');
        let headMesh = null;
        let highestY = -Infinity;

        model.traverse((child) => {
            if (child.isMesh) {
                const worldPos = new THREE.Vector3();
                child.getWorldPosition(worldPos);
                if (worldPos.y > highestY) {
                    highestY = worldPos.y;
                    headMesh = child;
                }
            }
        });

        if (!headMesh) {
            throw new Error('Could not find any meshes in model');
        }

        console.log('Selected as head (highest Y):', headMesh.name || 'unnamed', 'at Y:', highestY);

        // Get head bounding box
        const headBox = new THREE.Box3().setFromObject(headMesh);
        const headCenter = headBox.getCenter(new THREE.Vector3());
        const headSize = headBox.getSize(new THREE.Vector3());

        // Calculate camera distance to fit head perfectly in frame (FOV-based)
        const maxDim = Math.max(headSize.x, headSize.y, headSize.z);
        const fov = camera.fov * (Math.PI / 180);
        let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
        cameraZ *= 1.5; // 50% padding

        console.log('Calculated camera distance:', cameraZ, 'for head max dimension:', maxDim);

        // Update controls target to focus on head center
        controls.target.copy(headCenter);
        controls.update();

        // Position camera in front of head
        camera.position.set(
            headCenter.x,
            headCenter.y,
            headCenter.z + cameraZ
        );

        // Force render
        camera.updateMatrixWorld();
        scene.updateMatrixWorld(true);
        await new Promise(resolve => requestAnimationFrame(resolve));
        renderer.render(scene, camera);
        await new Promise(resolve => requestAnimationFrame(resolve));

        headIconProgress.value = 'Capturing...';

        // Capture screenshot
        const canvas = renderer.domElement;
        const canvasWidth = canvas.width;
        const canvasHeight = canvas.height;

        // Resize to 64x64
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = 64;
        finalCanvas.height = 64;
        const finalCtx = finalCanvas.getContext('2d');
        finalCtx.drawImage(canvas, 0, 0, canvasWidth, canvasHeight, 0, 0, 64, 64);

        // Convert to blob
        const headIconBlob = await new Promise(resolve => finalCanvas.toBlob(resolve, 'image/png'));

        headIconProgress.value = 'Uploading...';

        // Upload to server
        const formData = new FormData();
        formData.append('head_icon', headIconBlob, `model_${props.model.id}_head.png`);

        const response = await fetch(route('models.saveHeadIcon', props.model.id), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.success) {
            headIconProgress.value = 'Done!';
            showSuccessNotification('Head icon generated successfully!');
        } else {
            throw new Error(data.message || 'Failed to save head icon');
        }

        // Restore camera position and controls target
        camera.position.copy(originalPosition);
        controls.target.copy(originalTarget);
        controls.update();

    } catch (error) {
        console.error('Head icon error:', error);
        showErrorNotification('Failed to generate head icon: ' + error.message);
    }

    isGeneratingHeadIcon.value = false;
};

const generateGifThumbnail = async () => {
    if (!viewer3D.value) {
        showErrorNotification('3D model is not loaded yet');
        return;
    }

    isGeneratingGif.value = true;
    gifProgress.value = 'Preparing...';

    try {
        // Get the renderer and canvas from ModelViewer
        const renderer = viewer3D.value.getRenderer();
        const scene = viewer3D.value.getScene();
        const camera = viewer3D.value.getCamera();

        if (!renderer || !scene || !camera) {
            throw new Error('Could not access 3D viewer components');
        }

        // Get actual canvas dimensions
        const canvas = renderer.domElement;
        const canvasWidth = canvas.width;
        const canvasHeight = canvas.height;

        console.log('Canvas dimensions:', canvasWidth, 'x', canvasHeight);

        // Use smaller resolution for GIF (300x300 for smaller file size)
        const gifWidth = 300;
        const gifHeight = 300;

        console.log('GIF dimensions:', gifWidth, 'x', gifHeight);

        // Dynamically import gif.js
        const GIF = (await import('gif.js')).default;

        // Create GIF encoder with reduced dimensions for smaller file size
        const gif = new GIF({
            workers: 2,
            quality: 10, // 1-30, higher = lower quality but smaller file (10 = good quality)
            width: gifWidth,
            height: gifHeight,
            workerScript: '/gif.worker.js'
        });

        // Save the current camera position and target
        const originalPosition = camera.position.clone();
        const originalRotation = camera.rotation.clone();

        // Get the controls target (what the camera is looking at - the model center)
        const controls = viewer3D.value.getControls();
        const target = controls?.target ? { x: controls.target.x, y: controls.target.y, z: controls.target.z } : { x: 0, y: 30, z: 0 };

        console.log('Camera position:', originalPosition);
        console.log('Target:', target);

        // Calculate camera distance from the target (not from origin)
        const dx = originalPosition.x - target.x;
        const dy = originalPosition.y - target.y;
        const dz = originalPosition.z - target.z;
        const radius = Math.sqrt(dx * dx + dz * dz); // Horizontal distance
        const height = dy; // Vertical offset from target

        // Get the starting angle based on current camera position relative to target
        const startAngle = Math.atan2(dx, dz);

        console.log('Radius:', radius, 'Height:', height, 'Start angle:', startAngle);

        // Create a temporary canvas for resizing frames
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = gifWidth;
        tempCanvas.height = gifHeight;
        const tempCtx = tempCanvas.getContext('2d');

        // Capture 24 frames (2 second loop at 12fps)
        const numFrames = 24;
        const rotationStep = (Math.PI * 2) / numFrames;

        for (let i = 0; i < numFrames; i++) {
            gifProgress.value = `Capturing frame ${i + 1}/${numFrames}...`;

            // Rotate the camera around the target, starting from current position
            const angle = startAngle + (i * rotationStep);
            camera.position.x = target.x + Math.sin(angle) * radius;
            camera.position.z = target.z + Math.cos(angle) * radius;
            camera.position.y = target.y + height; // Keep same height relative to target

            // Use THREE.Vector3 for lookAt
            camera.lookAt(target.x, target.y, target.z);

            // Update camera matrix and force a render
            camera.updateMatrixWorld();
            scene.updateMatrixWorld(true);

            // Wait for next frame to ensure rendering is complete
            await new Promise(resolve => requestAnimationFrame(resolve));

            // Render the scene
            renderer.render(scene, camera);

            // Wait one more frame to ensure pixel data is ready
            await new Promise(resolve => requestAnimationFrame(resolve));

            // Resize the canvas to the smaller GIF dimensions (simple 2D downsampling)
            tempCtx.drawImage(canvas, 0, 0, canvasWidth, canvasHeight, 0, 0, gifWidth, gifHeight);

            // Add the resized frame to GIF
            gif.addFrame(tempCanvas, { copy: true, delay: 83 }); // 83ms = ~12fps

            // Small delay to let browser breathe
            await new Promise(resolve => setTimeout(resolve, 20));
        }

        gifProgress.value = 'Encoding GIF...';

        // Render the GIF
        gif.on('finished', async (blob) => {
            gifProgress.value = 'Generating head icon...';

            // Generate 64x64 head icon PNG using FOV-based auto-fit
            let headIconBlob = null;
            try {
                const THREE = await import('three');

                const model = viewer3D.value.getModel();
                if (!model) {
                    throw new Error('Model not loaded');
                }

                // Find the head mesh (highest Y position = topmost part)
                console.log('=== GIF: FINDING HEAD MESH ===');
                let headMesh = null;
                let highestY = -Infinity;

                model.traverse((child) => {
                    if (child.isMesh) {
                        const worldPos = new THREE.Vector3();
                        child.getWorldPosition(worldPos);
                        if (worldPos.y > highestY) {
                            highestY = worldPos.y;
                            headMesh = child;
                        }
                    }
                });

                if (!headMesh) {
                    throw new Error('Could not find head mesh');
                }

                console.log('GIF: Selected head at Y:', highestY);

                // Get head bounding box
                const headBox = new THREE.Box3().setFromObject(headMesh);
                const headCenter = headBox.getCenter(new THREE.Vector3());
                const headSize = headBox.getSize(new THREE.Vector3());

                // Calculate camera distance to fit head perfectly in frame (FOV-based)
                const maxDim = Math.max(headSize.x, headSize.y, headSize.z);
                const fov = camera.fov * (Math.PI / 180);
                let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
                cameraZ *= 1.5; // 50% padding

                console.log('GIF: Head camera distance:', cameraZ, 'for max dimension:', maxDim);

                // Update controls target to focus on head center (same as standalone button)
                if (controls) {
                    controls.target.set(headCenter.x, headCenter.y, headCenter.z);
                    controls.update();
                }

                // Position camera in front of head
                camera.position.set(
                    headCenter.x,
                    headCenter.y,
                    headCenter.z + cameraZ
                );
                camera.lookAt(headCenter.x, headCenter.y, headCenter.z);

                // Force render
                camera.updateMatrixWorld();
                scene.updateMatrixWorld(true);
                await new Promise(resolve => requestAnimationFrame(resolve));
                renderer.render(scene, camera);
                await new Promise(resolve => requestAnimationFrame(resolve));

                // Create 64x64 canvas for head icon
                const headCanvas = document.createElement('canvas');
                headCanvas.width = 64;
                headCanvas.height = 64;
                const headCtx = headCanvas.getContext('2d');
                headCtx.drawImage(canvas, 0, 0, canvasWidth, canvasHeight, 0, 0, 64, 64);

                // Convert to blob
                headIconBlob = await new Promise(resolve => headCanvas.toBlob(resolve, 'image/png'));

                // Restore camera AND controls target back to original position
                camera.position.copy(originalPosition);
                camera.lookAt(target.x, target.y, target.z);

                if (controls) {
                    controls.target.set(target.x, target.y, target.z);
                    controls.update();
                }
            } catch (error) {
                console.error('Head icon generation error:', error);
                // Continue even if head icon fails
            }

            gifProgress.value = 'Uploading...';

            // Upload to server
            const formData = new FormData();
            formData.append('gif', blob, `model_${props.model.id}.gif`);
            if (headIconBlob) {
                formData.append('head_icon', headIconBlob, `model_${props.model.id}_head.png`);
            }

            try {
                const response = await fetch(route('models.saveThumbnail', props.model.id), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (data.success) {
                    gifProgress.value = 'Done!';
                    showSuccessNotification('Thumbnail generated successfully!');
                } else {
                    throw new Error(data.message || 'Failed to save thumbnail');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showErrorNotification('Failed to upload thumbnail: ' + error.message);
            }

            isGeneratingGif.value = false;
        });

        gif.on('progress', (p) => {
            gifProgress.value = `Encoding: ${Math.round(p * 100)}%`;
        });

        gif.render();

    } catch (error) {
        console.error('GIF generation error:', error);
        showErrorNotification('Failed to generate thumbnail: ' + error.message);
        isGeneratingGif.value = false;
    }
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

// Light update methods
const updateAmbientIntensity = (event) => {
    const intensity = parseFloat(event.target.value);
    lightSettings.value.ambient.intensity = intensity;
    if (viewer3D.value) {
        viewer3D.value.setAmbientLightIntensity(intensity);
    }
};

const updateAmbientColor = (event) => {
    const color = event.target.value;
    lightSettings.value.ambient.color = color;
    if (viewer3D.value) {
        viewer3D.value.setAmbientLightColor(color);
    }
};

const updateDirectionalIntensity = (event) => {
    const intensity = parseFloat(event.target.value);
    lightSettings.value.directional.intensity = intensity;
    if (viewer3D.value) {
        viewer3D.value.setDirectionalLightIntensity(intensity);
    }
};

const updateDirectionalColor = (event) => {
    const color = event.target.value;
    lightSettings.value.directional.color = color;
    if (viewer3D.value) {
        viewer3D.value.setDirectionalLightColor(color);
    }
};

const updateDirectionalPosition = (axis, event) => {
    const value = parseFloat(event.target.value);
    lightSettings.value.directional.position[axis] = value;
    if (viewer3D.value) {
        const pos = lightSettings.value.directional.position;
        viewer3D.value.setDirectionalLightPosition(pos.x, pos.y, pos.z);
    }
};

const updateBackIntensity = (event) => {
    const intensity = parseFloat(event.target.value);
    lightSettings.value.back.intensity = intensity;
    if (viewer3D.value) {
        viewer3D.value.setBackLightIntensity(intensity);
    }
};

const updateBackColor = (event) => {
    const color = event.target.value;
    lightSettings.value.back.color = color;
    if (viewer3D.value) {
        viewer3D.value.setBackLightColor(color);
    }
};

const updateBackPosition = (axis, event) => {
    const value = parseFloat(event.target.value);
    lightSettings.value.back.position[axis] = value;
    if (viewer3D.value) {
        const pos = lightSettings.value.back.position;
        viewer3D.value.setBackLightPosition(pos.x, pos.y, pos.z);
    }
};

// Model type badge helpers
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

// Get box background and border colors based on model type
const getBoxClass = (type) => {
    const classes = {
        'complete': 'bg-green-500/10 border-green-500/20',
        'skin': 'bg-blue-500/10 border-blue-500/20',
        'sound': 'bg-purple-500/10 border-purple-500/20',
        'mixed': 'bg-orange-500/10 border-orange-500/20'
    };
    return classes[type] || 'bg-white/5 border-white/10';
};
</script>

<template>
    <Head :title="model.name" />
    <div :class="isThumbnailMode ? 'w-screen h-screen' : 'min-h-screen'" :style="isThumbnailMode ? 'width: 100vw; height: 100vh; margin: 0; padding: 0;' : ''">
        <!-- Header Section with Fade Shadow -->
        <div v-if="!isThumbnailMode" class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Back Button -->
                <button @click="goBack" class="inline-flex items-center gap-2 text-gray-400 hover:text-white mb-4 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Models
                </button>
            </div>
        </div>

        <div :class="isThumbnailMode ? 'w-full h-full' : 'max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-4'" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                <!-- Success Message -->
                <div v-if="!isThumbnailMode && $page.props.flash?.success" class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-xl text-green-400">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="!isThumbnailMode && $page.props.flash?.error" class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-red-400">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </div>

                <div :class="isThumbnailMode ? 'w-full h-full' : 'grid grid-cols-1 xl:grid-cols-[832px_1fr] gap-8'" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                    <!-- Left Column: 3D Viewer / Preview -->
                    <div :class="isThumbnailMode ? 'w-full h-full' : ''" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
                        <!-- 3D Viewer Card -->
                        <div :class="[isThumbnailMode ? 'w-full h-full' : 'backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl border border-white/10 pt-4 px-4 pb-4 mb-8']" :style="isThumbnailMode ? 'width: 100%; height: 100%; margin: 0; padding: 0;' : ''">
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
                                :skin-pack-base-path="skinPackBasePath"
                                :skin-name="currentSkin"
                                :load-full-player="!isWeaponModel"
                                :is-weapon="isWeaponModel"
                                :auto-rotate="autoRotate"
                                :show-grid="!isThumbnailMode"
                                :enable-sounds="true"
                                @loaded="onViewerLoaded"
                                @error="onViewerError"
                                @animations-ready="onAnimationsReady"
                                @sounds-ready="onSoundsReady"
                                :class="isThumbnailMode ? '' : 'rounded-xl'"
                                :style="isThumbnailMode ? 'width: 100%; height: 100%;' : 'width: 800px; height: 800px;'"
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

                        <!-- Description -->
                        <div v-if="model.description" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl p-6 border border-white/10 mb-6">
                            <h3 class="text-lg font-bold text-white mb-3">Description</h3>
                            <p class="text-gray-300 whitespace-pre-line">{{ model.description }}</p>
                        </div>

                        <!-- Technical Details -->
                        <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl p-6 border border-white/10 mb-6">
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
                            <div class="text-gray-400">
                                <p v-if="model.author">
                                    Created by <span class="text-white font-semibold">{{ model.author }}</span>
                                    <span v-if="model.author_email" class="text-gray-500">
                                        ({{ model.author_email }})
                                    </span>
                                </p>
                                <div class="flex items-center gap-3 mt-2">
                                    <p v-if="model.base_model" class="text-sm text-gray-500">
                                        Based on
                                        <Link :href="route('models.index', { base_model: model.base_model })" class="text-blue-400 font-semibold hover:text-blue-300 transition-colors cursor-pointer">
                                            {{ model.base_model }}
                                        </Link>
                                    </p>
                                    <span v-if="model.model_type" :class="getModelTypeBadgeClass(model.model_type)" class="text-xs px-2 py-1 rounded font-semibold">
                                        {{ getModelTypeLabel(model.model_type) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- ANIMATION BUTTONS -->
                        <div v-if="animationsReady" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl p-4 border border-white/10 mb-6">
                            <h3 class="text-sm font-bold text-white mb-3">Animations</h3>

                            <!-- LEGS ANIMATIONS -->
                            <div class="mb-3">
                                <h4 class="text-[10px] font-bold text-blue-400 mb-1.5 uppercase tracking-wider">Legs ({{ Object.keys(availableAnimations.legs || {}).length }})</h4>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="(_, animName) in availableAnimations.legs"
                                        :key="animName"
                                        @click="oneShotAnimations.legs.includes(animName) ? playOneShotAnimation(animName, 'legs') : (viewer3D.playLegsAnimation(animName), currentLegsAnim = animName, viewer3D.getAnimationManager().playing = true, triggerAnimationSound(animName, 'legs'))"
                                        :class="[
                                            'px-2 py-1 rounded text-[10px] font-semibold transition-all border',
                                            currentLegsAnim === animName
                                                ? 'bg-blue-500/80 border-blue-400/80 text-white shadow-sm'
                                                : 'bg-blue-600/15 border-blue-500/25 text-blue-300 hover:bg-blue-600/30 hover:border-blue-500/40'
                                        ]">
                                        {{ animName.replace('LEGS_', '') }}
                                    </button>
                                </div>
                            </div>

                            <!-- TORSO ANIMATIONS -->
                            <div class="mb-3">
                                <h4 class="text-[10px] font-bold text-green-400 mb-1.5 uppercase tracking-wider">Torso ({{ Object.keys(availableAnimations.torso || {}).length }})</h4>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="(_, animName) in availableAnimations.torso"
                                        :key="animName"
                                        @click="oneShotAnimations.torso.includes(animName) ? playOneShotAnimation(animName, 'torso') : (viewer3D.playTorsoAnimation(animName), currentTorsoAnim = animName, viewer3D.getAnimationManager().playing = true, triggerAnimationSound(animName, 'torso'))"
                                        :class="[
                                            'px-2 py-1 rounded text-[10px] font-semibold transition-all border',
                                            currentTorsoAnim === animName
                                                ? 'bg-green-500/80 border-green-400/80 text-white shadow-sm'
                                                : 'bg-green-600/15 border-green-500/25 text-green-300 hover:bg-green-600/30 hover:border-green-500/40'
                                        ]">
                                        {{ animName.replace('TORSO_', '') }}
                                    </button>
                                </div>
                            </div>

                            <!-- BOTH ANIMATIONS -->
                            <div v-if="availableAnimations.both && Object.keys(availableAnimations.both || {}).length > 0" class="mb-2">
                                <h4 class="text-[10px] font-bold text-purple-400 mb-1.5 uppercase tracking-wider">Both ({{ Object.keys(availableAnimations.both || {}).length }})</h4>
                                <div class="flex flex-wrap gap-1 mb-2">
                                    <button
                                        v-for="(_, animName) in availableAnimations.both"
                                        :key="animName"
                                        @click="playOneShotAnimation(animName, 'both')"
                                        class="px-2 py-1 rounded text-[10px] font-semibold bg-purple-600/15 border border-purple-500/25 text-purple-300 hover:bg-purple-600/30 hover:border-purple-500/40 transition-all">
                                        {{ animName.replace('BOTH_', '') }}
                                    </button>
                                </div>
                                <div class="text-[10px] text-gray-400 italic">
                                    One-shot animations hold 2s, then return to idle
                                </div>
                            </div>
                        </div>

                        <!-- Light Controls Card -->
                        <div v-if="viewerLoaded && !isThumbnailMode" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl border border-white/10 p-6 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-bold text-gray-300">Lighting</h4>
                                <button @click="showLightControls = !showLightControls" class="px-3 py-1 rounded text-xs font-semibold transition-all bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/30">
                                    {{ showLightControls ? '💡 Hide' : '💡 Show' }}
                                </button>
                            </div>

                            <div v-if="showLightControls" class="space-y-4">
                                <!-- Ambient Light -->
                                <div class="border-t border-white/10 pt-4">
                                    <h5 class="text-xs font-bold text-gray-400 mb-3">AMBIENT LIGHT</h5>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Intensity:</span>
                                            <input
                                                type="range"
                                                min="0"
                                                max="2"
                                                step="0.1"
                                                :value="lightSettings.ambient.intensity"
                                                @input="updateAmbientIntensity"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.ambient.intensity.toFixed(1) }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Color:</span>
                                            <input
                                                type="color"
                                                :value="lightSettings.ambient.color"
                                                @input="updateAmbientColor"
                                                class="w-12 h-8 rounded cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400">{{ lightSettings.ambient.color }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Directional Light -->
                                <div class="border-t border-white/10 pt-4">
                                    <h5 class="text-xs font-bold text-gray-400 mb-3">MAIN LIGHT</h5>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Intensity:</span>
                                            <input
                                                type="range"
                                                min="0"
                                                max="3"
                                                step="0.1"
                                                :value="lightSettings.directional.intensity"
                                                @input="updateDirectionalIntensity"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.directional.intensity.toFixed(1) }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Color:</span>
                                            <input
                                                type="color"
                                                :value="lightSettings.directional.color"
                                                @input="updateDirectionalColor"
                                                class="w-12 h-8 rounded cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400">{{ lightSettings.directional.color }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position X:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.directional.position.x"
                                                @input="(e) => updateDirectionalPosition('x', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.directional.position.x }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position Y:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.directional.position.y"
                                                @input="(e) => updateDirectionalPosition('y', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.directional.position.y }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position Z:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.directional.position.z"
                                                @input="(e) => updateDirectionalPosition('z', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.directional.position.z }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Back Light -->
                                <div class="border-t border-white/10 pt-4">
                                    <h5 class="text-xs font-bold text-gray-400 mb-3">BACK LIGHT (Rim)</h5>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Intensity:</span>
                                            <input
                                                type="range"
                                                min="0"
                                                max="2"
                                                step="0.1"
                                                :value="lightSettings.back.intensity"
                                                @input="updateBackIntensity"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.back.intensity.toFixed(1) }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Color:</span>
                                            <input
                                                type="color"
                                                :value="lightSettings.back.color"
                                                @input="updateBackColor"
                                                class="w-12 h-8 rounded cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400">{{ lightSettings.back.color }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position X:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.back.position.x"
                                                @input="(e) => updateBackPosition('x', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.back.position.x }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position Y:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.back.position.y"
                                                @input="(e) => updateBackPosition('y', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.back.position.y }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-400 w-20">Position Z:</span>
                                            <input
                                                type="range"
                                                min="-200"
                                                max="200"
                                                step="10"
                                                :value="lightSettings.back.position.z"
                                                @input="(e) => updateBackPosition('z', e)"
                                                class="flex-1 h-2 bg-white/10 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span class="text-xs text-gray-400 w-12 text-right">{{ lightSettings.back.position.z }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Animation Controls Card -->
                        <div v-if="animationsReady && !isThumbnailMode" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl border border-white/10 p-6 mb-6">
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
                        <div v-if="soundsReady && !isThumbnailMode" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl border border-white/10 p-4 mb-6">
                            <div class="flex items-center justify-between mb-3">
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
                            <div class="mb-3">
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
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="soundName in availableSounds"
                                    :key="soundName"
                                    @click="playSound(soundName)"
                                    :disabled="!soundsEnabled"
                                    class="px-2 py-1 rounded text-[10px] font-semibold bg-purple-600/15 border border-purple-500/25 text-purple-300 hover:bg-purple-600/30 hover:border-purple-500/40 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                                    {{ soundName }}
                                </button>
                            </div>
                        </div>

                        <!-- Uploader Info (Hidden for id Software, Inc.) -->
                        <div v-if="model.user.name !== 'id Software, Inc.'" class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-xl p-6 border border-white/10 mb-6">
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
                        <div class="relative mb-4">
                            <button @click="downloadModel"
                                    :disabled="isBaseQ3Model"
                                    :class="[
                                        'w-full px-6 py-4 text-white font-black rounded-xl transition-all shadow-lg text-lg',
                                        isBaseQ3Model
                                            ? 'bg-gradient-to-r from-gray-500 to-gray-600 cursor-not-allowed opacity-60'
                                            : 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 hover:shadow-green-500/50'
                                    ]">
                                <span class="flex items-center justify-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    {{ isBaseQ3Model ? 'Included with Quake 3' : 'Download Model' }}
                                </span>
                            </button>
                            <p v-if="isBaseQ3Model" class="text-center text-gray-400 text-xs mt-2">
                                This model is part of the base Quake 3 installation
                            </p>
                        </div>

                        <!-- Generate Thumbnail Button (Owner or Admin) -->
                        <button
                            v-if="$page.props.auth?.user && ($page.props.auth.user.admin || $page.props.auth.user.id === model.user_id)"
                            @click="generateGifThumbnail"
                            :disabled="isGeneratingGif || !viewerLoaded"
                            class="w-full px-6 py-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-black rounded-xl hover:from-purple-600 hover:to-purple-700 transition-all shadow-lg hover:shadow-purple-500/50 text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="!isGeneratingGif" class="flex items-center justify-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                </svg>
                                Generate GIF Thumbnail
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ gifProgress }}
                            </span>
                        </button>

                        <p v-if="!isBaseQ3Model" class="text-center text-gray-500 text-sm mt-2">
                            Extract to your Quake 3 baseq3 directory
                        </p>

                        <!-- Performance Metrics Panel (only visible to admin neyoneit) -->
                        <div v-if="load_times && $page.props.auth?.user?.username === 'neyoneit'" class="mt-8">
                            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
                                <h3 class="text-lg font-bold text-white mb-4">⚡ Performance Metrics</h3>

                                <!-- Backend Timings -->
                                <div class="mb-6">
                                    <h4 class="text-sm font-semibold text-blue-400 mb-3">🖥️ Backend (Server)</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
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
                                        <div v-for="(time, key) in { frontend_mount: frontendTimings.mount_to_ready, dom_content_loaded: frontendTimings.dom_content_loaded, total_page_load: frontendTimings.total_page_load, model_3d_load: frontendTimings.model_load_time }" :key="key" class="bg-white/5 rounded-lg p-3 border border-white/10">
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
                    </div>
                </div>
            </div>

            <!-- Custom Notification Popup -->
            <Transition
                enter-active-class="transform transition ease-out duration-300"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transform transition ease-in duration-200"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0">
                <div v-if="showNotification" class="fixed top-8 right-8 z-50 max-w-md">
                    <div :class="[
                        'backdrop-blur-xl rounded-2xl border shadow-2xl p-6',
                        notificationType === 'success'
                            ? 'bg-green-500/20 border-green-500/30'
                            : 'bg-red-500/20 border-red-500/30'
                    ]">
                        <div class="flex items-center gap-4">
                            <!-- Icon -->
                            <div :class="[
                                'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center',
                                notificationType === 'success'
                                    ? 'bg-green-500/30'
                                    : 'bg-red-500/30'
                            ]">
                                <svg v-if="notificationType === 'success'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-green-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-red-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>

                            <!-- Message -->
                            <div class="flex-1">
                                <h3 :class="[
                                    'text-lg font-bold mb-1',
                                    notificationType === 'success' ? 'text-green-300' : 'text-red-300'
                                ]">
                                    {{ notificationType === 'success' ? 'Success!' : 'Error' }}
                                </h3>
                                <p class="text-white text-sm">{{ notificationMessage }}</p>
                            </div>

                            <!-- Close Button -->
                            <button @click="showNotification = false" class="flex-shrink-0 text-gray-400 hover:text-white transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
</template>
