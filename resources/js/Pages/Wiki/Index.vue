<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    pages: Array,
});
</script>

<template>
    <div class="pb-4">
        <Head title="Wiki" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Wiki</h1>
                        <p class="text-gray-400">Community knowledge base for Quake III DeFRaG</p>
                    </div>
                    <Link
                        v-if="$page.props.auth.user && ($page.props.auth.user.admin || $page.props.auth.user.is_moderator)"
                        :href="route('wiki.create')"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105"
                    >
                        New Page
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template v-for="page in pages" :key="page.id">
                    <Link
                        :href="route('wiki.show', page.slug)"
                        class="block bg-gray-800/60 border border-gray-700/50 rounded-xl p-6 hover:bg-gray-800/80 hover:border-gray-600/50 transition-all duration-300 group"
                    >
                        <h2 class="text-xl font-bold text-gray-200 group-hover:text-white mb-2">{{ page.title }}</h2>
                        <div v-if="page.children && page.children.length > 0" class="space-y-1">
                            <div
                                v-for="child in page.children"
                                :key="child.id"
                                class="text-sm text-gray-400 flex items-center gap-2"
                            >
                                <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                {{ child.title }}
                            </div>
                        </div>
                    </Link>
                </template>

                <div v-if="!pages || pages.length === 0" class="col-span-full text-center py-20">
                    <p class="text-gray-500 text-lg">No wiki pages yet.</p>
                </div>
            </div>
        </div>
    </div>
</template>
