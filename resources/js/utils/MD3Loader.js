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
        this.fallbackBaseUrl = null; // Fallback base URL for missing textures (e.g., baseq3 path)
        this.pathCache = new Map(); // Cache for case-insensitive path resolutions
    }

    /**
     * Fetch a file - the server handles case-insensitive resolution
     * No more trying multiple variations client-side!
     *
     * @param {string} url - The URL to fetch
     * @returns {Promise<Response>} - Fetch response
     */
    async fetchCaseInsensitive(url) {
        // Server-side handles all case-insensitive resolution now
        // Just fetch once - no more trying multiple variations!
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`File not found: ${url}`);
        }
        return response;
    }

    /**
     * Load an MD3 file from a URL
     * @param {string} url - The URL to the MD3 file
     * @param {string} skinUrl - Optional URL to the .skin file
     * @param {string} baseUrlOverride - Optional override for texture base path (for weapon skin packs)
     * @returns {Promise<THREE.Group>} - Promise that resolves to a Three.js Group
     */
    async load(url, skinUrl = null, baseUrlOverride = null) {
        // Load skin file if provided
        if (skinUrl) {
            await this.loadSkin(skinUrl);
        }

        // Extract base URL for texture loading (everything up to the last /)
        // For weapon skin packs: use override to point textures to pk3 even when loading baseq3 MD3
        const baseUrl = baseUrlOverride || url.substring(0, url.lastIndexOf('/') + 1);

        const response = await this.fetchCaseInsensitive(url);
        const arrayBuffer = await response.arrayBuffer();
        return this.parse(arrayBuffer, baseUrl);
    }

    /**
     * Load and parse a .skin file
     * @param {string} url - The URL to the .skin file
     */
    async loadSkin(url) {
        try {
            const response = await this.fetchCaseInsensitive(url);
            if (!response.ok) {
                // 404 or other error - no skin file exists
                this.skinData = null;
                return;
            }
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
        const response = await this.fetchCaseInsensitive(url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const text = await response.text();
        this.shaderParser.parseShaderFile(text);
        this.shaders = this.shaderParser.getAllShaders();

        // Log registered shaders for debugging
        const shaderNames = Array.from(this.shaders.keys());
        console.log(`📋 Registered ${shaderNames.length} shader(s) from ${url.split('/').pop()}`);

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
     * Load ALL shader files from a pk3's scripts directory using the API
     * @param {number} modelId - Model ID to get shaders for
     * @param {string} scriptsUrl - Scripts directory URL (e.g., /storage/models/extracted/xxx/scripts/)
     */
    async loadAllShadersFromPk3(modelId, scriptsUrl) {
        try {
            // Get list of ALL shader files from backend API
            const response = await fetch(`/models/${modelId}/shaders`);
            if (!response.ok) {
                console.warn(`⚠️ Failed to get shader list for model ${modelId}`);
                return 0;
            }

            const data = await response.json();
            const shaderFiles = data.shaders || [];

            if (shaderFiles.length === 0) {
                console.log(`ℹ️ No custom shaders in pk3 for model ${modelId}`);
                return 0;
            }

            console.log(`📄 Found ${shaderFiles.length} shader files in pk3:`, shaderFiles);

            // Load ALL shader files
            let loadedCount = 0;
            for (const shaderFile of shaderFiles) {
                try {
                    await this.loadShaderFile(scriptsUrl + shaderFile);
                    console.log(`✅ Loaded shader: ${shaderFile}`);
                    loadedCount++;
                } catch (e) {
                    console.warn(`⚠️ Failed to load shader ${shaderFile}:`, e);
                }
            }

            return loadedCount;
        } catch (error) {
            console.warn('Failed to load shaders from pk3:', error);
            return 0;
        }
    }

    /**
     * Load shader files for a specific model
     * @param {string} baseUrl - Base URL to the model directory (may or may not end with /)
     * @param {string} modelName - Name of the model
     * @param {number|null} modelId - Model ID for API calls (optional, for pk3 models)
     */
    async loadShadersForModel(baseUrl, modelName, modelId = null) {
        try {
            // Ensure baseUrl ends with /
            if (!baseUrl.endsWith('/')) {
                baseUrl += '/';
            }

            // Get the scripts directory URL
            // For base Q3 models: /baseq3/models/players/major/ -> /baseq3/scripts/
            // For user models: /storage/models/extracted/xxx/models/players/niria/ -> /storage/models/extracted/xxx/scripts/
            const scriptsUrl = baseUrl.replace(/models\/players\/[^\/]+\/$/, 'scripts/');

            const isBaseQ3 = baseUrl.includes('/baseq3/');

            // ALWAYS load baseq3 shaders FIRST as the foundation (like Quake engine does)
            // This ensures all models have the default Q3 shaders available
            try {
                await this.loadShaderFile('/baseq3/scripts/models.shader');
                console.log(`✅ Loaded baseq3 model shaders (foundation) - ${this.shaders.size} shaders total`);
            } catch (e) {
                console.warn('⚠️ Failed to load baseq3 model shaders:', e);
            }

            // If this is a user-uploaded pk3, load ALL shader files from it
            if (!isBaseQ3 && modelId) {
                // Use API to get ALL shader files in the pk3
                const loadedCount = await this.loadAllShadersFromPk3(modelId, scriptsUrl);
                if (loadedCount > 0) {
                    console.log(`✅ Loaded ${loadedCount} custom pk3 shaders (override baseq3)`);
                } else {
                    console.log(`ℹ️ No custom shaders in pk3 - using baseq3 shaders for model ${modelName}`);
                }
            }
        } catch (error) {
            console.warn('Failed to load shaders for model:', error);
        }
    }

    /**
     * Load shader files for a weapon model
     * @param {string} baseUrl - Base URL to the weapon directory (e.g., /storage/models/extracted/weapon1test-1760789758/models/weapons2/plasma/)
     * @param {string} weaponName - Name of the weapon (e.g., 'plasma')
     * @param {number|null} modelId - Model ID for fetching shader list from API (null for base Q3 weapons)
     */
    async loadShadersForWeapon(baseUrl, weaponName, modelId = null) {
        try {
            // Ensure baseUrl ends with /
            if (!baseUrl.endsWith('/')) {
                baseUrl += '/';
            }

            // Get the scripts directory URL
            // For base Q3 weapons: /baseq3/models/weapons2/plasma/ -> /baseq3/scripts/
            // For user weapons: /storage/models/extracted/xxx/models/weapons2/plasma/ -> /storage/models/extracted/xxx/scripts/
            const scriptsUrl = baseUrl.replace(/models\/weapons2\/[^\/]+\/$/, 'scripts/');

            const isBaseQ3 = baseUrl.includes('/baseq3/');

            // ALWAYS load baseq3 shaders FIRST as the foundation (like Quake engine does)
            // This ensures all weapons have the default Q3 shaders available
            try {
                await this.loadShaderFile('/baseq3/scripts/models.shader');
                console.log(`✅ Loaded baseq3 weapon shaders (foundation) - ${this.shaders.size} shaders total`);
            } catch (e) {
                console.warn('⚠️ Failed to load baseq3 weapon shaders:', e);
            }

            // If this is a user-uploaded pk3, load its custom shaders which will OVERRIDE baseq3
            if (!isBaseQ3 && modelId) {
                try {
                    console.log(`📡 Fetching custom shader list from API for model ${modelId}...`);
                    const response = await fetch(`/models/${modelId}/shaders`);

                    if (!response.ok) {
                        throw new Error(`Failed to fetch shader list: ${response.statusText}`);
                    }

                    const data = await response.json();
                    const shaderFiles = data.shaders || [];

                    console.log(`📋 Found ${shaderFiles.length} custom shader file(s) in pk3:`, shaderFiles);

                    if (shaderFiles.length === 0) {
                        console.log(`ℹ️ No custom shaders in pk3 - using baseq3 shaders for weapon ${weaponName}`);
                    } else {
                        // Load each custom shader file which OVERWRITES baseq3 shaders
                        let loadedCount = 0;
                        for (const shaderFile of shaderFiles) {
                            try {
                                await this.loadShaderFile(scriptsUrl + shaderFile);
                                console.log(`✅ Loaded custom shader (overrides baseq3): ${shaderFile}`);
                                loadedCount++;
                            } catch (e) {
                                console.warn(`⚠️ Failed to load shader file ${shaderFile}:`, e);
                            }
                        }

                        console.log(`✅ Loaded ${loadedCount}/${shaderFiles.length} custom shader(s) - these override baseq3`);
                    }
                } catch (error) {
                    console.warn('⚠️ Error loading custom shaders from pk3, using baseq3 shaders:', error);
                }
            }
        } catch (error) {
            console.warn('Failed to load shaders for weapon:', error);
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
                        // e.g., /storage/models/extracted/worm-123/models/players/worm/head_red.skin
                        // We want: /storage/models/extracted/worm-123/
                        const match = baseUrl.match(/(\/storage\/models\/extracted\/[^\/]+)/);
                        if (match) {
                            texturePath = match[1] + '/' + texturePath;
                            console.log(`✅ Resolved texture path: ${texturePath} (from baseUrl: ${baseUrl})`);
                        } else {
                            console.warn(`❌ Failed to match baseUrl pattern: ${baseUrl}`);
                        }
                    }
                } else {
                    // Relative to skin file location
                    texturePath = baseDir + texturePath;
                    console.log(`✅ Resolved texture path (relative): ${texturePath}`);
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
    parse(buffer, baseUrl = null) {
        const view = new DataView(buffer);
        const header = this.parseHeader(view);

        if (header.ident !== 'IDP3') {
            throw new Error('Not a valid MD3 file');
        }

        const group = new THREE.Group();
        group.name = header.name;

        // Store baseUrl for texture loading
        const textureBaseUrl = baseUrl;

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
            mesh.userData.surface = surface.name; // Store surface name as string
            mesh.userData.surfaceData = surface; // Store full surface object for animations
            mesh.userData.frames = frames;

            // MD3 surfaces often have a _1, _2, etc suffix for LOD (level of detail)
            // Strip the suffix to match skin file surface names
            const skinSurfaceName = surface.name.replace(/_\d+$/, '');

            console.log(`🔍 Processing surface: "${surface.name}" -> skin lookup: "${skinSurfaceName}", has skinData: ${!!this.skinData}, has texture mapping: ${!!(this.skinData && this.skinData[skinSurfaceName])}`);

            // Load texture and apply shaders asynchronously
            if (this.skinData && this.skinData[skinSurfaceName]) {
                const texturePath = this.skinData[skinSurfaceName];

                // Skip nodraw surfaces
                if (texturePath.includes('/nodraw') || texturePath.includes('common/nodraw')) {
                    mesh.visible = false;
                } else {
                    // Extract shader name from texture path
                    // Remove extension first
                    let shaderName = texturePath.replace(/\.(tga|jpg|png)$/i, '');

                    // Normalize to Q3 format: extract just "models/players/xxx/texture" or "models/weapons2/xxx/texture" part
                    // texturePath: /storage/models/extracted/xxx/models/players/niria/slashskate.TGA
                    // shaderName should be: models/players/niria/slashskate
                    // texturePath: /storage/models/extracted/xxx/models/weapons2/plasma/plasma.TGA
                    // shaderName should be: models/weapons2/plasma/plasma
                    const match = shaderName.match(/models\/(players|weapons2)\/[^\/]+\/.+$/);
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
                            // Handle null return (nodraw surfaces)
                            if (shaderMaterial === null) {
                                // Hide the mesh - it's a nodraw surface
                                mesh.visible = false;
                                material.dispose();
                                return;
                            }

                            // Replace the basic material with custom shader material
                            mesh.material = shaderMaterial;
                            material.dispose();

                        }).catch(err => {
                            console.warn(`Failed to create shader material for ${surface.name}:`, err);
                        });
                    } else {
                        console.log(`⚠️ No shader found for "${shaderName}" (total shaders: ${this.shaders.size}) - loading texture directly for surface: ${surface.name}`);
                        // No shader, just load texture (with fallback if available)
                        this.loadTextureForMesh(texturePath, material, this.fallbackBaseUrl).then(() => {
                            console.log(`✅ Texture loaded and applied to ${surface.name}: ${texturePath}`);
                        }).catch(err => {
                            console.warn(`❌ Failed to load texture for ${surface.name}:`, err);
                        });
                    }
                }
            } else if (!this.skinData && surface.shaders && surface.shaders.length > 0) {
                // No skin file - use MD3's embedded shader names (for weapons, items, etc.)
                const embeddedShaderName = surface.shaders[0].name;
                console.log(`📦 No skin file - using embedded shader name: "${embeddedShaderName}" for surface: ${surface.name}`);

                // The embedded shader name might be empty or a default placeholder
                if (embeddedShaderName && embeddedShaderName.trim() && !embeddedShaderName.includes('default')) {
                    // Remove existing extension (TGA, jpg, png, etc.) from embedded name
                    // embeddedShaderName: "models/weapons2/bfg/bfg.TGA" -> "models/weapons2/bfg/bfg"
                    const shaderName = embeddedShaderName.replace(/\.(tga|jpg|png|jpeg)$/i, '');

                    // Check if we have a shader definition for this name
                    const shader = this.shaders.get(shaderName);

                    if (shader) {
                        console.log(`🎨 Found shader for "${shaderName}"`, shader);
                        // Construct texture path based on model type
                        let texturePath;
                        if (textureBaseUrl) {
                            // User-uploaded model - construct full path to main texture
                            // textureBaseUrl: /storage/models/extracted/weapon1test-1760789758/models/weapons2/plasma/
                            // shaderName: models/weapons2/plasma/plasma
                            // Result: /storage/models/extracted/weapon1test-1760789758/models/weapons2/plasma/plasma.tga
                            const fileName = shaderName.split('/').pop();
                            texturePath = `${textureBaseUrl}${fileName}.tga`;
                            console.log(`🔫 Resolved weapon texture path: ${texturePath} (from base: ${textureBaseUrl})`);
                        } else {
                            // Base Q3 model - prepend /baseq3/
                            texturePath = shaderName.startsWith('models/')
                                ? `/baseq3/${shaderName}.tga`
                                : `/${shaderName}.tga`;
                        }

                        // Apply shader properties and load texture
                        this.shaderMaterialSystem.createMaterialForShader(
                            shader,
                            texturePath,
                            surface.name
                        ).then(shaderMaterial => {
                            // Handle null return (nodraw surfaces)
                            if (shaderMaterial === null) {
                                mesh.visible = false;
                                material.dispose();
                                return;
                            }

                            // Replace the basic material with custom shader material
                            mesh.material = shaderMaterial;
                            material.dispose();

                        }).catch(err => {
                            console.warn(`Failed to create shader material for ${surface.name}:`, err);
                        });
                    } else {
                        console.log(`⚠️ No shader found for "${shaderName}" - loading texture directly for surface: ${surface.name}`);
                        // No shader - load texture directly
                        let texturePath;
                        if (shaderName.startsWith('models/')) {
                            // Extract just the filename from the shader name
                            const fileName = shaderName.split('/').pop();
                            // Use the base URL from the model's location
                            texturePath = textureBaseUrl ? `${textureBaseUrl}${fileName}.tga` : `/baseq3/${shaderName}.tga`;
                        } else {
                            texturePath = `/${shaderName}.tga`;
                        }

                        console.log(`🔫 Attempting to load weapon texture: ${texturePath}`);

                        this.loadTextureForMesh(texturePath, material, null).then(() => {
                            console.log(`✅ Texture loaded for ${surface.name}: ${texturePath}`);
                        }).catch(err => {
                            console.warn(`❌ Failed to load embedded shader texture for ${surface.name}:`, err);
                        });
                    }
                } else {
                    console.log(`⚠️ Embedded shader name is empty or default for ${surface.name}`);
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
     * Load texture for a mesh material (with case-insensitive and multi-extension fallback)
     * @param {string} url - Texture URL
     * @param {THREE.Material} material - Material to apply texture to
     * @param {string} fallbackBaseUrl - Optional fallback base URL (e.g., for loading from base model if skin pack is missing textures)
     */
    async loadTextureForMesh(url, material, fallbackBaseUrl = null) {
        const lastDot = url.lastIndexOf('.');
        if (lastDot === -1) {
            throw new Error('Invalid texture URL: ' + url);
        }

        const extension = url.substring(lastDot + 1);
        const basePath = url.substring(0, lastDot);
        const filename = url.substring(url.lastIndexOf('/') + 1);

        // Try multiple extension variants
        // Order: lowercase original, uppercase original, jpg, JPG, jpeg, JPEG, png, PNG
        const extensionsToTry = [
            extension.toLowerCase(),
            extension.toUpperCase(),
        ];

        // If original extension is .tga, also try jpg/jpeg/png as fallbacks
        if (extension.toLowerCase() === 'tga') {
            extensionsToTry.push('jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG');
        }

        let lastError = null;

        // First try the original path with all extensions
        for (const ext of extensionsToTry) {
            try {
                const testUrl = basePath + '.' + ext;
                const loader = ext.toLowerCase() === 'tga' ? this.tgaLoader : this.textureLoader;

                const texture = await this.tryLoadTexture(loader, testUrl);
                texture.wrapS = THREE.RepeatWrapping;
                texture.wrapT = THREE.RepeatWrapping;
                texture.flipY = false;
                material.map = texture;
                material.needsUpdate = true;

                // Apply shader effects if available
                this.applyShaderEffects(material, url);

                return texture;
            } catch (error) {
                lastError = error;
                // Continue to next extension
            }
        }

        // If original path failed and we have a fallback base URL, try loading from there
        if (fallbackBaseUrl) {
            // Extract the relative path from the original URL (everything after /storage/models/extracted/xxx/)
            // e.g., /storage/models/extracted/xxx/textures/sfx/file.tga -> textures/sfx/file
            let relativePath = url;
            if (url.includes('/extracted/')) {
                // Find the part after /extracted/xxx/
                const extractedIndex = url.indexOf('/extracted/');
                const afterExtracted = url.substring(extractedIndex + '/extracted/'.length);
                // Skip the PK3 folder name (e.g., "anarki-model-skin-mysticsurfer-1760897528-8328/")
                const firstSlashAfterExtracted = afterExtracted.indexOf('/');
                if (firstSlashAfterExtracted !== -1) {
                    relativePath = afterExtracted.substring(firstSlashAfterExtracted + 1);
                }
            }

            const fallbackBasePath = fallbackBaseUrl + relativePath.substring(0, relativePath.lastIndexOf('.'));

            for (const ext of extensionsToTry) {
                try {
                    const testUrl = fallbackBasePath + '.' + ext;
                    const loader = ext.toLowerCase() === 'tga' ? this.tgaLoader : this.textureLoader;

                    const texture = await this.tryLoadTexture(loader, testUrl);
                    texture.wrapS = THREE.RepeatWrapping;
                    texture.wrapT = THREE.RepeatWrapping;
                    texture.flipY = false;
                    material.map = texture;
                    material.needsUpdate = true;

                    // Apply shader effects if available
                    this.applyShaderEffects(material, url);

                    console.log(`✅ Image fallback worked`);
                    return texture;
                } catch (error) {
                    lastError = error;
                    // Continue to next extension
                }
            }
        }

        // If we get here, all attempts failed
        console.warn(`Failed to load texture (tried extensions: ${extensionsToTry.join(', ')}) for ${basePath}${fallbackBaseUrl ? ' and fallback ' + fallbackBaseUrl + filename.substring(0, filename.lastIndexOf('.')) : ''}`);
        throw lastError;
    }

    // Helper method to load texture with proper error handling and case-insensitive path resolution
    async tryLoadTexture(loader, url) {
        try {
            // First verify the file exists with case-insensitive resolution
            const response = await this.fetchCaseInsensitive(url);
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);

            // Now load the texture from the blob URL
            return new Promise((resolve, reject) => {
                loader.load(
                    objectUrl,
                    (texture) => {
                        URL.revokeObjectURL(objectUrl);
                        resolve(texture);
                    },
                    undefined,
                    (error) => {
                        URL.revokeObjectURL(objectUrl);
                        reject(error);
                    }
                );
            });
        } catch (error) {
            throw error;
        }
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
     * Apply skin pack textures to an already-loaded model part
     * @param {THREE.Group} modelPart - The model part (lower, upper, or head)
     * @param {string} skinPackBasePath - Base path to skin pack
     * @param {string} skinFilename - Skin filename (e.g., lower_greensuit.skin)
     */
    async applySkinPackTextures(modelPart, skinPackBasePath, skinFilename) {
        try {
            const skinFileUrl = `${skinPackBasePath}${skinFilename}`;
            console.log(`🎨 Loading skin pack skin file: ${skinFileUrl}`);

            // Load and parse the skin file
            const response = await this.fetchCaseInsensitive(skinFileUrl);
            if (!response.ok) {
                console.log(`ℹ️ No skin pack skin file found: ${skinFileUrl}`);
                return;
            }
            const text = await response.text();
            const skinData = this.parseSkin(text, skinFileUrl);

            if (!skinData || Object.keys(skinData).length === 0) {
                console.log(`ℹ️ Empty skin pack skin file: ${skinFileUrl}`);
                return;
            }

            // Apply textures from skin file to each mesh
            console.log(`🎨 Skin data contains ${Object.keys(skinData).length} surface mappings:`, skinData);

            modelPart.traverse((child) => {
                if (child.isMesh && child.material) {
                    const surfaceName = child.userData.surface || child.name;
                    const textureMapping = skinData[surfaceName];

                    console.log(`🔍 Checking mesh: ${surfaceName}, has mapping: ${!!textureMapping}, material type: ${child.material.type}`);

                    if (textureMapping) {
                        // textureMapping is already a resolved path from parseSkin
                        const texturePath = textureMapping;
                        console.log(`🎨 Replacing texture on ${surfaceName} with skin pack texture: ${texturePath}`);

                        // Load the new texture
                        const loader = texturePath.toLowerCase().endsWith('.tga') ? this.tgaLoader : this.textureLoader;
                        loader.load(
                            texturePath,
                            (texture) => {
                                texture.flipY = false;
                                texture.wrapS = THREE.RepeatWrapping;
                                texture.wrapT = THREE.RepeatWrapping;

                                // Handle both basic materials and shader materials
                                if (child.material.uniforms && child.material.uniforms.map0) {
                                    // Q3 Shader material - replace map0 uniform
                                    if (child.material.uniforms.map0.value) {
                                        child.material.uniforms.map0.value.dispose();
                                    }
                                    child.material.uniforms.map0.value = texture;
                                    console.log(`✅ Applied skin pack texture to shader material ${surfaceName} (map0)`);
                                } else if (child.material.map) {
                                    // Basic material - replace map property
                                    child.material.map.dispose();
                                    child.material.map = texture;
                                    console.log(`✅ Applied skin pack texture to basic material ${surfaceName} (map)`);
                                } else {
                                    // Material has no map property, try to set it
                                    child.material.map = texture;
                                    console.log(`✅ Set skin pack texture on material ${surfaceName} (no previous map)`);
                                }

                                child.material.needsUpdate = true;
                            },
                            undefined,
                            (error) => {
                                console.warn(`⚠️ Failed to load skin pack texture ${texturePath}:`, error);
                            }
                        );
                    }
                }
            });
        } catch (error) {
            console.warn(`Failed to apply skin pack textures from ${skinFilename}:`, error);
        }
    }

    /**
     * Load a complete player model with all three parts (head, upper, lower)
     * @param {string} baseUrl - Base URL to the model directory
     * @param {string} modelName - Name of the model
     * @param {string} skinName - Skin name (default, blue, red)
     * @param {string|null} skinPackBasePath - Path to skin pack if using one
     * @param {string|null} baseModelName - Name of the base model this is based on (from DB)
     * @returns {Promise<THREE.Group>} - Complete player model
     */
    async loadPlayerModel(baseUrl, modelName, skinName = 'default', skinPackBasePath = null, baseModelName = null, modelId = null) {
        // Ensure baseUrl ends with /
        if (!baseUrl.endsWith('/')) {
            baseUrl += '/';
        }

        // If skinPackBasePath is provided, ensure it ends with /
        if (skinPackBasePath && !skinPackBasePath.endsWith('/')) {
            skinPackBasePath += '/';
        }

        const playerGroup = new THREE.Group();
        playerGroup.name = `player_${modelName}`;

        // Determine the base model to load
        // If baseModelName is provided, load that base model first, then override with PK3
        // Otherwise, just load from PK3 directly
        const actualBaseModelName = baseModelName || modelName;
        const shouldLoadBaseModel = !!baseModelName;

        try {
            // STEP 1: Load ALL shaders (not filtered by model name)
            // Load baseq3 shaders foundation
            try {
                await this.loadShadersForModel('/baseq3/scripts/');
                console.log(`✅ Loaded baseq3 shaders (foundation)`);
            } catch (e) {
                console.warn('⚠️ Failed to load baseq3 shaders:', e);
            }

            // Load PK3 shaders to override (from baseUrl)
            const pk3ScriptsUrl = baseUrl.replace(/models\/players\/[^\/]+\/$/, 'scripts/');
            const beforeCount = this.shaders.size;
            await this.loadShadersForModel(pk3ScriptsUrl, null, modelId);
            const afterCount = this.shaders.size;
            if (afterCount > beforeCount) {
                console.log(`✅ Loaded ${afterCount - beforeCount} PK3 shaders (overrides)`);
            }

            // STEP 2: Determine where to load MD3s from
            let mainPlayerPath;
            let skinBasePath;

            // List of base Q3 models from pak0-pak8.pk3
            const baseQ3Models = [
                'sarge', 'grunt', 'major', 'visor', 'slash', 'biker', 'tankjr',
                'orbb', 'crash', 'razor', 'doom', 'klesk', 'anarki', 'xaero',
                'mynx', 'hunter', 'bones', 'sorlag', 'lucy', 'keel', 'uriel',
                'bitterman'
            ];
            const isBaseQ3Model = baseQ3Models.includes(actualBaseModelName.toLowerCase());

            if (shouldLoadBaseModel && isBaseQ3Model) {
                // Base model from baseq3, skins from PK3
                mainPlayerPath = `/baseq3/models/players/${actualBaseModelName}/`;
                skinBasePath = baseUrl;
                // Set fallback URL for shader textures - if texture not found in PK3, try baseq3
                this.fallbackBaseUrl = '/baseq3/';
                console.log(`👤 Loading base model from baseq3: ${actualBaseModelName}, will apply PK3 from: ${baseUrl}`);
            } else if (shouldLoadBaseModel) {
                // Custom base model (from another PK3) - but it's NOT in baseq3
                // This means it's actually a complete custom model, so load from PK3
                mainPlayerPath = baseUrl;
                skinBasePath = baseUrl;
                // Set fallback URL for shader textures - if texture not found in PK3, try baseq3
                this.fallbackBaseUrl = '/baseq3/';
                console.log(`👤 Loading custom complete model: ${actualBaseModelName} from PK3: ${baseUrl}`);
            } else {
                // Complete model - load everything from PK3
                mainPlayerPath = baseUrl;
                skinBasePath = baseUrl;
                // Set fallback URL for shader textures - if texture not found in PK3, try baseq3
                this.fallbackBaseUrl = '/baseq3/';
                console.log(`👤 Loading complete model from PK3: ${baseUrl}`);
            }

            // Load lower body (legs) from base model with specified skin
            const lowerUrl = `${mainPlayerPath}lower.md3`;
            const lowerSkinUrl = `${skinBasePath}lower_${skinName}.skin`;
            console.log(`👤 Loading player lower from: ${lowerUrl} with skin: ${lowerSkinUrl}`);
            const lower = await this.load(lowerUrl, lowerSkinUrl);
            lower.name = 'lower';
            lower.userData.source = 'baseq3';
            playerGroup.add(lower);

            // Find the tag_torso on the lower model
            const lowerTags = lower.userData.tags;
            if (lowerTags && lowerTags[0]) {
                const torsoTag = lowerTags[0].find(tag => tag.name === 'tag_torso');

                if (torsoTag) {
                    // Load upper body (torso) from base model with specified skin
                    const upperUrl = `${mainPlayerPath}upper.md3`;
                    const upperSkinUrl = `${skinBasePath}upper_${skinName}.skin`;
                    const upper = await this.load(upperUrl, upperSkinUrl);
                    upper.name = 'upper';
                    upper.userData.source = mainPlayerPath.includes('/baseq3/') ? 'baseq3' : 'pk3';

                    // Position upper body at torso tag
                    this.attachToTag(upper, torsoTag);
                    lower.add(upper); // ATTACH UPPER TO LOWER so it moves with legs!

                    // Find the tag_head on the upper model
                    const upperTags = upper.userData.tags;
                    if (upperTags && upperTags[0]) {
                        const headTag = upperTags[0].find(tag => tag.name === 'tag_head');

                        if (headTag) {
                            // Load head from base model with specified skin
                            const headUrl = `${mainPlayerPath}head.md3`;
                            const headSkinUrl = `${skinBasePath}head_${skinName}.skin`;
                            const head = await this.load(headUrl, headSkinUrl);
                            head.name = 'head';
                            head.userData.source = mainPlayerPath.includes('/baseq3/') ? 'baseq3' : 'pk3';

                            // Position head at head tag
                            this.attachToTag(head, headTag);
                            upper.add(head); // Attach head to upper body
                        }
                    }
                }
            }

            // STEP 2B: If we loaded a base model FROM BASEQ3, NOW try to load from PK3 and replace whatever exists
            // Skip this step for custom complete models (already loaded from PK3)
            // Also skip if baseUrl is the same as baseq3 path (no actual PK3 override)
            const isBaseQ3Path = baseUrl.startsWith('/baseq3/');
            if (shouldLoadBaseModel && isBaseQ3Model && !isBaseQ3Path) {
                console.log(`👤 Step 2B: Trying to load PK3 overrides from: ${baseUrl}`);

                // Try to replace lower with PK3 version (if exists)
                try {
                    const pk3LowerUrl = `${baseUrl}lower.md3`;
                    const pk3LowerSkinUrl = `${baseUrl}lower_${skinName}.skin`;
                    const pk3Lower = await this.load(pk3LowerUrl, pk3LowerSkinUrl);
                    pk3Lower.name = 'lower';
                    pk3Lower.userData.source = 'pk3';

                    // Find and preserve upper/head before replacing lower
                    const oldLower = playerGroup.userData.lower;
                    const oldUpper = oldLower?.children.find(c => c.name === 'upper');

                    // Remove old lower and add new one
                    playerGroup.remove(oldLower);
                    playerGroup.add(pk3Lower);

                    // Re-attach upper if it existed
                    if (oldUpper) {
                        const torsoTag = pk3Lower.userData.tags?.[0]?.find(tag => tag.name === 'tag_torso');
                        if (torsoTag) {
                            this.attachToTag(oldUpper, torsoTag);
                            pk3Lower.add(oldUpper);
                        }
                    }

                    console.log(`✅ Replaced lower with PK3 version`);
                } catch (e) {
                    console.log(`ℹ️ No PK3 lower.md3 - using base model lower`);
                }

                // Try to replace upper with PK3 version (if exists)
                const currentLower = playerGroup.children.find(c => c.name === 'lower');
                if (currentLower) {
                    try {
                        const pk3UpperUrl = `${baseUrl}upper.md3`;
                        const pk3UpperSkinUrl = `${baseUrl}upper_${skinName}.skin`;
                        const pk3Upper = await this.load(pk3UpperUrl, pk3UpperSkinUrl);
                        pk3Upper.name = 'upper';
                        pk3Upper.userData.source = 'pk3';

                        // Find and preserve head before replacing upper
                        const oldUpper = currentLower.children.find(c => c.name === 'upper');
                        const oldHead = oldUpper?.children.find(c => c.name === 'head');

                        // Remove old upper
                        if (oldUpper) {
                            currentLower.remove(oldUpper);
                        }

                        // Attach new upper to lower
                        const torsoTag = currentLower.userData.tags?.[0]?.find(tag => tag.name === 'tag_torso');
                        if (torsoTag) {
                            this.attachToTag(pk3Upper, torsoTag);
                            currentLower.add(pk3Upper);
                        }

                        // Re-attach head if it existed
                        if (oldHead) {
                            const headTag = pk3Upper.userData.tags?.[0]?.find(tag => tag.name === 'tag_head');
                            if (headTag) {
                                this.attachToTag(oldHead, headTag);
                                pk3Upper.add(oldHead);
                            }
                        }

                        console.log(`✅ Replaced upper with PK3 version`);
                    } catch (e) {
                        console.log(`ℹ️ No PK3 upper.md3 - using base model upper`);
                    }
                }

                // Try to replace head with PK3 version (if exists)
                const currentUpper = currentLower?.children.find(c => c.name === 'upper');
                if (currentUpper) {
                    try {
                        const pk3HeadUrl = `${baseUrl}head.md3`;
                        const pk3HeadSkinUrl = `${baseUrl}head_${skinName}.skin`;
                        const pk3Head = await this.load(pk3HeadUrl, pk3HeadSkinUrl);
                        pk3Head.name = 'head';
                        pk3Head.userData.source = 'pk3';

                        // Remove old head
                        const oldHead = currentUpper.children.find(c => c.name === 'head');
                        if (oldHead) {
                            currentUpper.remove(oldHead);
                        }

                        // Attach new head to upper
                        const headTag = currentUpper.userData.tags?.[0]?.find(tag => tag.name === 'tag_head');
                        if (headTag) {
                            this.attachToTag(pk3Head, headTag);
                            currentUpper.add(pk3Head);
                        }

                        console.log(`✅ Replaced head with PK3 version`);
                    } catch (e) {
                        console.log(`ℹ️ No PK3 head.md3 - using base model head`);
                    }
                }
            }

            // Store references for animation
            playerGroup.userData.lower = playerGroup.children.find(c => c.name === 'lower');
            console.log(`🔍 Lower reference:`, playerGroup.userData.lower?.name);

            // Find upper - might be direct child or attached to a tag
            if (playerGroup.userData.lower) {
                console.log(`🔍 Lower has ${playerGroup.userData.lower.children.length} children:`, playerGroup.userData.lower.children.map(c => c.name));
                let upper = playerGroup.userData.lower.children.find(c => c.name === 'upper');
                console.log(`🔍 Upper as direct child:`, upper?.name);
                // If not found as direct child, search through all descendants
                if (!upper) {
                    console.log(`🔍 Searching via traverse...`);
                    playerGroup.userData.lower.traverse((child) => {
                        console.log(`  - Found child:`, child.name, child.type);
                        if (child.name === 'upper' && !upper) {
                            upper = child;
                            console.log(`🔍 Upper found via traverse:`, upper.name);
                        }
                    });
                }
                playerGroup.userData.upper = upper;
                console.log(`🔍 Final upper reference:`, playerGroup.userData.upper?.name);
            }

            // Find head - might be direct child of upper or attached to a tag
            if (playerGroup.userData.upper) {
                let head = playerGroup.userData.upper.children.find(c => c.name === 'head');
                console.log(`🔍 Head as direct child:`, head?.name);
                // If not found as direct child, search through all descendants
                if (!head) {
                    playerGroup.userData.upper.traverse((child) => {
                        if (child.name === 'head' && !head) {
                            head = child;
                            console.log(`🔍 Head found via traverse:`, head.name);
                        }
                    });
                }
                playerGroup.userData.head = head;
                console.log(`🔍 Final head reference:`, playerGroup.userData.head?.name);
            }

            // STEP 3: Load animation config
            // Load from base model first (if loading base model)
            if (shouldLoadBaseModel) {
                const animConfigUrl = `${mainPlayerPath}animation.cfg`;
                console.log(`🎬 STEP 3A: Loading animation.cfg from base model: ${animConfigUrl}`);
                const animations = await this.loadAnimationConfig(animConfigUrl);
                if (animations) {
                    playerGroup.userData.animations = animations;
                    console.log(`✅ Loaded animation config from base model`);
                } else {
                    console.log(`ℹ️ No animation.cfg in base model`);
                }
            }

            // ALWAYS try to load from PK3 and override
            const pk3AnimConfigUrl = `${baseUrl}animation.cfg`;
            console.log(`🎬 STEP 3B: Loading animation.cfg from PK3: ${pk3AnimConfigUrl}`);
            const pk3Animations = await this.loadAnimationConfig(pk3AnimConfigUrl);
            if (pk3Animations) {
                playerGroup.userData.animations = pk3Animations;
                console.log(`✅ Loaded animation config from PK3 (override)`);
            } else {
                console.log(`ℹ️ No animation.cfg in PK3`);
                if (!shouldLoadBaseModel && !playerGroup.userData.animations) {
                    console.warn(`⚠️ No animation config found anywhere!`);
                }
            }

            return playerGroup;

        } catch (error) {
            console.error('Failed to load complete player model:', error);
            throw error;
        }
    }

    /**
     * Load a complete weapon model with all parts (main + hand + barrel + flash)
     * @param {string} baseUrl - Base URL to the weapon directory (e.g., /baseq3/models/weapons2/machinegun/)
     * @param {string} weaponName - Name of the weapon (e.g., "machinegun")
     * @param {number|null} modelId - Model ID for fetching shader list from API (null for base Q3 weapons)
     * @returns {Promise<THREE.Group>} - Complete weapon model
     */
    async loadWeaponModel(baseUrl, weaponName, modelId = null) {
        // Ensure baseUrl ends with /
        if (!baseUrl.endsWith('/')) {
            baseUrl += '/';
        }

        const isBaseQ3 = baseUrl.includes('/baseq3/');
        const baseq3WeaponPath = `/baseq3/models/weapons2/${weaponName}/`;

        const weaponGroup = new THREE.Group();
        weaponGroup.name = `weapon_${weaponName}`;

        // Load shader files for this weapon first (before loading MD3 files)
        // This already loads baseq3 shaders first, then pk3 overrides
        await this.loadShadersForWeapon(baseUrl, weaponName, modelId);

        // Like Quake engine: Load baseq3 weapon FIRST (complete working weapon)
        // Then load pk3 weapon parts to replace/override if they exist
        let mainWeaponPath = baseq3WeaponPath;

        if (!isBaseQ3) {
            console.log(`🔄 Loading baseq3 ${weaponName} first, then will apply pk3 overrides from ${baseUrl}`);
        } else {
            console.log(`🔫 Loading baseq3 ${weaponName}`);
        }

        try {
            // Load main weapon body from baseq3
            // For weapon skin packs: pass pk3 baseUrl so textures resolve to pk3 (where custom textures are)
            const mainUrl = `${mainWeaponPath}${weaponName}.md3`;
            console.log(`🔫 Loading weapon main body from baseq3: ${mainUrl}`);
            const textureBaseUrl = !isBaseQ3 ? baseUrl : null; // Use pk3 path for textures if not pure baseq3
            const main = await this.load(mainUrl, null, textureBaseUrl);
            main.name = 'main';
            main.userData.source = 'baseq3';
            weaponGroup.add(main);

            // Find tags on the main model for attachments
            const mainTags = main.userData.tags;
            console.log(`🔫 Weapon ${weaponName} tags:`, mainTags && mainTags[0] ? mainTags[0].map(t => t.name) : 'none');
            if (mainTags && mainTags[0]) {
                // Try to load and attach barrel (from baseq3)
                const barrelTag = mainTags[0].find(tag => tag.name === 'tag_barrel');
                if (barrelTag) {
                    try {
                        const barrelUrl = `${mainWeaponPath}${weaponName}_barrel.md3`;
                        console.log(`🔫 Loading weapon barrel from baseq3: ${barrelUrl}`);
                        const barrel = await this.load(barrelUrl, null, textureBaseUrl);
                        barrel.name = 'barrel';
                        barrel.userData.source = 'baseq3';
                        this.attachToTag(barrel, barrelTag);
                        main.add(barrel);
                        console.log(`✅ Attached barrel to ${weaponName}`);
                    } catch (e) {
                        // Barrel not found or failed to load, continue without it
                        console.log(`⚠️ No barrel for ${weaponName}:`, e.message);
                    }
                }

                // Try to load and attach hand (from baseq3)
                // Some weapons use 'tag_hand', others use 'tag_weapon'
                const handTag = mainTags[0].find(tag => tag.name === 'tag_hand' || tag.name === 'tag_weapon');
                if (handTag) {
                    try {
                        const handUrl = `${mainWeaponPath}${weaponName}_hand.md3`;
                        console.log(`🔫 Loading weapon hand from baseq3: ${handUrl}`);
                        const hand = await this.load(handUrl, null, textureBaseUrl);
                        hand.name = 'hand';
                        hand.userData.source = 'baseq3';
                        this.attachToTag(hand, handTag);
                        main.add(hand);
                        console.log(`✅ Attached hand to ${weaponName}`);
                    } catch (e) {
                        // Hand not found or failed to load, continue without it
                        console.log(`⚠️ No hand for ${weaponName}:`, e.message);
                    }
                }

                // Try to load and attach flash (from baseq3)
                const flashTag = mainTags[0].find(tag => tag.name === 'tag_flash');
                if (flashTag) {
                    try {
                        const flashUrl = `${mainWeaponPath}${weaponName}_flash.md3`;
                        console.log(`🔫 Loading weapon flash from baseq3: ${flashUrl}`);
                        const flash = await this.load(flashUrl, null, textureBaseUrl);
                        flash.name = 'flash';
                        flash.userData.source = 'baseq3';
                        this.attachToTag(flash, flashTag);
                        main.add(flash);
                        // Hide flash by default (it's only shown when firing)
                        flash.visible = false;
                        console.log(`✅ Attached flash to ${weaponName} (hidden)`);
                    } catch (e) {
                        // Flash not found or failed to load, continue without it
                        console.log(`⚠️ No flash for ${weaponName}:`, e.message);
                    }
                }
            }

            // If this is a pk3 weapon (not baseq3), try to load pk3 overrides
            if (!isBaseQ3) {
                console.log(`🔄 Checking for pk3 overrides in ${baseUrl}...`);

                // Try to replace main body with pk3 version
                try {
                    const pk3MainUrl = `${baseUrl}${weaponName}.md3`;
                    console.log(`🔄 Trying to load pk3 main body: ${pk3MainUrl}`);
                    // Load pk3 main with pk3 textures (baseUrl already points to pk3)
                    const pk3Main = await this.load(pk3MainUrl, null, baseUrl);

                    // Save baseq3 attachments before removing main
                    const baseq3Hand = main.children.find(c => c.name === 'hand');
                    const baseq3Flash = main.children.find(c => c.name === 'flash');

                    // Replace baseq3 main with pk3 main
                    weaponGroup.remove(main);
                    pk3Main.name = 'main';
                    pk3Main.userData.source = 'pk3';
                    weaponGroup.add(pk3Main);
                    console.log(`✅ Replaced main body with pk3 version`);

                    // Update reference and reload attachments for pk3 main
                    const pk3MainTags = pk3Main.userData.tags;
                    if (pk3MainTags && pk3MainTags[0]) {
                        // Try pk3 barrel
                        const pk3BarrelTag = pk3MainTags[0].find(tag => tag.name === 'tag_barrel');
                        if (pk3BarrelTag) {
                            try {
                                const pk3BarrelUrl = `${baseUrl}${weaponName}_barrel.md3`;
                                const pk3Barrel = await this.load(pk3BarrelUrl, null, baseUrl);
                                pk3Barrel.name = 'barrel';
                                pk3Barrel.userData.source = 'pk3';
                                this.attachToTag(pk3Barrel, pk3BarrelTag);
                                pk3Main.add(pk3Barrel);
                                console.log(`✅ Attached pk3 barrel`);
                            } catch (e) {
                                console.log(`ℹ️ No pk3 barrel, using baseq3 barrel`);
                            }
                        }

                        // Try pk3 hand
                        const pk3HandTag = pk3MainTags[0].find(tag => tag.name === 'tag_hand' || tag.name === 'tag_weapon');
                        if (pk3HandTag) {
                            try {
                                const pk3HandUrl = `${baseUrl}${weaponName}_hand.md3`;
                                const pk3Hand = await this.load(pk3HandUrl, null, baseUrl);
                                pk3Hand.name = 'hand';
                                pk3Hand.userData.source = 'pk3';
                                this.attachToTag(pk3Hand, pk3HandTag);
                                pk3Main.add(pk3Hand);
                                console.log(`✅ Attached pk3 hand`);
                            } catch (e) {
                                console.log(`ℹ️ No pk3 hand found`);
                                // Fall back to baseq3 hand if available
                                if (baseq3Hand) {
                                    this.attachToTag(baseq3Hand, pk3HandTag);
                                    pk3Main.add(baseq3Hand);
                                    console.log(`✅ Using baseq3 hand as fallback`);
                                }
                            }
                        }

                        // Try pk3 flash
                        const pk3FlashTag = pk3MainTags[0].find(tag => tag.name === 'tag_flash');
                        if (pk3FlashTag) {
                            try {
                                const pk3FlashUrl = `${baseUrl}${weaponName}_flash.md3`;
                                const pk3Flash = await this.load(pk3FlashUrl, null, baseUrl);
                                pk3Flash.name = 'flash';
                                pk3Flash.userData.source = 'pk3';
                                this.attachToTag(pk3Flash, pk3FlashTag);
                                pk3Main.add(pk3Flash);
                                pk3Flash.visible = false;
                                console.log(`✅ Attached pk3 flash`);
                            } catch (e) {
                                console.log(`ℹ️ No pk3 flash found`);
                                // Fall back to baseq3 flash if available
                                if (baseq3Flash) {
                                    this.attachToTag(baseq3Flash, pk3FlashTag);
                                    pk3Main.add(baseq3Flash);
                                    baseq3Flash.visible = false;
                                    console.log(`✅ Using baseq3 flash as fallback`);
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.log(`ℹ️ No pk3 main body found, using baseq3 weapon: ${e.message}`);
                }
            }

            // Add animation state to the weapon group
            weaponGroup.userData.animation = {
                firing: false,
                muzzleFlashTime: 0,
                barrelAngle: 0,
                barrelSpinning: false,
                barrelTime: Date.now(),
                sounds: [], // Will be populated with weapon sounds
                projectiles: [], // Array of active projectiles
                scene: null, // Will be set by ModelViewer
                weaponName: weaponName // Store weapon name for projectile creation
            };

            // Store sound paths for later loading (will be loaded by ModelViewer with proper AudioListener)
            weaponGroup.userData.animation.soundPaths = [];
            const lowerName = weaponName.toLowerCase();

            if (lowerName.includes('plasma') || lowerName.includes('pg')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/plasma/hyprbf1a.wav',  // [0] Fire sound
                    '/baseq3/sound/weapons/plasma/plasmx1a.wav'   // [1] Hit/impact sound
                ];
            } else if (lowerName.includes('rocket') || lowerName.includes('rl')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/rocket/rocklf1a.wav',  // [0] Fire sound
                    '/baseq3/sound/weapons/rocket/rocklx1a.wav'   // [1] Explosion/hit sound
                ];
            } else if (lowerName.includes('rail') || lowerName.includes('rg')) {
                // CHECK RAILGUN BEFORE LIGHTNING! (railgun contains 'lg')
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/railgun/rg_hum.wav',        // [0] Hum sound
                    '/baseq3/sound/weapons/railgun/railgf1a.wav',      // [1] Fire sound
                    '/baseq3/sound/weapons/plasma/plasmx1a.wav'        // [2] Impact sound (same as plasma)
                ];
            } else if (lowerName.includes('lightning') || lowerName.includes('lg')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/melee/fsthum.wav',  // [0] Idle sound (plays when not firing)
                    '/baseq3/sound/weapons/lightning/lg_hum.wav',  // [1] Repeating fire sound (loops while firing)
                    '/baseq3/sound/weapons/lightning/lg_fire.wav',  // [2] Initial fire sound (plays once on click)
                    '/baseq3/sound/weapons/lightning/lg_hit.wav',  // [3] Hit sound 1
                    '/baseq3/sound/weapons/lightning/lg_hit2.wav',  // [4] Hit sound 2
                    '/baseq3/sound/weapons/lightning/lg_hit3.wav'  // [5] Hit sound 3
                ];
            } else if (lowerName.includes('grenade') || lowerName.includes('gl')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/grenade/grenlf1a.wav',  // [0] Fire sound
                    '/baseq3/sound/weapons/rocket/rocklx1a.wav'    // [1] Explosion sound (same as rocket)
                ];
            } else if (lowerName.includes('shotgun') || lowerName.includes('sg')) {
                weaponGroup.userData.animation.soundPaths = ['/baseq3/sound/weapons/shotgun/sshotf1b.wav'];
            } else if (lowerName.includes('machine') || lowerName.includes('mg')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/machinegun/machgf1b.wav',  // [0] Fire sound 1
                    '/baseq3/sound/weapons/machinegun/machgf2b.wav',  // [1] Fire sound 2
                    '/baseq3/sound/weapons/machinegun/machgf3b.wav',  // [2] Fire sound 3
                    '/baseq3/sound/weapons/machinegun/machgf4b.wav',  // [3] Fire sound 4
                    '/baseq3/sound/weapons/machinegun/ric1.wav',      // [4] Ricochet sound 1
                    '/baseq3/sound/weapons/machinegun/ric2.wav',      // [5] Ricochet sound 2
                    '/baseq3/sound/weapons/machinegun/ric3.wav'       // [6] Ricochet sound 3
                ];
            } else if (lowerName.includes('bfg')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/bfg/bfg_hum.wav',    // [0] Hum sound
                    '/baseq3/sound/weapons/bfg/bfg_fire.wav',   // [1] Fire sound
                    '/baseq3/sound/weapons/rocket/rocklx1a.wav' // [2] Explosion sound (same as rocket)
                ];
            } else if (lowerName.includes('gauntlet') || lowerName.includes('gt')) {
                weaponGroup.userData.animation.soundPaths = [
                    '/baseq3/sound/weapons/melee/fstrun.wav',  // [0] Spinning sound (loops while firing)
                    '/baseq3/sound/weapons/melee/fstatck.wav'  // [1] Hit/attack sound
                ];
            } else if (lowerName.includes('grapple') || lowerName.includes('hook')) {
                weaponGroup.userData.animation.soundPaths = [];
            }

            console.log(`🔊 Weapon ${weaponName} will load ${weaponGroup.userData.animation.soundPaths.length} sounds`);

            // Helper function to create weapon-specific projectiles (following Q3 engine logic)
            const createProjectileForWeapon = async (weaponName) => {
                const projectileGroup = new THREE.Group();

                // Determine projectile type based on weapon name
                const lowerName = weaponName.toLowerCase();

                if (lowerName.includes('rocket') || lowerName.includes('rl')) {
                    // Rocket projectile - elongated missile shape (would use rocket.md3 model in real Q3)
                    const rocketGeom = new THREE.CylinderGeometry(3, 3, 20, 8);
                    rocketGeom.rotateX(Math.PI / 2);
                    const rocketMat = new THREE.MeshBasicMaterial({ color: 0x888888 });
                    const rocket = new THREE.Mesh(rocketGeom, rocketMat);
                    projectileGroup.add(rocket);

                    // Rocket exhaust flame
                    const flameGeom = new THREE.ConeGeometry(4, 15, 8);
                    flameGeom.rotateX(Math.PI / 2);
                    const flameMat = new THREE.MeshBasicMaterial({
                        color: 0xff6600,
                        transparent: true,
                        opacity: 0.8
                    });
                    const flame = new THREE.Mesh(flameGeom, flameMat);
                    flame.position.z = -12;
                    projectileGroup.add(flame);

                    const light = new THREE.PointLight(0xff6600, 4, 300);
                    projectileGroup.add(light);

                    projectileGroup.userData.rocket = rocket;
                    projectileGroup.userData.flame = flame;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 1000; // Slower than plasma
                    projectileGroup.userData.projectileType = 'rocket';

                } else if (lowerName.includes('plasma') || lowerName.includes('pg')) {
                    // Plasma projectile - sprite-based like Q3 (uses railDisc shader)
                    // Create circular sprite billboard
                    const spriteMap = new THREE.TextureLoader().load('data:image/svg+xml;base64,' + btoa(`
                        <svg width="32" height="32" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <radialGradient id="grad">
                                    <stop offset="0%" stop-color="#ffffff" stop-opacity="1"/>
                                    <stop offset="40%" stop-color="#88ffff" stop-opacity="0.8"/>
                                    <stop offset="100%" stop-color="#4488ff" stop-opacity="0"/>
                                </radialGradient>
                            </defs>
                            <circle cx="16" cy="16" r="16" fill="url(#grad)"/>
                        </svg>
                    `));

                    const spriteMat = new THREE.SpriteMaterial({
                        map: spriteMap,
                        color: 0x88ccff,
                        transparent: true,
                        opacity: 0.8,
                        blending: THREE.AdditiveBlending
                    });
                    const sprite = new THREE.Sprite(spriteMat);
                    sprite.scale.set(16, 16, 1); // radius in Q3 is 0.25 units but scaled for visibility
                    projectileGroup.add(sprite);

                    const light = new THREE.PointLight(0x6699ff, 3, 300);
                    projectileGroup.add(light);

                    projectileGroup.userData.sprite = sprite;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 2000;
                    projectileGroup.userData.projectileType = 'plasma';
                    projectileGroup.userData.trailParticles = []; // Will spawn trail sprites

                } else if (lowerName.includes('grenade') || lowerName.includes('gl')) {
                    // Grenade projectile - darker metallic sphere with orange glow
                    const grenadeGeom = new THREE.SphereGeometry(5, 12, 12);
                    const grenadeMat = new THREE.MeshBasicMaterial({
                        color: 0x444444,
                        metalness: 0.8
                    });
                    const grenade = new THREE.Mesh(grenadeGeom, grenadeMat);
                    projectileGroup.add(grenade);

                    // Add orange/red warning glow
                    const glowGeom = new THREE.SphereGeometry(6, 12, 12);
                    const glowMat = new THREE.MeshBasicMaterial({
                        color: 0xff4400,
                        transparent: true,
                        opacity: 0.4,
                        blending: THREE.AdditiveBlending
                    });
                    const glow = new THREE.Mesh(glowGeom, glowMat);
                    projectileGroup.add(glow);

                    const light = new THREE.PointLight(0xff6600, 2, 200);
                    projectileGroup.add(light);

                    projectileGroup.userData.grenade = grenade;
                    projectileGroup.userData.glow = glow;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 800;
                    projectileGroup.userData.hasGravity = true; // Grenades arc
                    projectileGroup.userData.projectileType = 'grenade';

                } else if (lowerName.includes('lightning') || lowerName.includes('lg')) {
                    // Lightning gun - hitscan beam (like Q3 engine)
                    // Creates a cylindrical beam from muzzle to hit point
                    // Using procedural geometry like DoRailCore() in Q3
                    console.log('⚡ Creating lightning beam (hitscan) for:', lowerName);

                    // Create 4 beam cylinders rotated at 45° intervals (like Q3's DoRailCore)
                    const beamGroup = new THREE.Group();
                    const beamLength = 2000; // Default beam length (will be scaled to target distance)

                    for (let i = 0; i < 4; i++) {
                        const angle = (i * 45) * (Math.PI / 180);

                        // Create cylinder pointing along Y axis
                        const beamGeom = new THREE.CylinderGeometry(3, 3, beamLength, 8);
                        beamGeom.translate(0, beamLength / 2, 0); // Pivot at base

                        const beamMat = new THREE.MeshBasicMaterial({
                            color: 0x8888ff,
                            transparent: true,
                            opacity: 0.6,
                            blending: THREE.AdditiveBlending,
                            depthWrite: false
                        });

                        const beam = new THREE.Mesh(beamGeom, beamMat);

                        // Rotate around Y axis (the beam's own length) to create cross pattern
                        beam.rotation.y = angle;
                        beamGroup.add(beam);
                    }

                    // Rotate entire beam group to point forward along Z axis
                    beamGroup.rotation.x = Math.PI / 2;
                    projectileGroup.add(beamGroup);

                    const light = new THREE.PointLight(0x8888ff, 5, 400);
                    projectileGroup.add(light);

                    projectileGroup.userData.beamGroup = beamGroup;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.beamLength = beamLength;
                    projectileGroup.userData.velocity = 10000; // Instant hitscan
                    projectileGroup.userData.lifetime = 50; // Very short - beam disappears quickly
                    projectileGroup.userData.projectileType = 'lightning';

                } else if (lowerName.includes('rail') || lowerName.includes('rg')) {
                    // Railgun - instant beam (no projectile in Q3, just visual trail)
                    const beamGeom = new THREE.CylinderGeometry(1, 1, 1000, 6);
                    beamGeom.rotateX(Math.PI / 2);
                    const beamMat = new THREE.MeshBasicMaterial({
                        color: 0x00ff00,
                        transparent: true,
                        opacity: 0.7
                    });
                    const beam = new THREE.Mesh(beamGeom, beamMat);
                    projectileGroup.add(beam);

                    projectileGroup.userData.beam = beam;
                    projectileGroup.userData.velocity = 10000; // Very fast
                    projectileGroup.userData.lifetime = 100; // Very short lived
                    projectileGroup.userData.projectileType = 'rail';

                } else if (lowerName.includes('shotgun') || lowerName.includes('sg')) {
                    // Shotgun - hitscan with bullet tracers (no visible projectile in Q3)
                    // Create small tracer line for each pellet
                    const tracerGeom = new THREE.CylinderGeometry(0.3, 0.3, 20, 4);
                    tracerGeom.rotateX(Math.PI / 2);
                    const tracerMat = new THREE.MeshBasicMaterial({
                        color: 0xffaa00,
                        transparent: true,
                        opacity: 0.6
                    });
                    const tracer = new THREE.Mesh(tracerGeom, tracerMat);
                    projectileGroup.add(tracer);

                    projectileGroup.userData.tracer = tracer;
                    projectileGroup.userData.velocity = 10000; // Instant hitscan
                    projectileGroup.userData.lifetime = 30; // Very short visual effect
                    projectileGroup.userData.projectileType = 'shotgun';

                } else if (lowerName.includes('machine') || lowerName.includes('mg')) {
                    // Machinegun - hitscan with bullet tracers
                    const tracerGeom = new THREE.CylinderGeometry(0.4, 0.4, 30, 4);
                    tracerGeom.rotateX(Math.PI / 2);
                    const tracerMat = new THREE.MeshBasicMaterial({
                        color: 0xffff00,
                        transparent: true,
                        opacity: 0.7
                    });
                    const tracer = new THREE.Mesh(tracerGeom, tracerMat);
                    projectileGroup.add(tracer);

                    projectileGroup.userData.tracer = tracer;
                    projectileGroup.userData.velocity = 10000; // Instant hitscan
                    projectileGroup.userData.lifetime = 40; // Very short visual effect
                    projectileGroup.userData.projectileType = 'machinegun';

                } else if (lowerName.includes('bfg')) {
                    // BFG - multi-layered glowing green sphere projectile
                    // Layer 1: Bright core
                    const coreGeom = new THREE.SphereGeometry(6, 32, 32);
                    const coreMat = new THREE.MeshBasicMaterial({
                        color: 0x00ff00,
                        transparent: true,
                        opacity: 0.9
                    });
                    const core = new THREE.Mesh(coreGeom, coreMat);
                    projectileGroup.add(core);

                    // Layer 2: Inner glow
                    const innerGlowGeom = new THREE.SphereGeometry(10, 32, 32);
                    const innerGlowMat = new THREE.MeshBasicMaterial({
                        color: 0x00ff00,
                        transparent: true,
                        opacity: 0.4,
                        blending: THREE.AdditiveBlending,
                        depthWrite: false
                    });
                    const innerGlow = new THREE.Mesh(innerGlowGeom, innerGlowMat);
                    projectileGroup.add(innerGlow);

                    // Layer 3: Outer glow
                    const outerGlowGeom = new THREE.SphereGeometry(15, 32, 32);
                    const outerGlowMat = new THREE.MeshBasicMaterial({
                        color: 0x00ff00,
                        transparent: true,
                        opacity: 0.15,
                        blending: THREE.AdditiveBlending,
                        depthWrite: false
                    });
                    const outerGlow = new THREE.Mesh(outerGlowGeom, outerGlowMat);
                    projectileGroup.add(outerGlow);

                    // Strong green point light
                    const light = new THREE.PointLight(0x00ff00, 12, 600);
                    projectileGroup.add(light);

                    projectileGroup.userData.core = core;
                    projectileGroup.userData.innerGlow = innerGlow;
                    projectileGroup.userData.outerGlow = outerGlow;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 2000;
                    projectileGroup.userData.lifetime = 10000; // 10 seconds like Q3
                    projectileGroup.userData.projectileType = 'bfg';

                } else if (lowerName.includes('grapple') || lowerName.includes('hook')) {
                    // Grapple - uses rocket model in Q3 (async load)
                    const loader = new MD3Loader();

                    // Create placeholder while model loads
                    const placeholderGeom = new THREE.CylinderGeometry(2, 3, 15, 8);
                    placeholderGeom.rotateX(Math.PI / 2);
                    const placeholderMat = new THREE.MeshBasicMaterial({
                        color: 0x888888,
                        transparent: true,
                        opacity: 0.8
                    });
                    const placeholder = new THREE.Mesh(placeholderGeom, placeholderMat);
                    projectileGroup.add(placeholder);

                    // Load actual rocket model for grapple
                    loader.load('/baseq3/models/ammo/rocket/rocket.md3', null)
                        .then(rocketModel => {
                            // Remove placeholder
                            projectileGroup.remove(placeholder);

                            // Scale the rocket model
                            rocketModel.scale.set(1.2, 1.2, 1.2);

                            // Make it metallic/gray for hook
                            rocketModel.traverse((child) => {
                                if (child.isMesh && child.material) {
                                    child.material.color = new THREE.Color(0x999999);
                                    child.material.metalness = 0.8;
                                    child.material.needsUpdate = true;
                                }
                            });

                            projectileGroup.add(rocketModel);
                            projectileGroup.userData.hookModel = rocketModel;
                        })
                        .catch(err => {
                            console.warn('Failed to load grapple model, using placeholder:', err);
                        });

                    // Orange light (Q3 uses RGB 1, 0.75, 0)
                    const light = new THREE.PointLight(0xffbf00, 3, 200);
                    projectileGroup.add(light);

                    projectileGroup.userData.placeholder = placeholder;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 800; // Q3 speed
                    projectileGroup.userData.lifetime = 10000; // 10 seconds
                    projectileGroup.userData.projectileType = 'grapple';

                } else if (lowerName.includes('gauntlet') || lowerName.includes('gt')) {
                    // Gauntlet - no projectile, melee weapon
                    // Return empty group (gauntlet doesn't fire projectiles)
                    projectileGroup.userData.projectileType = 'gauntlet';
                    projectileGroup.userData.velocity = 0;
                    return null; // No projectile for gauntlet

                } else {
                    // Default projectile (for unknown weapons or bullets)
                    const bulletGeom = new THREE.SphereGeometry(3, 8, 8);
                    const bulletMat = new THREE.MeshBasicMaterial({ color: 0xffff00 });
                    const bullet = new THREE.Mesh(bulletGeom, bulletMat);
                    projectileGroup.add(bullet);

                    const light = new THREE.PointLight(0xffff00, 1, 100);
                    projectileGroup.add(light);

                    projectileGroup.userData.bullet = bullet;
                    projectileGroup.userData.light = light;
                    projectileGroup.userData.velocity = 1500;
                    projectileGroup.userData.projectileType = 'bullet';
                }

                return projectileGroup;
            };

            // Add method to spawn a projectile
            weaponGroup.userData.spawnProjectile = async function() {
                if (!this.animation.scene || !this.animation.camera) return;

                // Try to get flash for spawn position, but always use weaponGroup for direction
                const flash = weaponGroup.getObjectByName('flash');
                const spawnPosSource = flash || weaponGroup.getObjectByName('weapon') || weaponGroup;

                // Get spawn world position (from muzzle flash or weapon)
                const spawnWorldPos = new THREE.Vector3();
                spawnPosSource.getWorldPosition(spawnWorldPos);

                // ALWAYS get the weapon's forward direction from the main weaponGroup (not flash)
                // This ensures consistent direction across all weapons
                const spawnWorldQuat = new THREE.Quaternion();
                weaponGroup.getWorldQuaternion(spawnWorldQuat);

                // Get the local forward direction and rotate it 90 degrees
                // Start with -Z, rotate around Y-axis to point forward
                const localForward = new THREE.Vector3(0, 0, -1);

                // Apply -90 degree rotation around Y-axis to point forward in weapon space
                const rotationQuat = new THREE.Quaternion();
                rotationQuat.setFromAxisAngle(new THREE.Vector3(0, 1, 0), -Math.PI / 2); // -90 degrees

                const direction = localForward.clone();
                direction.applyQuaternion(rotationQuat); // First rotate to point forward
                direction.applyQuaternion(spawnWorldQuat); // Then apply weapon orientation
                direction.normalize();

                // Create weapon-specific projectile
                const projectile = await createProjectileForWeapon(this.animation.weaponName);

                // Check if projectile was created (gauntlet returns null)
                if (!projectile) {
                    return;
                }

                // Position at spawn point with small forward offset
                projectile.position.copy(spawnWorldPos);
                const offset = direction.clone().multiplyScalar(20);
                projectile.position.add(offset);

                // Orient projectile to face the direction of travel
                projectile.lookAt(projectile.position.clone().add(direction));

                // Get velocity from projectile type or use default
                const velocityMagnitude = projectile.userData.velocity || 2000;
                const velocityVector = direction.clone().multiplyScalar(velocityMagnitude);

                // Add projectile data
                projectile.userData.velocity = velocityVector;
                projectile.userData.direction = direction;
                projectile.userData.spawnTime = Date.now();
                projectile.userData.lifetime = projectile.userData.lifetime || 2000; // Use custom or default

                // Add to scene and track
                this.animation.scene.add(projectile);
                this.animation.projectiles.push(projectile);
            };

            // Add method to trigger firing animation
            weaponGroup.userData.fire = function(isFirstFire = false) {
                console.log('🔫🔫🔫 FIRE() CALLED for weapon:', this.animation.weaponName, 'isFirstFire:', isFirstFire);
                this.animation.firing = true;
                this.animation.muzzleFlashTime = Date.now();

                // Only spin barrel for weapons that have spinning barrels (chaingun/vulcan/minigun)
                // Machinegun, plasmagun, and other weapons don't have spinning barrels
                const lowerName = this.animation.weaponName.toLowerCase();
                const hasSpinningBarrel = lowerName.includes('chain') ||
                                         lowerName.includes('vulcan') ||
                                         lowerName.includes('minigun');

                console.log(`🔫 Weapon: ${this.animation.weaponName}, hasSpinningBarrel: ${hasSpinningBarrel}`);

                if (hasSpinningBarrel) {
                    this.animation.barrelSpinning = true;
                    this.animation.barrelTime = Date.now();
                    console.log('🔄 Barrel spinning started');
                }

                // Show muzzle flash
                const flash = weaponGroup.getObjectByName('flash');
                if (flash) {
                    flash.visible = true;
                    // Random rotation for flash
                    flash.rotation.z = Math.random() * Math.PI * 2;
                }

                // Spawn projectile
                this.spawnProjectile();

                // Play fire sounds based on weapon type
                const isLightningGun = lowerName.includes('lightning') || lowerName.includes('lg');
                const isGauntlet = lowerName.includes('gauntlet') || lowerName.includes('gt');
                const isPlasma = lowerName.includes('plasma') || lowerName.includes('pg');
                const isRocket = lowerName.includes('rocket') || lowerName.includes('rl');
                const isMachinegun = lowerName.includes('machine') || lowerName.includes('mg');
                const isGrenade = lowerName.includes('grenade') || lowerName.includes('gl');
                const isRailgun = lowerName.includes('rail') || lowerName.includes('rg');
                const isBFG = lowerName.includes('bfg');

                console.log('🔫🔫🔫 WEAPON DETECTION - lowerName:', lowerName, 'isRailgun:', isRailgun, 'isLightningGun:', isLightningGun, 'isPlasma:', isPlasma);

                if (this.animation.sounds.length > 0) {
                    // IMPORTANT: Check railgun BEFORE lightning gun because "railgun" contains "lg"!
                    if (isRailgun) {
                        console.log('🔫🔫🔫 RAILGUN FIRE METHOD CALLED! isFirstFire:', isFirstFire);

                        // Railgun has 3 sounds:
                        // [0] rg_hum.wav - hum sound (loops CONTINUOUSLY - both idle and firing)
                        // [1] railgf1a.wav - fire sound
                        // [2] plasmx1a.wav - impact sound (ONLY in fireWithHit())

                        if (isFirstFire) {
                            console.log('🔫 Starting hum sound for first fire');
                            // Start hum sound looping ONCE on first fire
                            const humSound = this.animation.sounds[0];
                            if (humSound && !humSound.isPlaying) {
                                humSound.setLoop(true);
                                humSound.play();
                            }
                        }

                        console.log('🔫 About to play railgun fire sound, sounds array length:', this.animation.sounds.length);

                        // Play fire sound - use Web Audio API directly to bypass THREE.js limitations
                        const fireSound = this.animation.sounds[1];
                        console.log('🔫 fireSound exists:', !!fireSound, 'has buffer:', !!fireSound?.buffer);

                        if (fireSound && fireSound.buffer) {
                            console.log('🔫 INSIDE FIRE SOUND BLOCK!');
                            // Use Web Audio API directly - creates independent sound source that plays to completion
                            const audioContext = THREE.AudioContext.getContext();

                            // CRITICAL: Resume audio context in case browser suspended it
                            if (audioContext.state === 'suspended') {
                                console.log('⚠️ AudioContext was suspended, resuming...');
                                audioContext.resume();
                            }

                            const source = audioContext.createBufferSource();
                            source.buffer = fireSound.buffer;

                            // Create gain node for volume control
                            const gainNode = audioContext.createGain();
                            gainNode.gain.value = 0.5;

                            // Connect: source -> gain -> destination
                            source.connect(gainNode);
                            gainNode.connect(audioContext.destination);

                            // Play the sound - it WILL finish no matter what
                            source.start(0);

                            console.log('🔫 RAILGUN FIRE SOUND STARTED - AudioContext state:', audioContext.state, 'Buffer duration:', fireSound.buffer.duration);
                        } else {
                            console.error('❌ RAILGUN FIRE SOUND NOT STARTED - fireSound or buffer missing!');
                        }

                        // NOTE: Impact sound is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isLightningGun) {
                        // Lightning gun has 3 sounds (based on Q3 engine cg_weapons.c):
                        // [0] fsthum.wav - idle hum (loops CONTINUOUSLY - both idle and firing)
                        // [1] lg_hum.wav - firingSound (loops CONTINUOUSLY while firing)
                        // [2] lg_fire.wav - flashSound (plays ONLY ONCE on initial press, NOT every shot!)

                        if (isFirstFire && this.animation.sounds.length >= 3) {
                            // Keep idle sound playing - do NOT stop it
                            // It should loop continuously throughout

                            // Start continuous firing hum (lg_hum.wav) - loops while firing
                            const humSound = this.animation.sounds[1];
                            if (humSound && !humSound.isPlaying) {
                                humSound.setLoop(true);
                                humSound.play();
                            }

                            // Play flash sound (lg_fire.wav) ONLY on first fire
                            const fireSound = this.animation.sounds[2];
                            if (fireSound) {
                                if (fireSound.isPlaying) {
                                    fireSound.stop();
                                }
                                fireSound.play();
                            }
                        }

                        // Do NOTHING on continuous firing - just let lg_hum.wav loop
                    } else if (isGauntlet) {
                        // Gauntlet has 2 sounds (based on Q3 engine cg_weapons.c):
                        // [0] fstrun.wav - firingSound (loops CONTINUOUSLY while firing)
                        // [1] fstatck.wav - flashSound (plays on each attack) - ONLY in fireWithHit()

                        if (isFirstFire && this.animation.sounds.length >= 2) {
                            // Start continuous running sound (fstrun.wav) - loops while firing
                            const runSound = this.animation.sounds[0];
                            if (runSound && !runSound.isPlaying) {
                                runSound.setLoop(true);
                                runSound.play();
                            }
                        }

                        // NOTE: Attack sound (fstatck.wav) is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isPlasma) {
                        // Plasma gun has 2 sounds:
                        // [0] hyprbf1a.wav - fire sound (plays every shot)
                        // [1] plasmx1a.wav - impact sound (ONLY in fireWithHit())

                        // Play ONLY the fire sound (index 0)
                        const fireSound = this.animation.sounds[0];
                        if (fireSound) {
                            if (fireSound.isPlaying) {
                                fireSound.stop();
                            }
                            fireSound.play();
                        }

                        // NOTE: Impact sound (plasmx1a.wav) is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isRocket) {
                        // Rocket launcher has 2 sounds:
                        // [0] rocklf1a.wav - fire sound (plays every shot)
                        // [1] rocklx1a.wav - explosion sound (ONLY in fireWithHit())

                        // Play ONLY the fire sound (index 0)
                        const fireSound = this.animation.sounds[0];
                        if (fireSound) {
                            if (fireSound.isPlaying) {
                                fireSound.stop();
                            }
                            fireSound.play();
                        }

                        // NOTE: Explosion sound (rocklx1a.wav) is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isMachinegun) {
                        // Machinegun has 7 sounds:
                        // [0-3] machgf1b-4.wav - fire sounds (randomly pick one)
                        // [4-6] ric1-3.wav - ricochet sounds (ONLY in fireWithHit())

                        // Play ONE random fire sound from indices 0-3
                        const fireIndex = Math.floor(Math.random() * 4);
                        const fireSound = this.animation.sounds[fireIndex];
                        if (fireSound) {
                            if (fireSound.isPlaying) {
                                fireSound.stop();
                            }
                            fireSound.play();
                        }

                        // NOTE: Ricochet sounds are NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isGrenade) {
                        // Grenade launcher has 2 sounds:
                        // [0] grenlf1a.wav - fire sound
                        // [1] rocklx1a.wav - explosion sound (ONLY in fireWithHit())

                        const fireSound = this.animation.sounds[0];
                        if (fireSound) {
                            if (fireSound.isPlaying) {
                                fireSound.stop();
                            }
                            fireSound.play();
                        }

                        // NOTE: Explosion sound is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else if (isBFG) {
                        // BFG has 3 sounds:
                        // [0] bfg_hum.wav - hum sound
                        // [1] bfg_fire.wav - fire sound
                        // [2] rocklx1a.wav - explosion sound (ONLY in fireWithHit())

                        // Play hum + fire sounds, but not explosion
                        for (let i = 0; i < 2; i++) {
                            const sound = this.animation.sounds[i];
                            if (sound) {
                                if (sound.isPlaying) {
                                    sound.stop();
                                }
                                sound.play();
                            }
                        }

                        // NOTE: Explosion sound is NOT played here
                        // Use fireWithHit() method to play hit sounds
                    } else {
                        // All other weapons: play ALL sounds simultaneously every fire
                        this.animation.sounds.forEach(sound => {
                            if (sound) {
                                // Stop and restart the sound to allow rapid fire overlapping sounds
                                if (sound.isPlaying) {
                                    sound.stop();
                                }
                                sound.play();
                            }
                        });
                    }
                }
            };

            // Add method to trigger firing animation WITH hit sounds
            weaponGroup.userData.fireWithHit = function(isFirstFire = false) {
                // Call regular fire first
                this.fire(isFirstFire);

                // Then play hit sounds for weapons that have them
                const lowerName = this.animation.weaponName.toLowerCase();
                const isLightningGun = lowerName.includes('lightning') || lowerName.includes('lg');
                const isGauntlet = lowerName.includes('gauntlet') || lowerName.includes('gt');
                const isPlasma = lowerName.includes('plasma') || lowerName.includes('pg');
                const isRocket = lowerName.includes('rocket') || lowerName.includes('rl');
                const isMachinegun = lowerName.includes('machine') || lowerName.includes('mg');
                const isGrenade = lowerName.includes('grenade') || lowerName.includes('gl');
                const isRailgun = lowerName.includes('rail') || lowerName.includes('rg');
                const isBFG = lowerName.includes('bfg');

                if (isLightningGun && this.animation.sounds.length >= 6) {
                    // Lightning gun has 3 hit sounds at indices [3], [4], [5]
                    // Randomly play one of them
                    const hitIndex = 3 + Math.floor(Math.random() * 3);
                    const hitSound = this.animation.sounds[hitIndex];
                    if (hitSound) {
                        if (hitSound.isPlaying) {
                            hitSound.stop();
                        }
                        hitSound.play();
                    }
                } else if (isGauntlet && this.animation.sounds.length >= 2) {
                    // Gauntlet has attack sound at index [1]
                    const attackSound = this.animation.sounds[1];
                    if (attackSound) {
                        if (attackSound.isPlaying) {
                            attackSound.stop();
                        }
                        attackSound.play();
                    }
                } else if (isPlasma && this.animation.sounds.length >= 2) {
                    // Plasma gun has impact sound at index [1]
                    const impactSound = this.animation.sounds[1];
                    if (impactSound) {
                        if (impactSound.isPlaying) {
                            impactSound.stop();
                        }
                        impactSound.play();
                    }
                } else if (isRocket && this.animation.sounds.length >= 2) {
                    // Rocket launcher has explosion sound at index [1]
                    const explosionSound = this.animation.sounds[1];
                    if (explosionSound) {
                        if (explosionSound.isPlaying) {
                            explosionSound.stop();
                        }
                        explosionSound.play();
                    }
                } else if (isMachinegun && this.animation.sounds.length >= 7) {
                    // Machinegun has 3 ricochet sounds at indices [4], [5], [6]
                    // Randomly play one of them
                    const ricIndex = 4 + Math.floor(Math.random() * 3);
                    const ricSound = this.animation.sounds[ricIndex];
                    if (ricSound) {
                        if (ricSound.isPlaying) {
                            ricSound.stop();
                        }
                        ricSound.play();
                    }
                } else if (isGrenade && this.animation.sounds.length >= 2) {
                    // Grenade launcher has explosion sound at index [1]
                    const explosionSound = this.animation.sounds[1];
                    if (explosionSound) {
                        if (explosionSound.isPlaying) {
                            explosionSound.stop();
                        }
                        explosionSound.play();
                    }
                } else if (isRailgun && this.animation.sounds.length >= 3) {
                    // Railgun has impact sound at index [2]
                    const impactSound = this.animation.sounds[2];
                    if (impactSound) {
                        if (impactSound.isPlaying) {
                            impactSound.stop();
                        }
                        impactSound.play();
                    }
                } else if (isBFG && this.animation.sounds.length >= 3) {
                    // BFG has explosion sound at index [2]
                    const explosionSound = this.animation.sounds[2];
                    if (explosionSound) {
                        if (explosionSound.isPlaying) {
                            explosionSound.stop();
                        }
                        explosionSound.play();
                    }
                }
            };

            // Add method to update weapon animations
            weaponGroup.userData.updateAnimation = function(deltaTime) {
                const now = Date.now();
                const MUZZLE_FLASH_TIME = 20; // milliseconds
                const SPIN_SPEED = 0.9; // degrees per millisecond
                const COAST_TIME = 1000; // milliseconds

                // Update muzzle flash visibility
                if (this.animation.muzzleFlashTime > 0) {
                    if (now - this.animation.muzzleFlashTime > MUZZLE_FLASH_TIME) {
                        // Hide flash after duration
                        const flash = weaponGroup.getObjectByName('flash');
                        if (flash) {
                            flash.visible = false;
                        }
                        this.animation.muzzleFlashTime = 0;
                        // DON'T set firing = false here! That's controlled by stopFiring() method
                        // The firing state needs to persist while the button is held down
                    }
                }

                // Update barrel/blade spinning (for weapons with spinning parts)
                const lowerName = this.animation.weaponName.toLowerCase();
                const hasSpinningBarrel = lowerName.includes('chain') ||
                                         lowerName.includes('vulcan') ||
                                         lowerName.includes('minigun');
                const hasSpinningBlade = lowerName.includes('gauntlet') || lowerName.includes('gt');

                if (hasSpinningBarrel) {
                    const barrel = weaponGroup.getObjectByName('barrel');
                    if (barrel) {
                        const delta = now - this.animation.barrelTime;
                        let angle;

                        if (this.animation.barrelSpinning) {
                            // Spinning
                            angle = this.animation.barrelAngle + delta * SPIN_SPEED;
                        } else {
                            // Coasting to a stop
                            const coastDelta = Math.min(delta, COAST_TIME);
                            const speed = 0.5 * (SPIN_SPEED + (COAST_TIME - coastDelta) / COAST_TIME);
                            angle = this.animation.barrelAngle + coastDelta * speed;
                        }

                        // Apply rotation to barrel (roll axis)
                        barrel.rotation.z = (angle * Math.PI) / 180; // Convert to radians

                        // Stop spinning after coast time
                        if (!this.animation.barrelSpinning && delta > COAST_TIME) {
                            this.animation.barrelAngle = angle;
                        } else {
                            this.animation.barrelAngle = angle;
                            this.animation.barrelTime = now;
                        }
                    }
                }

                // Update gauntlet blade spinning
                if (hasSpinningBlade) {
                    // Find the barrel (in gauntlet, the spinning blade uses barrel tag)
                    // Need to search recursively since barrel is attached to main body
                    let blade = null;
                    weaponGroup.traverse((child) => {
                        if (child.name === 'barrel' && !blade) {
                            blade = child;
                        }
                    });
                    if (blade) {
                        // Store initial quaternion on first access
                        if (!blade.userData.initialQuaternion) {
                            blade.userData.initialQuaternion = blade.quaternion.clone();
                        }

                        const delta = now - this.animation.barrelTime;

                        // Gauntlet blade spins ONLY while firing (no coasting)
                        if (this.animation.firing) {
                            // Calculate spin angle
                            const angle = this.animation.barrelAngle + delta * (SPIN_SPEED * 2);

                            // Start from initial rotation
                            blade.quaternion.copy(blade.userData.initialQuaternion);

                            // Apply spin around local X axis
                            const spinQuat = new THREE.Quaternion();
                            spinQuat.setFromAxisAngle(new THREE.Vector3(1, 0, 0), (angle * Math.PI) / 180);
                            blade.quaternion.multiply(spinQuat);

                            this.animation.barrelAngle = angle;
                            this.animation.barrelTime = now;
                        } else {
                            // Reset to initial rotation when not firing
                            blade.quaternion.copy(blade.userData.initialQuaternion);
                            this.animation.barrelAngle = 0;
                        }

                        // Add light effect ONLY when firing
                        if (this.animation.firing) {
                            // Add or update point light for blade glow
                            let bladeLight = blade.getObjectByName('blade_light');
                            if (!bladeLight) {
                                bladeLight = new THREE.PointLight(0x6666ff, 2, 50);
                                bladeLight.name = 'blade_light';
                                blade.add(bladeLight);
                            }
                            bladeLight.intensity = 2 + Math.sin(this.animation.barrelAngle * 0.1) * 0.5; // Pulsing effect
                        } else {
                            // Remove light when not firing
                            const bladeLight = blade.getObjectByName('blade_light');
                            if (bladeLight) {
                                blade.remove(bladeLight);
                            }
                        }
                    }
                }

                // Update projectiles
                const projectilesToRemove = [];
                for (let i = 0; i < this.animation.projectiles.length; i++) {
                    const projectile = this.animation.projectiles[i];
                    const age = now - projectile.userData.spawnTime;

                    // Remove old projectiles
                    if (age > projectile.userData.lifetime) {
                        projectilesToRemove.push(i);
                        if (this.animation.scene) {
                            this.animation.scene.remove(projectile);
                        }
                        continue;
                    }

                    // Update position based on velocity
                    const velocity = projectile.userData.velocity;
                    const oldPos = projectile.position.clone();
                    projectile.position.x += velocity.x * deltaTime;
                    projectile.position.y += velocity.y * deltaTime;
                    projectile.position.z += velocity.z * deltaTime;

                    // Apply gravity to grenades
                    if (projectile.userData.hasGravity) {
                        const gravity = -500; // units/second^2
                        projectile.userData.velocity.y += gravity * deltaTime;
                    }

                    // Animate the projectile based on type
                    const timeInSeconds = age / 1000;

                    // Plasma projectile animation (sprite pulsing like Q3)
                    if (projectile.userData.projectileType === 'plasma' && projectile.userData.sprite) {
                        // Pulse the sprite opacity
                        const pulse = Math.sin(timeInSeconds * 10) * 0.2 + 0.8;
                        projectile.userData.sprite.material.opacity = 0.8 * pulse;

                        // Pulse the light intensity
                        projectile.userData.light.intensity = 3 + Math.sin(timeInSeconds * 12) * 1.5;

                        // Rotate sprite slowly
                        projectile.userData.sprite.material.rotation += deltaTime * 2;
                    }

                    // Lightning beam animation (flickering electric arc)
                    if (projectile.userData.projectileType === 'lightning') {
                        // Pulse the light rapidly
                        if (projectile.userData.light) {
                            projectile.userData.light.intensity = 4 + Math.random() * 3;
                        }

                        // Animate the procedural beam group (4 rotating cylinders)
                        if (projectile.userData.beamGroup) {
                            // Flicker each beam cylinder independently for electric arc effect
                            projectile.userData.beamGroup.children.forEach((beam) => {
                                if (beam.material) {
                                    const flicker = Math.random() * 0.4 + 0.6; // 0.6 to 1.0
                                    beam.material.opacity = 0.7 * flicker;

                                    // Slight random scale variation
                                    beam.scale.x = 1 + (Math.random() - 0.5) * 0.2;
                                    beam.scale.z = 1 + (Math.random() - 0.5) * 0.2;
                                }
                            });
                        }
                    }

                    // Rocket projectile animation (flame flicker, rotation)
                    if (projectile.userData.rocket) {
                        // Spin the rocket slowly
                        projectile.userData.rocket.rotation.z += deltaTime * 3;

                        // Flicker the flame
                        if (projectile.userData.flame) {
                            const flicker = Math.sin(timeInSeconds * 20) * 0.2 + 0.8;
                            projectile.userData.flame.material.opacity = 0.8 * flicker;
                            projectile.userData.flame.scale.y = 1 + Math.sin(timeInSeconds * 15) * 0.3;
                        }

                        // Pulse the light
                        projectile.userData.light.intensity = 4 + Math.sin(timeInSeconds * 15) * 2;
                    }

                    // Grenade projectile animation (tumbling)
                    if (projectile.userData.grenade) {
                        projectile.userData.grenade.rotation.x += deltaTime * 5;
                        projectile.userData.grenade.rotation.y += deltaTime * 3;
                    }

                    // Grapple hook animation (spinning rocket model)
                    if (projectile.userData.projectileType === 'grapple') {
                        // Spin the hook/rocket model if loaded
                        if (projectile.userData.hookModel) {
                            projectile.userData.hookModel.rotation.z += deltaTime * 8; // Fast spin like drilling
                        }
                    }

                    // BFG projectile animation (pulsing multi-layered spheres)
                    if (projectile.userData.projectileType === 'bfg') {
                        // Pulse the core
                        if (projectile.userData.core) {
                            const corePulse = Math.sin(timeInSeconds * 10) * 0.1 + 1.0;
                            projectile.userData.core.scale.setScalar(corePulse);
                        }

                        // Pulse the inner glow at different rate
                        if (projectile.userData.innerGlow) {
                            const innerPulse = Math.sin(timeInSeconds * 7) * 0.15 + 1.0;
                            projectile.userData.innerGlow.scale.setScalar(innerPulse);
                            // Rotate inner glow slowly
                            projectile.userData.innerGlow.rotation.y += deltaTime * 2;
                            projectile.userData.innerGlow.rotation.x += deltaTime * 1;
                        }

                        // Pulse the outer glow at different rate
                        if (projectile.userData.outerGlow) {
                            const outerPulse = Math.sin(timeInSeconds * 5) * 0.2 + 1.0;
                            projectile.userData.outerGlow.scale.setScalar(outerPulse);
                            // Rotate outer glow slowly in opposite direction
                            projectile.userData.outerGlow.rotation.y -= deltaTime * 1.5;
                            projectile.userData.outerGlow.rotation.z += deltaTime * 0.8;
                        }

                        // Pulse the green light intensely
                        if (projectile.userData.light) {
                            projectile.userData.light.intensity = 12 + Math.sin(timeInSeconds * 12) * 6;
                        }
                    }

                    // Rail beam animation (fade out quickly)
                    if (projectile.userData.beam) {
                        const fadeSpeed = age / projectile.userData.lifetime;
                        projectile.userData.beam.material.opacity = 0.7 * (1 - fadeSpeed);
                    }

                    // Bullet animation (slight rotation)
                    if (projectile.userData.bullet) {
                        projectile.userData.bullet.rotation.x += deltaTime * 20;
                    }

                    // Fade out near end of lifetime
                    const lifetimePercent = age / projectile.userData.lifetime;
                    if (lifetimePercent > 0.7) {
                        const fadePercent = (lifetimePercent - 0.7) / 0.3;

                        // Fade plasma sprite
                        if (projectile.userData.sprite) {
                            projectile.userData.sprite.material.opacity *= (1 - fadePercent);
                        }

                        // Fade rocket
                        if (projectile.userData.rocket) {
                            projectile.userData.rocket.material.opacity = 1.0 * (1 - fadePercent);
                            projectile.userData.rocket.material.transparent = true;
                            if (projectile.userData.flame) {
                                projectile.userData.flame.material.opacity *= (1 - fadePercent);
                            }
                        }

                        // Fade others
                        if (projectile.userData.grenade) {
                            projectile.userData.grenade.material.opacity = 1.0 * (1 - fadePercent);
                            projectile.userData.grenade.material.transparent = true;
                        }
                        if (projectile.userData.bullet) {
                            projectile.userData.bullet.material.opacity = 1.0 * (1 - fadePercent);
                            projectile.userData.bullet.material.transparent = true;
                        }
                        if (projectile.userData.beam) {
                            projectile.userData.beam.material.opacity *= (1 - fadePercent);
                        }

                        // Fade light for all projectiles
                        if (projectile.userData.light) {
                            projectile.userData.light.intensity *= (1 - fadePercent);
                        }
                    }
                }

                // Remove dead projectiles from array (iterate backwards to avoid index issues)
                for (let i = projectilesToRemove.length - 1; i >= 0; i--) {
                    this.animation.projectiles.splice(projectilesToRemove[i], 1);
                }
            };

            // Add method to stop firing
            weaponGroup.userData.stopFiring = function() {
                this.animation.firing = false;
                this.animation.barrelSpinning = false;
                this.animation.barrelTime = Date.now();

                const lowerName = this.animation.weaponName.toLowerCase();
                const isLightningGun = lowerName.includes('lightning') || lowerName.includes('lg');
                const isGauntlet = lowerName.includes('gauntlet') || lowerName.includes('gt');

                // For lightning gun, restart idle sound when stopping fire
                if (isLightningGun && this.animation.sounds.length >= 3) {
                    // Stop the repeating fire hum
                    const humSound = this.animation.sounds[1];
                    if (humSound && humSound.isPlaying) {
                        humSound.stop();
                    }

                    // Restart idle sound (fsthum.wav)
                    const idleSound = this.animation.sounds[0];
                    if (idleSound && !idleSound.isPlaying) {
                        idleSound.setLoop(true);
                        idleSound.play();
                    }
                }

                // For gauntlet, stop the running sound when stopping fire
                if (isGauntlet && this.animation.sounds.length >= 2) {
                    // Stop the running sound (fstrun.wav)
                    const runSound = this.animation.sounds[0];
                    if (runSound && runSound.isPlaying) {
                        runSound.stop();
                    }
                }
            };

            return weaponGroup;

        } catch (error) {
            console.error('Failed to load complete weapon model:', error);
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
            const response = await this.fetchCaseInsensitive(url);
            if (!response.ok) {
                return null;
            }
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
        const surfaces = modelPart.children.filter(child => child.userData.surfaceData);

        if (!frames || surfaces.length === 0) return null;

        const tracks = [];
        const duration = animDef.numFrames / animDef.fps;
        const timeStep = 1 / animDef.fps;

        // Create tracks for each mesh
        surfaces.forEach(mesh => {
            const surface = mesh.userData.surfaceData;
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

    /**
     * Convert AudioBuffer to WAV format
     * @param {AudioBuffer} buffer - The audio buffer to convert
     * @returns {ArrayBuffer} - WAV file data
     */
    audioBufferToWav(buffer) {
        const numberOfChannels = buffer.numberOfChannels;
        const sampleRate = buffer.sampleRate;
        const format = 1; // PCM
        const bitDepth = 16;

        const bytesPerSample = bitDepth / 8;
        const blockAlign = numberOfChannels * bytesPerSample;

        const data = [];
        for (let i = 0; i < buffer.numberOfChannels; i++) {
            data.push(buffer.getChannelData(i));
        }

        const dataLength = buffer.length * numberOfChannels * bytesPerSample;
        const bufferLength = 44 + dataLength;
        const arrayBuffer = new ArrayBuffer(bufferLength);
        const view = new DataView(arrayBuffer);

        // Write WAV header
        const writeString = (offset, string) => {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        };

        writeString(0, 'RIFF');
        view.setUint32(4, 36 + dataLength, true);
        writeString(8, 'WAVE');
        writeString(12, 'fmt ');
        view.setUint32(16, 16, true); // fmt chunk size
        view.setUint16(20, format, true);
        view.setUint16(22, numberOfChannels, true);
        view.setUint32(24, sampleRate, true);
        view.setUint32(28, sampleRate * blockAlign, true);
        view.setUint16(32, blockAlign, true);
        view.setUint16(34, bitDepth, true);
        writeString(36, 'data');
        view.setUint32(40, dataLength, true);

        // Write audio data
        let offset = 44;
        for (let i = 0; i < buffer.length; i++) {
            for (let channel = 0; channel < numberOfChannels; channel++) {
                const sample = Math.max(-1, Math.min(1, data[channel][i]));
                view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7FFF, true);
                offset += 2;
            }
        }

        return arrayBuffer;
    }
}
