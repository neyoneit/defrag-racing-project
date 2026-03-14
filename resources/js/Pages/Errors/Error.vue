<template>
    <div class="min-h-screen flex items-center justify-center px-4 main-background">
        <div class="max-w-md w-full">
            <!-- Error Card -->
            <div class="backdrop-blur-xl bg-black/80 rounded-xl shadow-2xl p-8 border border-white/10 text-center">
                <!-- Error Icon -->
                <div class="mb-6">
                    <div class="mx-auto w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>

                <!-- Error Code -->
                <h1 class="text-6xl font-bold text-white mb-2">{{ status }}</h1>

                <!-- Error Title -->
                <h2 class="text-xl font-semibold text-white mb-4">{{ title }}</h2>

                <!-- Error Message -->
                <p class="text-gray-400 mb-8">
                    {{ description }}
                </p>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button
                        @click="goBack"
                        class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors font-semibold"
                    >
                        Go Back
                    </button>
                    <a
                        href="/"
                        class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors font-semibold"
                    >
                        Go to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    layout: null, // Don't use any layout for error pages
};
</script>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
});

const title = computed(() => {
    return {
        503: 'Service Unavailable',
        500: 'Server Error',
        404: 'Page Not Found',
        403: 'Forbidden',
    }[props.status] || 'Error';
});

const description = computed(() => {
    return {
        503: 'Sorry, we are doing some maintenance. Please check back soon.',
        500: 'Whoops, something went wrong on our servers.',
        404: 'Sorry, the page you are looking for could not be found.',
        403: 'Sorry, you do not have permission to access this page.',
    }[props.status] || 'An error occurred.';
});

const goBack = () => {
    window.history.back();
};
</script>
