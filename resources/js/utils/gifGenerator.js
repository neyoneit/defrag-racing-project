/**
 * GIF/Thumbnail generation utilities for model upload and batch generation.
 * Extracted from BatchGenerateGifs.vue for reuse in Create.vue upload flow.
 */

const DEBUG = true;

let cachedGifModule = null;
async function getGifModule() {
    if (!cachedGifModule) {
        cachedGifModule = (await import('gif.js')).default;
    }
    return cachedGifModule;
}

function getViewerComponents(viewerRef) {
    const viewer = viewerRef.value || viewerRef;
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

/**
 * Generate a rotate GIF (camera orbits around static model, 36 frames).
 */
export async function generateRotateGif(viewerRef, onStatus) {
    const { viewer, renderer, scene, camera } = getViewerComponents(viewerRef);
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
    let shaderTime = 0;

    for (let i = 0; i < numFrames; i++) {
        onStatus?.(`[Rotate] Frame ${i + 1}/${numFrames}`);
        const angle = startAngle + (i * (Math.PI * 2) / numFrames);
        camera.position.x = target.x + Math.sin(angle) * radius;
        camera.position.z = target.z + Math.cos(angle) * radius;
        camera.position.y = target.y + height;
        camera.lookAt(target.x, target.y, target.z);
        camera.updateMatrixWorld();
        shaderTime += 0.083; // ~83ms per frame (12fps rotate)
        viewer.updateShaderAnimations?.(shaderTime);
        scene.updateMatrixWorld(true);

        await new Promise(resolve => requestAnimationFrame(resolve));
        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: 83 });
        await new Promise(resolve => setTimeout(resolve, 30));
    }

    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    onStatus?.('Encoding rotate GIF...');
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

/**
 * Generate an animation GIF (fixed camera, model plays animation).
 */
export async function generateAnimationGif(viewerRef, legsAnim, torsoAnim, label, isLoop = false, onStatus) {
    const { viewer, renderer, scene, camera } = getViewerComponents(viewerRef);
    const animManager = viewer.getAnimationManager();
    if (!animManager) throw new Error('No animation manager available');

    const anims = viewer.getAvailableAnimations();
    const legsData = anims.legs?.[legsAnim] || anims.both?.[legsAnim];
    const torsoData = anims.torso?.[torsoAnim] || anims.both?.[torsoAnim];

    if (!legsData && !torsoData) throw new Error(`Animations ${legsAnim}/${torsoAnim} not found`);

    let animFps, numFrames;
    const MAX_GIF_FRAMES = 300;
    if (isLoop) {
        animFps = legsData?.fps || torsoData?.fps || 15;
        const baseFrames = legsData?.numFrames || torsoData?.numFrames || 1;
        const minFrames = Math.ceil(3 * animFps);
        const numCycles = Math.max(1, Math.ceil(minFrames / baseFrames));
        numFrames = Math.min(baseFrames * numCycles, MAX_GIF_FRAMES);
    } else {
        animFps = Math.max(legsData?.fps || 0, torsoData?.fps || 0) || 15;
        const legsDuration = legsData ? legsData.numFrames / legsData.fps : 0;
        const torsoDuration = torsoData ? torsoData.numFrames / torsoData.fps : 0;
        numFrames = Math.min(Math.max(Math.ceil(Math.max(legsDuration, torsoDuration) * animFps), 2), MAX_GIF_FRAMES);
    }
    const frameDelay = Math.round(1000 / animFps);

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

    viewer.pauseAnimationLoop();

    animManager.stop();
    if (legsData) animManager.playLegsAnimation(legsAnim);
    if (torsoData) animManager.playTorsoAnimation(torsoAnim);
    animManager.playing = true;
    animManager.legsTime = 0;
    animManager.torsoTime = 0;
    animManager.legsFrame = 0;
    animManager.torsoFrame = 0;
    animManager.update(0);

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = gifWidth; tempCanvas.height = gifHeight;
    const tempCtx = tempCanvas.getContext('2d');

    const gif = await createGifEncoder(gifWidth, gifHeight);
    const dt = 1 / animFps;

    for (let i = 0; i < numFrames; i++) {
        onStatus?.(`[${label}] Frame ${i + 1}/${numFrames}`);
        if (i > 0) animManager.update(dt);
        scene.updateMatrixWorld(true);

        await new Promise(resolve => requestAnimationFrame(resolve));
        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: frameDelay });
        await new Promise(resolve => setTimeout(resolve, 20));
    }

    animManager.stop();
    animManager.resetToIdle();
    viewer.resumeAnimationLoop();
    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    onStatus?.(`Encoding ${label} GIF...`);
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

