<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { getCurrentInstance } from 'vue';
import Pagination from '@/Components/Basic/Pagination.vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const deleteRevision = (revisionId) => {
    if (!confirm('Delete this revision permanently?')) return;
    router.delete(route('wiki.deleteRevision', { slug: props.page.slug, revision: revisionId }));
};

const props = defineProps({
    page: Object,
    revisions: Object,
    isStaff: Boolean,
    isAdmin: Boolean,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <div class="pb-4">
        <Head :title="page.title + ' - History - Wiki'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <Link :href="route('wiki.show', page.slug)" class="hover:text-gray-300 transition">{{ page.title }}</Link>
                    <span>/</span>
                    <span class="text-gray-400">History</span>
                </div>
                <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">History: {{ page.title }}</h1>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
                <div v-if="revisions.data && revisions.data.length > 0">
                    <div
                        v-for="(revision, index) in revisions.data"
                        :key="revision.id"
                        class="flex items-center gap-4 px-6 py-4 border-b border-gray-700/30 hover:bg-gray-700/20 transition"
                    >
                        <div class="flex-shrink-0 w-8 h-8 bg-gray-700/50 rounded-full flex items-center justify-center">
                            <span class="text-xs text-gray-400">#{{ revision.id }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <Link v-if="revision.user" :href="'/profile/' + revision.user.id" class="text-sm text-gray-300 font-medium hover:text-blue-400 transition" v-html="q3tohtml(revision.user.name || revision.user.username)"></Link>
                                <span v-else class="text-sm text-gray-300 font-medium">System</span>
                                <span class="text-xs text-gray-500">{{ formatDate(revision.created_at) }}</span>
                            </div>
                            <p v-if="revision.summary" class="text-sm text-gray-500 truncate mt-0.5">{{ revision.summary }}</p>
                            <p v-else class="text-sm text-gray-600 italic mt-0.5">No summary</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Link
                                :href="route('wiki.revision', { slug: page.slug, revision: revision.id }) + '?diff=1'"
                                class="px-3 py-1.5 text-xs bg-purple-600/40 hover:bg-purple-600/70 text-purple-300 rounded-lg transition flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Diff
                            </Link>
                            <Link
                                :href="route('wiki.revision', { slug: page.slug, revision: revision.id })"
                                class="px-3 py-1.5 text-xs bg-gray-700/60 hover:bg-gray-700 text-gray-300 rounded-lg transition"
                            >
                                View
                            </Link>
                            <Link
                                v-if="isStaff"
                                :href="route('wiki.revert', { slug: page.slug, revision: revision.id })"
                                method="post"
                                as="button"
                                class="px-3 py-1.5 text-xs bg-yellow-600/60 hover:bg-yellow-600 text-white rounded-lg transition"
                            >
                                Revert
                            </Link>
                            <button
                                v-if="isAdmin"
                                @click="deleteRevision(revision.id)"
                                class="px-3 py-1.5 text-xs bg-red-600/60 hover:bg-red-600 text-white rounded-lg transition"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-16 text-gray-500">
                    No revisions yet. This page hasn't been edited.
                </div>
            </div>

            <div v-if="revisions.data && revisions.last_page > 1" class="mt-4">
                <Pagination :links="revisions.links" />
            </div>
        </div>
    </div>
</template>
