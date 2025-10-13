import * as THREE from 'three';
import { TGALoader } from 'three/examples/jsm/loaders/TGALoader.js';
import { Q3ShaderParser } from './Q3ShaderParser.js';
import { Q3ShaderMaterialSystem } from './Q3ShaderMaterial.js';

/**
 * MD3 (Quake 3 Model) Loader for Three.js
 *
 * Loads Quake 3 MD3 model files and creates Three.js geometry
 * MD3 models consist of three parts: head, upper, lower
 */
export class MD3Loader {
    constructor() {
        this.textureLoader = new THREE.TextureLoader();
        this.tgaLoader = new TGALoader();
        this.skinData = null; // Stores parsed skin file data
        this.shaderParser = new Q3ShaderParser();
        this.shaders = new Map(); // Stores loaded shader definitions
        this.shaderMaterialSystem = new Q3ShaderMaterialSystem(this);
    }

    /**
     * Load an MD3 file from a URL
     * @param {string} url - The URL to the MD3 file
     * @param {string} skinUrl - Optional URL to the .skin file
     * @returns {Promise<THREE.Group>} - Promise that resolves to a Three.js Group
     */
    async load(url, skinUrl = null) {
        // Load skin file if provided
        if (skinUrl) {
            await this.loadSkin(skinUrl);
        }

        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        return this.parse(arrayBuffer);
    }

    /**
     * Load and parse a .skin file
     * @param {string} url - The URL to the .skin file
     */
    async loadSkin(url) {
        try {
            const response = await fetch(url);
            const text = await response.text();
            this.skinData = this.parseSkin(text, url);
        } catch (error) {
            console.warn('Failed to load skin file:', error);
            this.skinData = null;
        }
    }

    /**
     * Load and parse shader files
     * @param {string} url - The URL to the .shader file
     */
    async loadShaderFile(url) {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const text = await response.text();
        this.shaderParser.parseShaderFile(text);
        this.shaders = this.shaderParser.getAllShaders();
    }

    /**
     * Load all shader files from a model directory
     * @param {string} baseUrl - Base URL to the model directory
     */
    async loadShaders(baseUrl) {
        try {
            // Try to load shader files from scripts directory
            const scriptsUrl = baseUrl.replace(/models\/players\/[^\/]+\/$/, 'scripts/');

            // Fetch directory listing to find .shader files
            // Since we can't list directories, we'll try common shader file patterns
            const shaderPatterns = [
                'model.shader',
                'models.shader',
                'player.shader',
                'players.shader'
            ];

            for (const pattern of shaderPatterns) {
                try {
                    await this.loadShaderFile(scriptsUrl + pattern);
                } catch (e) {
                    // Ignore, try next pattern
                }
            }
        } catch (error) {
            console.warn('Failed to load shaders:', error);
        }
    }

    /**
     * Load shader files for a specific model
     * @param {string} baseUrl - Base URL to the model directory (may or may not end with /)
     * @param {string} modelName - Name of the model
     */
    async loadShadersForModel(baseUrl, modelName) {
        try {
            // Ensure baseUrl ends with /
            if (!baseUrl.endsWith('/')) {
                baseUrl += '/';
            }

            // Get the scripts directory URL
            // For base Q3 models: /baseq3/models/players/major/ -> /baseq3/scripts/
            // For user models: /storage/models/extracted/xxx/models/players/niria/ -> /storage/models/extracted/xxx/scripts/
            const scriptsUrl = baseUrl.replace(/models\/players\/[^\/]+\/$/, 'scripts/');

            // For base Q3 models, load the bulk models.shader file that contains all model shaders
            // For user models, try model-specific shader files
            const isBaseQ3 = baseUrl.includes('/baseq3/');

            if (isBaseQ3) {
                // Base Q3 uses a single models.shader file with all model shaders
                try {
                    await this.loadShaderFile(scriptsUrl + 'models.shader');
                    console.log(`✅ Loaded base Q3 models.shader - ${this.shaders.size} shaders total`);
                } catch (e) {
                    console.warn('Failed to load base Q3 models.shader:', e);
                }
            } else {
                // User-uploaded models: try model-specific shader files
                const shaderPatterns = [
                    `${modelName}.shader`,
                    'model.shader',
                    'models.shader',
                    'player.shader',
                    'players.shader'
                ];

                for (const pattern of shaderPatterns) {
                    try {
                        await this.loadShaderFile(scriptsUrl + pattern);
                        // Successfully loaded a shader file
                        break;
                    } catch (e) {
                        // Silently ignore, try next pattern
                    }
                }
            }
        } catch (error) {
            console.warn('Failed to load shaders for model:', error);
        }
    }

