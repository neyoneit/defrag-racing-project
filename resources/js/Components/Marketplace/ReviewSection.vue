<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    listingId: Number,
    reviews: Array,
    canReview: Boolean,
    userReview: Object,
});

const rating = ref(0);
const hoverRating = ref(0);
const comment = ref('');
const submitting = ref(false);

const submitReview = () => {
    if (rating.value < 1 || rating.value > 5) return;
    submitting.value = true;

    router.post(route('marketplace.review', props.listingId), {
        rating: rating.value,
        comment: comment.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
};

const timeAgo = (date) => {
    const now = new Date();
    const d = new Date(date);
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return d.toLocaleDateString();
};
</script>

<template>
    <div>
        <!-- Submit Review Form -->
        <div v-if="canReview" class="bg-black/20 border border-white/10 rounded-xl p-4 mb-4">
            <h4 class="text-sm font-bold text-white mb-3">Leave a Review</h4>

            <!-- Star Rating -->
            <div class="flex items-center gap-1 mb-3">
                <button
                    v-for="i in 5"
                    :key="i"
                    @click="rating = i"
                    @mouseenter="hoverRating = i"
                    @mouseleave="hoverRating = 0"
                    class="focus:outline-none transition-transform hover:scale-110"
                >
                    <svg
                        class="w-7 h-7 transition-colors"
                        :class="i <= (hoverRating || rating) ? 'text-yellow-400' : 'text-gray-600'"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </button>
                <span v-if="rating > 0" class="ml-2 text-sm text-gray-400">{{ rating }}/5</span>
            </div>

            <!-- Comment -->
            <textarea
                v-model="comment"
                placeholder="Write your review (optional)..."
                rows="3"
                class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 resize-none"
                maxlength="2000"
            ></textarea>

            <button
                @click="submitReview"
                :disabled="rating < 1 || submitting"
                class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:text-gray-500 text-white text-sm font-semibold rounded-lg transition"
            >
                {{ submitting ? 'Submitting...' : 'Submit Review' }}
            </button>
        </div>

        <!-- Your existing review -->
        <div v-else-if="userReview" class="bg-blue-500/5 border border-blue-500/20 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold text-blue-400">Your Review</span>
                <div class="flex items-center gap-0.5">
                    <svg v-for="i in 5" :key="i" class="w-3.5 h-3.5" :class="i <= userReview.rating ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </div>
            </div>
            <p v-if="userReview.comment" class="text-sm text-gray-300">{{ userReview.comment }}</p>
        </div>

        <!-- Reviews List -->
        <div v-if="reviews && reviews.length > 0" class="space-y-3">
            <h4 class="text-sm font-bold text-white">Reviews ({{ reviews.length }})</h4>
            <div
                v-for="review in reviews"
                :key="review.id"
                class="bg-black/20 border border-white/5 rounded-lg p-3"
            >
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <img
                            :src="review.reviewer?.profile_photo_path ? '/storage/' + review.reviewer.profile_photo_path : '/images/null.jpg'"
                            class="h-5 w-5 rounded-full object-cover border border-gray-600"
                        />
                        <span class="text-xs font-semibold text-gray-300" v-html="q3tohtml(review.reviewer?.name)"></span>
                        <div class="flex items-center gap-0.5">
                            <svg v-for="i in 5" :key="i" class="w-3 h-3" :class="i <= review.rating ? 'text-yellow-400' : 'text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        </div>
                    </div>
                    <span class="text-xs text-gray-500">{{ timeAgo(review.created_at) }}</span>
                </div>
                <p v-if="review.comment" class="text-sm text-gray-400 mt-1">{{ review.comment }}</p>
            </div>
        </div>

        <div v-else-if="!canReview && !userReview" class="text-sm text-gray-500 text-center py-4">
            No reviews yet
        </div>
    </div>
</template>
