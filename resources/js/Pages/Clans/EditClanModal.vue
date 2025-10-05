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
        name_shadow_color: props.clan?.name_shadow_color || '#3b82f6'
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