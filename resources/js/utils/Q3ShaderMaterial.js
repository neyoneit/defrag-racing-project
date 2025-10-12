import * as THREE from 'three';

/**
 * Q3ShaderMaterial - Handles Quake 3 shader rendering with multiple stages
 *
 * Q3 shaders support multi-pass rendering where each stage is drawn on top
 * of the previous with different textures, blend modes, and animations.
 */
export class Q3ShaderMaterialSystem {
    constructor(loader) {
        this.loader = loader; // Reference to MD3Loader for texture loading
        this.materials = new Map(); // Store materials by mesh
    }

    /**
     * Create material(s) for a mesh based on Q3 shader definition
     * Returns an array of materials (one per stage) or a single material
     */
    async createMaterialForShader(shader, defaultTexturePath, surfaceName) {
        if (!shader || !shader.stages || shader.stages.length === 0) {
            // No shader, return basic material
            return this.createBasicMaterial();
        }

        // For multi-stage shaders, we need to handle them differently
        // Unfortunately Three.js doesn't support multi-pass materials natively
        // So we'll create a single material that approximates the shader effect

        const material = new THREE.MeshPhongMaterial({
            color: 0xffffff,
            side: THREE.DoubleSide,  // Default to DoubleSide, will be overridden if shader specifies cull
            transparent: false,  // Only enable if shader requires it
            depthWrite: true,
        });

        // Store shader data for animation
        material.userData.shader = shader;
        material.userData.surfaceName = surfaceName;
        material.userData.stages = [];

        // Process each stage
        for (let i = 0; i < shader.stages.length; i++) {
            const stage = shader.stages[i];
            const stageData = {
                index: i,
                map: stage.map,
                blendFunc: stage.blendFunc,
                rgbGen: stage.rgbGen,
                alphaGen: stage.alphaGen,
                tcGen: stage.tcGen,
                tcMod: stage.tcMod || [],
                depthWrite: stage.depthWrite,
                alphaFunc: stage.alphaFunc,
                texture: null,
                // Animation state
                tcModState: this.initTcModState(stage.tcMod),
            };

            material.userData.stages.push(stageData);
        }

        // Load texture - for multi-stage shaders, find the stage with the main texture
        // Priority: last stage with blendFunc > first stage with real texture > default
        let textureStage = shader.stages[0];
        let texturePath = defaultTexturePath;

        // For multi-stage shaders, check if there's a better stage to use
        if (shader.stages.length > 1) {
            // Find the last stage with a blendFunc and real texture (not environment/lightmap)
            for (let i = shader.stages.length - 1; i >= 0; i--) {
                const stage = shader.stages[i];
                if (stage.blendFunc && stage.map &&
                    stage.map !== '$lightmap' &&
                    stage.map !== '$whiteimage' &&
                    stage.tcGen !== 'environment') {
                    textureStage = stage;
                    break;
                }
            }
        }

        if (textureStage.map && textureStage.map !== '$lightmap' && textureStage.map !== '$whiteimage') {
            texturePath = this.resolveTexturePath(textureStage.map, defaultTexturePath);
        }

        try {
            await this.loader.loadTextureForMesh(texturePath, material);
            material.userData.stages[0].texture = material.map;

            // Handle clampmap directive
            if (textureStage.mapType === 'clampmap') {
                material.map.wrapS = THREE.ClampToEdgeWrapping;
                material.map.wrapT = THREE.ClampToEdgeWrapping;
            }

            // For additive/sort additive shaders, always use alphaTest to cut out transparent areas
            if (shader.sort === 'additive' || (textureStage.blendFunc &&
                typeof textureStage.blendFunc === 'object' &&
                textureStage.blendFunc.src === 'GL_ONE' &&
                textureStage.blendFunc.dst === 'GL_ONE')) {
                material.alphaTest = 0.01;  // Very low threshold - cut out only fully transparent pixels
            }
        } catch (error) {
            console.warn('Failed to load shader texture:', error);
        }

        // Apply shader properties from the texture stage
        this.applyShaderProperties(material, shader, textureStage);

        // For additive/transparent materials, set render order
        if (material.transparent && material.blending === THREE.AdditiveBlending) {
            material.depthWrite = false;
            material.depthTest = true;
        }

        return material;
    }

    /**
     * Initialize tcMod animation state
     */
    initTcModState(tcModArray) {
        if (!tcModArray || tcModArray.length === 0) return null;

        return {
            offset: new THREE.Vector2(0, 0),
            rotation: 0,
            scale: new THREE.Vector2(1, 1),
            center: new THREE.Vector2(0.5, 0.5),
        };
    }

