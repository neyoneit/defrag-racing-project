<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, nextTick, onBeforeUnmount } from 'vue';
import ModelViewer from '@/Components/ModelViewer.vue';
import { generateAllGifs, waitForTextures } from '@/utils/gifGenerator.js';

// Step management
const step = ref(1); // 1 = upload form, 2 = GIF generation, 3 = done

// Step 1: Form data
const formName = ref('');
const formDescription = ref('');
const formCategory = ref('player');
const formIsNsfw = ref(false);
const formFile = ref(null);
const fileInput = ref(null);
const selectedFileName = ref('');
const uploading = ref(false);
const uploadError = ref('');

// Step 2: GIF generation
const tempSlug = ref('');
const detectedModels = ref([]);
const currentModelIndex = ref(-1);
const viewer3D = ref(null);
const viewerLoaded = ref(false);
const viewerError = ref(null);
const gifStatus = ref('');
const gifProgress = ref('');
const generatedGifs = ref({}); // { [modelIndex]: { rotateBlob, idleBlob, ... } }
const allGifsReady = ref(false);
const saving = ref(false);

// Step 3: Result
const successMessage = ref('');
const redirectUrl = ref('');

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        formFile.value = file;
        selectedFileName.value = file.name;
    }
};

// Step 1: Upload file for extraction
async function submitUpload() {
    if (!formFile.value || !formName.value) return;
    uploading.value = true;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('name', formName.value);
    formData.append('description', formDescription.value || '');
    formData.append('category', formCategory.value);
    formData.append('is_nsfw', formIsNsfw.value ? '1' : '0');
    formData.append('model_file', formFile.value);

    try {
        const response = await fetch(route('models.tempUpload'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();

        if (!data.success) {
            uploadError.value = data.message || 'Upload failed.';
            uploading.value = false;
            return;
        }

        tempSlug.value = data.slug;
        detectedModels.value = data.models;
        step.value = 2;
        uploading.value = false;

        // Start GIF generation automatically
        await nextTick();
        startGifGeneration();
    } catch (e) {
        uploadError.value = 'Network error: ' + e.message;
        uploading.value = false;
    }
}

// Step 2: Generate GIFs for each detected model
async function startGifGeneration() {
    for (let i = 0; i < detectedModels.value.length; i++) {
        currentModelIndex.value = i;
        const modelInfo = detectedModels.value[i];
        gifProgress.value = `Model ${i + 1}/${detectedModels.value.length}: ${modelInfo.display_name}`;

        if (!modelInfo.viewer_path) {
            gifStatus.value = 'No viewer path - skipping GIF generation';
            generatedGifs.value[i] = { rotateBlob: null, idleBlob: null, gestureBlob: null, headIconBlob: null, thumbnailBlob: null };
            continue;
        }

        // Load model in viewer
        viewerLoaded.value = false;
        viewerError.value = null;
        gifStatus.value = 'Loading model in 3D viewer...';

        await nextTick();

        try {
            await waitForViewerLoaded();
            await waitForTextures(viewer3D);

            gifStatus.value = 'Generating GIFs...';
            const gifs = await generateAllGifs(viewer3D, modelInfo, (status) => {
                gifStatus.value = status;
            });

            generatedGifs.value[i] = gifs;

            // Build status
            const parts = [];
            if (gifs.rotateBlob) parts.push('rotate');
            if (gifs.idleBlob) parts.push('idle');
            if (gifs.gestureBlob) parts.push('gesture');
            if (gifs.headIconBlob) parts.push('head');
            if (gifs.thumbnailBlob) parts.push('still');
            gifStatus.value = `Done: ${parts.join(', ')}`;
        } catch (e) {
            console.error('GIF generation error:', e);
            gifStatus.value = 'Error: ' + e.message;
            generatedGifs.value[i] = { rotateBlob: null, idleBlob: null, gestureBlob: null, headIconBlob: null, thumbnailBlob: null };
        }

        // Small delay between models
        if (i < detectedModels.value.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    currentModelIndex.value = -1;
    allGifsReady.value = true;
    gifProgress.value = 'All GIFs generated! Ready to save.';
    gifStatus.value = '';
}

function waitForViewerLoaded() {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            reject(new Error('Model load timed out (20s)'));
        }, 20000);

        const interval = setInterval(() => {
            if (viewerError.value) {
                clearInterval(interval);
                clearTimeout(timeout);
                reject(new Error('Model load failed: ' + viewerError.value));
                return;
            }
            if (viewerLoaded.value) {
                clearInterval(interval);
                clearTimeout(timeout);
                resolve();
            }
        }, 100);
    });
}

