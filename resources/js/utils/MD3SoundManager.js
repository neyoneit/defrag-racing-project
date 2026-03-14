/**
 * MD3SoundManager - Manages sound loading and playback for Quake 3 player models
 * Handles WAV files using Web Audio API and syncs sounds with animations
 */
export class MD3SoundManager {
    constructor(baseSoundPath, modelName, baseModelSoundPath = null, loader = null) {
        this.baseSoundPath = baseSoundPath; // e.g., /storage/models/extracted/worm-1760208803/sound/player/worm (PK3 path)
        this.baseModelSoundPath = baseModelSoundPath; // e.g., /baseq3/sound/player/grunt (base model path - load first, then override with PK3)
        this.modelName = modelName;
        this.loader = loader; // MD3Loader instance for case-insensitive fetching
        this.audioContext = null;
        this.soundBuffers = new Map(); // Map of sound name -> AudioBuffer
        this.currentSources = new Map(); // Map of sound name -> AudioBufferSourceNode
        this.volume = 0.5;
        this.enabled = true;

        // Fallback sound path for base Q3 models (use sarge sounds if model sounds don't exist)
        this.fallbackSoundPath = null;
        if (baseSoundPath.includes('/baseq3/')) {
            // For base Q3 models, fallback to sarge sounds
            this.fallbackSoundPath = '/baseq3/sound/player/sarge';
        }

        // Sound mapping for animations
        this.animationSounds = {
            'LEGS_JUMP': ['jump1'],
            'LEGS_LAND': ['fall1'],
            'LEGS_LANDB': ['fall1'],
            'TORSO_GESTURE': ['taunt'],
            'BOTH_DEATH1': ['death1'],
            'BOTH_DEATH2': ['death2'],
            'BOTH_DEATH3': ['death3'],
        };

        // Available sound files in Quake 3 player models
        this.soundFiles = [
            'death1.wav',
            'death2.wav',
            'death3.wav',
            'drown.wav',
            'fall1.wav',
            'falling1.wav',
            'gasp.wav',
            'jump1.wav',
            'pain25_1.wav',
            'pain50_1.wav',
            'pain75_1.wav',
            'pain100_1.wav',
            'taunt.wav',
        ];
    }

    /**
     * Initialize the Audio Context (must be called after user interaction)
     */
    async initialize() {
        if (this.audioContext) {
            return; // Already initialized
        }

        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (error) {
            console.error('Failed to create audio context:', error);
            this.enabled = false;
        }
    }

    /**
     * Load all sound files for the player model
     * Follows the pattern: Load from base model first, then override with PK3
     */
    async loadSounds() {
        if (!this.enabled || !this.audioContext) {
            await this.initialize();
        }

        if (!this.enabled) {
            console.warn('Audio disabled, skipping sound loading');
            return;
        }

        let baseModelLoadedCount = 0;
        let pk3LoadedCount = 0;
        let pk3OverrideCount = 0;

        // STEP 1: Load sounds from base model (if baseModelSoundPath provided)
        if (this.baseModelSoundPath) {
            console.log(`🔊 STEP 1: Loading sounds from base model: ${this.baseModelSoundPath}`);
            const baseModelPromises = this.soundFiles.map(async (filename) => {
                try {
                    const soundName = filename.replace('.wav', '');
                    const url = `${this.baseModelSoundPath}/${filename}`;
                    const response = this.loader ? await this.loader.fetchCaseInsensitive(url) : await fetch(url);

                    if (!response.ok) {
                        return null;
                    }

                    const arrayBuffer = await response.arrayBuffer();
                    const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);

                    this.soundBuffers.set(soundName, audioBuffer);
                    return soundName;
                } catch (error) {
                    return null;
                }
            });

            const baseModelResults = await Promise.all(baseModelPromises);
            baseModelLoadedCount = baseModelResults.filter(r => r !== null).length;
            if (baseModelLoadedCount > 0) {
                console.log(`✅ Loaded ${baseModelLoadedCount}/${this.soundFiles.length} sounds from base model`);
            }
        }

