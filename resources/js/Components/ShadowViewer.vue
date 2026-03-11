<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import * as THREE from 'three';
import { TGALoader } from 'three/examples/jsm/loaders/TGALoader.js';

const DEBUG = false;

const props = defineProps({
    viewerPath: {
        type: String,
        required: true
    },
    shadowTextures: {
        type: Array,
        default: () => []
    },
    shadowShader: {
        type: String,
        default: null
    },
    autoRotate: {
        type: Boolean,
        default: false
    },
    backgroundColor: {
        type: String,
        default: 'black'
    },
    thumbnailMode: {
        type: Boolean,
        default: false
    },
    groundTexturePath: {
        type: String,
        default: '/baseq3/textures/base_wall/concrete.jpg'
    }
});

const emit = defineEmits(['loaded', 'error']);

const container = ref(null);
let renderer = null;
let scene = null;
let camera = null;
let animationId = null;
let shaderLayers = [];
let groundMesh = null;
let startTime = 0;
let userRotation = 0;
let isDragging = false;
let lastMouseX = 0;

// Parse Q3 shader to extract layer info
function parseQ3Shader(shaderText) {
    if (!shaderText) return [];

    const layers = [];
    // Match shader stages (blocks within { })
    // First find the main shader block
    const mainBlockMatch = shaderText.match(/\{([\s\S]*)\}/);
    if (!mainBlockMatch) return [];

    const content = mainBlockMatch[1];
    // Find nested stage blocks
    const stageRegex = /\{([^{}]*)\}/g;
    let match;

    while ((match = stageRegex.exec(content)) !== null) {
        const stageContent = match[1];
        const layer = {
            texture: null,
            clampmap: false,
            blendSrc: null,
            blendDst: null,
            tcmodRotate: 0,
            rgbGen: null,
            rgbConst: null,
        };

        // Parse map/clampmap
        const mapMatch = stageContent.match(/(?:clamp)?map\s+(\S+)/i);
        if (mapMatch) {
            layer.texture = mapMatch[1];
            layer.clampmap = /clampmap/i.test(stageContent);
        }

        // Parse blendFunc
        const blendMatch = stageContent.match(/blendFunc\s+(\S+)(?:\s+(\S+))?/i);
        if (blendMatch) {
            const b1 = blendMatch[1].toLowerCase();
            const b2 = blendMatch[2]?.toLowerCase();

            if (b1 === 'blend') {
                layer.blendSrc = 'SrcAlpha';
                layer.blendDst = 'OneMinusSrcAlpha';
            } else if (b1 === 'add') {
                layer.blendSrc = 'One';
                layer.blendDst = 'One';
            } else {
                layer.blendSrc = b1;
                layer.blendDst = b2;
            }
        }

        // Parse tcmod rotate
        const rotateMatch = stageContent.match(/tcmod\s+rotate\s+([-\d.]+)/i);
        if (rotateMatch) {
            layer.tcmodRotate = parseFloat(rotateMatch[1]);
        }

        // Parse rgbGen
        const rgbGenMatch = stageContent.match(/rgbGen\s+(\S+)(?:\s*\(\s*([\d.\s]+)\s*\))?/i);
        if (rgbGenMatch) {
            layer.rgbGen = rgbGenMatch[1].toLowerCase();
            if (layer.rgbGen === 'const' && rgbGenMatch[2]) {
                const parts = rgbGenMatch[2].trim().split(/\s+/).map(Number);
                layer.rgbConst = parts;
            }
        }

        if (layer.texture) {
            layers.push(layer);
        }
    }

    DEBUG && console.log('Parsed shader layers:', layers);
    return layers;
}

// Resolve texture path - try common extensions
function resolveTexturePath(basePath, texturePath) {
    // If texture path already has extension, use as-is
    if (/\.(tga|jpg|jpeg|png|bmp)$/i.test(texturePath)) {
        return basePath + '/' + texturePath;
    }
    // Try common extensions - browser can load jpg/png directly
    // TGA needs special handling but we'll try jpg/png first
    return basePath + '/' + texturePath;
}

