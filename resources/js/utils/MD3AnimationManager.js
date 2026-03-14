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

        // Animation speed multiplier
        this.speed = 1.0;

        // Sound callback for animation events
        this.soundCallback = null;

        // Track which animations have triggered sounds
        this.soundTriggered = {
            legs: false,
            torso: false
        };

        // No-loop flags for one-shot animations (clamp to last frame instead of looping)
        this.noLoopLegs = false;
        this.noLoopTorso = false;
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
        // Check legs category first, then both category (for death animations)
        let anim = null;
        if (this.animations?.legs[animName]) {
            anim = this.animations.legs[animName];
        } else if (this.animations?.both[animName]) {
            anim = this.animations.both[animName];
        } else {
            console.warn(`Animation ${animName} not found in legs or both animations`);
            return;
        }

        // Q3 behavior: PM_ContinueLegsAnim() - DON'T restart if already playing same animation
        if (this.currentLegsAnim && this.currentLegsAnim.name === animName) {
            // Already playing this animation - just keep it going (don't reset)
            return;
        }

        // VALIDATE ANIMATION FITS IN MODEL
        const lower = this.playerModel.userData.lower;
        if (lower && lower.children[0] && lower.children[0].userData.surfaceData) {
            const totalFrames = lower.children[0].userData.surfaceData.vertices.length;
            const lastFrame = anim.firstFrame + anim.numFrames - 1;

            if (anim.firstFrame >= totalFrames) {
                console.error(`❌ ${animName} INVALID! firstFrame=${anim.firstFrame} >= totalFrames=${totalFrames}. animation.cfg is WRONG for this model!`);
                return;  // DON'T PLAY INVALID ANIMATION
            }

            if (lastFrame >= totalFrames) {
                console.warn(`⚠️ ${animName} PARTIALLY OUT OF BOUNDS! lastFrame=${lastFrame} >= totalFrames=${totalFrames}. Will clamp.`);
            }
        }

        this.currentLegsAnim = anim;
        this.currentLegsAnim.name = animName; // Store name for reference
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
        // Check torso category first, then both category (for death animations)
        let anim = null;
        if (this.animations?.torso[animName]) {
            anim = this.animations.torso[animName];
        } else if (this.animations?.both[animName]) {
            anim = this.animations.both[animName];
        } else {
            console.warn(`Animation ${animName} not found in torso or both animations`);
            return;
        }

        this.currentTorsoAnim = anim;
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

            // Update time with speed multiplier
            if (isLegs) {
                this.legsTime += deltaTime * this.speed;
            } else {
                this.torsoTime += deltaTime * this.speed;
            }

            const currentTime = isLegs ? this.legsTime : this.torsoTime;

            // Calculate current frame
            const frameFloat = (currentTime / frameTime);
            frame = Math.floor(frameFloat);

            // Handle looping
            if (frame >= anim.numFrames) {
                // Check if no-loop is enabled (for one-shot animations)
                const noLoop = isLegs ? this.noLoopLegs : this.noLoopTorso;

                if (noLoop) {
                    // Clamp to last frame (don't loop) - for one-shot animations
                    frame = anim.numFrames - 1;

                    // Reset time to match the last frame
                    if (isLegs) {
                        this.legsTime = frame * frameTime;
                    } else {
                        this.torsoTime = frame * frameTime;
                    }
                } else {
                    // Normal looping behavior for continuous animations
                    if (anim.loopingFrames > 0) {
                        // Q3-style partial loop: loop back to (numFrames - loopingFrames) position
                        // Example: numFrames=11, loopingFrames=10 -> loops to frame 1
                        frame = (frame % anim.loopingFrames) + (anim.numFrames - anim.loopingFrames);
                    } else {
                        // No loopingFrames defined - loop entire animation from start
                        // This makes LEGS_JUMP, LEGS_LAND, etc. loop endlessly for viewing
                        frame = frame % anim.numFrames;
                    }

                    // Reset time to match the new frame
                    if (isLegs) {
                        this.legsTime = frame * frameTime;
                    } else {
                        this.torsoTime = frame * frameTime;
                    }
                }
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
            if (child.userData.surfaceData) {
                this.updateMeshFrame(child, anim, frame);
            }
        });

        // Update tags for this frame (tags animate with the model!)
        if (isLegs && modelPart.userData.tags) {
            // If md3Frame exceeds available tags, clamp to last available frame
            // This prevents wrapping to beginning of model when animation extends beyond tag data
            const totalTagFrames = modelPart.userData.tags.length;
            const safeTagFrame = Math.min(md3Frame, totalTagFrames - 1);

            if (modelPart.userData.tags[safeTagFrame]) {
                // Update upper body position to follow tag_torso
                const upper = this.playerModel.userData.upper;
                if (upper) {
                    const torsoTag = modelPart.userData.tags[safeTagFrame].find(tag => tag.name === 'tag_torso');
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
        }

        // Update head tag if upper body is animating
        if (!isLegs && modelPart.userData.tags) {
            // If md3Frame exceeds available tags, clamp to last available frame
            const totalTagFrames = modelPart.userData.tags.length;
            const safeTagFrame = Math.min(md3Frame, totalTagFrames - 1);

            if (modelPart.userData.tags[safeTagFrame]) {
                const head = this.playerModel.userData.head;
                if (head) {
                    const headTag = modelPart.userData.tags[safeTagFrame].find(tag => tag.name === 'tag_head');
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
    }

    /**
     * Update mesh geometry to a specific frame
     * @param {THREE.Mesh} mesh - The mesh to update
     * @param {Object} anim - Animation definition
     * @param {number} frame - Frame number
     */
    updateMeshFrame(mesh, anim, frame) {
        const surface = mesh.userData.surfaceData;
        if (!surface || !surface.vertices) {
            return;
        }

        // Calculate frame index and clamp to available frames
        let frameIndex = anim.firstFrame + frame;

        // Debug logging (disabled - animation is working correctly)
        // console.log(`🎬 Frame calculation for ${anim.name}: firstFrame=${anim.firstFrame}, frame=${frame}, frameIndex=${frameIndex}, totalFrames=${surface.vertices.length}`)

        // If firstFrame itself is beyond available frames, remap the animation to available range
        if (anim.firstFrame >= surface.vertices.length) {
            // Animation starts beyond available frames - use beginning of file
            // This handles cases where upper.md3 has fewer frames than animation.cfg expects
            frameIndex = frame % surface.vertices.length;
        } else if (frameIndex >= surface.vertices.length) {
            // Animation frame exceeds available vertex data
            // Clamp to last available frame to hold the final pose
            frameIndex = surface.vertices.length - 1;
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
     * Set animation speed multiplier
     * @param {number} speed - Speed multiplier (1.0 = normal, 2.0 = 2x speed, etc.)
     */
    setSpeed(speed) {
        this.speed = Math.max(0.1, Math.min(3.0, speed)); // Clamp between 0.1x and 3.0x
    }

    /**
     * Set manual frame for debugging
     * @param {number} frame - Frame number to display
     */
    setManualFrame(frame) {
        // Stop automatic animation
        this.playing = false;

        // Update legs to specific frame
        if (this.currentLegsAnim && this.playerModel.userData.lower) {
            const clampedFrame = Math.min(frame, this.currentLegsAnim.numFrames - 1);
            const md3Frame = this.currentLegsAnim.firstFrame + clampedFrame;
            const lower = this.playerModel.userData.lower;

            // Update leg meshes
            lower.children.forEach(child => {
                if (child.userData.surfaceData) {
                    this.updateMeshFrame(child, this.currentLegsAnim, clampedFrame);
                }
            });

            // Update torso tag attachment
            if (lower.userData.tags) {
                const totalTagFrames = lower.userData.tags.length;
                let safeTagFrame;

                // If md3Frame exceeds available tags, use the last valid frame
                if (md3Frame >= totalTagFrames) {
                    safeTagFrame = totalTagFrames - 1;
                } else {
                    safeTagFrame = md3Frame;
                }

                if (lower.userData.tags[safeTagFrame]) {
                    const upper = this.playerModel.userData.upper;
                    if (upper) {
                        const torsoTag = lower.userData.tags[safeTagFrame].find(tag => tag.name === 'tag_torso');
                        if (torsoTag) {
                            upper.position.copy(torsoTag.origin);

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
            }
        }

        // Update torso to specific frame
        if (this.currentTorsoAnim && this.playerModel.userData.upper) {
            const clampedFrame = Math.min(frame, this.currentTorsoAnim.numFrames - 1);
            const upper = this.playerModel.userData.upper;

            upper.children.forEach(child => {
                if (child.userData.surfaceData) {
                    this.updateMeshFrame(child, this.currentTorsoAnim, clampedFrame);
                }
            });
        }
    }

    /**
     * Get available animations
     */
    getAvailableAnimations() {
        if (!this.animations) return { legs: {}, torso: {}, both: {} };

        return {
            legs: this.animations.legs,
            torso: this.animations.torso,
            both: this.animations.both || {}
        };
    }
}
