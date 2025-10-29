<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    challenge: Object,
    userParticipation: Object,
});

const showSubmitModal = ref(false);

const submitForm = useForm({
    record_id: null,
    submission_notes: '',
});

const formatTime = (milliseconds) => {
    const seconds = Math.floor(milliseconds / 1000);
    const ms = milliseconds % 1000;
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;

    if (minutes > 0) {
        return `${minutes}:${String(secs).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    }
    return `${secs}.${String(ms).padStart(3, '0')}`;
};

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
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head :title="challenge.title" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pointer-events-auto">
                <!-- Back Button -->
                <Link :href="route('headhunter.index')" class="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Challenges
                </Link>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
                <!-- Challenge Header -->
                <div class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8 mb-6">
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

                    <!-- Challenge Details -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="backdrop-blur-xl bg-black/40 border border-white/10 rounded-xl p-4">
                            <div class="text-sm text-gray-400 mb-1">Map</div>
                            <Link :href="`/maps/${challenge.mapname}`" class="text-white font-bold hover:text-blue-400 transition-colors">
                                {{ challenge.mapname }}
                            </Link>
                        </div>

                        <div class="backdrop-blur-xl bg-black/40 border border-white/10 rounded-xl p-4">
                            <div class="text-sm text-gray-400 mb-1">Physics & Mode</div>
                            <div class="flex items-center gap-2">
                                <img :src="`/images/modes/${challenge.physics}-icon.svg`" class="w-5 h-5" :alt="challenge.physics" />
                                <span class="text-white font-bold capitalize">{{ challenge.mode }}</span>
                            </div>
                        </div>

                        <div class="backdrop-blur-xl bg-black/40 border border-white/10 rounded-xl p-4">
                            <div class="text-sm text-gray-400 mb-1">Target Time</div>
                            <div class="text-yellow-400 font-black text-xl">{{ formatTime(challenge.target_time) }}</div>
                        </div>

                        <div class="backdrop-blur-xl bg-black/40 border border-white/10 rounded-xl p-4">
                            <div class="text-sm text-gray-400 mb-1">Participants</div>
                            <div class="text-white font-bold text-xl">{{ challenge.participants?.length || 0 }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
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
                        </div>

                        <div v-if="challenge.expires_at" class="text-sm text-gray-400">
                            Expires: <span class="text-white">{{ new Date(challenge.expires_at).toLocaleDateString() }}</span>
                        </div>
                    </div>

                    <!-- User Actions -->
                    <div v-if="$page.props.auth.user && $page.props.auth.user.id !== challenge.creator_id" class="mt-6 pt-6 border-t border-white/10">
                        <div v-if="!userParticipation">
                            <button
                                @click="participate"
                                class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                            >
                                Participate in Challenge
                            </button>
                        </div>

                        <div v-else>
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-white font-semibold">Your Status:</span>
                                    <span :class="`px-3 py-1 text-sm font-semibold rounded-full border ${getStatusColor(userParticipation.status)}`">
                                        {{ userParticipation.status }}
                                    </span>
                                </div>

                                <button
                                    v-if="userParticipation.status === 'participating'"
                                    @click="showSubmitModal = true"
                                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl"
                                >
                                    Submit Proof
                                </button>
                            </div>

                            <div v-if="userParticipation.rejection_reason" class="mt-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                                <div class="text-sm font-semibold text-red-400 mb-1">Rejection Reason:</div>
                                <div class="text-sm text-gray-300">{{ userParticipation.rejection_reason }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approved Participants -->
                <div v-if="challenge.participants && challenge.participants.length > 0" class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
                    <h2 class="text-2xl font-black text-white mb-6">Successful Completions</h2>

                    <div class="space-y-4">
                        <div
                            v-for="participant in challenge.participants"
                            :key="participant.id"
                            class="backdrop-blur-xl bg-black/40 border border-white/10 rounded-xl p-4"
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

                                    <div v-if="participant.record" class="text-sm text-gray-400">
                                        Time: <span class="text-yellow-400 font-bold">{{ formatTime(participant.record.time) }}</span>
                                    </div>
                                </div>

                                <div class="text-sm text-gray-400">
                                    {{ new Date(participant.submitted_at).toLocaleDateString() }}
                                </div>
                            </div>

                            <div v-if="participant.submission_notes" class="mt-2 text-sm text-gray-300">
                                {{ participant.submission_notes }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Proof Modal -->
        <div v-if="showSubmitModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/75 transition-opacity" aria-hidden="true" @click="showSubmitModal = false"></div>

                <div class="inline-block align-bottom backdrop-blur-xl bg-gradient-to-br from-gray-900/95 to-gray-950/95 border border-white/10 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <h3 class="text-2xl font-black text-white mb-4">Submit Proof</h3>

                        <form @submit.prevent="submitProof" class="space-y-4">
                            <!-- Instructions -->
                            <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
                                <div class="flex gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <div class="text-sm text-blue-300">
                                        <p class="font-semibold mb-1">How to submit proof:</p>
                                        <ol class="list-decimal list-inside space-y-1">
                                            <li>First, upload your demo through the regular demo upload section</li>
                                            <li>After uploading, you'll receive a Record ID</li>
                                            <li>Enter that Record ID below to submit as proof</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Record ID *</label>
                                <input
                                    v-model="submitForm.record_id"
                                    type="number"
                                    required
                                    placeholder="Enter your record ID from uploaded demo"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <div v-if="submitForm.errors.record_id" class="text-red-400 text-sm mt-1">{{ submitForm.errors.record_id }}</div>
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
                                    :disabled="submitForm.processing"
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
</template>