// Map Q3 blend pair to a Three.js built-in blending mode, or null if no match
function mapBuiltinBlending(blendSrc, blendDst) {
    const src = blendSrc?.toLowerCase();
    const dst = blendDst?.toLowerCase();

    // GL_ZERO, GL_ONE_MINUS_SRC_COLOR = SubtractiveBlending (Q3 shadow default)
    if ((src === 'gl_zero' || src === 'zero') &&
        (dst === 'gl_one_minus_src_color' || dst === 'oneminussrccolor')) {
        return THREE.SubtractiveBlending;
    }
    // GL_ONE, GL_ONE = AdditiveBlending
    if ((src === 'gl_one' || src === 'one') && (dst === 'gl_one' || dst === 'one')) {
        return THREE.AdditiveBlending;
    }
    // GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA = NormalBlending
    if ((src === 'gl_src_alpha' || src === 'srcalpha') &&
        (dst === 'gl_one_minus_src_alpha' || dst === 'oneminussrcalpha')) {
        return THREE.NormalBlending;
    }
    // GL_DST_COLOR, GL_ZERO = MultiplyBlending
    if ((src === 'gl_dst_color' || src === 'dstcolor') &&
        (dst === 'gl_zero' || dst === 'zero')) {
        return THREE.MultiplyBlending;
    }
    return null;
}

// Map Q3 blend constants to Three.js (fallback for CustomBlending)
function mapBlendFactor(q3Factor) {
    const map = {
        'gl_zero': THREE.ZeroFactor,
        'gl_one': THREE.OneFactor,
        'gl_src_color': THREE.SrcColorFactor,
        'gl_one_minus_src_color': THREE.OneMinusSrcColorFactor,
        'gl_dst_color': THREE.DstColorFactor,
        'gl_one_minus_dst_color': THREE.OneMinusDstColorFactor,
        'gl_src_alpha': THREE.SrcAlphaFactor,
        'gl_one_minus_src_alpha': THREE.OneMinusSrcAlphaFactor,
        'gl_dst_alpha': THREE.DstAlphaFactor,
        'gl_one_minus_dst_alpha': THREE.OneMinusDstAlphaFactor,
        'srcalpha': THREE.SrcAlphaFactor,
        'oneminussrcalpha': THREE.OneMinusSrcAlphaFactor,
        'one': THREE.OneFactor,
        'zero': THREE.ZeroFactor,
    };
    return map[q3Factor?.toLowerCase()] ?? THREE.OneFactor;
}

async function loadTexture(url) {
    const textureLoader = new THREE.TextureLoader();
    const tgaLoader = new TGALoader();

    function tryLoad(tryUrl) {
        const isTga = /\.tga$/i.test(tryUrl);
        const loader = isTga ? tgaLoader : textureLoader;
        return new Promise((resolve, reject) => {
            loader.load(tryUrl, resolve, undefined, reject);
        });
    }

    // Try multiple extensions
    const baseUrl = url.replace(/\.(tga|jpg|jpeg|png|bmp)$/i, '');
    const extensions = ['.tga', '.jpg', '.png'];

    // If URL already has extension, try it first
    if (/\.(tga|jpg|jpeg|png|bmp)$/i.test(url)) {
        try {
            const tex = await tryLoad(url);
            DEBUG && console.log('Loaded texture:', url);
            return tex;
        } catch (e) {
            // Try other extensions
        }
    }

    for (const ext of extensions) {
        const tryUrl = baseUrl + ext;
        if (tryUrl === url) continue; // Already tried
        try {
            const tex = await tryLoad(tryUrl);
            DEBUG && console.log('Loaded texture:', tryUrl);
            return tex;
        } catch (e) {
            // Try next extension
        }
    }

    // Fallback: try baseq3 paths for common shadow textures
    // Extract just the relative path (e.g. gfx/misc/shadow or gfx/damage/shadow)
    const relativeMatch = url.match(/(gfx\/(?:misc|damage)\/[^/.]+)/i);
    if (relativeMatch) {
        const relativePath = relativeMatch[1];
        for (const ext of extensions) {
            const fallbackUrl = '/baseq3/' + relativePath + ext;
            try {
                const tex = await tryLoad(fallbackUrl);
                DEBUG && console.log('Loaded texture from baseq3:', fallbackUrl);
                return tex;
            } catch (e) {
                // Try next
            }
        }
    }

    console.warn('Could not load texture:', url);
    return null;
}