    /**
     * Parse a .skin file content
     * @param {string} content - The skin file text content
     * @param {string} baseUrl - The base URL for resolving relative texture paths
     * @returns {Object} - Mapping of surface names to texture URLs
     */
    parseSkin(content, baseUrl) {
        const skinMap = {};
        const lines = content.split('\n');

        // Get the base directory from the skin URL
        const baseDir = baseUrl.substring(0, baseUrl.lastIndexOf('/') + 1);

        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith('//')) continue;

            const parts = trimmed.split(',');
            if (parts.length === 2) {
                const surfaceName = parts[0].trim();
                let texturePath = parts[1].trim();

                // Convert relative path to absolute URL
                // If texture path starts with models/, use it as-is from storage root
                if (texturePath.startsWith('models/')) {
                    // Check if this is a base Q3 model (in public/baseq3)
                    if (baseUrl.includes('/baseq3/')) {
                        // Base Q3 models: /baseq3/models/players/xxx/
                        // Texture path: models/players/xxx/texture.tga
                        // Result: /baseq3/models/players/xxx/texture.tga
                        const match = baseUrl.match(/\/baseq3\/models\/players\/([^\/]+)\//);
                        if (match) {
                            const modelName = match[1];
                            texturePath = `/baseq3/models/players/${modelName}/${texturePath.split('/').pop()}`;
                        }
                    } else {
                        // User-uploaded models
                        // Extract the base storage path from the skin URL
                        // e.g., /storage/models/extracted/worm-123/models/players/worm/
                        // We want: /storage/models/extracted/worm-123/
                        const match = baseUrl.match(/(\/storage\/models\/extracted\/[^\/]+)\//);
                        if (match) {
                            texturePath = match[1] + '/' + texturePath;
                        }
                    }
                } else {
                    // Relative to skin file location
                    texturePath = baseDir + texturePath;
                }

                skinMap[surfaceName] = texturePath;
            }
        }

        return skinMap;
    }

    /**
     * Parse MD3 binary data
     * @param {ArrayBuffer} buffer - The MD3 file data
     * @returns {THREE.Group} - Three.js Group containing the model
     */
    parse(buffer) {
        const view = new DataView(buffer);
        const header = this.parseHeader(view);

        if (header.ident !== 'IDP3') {
            throw new Error('Not a valid MD3 file');
        }

        const group = new THREE.Group();
        group.name = header.name;

        // Parse frames (animation keyframes)
        const frames = this.parseFrames(view, header);

        // Parse tags (attachment points for other models)
        const tags = this.parseTags(view, header);

        // Parse surfaces (mesh geometry)
        const surfaces = this.parseSurfaces(view, header);

        // Create meshes for each surface (non-async, textures load in background)
        surfaces.forEach(surface => {
            const geometry = this.createGeometry(surface); // Use first frame
            const material = new THREE.MeshPhongMaterial({
                color: 0xffffff,
                side: THREE.DoubleSide,
            });

            const mesh = new THREE.Mesh(geometry, material);
            mesh.name = surface.name;
            mesh.userData.surface = surface;
            mesh.userData.frames = frames;

            // Load texture and apply shaders asynchronously
            if (this.skinData && this.skinData[surface.name]) {
                const texturePath = this.skinData[surface.name];

                // Skip nodraw surfaces
                if (texturePath.includes('/nodraw') || texturePath.includes('common/nodraw')) {
                    mesh.visible = false;
                } else {
                    // Extract shader name from texture path
                    // Remove extension first
                    let shaderName = texturePath.replace(/\.(tga|jpg|png)$/i, '');

                    // Normalize to Q3 format: extract just "models/players/xxx/texture" part
                    // texturePath: /storage/models/extracted/xxx/models/players/niria/slashskate.TGA
                    // shaderName should be: models/players/niria/slashskate
                    const match = shaderName.match(/models\/players\/[^\/]+\/.+$/);
                    if (match) {
                        shaderName = match[0];
                    }

                    const shader = this.shaders.get(shaderName);

                    if (shader) {
                        console.log(`🎨 Found shader for "${shaderName}"`, shader);
                        // Apply shader properties and load texture
                        this.shaderMaterialSystem.createMaterialForShader(
                            shader,
                            texturePath,
                            surface.name
                        ).then(shaderMaterial => {
                            // Replace the basic material with custom shader material
                            mesh.material = shaderMaterial;
                            material.dispose();

                            if (shaderMaterial.userData.isMultiStage) {
                                console.log(`✅ Applied multi-stage shader material to ${surface.name} (${shaderMaterial.userData.numStages} stages)`);
                            } else {
                                console.log(`✅ Applied shader material to ${surface.name}`);
                            }
                        }).catch(err => {
                            console.warn(`Failed to create shader material for ${surface.name}:`, err);
                        });
                    } else {
                        console.log(`⚠️ No shader found for "${shaderName}" (total shaders: ${this.shaders.size})`);
                        // No shader, just load texture
                        this.loadTextureForMesh(texturePath, material).catch(err => {
                            console.warn(`Failed to load texture for ${surface.name}:`, err);
                        });
                    }
                }
            }

            group.add(mesh);
        });

        group.userData.header = header;
        group.userData.frames = frames;
        group.userData.tags = tags;

        return group;
    }

