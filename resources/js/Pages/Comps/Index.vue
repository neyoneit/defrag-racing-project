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
                <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                    {{ $t('Comps') }}
                </h1>
                <p class="text-gray-400 mt-2 max-w-3xl drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                    {{ $t('Every week the site draws five maps, everyone votes, and the winners are played for a week. Nobody organises it and nobody can forget to.') }}
                </p>
            </div>
        </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-10" style="margin-top: -22rem;">

        <!-- ============================== YOU ============================== -->
        <!-- One strip rather than four tiles. Holding a wildcard has to be
             visible - the only sign used to be a button appearing on the
             ballot, which tells you nothing on the six days a week when no
             ballot is open - but it is a status line, not the point of the
             page, and four tall cards pushed the actual competition below the
             fold. -->
        <section v-if="me" class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-2.5">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('Your comps') }}</span>

                <!-- Wildcards. Gold only when there is one to spend, so the
                     strip stays quiet the rest of the time. -->
                <span class="inline-flex items-center gap-1.5"
                      :title="totalSpent > 0 ? $t('Already spent: :count', { count: totalSpent }) : ''">
                    <svg class="w-3.5 h-3.5" :class="totalHeld > 0 ? 'text-amber-400' : 'text-gray-700'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                    <span class="text-sm font-black tabular-nums" :class="totalHeld > 0 ? 'text-amber-300' : 'text-gray-600'">{{ totalHeld }}</span>
                    <span class="text-[11px] text-gray-500">
                        {{ totalHeld > 0 ? heldBreakdown : $t('Wildcards') }}
                    </span>
                </span>

                <span class="hidden sm:block h-4 w-px bg-white/10"></span>

                <!-- Progress to the next one, as one line per physics. -->
                <span class="inline-flex items-center gap-3">
                    <span class="text-[11px] text-gray-500">{{ $t('Next wildcard') }}</span>
                    <span v-for="physics in PHYSICS" :key="physics" class="inline-flex items-center gap-1.5">
                        <span class="text-[9px] font-black uppercase tracking-wider text-gray-600">{{ physics }}</span>
                        <span class="w-12 h-1 rounded-full bg-black/50 overflow-hidden">
                            <span class="block h-full rounded-full bg-amber-400/70"
                                  :style="{ width: (((me.wins[physics] % winsPerWildcard) / winsPerWildcard) * 100) + '%' }"></span>
                        </span>
                        <span class="text-[10px] tabular-nums text-gray-500">{{ me.wins[physics] % winsPerWildcard }}/{{ winsPerWildcard }}</span>
                    </span>
                </span>

                <span class="hidden sm:block h-4 w-px bg-white/10"></span>

                <span class="inline-flex items-baseline gap-1.5">
                    <span class="text-sm font-black tabular-nums text-white">{{ me.wins.cpm + me.wins.vq3 }}</span>
                    <span class="text-[11px] text-gray-500">{{ $t('Weeks won') }}</span>
                </span>

                <span class="inline-flex items-baseline gap-1.5">
                    <span class="text-sm font-black tabular-nums text-white">{{ me.average_rank ?? '-' }}</span>
                    <span class="text-[11px] text-gray-500">
                        <template v-if="me.rounds_entered">
                            {{ $t('Average rank') }} ({{ me.rounds_entered }})
                        </template>
                        <template v-else>{{ $t('You have not finished a round yet.') }}</template>
                    </span>
                </span>

                <span v-if="me.best_rank" class="inline-flex items-baseline gap-1.5">
                    <span class="text-sm font-black tabular-nums text-white">{{ me.best_rank }}</span>
                    <span class="text-[11px] text-gray-500">{{ $t('Best') }}</span>
                </span>
            </div>
        </section>

        <!-- ============================ VOTING ============================= -->
        <!-- Same panel as Playing now on purpose: the two halves of this page
             are the two halves of the same week, and giving one a box and the
             other a bare heading made them read as unrelated. -->
        <section v-if="voting" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-white/5 backdrop-blur-sm px-5 py-3">
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-blue-500/20 border border-blue-500/40 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-300">
                        {{ voting.is_open ? $t('Voting') : $t('Decided') }}
                    </span>
                    <span class="font-bold text-white">{{ $t('Vote on the next map') }}</span>
                    <span class="text-xs text-gray-500">
                        {{ categoryLabel(voting.category) }}<template v-if="voting.weapon"> ({{ voting.weapon }})</template>
                    </span>
                </div>
                <CompsCountdown v-if="voting.is_open" :until="voting.closes_at" :label="$t('Voting closes in')" />
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
