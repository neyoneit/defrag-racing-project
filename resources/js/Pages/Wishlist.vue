<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    wishes: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    projects: { type: Object, default: () => ({}) },
    projectCounts: { type: Object, default: () => ({}) },
    filter: { type: String, default: null },
    projectFilter: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const showForm = ref(false);

const form = useForm({
    project: 'web',
    title: '',
    body: '',
});

const submit = () => {
    form.post('/wishlist', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

// Voting is a plain POST rather than an optimistic local update: the score
// decides the order of the list, and a number that moves before the server
// agrees is a number people stop trusting.
const vote = (wish, value) => {
    if (!user.value || wish.pending) return;
    router.post(`/wishlist/${wish.id}/vote`, { value }, { preserveScroll: true, preserveState: true });
};

const remove = (wish) => {
    if (!confirm('Remove this wish?')) return;
    router.delete(`/wishlist/${wish.id}`, { preserveScroll: true });
};

// Both filters go through the same call so picking one never silently drops
// the other.
const applyFilters = (status, project) => {
    const query = {};
    if (status) query.status = status;
    if (project) query.project = project;

    router.get('/wishlist', query, { preserveScroll: true, preserveState: true, replace: true });
};

const setFilter = (status) => applyFilters(status, props.projectFilter);
const setProject = (project) => applyFilters(props.filter, project);

const statusClass = (status) => ({
    considering: 'bg-white/5 border-white/15 text-gray-300',
    planned: 'bg-blue-500/15 border-blue-400/30 text-blue-300',
    done: 'bg-green-500/15 border-green-400/30 text-green-300',
    rejected: 'bg-red-500/15 border-red-400/30 text-red-300',
}[status] || 'bg-white/5 border-white/15 text-gray-300');

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString() : '';
</script>

<template>
    <Head title="Wishlist" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <!-- Label beside the heading, not above it: on its own line
                         it cost a whole row of the fold to say one word. -->
                    <div class="flex items-center gap-3 flex-wrap mb-3">
                        <h1 class="text-2xl md:text-3xl font-black text-white">Wishlist</h1>
                        <span class="px-3 py-1 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-300 text-xs font-bold uppercase tracking-wider">
                            Book of wishes
                        </span>
                    </div>
                    <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                        What do you want built here? Write it down, and vote on what everyone else wants.
                        The list is ordered by score, so what the community actually wants sits at the top.
                    </p>
                </div>

                <button v-if="user" @click="showForm = !showForm"
                    class="px-5 py-2.5 rounded-lg bg-purple-500 hover:bg-purple-600 text-white font-bold transition-colors">
                    {{ showForm ? 'Close' : 'Add a wish' }}
                </button>
                <Link v-else href="/login"
                    class="px-5 py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-200 font-bold transition-colors">
                    Log in to add or vote
                </Link>
            </div>

            <form v-if="showForm && user" @submit.prevent="submit"
                class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5 mb-6 space-y-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-2">Which project is it about?</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="(label, key) in projects" :key="key" type="button" @click="form.project = key"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition-colors"
                            :class="form.project === key
                                ? 'bg-purple-500/20 border-purple-400/50 text-purple-200'
                                : 'bg-black/40 border-white/10 text-gray-400 hover:text-white hover:border-white/25'">
                            {{ label }}
                        </button>
                    </div>
                    <div v-if="form.errors.project" class="text-red-400 text-sm mt-1">{{ form.errors.project }}</div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1">Title</label>
                    <input v-model="form.title" type="text" maxlength="120"
                        class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                        placeholder="One line - what do you want?" />
                    <div v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1">Description</label>
                    <textarea v-model="form.body" rows="4" maxlength="2000"
                        class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                        placeholder="Briefly: what it does, and why it would help."></textarea>
                    <div v-if="form.errors.body" class="text-red-400 text-sm mt-1">{{ form.errors.body }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="px-5 py-2.5 rounded-lg bg-purple-500 hover:bg-purple-600 text-white font-bold transition-colors disabled:opacity-50">
                        Post it
                    </button>
                    <span class="text-sm text-gray-500">
                        An admin reads it before it goes on the list. You will see it here with a
                        "waiting" mark until then.
                    </span>
                </div>
            </form>

            <!-- Project first, status second: which of our things you care
                 about narrows the list far harder than what state it is in. -->
            <div class="flex flex-wrap gap-2 mb-3">
                <button @click="setProject(null)"
                    class="px-3 py-1.5 rounded-lg text-sm font-bold border transition-colors"
                    :class="!projectFilter ? 'bg-blue-500/20 border-blue-400/40 text-blue-200' : 'bg-black/30 border-white/10 text-gray-400 hover:text-white'">
                    All projects
                </button>
                <button v-for="(label, key) in projects" :key="key" @click="setProject(key)"
                    class="px-3 py-1.5 rounded-lg text-sm font-bold border transition-colors"
                    :class="projectFilter === key ? 'bg-blue-500/20 border-blue-400/40 text-blue-200' : 'bg-black/30 border-white/10 text-gray-400 hover:text-white'">
                    {{ label }}
                    <span class="ml-1 text-xs opacity-60">{{ projectCounts[key] || 0 }}</span>
                </button>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button @click="setFilter(null)"
                    class="px-4 py-2 rounded-lg text-sm font-bold border transition-colors"
                    :class="!filter ? 'bg-purple-500/20 border-purple-400/40 text-purple-200' : 'bg-black/30 border-white/10 text-gray-400 hover:text-white'">
                    Any status
                </button>
                <button v-for="(label, key) in statuses" :key="key" @click="setFilter(key)"
                    class="px-4 py-2 rounded-lg text-sm font-bold border transition-colors"
                    :class="filter === key ? 'bg-purple-500/20 border-purple-400/40 text-purple-200' : 'bg-black/30 border-white/10 text-gray-400 hover:text-white'">
                    {{ label }}
                </button>
            </div>

            <div v-if="!wishes.length" class="bg-black/40 border border-white/10 rounded-2xl p-10 text-center text-gray-500">
                Nothing here yet. Be the first.
            </div>

            <div v-else class="space-y-3">
                <div v-for="wish in wishes" :key="wish.id"
                    class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-4 md:p-5 flex gap-4">

                    <!-- Score column. The net number is the big one because it
                         is what the list is sorted by; the split sits under it
                         so a contested wish does not read like an ignored one. -->
                    <div class="flex flex-col items-center gap-1 w-16 shrink-0" :class="wish.pending ? 'opacity-40' : ''">
                        <button @click="vote(wish, 1)" :disabled="!user || wish.pending"
                            class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors disabled:opacity-40"
                            :class="wish.my_vote === 1 ? 'bg-green-500/25 text-green-300' : 'bg-white/5 text-gray-400 hover:bg-white/10'">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l8 10h-6v6h-4v-6H4z"/></svg>
                        </button>
                        <div class="text-xl font-black" :class="wish.score > 0 ? 'text-green-300' : wish.score < 0 ? 'text-red-300' : 'text-gray-400'">
                            {{ wish.score > 0 ? '+' : '' }}{{ wish.score }}
                        </div>
                        <div class="text-[10px] text-gray-600 whitespace-nowrap">+{{ wish.upvotes }} / -{{ wish.downvotes }}</div>
                        <button @click="vote(wish, -1)" :disabled="!user || wish.pending"
                            class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors disabled:opacity-40"
                            :class="wish.my_vote === -1 ? 'bg-red-500/25 text-red-300' : 'bg-white/5 text-gray-400 hover:bg-white/10'">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20L4 10h6V4h4v6h6z"/></svg>
                        </button>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span v-if="wish.pending"
                                class="text-[11px] px-2 py-0.5 rounded border border-amber-400/40 bg-amber-500/15 text-amber-200 font-bold">
                                Waiting for approval
                            </span>
                            <button type="button" @click="setProject(wish.project)"
                                class="text-[11px] px-2 py-0.5 rounded border border-blue-400/30 bg-blue-500/15 text-blue-200 font-bold hover:bg-blue-500/25 transition-colors">
                                {{ wish.project_label }}
                            </button>
                            <h2 class="text-lg font-bold text-white">{{ wish.title }}</h2>
                            <span class="text-[11px] px-2 py-0.5 rounded border font-bold" :class="statusClass(wish.status)">
                                {{ wish.status_label }}
                            </span>
                        </div>
                        <p class="text-gray-300 whitespace-pre-line leading-relaxed">{{ wish.body }}</p>

                        <div v-if="wish.status_note" class="mt-3 border-l-2 border-purple-400/40 pl-3 text-sm text-purple-200">
                            {{ wish.status_note }}
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            <Link v-if="wish.author" :href="`/profile/${wish.author.id}`" class="hover:underline">
                                {{ wish.author.name }}
                            </Link>
                            <span v-else>deleted account</span>
                            <span>{{ fmtDate(wish.created_at) }}</span>
                            <button v-if="wish.mine" @click="remove(wish)" class="text-red-400 hover:underline">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
