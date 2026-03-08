<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted } from 'vue';
import ModelViewer from '@/Components/ModelViewer.vue';

const page = usePage();

const props = defineProps({
    models: Array,
});

const modelIdsInput = ref('');
const isProcessing = ref(false);
const stopRequested = ref(false);
const currentModelIndex = ref(-1);
const currentModel = ref(null);
const viewer3D = ref(null);
const viewerLoaded = ref(false);
const loadGeneration = ref(0); // Track which load cycle we're on to prevent race conditions
const results = ref([]);
const overallProgress = ref('');
const currentStatus = ref('');
const consecutiveErrors = ref(0);
const viewerError = ref(null);

// Auto-refresh after N models to reclaim WebGL contexts and prevent browser degradation
const MODELS_PER_CYCLE = 5;
const BATCH_STATE_KEY = 'batch_gif_state';

// Track completed IDs in localStorage to survive page refreshes
const STORAGE_KEY = 'batch_gif_completed_ids';
function getCompletedIds() {
    try {
        return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
    } catch { return new Set(); }
}
function markCompleted(id) {
    const completed = getCompletedIds();
    completed.add(id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...completed]));
}
function clearCompleted() {
    localStorage.removeItem(STORAGE_KEY);
}
const completedCount = ref(getCompletedIds().size);

// Track failed IDs in localStorage to survive auto-refreshes
const FAILED_KEY = 'batch_gif_failed_ids';
function getFailedIds() {
    try {
        return JSON.parse(localStorage.getItem(FAILED_KEY) || '[]');
    } catch { return []; }
}
function markFailed(id, name, message) {
    const failed = getFailedIds();
    // Don't duplicate
    if (!failed.find(f => f.id === id)) {
        failed.push({ id, name, message });
        localStorage.setItem(FAILED_KEY, JSON.stringify(failed));
    }
}
function clearFailed() {
    localStorage.removeItem(FAILED_KEY);
}
const failedList = ref(getFailedIds());

// Save/restore batch state for auto-refresh continuation
function saveBatchState(ids, startIndex) {
    localStorage.setItem(BATCH_STATE_KEY, JSON.stringify({ ids, startIndex }));
}
function loadBatchState() {
    try {
        const state = JSON.parse(localStorage.getItem(BATCH_STATE_KEY));
        if (state && Array.isArray(state.ids) && typeof state.startIndex === 'number') {
            return state;
        }
    } catch { /* ignore */ }
    return null;
}
function clearBatchState() {
    localStorage.removeItem(BATCH_STATE_KEY);
}

// Cache GIF module to avoid re-importing each iteration
let cachedGifModule = null;
async function getGifModule() {
    if (!cachedGifModule) {
        cachedGifModule = (await import('gif.js')).default;
    }
    return cachedGifModule;
}

// Auto-resume on page load if batch state exists
onMounted(() => {
    const state = loadBatchState();
    if (state) {
        modelIdsInput.value = state.ids.join(', ');
        overallProgress.value = `Auto-resuming batch from model ${state.startIndex + 1}/${state.ids.length}...`;
        nextTick(() => startBatch(state.startIndex));
    }
});

// Parse model IDs from input
const selectedModelIds = computed(() => {
    return modelIdsInput.value
        .split(/[,\s]+/)
        .map(id => parseInt(id.trim()))
        .filter(id => !isNaN(id) && id > 0);
});

// Find model data by ID
function findModel(id) {
    return props.models.find(m => m.id === id);
}

// Compute model file path (same logic as Show.vue)
function getModelFilePath(model) {
    if (model.category === 'weapon') {
        if (!model.file_path) return null;
        const mainFile = model.main_file || `${model.base_model || model.name}.md3`;
        if (model.file_path.startsWith('baseq3/')) {
            return `/${model.file_path}/${mainFile}`;
        }
        return `/storage/${model.file_path}/${mainFile}`;
    }

    if (!model.file_path) return null;
    if (model.file_path.startsWith('baseq3/')) {
        return `/${model.file_path}/head.md3`;
    }
    if (model.file_path.includes('/models/players/') || model.file_path.includes('/Models/Players/')) {
        return `/storage/${model.file_path}/head.md3`;
    }
    const modelName = model.base_model || model.name;
    return `/storage/${model.file_path}/models/players/${modelName}/head.md3`;
}