async function loadGroundTexture(texPath) {
    try {
        const groundTex = await loadTexture(texPath);
        if (!groundTex) return;

        groundTex.wrapS = THREE.RepeatWrapping;
        groundTex.wrapT = THREE.RepeatWrapping;
        groundTex.repeat.set(2, 2);
        groundTex.magFilter = THREE.LinearFilter;
        groundTex.minFilter = THREE.LinearMipmapLinearFilter;

        if (groundMesh) {
            // Swap texture on existing mesh
            groundMesh.material.map.dispose();
            groundMesh.material.map = groundTex;
            groundMesh.material.needsUpdate = true;
        } else {
            // Create new ground mesh
            const groundGeometry = new THREE.PlaneGeometry(4, 4);
            groundGeometry.rotateX(-Math.PI / 2);
            groundMesh = new THREE.Mesh(groundGeometry, new THREE.MeshBasicMaterial({
                map: groundTex,
                side: THREE.DoubleSide,
                depthWrite: true,
            }));
            groundMesh.position.y = -0.01;
            groundMesh.renderOrder = 0;
            scene.add(groundMesh);
        }
        DEBUG && console.log('Ground texture loaded:', texPath);
    } catch (e) {
        DEBUG && console.warn('Could not load ground texture:', e);
    }
}

