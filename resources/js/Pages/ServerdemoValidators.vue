<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    application: { type: Object, default: null },
    applicantCount: { type: Number, default: 0 },
    validators: { type: Array, default: () => [] },
    voting: {
        type: Object,
        default: () => ({ open: false, canVote: false, myVotes: [], candidates: [] }),
    },
});

const votedFor = (applicationId) => (props.voting.myVotes || []).includes(applicationId);

const toggleVote = (candidate) => {
    router.post(route('serverdemo-validators.vote', candidate.id), {}, {
        preserveScroll: true,
    });
};

const page = usePage();

const user = computed(() => page.props.auth?.user);

const form = useForm({
    motivation: '',
    experience: '',
    availability: '',
    contact: '',
});

const statusLabel = {
    pending: 'Waiting to be reviewed',
    shortlisted: 'Shortlisted - goes to the vote',
    approved: 'Approved - you are a validator',
    not_selected: 'Not selected this round - apply again next time',
    rejected: 'Not accepted this time',
};

const statusClass = {
    pending: 'bg-yellow-500/15 border-yellow-400/30 text-yellow-300',
    shortlisted: 'bg-blue-500/15 border-blue-400/30 text-blue-300',
    approved: 'bg-green-500/15 border-green-400/30 text-green-300',
    not_selected: 'bg-white/5 border-white/15 text-gray-300',
    rejected: 'bg-red-500/15 border-red-400/30 text-red-300',
};

// Anything already decided against may be replaced with a fresh application;
// anything still in play may not.
const canApply = computed(() => !props.application
    || ['rejected', 'not_selected'].includes(props.application.status));

