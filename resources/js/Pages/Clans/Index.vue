<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ClanCard from './ClanCard.vue';
    import { ref } from 'vue';
    import InvitePlayerModal from './InvitePlayerModal.vue';
    import KickPlayerModal from './KickPlayerModal.vue';
    import LeaveClanModal from './LeaveClanModal.vue';
    import TransferOwnershipModal from './TransferOwnershipModal.vue';
    import DismantleClanModal from './DismantleClanModal.vue';
    import InvitationsModal from './InvitationsModal.vue';
    import ConfirmTransferOwnershipModal from './ConfirmTransferOwnershipModal.vue';
    import MemberNoteEditor from './MemberNoteEditor.vue';
    import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import PlayerSelectDefrag from '@/Components/Basic/PlayerSelectDefrag2.vue';
    import { usePage } from '@inertiajs/vue3';
    import { router } from '@inertiajs/vue3';
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

    const page = usePage()

    const props = defineProps({
        clans: Object,
        myClan: Object,
        users: Array,
        invitations: Array
    });

    const showInvitiationsModal = ref(false);

    const showInvitePlayer = ref(false);
    const showKickPlayer = ref(false);
    const showTransferOwnership = ref(false);
    const showLeaveClan = ref(false);
    const showEditClan = ref(false);
    const showDismantleClan = ref(false);
    const showEditMemberNotes = ref(false);

    // Invite Player Form
    const inviteUserId = ref([]);
    const inviteForm = useForm({
        _method: 'POST',
        player_id: []
    });

    const submitInviteForm = () => {
        if (inviteUserId.value.length > 0) {
            inviteForm.player_id = inviteUserId.value[0];
        } else {
            return;
        }

        inviteForm.post(route('clans.manage.invite'), {
            errorBag: 'submitInviteForm',
            preserveScroll: true,
            onSuccess: () => {
                showInvitePlayer.value = false;
                inviteUserId.value = [];
            }
        });
    };

    // Kick Player Form
    const kickUserId = ref([]);
    const kickForm = useForm({
        _method: 'POST',
        player_id: []
    });

    const submitKickForm = () => {
        if (kickUserId.value.length > 0) {
            kickForm.player_id = kickUserId.value[0];
        } else {
            return;
        }

        kickForm.post(route('clans.manage.kick'), {
            errorBag: 'submitKickForm',
            preserveScroll: true,
            onSuccess: () => {
                showKickPlayer.value = false;
                kickUserId.value = [];
            }
        });
    };

    // Transfer Ownership
    const transferUserId = ref([]);
    const transferForm = useForm({
        _method: 'POST',
        player_id: []
    });
    const showTransferConfirm = ref(false);

    const submitTransferForm = () => {
        if (transferUserId.value.length > 0) {
            transferForm.player_id = transferUserId.value[0];
            showTransferConfirm.value = true;
        }
    };

    const finalTransferSubmit = () => {
        transferForm.post(route('clans.manage.transfer'), {
            preserveScroll: true,
            onSuccess: () => {
                showTransferOwnership.value = false;
                showTransferConfirm.value = false;
                transferUserId.value = [];
            }
        });
    };

    // Leave Clan
    const leaveForm = useForm({
        _method: 'POST'
    });

    const submitLeaveForm = () => {
        leaveForm.post(route('clans.manage.leave'), {
            errorBag: 'submitLeaveForm',
            preserveScroll: true,
            onSuccess: () => {
                showLeaveClan.value = false;
            }
        });
    };

    // Dismantle Clan
    const dismantleForm = useForm({
        _method: 'POST'
    });

    const submitDismantleForm = () => {
        dismantleForm.post(route('clans.manage.dismantle'), {
            errorBag: 'submitDismantleForm',
            preserveScroll: true,
            onSuccess: () => {
                showDismantleClan.value = false;
            }
        });
    };

    const showInvitations = () => {
        if (page.props.auth?.user) {
            showInvitiationsModal.value = !showInvitiationsModal.value
            return;
        }

        router.get('/login')
    }

    // Edit Clan Form
    const imagePreview = ref(null);
    const imageInput = ref(null);
    const backgroundPreview = ref(null);
    const backgroundInput = ref(null);
    const showImageCropper = ref(false);
    const showBackgroundCropper = ref(false);
    const tempImageSrc = ref(null);
    const tempBackgroundSrc = ref(null);

    const form = useForm({
        _method: 'POST',
        name: props.myClan?.name || '',
        image: null,
        background: null
    });

    const submitForm = () => {
        form.post(route('clans.manage.update', props.myClan.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                showEditClan.value = false;
                imagePreview.value = null;
                backgroundPreview.value = null;
                form.reset('image', 'background');
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
            tempImageSrc.value = e.target.result;
            showImageCropper.value = true;
        };

        reader.readAsDataURL(image);
    };

    const imageCropper = ref(null);

    const handleImageCrop = () => {
        if (!imageCropper.value) return;

        const { canvas } = imageCropper.value.getResult();

        canvas.toBlob((blob) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target.result;
            };
            reader.readAsDataURL(blob);

            form.image = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            showImageCropper.value = false;
            tempImageSrc.value = null;
        }, 'image/jpeg', 0.92);
    };

    const cancelImageCrop = () => {
        showImageCropper.value = false;
        tempImageSrc.value = null;
        imageInput.value.value = '';
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
            tempBackgroundSrc.value = e.target.result;
            showBackgroundCropper.value = true;
        };

        reader.readAsDataURL(background);
    };

    const backgroundCropper = ref(null);

    const handleBackgroundCrop = () => {
        if (!backgroundCropper.value) return;

        const { canvas } = backgroundCropper.value.getResult();

        canvas.toBlob((blob) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                backgroundPreview.value = e.target.result;
            };
            reader.readAsDataURL(blob);

            form.background = new File([blob], 'background.jpg', { type: 'image/jpeg' });
            showBackgroundCropper.value = false;
            tempBackgroundSrc.value = null;
        }, 'image/jpeg', 0.92);
    };

    const cancelBackgroundCrop = () => {
        showBackgroundCropper.value = false;
        tempBackgroundSrc.value = null;
        backgroundInput.value.value = '';
    };
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Clans" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-20">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Clans</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <span class="text-sm font-semibold">Join forces with other players</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            @click="showInvitations"
                            class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600/20 to-blue-800/20 hover:from-blue-600/30 hover:to-blue-800/30 border border-blue-500/30 hover:border-blue-500/50 rounded-xl text-white transition-all duration-300 hover:scale-105"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                            </svg>
                            <span>Invitations</span>
                            <span class="px-2 py-0.5 bg-blue-500 rounded-full text-xs font-bold">{{ invitations.length }}</span>
                        </button>

                        <Link
                            v-if="! myClan"
                            :href="route('clans.manage.create')"
                            class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 rounded-xl text-white font-bold transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-blue-500/50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Create New Clan
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-10">
            <!-- My Clan Section -->
            <div v-if="myClan" class="mb-12">
                <h2 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    My Clan
                </h2>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-blue-700/10 blur-xl"></div>
                    <div class="relative">
                        <ClanCard
                            :clan="myClan"
                            manage
                            highlighted
                            :InvitePlayer="() => { showInvitePlayer = !showInvitePlayer; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; }"
                            :KickPlayer="() => { showKickPlayer = !showKickPlayer; showInvitePlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; }"
                            :TransferOwnership="() => { showTransferOwnership = !showTransferOwnership; showInvitePlayer = false; showKickPlayer = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; }"
                            :LeaveClan="() => { showLeaveClan = !showLeaveClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; }"
                            :EditClan="() => { showEditClan = !showEditClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditMemberNotes = false; }"
                            :DismantleClan="() => { showDismantleClan = !showDismantleClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showEditClan = false; showEditMemberNotes = false; }"
                            :EditMemberNotes="() => { showEditMemberNotes = !showEditMemberNotes; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; }"
                        />

                        <!-- Edit Clan Section -->
                        <div v-if="showEditClan && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Edit Clan</h3>
                                </div>

                                <form @submit.prevent="submitForm" class="p-6 space-y-6">
                                    <!-- Clan Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">
                                            Clan Name: <span v-html="q3tohtml(form.name)"></span>
                                        </label>
                                        <TextInput
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="w-full"
                                            required
                                            autocomplete="name"
                                        />
                                        <div class="text-xs text-gray-400 mt-2">
                                            You can use Quake3 color codes, such as: ^1Red^2Green
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.name" />
                                    </div>

                                    <!-- Clan Avatar -->
                                    <div>
                                        <input
                                            id="image"
                                            ref="imageInput"
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="updateimagePreview"
                                        >

                                        <label class="block text-sm font-medium text-gray-300 mb-2">Clan Avatar</label>

                                        <div class="flex items-center gap-4">
                                            <div v-if="imagePreview" class="shrink-0">
                                                <img :src="imagePreview" class="rounded-full h-20 w-20 object-cover border-2 border-white/20">
                                            </div>
                                            <div v-else-if="myClan.image" class="shrink-0">
                                                <img :src="'/storage/' + myClan.image" class="rounded-full h-20 w-20 object-cover border-2 border-white/20">
                                            </div>

                                            <SecondaryButton v-if="!showImageCropper" type="button" @click.prevent="selectNewImage">
                                                Change Avatar
                                            </SecondaryButton>
                                        </div>

                                        <!-- Image Cropper -->
                                        <div v-if="showImageCropper" class="mt-4">
                                            <div class="bg-black rounded-lg overflow-hidden border border-white/10" style="height: 400px;">
                                                <Cropper
                                                    ref="imageCropper"
                                                    :src="tempImageSrc"
                                                    :stencil-props="{
                                                        aspectRatio: 1,
                                                        movable: true,
                                                        resizable: true
                                                    }"
                                                    :canvas="{
                                                        maxWidth: 400,
                                                        maxHeight: 400
                                                    }"
                                                    class="cropper"
                                                />
                                            </div>
                                            <div class="flex gap-3 mt-3">
                                                <PrimaryButton type="button" @click="handleImageCrop">
                                                    Crop & Apply
                                                </PrimaryButton>
                                                <SecondaryButton type="button" @click="cancelImageCrop">
                                                    Cancel
                                                </SecondaryButton>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.image" class="mt-2" />
                                    </div>

                                    <!-- Clan Background -->
                                    <div>
                                        <input
                                            id="background"
                                            ref="backgroundInput"
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="updateBackgroundPreview"
                                        >

                                        <label class="block text-sm font-medium text-gray-300 mb-2">Clan Background</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            Recommended size: 1920x400px (wide banner format)
                                        </div>

                                        <div v-if="backgroundPreview || myClan.background" class="mb-3">
                                            <img
                                                :src="backgroundPreview || '/storage/' + myClan.background"
                                                class="rounded-lg w-full h-32 object-cover border border-white/20"
                                            >
                                        </div>

                                        <SecondaryButton v-if="!showBackgroundCropper" type="button" @click.prevent="selectNewBackground">
                                            {{ myClan.background ? 'Change Background' : 'Add Background' }}
                                        </SecondaryButton>

                                        <!-- Background Cropper -->
                                        <div v-if="showBackgroundCropper" class="mt-4">
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
                                            <div class="flex gap-3 mt-3">
                                                <PrimaryButton type="button" @click="handleBackgroundCrop">
                                                    Crop & Apply
                                                </PrimaryButton>
                                                <SecondaryButton type="button" @click="cancelBackgroundCrop">
                                                    Cancel
                                                </SecondaryButton>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.background" class="mt-2" />
                                    </div>

                                    <div class="pt-4">
                                        <PrimaryButton type="submit" class="w-full justify-center">
                                            Save Changes
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Member Notes Section -->
                        <div v-if="showEditMemberNotes && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Member Details</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <MemberNoteEditor
                                        v-for="player in myClan.players"
                                        :key="player.id"
                                        :player="player"
                                        :clan="myClan"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Invite Player Panel -->
                        <div v-if="showInvitePlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Invite Player</h3>
                                </div>

                                <form @submit.prevent="submitInviteForm" class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Select Player</label>
                                        <PlayerSelectDefrag
                                            id="invite_player_id"
                                            v-model="inviteUserId"
                                            :values="[]"
                                            :options="users"
                                        />
                                        <InputError :message="inviteForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="inviteUserId.length > 0" class="text-sm text-gray-400">
                                        Selected: <span class="text-white font-medium" v-html="q3tohtml(users.find(p => p.id === inviteUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center">
                                        Invite Player
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Kick Player Panel -->
                        <div v-if="showKickPlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Kick Player</h3>
                                </div>

                                <form @submit.prevent="submitKickForm" class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Select Player</label>
                                        <PlayerSelectDefrag
                                            id="kick_player_id"
                                            v-model="kickUserId"
                                            :values="[]"
                                            :options="myClan.players.map(p => p.user)"
                                        />
                                        <InputError :message="kickForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="kickUserId.length > 0" class="text-sm text-gray-400">
                                        Selected: <span class="text-white font-medium" v-html="q3tohtml(myClan.players.map(p => p.user).find(p => p.id === kickUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center bg-orange-600 hover:bg-orange-500">
                                        Kick Player
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Transfer Ownership Panel -->
                        <div v-if="showTransferOwnership && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Transfer Ownership</h3>
                                </div>

                                <form @submit.prevent="submitTransferForm" class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Select New Owner</label>
                                        <PlayerSelectDefrag
                                            id="transfer_player_id"
                                            v-model="transferUserId"
                                            :values="[]"
                                            :options="myClan.players.map(p => p.user)"
                                        />
                                        <InputError :message="transferForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="transferUserId.length > 0" class="text-sm text-gray-400">
                                        Selected: <span class="text-white font-medium" v-html="q3tohtml(myClan.players.map(p => p.user).find(p => p.id === transferUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center bg-sky-600 hover:bg-sky-500">
                                        Transfer Ownership
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Leave Clan Panel -->
                        <div v-if="showLeaveClan && myClan.admin_id !== $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Leave Clan</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <p class="text-gray-300">Are you sure you want to leave the clan? To join again you will need to be invited by the clan's admin!</p>
                                    <div class="flex gap-3">
                                        <PrimaryButton @click="submitLeaveForm" class="flex-1 justify-center bg-red-600 hover:bg-red-500">
                                            Leave Clan
                                        </PrimaryButton>
                                        <PrimaryButton @click="showLeaveClan = false" class="flex-1 justify-center bg-gray-600 hover:bg-gray-500">
                                            Cancel
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dismantle Clan Panel -->
                        <div v-if="showDismantleClan && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Dismantle Clan</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <p class="text-gray-300">Are you sure you want to delete this clan? This action cannot be undone.</p>
                                    <div class="flex gap-3">
                                        <PrimaryButton @click="submitDismantleForm" class="flex-1 justify-center bg-red-600 hover:bg-red-500">
                                            Delete Clan
                                        </PrimaryButton>
                                        <PrimaryButton @click="showDismantleClan = false" class="flex-1 justify-center bg-gray-600 hover:bg-gray-500">
                                            Cancel
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div v-if="myClan" class="h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent mb-12"></div>

            <!-- All Clans Section -->
            <div>
                <h2 class="text-2xl font-bold text-white mb-6">All Clans</h2>

                <div class="space-y-4">
                    <ClanCard v-for="clan in clans.data" :key="clan.id" :clan="clan" />

                    <div v-if="clans.total === 0" class="text-center py-20">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-white/5 rounded-full mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                        </div>
                        <p class="text-gray-400 text-lg">No clans to show yet</p>
                        <p class="text-gray-500 text-sm mt-2">Be the first to create one!</p>
                    </div>

                    <div class="border-t border-white/5 bg-transparent p-3" v-if="clans.total > clans.per_page">
                        <Pagination :current_page="clans.current_page" :last_page="clans.last_page" :link="clans.first_page_url" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Confirmation Modal -->
        <ConfirmTransferOwnershipModal
            v-if="myClan && showTransferConfirm && transferUserId.length > 0"
            :show="showTransferConfirm"
            :close="() => showTransferConfirm = false"
            :submit="finalTransferSubmit"
            :player="myClan.players.map(p => p.user).find(p => p.id === transferForm.player_id)"
            :clan="myClan"
        />

        <InvitationsModal
            :show="showInvitiationsModal"
            :close="() => showInvitiationsModal = false"
            :invitations="invitations"
            :myClan="myClan"
        />
    </div>
</template>

<style scoped>
.cropper {
    height: 400px;
    width: 100%;
}
</style>