/**
 * Generate a weapon idle GIF (front view with shader animations, ~3s at 15fps).
 */
export async function generateWeaponIdleGif(viewerRef, onStatus) {
    const { viewer, renderer, scene, camera } = getViewerComponents(viewerRef);
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

    camera.position.x = target.x;
    camera.position.z = target.z + radius;
    camera.position.y = target.y + (originalPosition.y - target.y) * 0.5;
    camera.lookAt(target.x, target.y, target.z);
    camera.updateMatrixWorld();
    scene.updateMatrixWorld(true);

    await new Promise(resolve => requestAnimationFrame(resolve));

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = gifWidth; tempCanvas.height = gifHeight;
    const tempCtx = tempCanvas.getContext('2d');

    const numFrames = 45;
    const fps = 15;
    const frameDelay = Math.round(1000 / fps);
    const dt = 1 / fps;
    let shaderTime = 0;

    const gif = await createGifEncoder(gifWidth, gifHeight);
    for (let i = 0; i < numFrames; i++) {
        onStatus?.(`[Weapon Idle] Frame ${i + 1}/${numFrames}`);
        shaderTime += dt;
        viewer.updateShaderAnimations?.(shaderTime);
        scene.updateMatrixWorld(true);
        await new Promise(resolve => requestAnimationFrame(resolve));
        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: frameDelay });
        await new Promise(resolve => setTimeout(resolve, 30));
    }

    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    onStatus?.('Encoding weapon idle GIF...');
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

/**
 * Generate a head icon (64x64 PNG).
 */
export async function generateHeadIcon(viewerRef, onStatus) {
    const { viewer, renderer, scene, camera } = getViewerComponents(viewerRef);
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

    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    return headIconBlob;
}

/**
 * Generate a still thumbnail (300x300 PNG from idle pose middle frame).
 */
export async function generateStillThumbnail(viewerRef, onStatus) {
    const { viewer, renderer, scene, camera } = getViewerComponents(viewerRef);
    const canvas = renderer.domElement;
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

    camera.position.x = target.x;
    camera.position.z = target.z + radius;
    camera.position.y = target.y + dy * 0.5;
    camera.lookAt(target.x, target.y, target.z);
    camera.updateMatrixWorld();

    const animManager = viewer.getAnimationManager?.();
    if (animManager) {
        const anims = viewer.getAvailableAnimations?.();
        const legsData = anims?.legs?.['LEGS_IDLE'] || anims?.both?.['LEGS_IDLE'];
        const torsoData = anims?.torso?.['TORSO_STAND'] || anims?.both?.['TORSO_STAND'];
        if (legsData || torsoData) {
            animManager.stop();
            if (legsData) animManager.playLegsAnimation('LEGS_IDLE');
            if (torsoData) animManager.playTorsoAnimation('TORSO_STAND');
            animManager.playing = true;
            animManager.legsTime = 0; animManager.torsoTime = 0;
            animManager.legsFrame = 0; animManager.torsoFrame = 0;
            const midFrames = Math.floor((legsData?.numFrames || torsoData?.numFrames || 1) / 2);
            const fps = legsData?.fps || torsoData?.fps || 15;
            for (let i = 0; i < midFrames; i++) {
                animManager.update(1 / fps);
            }
        }
    }

    scene.updateMatrixWorld(true);
    await new Promise(resolve => requestAnimationFrame(resolve));
    renderer.render(scene, camera);
    await new Promise(resolve => requestAnimationFrame(resolve));

    const stillCanvas = document.createElement('canvas');
    stillCanvas.width = 300; stillCanvas.height = 300;
    const stillCtx = stillCanvas.getContext('2d');
    stillCtx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, 300, 300);
    const blob = await new Promise(resolve => stillCanvas.toBlob(resolve, 'image/png'));
    stillCanvas.width = 0; stillCanvas.height = 0;

    if (animManager) {
        animManager.stop();
        animManager.resetToIdle?.();
    }

    camera.position.copy(originalPosition);
    camera.lookAt(originalTarget.x, originalTarget.y, originalTarget.z);
    if (controls) { controls.target.copy(originalTarget); controls.update(); }

    return blob;
}

