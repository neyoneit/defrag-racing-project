<script setup>
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import Modal from '@/Components/Laravel/Modal.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import { ref } from 'vue';

    const props = defineProps({
        clan: Object,
        close: Function,
        show: Boolean
    });


    const imagePreview = ref(null);
    const imageInput = ref(null);
    const backgroundPreview = ref(null);
    const backgroundInput = ref(null);

    const form = useForm({
        _method: 'POST',
        name: props.clan?.name || '',
        image: null,
        background: null,
        name_effect: props.clan?.name_effect || 'particles',
        name_shadow_color: props.clan?.name_shadow_color || '#3b82f6',
        featured_stats: props.clan?.featured_stats || []
    });

    const submitForm = () => {
        form.post(route('clans.manage.update', props.clan.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                props.close();
            }
        });
    };


    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateimagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
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

    const selectNewBackground = () => {
        backgroundInput.value.click();
    };

    const updateBackgroundPreview = () => {
        const background = backgroundInput.value.files[0];

        if (! background) {
            return;
        };

        if (! background.type.startsWith('image/')) {
            form.errors.background = 'The file must be an Image.';
            backgroundInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.background = '';

        reader.onload = (e) => {
            backgroundPreview.value = e.target.result;
        };

        reader.readAsDataURL(background);

        form.background = background;
    };
</script>

<template>
    <div>
        <Modal :show="show" maxWidth="xl" :closeable="false">
            <div class="p-5">
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        Invite Player
                    </h2>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div class="w-full bg-gray-700 my-2" style="height: 1px;"></div>

                <form @submit.prevent="submitForm">
                    <div class="my-3">
                        <InputLabel for="name" v-html="'Clan Name: ' + q3tohtml(form.name)" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            You can use Quake3 color codes, such as: ^1Red^2Green.
                        </div>
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="mb-3">
                        <input
                            id="image"
                            ref="imageInput"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="updateimagePreview"
                        >

                        <InputLabel for="image" value="Clan Avatar" />

                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewImage">
                            Select an Image
                        </SecondaryButton>

                        <div v-if="imagePreview" class="mt-2">
                            <img :src="imagePreview" class="mt-2 rounded-full h-16 w-16">
                        </div>

                        <div v-else class="mt-2">
                            <img :src="'/storage/' + clan.image" class="mt-2 rounded-full h-16 w-16">
                        </div>

                        <InputError :message="form.errors.image" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <input
                            id="background"
                            ref="backgroundInput"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="updateBackgroundPreview"
                        >

                        <InputLabel for="background" value="Clan Background Image" />

                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-2">
                            Recommended size: 1920x400px (wide banner format)
                        </div>

                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewBackground">
                            Select Background Image
                        </SecondaryButton>

                        <div v-if="backgroundPreview" class="mt-2">
                            <img :src="backgroundPreview" class="mt-2 rounded-lg w-full h-32 object-cover">
                        </div>

                        <div v-else-if="clan.background" class="mt-2">
                            <img :src="'/storage/' + clan.background" class="mt-2 rounded-lg w-full h-32 object-cover">
                        </div>

                        <InputError :message="form.errors.background" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="name_effect" value="Clan Name Effect" />

                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-2">
                            Choose an animated effect to highlight your clan name
                        </div>

                        <select
                            id="name_effect"
                            v-model="form.name_effect"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="particles">Particles (Blue dots)</option>
                            <option value="orbs">Orbs (Floating blobs)</option>
                            <option value="lines">Lines (Horizontal pulses)</option>
                            <option value="matrix">Matrix (Green code rain)</option>
                            <option value="glitch">Glitch (Cyberpunk effect)</option>
                            <option value="none">None (Minimal)</option>
                        </select>

                        <InputError :message="form.errors.name_effect" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="name_shadow_color" value="Clan Name Shadow Color" />

                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-2">
                            Choose the color for your clan name's text shadow
                        </div>

                        <div class="flex items-center gap-3 mt-2">
                            <input
                                id="name_shadow_color"
                                v-model="form.name_shadow_color"
                                type="color"
                                class="h-10 w-20 rounded cursor-pointer border-gray-300 dark:border-gray-700"
                            />
                            <input
                                v-model="form.name_shadow_color"
                                type="text"
                                class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                placeholder="#3b82f6"
                            />
                        </div>

                        <InputError :message="form.errors.name_shadow_color" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="featured_stats" value="Featured Stats (Select up to 3)" />

                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1 mb-2">
                            Choose which stats to display on your clan card. You can select 1-3 stats or none.
                        </div>

                        <div class="mt-2 space-y-2">
                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                <input
                                    type="checkbox"
                                    value="total_records"
                                    v-model="form.featured_stats"
                                    :disabled="!form.featured_stats.includes('total_records') && form.featured_stats.length >= 3"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                                <div class="flex items-center gap-2 flex-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <div>
                                        <div class="text-sm font-medium text-gray-200">Total Records</div>
                                        <div class="text-xs text-gray-500">Display total number of records</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                <input
                                    type="checkbox"
                                    value="world_records"
                                    v-model="form.featured_stats"
                                    :disabled="!form.featured_stats.includes('world_records') && form.featured_stats.length >= 3"
                                    class="rounded border-gray-300 text-yellow-600 shadow-sm focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                                <div class="flex items-center gap-2 flex-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                    </svg>
                                    <div>
                                        <div class="text-sm font-medium text-gray-200">World Records</div>
                                        <div class="text-xs text-gray-500">Display #1 world record positions</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                <input
                                    type="checkbox"
                                    value="podium_finishes"
                                    v-model="form.featured_stats"
                                    :disabled="!form.featured_stats.includes('podium_finishes') && form.featured_stats.length >= 3"
                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                                <div class="flex items-center gap-2 flex-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                    </svg>
                                    <div>
                                        <div class="text-sm font-medium text-gray-200">Top 3 Positions</div>
                                        <div class="text-xs text-gray-500">Display podium finishes (1st, 2nd, 3rd)</div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div class="text-xs text-gray-500 mt-2">
                            Selected: {{ form.featured_stats.length }}/3
                        </div>

                        <InputError :message="form.errors.featured_stats" class="mt-2" />
                    </div>

                    <div class="flex justify-center">
                        <PrimaryButton type="submit" class="w-32 justify-center">
                            Edit
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>