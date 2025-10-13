import * as THREE from 'three';

/**
 * Q3ShaderMaterial - Handles Quake 3 shader rendering with custom GLSL shaders
 *
 * This implementation uses THREE.ShaderMaterial with custom vertex and fragment shaders
 * to properly handle multi-stage Q3 shaders in a single material (no flickering!)
 */
export class Q3ShaderMaterialSystem {
    // Static texture cache shared across ALL instances (so textures load only once per model)
    static textureCache = new Map();

    constructor(loader) {
        this.loader = loader; // Reference to MD3Loader for texture loading
        this.materials = new Map(); // Store materials by mesh
    }

    /**
     * Create material for a mesh based on Q3 shader definition
     * Returns a single THREE.ShaderMaterial that handles all stages
     */
    async createMaterialForShader(shader, defaultTexturePath, surfaceName) {
        if (!shader || !shader.stages || shader.stages.length === 0) {
            // No shader, return basic material
            return this.createBasicMaterial();
        }

        // Check if this is a multi-stage shader
        if (shader.stages.length > 1) {
            console.log(`🎬 Multi-stage shader detected: ${shader.name} with ${shader.stages.length} stages`);
            return await this.createMultiStageMaterial(shader, defaultTexturePath, surfaceName);
        }

        // Single stage shader - create one material
        return await this.createSingleStageMaterial(shader, shader.stages[0], defaultTexturePath, surfaceName);
    }