// Get first available skin name for model
function getFirstSkin(model) {
    const skins = model.available_skins;
    if (typeof skins === 'string') {
        try {
            const parsed = JSON.parse(skins);
            if (Array.isArray(parsed) && parsed.length > 0) return parsed[0];
        } catch (e) { /* ignore */ }
    }
    if (Array.isArray(skins) && skins.length > 0) return skins[0];
    return 'default';
}

// Compute skin file path
function getSkinFilePath(model) {
    if (model.category === 'weapon') return null;
    if (!model.file_path) return null;

    const skinName = getFirstSkin(model);
    if (model.file_path.startsWith('baseq3/')) {
        return `/${model.file_path}/head_${skinName}.skin`;
    }
    const modelName = model.base_model || model.name;
    if (model.file_path.includes('/models/players/') || model.file_path.includes('/Models/Players/')) {
        return `/storage/${model.file_path}/head_${skinName}.skin`;
    }
    return `/storage/${model.file_path}/models/players/${modelName}/head_${skinName}.skin`;
}

// Get skin pack base path for mixed/skin packs
function getSkinPackBasePath(model) {
    const baseModelData = getBaseModelData(model);
    if (!baseModelData) return null;
    if (model.file_path && !model.file_path.startsWith('baseq3/')) {
        if (model.file_path.includes('/models/players/') || model.file_path.includes('/Models/Players/')) {
            return `/storage/${model.file_path}`;
        }
        const modelName = model.base_model || model.name;
        return `/storage/${model.file_path}/models/players/${modelName}`;
    }
    return null;
}

// Get base model data for skin/mixed packs
function getBaseModelData(model) {
    if (model.model_type === 'complete' || !model.base_model) return null;
    if (model.base_model_file_path) {
        return { name: model.base_model, file_path: model.base_model_file_path };
    }
    // Try to find base model in the models list (case-insensitive, base_model is lowercase)
    const baseName = model.base_model.toLowerCase();
    const base = props.models.find(m => m.name.toLowerCase() === baseName && m.model_type === 'complete');
    if (base) {
        return { name: base.name, file_path: base.file_path };
    }
    return null;
}

// --- Shared GIF helpers ---
function getViewerComponents() {
    const viewer = viewer3D.value;
    if (!viewer) throw new Error('Viewer not available');
    const renderer = viewer.getRenderer();
    const scene = viewer.getScene();
    const camera = viewer.getCamera();
    if (!renderer || !scene || !camera) throw new Error('Could not access 3D viewer components');
    return { viewer, renderer, scene, camera };
}

function captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight) {
    renderer.render(scene, camera);
    tempCtx.fillStyle = '#000000';
    tempCtx.fillRect(0, 0, gifWidth, gifHeight);
    tempCtx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, gifWidth, gifHeight);
}

async function createGifEncoder(gifWidth, gifHeight) {
    const GIF = await getGifModule();
    return new GIF({
        workers: 4,
        quality: 10,
        width: gifWidth,
        height: gifHeight,
        workerScript: '/gif.worker.js'
    });
}

async function finalizeGif(gif) {
    const blob = await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('GIF encoding timed out')), 60000);
        gif.on('finished', (b) => { clearTimeout(timeout); resolve(b); });
        gif.render();
    });
    try { gif.abort(); if (gif.freeWorkers) gif.freeWorkers.forEach(w => w.terminate()); } catch (e) { /* ignore */ }
    return blob;
}