// Helper for template - URL.createObjectURL not available directly in Vue templates
const createObjectURL = (blob) => window.URL.createObjectURL(blob);

const onViewerLoaded = () => { viewerLoaded.value = true; };
const onViewerError = (err) => {
    viewerError.value = err?.message || String(err);
    viewerLoaded.value = false;
};

// Step 2 → 3: Save model with GIFs
async function saveModel() {
    saving.value = true;
    gifStatus.value = 'Saving model...';

    const formData = new FormData();
    formData.append('slug', tempSlug.value);

    // Append GIF files for each model
    for (let i = 0; i < detectedModels.value.length; i++) {
        const gifs = generatedGifs.value[i];
        if (!gifs) continue;

        formData.append(`gifs[${i}][model_index]`, i);
        if (gifs.rotateBlob) formData.append(`gifs[${i}][rotate_gif]`, gifs.rotateBlob, `rotate_${i}.gif`);
        if (gifs.idleBlob) formData.append(`gifs[${i}][idle_gif]`, gifs.idleBlob, `idle_${i}.gif`);
        if (gifs.gestureBlob) formData.append(`gifs[${i}][gesture_gif]`, gifs.gestureBlob, `gesture_${i}.gif`);
        if (gifs.headIconBlob) formData.append(`gifs[${i}][head_icon]`, gifs.headIconBlob, `head_${i}.png`);
        if (gifs.thumbnailBlob) formData.append(`gifs[${i}][thumbnail]`, gifs.thumbnailBlob, `still_${i}.png`);
    }

    try {
        const response = await fetch(route('models.storeWithGifs'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();

        if (!data.success) {
            gifStatus.value = 'Error: ' + (data.message || 'Save failed');
            saving.value = false;
            return;
        }

        successMessage.value = data.message;
        redirectUrl.value = data.redirect;
        step.value = 3;
        saving.value = false;
    } catch (e) {
        gifStatus.value = 'Network error: ' + e.message;
        saving.value = false;
    }
}

// Cancel / cleanup
async function cancelUpload() {
    if (tempSlug.value) {
        try {
            await fetch(route('models.deleteTempUpload'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ slug: tempSlug.value }),
            });
        } catch (e) { /* ignore cleanup errors */ }
    }
    // Reset to step 1
    step.value = 1;
    tempSlug.value = '';
    detectedModels.value = [];
    generatedGifs.value = {};
    allGifsReady.value = false;
    currentModelIndex.value = -1;
    gifStatus.value = '';
    gifProgress.value = '';
}

// Cleanup temp files on page leave
onBeforeUnmount(() => {
    if (tempSlug.value && step.value !== 3) {
        // Fire-and-forget cleanup
        fetch(route('models.deleteTempUpload'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ slug: tempSlug.value }),
        }).catch(() => {});
    }
});

// Get current model info for viewer
function currentViewerModel() {
    if (currentModelIndex.value < 0 || currentModelIndex.value >= detectedModels.value.length) return null;
    return detectedModels.value[currentModelIndex.value];
}
</script>

