<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Login" />

        <!-- Modern Login Card -->
        <div class="w-full max-w-md">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-white mb-2">Welcome Back</h1>
                <p class="text-gray-400">Sign in to your account to continue</p>
            </div>

            <!-- Login Card -->
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl p-8 shadow-2xl border border-white/10">
                <!-- Status Message -->
                <div v-if="status" class="mb-6 p-4 rounded-lg bg-green-500/10 border border-green-500/30">
                    <p class="text-sm font-medium text-green-400">{{ status }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-bold text-gray-300 mb-2">Username</label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Enter your username"
                        />
                        <p v-if="form.errors.username" class="mt-2 text-sm text-red-400">{{ form.errors.username }}</p>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-bold text-gray-300 mb-2">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Enter your password"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer group">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="w-4 h-4 rounded bg-white/5 border border-white/10 text-blue-600 focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 cursor-pointer"
                            />
                            <span class="ml-2 text-sm text-gray-400 group-hover:text-white transition">Remember me</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-blue-400 hover:text-blue-300 transition"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-600/50 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] disabled:cursor-not-allowed disabled:transform-none shadow-lg shadow-blue-500/20"
                    >
                        <span v-if="!form.processing">Log In</span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Logging in...
                        </span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-black/40 text-gray-400">Don't have an account?</span>
                    </div>
                </div>

                <!-- Register Link -->
                <Link
                    :href="route('register')"
                    class="block w-full text-center py-3 px-4 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white font-medium rounded-lg transition-all"
                >
                    Create Account
                </Link>
            </div>

            <!-- Footer Links -->
            <div class="mt-8 text-center">
                <Link :href="route('home')" class="text-sm text-gray-400 hover:text-white transition">
                    ← Back to Home
                </Link>
            </div>
        </div>
    </div>
</template>
