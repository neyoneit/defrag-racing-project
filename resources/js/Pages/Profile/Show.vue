<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import CountrySelect from '@/Components/Basic/CountrySelect.vue';
import countries from '@/Components/stubs/countries';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateSocialMediaForm from '@/Pages/Profile/Partials/UpdateSocialMediaForm.vue';
import VerifyMddProfile from '@/Pages/Profile/Partials/VerifyMddProfile.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import InputError from '@/Components/Laravel/InputError.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const props = defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Profile Information Form
const profileForm = useForm({
    _method: 'PUT',
    name: user.value.name || '',
    email: user.value.email || '',
    photo: null,
    country: user.value.country || 'US'
});

const photoInput = ref(null);
const photoPreview = ref(null);

const selectNewPhoto = () => photoInput.value.click();
const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];
    if (!photo) return;
    // 1MB size limit for profile photo
    const maxSize = 1 * 1024 * 1024;
    if (photo.size > maxSize) {
        profileForm.errors.photo = 'The profile photo must be smaller than 1MB.';
        photoInput.value.value = '';
        return;
    }
    profileForm.errors.photo = '';
    const reader = new FileReader();
    reader.onload = (e) => { photoPreview.value = e.target.result; };
    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            if (photoInput.value?.value) photoInput.value.value = null;
        },
    });
};

const updateProfile = () => {
    if (photoInput.value) profileForm.photo = photoInput.value.files[0];
    profileForm.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            if (photoInput.value?.value) photoInput.value.value = null;
        },
    });
};

const setCountry = (country) => { profileForm.country = country; };

// Background Upload Form
const backgroundForm = useForm({
    _method: 'POST',
    background: null
});

const backgroundInput = ref(null);
const backgroundPreview = ref(null);
const showBackgroundCropper = ref(false);
const tempBackgroundSrc = ref(null);
const backgroundCropper = ref(null);

const selectNewBackground = () => backgroundInput.value.click();

const updateBackgroundPreview = () => {
    const background = backgroundInput.value.files[0];
    if (!background) return;
    if (!background.type.startsWith('image/')) {
        backgroundForm.errors.background = 'The file must be an Image.';
        backgroundInput.value.value = '';
        return;
    }
    // 5MB size limit for profile background
    const maxSize = 5 * 1024 * 1024;
    if (background.size > maxSize) {
        backgroundForm.errors.background = 'The background image must be smaller than 5MB.';
        backgroundInput.value.value = '';
        return;
    }
    backgroundForm.errors.background = '';
    const reader = new FileReader();
    reader.onload = (e) => {
        tempBackgroundSrc.value = e.target.result;
        showBackgroundCropper.value = true;
    };
    reader.readAsDataURL(background);
};

const handleBackgroundCrop = () => {
    if (!backgroundCropper.value) return;

    const { canvas } = backgroundCropper.value.getResult();

    canvas.toBlob((blob) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            backgroundPreview.value = e.target.result;
        };
        reader.readAsDataURL(blob);

        backgroundForm.background = new File([blob], 'background.jpg', { type: 'image/jpeg' });
        showBackgroundCropper.value = false;
        tempBackgroundSrc.value = null;

        backgroundForm.post(route('settings.background'), {
            preserveScroll: true,
            onSuccess: () => {
                backgroundInput.value.value = '';
                backgroundPreview.value = null;
            }
        });
    }, 'image/jpeg', 0.92);
};

const cancelBackgroundCrop = () => {
    showBackgroundCropper.value = false;
    tempBackgroundSrc.value = null;
    backgroundInput.value.value = '';
};

const deleteBackground = () => {
    router.delete(route('settings.background.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            if (backgroundInput.value?.value) backgroundInput.value.value = null;
        }
    });
};

// Preferences Form
const prefsForm = useForm({
    _method: 'POST',
    color: user.value.color || '#1F2937',
    avatar_effect: user.value.avatar_effect || 'none',
    name_effect: user.value.name_effect || 'none',
    avatar_border_color: user.value.avatar_border_color || '#6b7280'
});