<template>
    <Head title="Upload Model" />
    <div class="min-h-screen">
        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <Link :href="route('models.index')" class="inline-flex items-center gap-2 text-gray-400 hover:text-white mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Models
                </Link>

                <h1 class="text-4xl font-black text-white mb-2">Upload Model</h1>
                <p class="text-gray-400">Share your custom Quake 3 models with the community</p>

                <!-- Step indicator -->
                <div class="flex items-center gap-4 mt-6">
                    <div class="flex items-center gap-2">
                        <div :class="[step >= 1 ? 'bg-blue-500' : 'bg-white/10', 'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white']">1</div>
                        <span class="text-sm" :class="step >= 1 ? 'text-white' : 'text-gray-500'">Upload</span>
                    </div>
                    <div class="w-8 h-px" :class="step >= 2 ? 'bg-blue-500' : 'bg-white/10'"></div>
                    <div class="flex items-center gap-2">
                        <div :class="[step >= 2 ? 'bg-blue-500' : 'bg-white/10', 'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white']">2</div>
                        <span class="text-sm" :class="step >= 2 ? 'text-white' : 'text-gray-500'">Preview & GIFs</span>
                    </div>
                    <div class="w-8 h-px" :class="step >= 3 ? 'bg-blue-500' : 'bg-white/10'"></div>
                    <div class="flex items-center gap-2">
                        <div :class="[step >= 3 ? 'bg-green-500' : 'bg-white/10', 'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white']">3</div>
                        <span class="text-sm" :class="step >= 3 ? 'text-green-400' : 'text-gray-500'">Done</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 py-6">

            <!-- ==================== STEP 1: Upload Form ==================== -->
            <div v-if="step === 1" class="backdrop-blur-xl bg-black/40 rounded-xl border border-white/5 p-8 shadow-2xl">
                <!-- Error -->
                <div v-if="uploadError" class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-red-400">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <span>{{ uploadError }}</span>
                    </div>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-6">
                    <!-- Model Name -->
                    <div>
                        <label for="name" class="block text-sm font-bold text-white mb-2">
                            Model Name <span class="text-red-400">*</span>
                        </label>
                        <input
                            id="name"
                            v-model="formName"
                            type="text"
                            required
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            placeholder="e.g., Custom Player Model">
                    </div>

                    <!-- Category -->
                    <div>
                        <label for="category" class="block text-sm font-bold text-white mb-2">
                            Category <span class="text-red-400">*</span>
                        </label>
                        <select
                            id="category"
                            v-model="formCategory"
                            required
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all [&>option]:bg-gray-900 [&>option]:text-white">
                            <option value="player">Player Model</option>
                            <option value="weapon">Weapon Model</option>
                        </select>
                    </div>

                    <!-- NSFW -->
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input
                                type="checkbox"
                                v-model="formIsNsfw"
                                class="w-5 h-5 rounded border-white/20 bg-white/5 text-red-500 focus:ring-red-500 focus:ring-offset-0 cursor-pointer">
                            <span class="text-sm font-bold text-white group-hover:text-red-400 transition-colors">Mark as NSFW (18+)</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500 ml-8">Content will be blurred and require age confirmation to view</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-bold text-white mb-2">
                            Description
                        </label>
                        <textarea
                            id="description"
                            v-model="formDescription"
                            rows="4"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                            placeholder="Describe your model, its features, and any special requirements..."></textarea>
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label class="block text-sm font-bold text-white mb-2">
                            Model File (ZIP or PK3) <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".zip,.pk3"
                                required
                                @change="handleFileChange"
                                class="hidden">
                            <button
                                type="button"
                                @click="fileInput.click()"
                                class="w-full px-6 py-4 bg-white/5 border-2 border-dashed border-white/20 rounded-xl hover:border-blue-500/50 hover:bg-white/10 transition-all text-left">
                                <div class="flex items-center justify-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3V15" />
                                    </svg>
                                    <div>
                                        <p class="text-white font-semibold">
                                            {{ selectedFileName || 'Click to select ZIP or PK3 file' }}
                                        </p>
                                        <p class="text-gray-400 text-sm">Maximum file size: 50MB</p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4">
                        <div class="flex gap-3">
                            <div class="shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </div>
                            <div class="text-sm text-gray-300">
                                <p class="font-semibold text-white mb-2">Upload Requirements:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>ZIP or PK3 file containing model files (MD3 format)</li>
                                    <li>Include textures (TGA/JPG format)</li>
                                    <li>Optional: README.txt with author info and model details</li>
                                    <li>Optional: Custom sounds (WAV format)</li>
                                    <li>Optional: CTF team skins (red/blue variants)</li>
                                    <li>Models will be reviewed before being publicly visible</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex gap-4">
                        <button
                            type="submit"
                            :disabled="uploading || !formFile || !formName"
                            class="flex-1 px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-black rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-blue-500/50 text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="uploading" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading & Extracting...
                            </span>
                            <span v-else class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3V15" />
                                </svg>
                                Upload & Continue
                            </span>
                        </button>

                        <Link
                            :href="route('models.index')"
                            class="px-6 py-4 bg-white/5 border border-white/10 text-white font-bold rounded-xl hover:bg-white/10 transition-all text-center flex items-center justify-center">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>

            <!-- ==================== STEP 2: Preview & GIF Generation ==================== -->
            <div v-if="step === 2" class="space-y-6">
                <!-- Progress info -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl border border-white/5 p-6 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-white">Preview & Generate Thumbnails</h2>
                            <p class="text-gray-400 text-sm mt-1">
                                Found {{ detectedModels.length }} model{{ detectedModels.length > 1 ? ' variations' : '' }}.
                                Generating preview GIFs automatically...
                            </p>
                        </div>
                        <div v-if="!allGifsReady" class="flex items-center gap-2 text-blue-400">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium">Processing...</span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="!allGifsReady" class="space-y-2">
                        <div class="w-full bg-white/5 rounded-full h-2">
                            <div
                                class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                                :style="{ width: `${Math.max(5, ((currentModelIndex + 1) / detectedModels.length) * 100)}%` }">
                            </div>
                        </div>
                        <p v-if="gifProgress" class="text-sm text-gray-400">{{ gifProgress }}</p>
                        <p v-if="gifStatus" class="text-xs text-gray-500 font-mono">{{ gifStatus }}</p>
                    </div>

                    <!-- Done message -->
                    <div v-if="allGifsReady" class="p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
                        <p class="text-green-400 font-medium">All thumbnails generated! Click "Save Model" to complete the upload.</p>
                    </div>
                </div>

                <!-- Model list with generated previews -->
                <div class="grid gap-4" :class="detectedModels.length > 1 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1'">
                    <div
                        v-for="(model, idx) in detectedModels"
                        :key="idx"
                        class="backdrop-blur-xl bg-black/40 rounded-xl border p-4 shadow-2xl"
                        :class="currentModelIndex === idx ? 'border-blue-500/50' : generatedGifs[idx]?.rotateBlob ? 'border-green-500/20' : 'border-white/5'">

                        <div class="flex items-center gap-3 mb-3">
                            <!-- Status icon -->
                            <div v-if="currentModelIndex === idx" class="w-6 h-6 flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <div v-else-if="generatedGifs[idx]?.rotateBlob" class="w-6 h-6 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div v-else class="w-6 h-6 flex items-center justify-center">
                                <div class="w-3 h-3 rounded-full bg-white/20"></div>
                            </div>

                            <div>
                                <h3 class="text-white font-bold text-sm">{{ model.display_name }}</h3>
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="model.category === 'weapon' ? 'bg-orange-500/20 text-orange-400' : 'bg-blue-500/20 text-blue-400'">
                                    {{ model.category }}
                                </span>
                            </div>
                        </div>

                        <!-- GIF preview thumbnails -->
                        <div v-if="generatedGifs[idx]" class="grid grid-cols-3 gap-2">
                            <div v-if="generatedGifs[idx].rotateBlob" class="aspect-square rounded-lg overflow-hidden bg-black border border-white/10">
                                <img :src="createObjectURL(generatedGifs[idx].rotateBlob)" class="w-full h-full object-cover" alt="Rotate">
                            </div>
                            <div v-if="generatedGifs[idx].idleBlob" class="aspect-square rounded-lg overflow-hidden bg-black border border-white/10">
                                <img :src="createObjectURL(generatedGifs[idx].idleBlob)" class="w-full h-full object-cover" alt="Idle">
                            </div>
                            <div v-if="generatedGifs[idx].gestureBlob" class="aspect-square rounded-lg overflow-hidden bg-black border border-white/10">
                                <img :src="createObjectURL(generatedGifs[idx].gestureBlob)" class="w-full h-full object-cover" alt="Gesture">
                            </div>
                            <div v-if="generatedGifs[idx].thumbnailBlob" class="aspect-square rounded-lg overflow-hidden bg-black border border-white/10">
                                <img :src="createObjectURL(generatedGifs[idx].thumbnailBlob)" class="w-full h-full object-cover" alt="Still">
                            </div>
                            <div v-if="generatedGifs[idx].headIconBlob" class="aspect-square rounded-lg overflow-hidden bg-black border border-white/10 flex items-center justify-center">
                                <img :src="createObjectURL(generatedGifs[idx].headIconBlob)" class="w-16 h-16" alt="Head">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3D Viewer (square aspect ratio to match GIF output) -->
                <div v-if="currentModelIndex >= 0 && currentViewerModel()?.viewer_path" class="backdrop-blur-xl bg-black/40 rounded-xl border border-white/5 overflow-hidden shadow-2xl mx-auto" style="width: 800px; max-width: 100%;">
                    <div class="relative" style="width: 800px; height: 800px; max-width: 100%;">
                        <ModelViewer
                            ref="viewer3D"
                            :model-path="currentViewerModel().viewer_path"
                            :skin-path="currentViewerModel().skin_path || null"
                            :is-weapon="currentViewerModel().category === 'weapon'"
                            :skin-name="currentViewerModel().skin_name || currentViewerModel().available_skins?.[0] || 'default'"
                            :load-full-player="currentViewerModel().category !== 'weapon'"
                            :auto-rotate="false"
                            :show-grid="true"
                            :enable-sounds="false"
                            background-color="black"
                            :thumbnail-mode="true"
                            @loaded="onViewerLoaded"
                            @error="onViewerError"
                        />
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex gap-4">
                    <button
                        @click="saveModel"
                        :disabled="!allGifsReady || saving"
                        class="flex-1 px-6 py-4 bg-gradient-to-r from-green-500 to-green-600 text-white font-black rounded-xl hover:from-green-600 hover:to-green-700 transition-all shadow-lg hover:shadow-green-500/50 text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="saving" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Save Model
                        </span>
                    </button>

                    <button
                        @click="cancelUpload"
                        :disabled="saving"
                        class="px-6 py-4 bg-white/5 border border-white/10 text-white font-bold rounded-xl hover:bg-white/10 transition-all disabled:opacity-50">
                        Cancel
                    </button>
                </div>
            </div>

            <!-- ==================== STEP 3: Done ==================== -->
            <div v-if="step === 3" class="backdrop-blur-xl bg-black/40 rounded-xl border border-green-500/20 p-8 shadow-2xl text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-16 h-16 text-green-400 mx-auto mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-2xl font-black text-white mb-2">Upload Complete!</h2>
                <p class="text-gray-400 mb-6">{{ successMessage }}</p>
                <div class="flex gap-4 justify-center">
                    <a
                        :href="redirectUrl"
                        class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg">
                        View Model
                    </a>
                    <Link
                        :href="route('models.create')"
                        class="px-6 py-3 bg-white/5 border border-white/10 text-white font-bold rounded-xl hover:bg-white/10 transition-all">
                        Upload Another
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
