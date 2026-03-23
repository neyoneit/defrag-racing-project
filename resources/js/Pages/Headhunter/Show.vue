<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const page = usePage();

const props = defineProps({
    challenge: Object,
    userParticipation: Object,
    userDemos: Array,
    currentRecords: Array,
    map: Object,
    isWithinGracePeriod: Boolean,
    hasActiveDispute: Boolean,
    creatorStats: Object,
    disputes: Array,
});

const showSubmitModal = ref(false);
const showRejectModal = ref(false);
const showDisputeModal = ref(false);
const showEditModal = ref(false);
const showRequestEditModal = ref(false);
const showCloseConfirm = ref(false);
const showApproveConfirm = ref(false);
const showRemoveConfirm = ref(false);
const showDisputeResponseModal = ref(false);
const rejectingParticipant = ref(null);
const approvingParticipant = ref(null);
const removingParticipant = ref(null);
const respondingDispute = ref(null);

const submitForm = useForm({
    record_id: null,
    offline_record_id: null,
    source: 'online',
    submission_notes: '',
});

const selectedDemo = ref(null);

const selectDemo = (demo) => {
    selectedDemo.value = demo;
    if (demo.source === 'offline') {
        submitForm.source = 'offline';
        submitForm.offline_record_id = demo.id;
        submitForm.record_id = null;
    } else {
        submitForm.source = 'online';
        submitForm.record_id = demo.id;
        submitForm.offline_record_id = null;
    }
};

const rejectForm = useForm({
    rejection_reason: '',
});

const disputeForm = useForm({
    reason: '',
    evidence: '',
});

const editForm = useForm({
    title: props.challenge.title,
    description: props.challenge.description,
    mapname: props.challenge.mapname,
    physics: props.challenge.physics,
    mode: props.challenge.mode,
    target_time: '',
    reward_amount: props.challenge.reward_amount || '',
    reward_currency: props.challenge.reward_currency || 'USD',
    reward_description: props.challenge.reward_description || '',
    expires_at: props.challenge.expires_at ? props.challenge.expires_at.slice(0, 16) : '',
});

const requestEditForm = useForm({
    reason: '',
});

const disputeResponseForm = useForm({
    creator_response: '',
});

// Initialize target_time display
editForm.target_time = formatTime(props.challenge.target_time);

const isCreator = computed(() => {
    return props.challenge.creator_id === page.props.auth?.user?.id;
});

const isAuthenticated = computed(() => {
    return !!page.props.auth?.user;
});

// Participants grouped by status
const submittedParticipants = computed(() => {
    return (props.challenge.participants || []).filter(p => p.status === 'submitted');
});

const getParticipantTime = (p) => {
    if (p.record) return p.record.time;
    if (p.offline_record) return p.offline_record.time_ms;
    return Infinity;
};

const approvedParticipants = computed(() => {
    return (props.challenge.participants || [])
        .filter(p => p.status === 'approved' && (p.record || p.offline_record))
        .sort((a, b) => getParticipantTime(a) - getParticipantTime(b));
});

const allParticipants = computed(() => {
    return props.challenge.participants || [];
});

// Expiration countdown
const countdown = ref('');
const countdownInterval = ref(null);

const updateCountdown = () => {
    if (!props.challenge.expires_at) {
        countdown.value = '';
        return;
    }
    const now = new Date();
    const expires = new Date(props.challenge.expires_at);
    const diff = expires - now;

    if (diff <= 0) {
        countdown.value = 'Expired';
        if (countdownInterval.value) clearInterval(countdownInterval.value);
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
        countdown.value = `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        countdown.value = `${hours}h ${minutes}m`;
    } else {
        countdown.value = `${minutes}m`;
    }
};

onMounted(() => {
    updateCountdown();
    if (props.challenge.expires_at) {
        countdownInterval.value = setInterval(updateCountdown, 60000);
    }
});

onUnmounted(() => {
    if (countdownInterval.value) clearInterval(countdownInterval.value);
});

// Progress indicator - how close user's best time is to target
const userProgress = computed(() => {
    if (!props.userDemos || props.userDemos.length === 0) return null;
    const bestTime = props.userDemos[0].time; // already sorted by time asc
    const target = props.challenge.target_time;
    const percentage = Math.min(100, Math.max(0, ((target - bestTime) / target) * 100 + 100));
    return {
        bestTime,
        percentage: Math.round(percentage),
        beatTarget: bestTime <= target,
    };
});

function formatTime(milliseconds) {
    if (!milliseconds) return '0.000';
    const seconds = Math.floor(milliseconds / 1000);
    const ms = milliseconds % 1000;
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;

    if (minutes > 0) {
        return `${minutes}:${String(secs).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    }
    return `${secs}.${String(ms).padStart(3, '0')}`;
}

function parseTimeToMs(timeInput) {
    const trimmed = timeInput.trim();
    if (trimmed.includes(':')) {
        const [minutes, rest] = trimmed.split(':');
        const [seconds, ms] = rest.split('.');
        return (parseInt(minutes) * 60 * 1000) + (parseInt(seconds) * 1000) + parseInt(ms || 0);
    }
    const dotCount = (trimmed.match(/\./g) || []).length;
    if (dotCount === 2) {
        const [minutes, seconds, ms] = trimmed.split('.');
        return (parseInt(minutes) * 60 * 1000) + (parseInt(seconds) * 1000) + parseInt(ms || 0);
    }
    const [seconds, ms] = trimmed.split('.');
    return (parseInt(seconds) * 1000) + parseInt(ms || 0);
}

const participate = () => {
    router.post(route('headhunter.participate', props.challenge.id));
};

const submitProof = () => {
    submitForm.post(route('headhunter.submitProof', props.challenge.id), {
        onSuccess: () => {
            showSubmitModal.value = false;
            submitForm.reset();
        }
    });
};

const openApproveConfirm = (participant) => {
    approvingParticipant.value = participant;
    showApproveConfirm.value = true;
};

const approveSubmission = () => {
    router.post(route('headhunter.approve', [props.challenge.id, approvingParticipant.value.id]), {}, {
        onSuccess: () => {
            showApproveConfirm.value = false;
            approvingParticipant.value = null;
        }
    });
};

const unapproveSubmission = (participantId) => {
    router.post(route('headhunter.unapprove', [props.challenge.id, participantId]));
};

const openRemoveConfirm = (participant) => {
    removingParticipant.value = participant;
    showRemoveConfirm.value = true;
};

const removeParticipant = () => {
    router.delete(route('headhunter.removeParticipant', [props.challenge.id, removingParticipant.value.id]), {
        onSuccess: () => {
            showRemoveConfirm.value = false;
            removingParticipant.value = null;
        }
    });
};

const openDisputeResponse = (dispute) => {
    respondingDispute.value = dispute;
    disputeResponseForm.reset();
    showDisputeResponseModal.value = true;
};

const submitDisputeResponse = () => {
    disputeResponseForm.post(route('headhunter.respondDispute', [props.challenge.id, respondingDispute.value.id]), {
        onSuccess: () => {
            showDisputeResponseModal.value = false;
            disputeResponseForm.reset();
            respondingDispute.value = null;
        }
    });
};

const openRejectModal = (participant) => {
    rejectingParticipant.value = participant;
    rejectForm.reset();
    showRejectModal.value = true;
};

const rejectSubmission = () => {
    rejectForm.post(route('headhunter.reject', [props.challenge.id, rejectingParticipant.value.id]), {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectForm.reset();
            rejectingParticipant.value = null;
        }
    });
};