        // STEP 2: Load sounds from PK3 and override (if they exist)
        console.log(`🔊 STEP 2: Loading sounds from PK3: ${this.baseSoundPath}`);
        const pk3Promises = this.soundFiles.map(async (filename) => {
            try {
                const soundName = filename.replace('.wav', '');
                const url = `${this.baseSoundPath}/${filename}`;
                const response = this.loader ? await this.loader.fetchCaseInsensitive(url) : await fetch(url);

                if (!response.ok) {
                    // If not found in PK3 and we have a fallback path (for pure baseq3 models), try fallback
                    if (this.fallbackSoundPath && !this.baseModelSoundPath) {
                        const fallbackUrl = `${this.fallbackSoundPath}/${filename}`;
                        const fallbackResponse = this.loader ? await this.loader.fetchCaseInsensitive(fallbackUrl) : await fetch(fallbackUrl);
                        if (fallbackResponse.ok) {
                            const arrayBuffer = await fallbackResponse.arrayBuffer();
                            const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
                            this.soundBuffers.set(soundName, audioBuffer);
                            console.log(`🔊 Sound fallback: ${soundName} → ${fallbackUrl}`);
                            return { soundName, override: false };
                        }
                    }
                    return null;
                }

                const arrayBuffer = await response.arrayBuffer();
                const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);

                const wasOverride = this.soundBuffers.has(soundName);
                this.soundBuffers.set(soundName, audioBuffer);

                return { soundName, override: wasOverride };
            } catch (error) {
                return null;
            }
        });

        const pk3Results = await Promise.all(pk3Promises);
        const validPk3Results = pk3Results.filter(r => r !== null);
        pk3LoadedCount = validPk3Results.length;
        pk3OverrideCount = validPk3Results.filter(r => r.override).length;

        if (pk3OverrideCount > 0) {
            console.log(`✅ Loaded ${pk3LoadedCount} sounds from PK3 (${pk3OverrideCount} overrides)`);
        } else if (pk3LoadedCount > 0) {
            console.log(`✅ Loaded ${pk3LoadedCount} sounds from PK3`);
        }

        const totalLoadedCount = this.soundBuffers.size;
        if (totalLoadedCount > 0) {
            console.log(`✅ Total sounds loaded: ${totalLoadedCount}/${this.soundFiles.length}`);
        }

        return totalLoadedCount > 0;
    }

    /**
     * Play a specific sound by name
     */
    playSound(soundName, options = {}) {
        if (!this.enabled || !this.audioContext || !this.soundBuffers.has(soundName)) {
            return null;
        }

        // Stop any currently playing instance of this sound if interrupt is enabled
        if (options.interrupt !== false && this.currentSources.has(soundName)) {
            this.stopSound(soundName);
        }

        try {
            const buffer = this.soundBuffers.get(soundName);
            const source = this.audioContext.createBufferSource();
            const gainNode = this.audioContext.createGain();

            source.buffer = buffer;

            // Set volume
            const volume = options.volume !== undefined ? options.volume : this.volume;
            gainNode.gain.value = volume;

            // Connect: source -> gain -> destination
            source.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            // Handle playback end
            source.onended = () => {
                this.currentSources.delete(soundName);
            };

            // Start playback
            source.start(0);
            this.currentSources.set(soundName, source);

            return source;
        } catch (error) {
            console.error(`Failed to play sound ${soundName}:`, error);
            return null;
        }
    }

    /**
     * Stop a specific sound
     */
    stopSound(soundName) {
        if (this.currentSources.has(soundName)) {
            try {
                const source = this.currentSources.get(soundName);
                source.stop();
                this.currentSources.delete(soundName);
            } catch (error) {
                // Sound might have already stopped
                this.currentSources.delete(soundName);
            }
        }
    }

    /**
     * Stop all currently playing sounds
     */
    stopAllSounds() {
        for (const [soundName, source] of this.currentSources.entries()) {
            try {
                source.stop();
            } catch (error) {
                // Ignore errors for already stopped sounds
            }
        }
        this.currentSources.clear();
    }

    /**
     * Play sound associated with an animation
     */
    playAnimationSound(animationName, options = {}) {
        if (!this.animationSounds[animationName]) {
            return null;
        }

        // Get the sound(s) for this animation
        const sounds = this.animationSounds[animationName];

        // Pick a random sound if multiple are available
        const soundName = sounds[Math.floor(Math.random() * sounds.length)];

        return this.playSound(soundName, options);
    }

    /**
     * Set master volume (0.0 to 1.0)
     */
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
    }

    /**
     * Enable/disable sound playback
     */
    setEnabled(enabled) {
        this.enabled = enabled;
        if (!enabled) {
            this.stopAllSounds();
        }
    }

    /**
     * Check if sounds are loaded
     */
    isLoaded() {
        return this.soundBuffers.size > 0;
    }

    /**
     * Get list of loaded sounds (sorted alphabetically)
     */
    getLoadedSounds() {
        return Array.from(this.soundBuffers.keys()).sort();
    }

    /**
     * Cleanup and release resources
     */
    dispose() {
        this.stopAllSounds();
        this.soundBuffers.clear();

        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
    }
}
