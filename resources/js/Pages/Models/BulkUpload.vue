<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    bulkResults: {
        type: Object,
        default: null
    }
});

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content);

const fileInput = ref(null);
const selectedFiles = ref([]);
const category = ref('player');
const uploading = ref(false);

const categories = [
    { value: 'player', label: 'Player Models', icon: '🏃' },
    { value: 'weapon', label: 'Weapon Models', icon: '🔫' },
    { value: 'shadow', label: 'Shadow Models', icon: '👤' },
];

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    selectedFiles.value = files;
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
    // Update file input
    const dt = new DataTransfer();
    selectedFiles.value.forEach(file => dt.items.add(file));
    if (fileInput.value) {
        fileInput.value.files = dt.files;
    }
};

const clearFiles = () => {
    selectedFiles.value = [];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleSubmit = (e) => {
    console.log('Form submitting...');
    console.log('Action:', e.target.action);
    console.log('CSRF Token:', csrfToken.value);
    console.log('Selected files:', selectedFiles.value.length);
    console.log('Category:', category.value);

    // Let the form submit naturally without Inertia interception
    uploading.value = true;
};
</script>

<template>
    <Head title="Bulk Upload Models" />
    <div class="min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <Link :href="route('models.index')" class="text-blue-400 hover:text-blue-300 mb-4 inline-block">
                    ← Back to Models
                </Link>
                <h1 class="text-4xl font-black text-white mb-2">Bulk Upload Models</h1>
                <p class="text-gray-400">Upload multiple PK3 files at once. Metadata will be extracted automatically.</p>
            </div>

            <!-- Results Section -->
            <div v-if="bulkResults" class="mb-8 space-y-4">
                <!-- Success Results -->
                <div v-if="bulkResults.success && bulkResults.success.length > 0" class="bg-green-500/10 border border-green-500/30 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-green-400 mb-4">✓ Successfully Uploaded ({{ bulkResults.success.length }})</h3>
                    <div class="space-y-2">
                        <div v-for="result in bulkResults.success" :key="result.id" class="flex items-center justify-between bg-black/20 rounded-lg p-3">
                            <div class="flex-1">
                                <p class="text-white font-semibold">{{ result.model }}</p>
                                <p class="text-xs text-gray-400">{{ result.file }}</p>
                            </div>
                            <Link :href="route('models.show', result.id)" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-sm">
                                View
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Failed Results -->
                <div v-if="bulkResults.failed && bulkResults.failed.length > 0" class="bg-red-500/10 border border-red-500/30 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-red-400 mb-4">✗ Failed ({{ bulkResults.failed.length }})</h3>
                    <div class="space-y-2">
                        <div v-for="(result, index) in bulkResults.failed" :key="index" class="bg-black/20 rounded-lg p-3">
                            <p class="text-white font-semibold">{{ result.file }}</p>
                            <p class="text-xs text-red-400">{{ result.error }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <form :action="route('models.bulk-upload.store')" method="POST" enctype="multipart/form-data" @submit="handleSubmit" class="space-y-6">
                <input type="hidden" name="_token" :value="csrfToken" />

                <!-- Category Selection -->
                <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-6">
                    <label class="block text-sm font-bold text-white mb-3">Category</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            v-for="cat in categories"
                            :key="cat.value"
                            type="button"
                            @click="category = cat.value"
                            :class="[
                                'p-4 rounded-xl font-semibold transition-all border-2',
                                category === cat.value
                                    ? 'bg-blue-500 text-white border-blue-400 shadow-lg shadow-blue-500/30'
                                    : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10 hover:text-white'
                            ]">
                            <span class="flex flex-col items-center gap-2">
                                <span class="text-3xl">{{ cat.icon }}</span>
                                <span class="text-sm">{{ cat.label }}</span>
                            </span>
                        </button>
                    </div>
                    <input type="hidden" name="category" :value="category" />
                </div>

                <!-- File Upload -->
                <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 to-white/5 rounded-2xl border border-white/10 p-6">
                    <label class="block text-sm font-bold text-white mb-3">
                        PK3 Files
                        <span class="text-gray-400 font-normal">(select multiple)</span>
                    </label>

                    <div class="border-2 border-dashed border-white/20 rounded-xl p-8 text-center hover:border-blue-500/50 transition-all cursor-pointer"
                         @click="() => fileInput?.click()">
                        <input
                            ref="fileInput"
                            type="file"
                            name="model_files[]"
                            multiple
                            accept=".pk3,.zip"
                            @change="handleFileSelect"
                            class="hidden" />

                        <div class="text-6xl mb-4">📦</div>
                        <p class="text-white font-bold mb-2">Click to select PK3 files</p>
                        <p class="text-sm text-gray-400">or drag and drop multiple files here</p>
                        <p class="text-xs text-gray-500 mt-2">Accepts .pk3 or .zip files (max 50MB each)</p>
                    </div>

                    <!-- Selected Files List -->
                    <div v-if="selectedFiles.length > 0" class="mt-4 space-y-2">
                        <div class="flex items-center justify-between text-sm text-gray-400 mb-2">
                            <span>{{ selectedFiles.length }} file(s) selected</span>
                            <button type="button" @click="clearFiles" class="text-red-400 hover:text-red-300">
                                Clear all
                            </button>
                        </div>
                        <div v-for="(file, index) in selectedFiles" :key="index" class="flex items-center justify-between bg-black/30 rounded-lg p-3">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="text-2xl">📄</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-white font-semibold truncate">{{ file.name }}</p>
                                    <p class="text-xs text-gray-400">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="removeFile(index)"
                                class="text-red-400 hover:text-red-300 ml-3">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="uploading || selectedFiles.length === 0"
                        class="flex-1 px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-blue-500/50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="uploading" class="flex items-center justify-center gap-2">
                            <div class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></div>
                            Uploading {{ selectedFiles.length }} file(s)...
                        </span>
                        <span v-else>Upload {{ selectedFiles.length }} Model(s)</span>
                    </button>

                    <Link :href="route('models.index')"
                          class="px-6 py-4 bg-white/5 text-white font-bold rounded-xl hover:bg-white/10 transition-all">
                        Cancel
                    </Link>
                </div>
            </form>

            <!-- Info Box -->
            <div class="mt-8 bg-blue-500/10 border border-blue-500/30 rounded-xl p-6">
                <h3 class="text-lg font-bold text-blue-400 mb-3">ℹ️ Bulk Upload Info</h3>
                <ul class="space-y-2 text-sm text-gray-300">
                    <li>• <strong>Automatic metadata extraction</strong> - Model name, author, skins, sounds will be detected automatically</li>
                    <li>• <strong>Original filename used</strong> - The PK3 filename will be used as the model display name</li>
                    <li>• <strong>Auto-approved</strong> - Admin bulk uploads are automatically approved and visible</li>
                    <li>• <strong>Supports both types</strong> - Complete models (with MD3) and skin-only packs are detected automatically</li>
                    <li>• <strong>Base model detection</strong> - System automatically determines which Q3 model each upload is based on</li>
                </ul>
            </div>
        </div>
    </div>
</template>