// --- ROTATE GIF: camera orbits around static model (36 frames) ---
async function generateRotateGif() {
    const { viewer, renderer, scene, camera } = getViewerComponents();
    const canvas = renderer.domElement;
    const gifWidth = 300, gifHeight = 300;

    const controls = viewer.getControls();
    const originalPosition = camera.position.clone();
    const originalTarget = controls?.target ? controls.target.clone() : { x: 0, y: 0, z: 0 };

    const modelObj = viewer.getModel();
    const pivot = viewer.getModelCenter();
    const target = { x: modelObj?.position.x || 0, y: pivot.y, z: modelObj?.position.z || 0 };

    const dx = originalPosition.x - target.x;
    const dy = originalPosition.y - target.y;
    const dz = originalPosition.z - target.z;
    const radius = Math.sqrt(dx * dx + dz * dz);
    const height = dy;
    const startAngle = Math.atan2(dx, dz);

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = gifWidth; tempCanvas.height = gifHeight;
    const tempCtx = tempCanvas.getContext('2d');

    const gif = await createGifEncoder(gifWidth, gifHeight);
    const numFrames = 36;

    for (let i = 0; i < numFrames; i++) {
        currentStatus.value = `[Rotate] Capturing frame ${i + 1}/${numFrames}...`;
        const angle = startAngle + (i * (Math.PI * 2) / numFrames);
        camera.position.x = target.x + Math.sin(angle) * radius;
        camera.position.z = target.z + Math.cos(angle) * radius;
        camera.position.y = target.y + height;
        camera.lookAt(target.x, target.y, target.z);
        camera.updateMatrixWorld();
        scene.updateMatrixWorld(true);

        await new Promise(resolve => requestAnimationFrame(resolve));
        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: 83 });
        await new Promise(resolve => setTimeout(resolve, 30));
    }

    // Restore camera
    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    currentStatus.value = 'Encoding rotate GIF...';
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

// --- ANIMATION GIF: fixed camera, model plays animation ---
async function generateAnimationGif(legsAnim, torsoAnim, label, isLoop = false) {
    const { viewer, renderer, scene, camera } = getViewerComponents();
    const animManager = viewer.getAnimationManager();
    if (!animManager) throw new Error('No animation manager available');

    const anims = viewer.getAvailableAnimations();
    const legsData = anims.legs?.[legsAnim] || anims.both?.[legsAnim];
    const torsoData = anims.torso?.[torsoAnim] || anims.both?.[torsoAnim];

    if (!legsData && !torsoData) throw new Error(`Animations ${legsAnim}/${torsoAnim} not found`);

    let animFps, numFrames;
    if (isLoop) {
        // LOOPING (idle): use legs native fps, capture exactly numFrames.
        // No intro skip, no loopingFrames tricks — just like the real-time viewer.
        animFps = legsData?.fps || torsoData?.fps || 15;
        numFrames = legsData?.numFrames || torsoData?.numFrames || 1;
    } else {
        // ONE-SHOT (gesture): use max fps so no frames from either part are skipped.
        animFps = Math.max(legsData?.fps || 0, torsoData?.fps || 0) || 15;
        const legsDuration = legsData ? legsData.numFrames / legsData.fps : 0;
        const torsoDuration = torsoData ? torsoData.numFrames / torsoData.fps : 0;
        numFrames = Math.max(Math.ceil(Math.max(legsDuration, torsoDuration) * animFps), 2);
    }
    const frameDelay = Math.round(1000 / animFps);

    console.log(`[${label}] ${isLoop ? 'LOOP' : 'ONE-SHOT'} animFps=${animFps}, delay=${frameDelay}ms, gifFrames=${numFrames}`);

    const canvas = renderer.domElement;
    const gifWidth = 300, gifHeight = 300;

    const controls = viewer.getControls();
    const originalPosition = camera.position.clone();
    const originalTarget = controls?.target ? controls.target.clone() : { x: 0, y: 0, z: 0 };

    const modelObj = viewer.getModel();
    const pivot = viewer.getModelCenter();
    const target = { x: modelObj?.position.x || 0, y: pivot.y, z: modelObj?.position.z || 0 };

    const dx = originalPosition.x - target.x;
    const dz = originalPosition.z - target.z;
    const radius = Math.sqrt(dx * dx + dz * dz);

    // Front view camera
    camera.position.x = target.x;
    camera.position.z = target.z + radius;
    camera.position.y = target.y + (originalPosition.y - target.y) * 0.5;
    camera.lookAt(target.x, target.y, target.z);
    camera.updateMatrixWorld();

    // Pause the viewer's own animation loop so we control animation timing exclusively
    viewer.pauseAnimationLoop();

    // Start animation from beginning
    animManager.stop();
    if (legsData) animManager.playLegsAnimation(legsAnim);
    if (torsoData) animManager.playTorsoAnimation(torsoAnim);
    animManager.playing = true;
    animManager.legsTime = 0;
    animManager.torsoTime = 0;
    animManager.legsFrame = 0;
    animManager.torsoFrame = 0;

    // Initialize mesh vertices to frame 0 — playLegsAnimation/playTorsoAnimation
    // only set internal state but don't update mesh geometry. Without this,
    // frame 0 in the GIF shows stale vertex data from the previous animation state.
    animManager.update(0);

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = gifWidth; tempCanvas.height = gifHeight;
    const tempCtx = tempCanvas.getContext('2d');

    const gif = await createGifEncoder(gifWidth, gifHeight);
    const dt = 1 / animFps;

    for (let i = 0; i < numFrames; i++) {
        currentStatus.value = `[${label}] Capturing frame ${i + 1}/${numFrames} (${animFps}fps, delay=${frameDelay}ms)...`;

        if (i > 0) {
            animManager.update(dt);
        }
        scene.updateMatrixWorld(true);

        await new Promise(resolve => requestAnimationFrame(resolve));
        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: frameDelay });
        await new Promise(resolve => setTimeout(resolve, 20));
    }

    // Restore camera, resume viewer animation loop
    animManager.stop();
    animManager.resetToIdle();
    viewer.resumeAnimationLoop();
    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    currentStatus.value = `Encoding ${label} GIF...`;
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