    /**
     * Parse MD3 header
     */
    parseHeader(view) {
        const header = {};

        // Read magic number "IDP3"
        header.ident = String.fromCharCode(
            view.getUint8(0),
            view.getUint8(1),
            view.getUint8(2),
            view.getUint8(3)
        );

        header.version = view.getInt32(4, true);
        header.name = this.readString(view, 8, 64);
        header.flags = view.getInt32(72, true);
        header.numFrames = view.getInt32(76, true);
        header.numTags = view.getInt32(80, true);
        header.numSurfaces = view.getInt32(84, true);
        header.numSkins = view.getInt32(88, true);
        header.ofsFrames = view.getInt32(92, true);
        header.ofsTags = view.getInt32(96, true);
        header.ofsSurfaces = view.getInt32(100, true);
        header.ofsEnd = view.getInt32(104, true);

        return header;
    }

    /**
     * Parse animation frames
     */
    parseFrames(view, header) {
        const frames = [];
        let offset = header.ofsFrames;

        for (let i = 0; i < header.numFrames; i++) {
            const frame = {};

            frame.minBounds = new THREE.Vector3(
                view.getFloat32(offset, true),
                view.getFloat32(offset + 4, true),
                view.getFloat32(offset + 8, true)
            );

            frame.maxBounds = new THREE.Vector3(
                view.getFloat32(offset + 12, true),
                view.getFloat32(offset + 16, true),
                view.getFloat32(offset + 20, true)
            );

            frame.localOrigin = new THREE.Vector3(
                view.getFloat32(offset + 24, true),
                view.getFloat32(offset + 28, true),
                view.getFloat32(offset + 32, true)
            );

            frame.radius = view.getFloat32(offset + 36, true);
            frame.name = this.readString(view, offset + 40, 16);

            frames.push(frame);
            offset += 56; // Size of MD3 frame
        }

        return frames;
    }

    /**
     * Parse tags (attachment points)
     */
    parseTags(view, header) {
        const tags = [];
        let offset = header.ofsTags;

        for (let i = 0; i < header.numFrames; i++) {
            const frameTags = [];

            for (let j = 0; j < header.numTags; j++) {
                const tag = {};
                tag.name = this.readString(view, offset, 64);

                tag.origin = new THREE.Vector3(
                    view.getFloat32(offset + 64, true),
                    view.getFloat32(offset + 68, true),
                    view.getFloat32(offset + 72, true)
                );

                // Read 3x3 rotation matrix
                tag.axis = [
                    new THREE.Vector3(
                        view.getFloat32(offset + 76, true),
                        view.getFloat32(offset + 80, true),
                        view.getFloat32(offset + 84, true)
                    ),
                    new THREE.Vector3(
                        view.getFloat32(offset + 88, true),
                        view.getFloat32(offset + 92, true),
                        view.getFloat32(offset + 96, true)
                    ),
                    new THREE.Vector3(
                        view.getFloat32(offset + 100, true),
                        view.getFloat32(offset + 104, true),
                        view.getFloat32(offset + 108, true)
                    )
                ];

                frameTags.push(tag);
                offset += 112; // Size of MD3 tag
            }

            tags.push(frameTags);
        }

        return tags;
    }