const updatePreferences = () => {
    prefsForm.post(route('settings.preferences'), { preserveScroll: true });
};

const avatarEffects = [
    { id: 'none', name: 'None', icon: '⭕' },
    { id: 'glow', name: 'Glow', icon: '✨' },
    { id: 'pulse', name: 'Pulse', icon: '💓' },
    { id: 'spin', name: 'Spin', icon: '🌀' },
    { id: 'bounce', name: 'Bounce', icon: '⬆️' },
    { id: 'shake', name: 'Shake', icon: '📳' },
    { id: 'ring', name: 'Ring', icon: '💍' },
    { id: 'fire', name: 'Fire', icon: '🔥' },
    { id: 'electric', name: 'Electric', icon: '⚡' },
    { id: 'rainbow', name: 'Rainbow', icon: '🌈' },
    { id: 'portal', name: 'Portal', icon: '🌌' },
    { id: 'hologram', name: 'Hologram', icon: '👾' },
    { id: 'glitch', name: 'Glitch', icon: '📺' },
    { id: 'frost', name: 'Frost', icon: '❄️' },
    { id: 'neon', name: 'Neon', icon: '💡' },
    { id: 'orbit', name: 'Orbit', icon: '🪐' },
    { id: 'matrix', name: 'Matrix', icon: '🟢' },
    { id: 'vortex', name: 'Vortex', icon: '🌪️' },
    { id: 'quantum', name: 'Quantum', icon: '⚛️' },
    { id: 'nebula', name: 'Nebula', icon: '☁️' },
    { id: 'supernova', name: 'Supernova', icon: '💥' },
    { id: 'digital', name: 'Digital', icon: '💾' },
    { id: 'cosmic', name: 'Cosmic', icon: '🌠' },
    { id: 'plasma', name: 'Plasma', icon: '🔮' }
];

const nameEffects = [
    { id: 'none', name: 'None', icon: '⭕' },
    { id: 'wave', name: 'Wave', icon: '🌊' },
    { id: 'bounce', name: 'Bounce', icon: '⬆️' },
    { id: 'shake', name: 'Shake', icon: '📳' },
    { id: 'glitch', name: 'Glitch', icon: '📺' },
    { id: 'rainbow', name: 'Rainbow', icon: '🌈' },
    { id: 'neon', name: 'Neon', icon: '💡' },
    { id: 'typewriter', name: 'Typewriter', icon: '⌨️' },
    { id: 'slide', name: 'Slide', icon: '➡️' },
    { id: 'fade', name: 'Fade', icon: '👻' },
    { id: 'zoom', name: 'Zoom', icon: '🔍' },
    { id: 'flip', name: 'Flip', icon: '🔄' },
    { id: 'shadow', name: 'Shadow', icon: '🌑' },
    { id: 'gradient', name: 'Gradient', icon: '🎨' },
    { id: 'electric', name: 'Electric', icon: '⚡' },
    { id: 'fire', name: 'Fire Text', icon: '🔥' },
    { id: 'matrix', name: 'Matrix', icon: '🟢' },
    { id: 'cyber', name: 'Cyber', icon: '🤖' },
    { id: 'vortex', name: 'Vortex', icon: '🌪️' },
    { id: 'quantum', name: 'Quantum', icon: '⚛️' },
    { id: 'nebula', name: 'Nebula', icon: '☁️' },
    { id: 'wave-distort', name: 'Wave Distort', icon: '〰️' },
    { id: 'plasma', name: 'Plasma', icon: '🔮' },
    { id: 'cosmic', name: 'Cosmic', icon: '🌠' },
    { id: 'shockwave', name: 'Shockwave', icon: '💥' },
    { id: 'fractal', name: 'Fractal', icon: '🔺' }
];

