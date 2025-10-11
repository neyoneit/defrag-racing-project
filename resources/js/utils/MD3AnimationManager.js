/**
 * MD3 Animation Manager
 * Handles MD3 model animations by updating vertex positions per frame
 */
export class MD3AnimationManager {
    constructor(playerModel) {
        this.playerModel = playerModel;
        this.animations = playerModel.userData.animations;

        this.currentLegsAnim = null;
        this.currentTorsoAnim = null;

        this.legsFrame = 0;
        this.torsoFrame = 0;

        this.legsTime = 0;
        this.torsoTime = 0;

        this.playing = false;

        // Sound callback for animation events
        this.soundCallback = null;

        // Track which animations have triggered sounds
        this.soundTriggered = {
            legs: false,
            torso: false
        };
    }

    /**
     * Set callback function for playing sounds
     * @param {Function} callback - Function to call with animation name
     */
    setSoundCallback(callback) {
        this.soundCallback = callback;
    }

    /**
     * Play a legs animation
     * @param {string} animName - Animation name (e.g., 'LEGS_WALK', 'LEGS_RUN')
     */
    playLegsAnimation(animName) {
        if (!this.animations || !this.animations.legs[animName]) {
            console.warn(`Animation ${animName} not found`);
            return;
        }

        this.currentLegsAnim = this.animations.legs[animName];
        this.legsFrame = 0;
        this.legsTime = 0;
        this.soundTriggered.legs = false;
        this.playing = true;

        // Trigger sound at animation start
        if (this.soundCallback) {
            this.soundCallback(animName);
        }
    }

    /**
     * Play a torso animation
     * @param {string} animName - Animation name (e.g., 'TORSO_STAND', 'TORSO_ATTACK')
     */
    playTorsoAnimation(animName) {
        if (!this.animations || !this.animations.torso[animName]) {
            console.warn(`Animation ${animName} not found`);
            return;
        }

        this.currentTorsoAnim = this.animations.torso[animName];
        this.torsoFrame = 0;
        this.torsoTime = 0;
        this.soundTriggered.torso = false;
        this.playing = true;

        // Trigger sound at animation start
        if (this.soundCallback) {
            this.soundCallback(animName);
        }
    }

    /**
     * Update animations
     * @param {number} deltaTime - Time since last update in seconds
     */
    update(deltaTime) {
        if (!this.playing) return;

        // Update legs animation
        if (this.currentLegsAnim) {
            this.updateModelPartAnimation(
                this.playerModel.userData.lower,
                this.currentLegsAnim,
                deltaTime,
                'legs'
            );
        }

        // Update torso animation
        if (this.currentTorsoAnim) {
            this.updateModelPartAnimation(
                this.playerModel.userData.upper,
                this.currentTorsoAnim,
                deltaTime,
                'torso'
            );
        }
    }

    /**
     * Update a specific model part's animation
     * @param {THREE.Group} modelPart - The model part (lower or upper)
     * @param {Object} anim - Animation definition
     * @param {number} deltaTime - Time delta
     * @param {string} type - 'legs' or 'torso'
     */
    updateModelPartAnimation(modelPart, anim, deltaTime, type) {
        if (!modelPart) return;

        const frameTime = 1 / anim.fps;
        const isLegs = type === 'legs';

        // Update time
        if (isLegs) {
            this.legsTime += deltaTime;
        } else {
            this.torsoTime += deltaTime;
        }

        const currentTime = isLegs ? this.legsTime : this.torsoTime;

        // Calculate current frame
        const frameFloat = (currentTime / frameTime);
        let frame = Math.floor(frameFloat);

        // Handle looping
        if (anim.loop && frame >= anim.numFrames) {
            frame = frame % anim.loopingFrames;
            if (isLegs) {
                this.legsTime = frame * frameTime;
            } else {
                this.torsoTime = frame * frameTime;
            }
        } else if (frame >= anim.numFrames) {
            frame = anim.numFrames - 1;
        }

        if (isLegs) {
            this.legsFrame = frame;
        } else {
            this.torsoFrame = frame;
        }

        // Update all meshes in this model part
        modelPart.children.forEach(child => {
            if (child.userData.surface) {
                this.updateMeshFrame(child, anim, frame);
            }
        });
    }

    /**
     * Update mesh geometry to a specific frame
     * @param {THREE.Mesh} mesh - The mesh to update
     * @param {Object} anim - Animation definition
     * @param {number} frame - Frame number
     */
    updateMeshFrame(mesh, anim, frame) {
        const surface = mesh.userData.surface;
        if (!surface || !surface.vertices) return;

        const frameIndex = anim.firstFrame + frame;
        if (frameIndex >= surface.vertices.length) return;

        const frameVerts = surface.vertices[frameIndex];
        const geometry = mesh.geometry;
        const positionAttr = geometry.attributes.position;
        const normalAttr = geometry.attributes.normal;

        // Update vertex positions and normals
        for (let i = 0; i < surface.numVerts; i++) {
            const vert = frameVerts[i];

            positionAttr.setXYZ(i, vert.x, vert.y, vert.z);
            normalAttr.setXYZ(i, vert.normal.x, vert.normal.y, vert.normal.z);
        }

        positionAttr.needsUpdate = true;
        normalAttr.needsUpdate = true;
    }

    /**
     * Stop all animations
     */
    stop() {
        this.playing = false;
        this.currentLegsAnim = null;
        this.currentTorsoAnim = null;
    }

    /**
     * Get available animations
     */
    getAvailableAnimations() {
        if (!this.animations) return { legs: [], torso: [] };

        return {
            legs: Object.keys(this.animations.legs),
            torso: Object.keys(this.animations.torso)
        };
    }
}