/**
 * Generate a shadow still thumbnail (300x300 PNG, no rotation).
 */
export async function generateShadowStill(viewerRef, onStatus) {
    const viewer = viewerRef.value || viewerRef;
    if (!viewer) throw new Error('Shadow viewer not available');

    const renderer = viewer.getRenderer();
    const scene = viewer.getScene();
    const camera = viewer.getCamera();
    if (!renderer || !scene || !camera) throw new Error('Could not access shadow viewer components');

    const canvas = renderer.domElement;

    // Set to initial state (no user rotation, time=0)
    viewer.setRotation(0);
    viewer.setShaderTime(0);
    scene.updateMatrixWorld(true);

    await new Promise(resolve => requestAnimationFrame(resolve));
    renderer.render(scene, camera);
    await new Promise(resolve => requestAnimationFrame(resolve));

    const stillCanvas = document.createElement('canvas');
    stillCanvas.width = 300; stillCanvas.height = 300;
    const stillCtx = stillCanvas.getContext('2d');
    stillCtx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, 300, 300);
    const blob = await new Promise(resolve => stillCanvas.toBlob(resolve, 'image/png'));
    stillCanvas.width = 0; stillCanvas.height = 0;

    onStatus?.('Shadow still thumbnail generated');
    return blob;
}

/**
 * Generate a shadow rotation GIF (clockwise rotation, captures shader animation).
 * The shadow itself rotates like the player turning in-game.
 */
export async function generateShadowRotateGif(viewerRef, onStatus) {
    const viewer = viewerRef.value || viewerRef;
    if (!viewer) throw new Error('Shadow viewer not available');

    const renderer = viewer.getRenderer();
    const scene = viewer.getScene();
    const camera = viewer.getCamera();
    if (!renderer || !scene || !camera) throw new Error('Could not access shadow viewer components');

    const canvas = renderer.domElement;
    const gifWidth = 300, gifHeight = 300;

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = gifWidth; tempCanvas.height = gifHeight;
    const tempCtx = tempCanvas.getContext('2d');

    const gif = await createGifEncoder(gifWidth, gifHeight);

    // 72 frames for a full rotation at ~15fps = ~4.8s GIF
    const numFrames = 72;
    const fps = 15;
    const frameDelay = Math.round(1000 / fps);

    // Use setRotation to set absolute rotation for each frame
    // Positive direction = clockwise when viewed from top
    for (let i = 0; i < numFrames; i++) {
        onStatus?.(`[Shadow Rotate] Frame ${i + 1}/${numFrames}`);

        // Set absolute rotation and shader time for this frame
        const rotation = (i / numFrames) * Math.PI * 2;
        const shaderTime = i / fps;
        viewer.setRotation(rotation);
        viewer.setShaderTime(shaderTime);

        // Let the animate loop render with updated values
        await new Promise(resolve => requestAnimationFrame(resolve));
        // Wait one more frame to ensure render happened
        await new Promise(resolve => requestAnimationFrame(resolve));

        captureFrame(renderer, scene, camera, canvas, tempCtx, tempCanvas, gifWidth, gifHeight);
        gif.addFrame(tempCanvas, { copy: true, delay: frameDelay });
    }

    // Reset
    viewer.setRotation(0);
    viewer.setShaderTime(0);

    onStatus?.('Encoding shadow rotate GIF...');
    const blob = await finalizeGif(gif);
    tempCanvas.width = 0; tempCanvas.height = 0;
    return blob;
}

/**
 * Generate all GIF variants for a model.
 * @param {Ref|Object} viewerRef - Vue ref or direct reference to ModelViewer/ShadowViewer component
 * @param {Object} model - Model data with at least { category }
 * @param {Function} onStatus - Status callback
 * @returns {{ rotateBlob, idleBlob, gestureBlob, headIconBlob, thumbnailBlob }}
 */
