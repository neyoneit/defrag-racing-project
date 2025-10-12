/**
 * MD3SoundManager - Manages sound loading and playback for Quake 3 player models
 * Handles WAV files using Web Audio API and syncs sounds with animations
 */
export class MD3SoundManager {
    constructor(baseSoundPath, modelName) {
        this.baseSoundPath = baseSoundPath; // e.g., /storage/models/extracted/worm-1760208803/sound/player/worm
        this.modelName = modelName;
        this.audioContext = null;
        this.soundBuffers = new Map(); // Map of sound name -> AudioBuffer
        this.currentSources = new Map(); // Map of sound name -> AudioBufferSourceNode
        this.volume = 0.5;
        this.enabled = true;

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
     */
    async loadSounds() {
        if (!this.enabled || !this.audioContext) {
            await this.initialize();
        }

        if (!this.enabled) {
            console.warn('Audio disabled, skipping sound loading');
            return;
        }

        const loadPromises = this.soundFiles.map(async (filename) => {
            try {
                const soundName = filename.replace('.wav', '');
                const url = `${this.baseSoundPath}/${filename}`;

                const response = await fetch(url);
                if (!response.ok) {
                    console.warn(`Sound file not found: ${url}`);
                    return null;
                }

                const arrayBuffer = await response.arrayBuffer();
                const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);

                this.soundBuffers.set(soundName, audioBuffer);
                return soundName;
            } catch (error) {
                console.warn(`Failed to load sound ${filename}:`, error);
                return null;
            }
        });

        const results = await Promise.all(loadPromises);
        const loadedCount = results.filter(r => r !== null).length;
        return loadedCount > 0;
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
