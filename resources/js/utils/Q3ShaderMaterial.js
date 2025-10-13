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
        // Check for surfaceparm nodraw - these surfaces should not be rendered
        if (shader && shader.surfaceParms && shader.surfaceParms.includes('nodraw')) {
            console.log(`🚫 Skipping surface with nodraw: ${surfaceName}`);
            return null; // Return null to signal that this mesh should not be rendered
        }

        if (!shader || !shader.stages || shader.stages.length === 0) {
            // No shader, return basic material
            return this.createBasicMaterial();
        }

        // Check if single stage has effects that need custom shader
        const needsCustomShader = shader.stages.length > 1 ||
            (shader.stages.length === 1 && (
                // Check for tcMod animations
                (shader.stages[0].tcMod && shader.stages[0].tcMod.some(mod =>
                    mod.type === 'stretch' || mod.type === 'turbulent' || mod.type === 'rotate' ||
                    mod.type === 'scale' || mod.type === 'transform'
                )) ||
                // Check for rgbGen wave animations
                (shader.stages[0].rgbGen && shader.stages[0].rgbGen.toLowerCase().startsWith('wave')) ||
                // Check for alphaGen wave animations
                (shader.stages[0].alphaGen && shader.stages[0].alphaGen.toLowerCase().startsWith('wave')) ||
                // Check for alphaFunc (requires custom shader for proper support)
                shader.stages[0].alphaFunc
            )) ||
            // Check for deformVertexes (requires vertex shader support)
            (shader.deformVertexes && shader.deformVertexes.length > 0);

        if (needsCustomShader) {
            return await this.createMultiStageMaterial(shader, defaultTexturePath, surfaceName);
        }

        // Single stage shader without special effects - create simple material
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

            // Extract tcMod parameters
            let scrollSpeed = [0, 0];
            let stretchParams = [0, 0, 0, 0]; // [base, amplitude, phase, frequency]
            let rotateSpeed = 0; // degrees per second
            let turbulentParams = [0, 0, 0, 0]; // [base, amplitude, phase, frequency]
            let scaleParams = [1, 1]; // [sScale, tScale]
            let transformParams = [1, 0, 0, 1, 0, 0]; // [m00, m01, m10, m11, t0, t1]

            if (stage.tcMod && Array.isArray(stage.tcMod)) {
                const scrollMod = stage.tcMod.find(mod => mod.type === 'scroll');
                if (scrollMod && scrollMod.params) {
                    scrollSpeed = [parseFloat(scrollMod.params[0]) || 0, parseFloat(scrollMod.params[1]) || 0];
                }

                const stretchMod = stage.tcMod.find(mod => mod.type === 'stretch');
                if (stretchMod && stretchMod.params) {
                    // params: [waveFunc, base, amplitude, phase, frequency]
                    stretchParams = [
                        parseFloat(stretchMod.params[1]) || 1.0,  // base
                        parseFloat(stretchMod.params[2]) || 0.0,  // amplitude
                        parseFloat(stretchMod.params[3]) || 0.0,  // phase
                        parseFloat(stretchMod.params[4]) || 1.0   // frequency
                    ];
                }

                const rotateMod = stage.tcMod.find(mod => mod.type === 'rotate');
                if (rotateMod && rotateMod.params) {
                    rotateSpeed = parseFloat(rotateMod.params[0]) || 0;
                }

                const turbulentMod = stage.tcMod.find(mod => mod.type === 'turbulent');
                if (turbulentMod && turbulentMod.params) {
                    // params: [waveFunc, base, amplitude, phase, frequency]
                    turbulentParams = [
                        parseFloat(turbulentMod.params[1]) || 0.0,  // base
                        parseFloat(turbulentMod.params[2]) || 0.0,  // amplitude
                        parseFloat(turbulentMod.params[3]) || 0.0,  // phase
                        parseFloat(turbulentMod.params[4]) || 0.0   // frequency
                    ];
                }

                const scaleMod = stage.tcMod.find(mod => mod.type === 'scale');
                if (scaleMod && scaleMod.params) {
                    scaleParams = [
                        parseFloat(scaleMod.params[0]) || 1.0,
                        parseFloat(scaleMod.params[1]) || 1.0
                    ];
                }

                const transformMod = stage.tcMod.find(mod => mod.type === 'transform');
                if (transformMod && transformMod.params) {
                    transformParams = [
                        parseFloat(transformMod.params[0]) || 1.0,  // m00
                        parseFloat(transformMod.params[1]) || 0.0,  // m01
                        parseFloat(transformMod.params[2]) || 0.0,  // m10
                        parseFloat(transformMod.params[3]) || 1.0,  // m11
                        parseFloat(transformMod.params[4]) || 0.0,  // t0
                        parseFloat(transformMod.params[5]) || 0.0   // t1
                    ];
                }
            }

            // Check for tcGen environment
            const tcGenType = stage.tcGen || 'base';

            // Parse rgbGen wave parameters
            let rgbGenWaveParams = [0, 0, 0, 0]; // [base, amplitude, phase, frequency]
            let rgbGenWaveFuncType = 0; // 0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth
            let rgbGenType = 'identity';
            if (stage.rgbGen) {
                const rgbGenStr = stage.rgbGen.toLowerCase();
                if (rgbGenStr === 'lightingdiffuse') {
                    rgbGenType = 'lightingDiffuse';
                } else if (rgbGenStr === 'vertex' || rgbGenStr === 'exactvertex') {
                    // vertex colors - we don't parse vertex colors from MD3, so treat as identity
                    rgbGenType = 'vertex';
                } else if (rgbGenStr === 'entity' || rgbGenStr === 'oneminusentity') {
                    // Entity modulation - no entity context in viewer, treat as identity
                    rgbGenType = 'entity';
                } else if (rgbGenStr === 'identitylighting') {
                    // identityLighting - (1,1,1) * tr.identityLight (~0.7)
                    rgbGenType = 'identityLighting';
                } else if (rgbGenStr.startsWith('wave')) {
                    rgbGenType = 'wave';
                    // Parse: "wave triangle .5 1 0 .2" or "wave sin 1 1 0 1"
                    const parts = rgbGenStr.split(/\s+/);
                    if (parts.length >= 6) {
                        const waveFunc = parts[1]; // sin, triangle, square, sawtooth, inversesawtooth
                        if (waveFunc === 'triangle') rgbGenWaveFuncType = 1;
                        else if (waveFunc === 'square') rgbGenWaveFuncType = 2;
                        else if (waveFunc === 'sawtooth') rgbGenWaveFuncType = 3;
                        else if (waveFunc === 'inversesawtooth') rgbGenWaveFuncType = 4;
                        // else defaults to 0 (sin)

                        rgbGenWaveParams = [
                            parseFloat(parts[2]) || 0.0,    // base
                            parseFloat(parts[3]) || 0.0,    // amplitude
                            parseFloat(parts[4]) || 0.0,    // phase
                            parseFloat(parts[5]) || 0.0     // frequency
                        ];
                    }
                }
            }

            // Parse alphaGen wave parameters (similar to rgbGen wave)
            let alphaGenWaveParams = [0, 0, 0, 0]; // [base, amplitude, phase, frequency]
            let alphaGenWaveFuncType = 0; // 0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth
            let alphaGenType = 'identity';
            if (stage.alphaGen) {
                const alphaGenStr = stage.alphaGen.toLowerCase();
                if (alphaGenStr === 'lightingspecular') {
                    alphaGenType = 'lightingSpecular';
                } else if (alphaGenStr === 'vertex' || alphaGenStr === 'exactvertex') {
                    alphaGenType = 'vertex';
                } else if (alphaGenStr === 'entity' || alphaGenStr === 'oneminusentity') {
                    alphaGenType = 'entity';
                } else if (alphaGenStr.startsWith('wave')) {
                    alphaGenType = 'wave';
                    // Parse: "wave sin 0 1 1 .1"
                    const parts = alphaGenStr.split(/\s+/);
                    if (parts.length >= 6) {
                        const waveFunc = parts[1]; // sin, triangle, square, sawtooth, inversesawtooth
                        if (waveFunc === 'triangle') alphaGenWaveFuncType = 1;
                        else if (waveFunc === 'square') alphaGenWaveFuncType = 2;
                        else if (waveFunc === 'sawtooth') alphaGenWaveFuncType = 3;
                        else if (waveFunc === 'inversesawtooth') alphaGenWaveFuncType = 4;
                        // else defaults to 0 (sin)

                        alphaGenWaveParams = [
                            parseFloat(parts[2]) || 0.0,    // base
                            parseFloat(parts[3]) || 0.0,    // amplitude
                            parseFloat(parts[4]) || 0.0,    // phase
                            parseFloat(parts[5]) || 0.0     // frequency
                        ];
                    }
                } else if (alphaGenStr.startsWith('const')) {
                    alphaGenType = 'const';
                    const parts = alphaGenStr.split(/\s+/);
                    if (parts.length >= 2) {
                        // Store const value in first param
                        alphaGenWaveParams[0] = parseFloat(parts[1]) || 1.0;
                    }
                }
            }

            // Store stage data for shader uniforms
            stageData.push({
                blendFunc: this.parseBlendFunc(stage.blendFunc),
                tcMod: stage.tcMod || [],
                rgbGen: rgbGenType,
                rgbGenWaveParams: rgbGenWaveParams,
                rgbGenWaveFuncType: rgbGenWaveFuncType,
                alphaGen: alphaGenType,
                alphaGenWaveParams: alphaGenWaveParams,
                alphaGenWaveFuncType: alphaGenWaveFuncType,
                tcGen: tcGenType,
                scrollSpeed: scrollSpeed,
                stretchParams: stretchParams,
                rotateSpeed: rotateSpeed,
                turbulentParams: turbulentParams,
                scaleParams: scaleParams,
                transformParams: transformParams,
            });
        }

        // Determine if the BASE stage (stage 0) uses blending that requires transparency
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

        // Check if base stage uses alpha blending (blend) - also needs transparency
        const hasAlphaBlending = (() => {
            const blendFunc = baseStage.blendFunc;
            if (!blendFunc) return false;

            if (typeof blendFunc === 'string' && blendFunc.toLowerCase() === 'blend') {
                return true;
            }
            if (typeof blendFunc === 'object' && blendFunc !== null &&
                blendFunc.src === 'GL_SRC_ALPHA' && blendFunc.dst === 'GL_ONE_MINUS_SRC_ALPHA') {
                return true;
            }
            return false;
        })();

        // Parse alphaFunc and depthWrite from first stage
        const firstStage = stages[0];
        let alphaTest = 0.0;
        let depthWrite = !hasAdditiveBlending; // Default: disable depth write for additive

        if (firstStage.alphaFunc) {
            // Convert Q3 alphaFunc to Three.js alphaTest value
            // GE128 = >= 128/255 = >= 0.5
            // GT0 = > 0/255 = > 0.0 (very small value)
            // LT128 = < 128/255 = < 0.5 (not commonly used)
            if (firstStage.alphaFunc === 'GE128') {
                alphaTest = 0.5;
            } else if (firstStage.alphaFunc === 'GT0') {
                alphaTest = 0.01;
            } else if (firstStage.alphaFunc === 'LT128') {
                alphaTest = 0.49; // Less than 0.5
            }
        }

        if (firstStage.depthWrite) {
            depthWrite = true;
        }

        // Check if any stage uses alphaGen effects (requires transparency)
        const hasAlphaGenEffects = stageData.some(stage =>
            stage.alphaGen === 'wave' || stage.alphaGen === 'lightingSpecular'
        );

        // Parse deformVertexes (can have multiple deformations)
        const deformVertexes = shader.deformVertexes || [];
        let hasDeformWave = false;
        let deformWaveSpread = 0;
        let deformWaveParams = [0, 0, 0, 0]; // [base, amplitude, phase, frequency]
        let deformWaveFuncType = 0; // 0=sin, 1=triangle, etc.

        let hasDeformMove = false;
        let deformMoveDir = [0, 0, 0];
        let deformMoveParams = [0, 0, 0, 0];
        let deformMoveFuncType = 0;

        let hasDeformNormal = false;
        let deformNormalFreq = 0;
        let deformNormalAmp = 0;

        let hasDeformAutoSprite = false;
        let hasDeformAutoSprite2 = false;

        for (const deform of deformVertexes) {
            if (deform.type === 'wave' && !hasDeformWave) {
                hasDeformWave = true;
                // params: [spread, waveFunc, base, amplitude, phase, frequency]
                deformWaveSpread = parseFloat(deform.params[0]) || 0;
                const waveFunc = (deform.params[1] || 'sin').toLowerCase();
                if (waveFunc === 'triangle') deformWaveFuncType = 1;
                else if (waveFunc === 'square') deformWaveFuncType = 2;
                else if (waveFunc === 'sawtooth') deformWaveFuncType = 3;
                else if (waveFunc === 'inversesawtooth') deformWaveFuncType = 4;

                deformWaveParams = [
                    parseFloat(deform.params[2]) || 0,  // base
                    parseFloat(deform.params[3]) || 0,  // amplitude
                    parseFloat(deform.params[4]) || 0,  // phase
                    parseFloat(deform.params[5]) || 0   // frequency
                ];
            } else if (deform.type === 'move') {
                hasDeformMove = true;
                // params: [x, y, z, waveFunc, base, amplitude, phase, frequency]
                deformMoveDir = [
                    parseFloat(deform.params[0]) || 0,
                    parseFloat(deform.params[1]) || 0,
                    parseFloat(deform.params[2]) || 0
                ];
                const waveFunc = (deform.params[3] || 'sin').toLowerCase();
                if (waveFunc === 'triangle') deformMoveFuncType = 1;
                else if (waveFunc === 'square') deformMoveFuncType = 2;
                else if (waveFunc === 'sawtooth') deformMoveFuncType = 3;
                else if (waveFunc === 'inversesawtooth') deformMoveFuncType = 4;

                deformMoveParams = [
                    parseFloat(deform.params[4]) || 0,  // base
                    parseFloat(deform.params[5]) || 0,  // amplitude
                    parseFloat(deform.params[6]) || 0,  // phase
                    parseFloat(deform.params[7]) || 0   // frequency
                ];
            } else if (deform.type === 'normal') {
                hasDeformNormal = true;
                // params: [frequency, amplitude]
                deformNormalFreq = parseFloat(deform.params[0]) || 0;
                deformNormalAmp = parseFloat(deform.params[1]) || 0;
            } else if (deform.type === 'autosprite') {
                hasDeformAutoSprite = true;
            } else if (deform.type === 'autosprite2') {
                hasDeformAutoSprite2 = true;
            }
        }

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

                // Stretch params for tcMod stretch (vec4: base, amplitude, phase, frequency)
                stretchParams0: { value: new THREE.Vector4(...stageData[0]?.stretchParams || [0, 0, 0, 0]) },
                stretchParams1: { value: new THREE.Vector4(...stageData[1]?.stretchParams || [0, 0, 0, 0]) },
                stretchParams2: { value: new THREE.Vector4(...stageData[2]?.stretchParams || [0, 0, 0, 0]) },
                stretchParams3: { value: new THREE.Vector4(...stageData[3]?.stretchParams || [0, 0, 0, 0]) },

                // Rotate speeds for tcMod rotate (degrees per second)
                rotateSpeed0: { value: stageData[0]?.rotateSpeed || 0 },
                rotateSpeed1: { value: stageData[1]?.rotateSpeed || 0 },
                rotateSpeed2: { value: stageData[2]?.rotateSpeed || 0 },
                rotateSpeed3: { value: stageData[3]?.rotateSpeed || 0 },

                // Turbulent params for tcMod turbulent (vec4: base, amplitude, phase, frequency)
                turbulentParams0: { value: new THREE.Vector4(...stageData[0]?.turbulentParams || [0, 0, 0, 0]) },
                turbulentParams1: { value: new THREE.Vector4(...stageData[1]?.turbulentParams || [0, 0, 0, 0]) },
                turbulentParams2: { value: new THREE.Vector4(...stageData[2]?.turbulentParams || [0, 0, 0, 0]) },
                turbulentParams3: { value: new THREE.Vector4(...stageData[3]?.turbulentParams || [0, 0, 0, 0]) },

                // Scale params for tcMod scale (vec2: sScale, tScale)
                scaleParams0: { value: new THREE.Vector2(...stageData[0]?.scaleParams || [1, 1]) },
                scaleParams1: { value: new THREE.Vector2(...stageData[1]?.scaleParams || [1, 1]) },
                scaleParams2: { value: new THREE.Vector2(...stageData[2]?.scaleParams || [1, 1]) },
                scaleParams3: { value: new THREE.Vector2(...stageData[3]?.scaleParams || [1, 1]) },

                // Transform params for tcMod transform (vec3: m00,m01,t0 vec3: m10,m11,t1)
                transformRow0_0: { value: new THREE.Vector3(stageData[0]?.transformParams[0] || 1, stageData[0]?.transformParams[1] || 0, stageData[0]?.transformParams[4] || 0) },
                transformRow1_0: { value: new THREE.Vector3(stageData[0]?.transformParams[2] || 0, stageData[0]?.transformParams[3] || 1, stageData[0]?.transformParams[5] || 0) },
                transformRow0_1: { value: new THREE.Vector3(stageData[1]?.transformParams[0] || 1, stageData[1]?.transformParams[1] || 0, stageData[1]?.transformParams[4] || 0) },
                transformRow1_1: { value: new THREE.Vector3(stageData[1]?.transformParams[2] || 0, stageData[1]?.transformParams[3] || 1, stageData[1]?.transformParams[5] || 0) },
                transformRow0_2: { value: new THREE.Vector3(stageData[2]?.transformParams[0] || 1, stageData[2]?.transformParams[1] || 0, stageData[2]?.transformParams[4] || 0) },
                transformRow1_2: { value: new THREE.Vector3(stageData[2]?.transformParams[2] || 0, stageData[2]?.transformParams[3] || 1, stageData[2]?.transformParams[5] || 0) },
                transformRow0_3: { value: new THREE.Vector3(stageData[3]?.transformParams[0] || 1, stageData[3]?.transformParams[1] || 0, stageData[3]?.transformParams[4] || 0) },
                transformRow1_3: { value: new THREE.Vector3(stageData[3]?.transformParams[2] || 0, stageData[3]?.transformParams[3] || 1, stageData[3]?.transformParams[5] || 0) },

                // tcGen types (0=base, 1=environment)
                tcGenType0: { value: stageData[0]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType1: { value: stageData[1]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType2: { value: stageData[2]?.tcGen === 'environment' ? 1 : 0 },
                tcGenType3: { value: stageData[3]?.tcGen === 'environment' ? 1 : 0 },

                // rgbGen types (0=identity, 1=lightingDiffuse, 2=wave)
                rgbGenType0: { value: stageData[0]?.rgbGen === 'lightingDiffuse' ? 1 : (stageData[0]?.rgbGen === 'wave' ? 2 : 0) },
                rgbGenType1: { value: stageData[1]?.rgbGen === 'lightingDiffuse' ? 1 : (stageData[1]?.rgbGen === 'wave' ? 2 : 0) },
                rgbGenType2: { value: stageData[2]?.rgbGen === 'lightingDiffuse' ? 1 : (stageData[2]?.rgbGen === 'wave' ? 2 : 0) },
                rgbGenType3: { value: stageData[3]?.rgbGen === 'lightingDiffuse' ? 1 : (stageData[3]?.rgbGen === 'wave' ? 2 : 0) },

                // rgbGen wave params (vec4: base, amplitude, phase, frequency)
                rgbGenWaveParams0: { value: new THREE.Vector4(...stageData[0]?.rgbGenWaveParams || [0, 0, 0, 0]) },
                rgbGenWaveParams1: { value: new THREE.Vector4(...stageData[1]?.rgbGenWaveParams || [0, 0, 0, 0]) },
                rgbGenWaveParams2: { value: new THREE.Vector4(...stageData[2]?.rgbGenWaveParams || [0, 0, 0, 0]) },
                rgbGenWaveParams3: { value: new THREE.Vector4(...stageData[3]?.rgbGenWaveParams || [0, 0, 0, 0]) },

                // rgbGen wave function types (0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth)
                rgbGenWaveFuncType0: { value: stageData[0]?.rgbGenWaveFuncType || 0 },
                rgbGenWaveFuncType1: { value: stageData[1]?.rgbGenWaveFuncType || 0 },
                rgbGenWaveFuncType2: { value: stageData[2]?.rgbGenWaveFuncType || 0 },
                rgbGenWaveFuncType3: { value: stageData[3]?.rgbGenWaveFuncType || 0 },

                // alphaGen types (0=identity, 1=wave, 2=lightingSpecular, 3=const)
                alphaGenType0: { value: this.getAlphaGenTypeValue(stageData[0]?.alphaGen) },
                alphaGenType1: { value: this.getAlphaGenTypeValue(stageData[1]?.alphaGen) },
                alphaGenType2: { value: this.getAlphaGenTypeValue(stageData[2]?.alphaGen) },
                alphaGenType3: { value: this.getAlphaGenTypeValue(stageData[3]?.alphaGen) },

                // alphaGen wave params (vec4: base, amplitude, phase, frequency)
                alphaGenWaveParams0: { value: new THREE.Vector4(...stageData[0]?.alphaGenWaveParams || [0, 0, 0, 0]) },
                alphaGenWaveParams1: { value: new THREE.Vector4(...stageData[1]?.alphaGenWaveParams || [0, 0, 0, 0]) },
                alphaGenWaveParams2: { value: new THREE.Vector4(...stageData[2]?.alphaGenWaveParams || [0, 0, 0, 0]) },
                alphaGenWaveParams3: { value: new THREE.Vector4(...stageData[3]?.alphaGenWaveParams || [0, 0, 0, 0]) },

                // alphaGen wave function types (0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth)
                alphaGenWaveFuncType0: { value: stageData[0]?.alphaGenWaveFuncType || 0 },
                alphaGenWaveFuncType1: { value: stageData[1]?.alphaGenWaveFuncType || 0 },
                alphaGenWaveFuncType2: { value: stageData[2]?.alphaGenWaveFuncType || 0 },
                alphaGenWaveFuncType3: { value: stageData[3]?.alphaGenWaveFuncType || 0 },

                // Lighting uniforms (will be updated from scene lights)
                ambientLightColor: { value: new THREE.Color(0x999999) },  // Default ambient
                directionalLightDirection: { value: new THREE.Vector3(0.5, 0.8, 0.5).normalize() },  // Default direction
                directionalLightColor: { value: new THREE.Color(0xCCCCCC) },  // Default directional

                // deformVertexes uniforms
                hasDeformWave: { value: hasDeformWave ? 1 : 0 },
                deformWaveSpread: { value: deformWaveSpread },
                deformWaveParams: { value: new THREE.Vector4(...deformWaveParams) },
                deformWaveFuncType: { value: deformWaveFuncType },

                hasDeformMove: { value: hasDeformMove ? 1 : 0 },
                deformMoveDir: { value: new THREE.Vector3(...deformMoveDir) },
                deformMoveParams: { value: new THREE.Vector4(...deformMoveParams) },
                deformMoveFuncType: { value: deformMoveFuncType },

                hasDeformNormal: { value: hasDeformNormal ? 1 : 0 },
                deformNormalFreq: { value: deformNormalFreq },
                deformNormalAmp: { value: deformNormalAmp },

                hasDeformAutoSprite: { value: hasDeformAutoSprite ? 1 : 0 },
                hasDeformAutoSprite2: { value: hasDeformAutoSprite2 ? 1 : 0 },
            },

            vertexShader: this.getVertexShader(),
            fragmentShader: this.getFragmentShader(),

            side: shader.cull ? this.getCullMode(shader.cull) : THREE.DoubleSide,
            transparent: alphaTest > 0 || hasAdditiveBlending || hasAlphaBlending || hasAlphaGenEffects, // Enable transparency
            alphaTest: alphaTest, // Set alpha test threshold
            depthWrite: depthWrite,
            depthTest: true,
            blending: hasAdditiveBlending ? THREE.AdditiveBlending : THREE.NormalBlending,
            lights: false, // We handle lighting manually
        });

        if (hasAdditiveBlending) {
            console.log(`✨ Additive blending detected - material will be translucent`);
        }
        if (hasAlphaBlending) {
            console.log(`✨ Alpha blending detected - material will use transparency`);
        }
        if (alphaTest > 0) {
            console.log(`✂️ Alpha test enabled: ${firstStage.alphaFunc} (threshold: ${alphaTest})`);
        }
        if (depthWrite && (hasAdditiveBlending || hasAlphaBlending)) {
            console.log(`📝 Depth write enabled (explicit from shader)`);
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
            uniform float time;

            // deformVertexes wave
            uniform int hasDeformWave;
            uniform float deformWaveSpread;
            uniform vec4 deformWaveParams; // x=base, y=amplitude, z=phase, w=frequency
            uniform int deformWaveFuncType;

            // deformVertexes move
            uniform int hasDeformMove;
            uniform vec3 deformMoveDir;
            uniform vec4 deformMoveParams; // x=base, y=amplitude, z=phase, w=frequency
            uniform int deformMoveFuncType;

            // deformVertexes normal
            uniform int hasDeformNormal;
            uniform float deformNormalFreq;
            uniform float deformNormalAmp;

            // deformVertexes autoSprite / autoSprite2
            uniform int hasDeformAutoSprite;
            uniform int hasDeformAutoSprite2;

            varying vec2 vUv;
            varying vec2 vEnvUv;
            varying vec3 vNormal;
            varying vec3 vPosition;

            // Generate wave function value (same as fragment shader)
            float generateWaveFunction(int funcType, float t) {
                if (funcType == 0) {
                    return sin(6.28318 * t);
                } else if (funcType == 1) {
                    if (t < 0.25) return t * 4.0;
                    else if (t < 0.75) return 2.0 - t * 4.0;
                    else return -4.0 + t * 4.0;
                } else if (funcType == 2) {
                    return (t < 0.5) ? 1.0 : -1.0;
                } else if (funcType == 3) {
                    return t;
                } else if (funcType == 4) {
                    return 1.0 - t;
                }
                return 0.0;
            }

            void main() {
                vUv = uv;

                // Start with original position
                vec3 deformedPosition = position;
                vec3 deformedNormal = normal;

                // Apply deformVertexes wave (deform along normal with wave function)
                if (hasDeformWave == 1 && abs(deformWaveParams.w) > 0.001) {
                    // Calculate offset based on vertex position (spread)
                    // Spread determines how much vertex position affects wave phase
                    float off = (position.x + position.y + position.z) * deformWaveSpread;

                    // Calculate wave phase with offset
                    float t = fract(time * deformWaveParams.w + deformWaveParams.z + off);

                    // Generate wave value
                    float waveFunc = generateWaveFunction(deformWaveFuncType, t);
                    float scale = deformWaveParams.x + deformWaveParams.y * waveFunc;

                    // Deform along normal
                    deformedPosition += normal * scale;
                }

                // Apply deformVertexes move (move all vertices along direction with wave)
                if (hasDeformMove == 1 && abs(deformMoveParams.w) > 0.001) {
                    // Calculate wave phase (no spread, all vertices move together)
                    float t = fract(time * deformMoveParams.w + deformMoveParams.z);

                    // Generate wave value
                    float waveFunc = generateWaveFunction(deformMoveFuncType, t);
                    float scale = deformMoveParams.x + deformMoveParams.y * waveFunc;

                    // Move along direction vector
                    deformedPosition += deformMoveDir * scale;
                }

                // Apply deformVertexes normal (deform along normal with noise-like pattern)
                if (hasDeformNormal == 1 && abs(deformNormalFreq) > 0.001) {
                    // Use vertex position as seed for pseudo-random offset
                    // This creates a noise-like deformation pattern
                    float phase = (position.x + position.y + position.z) * deformNormalFreq;
                    float noise = sin(phase + time) * deformNormalAmp;

                    // Deform along normal
                    deformedPosition += normal * noise;
                }

                // Apply deformVertexes autoSprite (billboard - face camera)
                // AutoSprite makes quads face the camera using view-space left/up vectors
                if (hasDeformAutoSprite == 1) {
                    // Q3 autoSprite: rebuild quads centered at midpoint, facing camera
                    // In vertex shader, we approximate by working in view space

                    // Don't apply any deformation - just keep the original position
                    // The actual billboarding will be done by the modelViewMatrix manipulation
                    // This is a limitation of vertex shaders - proper autoSprite needs geometry shader
                    //
                    // For now, we disable the deformation and let the mesh render normally
                    // A proper implementation would require CPU-side geometry rebuilding
                    // or a geometry shader (not available in WebGL 1.0)
                }

                // Apply deformVertexes autoSprite2 (billboard variant - complete camera facing)
                if (hasDeformAutoSprite2 == 1) {
                    // Same limitation as autoSprite
                }

                vNormal = normalize(normalMatrix * deformedNormal);
                vPosition = (modelViewMatrix * vec4(deformedPosition, 1.0)).xyz;

                // Calculate environment-mapped UVs (sphere mapping)
                vec3 viewNormal = normalize(vNormal);
                vEnvUv = viewNormal.xy * 0.5 + 0.5;

                gl_Position = projectionMatrix * modelViewMatrix * vec4(deformedPosition, 1.0);
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

            uniform vec4 stretchParams0; // x=base, y=amplitude, z=phase, w=frequency
            uniform vec4 stretchParams1;
            uniform vec4 stretchParams2;
            uniform vec4 stretchParams3;

            uniform float rotateSpeed0; // degrees per second
            uniform float rotateSpeed1;
            uniform float rotateSpeed2;
            uniform float rotateSpeed3;

            uniform vec4 turbulentParams0; // x=base, y=amplitude, z=phase, w=frequency
            uniform vec4 turbulentParams1;
            uniform vec4 turbulentParams2;
            uniform vec4 turbulentParams3;

            uniform vec2 scaleParams0; // x=sScale, y=tScale
            uniform vec2 scaleParams1;
            uniform vec2 scaleParams2;
            uniform vec2 scaleParams3;

            uniform vec3 transformRow0_0; // x=m00, y=m01, z=t0
            uniform vec3 transformRow1_0; // x=m10, y=m11, z=t1
            uniform vec3 transformRow0_1;
            uniform vec3 transformRow1_1;
            uniform vec3 transformRow0_2;
            uniform vec3 transformRow1_2;
            uniform vec3 transformRow0_3;
            uniform vec3 transformRow1_3;

            uniform int tcGenType0; // 0=base, 1=environment
            uniform int tcGenType1;
            uniform int tcGenType2;
            uniform int tcGenType3;

            uniform int rgbGenType0; // 0=identity, 1=lightingDiffuse, 2=wave
            uniform int rgbGenType1;
            uniform int rgbGenType2;
            uniform int rgbGenType3;

            uniform vec4 rgbGenWaveParams0; // x=base, y=amplitude, z=phase, w=frequency
            uniform vec4 rgbGenWaveParams1;
            uniform vec4 rgbGenWaveParams2;
            uniform vec4 rgbGenWaveParams3;

            uniform int rgbGenWaveFuncType0; // 0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth
            uniform int rgbGenWaveFuncType1;
            uniform int rgbGenWaveFuncType2;
            uniform int rgbGenWaveFuncType3;

            uniform int alphaGenType0; // 0=identity, 1=wave, 2=lightingSpecular, 3=const
            uniform int alphaGenType1;
            uniform int alphaGenType2;
            uniform int alphaGenType3;

            uniform vec4 alphaGenWaveParams0; // x=base, y=amplitude, z=phase, w=frequency
            uniform vec4 alphaGenWaveParams1;
            uniform vec4 alphaGenWaveParams2;
            uniform vec4 alphaGenWaveParams3;

            uniform int alphaGenWaveFuncType0; // 0=sin, 1=triangle, 2=square, 3=sawtooth, 4=inversesawtooth
            uniform int alphaGenWaveFuncType1;
            uniform int alphaGenWaveFuncType2;
            uniform int alphaGenWaveFuncType3;

            uniform vec3 ambientLightColor;
            uniform vec3 directionalLightDirection;
            uniform vec3 directionalLightColor;

            varying vec2 vUv;
            varying vec2 vEnvUv;
            varying vec3 vNormal;
            varying vec3 vPosition;

            // Apply tcMod stretch to UV coordinates
            vec2 applyStretch(vec2 uv, vec4 stretch) {
                if (abs(stretch.w) > 0.001) { // frequency != 0 means stretch is active
                    float waveValue = stretch.x + stretch.y * sin(6.28318 * (time * stretch.w + stretch.z));
                    float scale = 1.0 / waveValue; // Quake 3 inverts the wave value
                    return (uv - 0.5) * scale + 0.5; // Scale around center (0.5, 0.5)
                }
                return uv;
            }

            // Apply tcMod rotate to UV coordinates
            vec2 applyRotate(vec2 uv, float degsPerSecond) {
                if (abs(degsPerSecond) > 0.001) { // rotation speed != 0 means rotate is active
                    float angle = -degsPerSecond * time * 0.01745329; // Convert to radians (pi/180)
                    float s = sin(angle);
                    float c = cos(angle);
                    // Rotate around center (0.5, 0.5)
                    vec2 centered = uv - 0.5;
                    vec2 rotated = vec2(
                        centered.x * c - centered.y * s,
                        centered.x * s + centered.y * c
                    );
                    return rotated + 0.5;
                }
                return uv;
            }

            // Apply tcMod turbulent to UV coordinates
            vec2 applyTurbulent(vec2 uv, vec4 turb, vec3 vertexPos) {
                if (abs(turb.w) > 0.001) { // frequency != 0 means turbulent is active
                    float now = turb.z + time * turb.w; // phase + time * frequency
                    float scale = 1.0 / 128.0 * 0.125;
                    // Approximation using sin - Q3 uses lookup table
                    uv.x += sin((vertexPos.x + vertexPos.z) * scale + now) * turb.y;
                    uv.y += sin(vertexPos.y * scale + now) * turb.y;
                }
                return uv;
            }

            // Apply tcMod scale to UV coordinates
            vec2 applyScale(vec2 uv, vec2 scale) {
                if (abs(scale.x - 1.0) > 0.001 || abs(scale.y - 1.0) > 0.001) {
                    return uv * scale;
                }
                return uv;
            }

            // Apply tcMod transform to UV coordinates
            vec2 applyTransform(vec2 uv, vec3 row0, vec3 row1) {
                if (abs(row0.x - 1.0) > 0.001 || abs(row0.y) > 0.001 || abs(row0.z) > 0.001 ||
                    abs(row1.x) > 0.001 || abs(row1.y - 1.0) > 0.001 || abs(row1.z) > 0.001) {
                    // Matrix multiplication: dst = src * matrix + translate
                    return vec2(
                        uv.x * row0.x + uv.y * row1.x + row0.z,
                        uv.x * row0.y + uv.y * row1.y + row1.z
                    );
                }
                return uv;
            }

            // Generate wave function value based on type
            // Based on Q3 engine wave table generation (tr_init.c)
            float generateWaveFunction(int funcType, float t) {
                if (funcType == 0) {
                    // Sin wave: -1 to +1
                    return sin(6.28318 * t); // 2*PI*t
                } else if (funcType == 1) {
                    // Triangle wave: 0→1→-1→0
                    if (t < 0.25) {
                        return t * 4.0; // 0 → 1
                    } else if (t < 0.75) {
                        return 2.0 - t * 4.0; // 1 → -1
                    } else {
                        return -4.0 + t * 4.0; // -1 → 0
                    }
                } else if (funcType == 2) {
                    // Square wave: +1 or -1
                    return (t < 0.5) ? 1.0 : -1.0;
                } else if (funcType == 3) {
                    // Sawtooth wave: 0 → 1 (linear ramp up)
                    return t;
                } else if (funcType == 4) {
                    // Inverse sawtooth wave: 1 → 0 (linear ramp down)
                    return 1.0 - t;
                }
                return 0.0;
            }

            // Apply rgbGen wave modulation to color
            vec3 applyRgbGenWave(vec3 color, vec4 waveParams, int funcType) {
                if (abs(waveParams.w) > 0.001) { // frequency != 0 means wave is active
                    // Calculate wave phase: time * frequency + phase
                    float t = fract(time * waveParams.w + waveParams.z);

                    // Generate wave function value
                    float waveFunc = generateWaveFunction(funcType, t);

                    // Wave formula: base + amplitude * waveFunction
                    float waveValue = waveParams.x + waveParams.y * waveFunc;

                    // Clamp to [0, 1] range and apply to color
                    waveValue = clamp(waveValue, 0.0, 1.0);
                    return color * waveValue;
                }
                return color;
            }

            // Apply alphaGen wave modulation to alpha
            float applyAlphaGenWave(float alpha, vec4 waveParams, int funcType) {
                if (abs(waveParams.w) > 0.001) { // frequency != 0 means wave is active
                    // Calculate wave phase: time * frequency + phase
                    float t = fract(time * waveParams.w + waveParams.z);

                    // Generate wave function value
                    float waveFunc = generateWaveFunction(funcType, t);

                    // Wave formula: base + amplitude * waveFunction
                    float waveValue = waveParams.x + waveParams.y * waveFunc;

                    // Clamp to [0, 1] range
                    return clamp(waveValue, 0.0, 1.0);
                }
                return alpha;
            }

            // Calculate specular alpha (rim lighting effect)
            // Based on Q3 engine RB_CalcSpecularAlpha (tr_shade_calc.c)
            float calcSpecularAlpha() {
                // Fixed light position (as in Q3 engine)
                vec3 lightOrigin = vec3(-960.0, 1980.0, 96.0);

                // Light direction from vertex to light
                vec3 lightDir = normalize(lightOrigin - vPosition);

                // Calculate reflection vector
                float d = dot(vNormal, lightDir);
                vec3 reflected = vNormal * 2.0 * d - lightDir;

                // View direction from vertex to camera
                vec3 viewDir = normalize(-vPosition); // vPosition is in view space, camera is at origin

                // Calculate specular term
                float l = dot(reflected, viewDir);

                if (l < 0.0) {
                    return 0.0;
                } else {
                    // Apply power 4 (squared twice, as in Q3)
                    l = l * l;
                    l = l * l;
                    return clamp(l, 0.0, 1.0);
                }
            }

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

                // Calculate UV coordinates for stage 0 (apply in Q3 order)
                vec2 uv0 = (tcGenType0 == 1) ? vEnvUv : vUv;
                uv0 += scrollSpeed0 * time;
                uv0 = applyScale(uv0, scaleParams0);
                uv0 = applyTransform(uv0, transformRow0_0, transformRow1_0);
                uv0 = applyTurbulent(uv0, turbulentParams0, vPosition);
                uv0 = applyStretch(uv0, stretchParams0);
                uv0 = applyRotate(uv0, rotateSpeed0);

                // Start with base stage
                vec4 color = texture2D(tStage0, uv0);

                // Apply rgbGen
                if (rgbGenType0 == 1) {
                    // lightingDiffuse - multiply texture by lighting
                    color.rgb *= lighting;
                } else if (rgbGenType0 == 2) {
                    // wave
                    color.rgb = applyRgbGenWave(color.rgb, rgbGenWaveParams0, rgbGenWaveFuncType0);
                }

                // Apply alphaGen
                if (alphaGenType0 == 1) {
                    // wave
                    color.a = applyAlphaGenWave(color.a, alphaGenWaveParams0, alphaGenWaveFuncType0);
                } else if (alphaGenType0 == 2) {
                    // lightingSpecular
                    color.a = calcSpecularAlpha();
                } else if (alphaGenType0 == 3) {
                    // const
                    color.a = alphaGenWaveParams0.x;
                }

                // Blend additional stages on top
                if (numStages > 1) {
                    vec2 uv1 = (tcGenType1 == 1) ? vEnvUv : vUv;
                    uv1 += scrollSpeed1 * time;
                    uv1 = applyScale(uv1, scaleParams1);
                    uv1 = applyTransform(uv1, transformRow0_1, transformRow1_1);
                    uv1 = applyTurbulent(uv1, turbulentParams1, vPosition);
                    uv1 = applyStretch(uv1, stretchParams1);
                    uv1 = applyRotate(uv1, rotateSpeed1);
                    vec4 stage1 = texture2D(tStage1, uv1);

                    // Apply rgbGen
                    if (rgbGenType1 == 1) {
                        stage1.rgb *= lighting;
                    } else if (rgbGenType1 == 2) {
                        stage1.rgb = applyRgbGenWave(stage1.rgb, rgbGenWaveParams1, rgbGenWaveFuncType1);
                    }

                    // Apply alphaGen
                    if (alphaGenType1 == 1) {
                        stage1.a = applyAlphaGenWave(stage1.a, alphaGenWaveParams1, alphaGenWaveFuncType1);
                    } else if (alphaGenType1 == 2) {
                        stage1.a = calcSpecularAlpha();
                    } else if (alphaGenType1 == 3) {
                        stage1.a = alphaGenWaveParams1.x;
                    }

                    color = blendColors(stage1, color, blendMode1);
                }

                if (numStages > 2) {
                    vec2 uv2 = (tcGenType2 == 1) ? vEnvUv : vUv;
                    uv2 += scrollSpeed2 * time;
                    uv2 = applyScale(uv2, scaleParams2);
                    uv2 = applyTransform(uv2, transformRow0_2, transformRow1_2);
                    uv2 = applyTurbulent(uv2, turbulentParams2, vPosition);
                    uv2 = applyStretch(uv2, stretchParams2);
                    uv2 = applyRotate(uv2, rotateSpeed2);
                    vec4 stage2 = texture2D(tStage2, uv2);

                    // Apply rgbGen
                    if (rgbGenType2 == 1) {
                        stage2.rgb *= lighting;
                    } else if (rgbGenType2 == 2) {
                        stage2.rgb = applyRgbGenWave(stage2.rgb, rgbGenWaveParams2, rgbGenWaveFuncType2);
                    }

                    // Apply alphaGen
                    if (alphaGenType2 == 1) {
                        stage2.a = applyAlphaGenWave(stage2.a, alphaGenWaveParams2, alphaGenWaveFuncType2);
                    } else if (alphaGenType2 == 2) {
                        stage2.a = calcSpecularAlpha();
                    } else if (alphaGenType2 == 3) {
                        stage2.a = alphaGenWaveParams2.x;
                    }

                    color = blendColors(stage2, color, blendMode2);
                }

                if (numStages > 3) {
                    vec2 uv3 = (tcGenType3 == 1) ? vEnvUv : vUv;
                    uv3 += scrollSpeed3 * time;
                    uv3 = applyScale(uv3, scaleParams3);
                    uv3 = applyTransform(uv3, transformRow0_3, transformRow1_3);
                    uv3 = applyTurbulent(uv3, turbulentParams3, vPosition);
                    uv3 = applyStretch(uv3, stretchParams3);
                    uv3 = applyRotate(uv3, rotateSpeed3);
                    vec4 stage3 = texture2D(tStage3, uv3);

                    // Apply rgbGen
                    if (rgbGenType3 == 1) {
                        stage3.rgb *= lighting;
                    } else if (rgbGenType3 == 2) {
                        stage3.rgb = applyRgbGenWave(stage3.rgb, rgbGenWaveParams3, rgbGenWaveFuncType3);
                    }

                    // Apply alphaGen
                    if (alphaGenType3 == 1) {
                        stage3.a = applyAlphaGenWave(stage3.a, alphaGenWaveParams3, alphaGenWaveFuncType3);
                    } else if (alphaGenType3 == 2) {
                        stage3.a = calcSpecularAlpha();
                    } else if (alphaGenType3 == 3) {
                        stage3.a = alphaGenWaveParams3.x;
                    }

                    color = blendColors(stage3, color, blendMode3);
                }

                gl_FragColor = color;
            }
        `;
    }

    /**
     * Get numeric value for alphaGen type
     * 0=identity, 1=wave, 2=lightingSpecular, 3=const
     */
    getAlphaGenTypeValue(alphaGen) {
        if (!alphaGen || alphaGen === 'identity') return 0;
        if (alphaGen === 'wave') return 1;
        if (alphaGen === 'lightingSpecular') return 2;
        if (alphaGen === 'const') return 3;
        return 0; // Default to identity
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
     * Update lighting uniforms from scene lights
     */
    updateLightingFromScene(material, scene) {
        if (!material.uniforms) return;

        // Find lights in the scene
        let ambientLight = null;
        let directionalLight = null;

        scene.traverse((object) => {
            if (object.isAmbientLight && !ambientLight) {
                ambientLight = object;
            } else if (object.isDirectionalLight && !directionalLight) {
                directionalLight = object;
            }
        });

        // Update ambient light
        if (ambientLight && material.uniforms.ambientLightColor) {
            const color = ambientLight.color.clone().multiplyScalar(ambientLight.intensity);
            material.uniforms.ambientLightColor.value.copy(color);
        }

        // Update directional light
        if (directionalLight) {
            if (material.uniforms.directionalLightDirection) {
                // Get light direction in view space (normalize and negate for direction TO light)
                const direction = new THREE.Vector3();
                directionalLight.getWorldDirection(direction);
                direction.negate(); // Flip to point TO the light
                material.uniforms.directionalLightDirection.value.copy(direction);
            }
            if (material.uniforms.directionalLightColor) {
                const color = directionalLight.color.clone().multiplyScalar(directionalLight.intensity);
                material.uniforms.directionalLightColor.value.copy(color);
            }
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