export async function generateAllGifs(viewerRef, model, onStatus) {
    if (DEBUG) console.log('[GIF] generateAllGifs called, category:', model?.category, 'viewer:', viewerRef?.value ? 'present' : 'null');
    const isWeapon = model?.category === 'weapon';
    const isShadow = model?.category === 'shadow';

    // Shadow models use their own generators
    if (isShadow) {
        let thumbnailBlob = null;
        let rotateBlob = null;

        try {
            onStatus?.('Generating shadow still thumbnail...');
            thumbnailBlob = await generateShadowStill(viewerRef, onStatus);
        } catch (e) {
            console.warn('Shadow still failed:', e.message);
        }

        try {
            onStatus?.('Generating shadow rotation GIF...');
            rotateBlob = await generateShadowRotateGif(viewerRef, onStatus);
        } catch (e) {
            console.warn('Shadow rotate GIF failed:', e.message);
        }

        return { rotateBlob, idleBlob: null, gestureBlob: null, headIconBlob: null, thumbnailBlob };
    }

    if (DEBUG) console.log('[GIF] Starting rotate GIF...');
    onStatus?.('Generating rotate GIF...');
    const rotateBlob = await generateRotateGif(viewerRef, onStatus);
    if (DEBUG) console.log('[GIF] Rotate blob:', rotateBlob ? `${rotateBlob.size} bytes` : 'null');

    let idleBlob = null;
    if (isWeapon) {
        try {
            if (DEBUG) console.log('[GIF] Starting weapon idle GIF...');
            onStatus?.('Generating idle GIF (weapon)...');
            idleBlob = await generateWeaponIdleGif(viewerRef, onStatus);
            if (DEBUG) console.log('[GIF] Weapon idle blob:', idleBlob ? `${idleBlob.size} bytes` : 'null');
        } catch (e) {
            if (DEBUG) console.error('[GIF] Weapon idle GIF failed:', e.message, e.stack);
        }
    } else {
        try {
            onStatus?.('Generating idle GIF...');
            idleBlob = await generateAnimationGif(viewerRef, 'LEGS_IDLE', 'TORSO_STAND', 'Idle', true, onStatus);
        } catch (e) {
            console.warn('Idle GIF failed:', e.message);
        }
    }

    let gestureBlob = null;
    let headIconBlob = null;
    if (!isWeapon) {
        try {
            onStatus?.('Generating gesture GIF...');
            gestureBlob = await generateAnimationGif(viewerRef, 'LEGS_IDLE', 'TORSO_GESTURE', 'Gesture', false, onStatus);
        } catch (e) {
            console.warn('Gesture GIF failed:', e.message);
        }

        onStatus?.('Generating head icon...');
        headIconBlob = await generateHeadIcon(viewerRef, onStatus);
    }

    let thumbnailBlob = null;
    try {
        if (DEBUG) console.log('[GIF] Starting still thumbnail...');
        onStatus?.('Generating still thumbnail...');
        thumbnailBlob = await generateStillThumbnail(viewerRef, onStatus);
        if (DEBUG) console.log('[GIF] Thumbnail blob:', thumbnailBlob ? `${thumbnailBlob.size} bytes` : 'null');
    } catch (e) {
        if (DEBUG) console.error('[GIF] Still thumbnail failed:', e.message, e.stack);
    }

    if (DEBUG) console.log('[GIF] All done. rotate:', !!rotateBlob, 'idle:', !!idleBlob, 'gesture:', !!gestureBlob, 'head:', !!headIconBlob, 'thumb:', !!thumbnailBlob);
    return { rotateBlob, idleBlob, gestureBlob, headIconBlob, thumbnailBlob };
}

/**
 * Wait for all pending textures to finish loading (max 10s).
 */
export function waitForTextures(viewerRef) {
    return new Promise((resolve) => {
        const viewer = viewerRef.value || viewerRef;
        const maxWait = setTimeout(resolve, 10000);
        const check = setInterval(() => {
            const pending = viewer?.getPendingTextures?.() ?? 0;
            if (pending <= 0) {
                clearInterval(check);
                clearTimeout(maxWait);
                setTimeout(resolve, 300);
            }
        }, 100);
    });
}