const submit = () => {
    form.post(route('serverdemo-validators.apply'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Serverdemo Validators" />

    <div class="min-h-screen py-10 px-4 md:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="mb-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/15 border border-blue-400/30 text-blue-300 text-xs font-bold uppercase tracking-wider mb-4">
                    Step one of two
                </div>
                <h1 class="text-3xl md:text-4xl font-black text-white mb-3">Serverdemo validators</h1>
                <p class="text-gray-300 text-lg leading-relaxed">
                    When a player reports a record, someone has to watch the serverdemo of that run and
                    decide what actually happened. This form is the first step to being one of those people.
                </p>
            </div>

            <!-- How you get the position -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-6">
                <h2 class="text-xl font-bold text-white mb-4">How the selection works</h2>

                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500 text-white font-black text-sm flex items-center justify-center">1</div>
                        <div>
                            <div class="font-semibold text-white">You apply here</div>
                            <p class="text-gray-400 text-sm mt-1">
                                Tell us who you are and why you want to do this. Applying costs nothing and
                                commits you to nothing.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-300 font-black text-sm flex items-center justify-center">2</div>
                        <div>
                            <div class="font-semibold text-white">The applicants vote</div>
                            <p class="text-gray-400 text-sm mt-1">
                                Everyone who applied then votes on who gets the position. The people doing this
                                work pick the people they will be working with, rather than one person deciding
                                it alone.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-500/20 border border-green-400/40 text-green-300 font-black text-sm flex items-center justify-center">3</div>
                        <div>
                            <div class="font-semibold text-white">Access is granted by hand</div>
                            <p class="text-gray-400 text-sm mt-1">
                                Whoever comes through the vote is added in the admin panel with permission for
                                serverdemo validation and nothing else.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What the job actually is -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-6">
                <h2 class="text-xl font-bold text-white mb-4">What a validator does</h2>

                <ul class="space-y-3 text-gray-300 text-sm">
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span>
                            A cleared report is assigned to <span class="text-white font-semibold">one</span> validator, picked at random.
                            You watch the serverdemo of that run and say what you see.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span>
                            Not sure? <span class="text-white font-semibold">Hand it to someone else.</span> That is a normal outcome,
                            not a failure - leaving a note about what you already checked is part of the job.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span>
                            If the second person is not sure either, the report opens to
                            <span class="text-white font-semibold">every validator at once</span> and you work it out together in
                            the internal notes on that report.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span>
                            If the group cannot agree, it goes to the <span class="text-white font-semibold">admin</span> for a final call.
                        </span>
                    </li>
                </ul>
            </div>

            <!-- The guard rails -->
            <div class="bg-yellow-500/5 border border-yellow-500/25 rounded-2xl p-6 mb-6">
                <h2 class="text-xl font-bold text-yellow-300 mb-4">What you will not get</h2>

                <ul class="space-y-3 text-gray-300 text-sm">
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span>
                            <span class="text-white font-semibold">No access to the serverdemo archive.</span>
                            Not a browser, not a search, not a download link. Only the single demo belonging to a
                            report that has been handed to you.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span>
                            <span class="text-white font-semibold">Nothing reaches you automatically.</span>
                            Every report is checked by the admin first and only released if it is not a false
                            report. Nobody can flood validators with work by reporting a lot of runs.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span>
                            <span class="text-white font-semibold">A single report never moves.</span>
                            A run has to be reported independently by several different people before it is
                            looked at, on top of the admin clearing it. How many is deliberately not published.
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Who is doing it today -->
            <div v-if="validators.length > 0 || applicantCount > 0" class="flex flex-wrap gap-2 mb-8">
                <span v-if="applicantCount > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 border border-white/15 text-gray-300 text-xs font-bold">
                    {{ applicantCount }} {{ applicantCount === 1 ? 'application' : 'applications' }} so far
                </span>
                <span v-if="validators.length > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-500/10 border border-green-400/25 text-green-300 text-xs font-bold">
                    {{ validators.length }} active {{ validators.length === 1 ? 'validator' : 'validators' }}
                </span>
            </div>

            <!-- The vote -->
            <div v-if="voting.open" class="bg-blue-500/5 border border-blue-400/25 rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <h2 class="text-xl font-bold text-white">Voting is open</h2>
                </div>
                <p class="text-gray-400 text-sm mb-5">
                    {{ voting.title || 'Applicants pick who gets the position.' }}
                    Counts stay hidden until the round closes.
                </p>

                <div v-if="!user" class="text-gray-400 text-sm">
                    Log in to see the ballot.
                </div>

                <div v-else-if="!voting.canVote" class="rounded-lg bg-white/5 border border-white/10 px-4 py-3 text-gray-400 text-sm">
                    Only people who applied vote in this round.
                </div>

                <div v-else-if="voting.candidates.length === 0" class="text-gray-400 text-sm">
                    Nobody else is on the ballot yet.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="candidate in voting.candidates"
                        :key="candidate.id"
                        class="rounded-xl border p-4 transition-colors"
                        :class="votedFor(candidate.id)
                            ? 'bg-green-500/10 border-green-400/40'
                            : 'bg-black/20 border-white/10'"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-bold text-white mb-1" v-html="q3tohtml(candidate.name)"></div>
                                <p class="text-gray-400 text-sm whitespace-pre-line">{{ candidate.motivation }}</p>
                                <p v-if="candidate.experience" class="text-gray-500 text-sm mt-2 whitespace-pre-line">{{ candidate.experience }}</p>
                                <p v-if="candidate.availability" class="text-gray-600 text-xs mt-2">Around: {{ candidate.availability }}</p>
                            </div>

                            <button
                                @click="toggleVote(candidate)"
                                class="flex-shrink-0 px-4 py-2 rounded-xl font-semibold text-sm transition-colors"
                                :class="votedFor(candidate.id)
                                    ? 'bg-green-600 hover:bg-green-500 text-white'
                                    : 'bg-white/5 hover:bg-white/10 text-gray-300 border border-white/15'"
                            >
                                {{ votedFor(candidate.id) ? 'Voted' : 'Vote' }}
                            </button>
                        </div>
                    </div>

                    <p class="text-gray-500 text-xs">
                        Vote for as many people as you think should get it. Click again to take a vote back.
                    </p>
                </div>
            </div>

            <!-- Existing application -->
            <div v-if="application" class="rounded-2xl border p-5 mb-6" :class="statusClass[application.status] || statusClass.pending">
                <div class="font-bold mb-1">{{ statusLabel[application.status] || application.status }}</div>
                <p class="text-sm opacity-90">
                    You applied on {{ new Date(application.created_at).toLocaleDateString() }}.
                    <span v-if="application.review_note"> {{ application.review_note }}</span>
                </p>
            </div>

            <!-- Form -->
            <div v-if="user && canApply" class="bg-white/5 border border-white/10 rounded-2xl p-6">
                <h2 class="text-xl font-bold text-white mb-1">Apply</h2>
                <p class="text-gray-500 text-sm mb-5">Applying as <span class="font-semibold" v-html="q3tohtml(user.name)"></span>.</p>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">
                            Why do you want to do this?
                            <span class="text-red-400">*</span>
                        </label>
                        <textarea
                            v-model="form.motivation"
                            rows="5"
                            placeholder="A few sentences is plenty."
                            class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                        <p v-if="form.errors.motivation" class="text-red-400 text-sm mt-1">{{ form.errors.motivation }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">
                            Anything that makes you good at spotting a bad run?
                        </label>
                        <textarea
                            v-model="form.experience"
                            rows="4"
                            placeholder="Years played, physics you know well, moderating you have done elsewhere. Optional."
                            class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                        <p v-if="form.errors.experience" class="text-red-400 text-sm mt-1">{{ form.errors.experience }}</p>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Roughly when are you around?</label>
                            <input
                                v-model="form.availability"
                                type="text"
                                placeholder="Evenings CET, weekends, ..."
                                class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.availability" class="text-red-400 text-sm mt-1">{{ form.errors.availability }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Where can we reach you?</label>
                            <input
                                v-model="form.contact"
                                type="text"
                                placeholder="Discord handle"
                                class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.contact" class="text-red-400 text-sm mt-1">{{ form.errors.contact }}</p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.motivation.trim()"
                        class="px-5 py-3 bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:text-gray-500 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ form.processing ? 'Sending...' : 'Send application' }}
                    </button>
                </form>
            </div>

            <!-- Logged out -->
            <div v-else-if="!user" class="bg-white/5 border border-white/10 rounded-2xl p-6 text-center">
                <p class="text-gray-300 mb-4">You need an account to apply.</p>
                <Link :href="route('login')" class="inline-block px-5 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors">
                    Log in
                </Link>
            </div>
        </div>
    </div>
</template>
