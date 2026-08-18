<script setup>
// "Would this demo's settings count?" - answered without keeping the file.
//
// The confusing case this page is built around is a one-second demo. The
// settings live in the very first bytes of a recording, so a demo with no run
// in it answers the question perfectly - but a page that then says "no finish
// time" reads as a failure. So the run is a separate, calm block, and its
// empty state says out loud that this is fine.
//
// The same care goes to a cvar the demo simply does not carry. Plenty of demos
// have no handicap or g_killWallbug in them. Those are grey and say "not
// recorded", never red: somebody sent hunting for a problem they do not have
// will stop trusting the whole page.
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { t } from '@/utils/i18n';

const file = ref(null);
const busy = ref(false);
const error = ref(null);
const result = ref(null);
const dragging = ref(false);

function formatTime(ms) {
    if (!ms) return null;
    const s = Math.floor(ms / 1000);
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}.${String(ms % 1000).padStart(3, '0')}`;
}

async function send(picked) {
    if (!picked) return;

    file.value = picked;
    error.value = null;
    result.value = null;
    busy.value = true;

    const body = new FormData();
    body.append('demo', picked);

    try {
        const { data } = await window.axios.post(route('demos.check.run'), body);
        result.value = data;
    } catch (e) {
        error.value = e.response?.status === 429
            ? t('Too many checks in a row. Wait a minute and try again.')
            : (e.response?.data?.message || t('Something went wrong reading that file.'));
    } finally {
        busy.value = false;
    }
}

function onDrop(e) {
    dragging.value = false;
    send(e.dataTransfer?.files?.[0]);
}
</script>

<template>
    <Head :title="$t('Check your demo settings')" />

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-8">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold text-white">{{ $t('Check your demo settings') }}</h1>
            <p class="text-sm text-gray-400 mt-2">
                {{ $t('Drop a demo here and this tells you whether the settings in it would count. Nothing is saved - the file is read and thrown away.') }}
            </p>
            <p class="text-sm text-gray-400 mt-2">
                <strong class="text-gray-200">{{ $t('You do not need a finished run.') }}</strong>
                {{ $t('Record one second and stop. The settings are written at the very start of every demo.') }}
            </p>

            <!-- Drop zone -->
            <label
                class="mt-6 flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-10 cursor-pointer transition"
                :class="dragging ? 'border-blue-400/70 bg-blue-500/10' : 'border-white/15 bg-black/30 hover:border-white/30'"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <input type="file" class="hidden" accept=".dm_68,.dm_67,.dm_66" @change="send($event.target.files[0])" />
                <span class="text-sm text-gray-300">{{ busy ? $t('Reading the demo...') : $t('Drop a demo here, or click to pick one') }}</span>
                <span v-if="file && !busy" class="text-xs text-gray-500 break-all">{{ file.name }}</span>
            </label>

            <div v-if="error" class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                {{ error }}
            </div>

            <!-- Result -->
            <div v-if="result" class="mt-6 space-y-6">
                <div
                    class="rounded-xl border px-4 py-3"
                    :class="result.ok
                        ? 'border-green-500/30 bg-green-500/10'
                        : 'border-amber-500/30 bg-amber-500/10'"
                >
                    <div class="font-bold" :class="result.ok ? 'text-green-300' : 'text-amber-200'">
                        {{ result.ok ? $t('These settings would count in comps.') : $t('These settings would not count in comps.') }}
                    </div>
                    <div v-if="result.summary" class="text-sm text-gray-300 mt-1">{{ result.summary }}</div>
                </div>

                <!-- Settings -->
                <div class="rounded-xl border border-white/10 bg-black/30 overflow-hidden">
                    <div class="px-4 py-2 text-xs uppercase tracking-wider text-gray-500 border-b border-white/10">
                        {{ $t('Settings') }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider text-gray-500">
                                    <th class="px-4 py-2 text-left font-medium">{{ $t('Setting') }}</th>
                                    <th class="px-4 py-2 text-left font-medium">{{ $t('Needed') }}</th>
                                    <th class="px-4 py-2 text-left font-medium">{{ $t('In your demo') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="rule in result.rules"
                                    :key="rule.cvar"
                                    class="border-t border-white/5"
                                    :class="rule.state === 'unknown' ? 'opacity-50' : ''"
                                >
                                    <td class="px-4 py-2 font-mono text-xs text-gray-300">
                                        {{ rule.cvar }}
                                        <span v-if="rule.companion !== undefined && rule.companion !== null" class="block text-[11px] text-gray-500">
                                            g_synchronousClients {{ rule.companion }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ rule.needed }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">
                                        <span v-if="rule.state === 'bad'" class="text-red-300 font-bold">{{ rule.found }}</span>
                                        <span v-else-if="rule.state === 'ok'" class="text-green-300">{{ rule.found }}</span>
                                        <span v-else class="text-gray-500 font-sans" :title="$t('Your demo does not record this one, so it cannot be checked here. That is not a problem.')">
                                            {{ $t('not recorded') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="result.unknown" class="px-4 py-2 text-xs text-gray-500 border-t border-white/10">
                        {{ $t('Greyed rows are not written into your demo at all, so they cannot be checked here. That is normal and not a problem.') }}
                    </div>
                </div>

                <!-- The run -->
                <div class="rounded-xl border border-white/10 bg-black/30 px-4 py-3">
                    <div class="text-xs uppercase tracking-wider text-gray-500">{{ $t('The run') }}</div>
                    <div v-if="result.run.time_ms" class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-300">
                        <span>{{ $t('Map') }}: <span class="text-white">{{ result.run.map || '-' }}</span></span>
                        <span>{{ $t('Physics') }}: <span class="text-white">{{ result.run.physics || '-' }}</span></span>
                        <span>{{ $t('Time') }}: <span class="font-mono text-white">{{ formatTime(result.run.time_ms) }}</span></span>
                    </div>
                    <div v-else class="mt-2 text-sm text-gray-400">
                        {{ $t('There is no finished run in this demo. That is fine - the settings above were still checked.') }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        {{ $t('This page checks settings only. Whether a run counts in a round also depends on the map and the physics being played.') }}
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    {{ $t('Nothing was uploaded. Your file was read and deleted.') }}
                    <Link :href="route('comps.index')" class="text-blue-400 hover:underline">{{ $t('Weekly comps') }}</Link>
                </p>
            </div>
        </div>
    </div>
</template>
