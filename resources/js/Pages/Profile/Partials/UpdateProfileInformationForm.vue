<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/Laravel/InputError.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import CountrySelect from '@/Components/Basic/CountrySelect.vue';
import countries from '@/Components/stubs/countries'

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
    country: props.user.country
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    // 1MB size limit for profile photo
    const maxSize = 1 * 1024 * 1024; // 1MB in bytes
    if (photo.size > maxSize) {
        form.errors.photo = 'The profile photo must be smaller than 1MB.';
        photoInput.value.value = '';
        return;
    }

    const reader = new FileReader();
    form.errors.photo = '';

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};


const setCountry = (country) => {
    form.country = country
};
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500/20 to-indigo-600/20 border border-indigo-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-indigo-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">Profile Information</h2>
            </div>
            <div class="flex items-center gap-2">
                <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-xs font-medium text-green-400">Saved</span>
                </div>
                <PrimaryButton type="button" @click="updateProfileInformation" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Save
                </PrimaryButton>
            </div>
        </div>

        <form @submit.prevent="updateProfileInformation" class="space-y-3">
            <!-- Profile Photo -->
            <div>
                <!-- Profile Photo File Input -->
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <InputLabel for="photo" value="Photo" />
                <div class="text-xs text-gray-400 mt-1 mb-2">
                    Max 1MB. GIF supported (no compression).
                </div>

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-2">
                    <img :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" :alt="user.name" class="rounded-full h-20 w-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span
                        class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                    Select A New Photo
                </SecondaryButton>

                <SecondaryButton
                    v-if="user.profile_photo_path"
                    type="button"
                    class="mt-2"
                    @click.prevent="deletePhoto"
                >
                    Remove Photo
                </SecondaryButton>

                <InputError :message="form.errors.photo" class="mt-1" />
            </div>

            <!-- Username -->
            <div>
                <InputLabel for="username" value="Username" />
                <TextInput
                    id="username"
                    type="text"
                    :value="user.username"
                    class="mt-1 block w-full"
                    disabled
                />
            </div>

            <!-- Name -->
            <div>
                <InputLabel for="name" v-html="'Name: ' + q3tohtml(form.name)" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-1" />
                <div class="text-xs text-gray-400 mt-1">
                    You can use Quake3 color codes, such as: ^1Red^2Green
                </div>
            </div>

            <!-- Country -->
            <div>
                <div class="flex justify-between items-center mb-1">
                    <InputLabel for="name" value="Country" />

                    <div class="flex items-center text-xs text-gray-400">
                        {{ countries[user.country] }}
                        <img :src="`/images/flags/${user.country}.png`" class="w-6 ml-3">
                    </div>
                </div>
                <CountrySelect :setCountry="setCountry" />
                <InputError :message="form.errors.country" class="mt-1" />
            </div>

            <!-- Email -->
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" class="mt-1" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-xs mt-1 text-white">
                        Your email address is unverified.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-xs text-gray-400 hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800"
                            @click.prevent="sendEmailVerification"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-1 font-medium text-xs text-green-400">
                        A new verification link has been sent to your email address.
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
