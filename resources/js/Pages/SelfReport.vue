<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    hasMddAccount: { type: Boolean, default: false },
    blocked: { type: Object, default: null },
    records: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    sort: { type: String, default: 'date' },
    totalRecords: { type: Number, default: 0 },
    shown: { type: Number, default: 0 },
    reasons: { type: Object, default: () => ({}) },
    mine: { type: Array, default: () => [] },
});

const { proxy } = getCurrentInstance();
const formatTime = proxy.formatTime;

const SORTS = {
    date: 'Date of run',
    rank: 'Rank',
    name: 'Map name',
    map_added: 'Map release date',
};

const search = ref(props.search);
const sort = ref(props.sort);

// Both filters are a server round trip rather than client-side work: only the
// first 120 rows are sent, so sorting or filtering what arrived would quietly
// reorder a slice instead of the whole list.
const reload = () => {
    router.get('/amnesty', { search: search.value, sort: sort.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['records', 'search', 'sort', 'shown'],
    });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 350);
});
watch(sort, reload);

// Selection survives searching and re-sorting, so a player can collect runs
// from several searches before withdrawing them in one go.
const picked = ref(new Set());
const pickedCount = computed(() => picked.value.size);

const toggle = (id) => {
    const next = new Set(picked.value);
    next.has(id) ? next.delete(id) : next.add(id);
    picked.value = next;
};

const allShownPicked = computed(() => props.records.length > 0
    && props.records.every((record) => picked.value.has(record.id)));

const toggleAllShown = () => {
    const next = new Set(picked.value);
    if (allShownPicked.value) {
        props.records.forEach((record) => next.delete(record.id));
    } else {
        props.records.forEach((record) => next.add(record.id));
    }
    picked.value = next;
};

const clearPicked = () => { picked.value = new Set(); };

const form = useForm({
    record_ids: [],
    reason: 'other',
    note: '',
    confirm: false,
});

const submit = () => {
    form.record_ids = [...picked.value];
    form.post('/amnesty', {
        preserveScroll: true,
        onSuccess: () => {
            clearPicked();
            form.reset();
        },
    });
};

// A native <select> draws its option list with the operating system, which
// ignores every colour on this page - white rows and a blue highlight in the
// middle of a dark panel. So the list is ours, and it can hold labels as long
// as "No proper finish (client_finish=false)" without the browser cutting them.
const reasonOpen = ref(false);
const reasonBox = ref(null);

const closeReason = (event) => {
    if (reasonBox.value && !reasonBox.value.contains(event.target)) {
        reasonOpen.value = false;
    }
};