const closeChallenge = () => {
    router.post(route('headhunter.close', props.challenge.id), {}, {
        onSuccess: () => { showCloseConfirm.value = false; }
    });
};

const submitEdit = () => {
    editForm.transform((data) => ({
        ...data,
        target_time: parseTimeToMs(data.target_time),
    })).put(route('headhunter.update', props.challenge.id), {
        onSuccess: () => { showEditModal.value = false; }
    });
};

const submitRequestEdit = () => {
    requestEditForm.post(route('headhunter.requestEdit', props.challenge.id), {
        onSuccess: () => {
            showRequestEditModal.value = false;
            requestEditForm.reset();
        }
    });
};

const submitDispute = () => {
    disputeForm.post(route('headhunter.dispute', props.challenge.id), {
        onSuccess: () => {
            showDisputeModal.value = false;
            disputeForm.reset();
        }
    });
};

const getStatusColor = (status) => {
    const colors = {
        'open': 'text-green-400 bg-green-500/20 border-green-500/30',
        'claimed': 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30',
        'completed': 'text-blue-400 bg-blue-500/20 border-blue-500/30',
        'disputed': 'text-red-400 bg-red-500/20 border-red-500/30',
        'closed': 'text-gray-400 bg-gray-500/20 border-gray-500/30',
        'participating': 'text-blue-400 bg-blue-500/20 border-blue-500/30',
        'submitted': 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30',
        'approved': 'text-green-400 bg-green-500/20 border-green-500/30',
        'rejected': 'text-red-400 bg-red-500/20 border-red-500/30',
    };
    return colors[status] || colors['open'];
};

