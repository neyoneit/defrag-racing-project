<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { t } from '@/utils/i18n';

import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';
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

const statusLabel = computed(() => ({
    pending: t('Waiting to be reviewed'),
    shortlisted: t('Shortlisted - goes to the vote'),
    approved: t('Approved - you are a validator'),
    not_selected: t('Not selected this round - apply again next time'),
    rejected: t('Not accepted this time'),
}));

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

// The server wants 60 characters. Checking it here as well means a short
// answer never becomes a request - the rate limit counts every POST, so a
// rejected one used to cost the applicant an attempt for nothing.
const MOTIVATION_MIN = 60;

const motivationLength = computed(() => form.motivation.trim().length);
const motivationTooShort = computed(() => motivationLength.value < MOTIVATION_MIN);
const motivationMissing = computed(() => Math.max(0, MOTIVATION_MIN - motivationLength.value));

// The button is deliberately NOT disabled. A dead button explains nothing: the
// first person to hit this thought it was greyed out because of who he was -
// an admin - rather than because of what he had typed. Clicking it now says
// what is wrong and puts the cursor in the field that is wrong.
const motivationField = ref(null);
const blocked = ref(false);

// Inertia keeps errors until the next response, so a "too short" message sat
// under a long answer the applicant had since rewritten. It read like the
// validation was broken. Typing clears it, and the counter takes over.
watch(() => form.motivation, () => {
    form.clearErrors('motivation', 'throttle');
    blocked.value = false;
});