    /**
     * Create a custom shader material that handles multiple stages
     */
    async createMultiStageMaterial(shader, defaultTexturePath, surfaceName) {
        const stages = shader.stages;
        const numStages = Math.min(stages.length, 4); // Support up to 4 stages for now

        // Load textures for all stages
        const textures = [];
        const stageData = [];

        for (let i = 0; i < numStages; i++) {
            const stage = stages[i];
            let texture = null;

            // Handle special texture names
            if (stage.map === '$whiteimage') {
                // Use solid white texture
                texture = this.createWhiteTexture();
                console.log(`✅ Using $whiteimage for stage ${i}`);
            } else if (stage.map === '$lightmap') {
                // Lightmaps not supported, use white
                texture = this.createWhiteTexture();
                console.log(`⚠️ $lightmap not supported for stage ${i}, using white`);
            } else {
                // Load actual texture
                let texturePath = defaultTexturePath;
                if (stage.map) {
                    texturePath = this.resolveTexturePath(stage.map, defaultTexturePath);
                }

                try {
                    texture = await this.loadTexture(texturePath);
                    console.log(`✅ Loaded texture for stage ${i}: ${texturePath}`);
                } catch (error) {
                    console.warn(`Failed to load texture for stage ${i}:`, texturePath, error);
                    // For additive blending stages, use black fallback (so it doesn't add unwanted brightness)
                    // For other stages, use white fallback
                    const isAdditive = stage.blendFunc === 'add' ||
                                      (typeof stage.blendFunc === 'object' &&
                                       stage.blendFunc.src === 'GL_ONE' &&
                                       stage.blendFunc.dst === 'GL_ONE');
                    texture = isAdditive ? this.createBlackTexture() : this.createWhiteTexture();
                    console.log(`⚠️ Using ${isAdditive ? 'black' : 'white'} fallback for stage ${i}`);
                }
            }

            // Handle clampmap directive
            if (stage.mapType === 'clampmap') {
                texture.wrapS = THREE.ClampToEdgeWrapping;
                texture.wrapT = THREE.ClampToEdgeWrapping;
            } else {
                texture.wrapS = THREE.RepeatWrapping;
                texture.wrapT = THREE.RepeatWrapping;
            }

            textures.push(texture);

            // Extract tcMod scroll parameters
            let scrollSpeed = [0, 0];
            if (stage.tcMod && Array.isArray(stage.tcMod)) {
                const scrollMod = stage.tcMod.find(mod => mod.type === 'scroll');
                if (scrollMod && scrollMod.params) {
                    scrollSpeed = [parseFloat(scrollMod.params[0]) || 0, parseFloat(scrollMod.params[1]) || 0];
                    console.log(`✅ Stage ${i} tcMod scroll: [${scrollSpeed[0]}, ${scrollSpeed[1]}]`);
                }
            }

            // Check for tcGen environment
            const tcGenType = stage.tcGen || 'base';
            if (tcGenType === 'environment') {
                console.log(`✅ Stage ${i} tcGen: environment`);
            }

            // Store stage data for shader uniforms
            stageData.push({
                blendFunc: this.parseBlendFunc(stage.blendFunc),
                tcMod: stage.tcMod || [],
                rgbGen: stage.rgbGen || 'identity',
                alphaGen: stage.alphaGen || 'identity',
                tcGen: tcGenType,
                scrollSpeed: scrollSpeed,
            });
        }

        // Determine if the BASE stage (stage 0) uses additive blending
        // Only make the material see-through if the base stage is additive
        // If only later stages are additive, the base remains opaque
        const baseStage = stages[0];
        const hasAdditiveBlending = (() => {
            const blendFunc = baseStage.blendFunc;
            if (!blendFunc) return false;

            if (typeof blendFunc === 'string' && blendFunc.toLowerCase() === 'add') {
                return true;
            }
            if (typeof blendFunc === 'object' && blendFunc !== null && blendFunc.src === 'GL_ONE' && blendFunc.dst === 'GL_ONE') {
                return true;
            }
            return false;
        })();

        // Create custom shader material
        const material = new THREE.ShaderMaterial({
            uniforms: {
                // Textures for each stage
                tStage0: { value: textures[0] || null },
                tStage1: { value: textures[1] || null },
                tStage2: { value: textures[2] || null },
                tStage3: { value: textures[3] || null },

                // Number of active stages
                numStages: { value: numStages },

                // Time for animations
                time: { value: 0.0 },

                // Blend modes for each stage (vec4: src, dst, enabled, reserved)
                blendMode0: { value: new THREE.Vector4(...stageData[0]?.blendFunc || [1, 0, 1, 0]) },
                blendMode1: { value: new THREE.Vector4(...stageData[1]?.blendFunc || [1, 1, 1, 0]) },
                blendMode2: { value: new THREE.Vector4(...stageData[2]?.blendFunc || [1, 1, 1, 0]) },
                blendMode3: { value: new THREE.Vector4(...stageData[3]?.blendFunc || [1, 1, 1, 0]) },

                // Scroll speeds for tcMod scroll (vec2: s_speed, t_speed)
                scrollSpeed0: { value: new THREE.Vector2(...stageData[0]?.scrollSpeed || [0, 0]) },
                scrollSpeed1: { value: new THREE.Vector2(...stageData[1]?.scrollSpeed || [0, 0]) },
                scrollSpeed2: { value: new THREE.Vector2(...stageData[2]?.scrollSpeed || [0, 0]) },
                scrollSpeed3: { value: new THREE.Vector2(...stageData[3]?.scrollSpeed || [0, 0]) },

                // tcGen types (0=base, 1=environment)
                tcGenType0: { value: stageData[0]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType1: { value: stageData[1]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType2: { value: stageData[2]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType3: { value: stageData[3]?.tcGen === 'environment' ? 1 : 0 },

                // rgbGen types (0=identity, 1=lightingDiffuse)
                rgbGenType0: { value: stageData[0]?.rgbGen === 'lightingDiffuse' ? 1 : 0 },
                rgbGenType1: { value: stageData[1]?.rgbGen === 'lightingDiffuse' ? 1 : 0 },
                rgbGenType2: { value: stageData[2]?.rgbGen === 'lightingDiffuse' ? 1 : 0 },
                rgbGenType3: { value: stageData[3]?.rgbGen === 'lightingDiffuse' ? 1 : 0 },

                // Lighting uniforms (matching Q3 lightingDiffuse behavior)
                ambientLightColor: { value: new THREE.Color(0xB0B0B0) },  // ~69% ambient
                directionalLightDirection: { value: new THREE.Vector3(0.5, 0.5, 1.0).normalize() },
                directionalLightColor: { value: new THREE.Color(0x505050) },  // ~31% directional
            },

            vertexShader: this.getVertexShader(),
            fragmentShader: this.getFragmentShader(),

            side: shader.cull ? this.getCullMode(shader.cull) : THREE.DoubleSide,
            transparent: true, // Enable transparency for blending
            depthWrite: !hasAdditiveBlending, // Disable depth write for additive blending (makes it see-through)
            depthTest: true,
            blending: hasAdditiveBlending ? THREE.AdditiveBlending : THREE.NormalBlending,
            lights: false, // We handle lighting manually
        });

        if (hasAdditiveBlending) {
            console.log(`✨ Additive blending detected - material will be translucent`);
        }

        // Store shader data for animation
        material.userData.shader = shader;
        material.userData.surfaceName = surfaceName;
        material.userData.isMultiStage = true;
        material.userData.numStages = numStages;
        material.userData.stageData = stageData;

        return material;
    }

    /**
     * Create material for single-stage shader
     */
    async createSingleStageMaterial(shader, stage, defaultTexturePath, surfaceName) {
        const material = new THREE.MeshPhongMaterial({
            color: 0xffffff,
            side: shader.cull ? this.getCullMode(shader.cull) : THREE.DoubleSide,
            transparent: false,
            depthWrite: true,
        });

        // Determine texture path
        let texturePath = defaultTexturePath;
        if (stage.map && stage.map !== '$lightmap' && stage.map !== '$whiteimage') {
            texturePath = this.resolveTexturePath(stage.map, defaultTexturePath);
        }

        // Load texture
        try {
            await this.loader.loadTextureForMesh(texturePath, material);
        } catch (error) {
            console.warn(`Failed to load texture for single stage:`, texturePath, error);
        }

        // Apply shader properties
        this.applyShaderProperties(material, shader, stage);

        // Store shader data for animation
        material.userData.shader = shader;
        material.userData.surfaceName = surfaceName;
        material.userData.isMultiStage = false;

        return material;
    }

    /**
     * Get vertex shader source
     */
    getVertexShader() {
        return `
            varying vec2 vUv;
            varying vec2 vEnvUv;
            varying vec3 vNormal;
            varying vec3 vPosition;

            void main() {
                vUv = uv;
                vNormal = normalize(normalMatrix * normal);
                vPosition = (modelViewMatrix * vec4(position, 1.0)).xyz;

                // Calculate environment-mapped UVs (sphere mapping)
                // This is approximation of Q3's tcGen environment
                vec3 viewNormal = normalize(vNormal);
                vEnvUv = viewNormal.xy * 0.5 + 0.5;

                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }
        `;
    }

    /**
     * Get fragment shader source for multi-stage rendering
     */
    getFragmentShader() {
        return `
            uniform sampler2D tStage0;
            uniform sampler2D tStage1;
            uniform sampler2D tStage2;
            uniform sampler2D tStage3;

            uniform int numStages;
            uniform float time;

            uniform vec4 blendMode0; // x=srcFactor, y=dstFactor, z=enabled, w=reserved
            uniform vec4 blendMode1;
            uniform vec4 blendMode2;
            uniform vec4 blendMode3;

            uniform vec2 scrollSpeed0;
            uniform vec2 scrollSpeed1;
            uniform vec2 scrollSpeed2;
            uniform vec2 scrollSpeed3;

            uniform int tcGenType0; // 0=base, 1=environment
            uniform int tcGenType1;
            uniform int tcGenType2;
            uniform int tcGenType3;

            uniform int rgbGenType0; // 0=identity, 1=lightingDiffuse
            uniform int rgbGenType1;
            uniform int rgbGenType2;
            uniform int rgbGenType3;

            uniform vec3 ambientLightColor;
            uniform vec3 directionalLightDirection;
            uniform vec3 directionalLightColor;

            varying vec2 vUv;
            varying vec2 vEnvUv;
            varying vec3 vNormal;
            varying vec3 vPosition;

            // Apply blend function manually in shader
            vec4 blendColors(vec4 src, vec4 dst, vec4 blendMode) {
                if (blendMode.z < 0.5) return dst; // Stage disabled

                float srcFactor = blendMode.x;
                float dstFactor = blendMode.y;

                // Blend mode encoding:
                // 1.0 = GL_ONE (1)
                // 0.0 = GL_ZERO (0)
                // 2.0 = GL_SRC_ALPHA
                // 3.0 = GL_ONE_MINUS_SRC_ALPHA
                // 4.0 = GL_DST_COLOR

                vec3 srcColor = src.rgb;
                vec3 dstColor = dst.rgb;
                float srcAlpha = src.a;
                float dstAlpha = dst.a;

                // Apply source factor
                vec3 srcBlended = srcColor;
                if (srcFactor < 0.5) {
                    srcBlended = vec3(0.0); // GL_ZERO
                } else if (srcFactor > 0.5 && srcFactor < 1.5) {
                    srcBlended = srcColor; // GL_ONE
                } else if (srcFactor > 1.5 && srcFactor < 2.5) {
                    srcBlended = srcColor * srcAlpha; // GL_SRC_ALPHA
                } else if (srcFactor > 3.5 && srcFactor < 4.5) {
                    srcBlended = srcColor * dstColor; // GL_DST_COLOR
                }

                // Apply destination factor
                vec3 dstBlended = dstColor;
                if (dstFactor < 0.5) {
                    dstBlended = vec3(0.0); // GL_ZERO
                } else if (dstFactor > 0.5 && dstFactor < 1.5) {
                    dstBlended = dstColor; // GL_ONE
                } else if (dstFactor > 1.5 && dstFactor < 2.5) {
                    dstBlended = dstColor * srcAlpha; // GL_SRC_ALPHA
                } else if (dstFactor > 2.5 && dstFactor < 3.5) {
                    dstBlended = dstColor * (1.0 - srcAlpha); // GL_ONE_MINUS_SRC_ALPHA
                }

                // Combine
                vec3 finalColor = srcBlended + dstBlended;
                float finalAlpha = max(srcAlpha, dstAlpha);

                return vec4(finalColor, finalAlpha);
            }

            void main() {
                // Calculate lighting for stages that use rgbGen lightingDiffuse
                float diffuse = max(dot(vNormal, directionalLightDirection), 0.0);
                vec3 lighting = ambientLightColor + directionalLightColor * diffuse;

                // Calculate UV coordinates for stage 0
                vec2 uv0 = (tcGenType0 == 1) ? vEnvUv : vUv;
                uv0 += scrollSpeed0 * time;

                // Start with base stage
                vec4 color = texture2D(tStage0, uv0);

                // Apply lighting if this stage uses rgbGen lightingDiffuse
                if (rgbGenType0 == 1) {
                    color.rgb = lighting;
                }

                // Blend additional stages on top
                if (numStages > 1) {
                    vec2 uv1 = (tcGenType1 == 1) ? vEnvUv : vUv;
                    uv1 += scrollSpeed1 * time;
                    vec4 stage1 = texture2D(tStage1, uv1);

                    // Apply lighting if this stage uses rgbGen lightingDiffuse
                    if (rgbGenType1 == 1) {
                        stage1.rgb = lighting;
                    }

                    color = blendColors(stage1, color, blendMode1);
                }

                if (numStages > 2) {
                    vec2 uv2 = (tcGenType2 == 1) ? vEnvUv : vUv;
                    uv2 += scrollSpeed2 * time;
                    vec4 stage2 = texture2D(tStage2, uv2);

                    // Apply lighting if this stage uses rgbGen lightingDiffuse
                    if (rgbGenType2 == 1) {
                        stage2.rgb = lighting;
                    }

                    color = blendColors(stage2, color, blendMode2);
                }

                if (numStages > 3) {
                    vec2 uv3 = (tcGenType3 == 1) ? vEnvUv : vUv;
                    uv3 += scrollSpeed3 * time;
                    vec4 stage3 = texture2D(tStage3, uv3);

                    // Apply lighting if this stage uses rgbGen lightingDiffuse
                    if (rgbGenType3 == 1) {
                        stage3.rgb = lighting;
                    }

                    color = blendColors(stage3, color, blendMode3);
                }

                gl_FragColor = color;
            }
        `;
    }

    /**
     * Parse Q3 blend function to numeric values for shader
     * Returns [srcFactor, dstFactor, enabled, reserved]
     */
    parseBlendFunc(blendFunc) {
        if (!blendFunc) return [1, 0, 1, 0]; // GL_ONE, GL_ZERO (replace)

        if (typeof blendFunc === 'string') {
            switch (blendFunc.toLowerCase()) {
                case 'add':
                    return [1, 1, 1, 0]; // GL_ONE, GL_ONE (additive)
                case 'blend':
                    return [2, 3, 1, 0]; // GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA
                case 'filter':
                    return [0, 1, 1, 0]; // GL_ZERO, GL_SRC_COLOR (multiply)
                default:
                    return [1, 0, 1, 0];
            }
        } else if (typeof blendFunc === 'object') {
            const { src, dst } = blendFunc;
            let srcFactor = 1, dstFactor = 0;

            // Map GL constants to numeric values
            if (src === 'GL_ONE') srcFactor = 1;
            else if (src === 'GL_ZERO') srcFactor = 0;
            else if (src === 'GL_SRC_ALPHA') srcFactor = 2;
            else if (src === 'GL_ONE_MINUS_SRC_ALPHA') srcFactor = 3;
            else if (src === 'GL_DST_COLOR') srcFactor = 4;

            if (dst === 'GL_ONE') dstFactor = 1;
            else if (dst === 'GL_ZERO') dstFactor = 0;
            else if (dst === 'GL_SRC_ALPHA') dstFactor = 2;
            else if (dst === 'GL_ONE_MINUS_SRC_ALPHA') dstFactor = 3;
            else if (dst === 'GL_DST_COLOR') dstFactor = 4;

            return [srcFactor, dstFactor, 1, 0];
        }

        return [1, 0, 1, 0];
    }

    /**
     * Load texture and return promise with automatic format fallback
     * Tries multiple extensions like Q3 engine does: .tga, .TGA, .jpg, .JPG, .jpeg, .png
     * Uses static cache to avoid loading the same texture multiple times (shared across all instances)
     */
    loadTexture(url) {
        // Check static cache first
        if (Q3ShaderMaterialSystem.textureCache.has(url)) {
            console.log(`♻️ Using cached texture: ${url}`);
            return Promise.resolve(Q3ShaderMaterialSystem.textureCache.get(url));
        }

        return new Promise((resolve, reject) => {
            const lastDot = url.lastIndexOf('.');
            const basePath = url.substring(0, lastDot + 1);

            // Try these extensions in order (like Q3 engine)
            const fallbackExtensions = ['tga', 'TGA', 'jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG'];

            let currentIndex = 0;

            const tryNextExtension = () => {
                if (currentIndex >= fallbackExtensions.length) {
                    reject(new Error(`Failed to load texture: ${url} (tried all formats)`));
                    return;
                }

                const ext = fallbackExtensions[currentIndex];
                const testUrl = basePath + ext;
                const loader = (ext.toLowerCase() === 'tga') ? this.loader.tgaLoader : this.loader.textureLoader;

                loader.load(
                    testUrl,
                    (texture) => {
                        texture.flipY = false;
                        if (currentIndex > 0) {
                            console.log(`📁 Texture fallback: ${url} → ${testUrl}`);
                        }
                        // Store in static cache
                        Q3ShaderMaterialSystem.textureCache.set(url, texture);
                        resolve(texture);
                    },
                    undefined,
                    () => {
                        // Failed, try next extension
                        currentIndex++;
                        tryNextExtension();
                    }
                );
            };

            tryNextExtension();
        });
    }

    /**
     * Create a 1x1 white texture as fallback
     */
    createWhiteTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, 1, 1);
        const texture = new THREE.CanvasTexture(canvas);
        texture.wrapS = THREE.RepeatWrapping;
        texture.wrapT = THREE.RepeatWrapping;
        return texture;
    }

    /**
     * Create a 1x1 black texture as fallback (for additive blending stages)
     */
    createBlackTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'black';
        ctx.fillRect(0, 0, 1, 1);
        const texture = new THREE.CanvasTexture(canvas);
        texture.wrapS = THREE.RepeatWrapping;
        texture.wrapT = THREE.RepeatWrapping;
        return texture;
    }

