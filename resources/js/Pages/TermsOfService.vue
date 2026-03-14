<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    terms: String,
});
</script>

<template>
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Terms of Service" />

        <!-- Modern Terms Container -->
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-white mb-2">Terms of Service</h1>
                <p class="text-gray-400">Please read these terms carefully</p>
            </div>

            <!-- Terms Content Card -->
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl p-8 lg:p-12 shadow-2xl border border-white/10 mb-8">
                <div
                    class="prose prose-invert prose-headings:text-white prose-headings:font-black prose-p:text-gray-300 prose-strong:text-white prose-a:text-blue-400 prose-a:no-underline hover:prose-a:text-blue-300 prose-ul:text-gray-300 prose-ol:text-gray-300 prose-li:text-gray-300 max-w-none"
                    v-html="terms"
                />
            </div>

            <!-- Footer Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                    :href="route('home')"
                    class="px-6 py-3 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white font-medium rounded-lg transition-all"
                >
                    ← Back to Home
                </Link>
                <Link
                    v-if="!$page.props.auth.user"
                    :href="route('register')"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-blue-500/20"
                >
                    I Agree - Create Account
                </Link>
            </div>
        </div>
    </div>
</template>
