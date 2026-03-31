<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { getCurrentInstance } from 'vue';
import Pagination from '@/Components/Basic/Pagination.vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    revisions: Object,
    isStaff: Boolean,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const relativeTime = (date) => {
    const now = new Date();
    const d = new Date(date);
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return formatDate(date);
};
</script>

<template>
    <div class="pb-4">
        <Head title="Recent Changes - Wiki" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <span class="text-gray-400">Recent Changes</span>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">Recent Changes</h1>
                        <p class="text-gray-500 mt-1">All edits across the wiki</p>
                    </div>
                    <Link
                        :href="route('wiki.index')"
                        class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition"
                    >
                        Back to Wiki
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
                <div v-if="revisions.data && revisions.data.length > 0">
                    <div
                        v-for="revision in revisions.data"
                        :key="revision.id"
                        class="flex items-start gap-4 px-6 py-4 border-b border-gray-700/30 hover:bg-gray-700/20 transition group"
                    >
                        <!-- Timeline dot -->
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2.5 h-2.5 bg-blue-500/60 rounded-full group-hover:bg-blue-400 transition"></div>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Page title -->
                                <Link
                                    v-if="revision.page"
                                    :href="route('wiki.show', revision.page.slug)"
                                    class="text-sm font-semibold text-gray-200 hover:text-blue-400 transition"
                                >
                                    {{ revision.page.title }}
                                </Link>
                                <span v-else class="text-sm font-semibold text-gray-500 italic">deleted page</span>

                                <!-- Separator -->
                                <span class="text-gray-700">-</span>

                                <!-- User -->
                                <Link
                                    v-if="revision.user"
                                    :href="'/profile/' + revision.user.id"
                                    class="text-sm text-gray-400 hover:text-blue-400 transition"
                                    v-html="q3tohtml(revision.user.name || revision.user.username)"
                                ></Link>
                                <span v-else class="text-sm text-gray-500">System</span>

                                <!-- Time -->
                                <span class="text-xs text-gray-600" :title="formatDate(revision.created_at)">
                                    {{ relativeTime(revision.created_at) }}
                                </span>
                            </div>

                            <!-- Summary -->
                            <p v-if="revision.summary" class="text-sm text-gray-500 mt-0.5">{{ revision.summary }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Link
                                v-if="revision.page"
                                :href="route('wiki.revision', { slug: revision.page.slug, revision: revision.id }) + '?diff=1'"
                                class="px-3 py-1.5 text-xs bg-purple-600/40 hover:bg-purple-600/70 text-purple-300 rounded-lg transition flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Diff
                            </Link>
                            <Link
                                v-if="revision.page"
                                :href="route('wiki.revision', { slug: revision.page.slug, revision: revision.id })"
                                class="px-3 py-1.5 text-xs bg-gray-700/60 hover:bg-gray-700 text-gray-300 rounded-lg transition"
                            >
                                View
                            </Link>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-16 text-gray-500">
                    No changes recorded yet.
                </div>
            </div>

            <div v-if="revisions.data && revisions.last_page > 1" class="mt-4">
                <Pagination :links="revisions.links" />
            </div>
        </div>
    </div>
</template>