// --- HEAD ICON (64x64 PNG) ---
async function generateHeadIcon() {
    const { viewer, renderer, scene, camera } = getViewerComponents();
    const canvas = renderer.domElement;
    const controls = viewer.getControls();
    const originalPosition = camera.position.clone();
    const originalTarget = controls?.target ? controls.target.clone() : { x: 0, y: 0, z: 0 };

    let headIconBlob = null;
    try {
        const THREE = await import('three');
        const model = viewer.getModel();
        if (model) {
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
            if (headMesh) {
                const headBox = new THREE.Box3().setFromObject(headMesh);
                const headCenter = headBox.getCenter(new THREE.Vector3());
                const headSize = headBox.getSize(new THREE.Vector3());
                const maxDim = Math.max(headSize.x, headSize.y, headSize.z);
                const fov = camera.fov * (Math.PI / 180);
                let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 1.5;

                camera.position.set(headCenter.x, headCenter.y, headCenter.z + cameraZ);
                camera.lookAt(headCenter.x, headCenter.y, headCenter.z);
                camera.updateMatrixWorld();
                scene.updateMatrixWorld(true);
                await new Promise(resolve => requestAnimationFrame(resolve));
                renderer.render(scene, camera);
                await new Promise(resolve => requestAnimationFrame(resolve));

                const headCanvas = document.createElement('canvas');
                headCanvas.width = 64; headCanvas.height = 64;
                const headCtx = headCanvas.getContext('2d');
                headCtx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, 64, 64);
                headIconBlob = await new Promise(resolve => headCanvas.toBlob(resolve, 'image/png'));
                headCanvas.width = 0; headCanvas.height = 0;
            }
        }
    } catch (e) {
        console.error('Head icon error:', e);
    }

    // Restore camera
    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    return headIconBlob;
}

// --- Main: generate all GIF variants for a model ---
async function generateAllGifsForModel() {
    // 1. Rotate GIF
    currentStatus.value = 'Generating rotate GIF...';
    const rotateBlob = await generateRotateGif();

    // 2. Idle GIF (LEGS_IDLE + TORSO_STAND)
    let idleBlob = null;
    try {
        currentStatus.value = 'Generating idle GIF...';
        idleBlob = await generateAnimationGif('LEGS_IDLE', 'TORSO_STAND', 'Idle', true);
    } catch (e) {
        console.warn('Idle GIF failed (no animation?):', e.message);
    }

    // 3. Gesture GIF (LEGS_IDLE + TORSO_GESTURE)
    let gestureBlob = null;
    try {
        currentStatus.value = 'Generating gesture GIF...';
        gestureBlob = await generateAnimationGif('LEGS_IDLE', 'TORSO_GESTURE', 'Gesture');
    } catch (e) {
        console.warn('Gesture GIF failed (no animation?):', e.message);
    }

    // 4. Head icon
    currentStatus.value = 'Generating head icon...';
    const headIconBlob = await generateHeadIcon();

    return { rotateBlob, idleBlob, gestureBlob, headIconBlob };
}