const getParticipantStatusLabel = (status) => {
    const labels = {
        'participating': 'Joined',
        'submitted': 'Proof Submitted',
        'approved': 'Approved',
        'rejected': 'Rejected',
    };
    return labels[status] || status;
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head :title="challenge.title" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('headhunter.index')" class="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Challenges
                </Link>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">

            <!-- Disclaimer Banner -->
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-6">
                <div class="flex gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="text-sm text-yellow-300">
                        <strong>Disclaimer:</strong> Defrag Racing is not responsible for the fulfillment of rewards. All challenges and rewards are agreements between the creator and participants. Creators who fail to honor rewards may be banned from creating future challenges.
                    </div>
                </div>
            </div>

            <!-- Challenge Header -->
            <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8 mb-6">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <h1 class="text-3xl font-black text-white">{{ challenge.title }}</h1>
                            <span :class="`px-3 py-1 text-sm font-semibold rounded-full border ${getStatusColor(challenge.status)}`">
                                {{ challenge.status }}
                            </span>
                        </div>
                        <p class="text-gray-300 text-lg">{{ challenge.description }}</p>
                    </div>

                    <div class="text-right">
                        <div v-if="challenge.reward_amount" class="mb-2">
                            <div class="text-4xl font-black text-green-400">{{ challenge.reward_currency }} {{ challenge.reward_amount }}</div>
                            <div class="text-sm text-gray-500">Reward</div>
                        </div>
                        <div v-else-if="challenge.reward_description" class="mb-2">
                            <div class="text-lg font-semibold text-blue-400">{{ challenge.reward_description }}</div>
                            <div class="text-sm text-gray-500">Reward</div>
                        </div>
                    </div>
                </div>

                <!-- Challenge Details Grid -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <!-- Map with thumbnail -->
                    <div class="bg-black/40 border border-white/10 rounded-xl p-4">
                        <div class="text-sm text-gray-400 mb-1">Map</div>
                        <Link :href="`/maps/${challenge.mapname}`" class="block hover:opacity-80 transition-opacity">
                            <div v-if="map?.thumbnail" class="mb-2">
                                <img :src="`/storage/${map.thumbnail}`" @error="$event.target.style.display='none'" class="w-full h-16 object-cover rounded" />
                            </div>
                            <span class="text-white font-bold hover:text-blue-400 transition-colors">{{ challenge.mapname }}</span>
                        </Link>
                    </div>

                    <div class="bg-black/40 border border-white/10 rounded-xl p-4">
                        <div class="text-sm text-gray-400 mb-1">Physics & Mode</div>
                        <div class="flex items-center gap-2">
                            <img :src="`/images/modes/${challenge.physics}-icon.svg`" class="w-5 h-5" :alt="challenge.physics" />
                            <span class="text-white font-bold capitalize">{{ challenge.mode }}</span>
                        </div>
                    </div>

                    <div class="bg-black/40 border border-white/10 rounded-xl p-4">
                        <div class="text-sm text-gray-400 mb-1">Target Time</div>
                        <div class="text-yellow-400 font-black text-xl">{{ formatTime(challenge.target_time) }}</div>
                    </div>

                    <div class="bg-black/40 border border-white/10 rounded-xl p-4">
                        <div class="text-sm text-gray-400 mb-1">Participants</div>
                        <div class="text-white font-bold text-xl">{{ allParticipants.length }}</div>
                    </div>

                    <!-- Expiration countdown -->
                    <div class="bg-black/40 border border-white/10 rounded-xl p-4">
                        <div class="text-sm text-gray-400 mb-1">Expires</div>
                        <div v-if="challenge.expires_at">
                            <div v-if="countdown === 'Expired'" class="text-red-400 font-bold">Expired</div>
                            <div v-else class="text-orange-400 font-bold">{{ countdown }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ new Date(challenge.expires_at).toLocaleDateString() }}</div>
                        </div>
                        <div v-else class="text-gray-500 font-semibold">No expiry</div>
                    </div>
                </div>

                <!-- Creator info + stats -->
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        <span>Created by</span>
                        <Link
                            v-if="challenge.creator"
                            :href="route('profile.index', challenge.creator.id)"
                            class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/creator"
                        >
                            <div class="overflow-visible flex-shrink-0">
                                <div :class="'avatar-effect-' + (challenge.creator?.avatar_effect || 'none')" :style="`--effect-color: ${challenge.creator?.color || '#ffffff'}; --border-color: ${challenge.creator?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                    <img
                                        :src="challenge.creator?.profile_photo_path ? '/storage/' + challenge.creator?.profile_photo_path : '/images/null.jpg'"
                                        class="h-5 w-5 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                        :style="`border-color: ${challenge.creator?.avatar_border_color || '#6b7280'}`"
                                        :alt="challenge.creator?.name"
                                    />
                                </div>
                            </div>
                            <span
                                :class="'name-effect-' + (challenge.creator?.name_effect || 'none')"
                                :style="`--effect-color: ${challenge.creator?.color || '#ffffff'}`"
                                class="text-sm font-semibold text-gray-300 group-hover/creator:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                v-html="q3tohtml(challenge.creator?.name)"
                            ></span>
                        </Link>
                        <span v-else class="text-gray-500">Unknown</span>

                        <!-- Creator reputation badges -->
                        <div v-if="creatorStats" class="flex items-center gap-2 ml-2">
                            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-blue-500/15 text-blue-400 border border-blue-500/20" :title="`${creatorStats.challenges_created} challenges created`">
                                {{ creatorStats.challenges_created }} created
                            </span>
                            <span v-if="creatorStats.total_approved > 0" class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-green-500/15 text-green-400 border border-green-500/20" :title="`${creatorStats.total_approved} submissions approved`">
                                {{ creatorStats.total_approved }} approved
                            </span>
                            <span v-if="creatorStats.disputes_count > 0" class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-red-500/15 text-red-400 border border-red-500/20" :title="`${creatorStats.disputes_count} disputes filed`">
                                {{ creatorStats.disputes_count }} disputes
                            </span>
                        </div>
                    </div>

                    <!-- Grace period notice -->
                    <div v-if="isWithinGracePeriod" class="text-sm text-blue-400 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Grace period active - you can still edit
                    </div>
                </div>

                <!-- Login prompt for guests -->
                <div v-if="!isAuthenticated" class="mt-6 pt-6 border-t border-white/10">
                    <Link
                        :href="route('login')"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-gray-700/50 border border-white/10 text-gray-400 font-bold rounded-lg transition-all duration-300 hover:bg-gray-600/50 hover:text-gray-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        Log in to participate in this challenge
                    </Link>
                </div>

                <!-- Authenticated User Actions -->
                <div v-else class="mt-6 pt-6 border-t border-white/10 space-y-4">
                    <!-- Creator Management Actions -->
                    <div v-if="isCreator" class="flex flex-wrap gap-3">
                        <button
                            v-if="isWithinGracePeriod && challenge.status !== 'closed'"
                            @click="showEditModal = true"
                            class="px-4 py-2 bg-blue-600/20 border border-blue-500/30 text-blue-400 font-semibold rounded-lg hover:bg-blue-600/30 transition-colors flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                            Edit Challenge
                        </button>
                        <button
                            v-if="!isWithinGracePeriod && challenge.status !== 'closed'"
                            @click="showRequestEditModal = true"
                            class="px-4 py-2 bg-gray-600/20 border border-gray-500/30 text-gray-400 font-semibold rounded-lg hover:bg-gray-600/30 transition-colors flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                            Request Edit / Deletion
                        </button>
                        <button
                            v-if="challenge.status !== 'closed'"
                            @click="showCloseConfirm = true"
                            class="px-4 py-2 bg-red-600/20 border border-red-500/30 text-red-400 font-semibold rounded-lg hover:bg-red-600/30 transition-colors flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            Close Challenge
                        </button>
                    </div>

                    <!-- Participate / Submit Actions (for everyone including creator) -->
                    <div v-if="!userParticipation && challenge.status === 'open'">
                        <button
                            @click="participate"
                            class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                        >
                            Participate in Challenge
                        </button>
                        <div v-if="isWithinGracePeriod" class="mt-2 text-xs text-yellow-400 text-center flex items-center justify-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Grace period active - creator can still edit this challenge (created {{ new Date(challenge.created_at).toLocaleTimeString() }})
                        </div>
                    </div>

                    <div v-else-if="userParticipation">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span class="text-white font-semibold">Your Status:</span>
                                <span :class="`px-3 py-1 text-sm font-semibold rounded-full border ${getStatusColor(userParticipation.status)}`">
                                    {{ getParticipantStatusLabel(userParticipation.status) }}
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    v-if="userParticipation.status === 'participating'"
                                    @click="showSubmitModal = true"
                                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl"
                                >
                                    Submit Proof
                                </button>

                                <!-- Dispute button for approved participants -->
                                <button
                                    v-if="userParticipation.status === 'approved' && !hasActiveDispute"
                                    @click="showDisputeModal = true"
                                    class="px-4 py-2 bg-red-600/20 border border-red-500/30 text-red-400 font-semibold rounded-lg hover:bg-red-600/30 transition-colors"
                                >
                                    File Dispute
                                </button>
                                <span v-if="hasActiveDispute" class="px-4 py-2 text-sm text-yellow-400 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                                    Dispute pending
                                </span>
                            </div>
                        </div>

                        <!-- Progress indicator -->
                        <div v-if="userProgress && userParticipation.status === 'participating'" class="mt-4 p-4 bg-black/30 border border-white/5 rounded-lg">
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-gray-400">Your best time: <span class="text-white font-bold">{{ formatTime(userProgress.bestTime) }}</span></span>
                                <span class="text-gray-400">Target: <span class="text-yellow-400 font-bold">{{ formatTime(challenge.target_time) }}</span></span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full transition-all duration-500"
                                    :class="userProgress.beatTarget ? 'bg-green-500' : 'bg-blue-500'"
                                    :style="`width: ${userProgress.percentage}%`"
                                ></div>
                            </div>
                            <div v-if="userProgress.beatTarget" class="text-green-400 text-sm mt-1 font-semibold">
                                You beat the target! Submit your proof above.
                            </div>
                        </div>

                        <div v-if="userParticipation.rejection_reason" class="mt-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                            <div class="text-sm font-semibold text-red-400 mb-1">Rejection Reason:</div>
                            <div class="text-sm text-gray-300">{{ userParticipation.rejection_reason }}</div>
                            <button
                                v-if="userParticipation.status === 'rejected'"
                                @click="showSubmitModal = true"
                                class="mt-3 px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg transition-all duration-300 text-sm"
                            >
                                Resubmit Proof
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Creator: Pending Submissions to Review -->
            <div v-if="isCreator && submittedParticipants.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-yellow-500/30 rounded-2xl p-8 mb-6">
                <h2 class="text-2xl font-black text-white mb-2">Submissions Awaiting Review</h2>
                <p class="text-gray-400 text-sm mb-6">Review and approve or reject submitted proofs.</p>

                <div class="space-y-4">
                    <div
                        v-for="participant in submittedParticipants"
                        :key="participant.id"
                        class="bg-black/40 border border-white/10 rounded-xl p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <Link
                                    :href="route('profile.index', participant.user.id)"
                                    class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/participant"
                                >
                                    <div class="overflow-visible flex-shrink-0">
                                        <div :class="'avatar-effect-' + (participant.user?.avatar_effect || 'none')" :style="`--effect-color: ${participant.user?.color || '#ffffff'}; --border-color: ${participant.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                            <img
                                                :src="participant.user?.profile_photo_path ? '/storage/' + participant.user?.profile_photo_path : '/images/null.jpg'"
                                                class="h-6 w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                                :style="`border-color: ${participant.user?.avatar_border_color || '#6b7280'}`"
                                                :alt="participant.user?.name"
                                            />
                                        </div>
                                    </div>
                                    <span
                                        :class="'name-effect-' + (participant.user?.name_effect || 'none')"
                                        :style="`--effect-color: ${participant.user?.color || '#ffffff'}`"
                                        class="text-sm font-bold text-white group-hover/participant:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                        v-html="q3tohtml(participant.user?.name)"
                                    ></span>
                                </Link>

                                <div class="flex items-center gap-2 text-sm text-gray-400">
                                    <template v-if="participant.record">
                                        Time: <span class="text-yellow-400 font-bold">{{ formatTime(participant.record.time) }}</span>
                                        <span class="px-1 py-0.5 text-[9px] font-bold rounded bg-green-500/20 text-green-400 border border-green-500/30">VERIFIED</span>
                                    </template>
                                    <template v-else-if="participant.offline_record">
                                        Time: <span class="text-yellow-400 font-bold">{{ formatTime(participant.offline_record.time_ms) }}</span>
                                        <span class="px-1 py-0.5 text-[9px] font-bold rounded bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">OFFLINE</span>
                                    </template>
                                </div>

                                <div class="text-sm text-gray-500">
                                    {{ new Date(participant.submitted_at).toLocaleDateString() }}
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    @click="openApproveConfirm(participant)"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors text-sm"
                                >
                                    Approve
                                </button>
                                <button
                                    @click="openRejectModal(participant)"
                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors text-sm"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>

                        <div v-if="participant.submission_notes" class="mt-2 text-sm text-gray-300 bg-black/20 rounded p-2">
                            <span class="text-gray-500">Notes:</span> {{ participant.submission_notes }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Records (WR) -->
            <div v-if="currentRecords && currentRecords.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8 mb-6">
                <h2 class="text-2xl font-black text-white mb-6">Current World Records</h2>
                <div class="space-y-3">
                    <div
                        v-for="record in currentRecords"
                        :key="record.id"
                        class="bg-black/40 border border-white/10 rounded-xl p-4 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <Link
                                v-if="record.user"
                                :href="route('profile.index', record.user.id)"
                                class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/wr"
                            >
                                <div class="overflow-visible flex-shrink-0">
                                    <div :class="'avatar-effect-' + (record.user?.avatar_effect || 'none')" :style="`--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                        <img
                                            :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                                            class="h-6 w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                            :style="`border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                                            :alt="record.user?.name"
                                        />
                                    </div>
                                </div>
                                <span
                                    :class="'name-effect-' + (record.user?.name_effect || 'none')"
                                    :style="`--effect-color: ${record.user?.color || '#ffffff'}`"
                                    class="text-sm font-bold text-white group-hover/wr:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                    v-html="q3tohtml(record.name)"
                                ></span>
                            </Link>
                            <span v-else class="text-sm font-bold" v-html="q3tohtml(record.name)"></span>

                            <span v-if="challenge.mode === 'any'" class="text-xs text-gray-500 capitalize">{{ record.mode }}</span>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="text-yellow-400 font-black">{{ formatTime(record.time) }}</span>
                            <span class="text-xs text-gray-500">{{ record.date_set }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Challenge Leaderboard (approved completions sorted by time) -->
            <div v-if="approvedParticipants.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8 mb-6">
                <h2 class="text-2xl font-black text-white mb-6">Leaderboard</h2>

                <div class="space-y-3">
                    <div
                        v-for="(participant, index) in approvedParticipants"
                        :key="participant.id"
                        class="bg-black/40 border border-white/10 rounded-xl p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-8 text-center">
                                    <span :class="index === 0 ? 'text-yellow-400' : index === 1 ? 'text-gray-300' : index === 2 ? 'text-amber-600' : 'text-gray-500'" class="font-black text-lg">
                                        #{{ index + 1 }}
                                    </span>
                                </div>

                                <Link
                                    :href="route('profile.index', participant.user.id)"
                                    class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/lb"
                                >
                                    <div class="overflow-visible flex-shrink-0">
                                        <div :class="'avatar-effect-' + (participant.user?.avatar_effect || 'none')" :style="`--effect-color: ${participant.user?.color || '#ffffff'}; --border-color: ${participant.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                            <img
                                                :src="participant.user?.profile_photo_path ? '/storage/' + participant.user?.profile_photo_path : '/images/null.jpg'"
                                                class="h-6 w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                                :style="`border-color: ${participant.user?.avatar_border_color || '#6b7280'}`"
                                                :alt="participant.user?.name"
                                            />
                                        </div>
                                    </div>
                                    <span
                                        :class="'name-effect-' + (participant.user?.name_effect || 'none')"
                                        :style="`--effect-color: ${participant.user?.color || '#ffffff'}`"
                                        class="text-sm font-bold text-white group-hover/lb:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                        v-html="q3tohtml(participant.user?.name)"
                                    ></span>
                                </Link>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-yellow-400 font-black text-lg">{{ formatTime(getParticipantTime(participant)) }}</span>
                                    <span v-if="participant.offline_record" class="px-1 py-0.5 text-[9px] font-bold rounded bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">OFFLINE</span>
                                </div>
                                <span class="text-xs text-gray-500">{{ new Date(participant.submitted_at).toLocaleDateString() }}</span>
                                <div v-if="isCreator && challenge.status !== 'closed'" class="flex gap-1.5">
                                    <button
                                        @click="unapproveSubmission(participant.id)"
                                        class="px-2 py-1 text-xs bg-yellow-600/20 border border-yellow-500/30 text-yellow-400 font-semibold rounded hover:bg-yellow-600/30 transition-colors"
                                        title="Revert to submitted status"
                                    >
                                        Undo
                                    </button>
                                    <button
                                        @click="openRemoveConfirm(participant)"
                                        class="px-2 py-1 text-xs bg-red-600/20 border border-red-500/30 text-red-400 font-semibold rounded hover:bg-red-600/30 transition-colors"
                                        title="Remove participant"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="participant.submission_notes" class="mt-2 ml-12 text-sm text-gray-400">
                            {{ participant.submission_notes }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Disputes (visible to creator) -->
            <div v-if="isCreator && disputes && disputes.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-red-500/30 rounded-2xl p-8 mb-6">
                <h2 class="text-2xl font-black text-white mb-2">Disputes</h2>
                <p class="text-gray-400 text-sm mb-6">Review and respond to filed disputes. Unresolved disputes will result in automatic action after 14 days.</p>

                <div class="space-y-4">
                    <div
                        v-for="dispute in disputes"
                        :key="dispute.id"
                        class="bg-black/40 border border-white/10 rounded-xl p-4"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <Link
                                    v-if="dispute.claimer"
                                    :href="route('profile.index', dispute.claimer.id)"
                                    class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/dispute"
                                >
                                    <div class="overflow-visible flex-shrink-0">
                                        <div :class="'avatar-effect-' + (dispute.claimer?.avatar_effect || 'none')" :style="`--effect-color: ${dispute.claimer?.color || '#ffffff'}; --border-color: ${dispute.claimer?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                            <img
                                                :src="dispute.claimer?.profile_photo_path ? '/storage/' + dispute.claimer?.profile_photo_path : '/images/null.jpg'"
                                                class="h-6 w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                                :style="`border-color: ${dispute.claimer?.avatar_border_color || '#6b7280'}`"
                                                :alt="dispute.claimer?.name"
                                            />
                                        </div>
                                    </div>
                                    <span
                                        :class="'name-effect-' + (dispute.claimer?.name_effect || 'none')"
                                        :style="`--effect-color: ${dispute.claimer?.color || '#ffffff'}`"
                                        class="text-sm font-bold text-white group-hover/dispute:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                        v-html="q3tohtml(dispute.claimer?.name)"
                                    ></span>
                                </Link>
                                <span class="text-xs text-gray-500">{{ new Date(dispute.created_at).toLocaleDateString() }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="{
                                    'px-2 py-0.5 text-xs font-semibold rounded-full border': true,
                                    'text-yellow-400 bg-yellow-500/20 border-yellow-500/30': dispute.status === 'pending',
                                    'text-blue-400 bg-blue-500/20 border-blue-500/30': dispute.status === 'responded',
                                    'text-green-400 bg-green-500/20 border-green-500/30': dispute.status === 'resolved',
                                    'text-red-400 bg-red-500/20 border-red-500/30': dispute.status === 'auto_banned',
                                }">
                                    {{ dispute.status === 'auto_banned' ? 'Auto-banned' : dispute.status }}
                                </span>
                                <span v-if="dispute.status === 'pending' && dispute.days_until_auto_ban !== null" class="text-xs text-red-400">
                                    {{ dispute.days_until_auto_ban }} days left
                                </span>
                            </div>
                        </div>

                        <div class="text-sm text-gray-300 mb-2">
                            <span class="text-gray-500">Reason:</span> {{ dispute.reason }}
                        </div>
                        <div v-if="dispute.evidence" class="text-sm text-gray-300 mb-2">
                            <span class="text-gray-500">Evidence:</span> {{ dispute.evidence }}
                        </div>

                        <!-- Creator's response -->
                        <div v-if="dispute.creator_response" class="mt-3 p-3 bg-blue-500/10 border border-blue-500/20 rounded-lg text-sm">
                            <div class="text-blue-400 font-semibold mb-1">Your response:</div>
                            <div class="text-gray-300">{{ dispute.creator_response }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ new Date(dispute.creator_responded_at).toLocaleDateString() }}</div>
                        </div>

                        <!-- Respond button (only for pending disputes without response) -->
                        <div v-if="dispute.status === 'pending' && !dispute.creator_response" class="mt-3">
                            <button
                                @click="openDisputeResponse(dispute)"
                                class="px-4 py-2 bg-blue-600/20 border border-blue-500/30 text-blue-400 font-semibold rounded-lg hover:bg-blue-600/30 transition-colors text-sm"
                            >
                                Respond to Dispute
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Participants -->
            <div v-if="allParticipants.length > 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8 mb-6">
                <h2 class="text-2xl font-black text-white mb-6">Participants</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="participant in allParticipants"
                        :key="participant.id"
                        class="bg-black/40 border border-white/10 rounded-xl p-3 flex items-center justify-between"
                    >
                        <Link
                            :href="route('profile.index', participant.user.id)"
                            class="flex items-center gap-1.5 hover:bg-white/5 px-2 py-1 -mx-2 -my-1 rounded transition-colors group/part"
                        >
                            <div class="overflow-visible flex-shrink-0">
                                <div :class="'avatar-effect-' + (participant.user?.avatar_effect || 'none')" :style="`--effect-color: ${participant.user?.color || '#ffffff'}; --border-color: ${participant.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 12px`">
                                    <img
                                        :src="participant.user?.profile_photo_path ? '/storage/' + participant.user?.profile_photo_path : '/images/null.jpg'"
                                        class="h-5 w-5 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                                        :style="`border-color: ${participant.user?.avatar_border_color || '#6b7280'}`"
                                        :alt="participant.user?.name"
                                    />
                                </div>
                            </div>
                            <span
                                :class="'name-effect-' + (participant.user?.name_effect || 'none')"
                                :style="`--effect-color: ${participant.user?.color || '#ffffff'}`"
                                class="text-xs font-bold text-white group-hover/part:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]"
                                v-html="q3tohtml(participant.user?.name)"
                            ></span>
                        </Link>

                        <div class="flex items-center gap-2">
                            <span :class="`px-2 py-0.5 text-xs font-semibold rounded-full border ${getStatusColor(participant.status)}`">
                                {{ getParticipantStatusLabel(participant.status) }}
                            </span>
                            <button
                                v-if="isCreator && challenge.status !== 'closed'"
                                @click="openRemoveConfirm(participant)"
                                class="p-1 text-red-400/50 hover:text-red-400 transition-colors"
                                title="Remove participant"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Proof Modal (with demo dropdown) -->
        <div v-if="showSubmitModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" aria-hidden="true" @click="showSubmitModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-4">Submit Proof</h3>

                        <form @submit.prevent="submitProof" class="space-y-4">
                            <!-- Demo selection dropdown -->
                            <div v-if="userDemos && userDemos.length > 0">
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Select your record *</label>
                                <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                    <label
                                        v-for="demo in userDemos"
                                        :key="`${demo.source}-${demo.id}`"
                                        @click="selectDemo(demo)"
                                        :class="[
                                            'flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all',
                                            selectedDemo?.id === demo.id && selectedDemo?.source === demo.source
                                                ? 'bg-green-600/20 border-green-500/50'
                                                : 'bg-black/40 border-white/10 hover:border-white/20'
                                        ]"
                                    >
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="radio"
                                                :checked="selectedDemo?.id === demo.id && selectedDemo?.source === demo.source"
                                                class="text-green-500 focus:ring-green-500/50 bg-black/40 border-white/20"
                                            />
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-white font-bold">{{ formatTime(demo.time) }}</span>
                                                    <span v-if="demo.source === 'online'" class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-green-500/20 text-green-400 border border-green-500/30">VERIFIED</span>
                                                    <span v-else class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">OFFLINE</span>
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    <span class="capitalize">{{ demo.mode }}</span>
                                                    <span v-if="demo.rank === 1" class="ml-1 text-yellow-400 font-semibold">WR</span>
                                                    <span class="ml-2">{{ demo.date_set }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ demo.source === 'online' ? '' : 'Offline ' }}#{{ demo.id }}</div>
                                    </label>
                                </div>

                                <!-- Offline warning -->
                                <div v-if="selectedDemo?.source === 'offline'" class="mt-2 bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 text-xs text-yellow-300">
                                    Offline records are not server-verified. The challenge creator will need to manually verify your submission.
                                </div>

                                <div v-if="submitForm.errors.record_id" class="text-red-400 text-sm mt-1">{{ submitForm.errors.record_id }}</div>
                                <div v-if="submitForm.errors.offline_record_id" class="text-red-400 text-sm mt-1">{{ submitForm.errors.offline_record_id }}</div>
                            </div>

                            <!-- No eligible demos message -->
                            <div v-else class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                                <div class="text-sm text-red-300">
                                    <p class="font-semibold mb-1">No eligible records found</p>
                                    <p>You don't have any records on <strong>{{ challenge.mapname }}</strong> ({{ challenge.physics.toUpperCase() }} {{ challenge.mode }}) that beat the target time of <strong>{{ formatTime(challenge.target_time) }}</strong>.</p>
                                    <p class="mt-2">Upload a demo first, then come back to submit proof.</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Notes (optional)</label>
                                <textarea
                                    v-model="submitForm.submission_notes"
                                    rows="3"
                                    placeholder="Any additional notes about your submission..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                ></textarea>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button
                                    type="submit"
                                    :disabled="submitForm.processing || !selectedDemo"
                                    class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    {{ submitForm.processing ? 'Submitting...' : 'Submit Proof' }}
                                </button>
                                <button
                                    type="button"
                                    @click="showSubmitModal = false"
                                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showRejectModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-4">Reject Submission</h3>
                        <p class="text-gray-400 mb-4" v-if="rejectingParticipant">
                            Rejecting submission from <span class="text-white font-semibold" v-html="q3tohtml(rejectingParticipant.user?.name)"></span>
                        </p>

                        <form @submit.prevent="rejectSubmission" class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Reason for rejection *</label>
                                <textarea
                                    v-model="rejectForm.rejection_reason"
                                    rows="3"
                                    required
                                    placeholder="Explain why you're rejecting this submission..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-red-500/50 focus:ring-1 focus:ring-red-500/50"
                                ></textarea>
                                <div v-if="rejectForm.errors.rejection_reason" class="text-red-400 text-sm mt-1">{{ rejectForm.errors.rejection_reason }}</div>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button
                                    type="submit"
                                    :disabled="rejectForm.processing"
                                    class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    {{ rejectForm.processing ? 'Rejecting...' : 'Reject Submission' }}
                                </button>
                                <button
                                    type="button"
                                    @click="showRejectModal = false"
                                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute Modal -->
        <div v-if="showDisputeModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showDisputeModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">File a Dispute</h3>
                        <p class="text-gray-400 mb-4">If the challenge creator has not fulfilled the reward, you can file a dispute. The creator has 14 days to respond before automatic action is taken.</p>

                        <form @submit.prevent="submitDispute" class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Reason *</label>
                                <textarea
                                    v-model="disputeForm.reason"
                                    rows="3"
                                    required
                                    placeholder="Describe why you're filing this dispute..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-red-500/50 focus:ring-1 focus:ring-red-500/50"
                                ></textarea>
                                <div v-if="disputeForm.errors.reason" class="text-red-400 text-sm mt-1">{{ disputeForm.errors.reason }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Evidence (optional)</label>
                                <textarea
                                    v-model="disputeForm.evidence"
                                    rows="2"
                                    placeholder="Links to screenshots, conversations, etc."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                ></textarea>
                            </div>

                            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 text-sm text-yellow-300">
                                Filing a false dispute may result in account penalties. Only file a dispute if the creator has genuinely not fulfilled the reward.
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button
                                    type="submit"
                                    :disabled="disputeForm.processing"
                                    class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    {{ disputeForm.processing ? 'Filing...' : 'File Dispute' }}
                                </button>
                                <button
                                    type="button"
                                    @click="showDisputeModal = false"
                                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal (grace period) -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showEditModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-4">Edit Challenge</h3>
                        <p class="text-sm text-blue-400 mb-4">You are within the 1-hour grace period. After this period, you will need to request edits from an administrator.</p>

                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Title *</label>
                                <input v-model="editForm.title" type="text" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50" />
                                <div v-if="editForm.errors.title" class="text-red-400 text-sm mt-1">{{ editForm.errors.title }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Description *</label>
                                <textarea v-model="editForm.description" rows="3" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"></textarea>
                                <div v-if="editForm.errors.description" class="text-red-400 text-sm mt-1">{{ editForm.errors.description }}</div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Target Time *</label>
                                    <input v-model="editForm.target_time" type="text" required placeholder="M:SS.mmm" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50" />
                                    <div v-if="editForm.errors.target_time" class="text-red-400 text-sm mt-1">{{ editForm.errors.target_time }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Reward Amount</label>
                                    <input v-model="editForm.reward_amount" type="number" step="0.01" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50" />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Currency</label>
                                    <input v-model="editForm.reward_currency" type="text" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Reward Description</label>
                                <input v-model="editForm.reward_description" type="text" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Expiration Date</label>
                                <input v-model="editForm.expires_at" type="datetime-local" @click="$event.target.showPicker?.()" style="color-scheme: dark" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 cursor-pointer [&::-webkit-calendar-picker-indicator]:invert [&::-webkit-calendar-picker-indicator]:cursor-pointer" />
                                <p class="text-xs text-gray-500 mt-1">Leave empty for no expiration</p>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button type="submit" :disabled="editForm.processing" class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold rounded-lg transition-all duration-300">
                                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                                <button type="button" @click="showEditModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Edit Modal -->
        <div v-if="showRequestEditModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showRequestEditModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">Request Edit / Deletion</h3>
                        <p class="text-gray-400 mb-4">The grace period for direct editing has passed. Describe what changes you need and an administrator will review your request.</p>

                        <form @submit.prevent="submitRequestEdit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">What do you want to change? *</label>
                                <textarea
                                    v-model="requestEditForm.reason"
                                    rows="4"
                                    required
                                    placeholder="Describe the changes or deletion you need..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                ></textarea>
                                <div v-if="requestEditForm.errors.reason" class="text-red-400 text-sm mt-1">{{ requestEditForm.errors.reason }}</div>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button type="submit" :disabled="requestEditForm.processing" class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold rounded-lg transition-all duration-300">
                                    {{ requestEditForm.processing ? 'Sending...' : 'Send Request' }}
                                </button>
                                <button type="button" @click="showRequestEditModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Confirmation Modal -->
        <div v-if="showApproveConfirm" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showApproveConfirm = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">Approve Submission</h3>
                        <p class="text-gray-400 mb-4">
                            Are you sure you want to approve the submission from
                            <span class="text-white font-semibold" v-if="approvingParticipant" v-html="q3tohtml(approvingParticipant.user?.name)"></span>?
                        </p>

                        <div v-if="approvingParticipant" class="bg-black/40 border border-white/10 rounded-lg p-3 mb-4 text-sm">
                            <template v-if="approvingParticipant.record">
                                Time: <span class="text-yellow-400 font-bold">{{ formatTime(approvingParticipant.record.time) }}</span>
                                <span class="ml-2 px-1 py-0.5 text-[9px] font-bold rounded bg-green-500/20 text-green-400 border border-green-500/30">VERIFIED</span>
                            </template>
                            <template v-else-if="approvingParticipant.offline_record">
                                Time: <span class="text-yellow-400 font-bold">{{ formatTime(approvingParticipant.offline_record.time_ms) }}</span>
                                <span class="ml-2 px-1 py-0.5 text-[9px] font-bold rounded bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">OFFLINE</span>
                            </template>
                        </div>

                        <div class="flex items-center gap-3">
                            <button @click="approveSubmission" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-all duration-300">
                                Yes, Approve
                            </button>
                            <button @click="showApproveConfirm = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remove Participant Confirmation Modal -->
        <div v-if="showRemoveConfirm" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showRemoveConfirm = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">Remove Participant</h3>
                        <p class="text-gray-400 mb-4">
                            Are you sure you want to remove
                            <span class="text-white font-semibold" v-if="removingParticipant" v-html="q3tohtml(removingParticipant.user?.name)"></span>
                            from this challenge? This action cannot be undone.
                        </p>

                        <div class="flex items-center gap-3">
                            <button @click="removeParticipant" class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-all duration-300">
                                Yes, Remove
                            </button>
                            <button @click="showRemoveConfirm = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute Response Modal (for creator) -->
        <div v-if="showDisputeResponseModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showDisputeResponseModal = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">Respond to Dispute</h3>
                        <p class="text-gray-400 mb-4">Provide your response. An administrator will review both sides and make a decision.</p>

                        <div v-if="respondingDispute" class="bg-black/40 border border-white/10 rounded-lg p-3 mb-4 text-sm">
                            <div class="text-gray-500 mb-1">Dispute reason:</div>
                            <div class="text-gray-300">{{ respondingDispute.reason }}</div>
                            <div v-if="respondingDispute.evidence" class="mt-2">
                                <div class="text-gray-500 mb-1">Evidence:</div>
                                <div class="text-gray-300">{{ respondingDispute.evidence }}</div>
                            </div>
                        </div>

                        <form @submit.prevent="submitDisputeResponse" class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Your response *</label>
                                <textarea
                                    v-model="disputeResponseForm.creator_response"
                                    rows="4"
                                    required
                                    placeholder="Explain your side of the situation..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                ></textarea>
                                <div v-if="disputeResponseForm.errors.creator_response" class="text-red-400 text-sm mt-1">{{ disputeResponseForm.errors.creator_response }}</div>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <button type="submit" :disabled="disputeResponseForm.processing" class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold rounded-lg transition-all duration-300">
                                    {{ disputeResponseForm.processing ? 'Sending...' : 'Submit Response' }}
                                </button>
                                <button type="button" @click="showDisputeResponseModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Close Confirmation Modal -->
        <div v-if="showCloseConfirm" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" @click="showCloseConfirm = false"></div>

                <div class="inline-block align-bottom bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-2">Close Challenge</h3>
                        <p class="text-gray-400 mb-4">Are you sure? All participants will be notified. This action cannot be undone.</p>

                        <div v-if="allParticipants.length > 0" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 mb-4 text-sm text-yellow-300">
                            This challenge has {{ allParticipants.length }} participant(s). Make sure you have fulfilled any pending rewards before closing.
                        </div>

                        <div class="flex items-center gap-3">
                            <button @click="closeChallenge" class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-all duration-300">
                                Yes, Close Challenge
                            </button>
                            <button @click="showCloseConfirm = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