    /**
     * Resolve texture path from shader map directive
     */
    resolveTexturePath(mapPath, defaultPath) {
        // If map path is absolute (starts with models/ or textures/), use it
        if (mapPath.startsWith('models/') || mapPath.startsWith('textures/')) {
            // Check if this is a base Q3 model or user-uploaded model
            if (defaultPath.includes('/baseq3/')) {
                // Base Q3 model
                return '/baseq3/' + mapPath;
            } else {
                // User-uploaded model
                const match = defaultPath.match(/(.*?\/models\/extracted\/[^\/]+)\//);
                if (match) {
                    return match[1] + '/' + mapPath;
                }
            }
        }

        // Otherwise use default path
        return defaultPath;
    }

    /**
     * Apply shader properties to material (for single-stage materials)
     */
    applyShaderProperties(material, shader, stage) {
        // Apply cull mode
        if (shader.cull) {
            material.side = this.getCullMode(shader.cull);
        }

        // Apply blend function
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

        // Sort directive OVERRIDES blend function
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
        if (!cull) return THREE.DoubleSide;

        switch (cull.toLowerCase()) {
            case 'none':
            case 'disable':
            case 'twosided':
                return THREE.DoubleSide;
            case 'back':
            case 'backside':
            case 'backsided':
                return THREE.FrontSide;
            case 'front':
            case 'frontside':
            case 'frontsided':
                return THREE.BackSide;
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
                    material.blending = THREE.NormalBlending;
                    material.transparent = true;
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
                material.blending = THREE.NormalBlending;
                material.transparent = true;
                material.alphaTest = 0.01;
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
            material.emissive = new THREE.Color(0x000000);
        } else if (mode === 'identity' || mode === 'identitylighting') {
            if (material.blending !== THREE.AdditiveBlending) {
                material.emissive = new THREE.Color(0x888888);
                material.emissiveIntensity = 0.5;
            }
        } else if (mode === 'vertex') {
            material.vertexColors = true;
        } else if (mode === 'exactvertex') {
            material.vertexColors = true;
        }
    }

    /**
     * Apply alpha generation mode
     */
    applyAlphaGen(material, alphaGen) {
        const mode = alphaGen.toLowerCase();

        if (mode === 'vertex' || mode === 'lightingspecular') {
            material.transparent = true;
        }
    }

    /**
     * Update material animations (for custom shader materials)
     */
    updateMaterialAnimation(material, time) {
        // For multi-stage ShaderMaterials, update the time uniform
        if (material.uniforms && material.uniforms.time) {
            material.uniforms.time.value = time;
        }

        // For single-stage MeshPhongMaterials with tcMod animations
        // (keeping old animation system for backward compatibility)
        if (material.userData && material.userData.shader && material.userData.stages) {
            const stages = material.userData.stages;
            if (stages.length > 0 && material.map) {
                const stage = stages[0];
                if (stage.tcMod && stage.tcMod.length > 0) {
                    this.applyTcModAnimations(material, stage.tcMod, time);
                }
            }
        }
    }

    /**
     * Apply tcMod animations to material texture
     */
    applyTcModAnimations(material, tcModArray, time) {
        let offset = new THREE.Vector2(0, 0);
        let rotation = 0;
        let scale = new THREE.Vector2(1, 1);
        let center = new THREE.Vector2(0.5, 0.5);

        tcModArray.forEach(mod => {
            switch (mod.type) {
                case 'scroll':
                    const sSpeed = parseFloat(mod.params[0]);
                    const tSpeed = parseFloat(mod.params[1]);
                    offset.x = (time * sSpeed) % 1.0;
                    offset.y = (time * tSpeed) % 1.0;
                    break;
                case 'rotate':
                    const degreesPerSec = parseFloat(mod.params[0]);
                    rotation = (time * degreesPerSec * Math.PI / 180) % (Math.PI * 2);
                    break;
                case 'scale':
                    scale.x = parseFloat(mod.params[0]);
                    scale.y = parseFloat(mod.params[1]);
                    break;
            }
        });

        material.map.offset.copy(offset);
        material.map.rotation = rotation;
        material.map.repeat.copy(scale);
        material.map.center.copy(center);
        material.map.needsUpdate = true;
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
