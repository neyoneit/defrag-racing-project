<script setup>
    import { ref, watch, nextTick } from 'vue';
    import VueCropper from 'vue-cropperjs';

    const props = defineProps({
        show: Boolean,
        image: String,
        aspectRatio: {
            type: Number,
            default: NaN // NaN = free aspect ratio
        },
        title: {
            type: String,
            default: 'Crop Image'
        }
    });

    const emit = defineEmits(['close', 'crop']);

    const cropper = ref(null);
    const showCropper = ref(false);

    // Watch for modal show/hide to properly initialize cropper
    watch(() => props.show, async (newVal) => {
        if (newVal) {
            // Small delay to ensure DOM is ready
            showCropper.value = false;
            await nextTick();
            setTimeout(() => {
                showCropper.value = true;
            }, 100);
        } else {
            showCropper.value = false;
        }
    });

    const cropImage = () => {
        if (!cropper.value) {
            console.error('Cropper not initialized');
            return;
        }

        cropper.value.getCroppedCanvas({
            maxWidth: 1920,
            maxHeight: 1920,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        }).toBlob((blob) => {
            emit('crop', blob);
        }, 'image/jpeg', 0.92);
    };

    const handleClose = () => {
        emit('close');
    };
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-black/80 backdrop-blur-sm" @click="handleClose"></div>

            <!-- Modal panel -->
            <div class="inline-block w-full max-w-5xl my-8 overflow-hidden text-left align-middle transition-all transform bg-gradient-to-br from-gray-900 to-black border border-white/10 shadow-2xl rounded-2xl relative z-10">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600/20 to-purple-600/20 border-b border-white/10 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            {{ title }}
                        </h3>
                        <button
                            @click="handleClose"
                            class="text-gray-400 hover:text-white transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Cropper -->
                <div class="p-6">
                    <div v-if="!showCropper" class="cropper-wrapper flex items-center justify-center">
                        <div class="text-gray-400">Loading cropper...</div>
                    </div>
                    <div v-else class="cropper-wrapper">
                        <vue-cropper
                            ref="cropper"
                            :src="image"
                            :aspect-ratio="aspectRatio"
                            :view-mode="1"
                            :drag-mode="'move'"
                            :auto-crop-area="0.9"
                            :background="true"
                            :restore="false"
                            :guides="true"
                            :center="true"
                            :highlight="true"
                            :crop-box-movable="true"
                            :crop-box-resizable="true"
                            :toggle-drag-mode-on-dblclick="false"
                            :responsive="true"
                            :checkCrossOrigin="false"
                        />
                    </div>

                    <!-- Instructions -->
                    <div class="mt-4 p-4 bg-blue-600/10 border border-blue-500/20 rounded-lg">
                        <p class="text-sm text-gray-300">
                            <span class="font-semibold text-blue-400">Instructions:</span>
                            Drag the image to move it, use the corners to resize the crop area, and scroll to zoom in/out.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white/5 border-t border-white/10 px-6 py-4">
                    <div class="flex justify-end gap-3">
                        <button
                            @click="handleClose"
                            class="px-6 py-2 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 rounded-lg text-white transition-all duration-300"
                        >
                            Cancel
                        </button>
                        <button
                            @click="cropImage"
                            class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 rounded-lg text-white font-bold transition-all duration-300 shadow-lg hover:shadow-blue-500/50"
                        >
                            Crop & Upload
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Cropper wrapper - contains everything */
.cropper-wrapper {
    width: 100%;
    height: 500px;
    background: #000;
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

/* Override vue-cropper default styles using :deep() */
.cropper-wrapper :deep(img) {
    max-width: 100%;
    max-height: 100%;
}

/* The main cropper container that gets created */
.cropper-wrapper :deep(.cropper-container) {
    position: relative !important;
    height: 500px !important;
    width: 100% !important;
    background: #000 !important;
}

/* Wrap box should be contained */
.cropper-wrapper :deep(.cropper-wrap-box) {
    position: relative !important;
}

/* Canvas should not overflow */
.cropper-wrapper :deep(.cropper-canvas) {
    position: relative !important;
}

/* Drag box and crop box need proper z-index */
.cropper-wrapper :deep(.cropper-drag-box) {
    position: absolute;
    z-index: 1;
}

.cropper-wrapper :deep(.cropper-crop-box) {
    position: absolute;
    z-index: 2;
}

/* Cropper.js custom styling */
:deep(.cropper-view-box) {
    outline: 2px solid rgba(59, 130, 246, 0.8) !important;
    outline-color: rgba(59, 130, 246, 0.8) !important;
}

:deep(.cropper-point) {
    background-color: rgba(59, 130, 246, 1) !important;
    width: 12px !important;
    height: 12px !important;
}

:deep(.cropper-line) {
    background-color: rgba(59, 130, 246, 0.8) !important;
}

:deep(.cropper-face) {
    background-color: transparent !important;
    cursor: move !important;
}

:deep(.cropper-dashed) {
    border-color: rgba(59, 130, 246, 0.3) !important;
}

:deep(.cropper-center) {
    background-color: rgba(59, 130, 246, 0.8) !important;
}

/* Dark background for area outside crop */
:deep(.cropper-modal) {
    background-color: rgba(0, 0, 0, 0.6) !important;
}

/* Make sure all cropper elements stay within bounds */
.cropper-wrapper :deep(.cropper-modal),
.cropper-wrapper :deep(.cropper-bg) {
    position: absolute !important;
}
</style>