    /**
     * Parse surfaces (mesh data)
     */
    parseSurfaces(view, header) {
        const surfaces = [];
        let offset = header.ofsSurfaces;

        for (let i = 0; i < header.numSurfaces; i++) {
            const surface = {};

            surface.ident = this.readString(view, offset, 4);
            surface.name = this.readString(view, offset + 4, 64);
            surface.flags = view.getInt32(offset + 68, true);
            surface.numFrames = view.getInt32(offset + 72, true);
            surface.numShaders = view.getInt32(offset + 76, true);
            surface.numVerts = view.getInt32(offset + 80, true);
            surface.numTriangles = view.getInt32(offset + 84, true);
            surface.ofsTriangles = view.getInt32(offset + 88, true) + offset;
            surface.ofsShaders = view.getInt32(offset + 92, true) + offset;
            surface.ofsST = view.getInt32(offset + 96, true) + offset;
            surface.ofsXYZNormal = view.getInt32(offset + 100, true) + offset;
            surface.ofsEnd = view.getInt32(offset + 104, true);

            // Parse shaders (textures)
            surface.shaders = this.parseShaders(view, surface);

            // Parse triangles
            surface.triangles = this.parseTriangles(view, surface);

            // Parse texture coordinates
            surface.st = this.parseTexCoords(view, surface);

            // Parse vertices for all frames
            surface.vertices = this.parseVertices(view, surface);

            surfaces.push(surface);
            offset += surface.ofsEnd;
        }

        return surfaces;
    }

    /**
     * Parse shader (texture) information
     */
    parseShaders(view, surface) {
        const shaders = [];
        let offset = surface.ofsShaders;

        for (let i = 0; i < surface.numShaders; i++) {
            const shader = {};
            shader.name = this.readString(view, offset, 64);
            shader.shaderIndex = view.getInt32(offset + 64, true);
            shaders.push(shader);
            offset += 68;
        }

        return shaders;
    }

    /**
     * Parse triangle indices
     */
    parseTriangles(view, surface) {
        const triangles = [];
        let offset = surface.ofsTriangles;

        for (let i = 0; i < surface.numTriangles; i++) {
            triangles.push(
                view.getInt32(offset, true),
                view.getInt32(offset + 4, true),
                view.getInt32(offset + 8, true)
            );
            offset += 12;
        }

        return triangles;
    }

    /**
     * Parse texture coordinates
     */
    parseTexCoords(view, surface) {
        const st = [];
        let offset = surface.ofsST;

        for (let i = 0; i < surface.numVerts; i++) {
            st.push({
                s: view.getFloat32(offset, true),
                t: view.getFloat32(offset + 4, true)
            });
            offset += 8;
        }

        return st;
    }

    /**
     * Parse vertices for all frames
     */
    parseVertices(view, surface) {
        const frames = [];
        let offset = surface.ofsXYZNormal;

        for (let i = 0; i < surface.numFrames; i++) {
            const vertices = [];

            for (let j = 0; j < surface.numVerts; j++) {
                const x = view.getInt16(offset, true) / 64.0;
                const y = view.getInt16(offset + 2, true) / 64.0;
                const z = view.getInt16(offset + 4, true) / 64.0;

                const normal = this.decodeNormal(view.getUint16(offset + 6, true));

                vertices.push({ x, y, z, normal });
                offset += 8;
            }

            frames.push(vertices);
        }

        return frames;
    }

    /**
     * Decode MD3 compressed normal
     */
    decodeNormal(encoded) {
        const lat = (encoded >> 8) & 0xFF;
        const lng = encoded & 0xFF;

        const latRad = lat * Math.PI / 128.0;
        const lngRad = lng * Math.PI / 128.0;

        return new THREE.Vector3(
            Math.cos(latRad) * Math.sin(lngRad),
            Math.sin(latRad) * Math.sin(lngRad),
            Math.cos(lngRad)
        );
    }

