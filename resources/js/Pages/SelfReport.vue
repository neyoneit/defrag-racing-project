<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { getCurrentInstance, ref, watch } from 'vue';

const props = defineProps({
    hasMddAccount: { type: Boolean, default: false },
    records: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    totalRecords: { type: Number, default: 0 },
    reasons: { type: Object, default: () => ({}) },
    mine: { type: Array, default: () => [] },
});

const { proxy } = getCurrentInstance();
const formatTime = proxy.formatTime;

const search = ref(props.search);

// Searching is a GET reload rather than client-side filtering: only the 60
// most recent runs are sent, so filtering what arrived would silently hide
// everything older than that.
let searchTimer = null;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/self-report', { search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

const selected = ref(null);

const form = useForm({
    record_id: null,
    reason: 'other',
    note: '',
    confirm: false,
});

const pick = (record) => {
    selected.value = record;
    form.record_id = record.id;
    form.reason = 'other';
    form.note = '';
    form.confirm = false;
    form.clearErrors();
};

const submit = () => {
    form.post('/self-report', {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            form.reset();
        },
    });
};

const fmtDate = (value) => value ? new Date(value).toLocaleDateString() : '';
</script>

<template>
    <Head title="Withdraw your own time" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <div class="mb-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/15 border border-blue-400/30 text-blue-300 text-xs font-bold uppercase tracking-wider mb-4">
                    Amnesty
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white mb-3">Withdraw your own time</h1>
                <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                    If one of your times should not be standing - wrong cvar, a run that was never
                    legitimate, anything - take it down yourself. It costs you nothing: no validator, no
                    verdict, no mark on your account. The time goes and the
                    <Link href="/validation-log" class="text-purple-300 hover:underline">validation log</Link>
                    says you took it down.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3 mb-6">
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">It is immediate and final</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        The record is deleted the moment you confirm. We cannot put it back, so pick the
                        right run.
                    </p>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">Nothing else happens to you</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        A withdrawn time is not a case and is not reviewed. Doing this is treated as
                        putting the leaderboard right, because that is what it is.
                    </p>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                    <h2 class="text-white font-bold mb-2">Your name is in the log</h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        The time was public and its disappearance is public, so the log says where it
                        went. Hiding that would be the quiet edit this is meant to replace.
                    </p>
                </div>
            </div>

            <div v-if="!hasMddAccount" class="bg-black/40 border border-yellow-400/30 rounded-2xl p-6 text-yellow-200">
                Your account is not linked to an MDD profile, so there are no records tied to it here.
                Link it in your settings first.
            </div>

            <template v-else>
                <div class="grid gap-6 lg:grid-cols-2">

                    <!-- Your runs -->
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                        <div class="p-4 border-b border-white/5 flex items-center gap-3">
                            <input v-model="search" type="text" placeholder="Search your maps..."
                                class="flex-1 bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50" />
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ totalRecords }} total</span>
                        </div>

                        <div v-if="!records.length" class="p-8 text-center text-gray-500">
                            No runs found.
                        </div>
                        <div v-else class="divide-y divide-white/5 max-h-[32rem] overflow-y-auto">
                            <button v-for="record in records" :key="record.id" type="button" @click="pick(record)"
                                class="w-full text-left p-4 flex items-center gap-3 hover:bg-white/5 transition-colors"
                                :class="selected && selected.id === record.id ? 'bg-purple-500/10' : ''">
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-white truncate">{{ record.mapname }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ record.physics }} {{ record.mode }} · {{ fmtDate(record.date_set) }}
                                    </div>
                                </div>
                                <div class="font-mono text-sm text-gray-300 whitespace-nowrap">{{ formatTime(record.time) }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- The confirmation -->
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                        <div v-if="!selected" class="text-gray-500 py-12 text-center">
                            Pick one of your runs on the left.
                        </div>

                        <form v-else @submit.prevent="submit" class="space-y-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Withdrawing</div>
                                <div class="text-xl font-black text-white">{{ selected.mapname }}</div>
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ selected.physics }} {{ selected.mode }} · <span class="font-mono">{{ formatTime(selected.time) }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-300 mb-1">What was wrong with it</label>
                                <select v-model="form.reason"
                                    class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-purple-400/50">
                                    <option v-for="(label, key) in reasons" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <div v-if="form.errors.reason" class="text-red-400 text-sm mt-1">{{ form.errors.reason }}</div>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-300 mb-1">Anything to add (optional, public)</label>
                                <textarea v-model="form.note" rows="3" maxlength="500"
                                    class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                                    placeholder="Shown in the validation log next to the withdrawn time."></textarea>
                                <div v-if="form.errors.note" class="text-red-400 text-sm mt-1">{{ form.errors.note }}</div>
                            </div>

                            <label class="flex items-start gap-3 text-sm text-gray-300">
                                <input type="checkbox" v-model="form.confirm" class="mt-1 rounded bg-black/50 border-white/20" />
                                <span>I understand this deletes the time immediately and it cannot be restored.</span>
                            </label>
                            <div v-if="form.errors.confirm" class="text-red-400 text-sm">{{ form.errors.confirm }}</div>
                            <div v-if="form.errors.record_id" class="text-red-400 text-sm">{{ form.errors.record_id }}</div>

                            <div class="flex gap-3">
                                <button type="submit" :disabled="form.processing"
                                    class="px-5 py-2.5 rounded-lg bg-red-500/80 hover:bg-red-500 text-white font-bold transition-colors disabled:opacity-50">
                                    Withdraw this time
                                </button>
                                <button type="button" @click="selected = null"
                                    class="px-5 py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-300 font-bold transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="mine.length" class="mt-6 bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-white/5 text-white font-bold">Times you have withdrawn</div>
                    <div class="divide-y divide-white/5">
                        <div v-for="row in mine" :key="row.id" class="p-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="min-w-0 flex-1 font-semibold text-white">{{ row.mapname }}</div>
                            <div class="text-sm text-gray-400 uppercase">{{ row.physics }} {{ row.mode }}</div>
                            <div class="font-mono text-sm text-gray-300">{{ formatTime(row.time) }}</div>
                            <div class="text-sm text-gray-500">{{ fmtDate(row.created_at) }}</div>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