async function init() {
    if (!container.value) return;

    const width = container.value.clientWidth || 400;
    const height = container.value.clientHeight || 400;

    // Setup renderer
    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: false,
        preserveDrawingBuffer: true,
    });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor(0x000000);
    container.value.appendChild(renderer.domElement);

    // Orthographic camera (top-down)
    const aspect = width / height;
    const frustumSize = 2;
    camera = new THREE.OrthographicCamera(
        -frustumSize * aspect / 2,
        frustumSize * aspect / 2,
        frustumSize / 2,
        -frustumSize / 2,
        0.1, 100
    );
    camera.position.set(0, 10, 0);
    camera.lookAt(0, 0, 0);

    scene = new THREE.Scene();

    // Ambient light for visibility
    scene.add(new THREE.AmbientLight(0xffffff, 1.0));

    // Ground plane with Q3 floor texture underneath the shadow
    await loadGroundTexture(props.groundTexturePath);

    // Parse shader layers
    let parsedLayers = [];
    if (props.shadowShader) {
        parsedLayers = parseQ3Shader(props.shadowShader);
    }

    // If no shader parsed, create default layers from textures
    if (parsedLayers.length === 0 && props.shadowTextures.length > 0) {
        parsedLayers = props.shadowTextures.map((tex, i) => ({
            texture: tex,
            clampmap: false,
            blendSrc: i === 0 ? 'gl_zero' : 'gl_one',
            blendDst: i === 0 ? 'gl_one_minus_src_color' : 'gl_one',
            tcmodRotate: 0,
            rgbGen: null,
            rgbConst: null,
        }));
    }

    // Create disc geometry for shadow (0.8 * 1.3 = 1.04, 30% bigger)
    const discGeometry = new THREE.CircleGeometry(1.04, 64);
    discGeometry.rotateX(-Math.PI / 2); // Lay flat on XZ plane

    // Load and create layers
    for (let i = 0; i < parsedLayers.length; i++) {
        const layerDef = parsedLayers[i];
        const texturePath = resolveTexturePath(props.viewerPath, layerDef.texture);
        const texture = await loadTexture(texturePath);

        if (!texture) continue;

        texture.wrapS = layerDef.clampmap ? THREE.ClampToEdgeWrapping : THREE.RepeatWrapping;
        texture.wrapT = layerDef.clampmap ? THREE.ClampToEdgeWrapping : THREE.RepeatWrapping;
        texture.magFilter = THREE.LinearFilter;
        texture.minFilter = THREE.LinearMipmapLinearFilter;

        const materialParams = {
            map: texture,
            transparent: true,
            premultipliedAlpha: true,
            side: THREE.DoubleSide,
            depthWrite: false,
            depthTest: true,
        };

        // Apply color from rgbGen const
        if (layerDef.rgbConst && layerDef.rgbConst.length >= 3) {
            materialParams.color = new THREE.Color(layerDef.rgbConst[0], layerDef.rgbConst[1], layerDef.rgbConst[2]);
        }

        // Apply Q3-style blending over the ground texture
        // Use built-in blend modes where possible (more reliable than CustomBlending)
        const src = layerDef.blendSrc || 'gl_zero';
        const dst = layerDef.blendDst || 'gl_one_minus_src_color';
        const builtinBlend = mapBuiltinBlending(src, dst);
        if (builtinBlend !== null) {
            materialParams.blending = builtinBlend;
        } else {
            materialParams.blending = THREE.CustomBlending;
            materialParams.blendEquation = THREE.AddEquation;
            materialParams.blendSrc = mapBlendFactor(src);
            materialParams.blendDst = mapBlendFactor(dst);
            materialParams.blendSrcAlpha = THREE.ZeroFactor;
            materialParams.blendDstAlpha = THREE.OneFactor;
        }

        const mesh = new THREE.Mesh(discGeometry.clone(), new THREE.MeshBasicMaterial(materialParams));
        mesh.renderOrder = 1 + i; // Render after ground (renderOrder 0)
        mesh.position.y = i * 0.001; // Tiny offset to prevent z-fighting
        scene.add(mesh);

        shaderLayers.push({
            mesh,
            texture,
            tcmodRotate: layerDef.tcmodRotate,
            clampmap: layerDef.clampmap,
        });
    }

    // If no layers were loaded, try loading textures directly as fallback
    if (shaderLayers.length === 0 && props.shadowTextures.length > 0) {
        for (let i = 0; i < props.shadowTextures.length; i++) {
            const texPath = props.viewerPath + '/' + props.shadowTextures[i];
            const texture = await loadTexture(texPath);
            if (!texture) continue;

            texture.wrapS = THREE.RepeatWrapping;
            texture.wrapT = THREE.RepeatWrapping;

            const mesh = new THREE.Mesh(
                discGeometry.clone(),
                new THREE.MeshBasicMaterial({
                    map: texture,
                    transparent: true,
                    premultipliedAlpha: true,
                    side: THREE.DoubleSide,
                    depthWrite: false,
                    blending: THREE.SubtractiveBlending,
                })
            );
            mesh.renderOrder = 1 + i;
            mesh.position.y = i * 0.001;
            scene.add(mesh);

            shaderLayers.push({
                mesh,
                texture,
                tcmodRotate: 0,
                clampmap: false,
            });
        }
    }

    startTime = performance.now() / 1000;

    // Mouse interaction for manual rotation
    const canvas = renderer.domElement;
    canvas.addEventListener('mousedown', onMouseDown);
    canvas.addEventListener('mousemove', onMouseMove);
    canvas.addEventListener('mouseup', onMouseUp);
    canvas.addEventListener('mouseleave', onMouseUp);
    canvas.addEventListener('touchstart', onTouchStart, { passive: false });
    canvas.addEventListener('touchmove', onTouchMove, { passive: false });
    canvas.addEventListener('touchend', onMouseUp);

    // Start animation
    animate();

    emit('loaded');
    DEBUG && console.log('ShadowViewer initialized with', shaderLayers.length, 'layers');
}

function onMouseDown(e) {
    isDragging = true;
    lastMouseX = e.clientX;
}

