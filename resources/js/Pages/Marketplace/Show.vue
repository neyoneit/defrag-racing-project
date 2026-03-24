<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StatusBadge from '@/Components/Marketplace/StatusBadge.vue';
import ReviewSection from '@/Components/Marketplace/ReviewSection.vue';

const props = defineProps({
    listing: Object,
    canReview: Boolean,
    userReview: Object,
    ownerRating: Number,
    assignedRating: Number,
});

const processing = ref(false);

const workTypeLabels = {
    map: 'Map',
    player_model: 'Player Model',
    weapon_model: 'Weapon Model',
    shadow_model: 'Shadow Model',
};

const workTypeColors = {
    map: 'text-emerald-400 bg-emerald-500/20 border-emerald-500/30',
    player_model: 'text-purple-400 bg-purple-500/20 border-purple-500/30',
    weapon_model: 'text-orange-400 bg-orange-500/20 border-orange-500/30',
    shadow_model: 'text-cyan-400 bg-cyan-500/20 border-cyan-500/30',
};

const isOwner = $page => $page.props.auth.user?.id === props.listing.user_id;
const isAssigned = $page => $page.props.auth.user?.id === props.listing.assigned_to_user_id;

const updateStatus = (status) => {
    if (!confirm(`Are you sure you want to mark this listing as "${status}"?`)) return;
    processing.value = true;
    router.post(route('marketplace.status', props.listing.id), { status }, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
};

const assignToMe = () => {
    if (!confirm('Take this commission? The listing owner will be notified.')) return;
    processing.value = true;
    router.post(route('marketplace.assign', props.listing.id), {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const renderStars = (rating) => {
    if (!rating) return '';
    return rating;
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head :title="listing.title + ' - Marketplace'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('marketplace.index')" class="text-gray-400 hover:text-white text-sm transition mb-4 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Back to Marketplace
                </Link>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="grid grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="col-span-2 space-y-4">
                    <!-- Title & Status -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-3 flex-wrap">
                            <span :class="`px-2 py-0.5 text-xs font-semibold rounded uppercase ${listing.listing_type === 'request' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-green-500/20 text-green-400 border border-green-500/30'}`">
                                {{ listing.listing_type }}
                            </span>
                            <span :class="`px-2 py-0.5 text-xs font-semibold rounded border ${workTypeColors[listing.work_type]}`">
                                {{ workTypeLabels[listing.work_type] }}
                            </span>
                            <StatusBadge :status="listing.status" />
                        </div>

                        <h1 class="text-2xl font-black text-white mb-4">{{ listing.title }}</h1>

                        <div class="prose prose-invert max-w-none">
                            <p class="text-gray-300 whitespace-pre-wrap">{{ listing.description }}</p>
                        </div>

                        <div v-if="listing.budget" class="mt-4 flex items-center gap-2 p-3 bg-green-500/5 border border-green-500/20 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="text-green-400 font-bold">Budget: {{ listing.budget }}</span>
                        </div>

                        <div class="flex items-center gap-4 mt-4 text-sm text-gray-500">
                            <span>Posted {{ formatDate(listing.created_at) }}</span>
                            <span v-if="listing.completed_at">Completed {{ formatDate(listing.completed_at) }}</span>
                        </div>
                    </div>

                    <!-- Actions (owner) -->
                    <div v-if="$page.props.auth.user?.id === listing.user_id && listing.status !== 'completed' && listing.status !== 'cancelled'" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <h3 class="text-sm font-bold text-white mb-3">Manage Listing</h3>
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-if="listing.status === 'in_progress'"
                                @click="updateStatus('completed')"
                                :disabled="processing"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition"
                            >
                                Mark Completed
                            </button>
                            <button
                                v-if="listing.status === 'open'"
                                @click="updateStatus('in_progress')"
                                :disabled="processing"
                                class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition"
                            >
                                Mark In Progress
                            </button>
                            <button
                                @click="updateStatus('cancelled')"
                                :disabled="processing"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-gray-300 text-sm font-semibold rounded-lg transition"
                            >
                                Cancel Listing
                            </button>
                        </div>
                    </div>

                    <!-- Take Commission (for others) -->
                    <div
                        v-if="$page.props.auth.user && $page.props.auth.user.id !== listing.user_id && listing.status === 'open' && !listing.assigned_to_user_id"
                        class="bg-gradient-to-br from-blue-900/20 to-blue-950/30 border border-blue-500/20 rounded-xl p-4"
                    >
                        <button
                            @click="assignToMe"
                            :disabled="processing"
                            class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 text-white font-bold rounded-lg transition-all shadow-lg"
                        >
                            {{ processing ? 'Processing...' : 'Take This Commission' }}
                        </button>
                        <p class="text-xs text-gray-400 text-center mt-2">The listing owner will be notified</p>
                    </div>

                    <!-- Reviews -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                        <ReviewSection
                            :listing-id="listing.id"
                            :reviews="listing.reviews"
                            :can-review="canReview"
                            :user-review="userReview"
                        />
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Posted By -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Posted by</h3>
                        <Link v-if="listing.user" :href="route('profile.index', listing.user.id)" class="flex items-center gap-3 group">
                            <img
                                :src="listing.user?.profile_photo_path ? '/storage/' + listing.user.profile_photo_path : '/images/null.jpg'"
                                class="h-10 w-10 rounded-full object-cover border-2 border-gray-600 group-hover:border-blue-500 transition"
                            />
                            <div>
                                <div class="font-bold text-white text-sm group-hover:text-blue-300 transition" v-html="q3tohtml(listing.user?.name)"></div>
                                <div v-if="ownerRating" class="flex items-center gap-1 mt-0.5">
                                    <div class="flex items-center gap-0.5">
                                        <svg v-for="i in 5" :key="i" class="w-3 h-3" :class="i <= Math.round(ownerRating) ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ ownerRating }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Assigned To -->
                    <div v-if="listing.assigned_to" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Assigned to</h3>
                        <Link :href="route('profile.index', listing.assigned_to.id)" class="flex items-center gap-3 group">
                            <img
                                :src="listing.assigned_to?.profile_photo_path ? '/storage/' + listing.assigned_to.profile_photo_path : '/images/null.jpg'"
                                class="h-10 w-10 rounded-full object-cover border-2 border-gray-600 group-hover:border-blue-500 transition"
                            />
                            <div>
                                <div class="font-bold text-white text-sm group-hover:text-blue-300 transition" v-html="q3tohtml(listing.assigned_to?.name)"></div>
                                <div v-if="assignedRating" class="flex items-center gap-1 mt-0.5">
                                    <div class="flex items-center gap-0.5">
                                        <svg v-for="i in 5" :key="i" class="w-3 h-3" :class="i <= Math.round(assignedRating) ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ assignedRating }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Listing Info -->
                    <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Details</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Type</span>
                                <span class="text-white capitalize">{{ listing.listing_type }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Category</span>
                                <span class="text-white">{{ workTypeLabels[listing.work_type] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Status</span>
                                <StatusBadge :status="listing.status" />
                            </div>
                            <div v-if="listing.budget" class="flex justify-between">
                                <span class="text-gray-400">Budget</span>
                                <span class="text-green-400 font-semibold">{{ listing.budget }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