const closeReasonOnEscape = (event) => {
    if (event.key === 'Escape') {
        reasonOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', closeReason);
    document.addEventListener('keydown', closeReasonOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', closeReason);
    document.removeEventListener('keydown', closeReasonOnEscape);
});

const pickReason = (key) => {
    form.reason = key;
    reasonOpen.value = false;
};

const fmtDate = (value) => value ? new Date(value).toLocaleDateString() : '';
const thumb = (path) => path ? `/storage/${path}` : '/images/unknown.jpg';
</script>

<template>
    <Head title="Amnesty - self report" />

    <div class="">
        <!-- Header Section - same shape as Servers, Records and Ranking. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                        Amnesty
                    </h1>
                </div>

                <p class="text-gray-400 mt-2 max-w-3xl">
                    If a time of yours should not be standing - wrong cvar, a run that was never
                    legitimate, anything - take it down yourself. Tick as many as you like and withdraw
                    them in one go.
                </p>
            </div>
        </div>

        <!-- Bottom padding leaves room for the action bar, which is fixed and
             would otherwise cover the last rows of the list. -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" :class="pickedCount ? 'pb-56' : ''"
            style="margin-top: -22rem;">

            <!-- Said once, loudly, before anything else. The single reason
                 somebody would not use this is the fear that using it is itself
                 an admission everyone gets to see. -->
            <div class="rounded-2xl border border-emerald-400/40 bg-emerald-500/[0.09] p-5 flex gap-4 mb-4">
                <svg class="w-8 h-8 shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <div>
                    <h2 class="text-emerald-200 font-black text-lg mb-1">This is private, and it is your right</h2>
                    <p class="text-gray-200 leading-relaxed">
                        Nobody is told that you withdrew a run, and nobody is told why. Not the validators,
                        not the public, not the log, nowhere on the site - only the site admin can see it.
                        Anyone can use this, at any time, on any of their own runs. You need no permission,
                        you owe nobody an explanation, and nothing about it is ever held against you.
                        That is what an amnesty means.
                    </p>
                </div>
            </div>

            <!-- Three facts people ask before clicking, and the one reason to
                 do it now rather than later. -->
            <div class="grid gap-3 md:grid-cols-3 mb-8">
                <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                    <div class="text-white font-bold text-sm mb-1">It happens immediately</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        The times come off the leaderboard the moment you confirm, and you cannot take
                        that back. Check what you have ticked before you do it.
                    </p>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                    <div class="text-white font-bold text-sm mb-1">No case, no review</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        A withdrawn run is not reported and not judged by anybody. It is treated as
                        putting the leaderboard right, because that is what it is.
                    </p>
                </div>
                <div class="rounded-xl border border-red-400/25 bg-red-500/[0.07] p-4">
                    <div class="text-red-300 font-bold text-sm mb-1">Only while you are ahead of it</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Once somebody else reports the run it becomes a case, and when validators decide
                        it, the outcome is published with your name on it.
                    </p>
                </div>
            </div>

            <!-- The one condition on an otherwise unconditional promise. It is
                 stated as plainly as the promise itself, because a rule people
                 only meet when it is used on them reads as an excuse. -->
            <div class="rounded-xl border border-amber-400/30 bg-amber-500/[0.07] p-4 mb-8">
                <span class="text-amber-300 font-bold">Abusing this loses you the amnesty.</span>
                <span class="text-gray-300">
                    It is here to put the leaderboard right, not to launder anything. If it is shown that
                    somebody used it to game the system, that player loses access to the amnesty programme
                    and from then on their runs go through reports and validators like anybody else's.
                </span>
            </div>

            <div v-if="blocked" class="bg-black/40 border border-red-400/40 rounded-2xl p-6 mb-8">
                <h2 class="text-red-300 font-black text-lg mb-1">You no longer have access to the amnesty</h2>
                <p class="text-gray-300 leading-relaxed">
                    Withdrawn on {{ fmtDate(blocked.since) }}<span v-if="blocked.reason">: {{ blocked.reason }}</span>.
                    Your runs are handled through the normal reporting and validation process from here on.
                    Take it up with the admin if you think this is wrong.
                </p>
            </div>

            <div v-if="!blocked && !hasMddAccount" class="bg-black/40 border border-yellow-400/30 rounded-2xl p-6 text-yellow-200">
                Your account is not linked to an MDD profile, so there are no records tied to it here.
                Link it in your settings first.
            </div>

            <template v-else-if="!blocked">
                <!-- The list -->
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-white/5 flex flex-wrap items-center gap-3">
                        <input v-model="search" type="text" placeholder="Search your maps..."
                            class="flex-1 min-w-[12rem] bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-emerald-400/50" />

                        <!-- Buttons rather than a <select> for the same reason
                             the reason picker is hand-built: four options are
                             worth four buttons, and the browser cannot repaint
                             these in system colours. -->
                        <div class="flex items-center gap-1 p-1 rounded-lg bg-black/40 border border-white/10">
                            <span class="text-xs uppercase tracking-wide text-gray-500 px-2">Sort</span>
                            <button v-for="(label, key) in SORTS" :key="key" type="button" @click="sort = key"
                                class="px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"
                                :class="sort === key ? 'bg-emerald-500/20 text-emerald-200' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                {{ label }}
                            </button>
                        </div>

                        <button type="button" @click="toggleAllShown" :disabled="!records.length"
                            class="px-3 py-2 rounded-lg text-sm font-bold bg-white/5 hover:bg-white/10 text-gray-200 transition-colors disabled:opacity-40">
                            {{ allShownPicked ? 'Unselect these' : 'Select these' }}
                        </button>

                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ shown }} of {{ totalRecords }}
                        </span>
                    </div>

                    <div v-if="!records.length" class="p-10 text-center text-gray-500">
                        No runs found.
                    </div>

                    <div v-else class="divide-y divide-white/5">
                        <label v-for="record in records" :key="record.id"
                            class="flex items-center gap-4 p-3 cursor-pointer transition-colors"
                            :class="picked.has(record.id) ? 'bg-emerald-500/10' : 'hover:bg-white/[0.04]'">

                            <input type="checkbox" :checked="picked.has(record.id)" @change="toggle(record.id)"
                                class="w-5 h-5 shrink-0 rounded bg-black/50 border-white/20 text-emerald-500 focus:ring-emerald-500/40" />

                            <img :src="thumb(record.map_thumbnail)" alt=""
                                onerror="this.onerror=null;this.src='/images/unknown.jpg'"
                                class="w-24 h-14 shrink-0 rounded-lg object-cover bg-gray-900" />

                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-white truncate">{{ record.mapname }}</div>
                                <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-x-3">
                                    <span class="uppercase">{{ record.physics }} {{ record.mode }}</span>
                                    <span>run {{ fmtDate(record.date_set) }}</span>
                                    <span v-if="record.map_added">map {{ fmtDate(record.map_added) }}</span>
                                </div>
                            </div>

                            <div v-if="record.rank" class="text-center shrink-0 w-14">
                                <div class="text-[10px] uppercase tracking-wide text-gray-600">Rank</div>
                                <div class="font-black" :class="record.rank === 1 ? 'text-yellow-400' : record.rank <= 3 ? 'text-gray-300' : 'text-gray-400'">
                                    {{ record.rank }}
                                </div>
                            </div>

                            <div class="font-mono text-gray-200 whitespace-nowrap w-24 text-right">{{ formatTime(record.time) }}</div>
                        </label>
                    </div>

                    <div v-if="shown < totalRecords" class="p-3 text-center text-xs text-gray-500 border-t border-white/5">
                        Showing the first {{ shown }}. Search or re-sort to reach the rest - anything you have
                        already ticked stays ticked.
                    </div>
                </div>

                <div v-if="mine.length" class="mt-6 bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-white/5 flex flex-wrap items-baseline gap-x-3">
                        <span class="text-white font-bold">Runs you have withdrawn</span>
                        <span class="text-gray-500 text-sm">{{ mine.length }} in total, and why</span>
                        <span class="ml-auto text-xs text-gray-600">Visible to you and the site admin. Nobody else.</span>
                    </div>
                    <div class="divide-y divide-white/5">
                        <div v-for="row in mine" :key="row.id" class="p-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-white">{{ row.mapname }}</div>
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ row.reason }}<span v-if="row.note" class="text-gray-500"> - {{ row.note }}</span>
                                </div>
                            </div>
                            <div class="text-sm text-gray-400 uppercase whitespace-nowrap">{{ row.physics }} {{ row.mode }}</div>
                            <div class="font-mono text-sm text-gray-300 whitespace-nowrap">{{ formatTime(row.time) }}</div>
                            <div class="text-sm text-gray-500 whitespace-nowrap">{{ fmtDate(row.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Action bar. Fixed rather than sitting under the list: with
                     120 rows the confirmation would otherwise be a scroll away
                     from whatever was just ticked. -->
                <div v-if="pickedCount"
                    class="fixed bottom-0 inset-x-0 z-40 border-t border-emerald-400/30 bg-gray-950/95 backdrop-blur-xl shadow-[0_-8px_40px_rgba(0,0,0,0.6)]">
                    <form @submit.prevent="submit" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-4 flex flex-wrap items-end gap-4">

                        <div class="shrink-0">
                            <div class="text-2xl font-black text-emerald-300 leading-none">{{ pickedCount }}</div>
                            <button type="button" @click="clearPicked" class="text-xs text-gray-500 hover:text-white">
                                run<span v-if="pickedCount !== 1">s</span> picked - clear
                            </button>
                        </div>

                        <div ref="reasonBox" class="relative min-w-[16rem] flex-1">
                            <label class="block text-xs text-gray-400 mb-1">What was wrong with them</label>
                            <button type="button" @click="reasonOpen = !reasonOpen"
                                class="w-full flex items-center justify-between gap-2 bg-black/50 border rounded-lg px-3 py-2 text-left text-white transition-colors"
                                :class="reasonOpen ? 'border-emerald-400/60' : 'border-white/10 hover:border-white/25'">
                                <span>{{ reasons[form.reason] }}</span>
                                <svg class="w-4 h-4 opacity-60 transition-transform duration-200 shrink-0"
                                    :class="reasonOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Opens upward: the bar is already at the bottom
                                 of the window. -->
                            <div v-if="reasonOpen"
                                class="absolute z-50 bottom-full left-0 right-0 mb-2 p-1.5 rounded-xl border border-white/15 bg-gray-900/95 backdrop-blur-xl shadow-[0_8px_40px_rgba(0,0,0,0.6)] max-h-72 overflow-y-auto">
                                <button v-for="(label, key) in reasons" :key="key" type="button" @click="pickReason(key)"
                                    class="w-full flex items-center gap-2 text-left px-3 py-2 rounded-lg text-sm transition-colors"
                                    :class="form.reason === key
                                        ? 'bg-emerald-500/20 text-emerald-100'
                                        : 'text-gray-300 hover:bg-white/10 hover:text-white'">
                                    <svg class="w-4 h-4 shrink-0" :class="form.reason === key ? 'opacity-100' : 'opacity-0'"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span>{{ label }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="min-w-[14rem] flex-1">
                            <label class="block text-xs text-gray-400 mb-1">Note (optional, admin only)</label>
                            <input v-model="form.note" type="text" maxlength="500"
                                class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-emerald-400/50"
                                placeholder="Only the site admin reads this." />
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-300 max-w-xs">
                                <input type="checkbox" v-model="form.confirm" class="w-5 h-5 rounded bg-black/50 border-white/20 text-emerald-500" />
                                <span>I understand this cannot be taken back.</span>
                            </label>

                            <button type="submit" :disabled="form.processing"
                                class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition-colors disabled:opacity-50 whitespace-nowrap">
                                Withdraw {{ pickedCount }} run<span v-if="pickedCount !== 1">s</span>
                            </button>
                        </div>

                        <div v-if="form.errors.confirm || form.errors.record_ids || form.errors.reason"
                            class="w-full text-red-400 text-sm">
                            {{ form.errors.confirm || form.errors.record_ids || form.errors.reason }}
                        </div>
                    </form>
                </div>
            </template>

        </div>
    </div>
</template>