// Notifications Form
const notifsForm = useForm({
    _method: 'POST',
    defrag_news: user.value.defrag_news ? true : false,
    tournament_news: user.value.tournament_news ? true : false,
    invitations: true,
    records_vq3: user.value.records_vq3 || 'all',
    records_cpm: user.value.records_cpm || 'all'
});

const updateNotifications = () => {
    notifsForm.post(route('settings.notifications'), { preserveScroll: true });
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Settings" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div>
                    <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Settings</h1>
                    <p class="text-sm text-gray-400">Customize your profile and preferences</p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-4 pb-6">
            <!-- Profile, MDD & Social Media Grid -->
            <div class="grid grid-cols-3 gap-4">
                <!-- Profile Card -->
                <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-bold text-white">Profile</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="profileForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-xs font-medium text-green-400">Saved</span>
                                </div>
                                <PrimaryButton type="button" @click="updateProfile" :disabled="profileForm.processing">
                                    Save
                                </PrimaryButton>
                            </div>
                        </div>

                        <form @submit.prevent="updateProfile" class="space-y-3">
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

                            <div>
                                <InputLabel for="email" value="Email" />
                                <TextInput
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                />
                            </div>

                            <div>
                                <InputLabel for="name">
                                    Display Name <span v-html="'(' + q3tohtml(profileForm.name) + ')'"></span>
                                </InputLabel>
                                <TextInput
                                    id="name"
                                    v-model="profileForm.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="^1Red^2Green"
                                />
                            </div>

                            <div>
                                <InputLabel for="country" value="Country" />
                                <CountrySelect :setCountry="setCountry" :selectedCountry="profileForm.country" class="mt-1" />
                            </div>
                        </form>
                    </div>
                </div>

                <!-- MDD Verification -->
                <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 overflow-hidden">
                    <VerifyMddProfile :user="user" />
                </div>

                <!-- Social Media -->
                <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 overflow-hidden">
                    <UpdateSocialMediaForm :user="user" />
                </div>
            </div>

            <!-- Profile Images Card -->
            <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500/20 to-cyan-600/20 border border-cyan-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">Profile Images</h2>
                        </div>
                        <div v-if="profileForm.recentlySuccessful || backgroundForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                            <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-xs font-medium text-green-400">Saved</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <!-- Avatar Upload -->
                        <div>
                            <input ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview" accept="image/*" />
                            <InputLabel value="Avatar" class="text-sm font-medium mb-2" />
                            <div class="text-xs text-gray-400 mb-4">Max 1MB. GIF supported.</div>
                            <div class="flex flex-col items-center gap-4">
                                <div v-if="photoPreview" class="shrink-0">
                                    <img :src="photoPreview" class="rounded-full h-32 w-32 object-cover border-2 border-white/20 shadow-lg">
                                </div>
                                <div v-else-if="user.profile_photo_path" class="shrink-0">
                                    <img :src="'/storage/' + user.profile_photo_path" class="rounded-full h-32 w-32 object-cover border-2 border-white/20 shadow-lg">
                                </div>
                                <div v-else class="shrink-0 w-32 h-32 rounded-full bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                    <span class="text-xs text-gray-500">No avatar</span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="selectNewPhoto" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-md transition">
                                        Change
                                    </button>
                                    <button v-if="user.profile_photo_path" type="button" @click="deletePhoto" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <InputError :message="profileForm.errors.photo" class="mt-2" />
                        </div>

                        <!-- Background Upload -->
                        <div>
                            <input ref="backgroundInput" type="file" class="hidden" @change="updateBackgroundPreview" accept="image/*" />
                            <InputLabel value="Background" class="text-sm font-medium mb-2" />
                            <div class="text-xs text-gray-400 mb-4">Recommended: 1920x400px. Max 5MB. GIF supported.</div>
                            <div class="space-y-4">
                                <div v-if="backgroundPreview || user.profile_background_path" class="w-full h-32 rounded-lg overflow-hidden border-2 border-white/20 shadow-lg">
                                    <img :src="backgroundPreview || '/storage/' + user.profile_background_path" class="w-full h-full object-cover" />
                                </div>
                                <div v-else class="w-full h-32 rounded-lg bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                    <span class="text-xs text-gray-500">No background image</span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="selectNewBackground" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-md transition">
                                        Change
                                    </button>
                                    <button v-if="user.profile_background_path" type="button" @click="deleteBackground" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <InputError :message="backgroundForm.errors.background" class="mt-2" />
                        </div>
                    </div>

                    <!-- Background Cropper -->
                    <div v-if="showBackgroundCropper" class="mt-6 pt-6 border-t border-white/10">
                        <div class="bg-black rounded-lg overflow-hidden border border-white/10" style="height: 400px;">
                            <Cropper
                                ref="backgroundCropper"
                                :src="tempBackgroundSrc"
                                :stencil-props="{
                                    aspectRatio: 4.8,
                                    movable: true,
                                    resizable: true
                                }"
                                :canvas="{
                                    maxWidth: 1920,
                                    maxHeight: 400
                                }"
                                class="cropper"
                            />
                        </div>
                        <div class="flex gap-2 mt-4">
                            <PrimaryButton type="button" @click="handleBackgroundCrop">
                                Crop & Upload
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="cancelBackgroundCrop">
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences Card -->
            <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">Customization</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="prefsForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-xs font-medium text-green-400">Saved</span>
                            </div>
                            <PrimaryButton type="button" @click="updatePreferences" :disabled="prefsForm.processing">
                                Save
                            </PrimaryButton>
                        </div>
                    </div>

                    <form @submit.prevent="updatePreferences" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Left: Effects & Colors -->
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel for="effect_color" value="Effect Color" />
                                        <input v-model="prefsForm.color" id="effect_color" type="color" class="mt-1 w-full h-8 rounded border-2 border-grayop-700 bg-grayop-900 cursor-pointer" />
                                    </div>

                                    <div>
                                        <InputLabel for="border_color" value="Border Color" />
                                        <input v-model="prefsForm.avatar_border_color" id="border_color" type="color" class="mt-1 w-full h-8 rounded border-2 border-grayop-700 bg-grayop-900 cursor-pointer" />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="avatar_effect">
                                        Avatar Effect <span class="text-xs text-purple-400">({{ avatarEffects.find(e => e.id === prefsForm.avatar_effect)?.name }})</span>
                                    </InputLabel>
                                    <div class="mt-1 grid grid-cols-8 gap-1">
                                        <button
                                            v-for="effect in avatarEffects"
                                            :key="effect.id"
                                            type="button"
                                            @click="prefsForm.avatar_effect = effect.id"
                                            :class="[
                                                'w-12 h-12 rounded border transition-all hover:scale-105',
                                                prefsForm.avatar_effect === effect.id
                                                    ? 'border-purple-500 bg-purple-500/20 shadow-md shadow-purple-500/40'
                                                    : 'border-white/10 bg-black/20 hover:border-purple-500/50'
                                            ]"
                                            :title="effect.name"
                                        >
                                            <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="name_effect">
                                        Name Effect <span class="text-xs text-pink-400">({{ nameEffects.find(e => e.id === prefsForm.name_effect)?.name }})</span>
                                    </InputLabel>
                                    <div class="mt-1 grid grid-cols-8 gap-1">
                                        <button
                                            v-for="effect in nameEffects"
                                            :key="effect.id"
                                            type="button"
                                            @click="prefsForm.name_effect = effect.id"
                                            :class="[
                                                'w-12 h-12 rounded border transition-all hover:scale-105',
                                                prefsForm.name_effect === effect.id
                                                    ? 'border-pink-500 bg-pink-500/20 shadow-md shadow-pink-500/40'
                                                    : 'border-white/10 bg-black/20 hover:border-pink-500/50'
                                            ]"
                                            :title="effect.name"
                                        >
                                            <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Preview -->
                            <div>
                                <InputLabel value="Preview" />
                                <div class="mt-1 p-4 rounded-lg bg-gradient-to-br from-black/40 to-black/20 border border-white/5 h-full flex items-center justify-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div :class="'avatar-effect-' + prefsForm.avatar_effect" :style="`--effect-color: ${prefsForm.color}; --border-color: ${prefsForm.avatar_border_color}`">
                                            <img :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" class="w-24 h-24 rounded-full object-cover border-4" :style="`border-color: ${prefsForm.avatar_border_color}`" />
                                        </div>
                                        <div :class="'name-effect-' + prefsForm.name_effect" :style="`--effect-color: ${prefsForm.color}`">
                                            <div class="text-lg font-bold text-white" v-html="q3tohtml(user.name)"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications & Security Grid -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Notifications Card -->
                <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-500/20 to-orange-600/20 border border-orange-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-orange-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-bold text-white">Notifications</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="notifsForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-xs font-medium text-green-400">Saved</span>
                                </div>
                                <PrimaryButton type="button" @click="updateNotifications" :disabled="notifsForm.processing">
                                    Save
                                </PrimaryButton>
                            </div>
                        </div>

                        <form @submit.prevent="updateNotifications" class="space-y-3">
                            <div class="space-y-1.5">
                                <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 cursor-pointer transition-all">
                                    <input v-model="notifsForm.defrag_news" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-300">Defrag News</p>
                                        <p class="text-xs text-gray-400">Announcements & updates</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 cursor-pointer transition-all">
                                    <input v-model="notifsForm.tournament_news" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-300">Tournament News</p>
                                        <p class="text-xs text-gray-400">Round starts & results</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 opacity-50 cursor-not-allowed">
                                    <input type="checkbox" checked disabled class="w-4 h-4 rounded bg-white/10 border-white/20" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-300">Invitations <span class="text-xs text-yellow-400">(Required)</span></p>
                                        <p class="text-xs text-gray-400">Clan & team invites</p>
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2.5 rounded-lg bg-blue-500/5 border border-blue-500/20">
                                    <p class="text-sm font-bold text-blue-400 mb-2">VQ3 Records</p>
                                    <div class="space-y-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_vq3" type="radio" value="all" class="w-4 h-4 text-blue-600" />
                                            <span class="text-sm text-gray-300">All</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_vq3" type="radio" value="wr" class="w-4 h-4 text-blue-600" />
                                            <span class="text-sm text-gray-300">WR Only</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="p-2.5 rounded-lg bg-green-500/5 border border-green-500/20">
                                    <p class="text-sm font-bold text-green-400 mb-2">CPM Records</p>
                                    <div class="space-y-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_cpm" type="radio" value="all" class="w-4 h-4 text-green-600" />
                                            <span class="text-sm text-gray-300">All</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_cpm" type="radio" value="wr" class="w-4 h-4 text-green-600" />
                                            <span class="text-sm text-gray-300">WR Only</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Cards Stacked -->
                <div class="space-y-4">
                    <div v-if="$page.props.jetstream.canUpdatePassword" class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 overflow-hidden">
                        <UpdatePasswordForm />
                    </div>
                    <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication" class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 overflow-hidden">
                        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
                    </div>
                </div>
            </div>

            <!-- Sessions -->
            <div class="rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 overflow-hidden">
                <LogoutOtherBrowserSessionsForm :sessions="sessions" />
            </div>

            <!-- Danger Zone -->
            <div v-if="$page.props.jetstream.hasAccountDeletionFeatures" class="rounded-xl bg-gradient-to-br from-red-500/5 to-red-600/5 border border-red-500/30 overflow-hidden">
                <DeleteUserForm />
            </div>
        </div>
    </div>
</template>

<style scoped>
.cropper {
    height: 400px;
    width: 100%;
}
</style>