    /**
     * Create Three.js geometry from surface data
     */
    createGeometry(surface) {
        const geometry = new THREE.BufferGeometry();

        const vertices = surface.vertices[0]; // Use first frame
        const positions = new Float32Array(surface.numVerts * 3);
        const normals = new Float32Array(surface.numVerts * 3);
        const uvs = new Float32Array(surface.numVerts * 2);

        // Fill position and normal arrays
        for (let i = 0; i < surface.numVerts; i++) {
            const v = vertices[i];
            positions[i * 3] = v.x;
            positions[i * 3 + 1] = v.y;
            positions[i * 3 + 2] = v.z;

            normals[i * 3] = v.normal.x;
            normals[i * 3 + 1] = v.normal.y;
            normals[i * 3 + 2] = v.normal.z;

            uvs[i * 2] = surface.st[i].s;
            uvs[i * 2 + 1] = surface.st[i].t;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('normal', new THREE.BufferAttribute(normals, 3));
        geometry.setAttribute('uv', new THREE.BufferAttribute(uvs, 2));
        geometry.setIndex(surface.triangles);

        geometry.computeBoundingSphere();

        return geometry;
    }

    /**
     * Read a null-terminated string from DataView
     */
    readString(view, offset, maxLength) {
        let str = '';
        for (let i = 0; i < maxLength; i++) {
            const char = view.getUint8(offset + i);
            if (char === 0) break;
            str += String.fromCharCode(char);
        }
        return str;
    }

    /**
     * Load texture for a mesh material (with case-insensitive fallback)
     * @param {string} url - Texture URL
     * @param {THREE.Material} material - Material to apply texture to
     */
    async loadTextureForMesh(url, material) {
        // Try lowercase extension first (most common on Linux servers)
        const lastDot = url.lastIndexOf('.');
        if (lastDot !== -1) {
            const extension = url.substring(lastDot + 1);
            const basePath = url.substring(0, lastDot + 1);

            // Always try lowercase first
            const lowercaseUrl = basePath + extension.toLowerCase();
            const uppercaseUrl = basePath + extension.toUpperCase();

            // Determine which loader to use based on extension
            const loader = extension.toLowerCase() === 'tga' ? this.tgaLoader : this.textureLoader;

            // Try lowercase first, then uppercase
            try {
                const texture = await this.tryLoadTexture(loader, lowercaseUrl);
                texture.wrapS = THREE.RepeatWrapping;
                texture.wrapT = THREE.RepeatWrapping;
                texture.flipY = false;
                material.map = texture;
                material.needsUpdate = true;

                // Apply shader effects if available
                this.applyShaderEffects(material, url);

                return texture;
            } catch (error) {
                // Try uppercase extension
                try {
                    const texture = await this.tryLoadTexture(loader, uppercaseUrl);
                    texture.wrapS = THREE.RepeatWrapping;
                    texture.wrapT = THREE.RepeatWrapping;
                    texture.flipY = false;
                    material.map = texture;
                    material.needsUpdate = true;

                    // Apply shader effects if available
                    this.applyShaderEffects(material, url);

                    return texture;
                } catch (secondError) {
                    console.warn(`Failed to load texture (tried both ${lowercaseUrl} and ${uppercaseUrl})`);
                    throw error;
                }
            }
        }

        // Fallback if no extension found
        throw new Error('Invalid texture URL: ' + url);
    }

    // Helper method to load texture with proper error handling
    tryLoadTexture(loader, url) {
        return new Promise((resolve, reject) => {
            loader.load(
                url,
                (texture) => resolve(texture),
                undefined,
                (error) => reject(error)
            );
        });
    }

    /**
     * Load texture for a surface (legacy method, kept for compatibility)
     * @param {string} url - Texture URL
     * @param {THREE.Material} material - Material to apply texture to
     */
    async loadTexture(url, material) {
        return this.loadTextureForMesh(url, material);
    }

    /**
     * Apply Q3 shader effects to a material
     * @param {THREE.Material} material - Material to apply shader to
     * @param {string} texturePath - The texture path to find shader for
     */
    applyShaderEffects(material, texturePath) {
        // Extract shader name from texture path
        // e.g., "models/players/niria/niria_h.tga" -> "models/players/niria/niria_h"
        const shaderName = texturePath.replace(/\.(tga|jpg|png)$/i, '');

        const shader = this.shaders.get(shaderName);
        if (!shader || !shader.stages || shader.stages.length === 0) {
            return;
        }

        // Store shader info on material for animation
        material.userData.shader = shader;
        material.userData.shaderName = shaderName;

        // Apply first stage as base
        const baseStage = shader.stages[0];

        // Apply blend function
        if (baseStage.blendFunc) {
            if (typeof baseStage.blendFunc === 'string') {
                switch (baseStage.blendFunc) {
                    case 'add':
                        material.blending = THREE.AdditiveBlending;
                        break;
                    case 'blend':
                        material.blending = THREE.NormalBlending;
                        material.transparent = true;
                        break;
                    case 'filter':
                        material.blending = THREE.MultiplyBlending;
                        break;
                }
            } else if (typeof baseStage.blendFunc === 'object') {
                // Custom blend function
                const { src, dst } = baseStage.blendFunc;
                if (src === 'GL_ONE' && dst === 'GL_ONE') {
                    material.blending = THREE.AdditiveBlending;
                }
            }
        }

        // Apply RGB generation
        if (baseStage.rgbGen) {
            if (baseStage.rgbGen === 'lightingdiffuse') {
                // Use default lighting
                material.emissive = new THREE.Color(0x000000);
            } else if (baseStage.rgbGen === 'identity') {
                // Full brightness
                material.emissive = new THREE.Color(0xffffff);
                material.emissiveIntensity = 0.5;
            }
        }

        // Apply environment mapping for stages with tcGen environment
        const envMapStage = shader.stages.find(stage => stage.tcGen === 'environment');
        if (envMapStage) {
            // Create a simple environment map (chrome effect)
            material.envMapIntensity = 0.3;
            material.metalness = 0.5;
            material.roughness = 0.5;
        }

        // Apply cull mode
        if (shader.cull) {
            switch (shader.cull) {
                case 'none':
                case 'disable':
                    material.side = THREE.DoubleSide;
                    break;
                case 'back':
                    material.side = THREE.FrontSide;
                    break;
                case 'front':
                    material.side = THREE.BackSide;
                    break;
            }
        }

        material.needsUpdate = true;
    }

    /**
     * Load a complete player model with all three parts (head, upper, lower)
     * @param {string} baseUrl - Base URL to the model directory
     * @param {string} modelName - Name of the model
     * @param {string} skinName - Skin name (default, blue, red)
     * @returns {Promise<THREE.Group>} - Complete player model
     */
    async loadPlayerModel(baseUrl, modelName, skinName = 'default') {
        // Ensure baseUrl ends with /
        if (!baseUrl.endsWith('/')) {
            baseUrl += '/';
        }

        const playerGroup = new THREE.Group();
        playerGroup.name = `player_${modelName}`;

        try {
            // Load shader files first
            await this.loadShadersForModel(baseUrl, modelName);

            // Load lower body (legs)
            const lowerUrl = `${baseUrl}lower.md3`;
            const lowerSkinUrl = `${baseUrl}lower_${skinName}.skin`;
            const lower = await this.load(lowerUrl, lowerSkinUrl);
            lower.name = 'lower';

            playerGroup.add(lower);

            // Find the tag_torso on the lower model
            const lowerTags = lower.userData.tags;
            if (lowerTags && lowerTags[0]) {
                const torsoTag = lowerTags[0].find(tag => tag.name === 'tag_torso');

                if (torsoTag) {
                    // Load upper body (torso)
                    const upperUrl = `${baseUrl}upper.md3`;
                    const upperSkinUrl = `${baseUrl}upper_${skinName}.skin`;
                    const upper = await this.load(upperUrl, upperSkinUrl);
                    upper.name = 'upper';

                    // Position upper body at torso tag
                    this.attachToTag(upper, torsoTag);
                    lower.add(upper); // ATTACH UPPER TO LOWER so it moves with legs!

                    // Find the tag_head on the upper model
                    const upperTags = upper.userData.tags;
                    if (upperTags && upperTags[0]) {
                        const headTag = upperTags[0].find(tag => tag.name === 'tag_head');

                        if (headTag) {
                            // Load head
                            const headUrl = `${baseUrl}head.md3`;
                            const headSkinUrl = `${baseUrl}head_${skinName}.skin`;
                            const head = await this.load(headUrl, headSkinUrl);
                            head.name = 'head';

                            // Position head at head tag
                            this.attachToTag(head, headTag);
                            upper.add(head); // Attach head to upper body
                        }
                    }
                }
            }

            // Store references for animation
            playerGroup.userData.lower = lower;
            playerGroup.userData.upper = lower.children.find(c => c.name === 'upper'); // Upper is child of lower now!
            playerGroup.userData.head = playerGroup.userData.upper?.children.find(c => c.name === 'head');

            // Load animation config
            const animConfigUrl = `${baseUrl}animation.cfg`;
            const animations = await this.loadAnimationConfig(animConfigUrl);
            if (animations) {
                playerGroup.userData.animations = animations;
            }

            return playerGroup;

        } catch (error) {
            console.error('Failed to load complete player model:', error);
            throw error;
        }
    }

    /**
     * Attach a model part to a tag (attachment point)
     * @param {THREE.Group} model - The model to attach
     * @param {Object} tag - The tag data with origin and axis
     */
    attachToTag(model, tag) {
        // Set position from tag origin
        model.position.copy(tag.origin);

        // Set rotation from tag axis (3x3 rotation matrix)
        const matrix = new THREE.Matrix4();
        matrix.set(
            tag.axis[0].x, tag.axis[1].x, tag.axis[2].x, 0,
            tag.axis[0].y, tag.axis[1].y, tag.axis[2].y, 0,
            tag.axis[0].z, tag.axis[1].z, tag.axis[2].z, 0,
            0, 0, 0, 1
        );

        const rotation = new THREE.Euler();
        rotation.setFromRotationMatrix(matrix);
        model.rotation.copy(rotation);
    }

    /**
     * Load and parse animation.cfg file
     * @param {string} url - URL to the animation.cfg file
     * @returns {Promise<Object>} - Animation definitions
     */
    async loadAnimationConfig(url) {
        try {
            const response = await fetch(url);
            const text = await response.text();
            return this.parseAnimationConfig(text);
        } catch (error) {
            console.warn('Failed to load animation.cfg:', error);
            return null;
        }
    }

    /**
     * Parse animation.cfg content
     * @param {string} content - The animation.cfg file content
     * @returns {Object} - Parsed animation definitions
     */
    parseAnimationConfig(content) {
        const animations = {
            sex: 'n',
            torso: {},
            legs: {},
            both: {}
        };

        const lines = content.split('\n');

        let lineNumber = 0;
        for (const line of lines) {
            lineNumber++;
            const trimmed = line.trim();

            // Skip empty lines and comments
            if (!trimmed || trimmed.startsWith('//')) continue;

            // Parse sex line
            if (trimmed.startsWith('sex')) {
                const parts = trimmed.split(/\s+/);
                if (parts.length >= 2) {
                    animations.sex = parts[1];
                }
                continue;
            }

            // Parse animation line: firstFrame numFrames loopingFrames fps // NAME
            const match = trimmed.match(/^(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+\/\/\s*(.+)$/);
            if (match) {
                const [, firstFrame, numFrames, loopingFrames, fps, rawName] = match;

                // STRIP TABS, COMMENTS, AND EXTRA WHITESPACE FROM NAME
                let name = rawName.trim();
                // Remove everything after tab or comment
                name = name.split('\t')[0].trim();
                name = name.split('(')[0].trim();
                name = name.split('//')[0].trim();

                const anim = {
                    name: name,
                    firstFrame: parseInt(firstFrame),
                    numFrames: parseInt(numFrames),
                    loopingFrames: parseInt(loopingFrames),
                    fps: parseInt(fps),
                    loop: parseInt(loopingFrames) > 0
                };


                // Categorize animation by prefix
                if (name.startsWith('BOTH_')) {
                    animations.both[name] = anim;
                } else if (name.startsWith('TORSO_')) {
                    animations.torso[name] = anim;
                } else if (name.startsWith('LEGS_')) {
                    animations.legs[name] = anim;
                }
            }
        }

        // Q3 ENGINE FRAME OFFSET LOGIC (from cg_players.c:210-214)
        // The animation.cfg defines frame numbers for the COMBINED model,
        // but MD3 files split the frames: lower.md3 and upper.md3 each contain
        // BOTH animations (0-82) followed by their specific animations.
        //
        // Q3 calculates: skip = LEGS_WALKCR.firstFrame - TORSO_GESTURE.firstFrame
        // Then subtracts skip from all LEGS animation frames.
        //
        // This is because:
        // - animation.cfg: BOTH(0-82), TORSO(83-145), LEGS(146-294)
        // - lower.md3: BOTH(0-82), LEGS(83-231) - LEGS frames are offset by -63
        // - upper.md3: BOTH(0-82), TORSO(83-145) - TORSO frames match cfg

        const firstLegsAnim = animations.legs['LEGS_WALKCR'];
        const firstTorsoAnim = animations.torso['TORSO_GESTURE'];

        if (firstLegsAnim && firstTorsoAnim) {
            const skip = firstLegsAnim.firstFrame - firstTorsoAnim.firstFrame;

            // Apply offset to all LEGS animations
            for (const anim of Object.values(animations.legs)) {
                anim.firstFrame -= skip;
            }
        }

        return animations;
    }

    /**
     * Create animation clips for a player model
     * @param {THREE.Group} playerModel - The complete player model
     * @returns {Array} - Array of THREE.AnimationClip objects
     */
    createAnimationClips(playerModel) {
        const animations = playerModel.userData.animations;
        if (!animations) {
            console.warn('No animation data found');
            return [];
        }

        const clips = [];
        const lower = playerModel.userData.lower;
        const upper = playerModel.userData.upper;

        // Create clips for legs animations
        Object.values(animations.legs).forEach(anim => {
            if (lower && lower.userData.frames) {
                const clip = this.createMD3AnimationClip(anim, lower, 'legs');
                if (clip) clips.push(clip);
            }
        });

        // Create clips for torso animations
        Object.values(animations.torso).forEach(anim => {
            if (upper && upper.userData.frames) {
                const clip = this.createMD3AnimationClip(anim, upper, 'torso');
                if (clip) clips.push(clip);
            }
        });

        return clips;
    }

    /**
     * Create a single animation clip from MD3 animation data
     * @param {Object} animDef - Animation definition from animation.cfg
     * @param {THREE.Group} modelPart - The model part (lower or upper)
     * @param {string} prefix - 'legs' or 'torso'
     * @returns {THREE.AnimationClip} - The animation clip
     */
    createMD3AnimationClip(animDef, modelPart, prefix) {
        const frames = modelPart.userData.frames;
        const surfaces = modelPart.children.filter(child => child.userData.surface);

        if (!frames || surfaces.length === 0) return null;

        const tracks = [];
        const duration = animDef.numFrames / animDef.fps;
        const timeStep = 1 / animDef.fps;

        // Create tracks for each mesh
        surfaces.forEach(mesh => {
            const surface = mesh.userData.surface;
            const numVerts = surface.numVerts;

            // Create position and normal tracks
            const times = [];
            const positions = [];
            const normals = [];

            for (let f = 0; f < animDef.numFrames; f++) {
                const frameIndex = animDef.firstFrame + f;
                if (frameIndex >= surface.vertices.length) break;

                times.push(f * timeStep);

                const frameVerts = surface.vertices[frameIndex];
                for (let v = 0; v < numVerts; v++) {
                    const vert = frameVerts[v];
                    positions.push(vert.x, vert.y, vert.z);
                    normals.push(vert.normal.x, vert.normal.y, vert.normal.z);
                }
            }

            // Create KeyframeTracks
            const posTrack = new THREE.VectorKeyframeTrack(
                `${mesh.name}.morphTargetInfluences`,
                times,
                positions
            );

            tracks.push(posTrack);
        });

        return new THREE.AnimationClip(`${prefix}_${animDef.name}`, duration, tracks);
    }
}
