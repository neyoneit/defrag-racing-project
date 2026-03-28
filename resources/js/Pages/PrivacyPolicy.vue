<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    policy: String,
});
</script>

<template>
    <div class="py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Privacy Policy" />

        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-white mb-2">Privacy Policy</h1>
                <p class="text-gray-400">How we handle your data</p>
            </div>

            <!-- Policy Content Card -->
            <div class="bg-black/40 backdrop-blur-sm rounded-2xl p-8 lg:p-12 shadow-2xl border border-white/10 mb-8">
                <div
                    class="prose prose-invert prose-headings:text-white prose-headings:font-black prose-p:text-gray-300 prose-strong:text-white prose-a:text-blue-400 prose-a:no-underline hover:prose-a:text-blue-300 prose-ul:text-gray-300 prose-ol:text-gray-300 prose-li:text-gray-300 max-w-none"
                    v-html="policy"
                />
            </div>

            <!-- Footer Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                    :href="route('home')"
                    class="px-6 py-3 bg-black/40 backdrop-blur-sm hover:bg-black/50 border border-white/10 hover:border-white/20 text-white font-medium rounded-lg transition-all"
                >
                    ← Back to Home
                </Link>
            </div>
        </div>
    </div>
</template>