// Upload all GIF variants for a model
async function uploadThumbnails(modelId, { rotateBlob, idleBlob, gestureBlob, headIconBlob }) {
    const formData = new FormData();
    if (rotateBlob) formData.append('rotate_gif', rotateBlob, `model_${modelId}_rotate.gif`);
    if (idleBlob) formData.append('idle_gif', idleBlob, `model_${modelId}_idle.gif`);
    if (gestureBlob) formData.append('gesture_gif', gestureBlob, `model_${modelId}_gesture.gif`);
    if (headIconBlob) formData.append('head_icon', headIconBlob, `model_${modelId}_head.png`);

    const response = await fetch(route('models.saveThumbnail', modelId), {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const data = await response.json();
    if (!data.success) {
        throw new Error(data.message || 'Upload failed');
    }
    return data;
}

// Wait for all pending textures to finish loading (max 10s)
function waitForTextures() {
    return new Promise((resolve) => {
        const maxWait = setTimeout(resolve, 10000);
        const check = setInterval(() => {
            const pending = viewer3D.value?.getPendingTextures?.() ?? 0;
            if (pending <= 0) {
                clearInterval(check);
                clearTimeout(maxWait);
                // Small extra wait for GPU upload
                setTimeout(resolve, 300);
            }
        }, 100);
    });
}

// Wait for model viewer to emit loaded or error (with generation check to prevent race conditions)
function waitForViewerLoaded(expectedGeneration) {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            clearInterval(interval);
            reject(new Error('Model load timed out (15s)'));
        }, 15000);

        const interval = setInterval(() => {
            // Abort if generation changed (means a different model started loading)
            if (loadGeneration.value !== expectedGeneration) {
                clearInterval(interval);
                clearTimeout(timeout);
                reject(new Error('Load generation mismatch - stale event'));
                return;
            }
            // Detect error immediately instead of waiting for timeout
            if (viewerError.value) {
                clearInterval(interval);
                clearTimeout(timeout);
                const err = viewerError.value;
                viewerError.value = null;
                reject(new Error(`Model load failed: ${err}`));
                return;
            }
            if (viewerLoaded.value) {
                clearInterval(interval);
                clearTimeout(timeout);
                // Wait for all pending textures to finish loading
                waitForTextures().then(resolve);
            }
        }, 100);
    });
}

const onViewerLoaded = () => {
    viewerLoaded.value = true;
};

const onViewerError = (err) => {
    console.error('Viewer error:', err);
    viewerError.value = err?.message || String(err);
    viewerLoaded.value = false;
};

// Force regenerate flag - skips "already completed" check
const forceMode = ref(false);