function onMouseMove(e) {
    if (!isDragging) return;
    const dx = e.clientX - lastMouseX;
    userRotation += dx * 0.01; // Convert pixels to radians
    lastMouseX = e.clientX;
}

function onMouseUp() {
    isDragging = false;
}

function onTouchStart(e) {
    if (e.touches.length === 1) {
        e.preventDefault();
        isDragging = true;
        lastMouseX = e.touches[0].clientX;
    }
}

function onTouchMove(e) {
    if (!isDragging || e.touches.length !== 1) return;
    e.preventDefault();
    const dx = e.touches[0].clientX - lastMouseX;
    userRotation += dx * 0.01;
    lastMouseX = e.touches[0].clientX;
}

function animate() {
    animationId = requestAnimationFrame(animate);

    const currentTime = performance.now() / 1000;
    const elapsed = currentTime - startTime;

    // Update shader layer rotations
    for (const layer of shaderLayers) {
        if (layer.tcmodRotate !== 0) {
            // Q3 tcmod rotate is degrees per second
            const rotRadians = (layer.tcmodRotate * elapsed * Math.PI / 180);

            // Apply UV rotation via texture matrix
            const tex = layer.texture;
            tex.center.set(0.5, 0.5);
            tex.rotation = rotRadians;
        }

        // Apply user rotation to the mesh itself
        layer.mesh.rotation.y = userRotation;
    }

    if (renderer && scene && camera) {
        renderer.render(scene, camera);
    }
}

// Set rotation externally (for GIF generation)
function setRotation(radians) {
    userRotation = radians;
}

// Set shader time externally (for GIF generation)
function setShaderTime(time) {
    startTime = performance.now() / 1000 - time;
}

function handleResize() {
    if (!container.value || !renderer || !camera) return;
    const width = container.value.clientWidth;
    const height = container.value.clientHeight;
    renderer.setSize(width, height);

    const aspect = width / height;
    const frustumSize = 2;
    camera.left = -frustumSize * aspect / 2;
    camera.right = frustumSize * aspect / 2;
    camera.top = frustumSize / 2;
    camera.bottom = -frustumSize / 2;
    camera.updateProjectionMatrix();
}

function dispose() {
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }

    if (renderer) {
        const canvas = renderer.domElement;
        canvas.removeEventListener('mousedown', onMouseDown);
        canvas.removeEventListener('mousemove', onMouseMove);
        canvas.removeEventListener('mouseup', onMouseUp);
        canvas.removeEventListener('mouseleave', onMouseUp);
        canvas.removeEventListener('touchstart', onTouchStart);
        canvas.removeEventListener('touchmove', onTouchMove);
        canvas.removeEventListener('touchend', onMouseUp);
    }

    for (const layer of shaderLayers) {
        layer.texture?.dispose();
        layer.mesh?.geometry?.dispose();
        layer.mesh?.material?.dispose();
    }
    shaderLayers = [];

    if (renderer) {
        renderer.dispose();
        if (container.value && renderer.domElement.parentNode === container.value) {
            container.value.removeChild(renderer.domElement);
        }
        renderer = null;
    }

    scene = null;
    camera = null;
}

// Watch for ground texture changes
watch(() => props.groundTexturePath, (newPath) => {
    if (scene && newPath) {
        loadGroundTexture(newPath);
    }
});

onMounted(() => {
    init().catch(err => {
        console.error('ShadowViewer init error:', err);
        emit('error', err);
    });
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    dispose();
});

// Expose methods for GIF generator
defineExpose({
    getRenderer: () => renderer,
    getScene: () => scene,
    getCamera: () => camera,
    setRotation,
    setShaderTime,
    getShaderLayers: () => shaderLayers,
});
</script>

<template>
    <div ref="container" class="w-full h-full shadow-viewer"></div>
</template>

<style scoped>
.shadow-viewer {
    cursor: grab;
}
.shadow-viewer:active {
    cursor: grabbing;
}
</style>
