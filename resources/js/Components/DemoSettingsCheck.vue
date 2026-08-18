<script setup>
// Reads a demo and says whether its settings would count. Used twice: unrolled
// under the comps upload, where the question is actually asked, and as the
// whole of /comps/check for a link somebody can be sent.
//
// The confusing case this is built around is a one-second demo. The settings
// are recorded before the run starts, so a demo with nothing in it answers the
// question perfectly - but a panel that then says "no finish time" reads as a
// failure. So the run is a separate, calm block whose empty state says out
// loud that this is fine.
//
// The same care goes to a cvar the demo does not carry. Plenty of demos have
// no handicap or g_killWallbug in them. Those are grey and say "not recorded",
// never red: somebody sent hunting for a problem they do not have will stop
// trusting the panel that finds a real one.
import { ref } from 'vue';
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
        const { data } = await window.axios.post(route('comps.check.run'), body);
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
    <div class="space-y-3">
        <!-- Drop zone -->
        <label
            class="block cursor-pointer rounded-xl border bg-gradient-to-br from-white/[0.08] to-white/[0.02] backdrop-blur-sm p-1 transition"
            :class="dragging ? 'border-blue-400/60' : 'border-white/10 hover:border-white/20'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input type="file" class="hidden" accept=".dm_68,.dm_67,.dm_66" @change="send($event.target.files[0])" />
            <div class="flex flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed px-6 py-7 text-center transition"
                 :class="dragging ? 'border-blue-400/50 bg-blue-500/10' : 'border-white/10'">
                <svg class="h-7 w-7 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span class="text-sm font-bold text-gray-200">{{ busy ? $t('Reading the demo...') : $t('Drop a demo here, or click to pick one') }}</span>
                <span class="text-xs text-gray-400">
                    <strong class="text-gray-300">{{ $t('You do not need a finished run.') }}</strong>
                    {{ $t('Record one second and stop, that is enough.') }}
                </span>
                <span v-if="file && !busy" class="mt-1 break-all font-mono text-[11px] text-gray-500">{{ file.name }}</span>
            </div>
        </label>

        <div v-if="error" class="rounded-xl border border-red-500/30 border-l-4 border-l-red-400/70 bg-red-500/10 backdrop-blur-sm px-4 py-3 text-sm text-red-200">
            {{ error }}
        </div>

        <template v-if="result">
            <div
                class="rounded-xl border border-l-4 backdrop-blur-sm px-5 py-4"
                :class="result.ok
                    ? 'border-green-400/25 border-l-green-400/70 bg-gradient-to-br from-green-500/[0.12] to-white/[0.02]'
                    : 'border-amber-400/25 border-l-amber-400/70 bg-gradient-to-br from-amber-500/[0.12] to-white/[0.02]'"
            >
                <div class="text-base font-black" :class="result.ok ? 'text-green-300' : 'text-amber-200'">
                    {{ result.ok ? $t('These settings would count in comps.') : $t('These settings would not count in comps.') }}
                </div>
                <div v-if="result.summary" class="mt-1 text-sm leading-relaxed text-gray-300">{{ result.summary }}</div>
            </div>

            <!-- Settings -->
            <div class="overflow-hidden rounded-xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.02] backdrop-blur-sm">
                <div class="border-b border-white/10 px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                    {{ $t('Settings') }}
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wider text-gray-500">
                                <th class="px-5 py-2 text-left font-bold">{{ $t('Setting') }}</th>
                                <th class="px-3 py-2 text-left font-bold">{{ $t('Needed') }}</th>
                                <th class="px-5 py-2 text-right font-bold">{{ $t('In your demo') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rule in result.rules"
                                :key="rule.cvar"
                                class="border-t border-white/5"
                                :class="{
                                    'bg-red-500/[0.07]': rule.state === 'bad',
                                    'opacity-45': rule.state === 'unknown',
                                }"
                            >
                                <td class="px-5 py-2.5 align-top">
                                    <div class="font-mono text-[13px] font-bold text-gray-200">{{ rule.cvar }}</div>
                                    <div v-if="rule.companion !== undefined && rule.companion !== null"
                                         class="font-mono text-[11px] text-gray-500">
                                        g_synchronousClients {{ rule.companion }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-top font-mono text-xs text-gray-500">{{ rule.needed }}</td>
                                <td class="px-5 py-2.5 text-right align-top">
                                    <span v-if="rule.state === 'bad'"
                                          class="inline-flex items-center rounded-md bg-red-500/20 px-2 py-0.5 font-mono text-[13px] font-black text-red-200 ring-1 ring-inset ring-red-400/30">
                                        {{ rule.found }}
                                    </span>
                                    <span v-else-if="rule.state === 'ok'" class="font-mono text-[13px] font-bold text-green-300">
                                        {{ rule.found }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400"
                                          :title="$t('Your demo does not record this one, so it cannot be checked here. That is not a problem.')">
                                        {{ $t('not recorded') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="result.unknown" class="border-t border-white/10 px-5 py-2.5 text-xs leading-relaxed text-gray-400">
                    {{ $t('Greyed rows are not written into your demo at all, so they cannot be checked here. That is normal and not a problem.') }}
                </div>
            </div>

            <!-- The run -->
            <div class="rounded-xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.02] backdrop-blur-sm px-5 py-4">
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $t('The run') }}</div>

                <div v-if="result.run.time_ms" class="mt-2 flex flex-wrap gap-x-8 gap-y-1 text-sm">
                    <span class="text-gray-400">{{ $t('Map') }} <span class="ml-1 font-bold text-white">{{ result.run.map || '-' }}</span></span>
                    <span class="text-gray-400">{{ $t('Physics') }} <span class="ml-1 font-bold uppercase text-white">{{ result.run.physics || '-' }}</span></span>
                    <span class="text-gray-400">{{ $t('Time') }} <span class="ml-1 font-mono font-bold tabular-nums text-white">{{ formatTime(result.run.time_ms) }}</span></span>
                </div>
                <div v-else class="mt-2 text-sm text-gray-400">
                    {{ $t('There is no finished run in this demo. That is fine - the settings above were still checked.') }}
                </div>

                <div class="mt-3 border-t border-white/5 pt-2.5 text-xs leading-relaxed text-gray-400">
                    {{ $t('This page checks settings only. Whether a run counts in a round also depends on the map and the physics being played.') }}
                    {{ $t('Nothing was uploaded. Your file was read and deleted.') }}
                </div>
            </div>
        </template>
    </div>
</template>
