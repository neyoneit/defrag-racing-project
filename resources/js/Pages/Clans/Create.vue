<script setup>
    import { Head } from '@inertiajs/vue3';
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import InputError from '@/Components/Laravel/InputError.vue';

    const form = useForm({
        _method: 'POST',
        name: '',
        image: null
    });

    const imagePreview = ref(null);
    const imageInput = ref(null);

    const submitForm = () => {
        form.post(route('clans.manage.store'), {
            errorBag: 'submitForm',
            preserveScroll: true
        });
    };

    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateimagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
            form.errors.image = 'The image field is required.';
            return;
        };

        if (! image.type.startsWith('image/')) {
            form.errors.image = 'The file must be an Image.';
            imageInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.image = '';

        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };

        reader.readAsDataURL(image);

        form.image = image;
    };
</script>

<template>
    <div>
        <Head title="Create Clan" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-black text-white mb-2">Create Clan</h1>
                    <p class="text-gray-400">Create a new clan and manage it as admin</p>
                </div>
            </div>
        </div>

        <div class="flex justify-center px-4 sm:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="w-full max-w-md">
                <div class="bg-black/40 backdrop-blur-sm rounded-2xl p-8 shadow-2xl border border-white/10">
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-bold text-gray-300 mb-2" v-html="'Clan Name ' + (form.name ? '(' + q3tohtml(form.name) + ')' : '')"></label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                                placeholder="Enter clan name (Q3 color codes supported)"
                            />
                            <p class="text-xs text-gray-500 mt-1">Example: ^1Red^2Green</p>
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <input
                                ref="imageInput"
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="updateimagePreview"
                            >
                            <label class="block text-sm font-bold text-gray-300 mb-2">Clan Avatar</label>
                            <div class="flex items-center gap-4">
                                <div v-if="imagePreview" class="shrink-0">
                                    <img :src="imagePreview" class="rounded-full h-16 w-16 object-cover border-2 border-white/20 shadow-lg">
                                </div>
                                <div v-else class="shrink-0 w-16 h-16 rounded-full bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                    <span class="text-xs text-gray-500">No image</span>
                                </div>
                                <button type="button" @click="selectNewImage" class="px-4 py-2 bg-white/5 border border-white/10 hover:bg-white/10 text-gray-300 text-sm font-semibold rounded-lg transition-all">
                                    Select Image
                                </button>
                            </div>
                            <InputError :message="form.errors.image" class="mt-2" />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold rounded-lg transition-colors"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Clan' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
