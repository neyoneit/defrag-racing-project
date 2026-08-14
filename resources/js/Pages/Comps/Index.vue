<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
    import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import { t } from '@/utils/i18n';
    import { formatTime } from '@/utils/time';

    import BallotCard from '@/Components/Comps/BallotCard.vue';
    import CompsCountdown from '@/Components/Comps/CompsCountdown.vue';
    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';

    // The comps hub.
    //
    // Two things run side by side and always have: the week being played, and
    // the ballot for the week after it. That overlap is the whole design - it
    // is why there is never a gap where there is nothing to do, and it is why
    // this page has two halves rather than one.
    const props = defineProps({
        playing: { type: Object, default: null },
        voting: { type: Object, default: null },
        history: { type: Array, default: () => [] },
        me: { type: Object, default: null },
        pointsTable: { type: Array, default: () => [] },
        pointsForFinishing: { type: Number, default: 1 },
        winsPerWildcard: { type: Number, default: 5 },
        prize: { type: Object, default: null },
        betaNotice: { type: Boolean, default: false },
        adminUrl: { type: String, default: '' },
    });

    const page = usePage();
    const user = computed(() => page.props.auth?.user);

    const PHYSICS = ['cpm', 'vq3'];

    const CATEGORY_LABELS = {
        strafe: () => t('Strafe'),
        weapon: () => t('Weapon'),
        combo: () => t('Combo'),
    };

    const categoryLabel = (category) => (CATEGORY_LABELS[category] ?? (() => category))();

    // Several sentences link the word "admin", and it sits in a different
    // place in each language - mid-sentence in English, at the end in some of
    // the others - so it goes in as a placeholder the translator can move
    // rather than as two fragments glued around one fixed word order.
    //
    // t() leaves a placeholder it was not given, so the translated line is
    // split around what remains. That keeps the link an Inertia Link rather
    // than raw HTML: v-html would work but would navigate the whole page and
    // put markup into the language files.
    //
    // It takes the translated line, not the key, on purpose: lang:sync only
    // sees literals sitting directly inside t(), so a key passed through a
    // helper is a key that never reaches the language files.
    //
    // Every sentence below is phrased so the linked word is the subject. One
    // link text has to serve all of them, and an inflected language gives a
    // subject and an object different endings - "vyplacI admin" against
    // "napis adminovi" - so a sentence that needs the other case would read as
    // broken grammar in half the languages we ship.
    const aroundAdmin = (line) => {
        const at = line.indexOf(':admin');

        if (at === -1) {
            return { before: line, after: '' };
        }

        return { before: line.slice(0, at), after: line.slice(at + ':admin'.length) };
    };

    const betaLine = computed(() => aroundAdmin(
        t('Expect a few rough edges in the first weeks. If something does not look right, :admin will sort it out.'),
    ));

    const prizeFundedLine = computed(() => aroundAdmin(
        t('The first :count weeklies are paid out by :admin from own funds: :total EUR in total.', {
            count: props.prize?.funded_weeks,
            total: props.prize?.funded_total,
        }),
    ));

    const prizeCommunityLine = computed(() => aroundAdmin(
        t('Paid out by :admin or by the community.'),
    ));

    const prizeLaterLine = computed(() => aroundAdmin(
        t('Later weeks may be paid out by :admin again or by the community. Write "comps" in the note when you donate and it goes into the prize pool rather than towards the maintenance of the site.'),
    ));

    // Totals per ballot, so each bar can show a share rather than a bare count.
    const totals = computed(() => {
        const out = { cpm: 0, vq3: 0 };
        for (const c of props.voting?.candidates ?? []) {
            out.cpm += c.votes.cpm;
            out.vq3 += c.votes.vq3;
        }
        return out;
    });

    const castVote = ({ candidate, physics }) => {
        router.post(route('comps.vote', props.voting.round_id), {
            candidate_id: candidate,
            physics,
        }, { preserveScroll: true });
    };

    // Spending a wildcard ends the argument for that physics, so it asks first
    // and says exactly what it will do.
    const wildcardTarget = ref(null);

    const confirmWildcard = () => {
        router.post(route('comps.wildcard', props.voting.round_id), {
            candidate_id: wildcardTarget.value.candidate,
            physics: wildcardTarget.value.physics,
        }, {
            preserveScroll: true,
            onFinish: () => (wildcardTarget.value = null),
        });
    };

    const mapReport = ref(null);

    const confirmMapReport = () => {
        router.post(route('comps.report-map', props.voting.round_id), {
            map_id: mapReport.value.map_id,
            physics: mapReport.value.physics,
        }, {
            preserveScroll: true,
            onFinish: () => (mapReport.value = null),
        });
    };

    // Wildcards, at a glance.
    const totalHeld = computed(() =>
        PHYSICS.reduce((n, p) => n + (props.me?.held?.[p] ?? 0), 0));

    const totalSpent = computed(() =>
        PHYSICS.reduce((n, p) => n + (props.me?.spent?.[p] ?? 0), 0));

    const heldBreakdown = computed(() =>
        PHYSICS.filter((p) => (props.me?.held?.[p] ?? 0) > 0)
            .map((p) => `${p.toUpperCase()} ${props.me.held[p]}`)
            .join(' · '));

    // Uploading an entry. No physics field: the demo says which it is.
    const uploadForm = useForm({
        demo: null,
        is_highlight: false,
    });

    // Ticking the highlight box is easy to do by accident and impossible to
    // undo after the deadline: the run is off the leaderboard, and by the time
    // somebody notices, the round is over. So it asks, once, before sending.
    const highlightConfirm = ref(false);

    const send = () => {
        uploadForm.post(route('comps.submit', props.playing.round_id), {
            preserveScroll: true,
            onSuccess: () => uploadForm.reset('demo', 'is_highlight'),
            onFinish: () => (highlightConfirm.value = false),
        });
    };

    const submitEntry = () => {
        if (uploadForm.is_highlight) {
            highlightConfirm.value = true;

            return;
        }

        send();
    };

    const myEntriesIn = (physics) => (props.playing?.my_entries ?? []).filter((e) => e.physics === physics);

    const bestOf = (physics) => {
        const valid = myEntriesIn(physics).filter((e) => e.status === 'valid' && !e.is_highlight);
        return valid.length ? Math.min(...valid.map((e) => e.time)) : null;
    };

    const withdraw = (id) => {
        router.delete(route('comps.submission.destroy', id), { preserveScroll: true });
    };

    const errors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="$t('Comps')" />

    <div class="">
        <!-- Header Section - same shape as Servers, Records, Ranking and
             Wishlist: the gradient block holds the heading, the content is
             pulled up under it. A page of ours that starts differently reads
             as a page from somewhere else. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 lg:gap-8">
                    <div class="min-w-0">
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                            {{ $t('Comps') }}
                        </h1>
                        <p class="text-gray-400 mt-2 max-w-3xl drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                            {{ $t('Every week the site draws five maps, everyone votes, and the map winners for both physics are played for a week. Nobody organises it and nobody can forget to.') }}
                        </p>
                    </div>

                    <!-- Opposite the intro rather than below it. The first
                         weeks of anything new go wrong somewhere, and a player
                         whose run scored oddly needs to know it is worth
                         telling somebody rather than assuming that is how it
                         works. Switched off in admin once it has run clean. -->
                    <section v-if="betaNotice"
                             class="flex-shrink-0 lg:max-w-sm rounded-xl border border-amber-500/30 bg-amber-500/10 backdrop-blur-sm px-4 py-3">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 flex-shrink-0 text-amber-400 mt-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 6l7.5 13h-15L12 8zm-1 3v4h2v-4h-2zm0 5v2h2v-2h-2z" /></svg>
                            <p class="text-sm text-amber-100/90 leading-snug">
                                <span class="font-bold">{{ $t('Comps is brand new.') }}</span>
                                <span>{{ betaLine.before }}<Link :href="adminUrl" class="font-bold underline decoration-amber-400/40 hover:text-amber-50">{{ $t('the admin') }}</Link>{{ betaLine.after }}</span>
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

    <!-- Six units between sections, not ten. Ten was set when the page was
         three big boxes; with the header block above them it left a hole
         between the status strip and the ballot that read as a gap rather
         than a break. -->
    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-6" style="margin-top: -22rem;">

        <!-- The prize and your own standing are one header block, spaced
             tightly against each other. The page's 10-unit rhythm is for
             actual sections; giving a two-line status strip the same air
             around it as the competition itself reads as a gap, not a break. -->
        <div class="space-y-4">

        <!-- What a week pays and who is paying for it. Sits above everything
             else because it is the answer to the first question anybody asks
             about a competition. -->
        <!-- Tinted rather than plain glass. This is the one panel on the page
             that has to catch the eye on the way past, and a page where every
             box is the same shade of black says nothing is more important than
             anything else. Kept to a wash and a border, not a bright block. -->
        <section v-if="prize?.eur > 0"
                 class="relative overflow-hidden rounded-2xl border border-emerald-400/25 bg-gradient-to-br from-emerald-500/[0.12] via-black/40 to-black/40 backdrop-blur-sm p-5 md:p-6 shadow-[0_0_40px_-12px_rgba(16,185,129,0.35)]">
            <!-- A soft bloom behind the number, so the corner it sits in is
                 lit rather than outlined. -->
            <div class="pointer-events-none absolute -top-24 -left-16 w-72 h-72 rounded-full bg-emerald-400/10 blur-3xl"></div>

            <div class="relative flex flex-col md:flex-row md:items-center gap-5 md:gap-8">

                <!-- The number, at the size the number deserves. It is the
                     first thing anybody asks about a competition. -->
                <div class="flex-shrink-0">
                    <div class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-emerald-400/90 mb-1.5">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6v2H2v3a5 5 0 0 0 4.6 5A5.5 5.5 0 0 0 11 15.9V19H7v3h10v-3h-4v-3.1a5.5 5.5 0 0 0 4.4-3.9A5 5 0 0 0 22 7V4h-4V2zM4 7V6h2v3.9A3 3 0 0 1 4 7zm16 0a3 3 0 0 1-2 2.9V6h2v1z" /></svg>
                        {{ $t('Prize pool') }}
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl md:text-5xl font-black text-emerald-300 tabular-nums leading-none">{{ prize.total }}</span>
                        <span class="text-xl md:text-2xl font-black text-emerald-300/70 leading-none">EUR</span>
                    </div>
                    <div class="mt-1.5 text-sm text-emerald-100/60">
                        {{ $t('every week, :amount EUR per physics', { amount: prize.eur }) }}
                    </div>
                </div>

                <div class="hidden md:block w-px self-stretch bg-emerald-400/15"></div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-200">
                        <span v-if="prize.self_funded">{{ prizeFundedLine.before }}<Link :href="adminUrl" class="font-bold underline decoration-emerald-400/40 hover:text-white">{{ $t('the admin') }}</Link>{{ prizeFundedLine.after }}</span>
                        <span v-else>{{ prizeCommunityLine.before }}<Link :href="adminUrl" class="font-bold underline decoration-emerald-400/40 hover:text-white">{{ $t('the admin') }}</Link>{{ prizeCommunityLine.after }}</span>
                    </p>
                    <p class="mt-1.5 text-sm text-gray-500 leading-snug">
                        <span>{{ prizeLaterLine.before }}<Link :href="adminUrl" class="underline decoration-white/20 hover:text-gray-300">{{ $t('the admin') }}</Link>{{ prizeLaterLine.after }}</span>
                        <!-- The space is written out: Vue drops whitespace
                             between elements when it contains a newline, which
                             ran this straight into the sentence above it.

                             Split off rather than linked mid-sentence: the
                             clause lands in a different place in every
                             language, and a link glued into one word order
                             breaks in the other eight. -->
                        {{ ' ' }}
                        <Link :href="route('donations.index')" class="text-gray-400 underline decoration-white/20 hover:text-gray-200">{{ $t('See everything donations pay for.') }}</Link>
                    </p>
                </div>

                <Link :href="route('donations.index')"
                      class="flex-shrink-0 inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/15 px-5 py-3 text-sm font-bold text-emerald-200 hover:bg-emerald-500/25 hover:border-emerald-400/60 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-10.4A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8 3.6C20 16.1 12 21 12 21z" /></svg>
                    {{ $t('Donate to the pool') }}
                </Link>
            </div>
        </section>

        <!-- ============================== YOU ============================== -->
        <!-- One strip rather than four tiles. Holding a wildcard has to be
             visible - the only sign used to be a button appearing on the
             ballot, which tells you nothing on the six days a week when no
             ballot is open - but it is a status line, not the point of the
             page, and four tall cards pushed the actual competition below the
             fold. -->
        <section v-if="me" class="rounded-xl border border-white/10 bg-white/[0.04] backdrop-blur-sm px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <span class="text-[11px] font-black uppercase tracking-widest text-gray-300">{{ $t('Your comps') }}</span>

                <!-- Wildcards. Gold only when there is one to spend, so the
                     strip stays quiet the rest of the time. -->
                <span class="inline-flex items-center gap-1.5"
                      :title="totalSpent > 0 ? $t('Already spent: :count', { count: totalSpent }) : ''">
                    <svg class="w-3.5 h-3.5" :class="totalHeld > 0 ? 'text-amber-400' : 'text-gray-500'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                    <span class="text-base font-black tabular-nums" :class="totalHeld > 0 ? 'text-amber-300' : 'text-gray-300'">{{ totalHeld }}</span>
                    <span class="text-xs text-gray-400">
                        {{ totalHeld > 0 ? heldBreakdown : $t('Wildcards') }}
                    </span>
                </span>

                <span class="hidden sm:block h-4 w-px bg-white/15"></span>

                <!-- Progress to the next one, as one line per physics. -->
                <span class="inline-flex items-center gap-3">
                    <span class="text-xs text-gray-400">{{ $t('Next wildcard') }}</span>
                    <span v-for="physics in PHYSICS" :key="physics" class="inline-flex items-center gap-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ physics }}</span>
                        <span class="w-12 h-1.5 rounded-full bg-black/60 overflow-hidden">
                            <span class="block h-full rounded-full bg-amber-400"
                                  :style="{ width: (((me.wins[physics] % winsPerWildcard) / winsPerWildcard) * 100) + '%' }"></span>
                        </span>
                        <span class="text-[11px] tabular-nums text-gray-400">{{ me.wins[physics] % winsPerWildcard }}/{{ winsPerWildcard }}</span>
                    </span>
                </span>

                <span class="hidden sm:block h-4 w-px bg-white/15"></span>

                <span class="inline-flex items-baseline gap-1.5">
                    <span class="text-base font-black tabular-nums text-white">{{ me.wins.cpm + me.wins.vq3 }}</span>
                    <span class="text-xs text-gray-400">{{ $t('Weeks won') }}</span>
                </span>

                <span class="inline-flex items-baseline gap-1.5">
                    <span class="text-base font-black tabular-nums text-white">{{ me.average_rank ?? '-' }}</span>
                    <span class="text-xs text-gray-400">
                        <template v-if="me.rounds_entered">
                            {{ $t('Average rank') }} ({{ me.rounds_entered }})
                        </template>
                        <template v-else>{{ $t('You have not finished a round yet.') }}</template>
                    </span>
                </span>

                <span v-if="me.best_rank" class="inline-flex items-baseline gap-1.5">
                    <span class="text-base font-black tabular-nums text-white">{{ me.best_rank }}</span>
                    <span class="text-xs text-gray-400">{{ $t('Best') }}</span>
                </span>
            </div>

            <!-- What a wildcard actually is. The strip counted them and
                 tracked progress towards the next without ever saying what one
                 does, which is a scoreboard for a rule nobody was told. -->
            <p class="mt-2 pt-2 border-t border-white/10 text-xs text-gray-400 leading-snug">
                {{ $t('A wildcard picks next week\'s map outright and overrules the vote. You earn one for every :count weekly wins in a physics, and whoever spends one first decides that round - everyone else keeps theirs.', { count: winsPerWildcard }) }}
            </p>
        </section>

        </div>

        <!-- ============================ VOTING ============================= -->
        <!-- Same panel as Playing now on purpose: the two halves of this page
             are the two halves of the same week, and giving one a box and the
             other a bare heading made them read as unrelated. -->
        <!-- Tinted blue, the way the prize pool is tinted green, but lighter.
             The ballot is the half of the page with a deadline on it, so it
             should read as more urgent than the round being played and less
             loud than the money. -->
        <section v-if="voting"
                 class="rounded-2xl border border-blue-400/25 bg-black/40 backdrop-blur-sm overflow-hidden shadow-[0_0_35px_-16px_rgba(96,165,250,0.4)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blue-400/20 bg-gradient-to-r from-blue-500/[0.12] to-white/5 backdrop-blur-sm px-5 py-3.5">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="rounded-full bg-blue-500/20 border border-blue-500/40 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-300">
                        {{ voting.is_open ? $t('Voting') : $t('Decided') }}
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="font-bold text-white">{{ $t('Vote on the next map') }}</span>
                            <!-- The category, said out loud rather than in grey
                                 six-point text. It is the single fact that
                                 decides whether somebody cares about this
                                 week at all. -->
                            <span class="rounded-md bg-blue-500/20 border border-blue-400/40 px-2 py-0.5 text-[11px] font-black uppercase tracking-wider text-blue-200">
                                {{ categoryLabel(voting.category) }}<template v-if="voting.weapon"> · {{ voting.weapon }}</template>
                            </span>

                            <!-- The rotation is fixed and known years ahead, so
                                 there is no reason to keep the following week a
                                 surprise - and read as a chain, this week to
                                 next, it says at a glance whether the thing you
                                 are waiting for is close. -->
                            <template v-if="voting.next_category">
                                <span class="text-gray-600">&rarr;</span>
                                <span class="inline-flex items-baseline gap-1.5 rounded-md border border-white/15 px-2 py-0.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-gray-500">{{ $t('then') }}</span>
                                    <span class="text-[11px] font-black uppercase tracking-wider text-gray-300">{{ categoryLabel(voting.next_category) }}</span>
                                </span>
                            </template>
                        </div>
                        <div class="text-xs text-blue-100/50">
                            {{ $t('Vote on the next weekly comps map for both physics') }}
                        </div>
                    </div>
                </div>
                <!-- The pool for the week being voted on, between the heading
                     and the deadline. It is the week you are picking a map
                     for, and it need not pay what the current one pays: a
                     donation aimed at one weekly raises that weekly only. -->
                <div v-if="voting.prize?.eur > 0" class="flex items-baseline gap-2 px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-400/25">
                    <span class="text-lg font-black text-emerald-300 tabular-nums leading-none">{{ voting.prize.total }} EUR</span>
                    <span class="text-[11px] text-emerald-100/60">{{ $t('for this week') }}</span>
                </div>

                <CompsCountdown v-if="voting.is_open" :until="voting.closes_at" :label="$t('Voting closes in')" emphasis />
            </div>

            <div class="p-5">
            <p class="mb-4 text-sm text-gray-400 max-w-3xl">
                {{ $t('CPM and VQ3 vote separately, so each physics gets the map its own players picked. You have one vote in each and can move it until the deadline.') }}
            </p>

            <div v-if="user && !voting.may_vote" class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                {{ $t('Link your MDD profile to vote in comps.') }}
            </div>
            <div v-else-if="!user" class="mb-4 rounded-lg border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-gray-400">
                {{ $t('Sign in to vote.') }}
            </div>

            <div v-if="errors.wildcard" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                {{ errors.wildcard }}
            </div>

            <!-- Once decided, say so and by what -->
            <div v-if="!voting.is_open" class="mb-4 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="physics in PHYSICS"
                    :key="physics"
                    class="rounded-lg border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3"
                >
                    <div class="text-[10px] font-black uppercase tracking-wider text-gray-500">{{ physics }}</div>
                    <div class="font-bold text-white">{{ voting.decided?.[physics]?.map ?? '-' }}</div>
                    <div class="text-[11px] text-gray-500">
                        <template v-if="voting.decided?.[physics]?.decided_by === 'wildcard'">{{ $t('Chosen with a wildcard') }}</template>
                        <template v-else-if="voting.decided?.[physics]?.decided_by === 'carried'">{{ $t('Nobody voted in this physics, so it took the other one\'s map') }}</template>
                        <template v-else-if="voting.decided?.[physics]?.decided_by === 'random'">{{ $t('Nobody voted at all, so it was drawn at random') }}</template>
                        <template v-else>{{ $t('Chosen by vote') }}</template>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <BallotCard
                    v-for="candidate in voting.candidates"
                    :key="candidate.id"
                    :candidate="candidate"
                    :my-votes="voting.my_votes"
                    :wildcards-held="voting.wildcards_held"
                    :may-vote="voting.may_vote"
                    :is-open="voting.is_open"
                    :totals="totals"
                    @vote="castVote"
                    @wildcard="wildcardTarget = $event"
                    @report="mapReport = { ...$event, physics: 'cpm' }"
                />
            </div>
            </div>
        </section>

        <!-- ============================ PLAYING ============================ -->
        <section v-if="playing" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-white/5 backdrop-blur-sm px-5 py-3">
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-green-500/20 border border-green-500/40 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-green-300">
                        {{ $t('Playing now') }}
                    </span>
                    <span class="font-bold text-white">{{ playing.comp_title }}</span>
                    <span class="text-xs text-gray-500">
                        {{ categoryLabel(playing.category) }}<template v-if="playing.weapon"> ({{ playing.weapon }})</template>
                    </span>
                </div>
                <CompsCountdown :until="playing.ends_at" :label="$t('Ends in')" />
            </div>

            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div v-for="physics in PHYSICS" :key="physics" class="rounded-xl border border-white/10 bg-black/30 backdrop-blur-sm overflow-hidden">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-2">
                        <span class="text-xs font-black uppercase tracking-widest text-gray-300">{{ physics }}</span>
                        <span v-if="bestOf(physics) !== null" class="text-xs text-gray-400">
                            {{ $t('Your best') }}
                            <span class="font-bold text-white tabular-nums ml-1">{{ formatTime(bestOf(physics)) }}</span>
                        </span>
                    </div>

                    <div v-if="playing.maps?.[physics]" class="p-4 space-y-3">
                        <div class="flex gap-3">
                            <img
                                v-if="playing.maps[physics].thumbnail"
                                :src="`/storage/${playing.maps[physics].thumbnail}`"
                                :alt="playing.maps[physics].name"
                                class="w-28 h-20 rounded-lg object-cover flex-shrink-0"
                            />
                            <div class="min-w-0">
                                <Link
                                    :href="route('maps.map', playing.maps[physics].name)"
                                    class="block font-bold text-white hover:text-blue-300 transition-colors truncate"
                                >
                                    {{ playing.maps[physics].name }}
                                </Link>
                                <div v-if="playing.maps[physics].author" class="text-xs text-gray-500 truncate">
                                    {{ playing.maps[physics].author }}
                                </div>
                                <div v-if="playing.maps[physics].decided_by === 'wildcard'" class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                                    {{ $t('Chosen with a wildcard') }}
                                </div>
                            </div>
                        </div>

                        <!-- Who has entered. Deliberately not how fast: a live
                             leaderboard would hand everyone else the answer. -->
                        <div>
                            <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                {{ $t('Already uploaded a run') }} ({{ playing.entrants?.[physics]?.length ?? 0 }})
                            </div>
                            <div v-if="playing.entrants?.[physics]?.length" class="flex flex-wrap gap-x-3 gap-y-1.5">
                                <CompsPlayer
                                    v-for="p in playing.entrants[physics]"
                                    :key="p.id"
                                    :player="p"
                                    size="sm"
                                />
                            </div>
                            <div v-else class="text-xs text-gray-600">{{ $t('Nobody yet.') }}</div>

                            <!-- Runs an admin took out. Kept in the list rather
                                 than deleted from it: dropping them silently
                                 would make the round read as though they never
                                 turned up. -->
                            <div v-if="playing.removed_entrants?.[physics]?.length" class="mt-3">
                                <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-red-400/70">
                                    {{ $t('Removed') }} ({{ playing.removed_entrants[physics].length }})
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                    <CompsPlayer
                                        v-for="p in playing.removed_entrants[physics]"
                                        :key="'x' + p.id"
                                        :player="p"
                                        size="sm"
                                        struck
                                        :title="p.reason || $t('Removed from this round.')"
                                    />
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-gray-600 leading-snug">
                                {{ $t('Times stay hidden until the round closes, so nobody can be handed the time to beat.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entering -->
            <div class="border-t border-white/10 bg-black/30 backdrop-blur-sm p-5">
                <h3 class="font-bold text-white">{{ $t('Enter your run') }}</h3>
                <p class="mt-1 mb-4 text-sm text-gray-400 max-w-3xl">
                    {{ $t('Upload the demo here, online or offline. Nothing is collected for you: a run only counts once its demo has been uploaded through this page. Upload as often as you like - only your best time is scored.') }}
                </p>
                <p class="mb-4 text-sm text-gray-500 max-w-3xl">
                    {{ $t('You do not pick CPM or VQ3 - the demo says which it is and we read it out of the file.') }}
                    {{ $t('Your demos stay private until the round ends. Once it does they appear normally in Demos, on the map page and on your profile.') }}
                </p>

                <div v-if="!user" class="rounded-lg border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-gray-400">
                    {{ $t('Sign in to enter.') }}
                </div>

                <form v-else @submit.prevent="submitEntry" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <input
                            type="file"
                            accept=".dm_68"
                            @input="uploadForm.demo = $event.target.files[0]"
                            class="text-sm text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-sm file:text-white hover:file:bg-white/20"
                        />

                        <button
                            type="submit"
                            :disabled="uploadForm.processing || !uploadForm.demo"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {{ uploadForm.processing ? $t('Uploading...') : $t('Upload') }}
                        </button>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-gray-400 cursor-pointer max-w-2xl">
                        <input type="checkbox" v-model="uploadForm.is_highlight" class="mt-0.5 rounded border-white/20 bg-black/40" />
                        <span>
                            {{ $t('Upload as a highlight') }}
                            <span class="block text-xs text-gray-600">
                                {{ $t('A highlight is shown as a curiosity and is left out of the leaderboard entirely. Use it for a run worth watching rather than a run worth scoring.') }}
                            </span>
                        </span>
                    </label>

                    <div v-if="uploadForm.errors.demo" class="text-sm text-red-400">{{ uploadForm.errors.demo }}</div>
                </form>

                <!-- Your own entries, times and all. Yours are never a secret
                     from you. -->
                <div v-if="playing.my_entries?.length" class="mt-5">
                    <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ $t('Your entries') }}</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="entry in playing.my_entries"
                            :key="entry.id"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-white/5 bg-black/40 backdrop-blur-sm px-3 py-2"
                        >
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 w-8">{{ entry.physics || '-' }}</span>

                            <span v-if="entry.status === 'valid'" class="font-bold tabular-nums text-white">{{ formatTime(entry.time) }}</span>
                            <span v-else-if="entry.status === 'pending'" class="text-sm text-gray-500">{{ $t('Reading the demo...') }}</span>
                            <span v-else class="text-sm text-red-400" :title="entry.reason">{{ $t('Rejected') }}</span>

                            <!-- Online or offline, read off the demo's gametype
                                 rather than guessed from whether we paired it
                                 with a record. -->
                            <span v-if="entry.status === 'valid'"
                                  class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                  :class="entry.is_online ? 'bg-green-500/15 text-green-300' : 'bg-sky-500/15 text-sky-300'"
                                  :title="entry.gametype">
                                {{ entry.is_online ? $t('Online') : $t('Offline') }}
                            </span>

                            <!-- Pairing with a scraped record is a bonus for the
                                 site and changes nothing about the entry, so it
                                 is stated quietly. -->
                            <span v-if="entry.matched_record"
                                  class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                  :title="$t('This demo was paired with your record on the map.')">
                                {{ $t('Matched to a record') }}
                            </span>

                            <span v-if="entry.is_highlight" class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-purple-300">
                                {{ $t('Highlight') }}
                            </span>

                            <span v-if="entry.filename" class="hidden md:block truncate text-[11px] text-gray-600 max-w-[16rem]">{{ entry.filename }}</span>

                            <button
                                type="button"
                                @click="withdraw(entry.id)"
                                class="ml-auto text-xs text-gray-600 hover:text-red-400 transition-colors"
                            >
                                {{ $t('Withdraw') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================== HISTORY ============================ -->
        <section v-if="history.length">
            <h2 class="mb-4 text-xl font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ $t('Past comps') }}</h2>

            <div class="overflow-x-auto rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-white/5 backdrop-blur-sm text-[10px] uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-bold">{{ $t('Comp') }}</th>
                            <th class="px-4 py-2 text-left font-bold">{{ $t('Map') }}</th>
                            <th class="px-4 py-2 text-left font-bold">CPM</th>
                            <th class="px-4 py-2 text-left font-bold">VQ3</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="comp in history" :key="comp.id" class="hover:bg-white/5 transition-colors">
                            <td class="px-4 py-2.5">
                                <Link :href="route('comps.show', comp.id)" class="font-bold text-white hover:text-blue-300 transition-colors">
                                    {{ comp.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-2.5 text-gray-400">
                                <span v-for="(m, i) in comp.maps" :key="m">
                                    <template v-if="i">, </template>{{ m }}
                                </span>
                            </td>
                            <td v-for="physics in PHYSICS" :key="physics" class="px-4 py-2.5">
                                <div v-if="comp.winners?.[physics]?.length" class="flex flex-col gap-1">
                                    <div v-for="w in comp.winners[physics]" :key="w.id" class="flex items-center gap-2">
                                        <CompsPlayer :player="w" size="sm" />
                                        <span class="text-xs tabular-nums text-gray-500">{{ formatTime(w.time) }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-gray-600">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="!playing && !voting && !history.length" class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-6 py-12 text-center">
            <p class="text-gray-400">{{ $t('No comps have run yet. The first one starts as soon as it is switched on.') }}</p>
        </div>
        </div>

        <!-- Highlight confirmation -->
        <Teleport to="body">
            <div v-if="highlightConfirm" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="highlightConfirm = false">
                <div class="w-full max-w-md rounded-xl border border-purple-500/30 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="flex items-center gap-2 text-lg font-black text-white">
                        <svg class="w-5 h-5 text-purple-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                        {{ $t('Upload this as a highlight?') }}
                    </h3>
                    <p class="mt-3 text-sm text-gray-300">
                        {{ $t('This run will NOT count towards the leaderboard. It is shown as a curiosity and nothing else.') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ $t('Use it only for a run that does not belong on the leaderboard. If you want this time scored, go back and untick the box.') }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="highlightConfirm = false" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="send" :disabled="uploadForm.processing" class="rounded-lg bg-purple-500 px-4 py-2 text-sm font-bold text-white hover:bg-purple-400 disabled:opacity-40">
                            {{ $t('Yes, upload as a highlight') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Wildcard confirmation -->
        <Teleport to="body">
            <div v-if="wildcardTarget" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="wildcardTarget = null">
                <div class="w-full max-w-md rounded-xl border border-amber-500/30 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="flex items-center gap-2 text-lg font-black text-white">
                        <svg class="w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                        {{ $t('Use your wildcard?') }}
                    </h3>
                    <p class="mt-3 text-sm text-gray-400">
                        {{ $t('This makes :map the :physics map for this round, whatever the vote says.', { map: wildcardTarget.map, physics: wildcardTarget.physics.toUpperCase() }) }}
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ $t('You hold one wildcard and spending it uses it up. Whoever spends one first decides the round, so if somebody beats you to it yours stays unspent for another week.') }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="wildcardTarget = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="confirmWildcard" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-black hover:bg-amber-400">
                            {{ $t('Use it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Impossible-map report -->
        <Teleport to="body">
            <div v-if="mapReport" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="mapReport = null">
                <div class="w-full max-w-md rounded-xl border border-white/10 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="text-lg font-black text-white">{{ $t('Report an impossible map') }}</h3>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ $t('Tell an admin that :map cannot be finished in one of the physics. If it stands, the map leaves that ballot and never enters that pool again.', { map: mapReport.map }) }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <button
                            v-for="physics in PHYSICS"
                            :key="physics"
                            type="button"
                            @click="mapReport.physics = physics"
                            class="flex-1 rounded-lg border px-3 py-2 text-xs font-black uppercase tracking-wider transition-colors"
                            :class="mapReport.physics === physics ? 'border-blue-500/50 bg-blue-600/25 text-white' : 'border-white/10 bg-black/40 backdrop-blur-sm text-gray-400 hover:text-white'"
                        >
                            {{ $t('Impossible in :physics', { physics: physics.toUpperCase() }) }}
                        </button>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="mapReport = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="confirmMapReport" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-500">
                            {{ $t('Send report') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
