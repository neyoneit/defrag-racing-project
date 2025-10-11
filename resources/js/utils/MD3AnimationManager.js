import * as THREE from 'three';

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
            console.warn(`Animation ${animName} not found in legs animations:`, this.animations?.legs ? Object.keys(this.animations.legs) : 'No legs animations');
            return;
        }

        this.currentLegsAnim = this.animations.legs[animName];
        this.legsFrame = 0;
        this.legsTime = 0;
        this.soundTriggered.legs = false;
        this.playing = true;

        console.log(`🦵 Playing legs animation: ${animName}`, {
            firstFrame: this.currentLegsAnim.firstFrame,
            numFrames: this.currentLegsAnim.numFrames,
            fps: this.currentLegsAnim.fps,
            loop: this.currentLegsAnim.loop,
            loopingFrames: this.currentLegsAnim.loopingFrames
        });

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
            console.warn(`Animation ${animName} not found in torso animations:`, this.animations?.torso ? Object.keys(this.animations.torso) : 'No torso animations');
            return;
        }

        this.currentTorsoAnim = this.animations.torso[animName];
        this.torsoFrame = 0;
        this.torsoTime = 0;
        this.soundTriggered.torso = false;
        this.playing = true;

        console.log(`💪 Playing torso animation: ${animName}`, {
            firstFrame: this.currentTorsoAnim.firstFrame,
            numFrames: this.currentTorsoAnim.numFrames,
            fps: this.currentTorsoAnim.fps,
            loop: this.currentTorsoAnim.loop,
            loopingFrames: this.currentTorsoAnim.loopingFrames,
            upperBodyExists: !!this.playerModel.userData.upper
        });

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
        if (!modelPart) {
            console.warn(`${type} model part is null or undefined!`);
            return;
        }

        const isLegs = type === 'legs';
        let frame = 0;

        // Check if this is a static pose (single frame animation like TORSO_STAND)
        if (anim.numFrames === 1) {
            // Static pose - just stay on frame 0
            frame = 0;
        } else {
            // Animated - update time and calculate frame
            const frameTime = 1 / anim.fps;

            // Update time
            if (isLegs) {
                this.legsTime += deltaTime;
            } else {
                this.torsoTime += deltaTime;
            }

            const currentTime = isLegs ? this.legsTime : this.torsoTime;

            // Calculate current frame
            const frameFloat = (currentTime / frameTime);
            frame = Math.floor(frameFloat);

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
        }

        if (isLegs) {
            this.legsFrame = frame;
        } else {
            this.torsoFrame = frame;
        }

        // Calculate the actual MD3 frame index from animation frame
        const md3Frame = anim.firstFrame + frame;

        // Update all meshes in this model part
        modelPart.children.forEach(child => {
            if (child.userData.surface) {
                this.updateMeshFrame(child, anim, frame);
            }
        });

        // Update tags for this frame (tags animate with the model!)
        if (isLegs && modelPart.userData.tags && modelPart.userData.tags[md3Frame]) {
            // Update upper body position to follow tag_torso
            const upper = this.playerModel.userData.upper;
            if (upper) {
                const torsoTag = modelPart.userData.tags[md3Frame].find(tag => tag.name === 'tag_torso');
                if (torsoTag) {
                    // Update upper body position and rotation to match the tag
                    upper.position.copy(torsoTag.origin);

                    // Set rotation from tag axis (3x3 rotation matrix)
                    const matrix = new THREE.Matrix4();
                    matrix.set(
                        torsoTag.axis[0].x, torsoTag.axis[1].x, torsoTag.axis[2].x, 0,
                        torsoTag.axis[0].y, torsoTag.axis[1].y, torsoTag.axis[2].y, 0,
                        torsoTag.axis[0].z, torsoTag.axis[1].z, torsoTag.axis[2].z, 0,
                        0, 0, 0, 1
                    );

                    const rotation = new THREE.Euler();
                    rotation.setFromRotationMatrix(matrix);
                    upper.rotation.copy(rotation);
                }
            }
        }

        // Update head tag if upper body is animating
        if (!isLegs && modelPart.userData.tags && modelPart.userData.tags[md3Frame]) {
            const head = this.playerModel.userData.head;
            if (head) {
                const headTag = modelPart.userData.tags[md3Frame].find(tag => tag.name === 'tag_head');
                if (headTag) {
                    // Update head position and rotation to match the tag
                    head.position.copy(headTag.origin);

                    // Set rotation from tag axis
                    const matrix = new THREE.Matrix4();
                    matrix.set(
                        headTag.axis[0].x, headTag.axis[1].x, headTag.axis[2].x, 0,
                        headTag.axis[0].y, headTag.axis[1].y, headTag.axis[2].y, 0,
                        headTag.axis[0].z, headTag.axis[1].z, headTag.axis[2].z, 0,
                        0, 0, 0, 1
                    );

                    const rotation = new THREE.Euler();
                    rotation.setFromRotationMatrix(matrix);
                    head.rotation.copy(rotation);
                }
            }
        }
    }

    /**
     * Update mesh geometry to a specific frame
     * @param {THREE.Mesh} mesh - The mesh to update
     * @param {Object} anim - Animation definition
     * @param {number} frame - Frame number
     */
    updateMeshFrame(mesh, anim, frame) {
        const surface = mesh.userData.surface;
        if (!surface || !surface.vertices) {
            return;
        }

        // Calculate frame index and clamp to available frames
        let frameIndex = anim.firstFrame + frame;

        // Debug logging (only log first frame)
        if (frame === 0) {
            console.log(`🎬 Frame calculation for ${anim.name}: firstFrame=${anim.firstFrame}, frame=${frame}, frameIndex=${frameIndex}, totalFrames=${surface.vertices.length}`)
        }

        // If firstFrame itself is beyond available frames, remap the animation to available range
        if (anim.firstFrame >= surface.vertices.length) {
            // Animation starts beyond available frames - use beginning of file
            // This handles cases where upper.md3 has fewer frames than animation.cfg expects
            frameIndex = frame % surface.vertices.length;
        } else if (frameIndex >= surface.vertices.length) {
            // Animation started in valid range but current frame is out of bounds
            // Loop within available frames
            const availableFrames = surface.vertices.length - anim.firstFrame;
            if (availableFrames > 0) {
                frameIndex = anim.firstFrame + (frame % availableFrames);
            } else {
                frameIndex = 0;
            }
        }

        // Safety check
        if (frameIndex < 0 || frameIndex >= surface.vertices.length) {
            return;
        }

        const frameVerts = surface.vertices[frameIndex];
        if (!frameVerts || frameVerts.length === 0) {
            return;
        }

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
     * Stop all animations completely (freeze the model)
     */
    stop() {
        this.playing = false;
        this.currentLegsAnim = null;
        this.currentTorsoAnim = null;
    }

    /**
     * Reset to idle state (LEGS_IDLE + TORSO_STAND)
     */
    resetToIdle() {
        this.playLegsAnimation('LEGS_IDLE');
        this.playTorsoAnimation('TORSO_STAND');
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
