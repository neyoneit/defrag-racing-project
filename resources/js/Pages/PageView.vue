<script setup>
    import { Head, Link } from '@inertiajs/vue3';

    const props = defineProps({
        page: Object
    });
</script>

<template>
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <Head :title="page.title" />

        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-white mb-2">{{ page.title }}</h1>
            </div>

            <!-- Content Card -->
            <div class="bg-black/40 backdrop-blur-sm rounded-2xl p-8 lg:p-12 shadow-2xl border border-white/10 mb-8">
                <div
                    class="prose prose-invert prose-headings:text-white prose-headings:font-black prose-p:text-gray-300 prose-strong:text-white prose-a:text-blue-400 prose-a:no-underline hover:prose-a:text-blue-300 prose-ul:text-gray-300 prose-ol:text-gray-300 prose-li:text-gray-300 max-w-none"
                    v-html="page.content"
                />
            </div>

            <!-- Footer -->
            <div class="flex justify-center">
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
