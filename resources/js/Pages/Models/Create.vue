<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    description: '',
    category: 'player',
    model_file: null,
});

const fileInput = ref(null);
const selectedFileName = ref('');

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.model_file = file;
        selectedFileName.value = file.name;
    }
};

const submitForm = () => {
    form.post(route('models.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedFileName.value = '';
        },
    });
};
</script>

<template>
    <Head title="Upload Model" />
    <div class="min-h-screen">
        <!-- Header Section with Drop Shadow -->
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
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 py-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-xl text-green-400">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="$page.props.flash?.error" class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-red-400">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>{{ $page.props.flash.error }}</span>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="backdrop-blur-xl bg-black/40 rounded-xl border border-white/5 p-8 shadow-2xl">
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Model Name -->
                        <div>
                            <label for="name" class="block text-sm font-bold text-white mb-2">
                                Model Name <span class="text-red-400">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="e.g., Custom Player Model">
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                        </div>

                        <!-- Category -->
                        <div>
                            <label for="category" class="block text-sm font-bold text-white mb-2">
                                Category <span class="text-red-400">*</span>
                            </label>
                            <select
                                id="category"
                                v-model="form.category"
                                required
                                class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all [&>option]:bg-gray-900 [&>option]:text-white">
                                <option value="player">🏃 Player Model</option>
                                <option value="weapon">🔫 Weapon Model</option>
                            </select>
                            <p v-if="form.errors.category" class="mt-2 text-sm text-red-400">{{ form.errors.category }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-bold text-white mb-2">
                                Description
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                placeholder="Describe your model, its features, and any special requirements..."></textarea>
                            <p v-if="form.errors.description" class="mt-2 text-sm text-red-400">{{ form.errors.description }}</p>
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
                            <p v-if="form.errors.model_file" class="mt-2 text-sm text-red-400">{{ form.errors.model_file }}</p>
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

                        <!-- Submit Buttons -->
                        <div class="flex gap-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-1 px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-black rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-blue-500/50 text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="form.processing" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Uploading...
                                </span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3V15" />
                                    </svg>
                                    Upload Model
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
            </div>
    </div>
</template>
