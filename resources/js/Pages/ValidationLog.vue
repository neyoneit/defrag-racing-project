<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref } from 'vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    closed: { type: Array, default: () => [] },
    open: { type: Array, default: () => [] },
    minReports: { type: Number, default: 2 },
});

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const tab = ref('closed');

const fmtDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

// How long an open case has been sitting there. The point of publishing this
// is that nobody can quietly leave one alone, so it is stated in days rather
// than as a date somebody has to subtract in their head.
const daysOpen = (iso) => {
    if (!iso) return 0;
    return Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 86400000));
};

const outcomeClass = (key) => ({
    upheld: 'bg-red-500/15 border-red-400/30 text-red-300',
    dismissed: 'bg-green-500/15 border-green-400/30 text-green-300',
    inconclusive: 'bg-white/5 border-white/15 text-gray-300',
}[key] || 'bg-white/5 border-white/15 text-gray-300');

const kindLabel = (kind) => kind === 'public_demo' ? 'Uploaded demo' : 'Serverdemo';

const tabs = computed(() => [
    { key: 'closed', label: 'Decided', count: props.closed.length },
    { key: 'open', label: 'In progress', count: props.open.length },
]);
</script>

<template>
    <Head title="Validation log" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <div class="mb-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-400/30 text-purple-300 text-xs font-bold uppercase tracking-wider mb-4">
                    Public record
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white mb-3">Validation log</h1>
                <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                    Every report filed on this site, and what came of it. Nothing is enforced against anyone
                    before it can be read here.
                </p>
            </div>

            <!-- The rules of the log itself, stated on the log. Somebody who
                 arrives here because they were reported should not have to ask
                 what will and will not be published about them. -->
            <div class="grid gap-4 mb-8 lg:grid-cols-3">
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">Names appear when a case is decided</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Whichever way it went. Publishing only the upheld ones would hide every time the
                        process cleared somebody, and that is the half that shows it works.
                    </p>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">Open cases stay anonymous</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        An unchecked accusation is not a verdict. You can see how many there are, what
                        stage they are at and how long they have been waiting, but not who they are about.
                    </p>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">Reporters are never named</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        At any stage. A report is not evidence, it is a request to go and look, and it
                        takes at least {{ minReports }} different people before it becomes a case at all.
                    </p>
                </div>
            </div>

            <!-- Numbers first: the shape of the whole thing before the rows. -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
                <div v-for="cell in [
                    { label: 'Reports filed', value: stats.reports },
                    { label: 'Waiting on the admin', value: stats.awaiting_admin },
                    { label: 'Being validated', value: stats.in_validation },
                    { label: 'Upheld', value: stats.upheld, tone: 'text-red-300' },
                    { label: 'Cleared', value: stats.dismissed, tone: 'text-green-300' },
                    { label: 'Inconclusive', value: stats.inconclusive },
                ]" :key="cell.label" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-4">
                    <div class="text-2xl font-black" :class="cell.tone || 'text-white'">{{ cell.value ?? 0 }}</div>
                    <div class="text-[11px] uppercase tracking-wide text-gray-500 mt-1">{{ cell.label }}</div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="px-4 py-2 rounded-lg text-sm font-bold border transition-colors"
                    :class="tab === t.key
                        ? 'bg-purple-500/20 border-purple-400/40 text-purple-200'
                        : 'bg-black/30 border-white/10 text-gray-400 hover:text-white hover:border-white/25'">
                    {{ t.label }}
                    <span class="ml-1.5 text-xs opacity-70">{{ t.count }}</span>
                </button>
            </div>

            <!-- Decided cases -->
            <div v-if="tab === 'closed'" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                <div v-if="!closed.length" class="p-8 text-center text-gray-500">
                    Nothing has been decided yet.
                </div>
                <div v-else class="divide-y divide-white/5">
                    <div v-for="row in closed" :key="row.id" class="p-4 md:p-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <Link v-if="row.user_id" :href="`/profile/${row.user_id}`"
                                    class="font-bold hover:underline" v-html="q3tohtml(row.player)" />
                                <span v-else class="font-bold text-white" v-html="q3tohtml(row.player)"></span>
                                <span class="text-[11px] px-2 py-0.5 rounded border border-white/10 bg-white/5 text-gray-400">
                                    {{ kindLabel(row.kind) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-400 mt-1">
                                <span v-for="(reason, i) in row.reasons" :key="reason.label">
                                    <span v-if="i">, </span>{{ reason.label }}<span v-if="reason.count > 1"> ×{{ reason.count }}</span>
                                </span>
                                <span v-if="!row.reasons.length">Reported runs: {{ row.runs }}</span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500 whitespace-nowrap">
                            {{ row.runs }} run<span v-if="row.runs !== 1">s</span>
                        </div>
                        <div class="text-sm text-gray-500 whitespace-nowrap">
                            {{ fmtDate(row.opened_at) }} → {{ fmtDate(row.closed_at) }}
                        </div>
                        <div class="px-3 py-1 rounded-lg border text-xs font-bold whitespace-nowrap" :class="outcomeClass(row.outcome_key)">
                            {{ row.outcome }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open cases, anonymous -->
            <div v-else class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                <div v-if="!open.length" class="p-8 text-center text-gray-500">
                    Nothing is being validated right now.
                </div>
                <div v-else class="divide-y divide-white/5">
                    <div v-for="row in open" :key="row.id" class="p-4 md:p-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-gray-300">Case #{{ row.id }}</div>
                            <div class="text-sm text-gray-500 mt-1">
                                Name withheld until it is decided.
                            </div>
                        </div>
                        <span class="text-[11px] px-2 py-0.5 rounded border border-white/10 bg-white/5 text-gray-400 whitespace-nowrap">
                            {{ kindLabel(row.kind) }}
                        </span>
                        <div class="text-sm text-gray-500 whitespace-nowrap">
                            {{ row.runs }} run<span v-if="row.runs !== 1">s</span>
                        </div>
                        <div class="text-sm text-gray-400 whitespace-nowrap">{{ row.stage }}</div>
                        <div class="text-sm text-gray-500 whitespace-nowrap">
                            open {{ daysOpen(row.opened_at) }}d
                        </div>
                    </div>
                </div>
            </div>

            <!-- The way out, on the page somebody reads when they are worried.
                 No numbers and no list: what gets withdrawn is private, and
                 saying "3 people did this" against a leaderboard anyone can
                 diff would not be. -->
            <div class="mt-6 rounded-2xl border border-blue-400/25 bg-blue-500/[0.07] p-5">
                <h2 class="text-white font-bold mb-2">Standing on a time that should not count?</h2>
                <p class="text-gray-300 text-sm leading-relaxed max-w-3xl">
                    Take it down yourself and none of this happens to you. No case, no validators, no
                    entry on this page - it is between you and the admin, and nothing goes on your
                    account. Once somebody else reports it, that choice is gone: it becomes a case, and
                    when the case is decided it is published here with your name on it.
                </p>
                <Link href="/self-report"
                    class="inline-flex mt-3 px-4 py-2 rounded-lg bg-blue-500/80 hover:bg-blue-500 text-white text-sm font-bold transition-colors">
                    Withdraw one of your times
                </Link>
            </div>

        </div>
    </div>
</template>