// Main batch process
async function startBatch(startFrom = 0) {
    const ids = selectedModelIds.value;
    if (ids.length === 0) return;

    isProcessing.value = true;
    stopRequested.value = false;
    consecutiveErrors.value = 0;
    if (startFrom === 0) {
        results.value = [];
    }
    // Clear failed entries only for models we're about to process (from startFrom onwards)
    const idsToProcess = new Set(ids.slice(startFrom));
    const previousFailed = getFailedIds().filter(f => !idsToProcess.has(f.id));
    localStorage.setItem(FAILED_KEY, JSON.stringify(previousFailed));
    failedList.value = previousFailed;
    let processedThisCycle = 0;

    for (let i = startFrom; i < ids.length; i++) {
        // Check if stop was requested
        if (stopRequested.value) {
            clearBatchState();
            overallProgress.value = `Stopped at ${i}/${ids.length}`;
            break;
        }

        // Auto-refresh to reclaim WebGL contexts after N models
        if (processedThisCycle >= MODELS_PER_CYCLE) {
            currentStatus.value = `Auto-refreshing browser to free memory (processed ${processedThisCycle} this cycle)...`;
            saveBatchState(ids, i);
            await new Promise(resolve => setTimeout(resolve, 500));
            window.location.reload();
            return; // Page will reload and auto-resume from onMounted
        }

        const modelId = ids[i];
        const modelData = findModel(modelId);

        currentModelIndex.value = i;
        overallProgress.value = `Processing ${i + 1}/${ids.length}: Model #${modelId}`;

        // Skip already completed models (unless force mode)
        if (!forceMode.value && getCompletedIds().has(modelId)) {
            results.value.push({ id: modelId, name: findModel(modelId)?.name || `#${modelId}`, status: 'skipped', message: 'Already done' });
            continue;
        }

        if (!modelData) {
            markFailed(modelId, `#${modelId}`, 'Model not found');
            results.value.push({ id: modelId, name: `#${modelId}`, status: 'error', message: 'Model not found' });
            continue;
        }

        const modelPath = getModelFilePath(modelData);
        if (!modelPath) {
            markFailed(modelId, modelData.name, 'No file path');
            results.value.push({ id: modelId, name: modelData.name, status: 'error', message: 'No file path' });
            continue;
        }

        // Cooldown after consecutive errors to let browser recover
        if (consecutiveErrors.value >= 3) {
            currentStatus.value = `Cooldown (${consecutiveErrors.value} consecutive errors)... waiting 10s`;
            await new Promise(resolve => setTimeout(resolve, 10000));
            consecutiveErrors.value = 0;
        }

        // Unmount old viewer, mount new one
        currentStatus.value = 'Loading model...';
        viewerLoaded.value = false;
        viewerError.value = null;
        loadGeneration.value++;
        const thisGeneration = loadGeneration.value;
        currentModel.value = null;
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 200));

        currentModel.value = modelData;
        await nextTick();

        try {
            // Wait for model to load (with generation check)
            await waitForViewerLoaded(thisGeneration);

            // Generate all GIF variants (rotate, idle, gesture) + head icon
            currentStatus.value = 'Generating GIFs...';
            const allGifs = await generateAllGifsForModel();

            // Upload all variants
            currentStatus.value = 'Uploading...';
            await uploadThumbnails(modelId, allGifs);

            markCompleted(modelId);
            completedCount.value = getCompletedIds().size;
            consecutiveErrors.value = 0;
            processedThisCycle++;
            results.value.push({ id: modelId, name: modelData.name, status: 'success', message: 'Done' });
        } catch (err) {
            console.error(`Error processing model ${modelId}:`, err);
            consecutiveErrors.value++;
            processedThisCycle++;
            markFailed(modelId, modelData.name, err.message);
            results.value.push({ id: modelId, name: modelData.name, status: 'error', message: err.message });
        }

        // Wait between models - longer after errors to let browser recover
        const waitTime = consecutiveErrors.value > 0 ? 3000 : 300;
        await new Promise(resolve => setTimeout(resolve, waitTime));
    }

    // Batch complete (not interrupted by auto-refresh)
    clearBatchState();
    failedList.value = getFailedIds();
    currentModel.value = null;
    isProcessing.value = false;
    stopRequested.value = false;
    currentModelIndex.value = -1;
    const successCount = results.value.filter(r => r.status === 'success').length;
    const skippedCount = results.value.filter(r => r.status === 'skipped').length;
    overallProgress.value = `Finished! ${successCount} generated, ${skippedCount} skipped, ${ids.length - successCount - skippedCount} failed`;
    currentStatus.value = '';
}

function stopBatch() {
    stopRequested.value = true;
    clearBatchState();
    currentStatus.value = 'Stopping after current model...';
}

async function startBatchForce() {
    forceMode.value = true;
    await startBatch(0);
    forceMode.value = false;
}

// Quick-fill helpers
function fillAllWithoutGif() {
    const ids = props.models.filter(m => !m.idle_gif || !m.rotate_gif || !m.gesture_gif).map(m => m.id);
    modelIdsInput.value = ids.join(', ');
}

function fillAll() {
    modelIdsInput.value = props.models.map(m => m.id).join(', ');
}

function fillFailed() {
    const failed = getFailedIds();
    modelIdsInput.value = failed.map(f => f.id).join(', ');
}

async function retryFailed() {
    const failed = getFailedIds();
    if (failed.length === 0) return;
    modelIdsInput.value = failed.map(f => f.id).join(', ');
    await nextTick();
    startBatch(0);
}

