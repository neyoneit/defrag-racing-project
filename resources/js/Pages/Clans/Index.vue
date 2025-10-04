<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ClanCard from './ClanCard.vue';
    import { ref, computed } from 'vue';
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
        invitations: Array,
        currentSort: String,
        currentDir: String
    });

    const sortClans = (field) => {
        const newDir = props.currentSort === field && props.currentDir === 'desc' ? 'asc' : 'desc';
        router.get(route('clans.index'), { sort: field, dir: newDir }, {
            preserveState: true,
            preserveScroll: true
        });
    };

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
        tag: props.myClan?.tag || '',
        image: null,
        background: null,
        name_effect: props.myClan?.name_effect || 'none',
        avatar_effect: props.myClan?.avatar_effect || 'none',
        effect_color: props.myClan?.effect_color || '#60a5fa',
        avatar_effect_color: props.myClan?.avatar_effect_color || '#60a5fa',
        featured_stat: props.myClan?.featured_stat || null
    });

    const hexToRgba = (hex, alpha = 1) => {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const previewEffectColor = computed(() => form.effect_color || '#60a5fa');
    const previewAvatarEffectColor = computed(() => form.avatar_effect_color || '#60a5fa');

    const submitForm = () => {
        form.post(route('clans.manage.update', props.myClan.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Don't close the modal, just reset the file inputs
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
                                    <!-- Clan Name and Tag -->
                                    <div class="grid grid-cols-2 gap-4">
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
                                                Quake3 color codes: ^1Red^2Green
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.name" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">Clan Tag</label>
                                            <TextInput
                                                id="tag"
                                                v-model="form.tag"
                                                type="text"
                                                class="w-full"
                                                placeholder="e.g. TRY"
                                                maxlength="10"
                                            />
                                            <div class="text-xs text-gray-400 mt-2">
                                                Short tag (optional, 2-10 chars)
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.tag" />
                                        </div>
                                    </div>

                                    <!-- Clan Name Effect & Color -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Clan Name Effect & Color</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            Animated effect to highlight your clan name on the detail page
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Effect & Color Picker Column -->
                                            <div class="space-y-3">
                                                <select
                                                    v-model="form.name_effect"
                                                    class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                >
                                                    <option value="none">None</option>
                                                    <option value="particles">Particles</option>
                                                    <option value="orbs">Orbs</option>
                                                    <option value="lines">Lines</option>
                                                    <option value="matrix">Matrix</option>
                                                    <option value="glitch">Glitch</option>
                                                    <option value="wave">Wave</option>
                                                    <option value="neon">Neon Pulse</option>
                                                    <option value="rgb">RGB Split</option>
                                                    <option value="flicker">Flicker</option>
                                                    <option value="hologram">Hologram</option>
                                                </select>

                                                <div class="flex items-center gap-3">
                                                    <input
                                                        type="color"
                                                        v-model="form.effect_color"
                                                        class="h-10 w-16 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-900 cursor-pointer"
                                                    />
                                                    <input
                                                        type="text"
                                                        v-model="form.effect_color"
                                                        placeholder="#60a5fa"
                                                        maxlength="7"
                                                        class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Effect Preview Column -->
                                            <div class="bg-black/40 rounded-lg py-8 px-4 relative overflow-hidden border border-white/10">
                                                <div class="relative py-6 px-8">
                                                    <!-- Particles Effect Preview -->
                                                    <div v-if="form.name_effect === 'particles'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none">
                                                        <div class="absolute top-[20%] left-[15%] w-3 h-3 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 20px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 3s ease-in-out infinite;`"></div>
                                                        <div class="absolute top-[70%] left-[25%] w-2 h-2 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 2.5s ease-in-out infinite; animation-delay: 0.5s;`"></div>
                                                        <div class="absolute top-[40%] left-[80%] w-3 h-3 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 20px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 2.8s ease-in-out infinite; animation-delay: 1s;`"></div>
                                                    </div>

                                                    <!-- Orbs Effect Preview -->
                                                    <div v-if="form.name_effect === 'orbs'" class="absolute inset-0 -inset-x-32 -inset-y-16 pointer-events-none overflow-hidden">
                                                        <div class="absolute -left-8 top-1/2 -translate-y-1/2 w-40 h-40 rounded-full blur-3xl" :style="`animation: orb-float 6s ease-in-out infinite; background: radial-gradient(circle, ${hexToRgba(previewEffectColor, 0.4)} 0%, ${hexToRgba(previewEffectColor, 0.3)} 50%, transparent 100%);`"></div>
                                                        <div class="absolute -right-8 top-1/2 -translate-y-1/2 w-48 h-48 rounded-full blur-3xl" :style="`animation: orb-float 6s ease-in-out infinite; animation-delay: 1s; background: radial-gradient(circle, ${hexToRgba(previewEffectColor, 0.4)} 0%, ${hexToRgba(previewEffectColor, 0.3)} 50%, transparent 100%);`"></div>
                                                    </div>

                                                    <!-- Lines Effect Preview -->
                                                    <div v-if="form.name_effect === 'lines'" class="absolute inset-0 -inset-x-24 -inset-y-4 pointer-events-none">
                                                        <div class="absolute top-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.5)}; animation: line-scan 3s linear infinite;`"></div>
                                                        <div class="absolute bottom-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.5)}; animation: line-scan 3s linear infinite; animation-delay: 1.5s;`"></div>
                                                    </div>

                                                    <!-- Matrix Effect Preview -->
                                                    <div v-if="form.name_effect === 'matrix'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none overflow-hidden">
                                                        <div class="absolute top-0 left-[20%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.5s linear infinite;`"></div>
                                                        <div class="absolute top-0 left-[40%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2s linear infinite; animation-delay: 0.5s;`"></div>
                                                        <div class="absolute top-0 left-[60%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.8s linear infinite; animation-delay: 1s;`"></div>
                                                        <div class="absolute top-0 left-[80%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.3s linear infinite; animation-delay: 1.5s;`"></div>
                                                    </div>

                                                    <!-- Glitch Effect Preview -->
                                                    <div v-if="form.name_effect === 'glitch'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black blur-[1px]" :style="`color: ${hexToRgba(previewEffectColor, 0.3)}; animation: glitch-text 0.5s infinite; transform: translate(2px, -1px);`" v-html="q3tohtml(form.name)"></h1>
                                                        <h1 class="absolute text-4xl font-black blur-[1px]" :style="`color: ${hexToRgba(previewEffectColor, 0.3)}; animation: glitch-text 0.5s infinite; animation-delay: 0.2s; transform: translate(-2px, 1px);`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Wave Effect Preview -->
                                                    <div v-if="form.name_effect === 'wave'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite; animation-delay: 0.1s;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Neon Pulse Effect Preview -->
                                                    <div v-if="form.name_effect === 'neon'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; text-shadow: 0 0 20px ${previewEffectColor}, 0 0 40px ${previewEffectColor}; animation: neon-pulse 1.5s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- RGB Split Effect Preview -->
                                                    <div v-if="form.name_effect === 'rgb'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; animation: rgb-split 0.8s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Flicker Effect Preview -->
                                                    <div v-if="form.name_effect === 'flicker'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; text-shadow: 0 0 10px ${previewEffectColor}; animation: flicker 3s linear infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Hologram Effect Preview -->
                                                    <div v-if="form.name_effect === 'hologram'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.85)}; text-shadow: 0 0 15px ${previewEffectColor}; animation: hologram 2s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                        <div class="absolute inset-0 pointer-events-none" :style="`background: repeating-linear-gradient(0deg, transparent, transparent 2px, ${hexToRgba(previewEffectColor, 0.05)} 2px, ${hexToRgba(previewEffectColor, 0.05)} 4px);`"></div>
                                                    </div>

                                                    <!-- Clan name preview -->
                                                    <h1 class="relative text-4xl font-black text-white text-center drop-shadow-2xl flex items-center justify-center" style="text-shadow: 0 0 40px rgba(59,130,246,0.3);" v-html="q3tohtml(form.name)"></h1>
                                                </div>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.name_effect" class="mt-2" />
                                        <InputError :message="form.errors.effect_color" class="mt-2" />
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

                                    <!-- Avatar Effect -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Avatar Effect & Color</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            Visual effect and color applied to your clan avatar
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Effect Selector & Color Picker -->
                                            <div class="space-y-3">
                                                <select
                                                    v-model="form.avatar_effect"
                                                    class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                >
                                                    <option value="none">None</option>
                                                    <option value="glow">Glow</option>
                                                    <option value="pulse">Pulse</option>
                                                    <option value="ring">Rotating Ring</option>
                                                    <option value="shine">Shine</option>
                                                    <option value="border">Animated Border</option>
                                                    <option value="particles">Particle Orbit</option>
                                                    <option value="spin">Spin</option>
                                                </select>

                                                <div class="flex items-center gap-3">
                                                    <input
                                                        type="color"
                                                        v-model="form.avatar_effect_color"
                                                        class="h-10 w-16 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-900 cursor-pointer"
                                                    />
                                                    <input
                                                        type="text"
                                                        v-model="form.avatar_effect_color"
                                                        placeholder="#60a5fa"
                                                        maxlength="7"
                                                        class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Avatar Preview -->
                                            <div class="bg-black/40 rounded-lg p-6 flex items-center justify-center border border-white/10">
                                                <div class="relative">
                                                    <!-- Glow Effect -->
                                                    <div v-if="form.avatar_effect === 'glow'" class="absolute inset-0 rounded-full blur-xl opacity-75" :style="`background-color: ${previewAvatarEffectColor};`"></div>

                                                    <!-- Pulse Effect -->
                                                    <div v-if="form.avatar_effect === 'pulse'" class="absolute inset-0 rounded-full animate-ping opacity-75" :style="`background-color: ${previewAvatarEffectColor};`"></div>

                                                    <!-- Ring Effect -->
                                                    <div v-if="form.avatar_effect === 'ring'" class="absolute -inset-2">
                                                        <div class="w-full h-full rounded-full border-4 border-t-transparent animate-spin" :style="`border-color: ${previewAvatarEffectColor}; border-top-color: transparent;`"></div>
                                                    </div>

                                                    <!-- Shine Effect -->
                                                    <div v-if="form.avatar_effect === 'shine'" class="absolute inset-0 rounded-full overflow-hidden">
                                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-40 animate-shine"></div>
                                                    </div>

                                                    <!-- Animated Border -->
                                                    <div v-if="form.avatar_effect === 'border'" class="absolute -inset-1 rounded-full opacity-75 animate-pulse" :style="`background: linear-gradient(45deg, ${previewAvatarEffectColor}, transparent, ${previewAvatarEffectColor}); background-size: 200% 200%; animation: gradient-shift 3s ease infinite;`"></div>

                                                    <!-- Particle Orbit -->
                                                    <div v-if="form.avatar_effect === 'particles'" class="absolute -inset-4">
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-1" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-2" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-3" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                    </div>

                                                    <!-- Spin Effect -->
                                                    <div v-if="form.avatar_effect === 'spin'" class="absolute inset-0 animate-spin-slow"></div>

                                                    <img
                                                        :src="imagePreview || (myClan.image ? '/storage/' + myClan.image : '/images/null.jpg')"
                                                        class="relative h-20 w-20 rounded-full object-cover ring-2 ring-white/20"
                                                        :class="{'animate-spin-slow': form.avatar_effect === 'spin'}"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.avatar_effect" class="mt-2" />
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
                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Detail Page Preview -->
                                                <div>
                                                    <div class="text-xs text-gray-400 mb-2">Detail page preview:</div>
                                                    <img
                                                        :src="backgroundPreview || '/storage/' + myClan.background"
                                                        class="rounded-lg w-full h-40 object-cover border border-white/20"
                                                    >
                                                </div>
                                                <!-- List Card Preview -->
                                                <div>
                                                    <div class="text-xs text-gray-400 mb-2">Card preview:</div>
                                                    <img
                                                        :src="backgroundPreview || '/storage/' + myClan.background"
                                                        class="rounded-lg w-full h-24 object-cover border border-white/20"
                                                    >
                                                </div>
                                            </div>
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

                                    <!-- Featured Stat Box -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Featured Stat Box</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            Choose a stat box from your clan detail page to display prominently in the clan list.
                                        </div>

                                        <select
                                            v-model="form.featured_stat"
                                            class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        >
                                            <option :value="null">None - No Featured Stat</option>
                                            <option value="total_records">Total Records</option>
                                            <option value="world_records">World Records (#1 Positions)</option>
                                            <option value="podium_finishes">Podium Finishes (Top 3)</option>
                                            <option value="top10_positions">Top 10 Positions</option>
                                            <option value="avg_wr_age">Average WR Age</option>
                                            <option value="map_coverage">Map Coverage</option>
                                        </select>

                                        <InputError :message="form.errors.featured_stat" class="mt-2" />
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
                        <div v-if="showInvitePlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Invite Player</h3>
                                </div>

                                <form @submit.prevent="submitInviteForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
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
                        <div v-if="showKickPlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Kick Player</h3>
                                </div>

                                <form @submit.prevent="submitKickForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
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
                        <div v-if="showTransferOwnership && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">Transfer Ownership</h3>
                                </div>

                                <form @submit.prevent="submitTransferForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
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
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-white">All Clans</h2>

                    <!-- Sorting Controls -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            @click="sortClans('name')"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'name'
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            Name
                            <span v-if="currentSort === 'name'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('members')"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'members'
                                    ? 'bg-green-600 text-white shadow-lg shadow-green-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            Members
                            <span v-if="currentSort === 'members'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('wrs')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'wrs'
                                    ? 'bg-yellow-600 text-white shadow-lg shadow-yellow-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <img src="/images/powerups/quad.svg" class="w-5 h-5" alt="WRs" />
                            World Records
                            <span v-if="currentSort === 'wrs'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('top3')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'top3'
                                    ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <img src="/images/powerups/haste.svg" class="w-5 h-5" alt="Top 3" />
                            Top 3 Positions
                            <span v-if="currentSort === 'top3'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <ClanCard v-for="(clan, index) in clans.data" :key="clan.id" :clan="clan" :style="{ animationDelay: `${index * 50}ms` }" class="animate-slideInUp" />

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

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slideInUp {
    animation: slideInUp 0.5s ease-out forwards;
    opacity: 0;
}

/* Avatar Effect Animations */
@keyframes shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.animate-shine {
    animation: shine 3s ease-in-out infinite;
}

@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spin-slow 8s linear infinite;
}

@keyframes orbit-1 {
    0% {
        transform: rotate(0deg) translateX(2.5rem) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(2.5rem) rotate(-360deg);
    }
}

@keyframes orbit-2 {
    0% {
        transform: rotate(120deg) translateX(2.5rem) rotate(-120deg);
    }
    100% {
        transform: rotate(480deg) translateX(2.5rem) rotate(-480deg);
    }
}

@keyframes orbit-3 {
    0% {
        transform: rotate(240deg) translateX(2.5rem) rotate(-240deg);
    }
    100% {
        transform: rotate(600deg) translateX(2.5rem) rotate(-600deg);
    }
}

.animate-orbit-1 {
    animation: orbit-1 4s linear infinite;
}

.animate-orbit-2 {
    animation: orbit-2 4s linear infinite;
}

.animate-orbit-3 {
    animation: orbit-3 4s linear infinite;
}

@keyframes gradient-shift {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
</style>
