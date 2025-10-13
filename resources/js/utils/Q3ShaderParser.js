/**
 * Quake 3 Shader Parser
 * Parses .shader files and returns shader definitions
 */
export class Q3ShaderParser {
    constructor() {
        this.shaders = new Map();
    }

    /**
     * Parse a shader file content
     */
    parseShaderFile(content) {
        const lines = content.split('\n');
        let currentShader = null;
        let currentStage = null;
        let braceDepth = 0;
        let inShaderBlock = false;
        let inStageBlock = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();

            // Skip comments
            if (line.startsWith('//')) continue;

            // Remove inline comments
            const commentIndex = line.indexOf('//');
            if (commentIndex !== -1) {
                line = line.substring(0, commentIndex).trim();
            }

            if (!line) continue;

            // Check for opening brace
            if (line.includes('{')) {
                braceDepth++;

                if (braceDepth === 1 && currentShader) {
                    // Entering shader block
                    inShaderBlock = true;
                } else if (braceDepth === 2 && inShaderBlock) {
                    // Entering stage block
                    inStageBlock = true;
                    currentStage = {
                        map: null,
                        blendFunc: null,
                        rgbGen: null,
                        alphaGen: null,
                        tcGen: null,
                        tcMod: [],
                        depthWrite: true,
                        depthFunc: 'lequal',
                        alphaFunc: null
                    };
                }

                // Remove the brace from the line for further processing
                line = line.replace('{', '').trim();
            }

            // Check for closing brace
            if (line.includes('}')) {
                braceDepth--;

                if (braceDepth === 1 && inStageBlock) {
                    // Exiting stage block
                    if (currentStage && currentShader) {
                        currentShader.stages.push(currentStage);
                    }
                    currentStage = null;
                    inStageBlock = false;
                } else if (braceDepth === 0 && inShaderBlock) {
                    // Exiting shader block
                    if (currentShader) {
                        this.shaders.set(currentShader.name, currentShader);
                    }
                    currentShader = null;
                    inShaderBlock = false;
                }

                // Remove the brace from the line
                line = line.replace('}', '').trim();
            }

            if (!line) continue;

            // If we're not in any block, this is a shader name
            if (braceDepth === 0 && !inShaderBlock) {
                currentShader = {
                    name: line,
                    cull: null,  // No default cull mode - will be set by shader or use material default
                    surfaceParms: [],
                    deformVertexes: [],  // Array of vertex deformation effects
                    stages: []
                };
            }
            // Parse shader-level properties
            else if (braceDepth === 1 && inShaderBlock && !inStageBlock) {
                const [command, ...args] = line.split(/\s+/);

                switch (command.toLowerCase()) {
                    case 'cull':
                        currentShader.cull = args[0].toLowerCase();
                        break;
                    case 'surfaceparm':
                        currentShader.surfaceParms.push(args[0].toLowerCase());
                        break;
                    case 'nomipmaps':
                        currentShader.noMipMaps = true;
                        break;
                    case 'nopicmip':
                        currentShader.noPicMip = true;
                        break;
                    case 'deformvertexes':
                        // Parse deformVertexes: wave, move, normal, autosprite, autosprite2, bulge, etc.
                        currentShader.deformVertexes.push({
                            type: args[0].toLowerCase(),
                            params: args.slice(1)
                        });
                        break;
                }
            }
            // Parse stage-level properties
            else if (braceDepth === 2 && inStageBlock && currentStage) {
                const [command, ...args] = line.split(/\s+/);

                switch (command.toLowerCase()) {
                    case 'map':
                    case 'clampmap':
                    case 'animmap':
                        currentStage.map = args[0];
                        currentStage.mapType = command.toLowerCase();
                        break;
                    case 'blendfunc':
                        if (args.length === 1) {
                            currentStage.blendFunc = args[0].toLowerCase();
                        } else if (args.length === 2) {
                            currentStage.blendFunc = {
                                src: args[0].toUpperCase(),
                                dst: args[1].toUpperCase()
                            };
                        }
                        break;
                    case 'rgbgen':
                        currentStage.rgbGen = args.join(' ').toLowerCase();
                        break;
                    case 'alphagen':
                        currentStage.alphaGen = args.join(' ').toLowerCase();
                        break;
                    case 'tcgen':
                        currentStage.tcGen = args[0].toLowerCase();
                        break;
                    case 'tcmod':
                        currentStage.tcMod.push({
                            type: args[0].toLowerCase(),
                            params: args.slice(1)
                        });
                        break;
                    case 'depthwrite':
                        currentStage.depthWrite = true;
                        break;
                    case 'alphafunc':
                        currentStage.alphaFunc = args[0].toUpperCase();
                        break;
                    case 'depthfunc':
                        currentStage.depthFunc = args[0].toLowerCase();
                        break;
                }
            }
        }

        return this.shaders;
    }

    /**
     * Get a shader by name
     */
    getShader(name) {
        return this.shaders.get(name);
    }

    /**
     * Get all shaders
     */
    getAllShaders() {
        return this.shaders;
    }

    /**
     * Clear all parsed shaders
     */
    clear() {
        this.shaders.clear();
    }
}

export default Q3ShaderParser;