    /**
     * Resolve texture path from shader map directive
     */
    resolveTexturePath(mapPath, defaultPath) {
        // If map path is absolute (starts with models/), use it
        if (mapPath.startsWith('models/')) {
            // Extract base directory from defaultPath
            // defaultPath: /storage/models/extracted/xxx/models/players/niria/niria_h.tga
            // We want: /storage/models/extracted/xxx/
            const match = defaultPath.match(/(.*?\/models\/extracted\/[^\/]+)\//);
            if (match) {
                return match[1] + '/' + mapPath;
            }
        }

        // Otherwise use default path
        return defaultPath;
    }

    /**
     * Apply shader properties to material
     */
    applyShaderProperties(material, shader, stage) {
        // Apply cull mode (but don't override if shader specifies it)
        if (shader.cull) {
            material.side = this.getCullMode(shader.cull);
        }

        // Apply blend function FIRST (will be overridden by sort directive if present)
        if (stage.blendFunc) {
            this.applyBlendFunc(material, stage.blendFunc);
        }

        // Apply RGB generation
        if (stage.rgbGen) {
            this.applyRgbGen(material, stage.rgbGen);
        }

        // Apply alpha generation
        if (stage.alphaGen) {
            this.applyAlphaGen(material, stage.alphaGen);
        }

        // Apply environment mapping for tcGen environment
        if (stage.tcGen === 'environment') {
            material.envMapIntensity = 0.5;
            material.metalness = 0.3;
            material.roughness = 0.4;
        }

        // Sort directive OVERRIDES blend function - apply LAST
        if (shader.sort === 'additive') {
            material.blending = THREE.AdditiveBlending;
            material.transparent = true;
            material.depthWrite = false;
        }
    }

    /**
     * Get Three.js cull mode from Q3 cull directive
     */
    getCullMode(cull) {
        // Default to DoubleSide for player models (fixes see-through head issue)
        if (!cull) return THREE.DoubleSide;

        switch (cull.toLowerCase()) {
            case 'none':
            case 'disable':
            case 'twosided':
                return THREE.DoubleSide;
            case 'back':
            case 'backside':
            case 'backsided':
                return THREE.FrontSide; // Q3 "cull back" = render front
            case 'front':
            case 'frontside':
            case 'frontsided':
                return THREE.BackSide; // Q3 "cull front" = render back
            default:
                return THREE.DoubleSide;
        }
    }

    /**
     * Apply blend function to material
     */
    applyBlendFunc(material, blendFunc) {
        if (typeof blendFunc === 'string') {
            switch (blendFunc.toLowerCase()) {
                case 'add':
                    material.blending = THREE.AdditiveBlending;
                    material.transparent = true;
                    break;
                case 'blend':
                    // For multi-stage shaders, "blend" is used for layering
                    // Set blend mode but DON'T enable transparency - that's for multi-pass rendering
                    material.blending = THREE.NormalBlending;
                    break;
                case 'filter':
                    material.blending = THREE.MultiplyBlending;
                    material.transparent = true;
                    break;
            }
        } else if (typeof blendFunc === 'object') {
            const { src, dst } = blendFunc;

            if (src === 'GL_ONE' && dst === 'GL_ONE') {
                material.blending = THREE.AdditiveBlending;
                material.transparent = true;
                material.depthWrite = false;
            } else if (src === 'GL_SRC_ALPHA' && dst === 'GL_ONE_MINUS_SRC_ALPHA') {
                // This is "blend" mode - used in multi-stage shaders
                // DON'T enable transparency - it's for layering multiple passes
                material.blending = THREE.NormalBlending;
            } else if (src === 'GL_DST_COLOR' && dst === 'GL_ZERO') {
                material.blending = THREE.MultiplyBlending;
                material.transparent = true;
            }
        }
    }

    /**
     * Apply RGB generation mode
     */
    applyRgbGen(material, rgbGen) {
        const mode = rgbGen.toLowerCase();

        if (mode === 'lightingdiffuse') {
            // Use standard lighting
            material.emissive = new THREE.Color(0x000000);
        } else if (mode === 'identity' || mode === 'identitylighting') {
            // For additive blending, use texture colors directly without emissive
            // Emissive would add gray to transparent areas, making them visible
            if (material.blending !== THREE.AdditiveBlending) {
                material.emissive = new THREE.Color(0x888888);
                material.emissiveIntensity = 0.5;
            }
        } else if (mode === 'vertex') {
            // Use vertex colors
            material.vertexColors = true;
        } else if (mode === 'exactvertex') {
            // Use exact vertex colors
            material.vertexColors = true;
        } else if (mode.startsWith('wave')) {
            // Animated color - will be handled in updateShaderAnimations
            material.userData.rgbGenWave = rgbGen;
        }
    }

    /**
     * Apply alpha generation mode
     */
    applyAlphaGen(material, alphaGen) {
        const mode = alphaGen.toLowerCase();

        if (mode === 'vertex') {
            material.transparent = true;
        } else if (mode === 'lightingspecular') {
            material.transparent = true;
        } else if (mode.startsWith('wave')) {
            material.transparent = true;
            material.userData.alphaGenWave = alphaGen;
        }
    }

    /**
     * Update shader animations for a material at given time
     */
    updateMaterialAnimation(material, time) {
        if (!material.userData.shader || !material.userData.stages) return;

        const stages = material.userData.stages;
        if (stages.length === 0 || !material.map) return;

        // Update first stage animations (the one applied to material.map)
        const stage = stages[0];
        if (!stage.tcMod || stage.tcMod.length === 0) return;

        // Reset transforms
        let offset = new THREE.Vector2(0, 0);
        let rotation = 0;
        let scale = new THREE.Vector2(1, 1);
        let center = new THREE.Vector2(0.5, 0.5);

        // Apply each tcMod in order
        stage.tcMod.forEach(mod => {
            switch (mod.type) {
                case 'stretch':
                    scale = this.calculateStretch(mod.params, time);
                    break;
                case 'rotate':
                    rotation = this.calculateRotation(mod.params, time);
                    break;
                case 'scroll':
                    offset = this.calculateScroll(mod.params, time);
                    break;
                case 'scale':
                    scale = new THREE.Vector2(
                        parseFloat(mod.params[0]),
                        parseFloat(mod.params[1])
                    );
                    break;
                case 'turb':
                    // Turbulence - complex, skip for now
                    break;
            }
        });

        // Apply transforms to texture
        material.map.offset.copy(offset);
        material.map.rotation = rotation;
        material.map.repeat.copy(scale);
        material.map.center.copy(center);
        material.map.needsUpdate = true;
    }

    /**
     * Calculate stretch value from wave function
     */
    calculateStretch(params, time) {
        const [func, base, amp, phase, freq] = params;
        const baseVal = parseFloat(base);
        const amplitude = parseFloat(amp);
        const phaseVal = parseFloat(phase);
        const frequency = parseFloat(freq);

        const value = this.evaluateWaveFunc(func, baseVal, amplitude, phaseVal, frequency, time);
        return new THREE.Vector2(value, value);
    }

    /**
     * Calculate rotation from rotate parameters
     */
    calculateRotation(params, time) {
        const degreesPerSec = parseFloat(params[0]);
        return (time * degreesPerSec * Math.PI / 180) % (Math.PI * 2);
    }

    /**
     * Calculate scroll offset
     */
    calculateScroll(params, time) {
        const sSpeed = parseFloat(params[0]);
        const tSpeed = parseFloat(params[1]);
        return new THREE.Vector2(
            (time * sSpeed) % 1.0,
            (time * tSpeed) % 1.0
        );
    }

    /**
     * Evaluate wave function
     */
    evaluateWaveFunc(func, base, amplitude, phase, frequency, time) {
        const t = time * frequency + phase;

        switch (func.toLowerCase()) {
            case 'sin':
                return base + amplitude * Math.sin(t * Math.PI * 2);
            case 'triangle':
                const triT = (t % 1.0);
                return base + amplitude * (triT < 0.5 ? triT * 4 - 1 : 3 - triT * 4);
            case 'square':
                const sqT = (t % 1.0);
                return base + amplitude * (sqT < 0.5 ? -1 : 1);
            case 'sawtooth':
                const sawT = (t % 1.0);
                return base + amplitude * (sawT * 2 - 1);
            case 'inversesawtooth':
                const invT = (t % 1.0);
                return base + amplitude * (1 - invT * 2);
            default:
                return base;
        }
    }

    /**
     * Create basic material without shader
     */
    createBasicMaterial() {
        return new THREE.MeshPhongMaterial({
            color: 0xffffff,
            side: THREE.DoubleSide,
        });
    }
}
