<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <div>
        <Head title="Forgot Password" />

        <!-- Header Section with Gradient Shadow -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-black text-white mb-2">Forgot Password</h1>
                    <p class="text-gray-400">We'll send you a reset link</p>
                </div>
            </div>
        </div>

        <div class="flex justify-center px-4 sm:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="w-full max-w-md">

            <!-- Card -->
            <div class="bg-black/40 rounded-2xl p-8 shadow-2xl border border-white/10">
                <p class="text-sm text-gray-400 mb-6">
                    Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
                </p>

                <!-- Status Message -->
                <div v-if="status" class="mb-6 p-4 rounded-lg bg-green-500/10 border border-green-500/30">
                    <p class="text-sm font-medium text-green-400">{{ status }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-300 mb-2">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Enter your email address"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-600/50 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] disabled:cursor-not-allowed disabled:transform-none shadow-lg shadow-blue-500/20"
                    >
                        <span v-if="!form.processing">Email Password Reset Link</span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sending...
                        </span>
                    </button>
                </form>
            </div>

                <!-- Footer Links -->
                <div class="mt-8 text-center">
                    <Link :href="route('login')" class="text-sm text-gray-400 hover:text-white transition">
                        ← Back to Login
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