const submit = () => {
    // Stopping the short answer here means it never becomes a request. The
    // rate limit counts every POST, including the ones the server itself
    // rejects, so a bounced attempt used to cost the applicant one for nothing.
    if (motivationTooShort.value) {
        blocked.value = true;
        motivationField.value?.focus();
        motivationField.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    form.post(route('serverdemo-validators.apply'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="$t('Serverdemo Validators')" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <!-- Header. Prose is capped even though the page is not: a line of
                 body text running the full width of a monitor is unreadable. -->
            <div class="mb-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/15 border border-blue-400/30 text-blue-300 text-xs font-bold uppercase tracking-wider mb-4">
                    {{ $t('Step one of two') }}
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white mb-3">{{ $t('Serverdemo validators') }}</h1>
                <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                    {{ $t('When a player reports a record, someone has to watch the serverdemo of that run and decide what actually happened. This form is the first step to being one of those people.') }}
                </p>
            </div>

            <!-- The three explainers sit side by side rather than stacking, so
                 the width buys shorter lines instead of longer ones. -->
            <div class="grid gap-6 mb-6 lg:grid-cols-3">

            <!-- How you get the position -->
            <div class="h-full bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-white mb-4">{{ $t('How the selection works') }}</h2>

                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500 text-white font-black text-sm flex items-center justify-center">1</div>
                        <div>
                            <div class="font-semibold text-white">{{ $t('You apply here') }}</div>
                            <p class="text-gray-400 text-sm mt-1">
                                {{ $t('Tell us who you are and why you want to do this. Applying costs nothing and commits you to nothing.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-300 font-black text-sm flex items-center justify-center">2</div>
                        <div>
                            <div class="font-semibold text-white">{{ $t('The applicants vote') }}</div>
                            <p class="text-gray-400 text-sm mt-1">
                                {{ $t('Everyone who applied then votes on who gets the position. The people doing this work pick the people they will be working with, rather than one person deciding it alone.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-500/20 border border-green-400/40 text-green-300 font-black text-sm flex items-center justify-center">3</div>
                        <div>
                            <div class="font-semibold text-white">{{ $t('Access is granted by hand') }}</div>
                            <p class="text-gray-400 text-sm mt-1">
                                {{ $t('Whoever comes through the vote is added in the admin panel with permission for serverdemo validation and nothing else.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What the job actually is -->
            <div class="h-full bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-white mb-4">{{ $t('What a validator does') }}</h2>

                <ul class="space-y-3 text-gray-300 text-sm">
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('A cleared report is assigned to <span>one</span> validator, picked at random. You watch the serverdemo of that run and say what you see.')"></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('Not sure? <span>Hand it to someone else.</span> That is a normal outcome, not a failure - leaving a note about what you already checked is part of the job.')"></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('If the second person is not sure either, the report opens to <span>every validator at once</span> and you work it out together in the internal notes on that report.')"></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-blue-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('If the group cannot agree, it goes to the <span>admin</span> for a final call.')"></span>
                    </li>
                </ul>
            </div>

            <!-- The guard rails. Dark like its neighbours - the yellow wash it
                 had was the worst of the three to read - with the colour kept
                 in the border and heading so it still reads as a warning. -->
            <div class="h-full bg-black/40 backdrop-blur-sm border border-yellow-500/30 rounded-2xl p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-yellow-300 mb-4">{{ $t('What you will not get') }}</h2>

                <ul class="space-y-3 text-gray-300 text-sm">
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('<span>No access to the serverdemo archive.</span> Not a browser, not a search, not a download link. Only the single demo belonging to a report that has been handed to you.')"></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('<span>Nothing reaches you automatically.</span> Every report is checked by the admin first and only released if it is not a false report. Nobody can flood validators with work by reporting a lot of runs.')"></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-yellow-400 font-bold flex-shrink-0">-</span>
                        <span class="[&_span]:text-white [&_span]:font-semibold"
                              v-html="$t('<span>A single report never moves.</span> A run has to be reported independently by several different people before it is looked at, on top of the admin clearing it. How many is deliberately not published.')"></span>
                    </li>
                </ul>
            </div>

            </div><!-- /explainers -->

            <!-- Who is doing it today -->
            <div v-if="validators.length > 0 || applicantCount > 0" class="flex flex-wrap gap-2 mb-8">
                <span v-if="applicantCount > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 border border-white/15 text-gray-300 text-xs font-bold">
                    {{ $tc(':count application so far|:count applications so far', applicantCount) }}
                </span>
                <span v-if="validators.length > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-500/10 border border-green-400/25 text-green-300 text-xs font-bold">
                    {{ $tc(':count active validator|:count active validators', validators.length) }}
                </span>
            </div>

            <!-- The vote -->
            <div v-if="voting.open" class="bg-black/40 backdrop-blur-sm border border-blue-400/30 rounded-2xl p-6 mb-6 shadow-2xl">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <h2 class="text-xl font-bold text-white">{{ $t('Voting is open') }}</h2>
                </div>
                <p class="text-gray-400 text-sm mb-5">
                    {{ voting.title || $t('Applicants pick who gets the position.') }}
                    {{ $t('Counts stay hidden until the round closes.') }}
                </p>

                <div v-if="!user" class="text-gray-400 text-sm">
                    {{ $t('Log in to see the ballot.') }}
                </div>

                <div v-else-if="!voting.canVote" class="rounded-lg bg-white/5 border border-white/10 px-4 py-3 text-gray-400 text-sm">
                    {{ $t('Only people who applied vote in this round.') }}
                </div>

                <div v-else-if="voting.candidates.length === 0" class="text-gray-400 text-sm">
                    {{ $t('Nobody else is on the ballot yet.') }}
                </div>

                <div v-else class="grid gap-3 xl:grid-cols-2">
                    <div
                        v-for="candidate in voting.candidates"
                        :key="candidate.id"
                        class="rounded-xl border p-4 transition-colors backdrop-blur-sm"
                        :class="votedFor(candidate.id)
                            ? 'bg-green-500/10 border-green-400/40'
                            : 'bg-black/30 border-white/10'"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-bold text-white mb-1" v-html="q3tohtml(candidate.name)"></div>
                                <p class="text-gray-400 text-sm whitespace-pre-line">{{ candidate.motivation }}</p>
                                <p v-if="candidate.experience" class="text-gray-500 text-sm mt-2 whitespace-pre-line">{{ candidate.experience }}</p>
                                <p v-if="candidate.availability" class="text-gray-600 text-xs mt-2">{{ $t('Around: :when', { when: candidate.availability }) }}</p>
                            </div>

                            <button
                                @click="toggleVote(candidate)"
                                class="flex-shrink-0 px-4 py-2 rounded-xl font-semibold text-sm transition-colors"
                                :class="votedFor(candidate.id)
                                    ? 'bg-green-600 hover:bg-green-500 text-white'
                                    : 'bg-white/5 hover:bg-white/10 text-gray-300 border border-white/15'"
                            >
                                {{ votedFor(candidate.id) ? $t('Voted') : $t('Vote') }}
                            </button>
                        </div>
                    </div>

                    <p class="text-gray-500 text-xs xl:col-span-2">
                        {{ $t('Vote for as many people as you think should get it. Click again to take a vote back.') }}
                    </p>
                </div>
            </div>

            <!-- Existing application -->
            <div v-if="application" class="rounded-2xl border p-5 mb-6 backdrop-blur-sm shadow-2xl" :class="statusClass[application.status] || statusClass.pending">
                <div class="font-bold mb-1">{{ statusLabel[application.status] || application.status }}</div>
                <p class="text-sm opacity-90">
                    {{ $t('You applied on :date.', { date: new Date(application.created_at).toLocaleDateString($page.props.locale) }) }}
                    <span v-if="application.review_note"> {{ application.review_note }}</span>
                </p>
            </div>

            <!-- Form -->
            <div v-if="user && canApply" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-white mb-1">{{ $t('Apply') }}</h2>
                <p class="text-gray-500 text-sm mb-5">{{ $t('Applying as') }} <span class="font-semibold" v-html="q3tohtml(user.name)"></span>.</p>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- The two long answers share a row on wide screens, so
                         neither becomes a single field stretched across the
                         whole page. -->
                    <EnglishOnlyNotice :compact="false" />

                    <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">
                            {{ $t('Why do you want to do this?') }}
                            <span class="text-red-400">*</span>
                            <span class="font-normal text-gray-500">{{ $t('- required, at least :count characters', { count: MOTIVATION_MIN }) }}</span>
                        </label>
                        <textarea
                            ref="motivationField"
                            v-model="form.motivation"
                            rows="5"
                            :placeholder="$t('A couple of sentences is plenty. Why you, and what you would be looking at.')"
                            class="w-full px-4 py-3 bg-black/40 border rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :class="blocked ? 'border-amber-400/60' : 'border-white/10'"
                        ></textarea>
                        <p v-if="form.errors.motivation" class="text-red-400 text-sm mt-1">{{ form.errors.motivation }}</p>
                        <p v-else-if="motivationTooShort" class="text-sm mt-1" :class="blocked ? 'text-amber-300 font-semibold' : 'text-amber-400/70'">
                            {{ $tc('This one is the only answer that is required, and it is :count character short of the :min it needs|This one is the only answer that is required, and it is :count characters short of the :min it needs', motivationMissing, { min: MOTIVATION_MIN }) }}
                            <span class="text-gray-500 font-normal">{{ $t('(:count so far)', { count: motivationLength }) }}</span>.
                        </p>
                        <p v-else class="text-green-400/80 text-sm mt-1">
                            {{ $t('Long enough - you can send the application.') }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">
                            {{ $t('Anything that helps you spot a demo that broke the rules?') }}
                        </label>
                        <textarea
                            v-model="form.experience"
                            rows="4"
                            :placeholder="$t('Years played, physics you know well, scripts and binds you can recognise, moderating you have done elsewhere. Optional.')"
                            class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                        <p v-if="form.errors.experience" class="text-red-400 text-sm mt-1">{{ form.errors.experience }}</p>
                    </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">{{ $t('Roughly when are you around?') }}</label>
                            <input
                                v-model="form.availability"
                                type="text"
                                :placeholder="$t('Evenings CET, weekends, ...')"
                                class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.availability" class="text-red-400 text-sm mt-1">{{ form.errors.availability }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">{{ $t('Where can we reach you?') }}</label>
                            <input
                                v-model="form.contact"
                                type="text"
                                :placeholder="$t('Discord handle')"
                                class="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-lg text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.contact" class="text-red-400 text-sm mt-1">{{ form.errors.contact }}</p>
                        </div>
                    </div>

                    <p v-if="form.errors.throttle" class="text-red-400 text-sm">{{ form.errors.throttle }}</p>

                    <!-- Spelled out rather than implied by a greyed-out button.
                         Whoever hits this needs to know it is about the text in
                         the box and nothing else - not their account, not their
                         role, not the site being broken. -->
                    <div
                        v-if="blocked"
                        class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
                    >
                        <span class="font-bold">{{ $t('Nothing was sent yet.') }}</span>
                        {{ $tc('The first question - Why do you want to do this? - still needs :count more character.|The first question - Why do you want to do this? - still needs :count more characters.', motivationMissing) }}
                        {{ $t('That is the only thing stopping this form: your account is fine, and the other three answers are optional. Write a sentence or two about why you want to watch these demos and the button will go through.') }}
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-3 font-semibold rounded-xl transition-colors disabled:cursor-wait"
                            :class="motivationTooShort
                                ? 'bg-white/10 hover:bg-white/15 text-gray-300 border border-white/20'
                                : 'bg-blue-600 hover:bg-blue-500 text-white'"
                        >
                            {{ form.processing ? $t('Sending...') : $t('Send application') }}
                        </button>

                        <p v-if="motivationTooShort && ! blocked" class="text-gray-400 text-sm">
                            {{ $tc('Waiting on :count more character in the first answer.|Waiting on :count more characters in the first answer.', motivationMissing) }}
                        </p>
                    </div>
                </form>
            </div>

            <!-- Logged out -->
            <div v-else-if="!user" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 text-center shadow-2xl">
                <p class="text-gray-300 mb-4">{{ $t('You need an account to apply.') }}</p>
                <Link :href="route('login')" class="inline-block px-5 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors">
                    {{ $t('Log in') }}
                </Link>
            </div>
        </div>
    </div>
</template>