async function startAll() {
    modelIdsInput.value = props.models.map(m => m.id).join(', ');
    await nextTick();
    startBatch(0);
}

async function startAlreadyGenerated() {
    modelIdsInput.value = props.models.filter(m => m.idle_gif || m.rotate_gif || m.gesture_gif || m.thumbnail).map(m => m.id).join(', ');
    await nextTick();
    startBatch(0);
}
</script>

<template>
    <Head title="Batch Generate GIF Thumbnails" />

    <div class="min-h-screen bg-gray-900 text-white p-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">Batch Generate GIF Thumbnails</h1>

            <!-- Input -->
            <div class="bg-gray-800 rounded-lg p-6 mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    Model IDs (comma or space separated)
                </label>
                <textarea
                    v-model="modelIdsInput"
                    :disabled="isProcessing"
                    class="w-full bg-gray-700 text-white rounded px-4 py-2 h-24 font-mono text-sm"
                    placeholder="e.g. 3529, 3544, 3550"
                ></textarea>

                <div class="flex flex-wrap gap-3 mt-3">
                    <button
                        v-if="!isProcessing"
                        @click="startBatch(0)"
                        :disabled="selectedModelIds.length === 0"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 rounded font-medium"
                    >
                        Generate ({{ selectedModelIds.length }} models)
                    </button>
                    <button
                        v-if="!isProcessing"
                        @click="startBatchForce"
                        :disabled="selectedModelIds.length === 0"
                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 rounded font-medium"
                    >
                        Force Regenerate ({{ selectedModelIds.length }})
                    </button>
                    <button
                        v-else
                        @click="stopBatch"
                        :disabled="stopRequested"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 rounded font-medium"
                    >
                        {{ stopRequested ? 'Stopping...' : 'Stop Batch' }}
                    </button>
                    <button
                        @click="fillAllWithoutGif"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 rounded text-sm"
                    >
                        Fill: Missing GIFs ({{ models.filter(m => !m.idle_gif || !m.rotate_gif || !m.gesture_gif).length }})
                    </button>
                    <button
                        @click="fillAll"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 rounded text-sm"
                    >
                        Fill: All ({{ models.length }})
                    </button>
                    <button
                        v-if="failedList.length > 0"
                        @click="fillFailed"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-700 rounded text-sm font-medium"
                    >
                        Fill: Failed ({{ failedList.length }})
                    </button>
                    <button
                        v-if="failedList.length > 0"
                        @click="retryFailed"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-700 rounded text-sm font-medium"
                    >
                        Retry Failed ({{ failedList.length }})
                    </button>
                    <button
                        v-if="completedCount > 0 || failedList.length > 0"
                        @click="clearCompleted(); clearFailed(); completedCount = 0; failedList = [];"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 rounded text-sm"
                    >
                        Clear progress ({{ completedCount }} done, {{ failedList.length }} failed)
                    </button>
                    <button
                        @click="startAlreadyGenerated"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-700 rounded text-sm font-medium"
                    >
                        Regenerate existing ({{ models.filter(m => m.idle_gif || m.rotate_gif || m.thumbnail).length }})
                    </button>
                    <button
                        @click="startAll"
                        :disabled="isProcessing"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-700 rounded text-sm font-medium"
                    >
                        Regenerate ALL ({{ models.length }})
                    </button>
                </div>

                <!-- Parsed IDs preview -->
                <details v-if="selectedModelIds.length > 0" class="mt-3 text-sm text-gray-400">
                    <summary class="cursor-pointer hover:text-gray-300">Parsed IDs ({{ selectedModelIds.length }})</summary>
                    <div class="mt-1 max-h-32 overflow-y-auto">{{ selectedModelIds.join(', ') }}</div>
                </details>
            </div>

            <!-- Progress -->
            <div v-if="overallProgress" class="bg-gray-800 rounded-lg p-6 mb-6">
                <div class="text-lg font-medium">{{ overallProgress }}</div>
                <div v-if="currentStatus" class="text-sm text-gray-400 mt-1">{{ currentStatus }}</div>
            </div>

            <!-- Render preview -->
            <div v-if="currentModel" class="mb-6">
                <div class="text-sm text-gray-400 mb-2">
                    Rendering: {{ currentModel.name }} (#{{ currentModel.id }})
                </div>
                <div style="width: 800px; height: 800px;">
                    <div ref="viewerContainer" style="width: 800px; height: 800px;">
                        <ModelViewer
                            ref="viewer3D"
                            :model-path="getModelFilePath(currentModel)"
                            :model-id="currentModel.id"
                            :skin-path="getSkinFilePath(currentModel)"
                            :skin-pack-base-path="getSkinPackBasePath(currentModel)"
                            :skin-name="getFirstSkin(currentModel)"
                            :load-full-player="currentModel.category !== 'weapon'"
                            :is-weapon="currentModel.category === 'weapon'"
                            :auto-rotate="false"
                            :show-grid="false"
                            :enable-sounds="false"
                            :thumbnail-mode="true"
                            :base-model-name="currentModel.model_type !== 'complete' ? currentModel.base_model : null"
                            :base-model-file-path="getBaseModelData(currentModel)?.file_path || null"
                            @loaded="onViewerLoaded"
                            @error="onViewerError"
                        />
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div v-if="results.length > 0" class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-lg font-medium mb-4">Results</h2>
                <div class="space-y-2">
                    <div
                        v-for="result in results"
                        :key="result.id"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span
                            :class="{
                                'text-green-400': result.status === 'success',
                                'text-red-400': result.status === 'error',
                                'text-gray-500': result.status === 'skipped'
                            }"
                            class="font-mono w-10"
                        >
                            {{ result.status === 'success' ? 'OK' : result.status === 'skipped' ? 'SKIP' : 'FAIL' }}
                        </span>
                        <span class="text-gray-300">#{{ result.id }}</span>
                        <span class="text-white">{{ result.name }}</span>
                        <span v-if="result.status !== 'success'" class="text-xs" :class="result.status === 'error' ? 'text-red-400' : 'text-gray-500'">
                            {{ result.message }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Persistent failed models list -->
            <div v-if="failedList.length > 0 && !isProcessing" class="bg-red-900/20 border border-red-500/30 rounded-lg p-6 mb-6">
                <h2 class="text-lg font-medium text-red-400 mb-3">Failed models ({{ failedList.length }})</h2>
                <div class="space-y-1 max-h-64 overflow-y-auto">
                    <div v-for="f in failedList" :key="f.id" class="flex items-center gap-3 text-sm">
                        <span class="text-red-400 font-mono w-16">#{{ f.id }}</span>
                        <span class="text-white">{{ f.name }}</span>
                        <span class="text-xs text-red-400/70">{{ f.message }}</span>
                    </div>
                </div>
            </div>

            <!-- Model list reference -->
            <details class="mt-6">
                <summary class="cursor-pointer text-gray-400 hover:text-gray-300 text-sm">
                    Show all models ({{ models.length }})
                </summary>
                <div class="mt-2 bg-gray-800 rounded-lg p-4 max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400">
                            <tr>
                                <th class="text-left py-1 px-2">ID</th>
                                <th class="text-left py-1 px-2">Name</th>
                                <th class="text-left py-1 px-2">Category</th>
                                <th class="text-left py-1 px-2">Idle</th>
                                <th class="text-left py-1 px-2">Rotate</th>
                                <th class="text-left py-1 px-2">Gesture</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in models" :key="m.id" class="border-t border-gray-700">
                                <td class="py-1 px-2 font-mono text-gray-400">{{ m.id }}</td>
                                <td class="py-1 px-2">{{ m.name }}</td>
                                <td class="py-1 px-2 text-gray-400">{{ m.category }}</td>
                                <td class="py-1 px-2">
                                    <span v-if="m.idle_gif" class="text-green-400">Y</span>
                                    <span v-else class="text-red-400">N</span>
                                </td>
                                <td class="py-1 px-2">
                                    <span v-if="m.rotate_gif || m.thumbnail" class="text-green-400">Y</span>
                                    <span v-else class="text-red-400">N</span>
                                </td>
                                <td class="py-1 px-2">
                                    <span v-if="m.gesture_gif" class="text-green-400">Y</span>
                                    <span v-else class="text-red-400">N</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

    </div>
</template>
