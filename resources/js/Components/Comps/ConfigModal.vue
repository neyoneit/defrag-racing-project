<script setup>
/**
 * What a run has to be recorded with, and where to get it.
 *
 * This exists because the question is asked every week and answered wrongly
 * every week. Two things people believe that are not true:
 *
 *  - that there is one correct setting. There are two, and the site accepts
 *    both.
 *  - that a config can break an online run. It cannot: pmove_fixed,
 *    pmove_msec and g_synchronousClients travel in systeminfo, so online the
 *    SERVER sets them and whatever the player has is thrown away.
 *
 * Only one config is offered for download. Naming two invites people to pick,
 * and picking is the thing they are here to avoid - so the other one is named
 * as legal and left without a button.
 */
defineProps({
    show: Boolean,
});

defineEmits(['close']);

const CONFIG_URL = '/configs/defrag-racing-rules.cfg';
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="$emit('close')">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="$emit('close')"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-xl border border-white/10 bg-gradient-to-br from-gray-900 to-gray-950 shadow-2xl">

                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $t('Settings your run has to be made with') }}</h3>
                            <p class="mt-1 text-sm text-gray-400">{{ $t('The same for comps as for any record on the site.') }}</p>
                        </div>
                        <button type="button" @click="$emit('close')"
                                class="rounded-lg p-1.5 text-gray-500 transition hover:bg-white/5 hover:text-white"
                                :aria-label="$t('Close')">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">

                        <!-- Online first, because it is the half nobody has to
                             do anything about, and saying so removes most of
                             the worry before the detail starts. -->
                        <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/5 p-4">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" /></svg>
                                <div>
                                    <div class="font-bold text-emerald-200">{{ $t('Online you do not have to do anything') }}</div>
                                    <p class="mt-1 text-sm text-emerald-100/70">
                                        {{ $t('The server sets the physics, not you. The defrag.racing servers already run the correct values, so every run on them counts.') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-amber-500/25 bg-amber-500/5 p-4">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                                <div>
                                    <div class="font-bold text-amber-200">{{ $t('Offline you do') }}</div>
                                    <p class="mt-1 text-sm text-amber-100/70">
                                        {{ $t('Offline you are the server. Quake starts with settings that are not accepted, so you have to load one of the two rulesets below before you record.') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Both, named. Somebody already running the second
                             one has to be able to see that they are fine,
                             which is the whole reason it is listed. -->
                        <div>
                            <h4 class="mb-2 text-sm font-bold text-white">{{ $t('Both of these are accepted') }}</h4>

                            <div class="overflow-x-auto rounded-xl border border-white/10">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-400">
                                        <tr>
                                            <th class="px-4 py-2 font-semibold">{{ $t('Ruleset') }}</th>
                                            <th class="px-4 py-2 font-mono font-semibold">pmove_fixed</th>
                                            <th class="px-4 py-2 font-mono font-semibold">g_synchronousClients</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        <tr class="bg-blue-500/5">
                                            <td class="px-4 py-2.5">
                                                <span class="font-bold text-white">{{ $t('Example 1') }}</span>
                                                <span class="ml-1.5 rounded bg-blue-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-300">{{ $t('recommended') }}</span>
                                            </td>
                                            <td class="px-4 py-2.5 font-mono text-emerald-300">1</td>
                                            <!-- The default is named beside it: "not set" alone
                                                 reads as a gap somebody has to fill, when it is
                                                 in fact the value that is already correct. -->
                                            <td class="px-4 py-2.5 font-mono text-gray-500">{{ $t('not set (default :value)', { value: '0' }) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2.5 font-bold text-white">{{ $t('Example 2') }}</td>
                                            <td class="px-4 py-2.5 font-mono text-gray-300">0</td>
                                            <td class="px-4 py-2.5 font-mono text-emerald-300">1</td>
                                        </tr>
                                        <tr class="bg-red-500/5">
                                            <td class="px-4 py-2.5 text-red-300">{{ $t('Quake out of the box') }}</td>
                                            <td class="px-4 py-2.5 font-mono text-red-300">0</td>
                                            <td class="px-4 py-2.5 font-mono text-red-300">0</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="mt-2 text-xs text-gray-500">
                                {{ $t('Either one holds the physics step at 8 ms, which is what the rule is about. The last row is neither, and a run made that way is flagged.') }}
                            </p>
                        </div>

                        <!-- One button, not two. Naming both rulesets is
                             information; offering both is a decision, and this
                             page exists to take that decision away. -->
                        <div class="rounded-xl border border-blue-500/25 bg-blue-500/5 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-white">{{ $t('Take the defrag.racing config') }}</div>
                                    <p class="mt-1 text-sm text-gray-400">
                                        {{ $t('It is example 1 above, the DFWC 2021 ruleset. It sets the same physics the defrag.racing servers run, so what you practise offline is what you get online. Example 2, the dfcomps ruleset, is just as legal - if you already use it, you do not have to change anything.') }}
                                    </p>
                                </div>

                                <a :href="CONFIG_URL" download
                                   class="inline-flex flex-shrink-0 items-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:from-blue-700 hover:to-blue-800">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    {{ $t('Download') }}
                                </a>
                            </div>

                            <div class="mt-3 border-t border-white/10 pt-3">
                                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('How to use it') }}</div>
                                <ol class="space-y-1 text-sm text-gray-300">
                                    <li v-html="$t('1. Put the file in your <code>quake3/defrag/</code> folder.')" class="[&_code]:rounded [&_code]:bg-black/40 [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs [&_code]:text-blue-300"></li>
                                    <li v-html="$t('2. Start the game and type <code>/exec defrag-racing-rules.cfg</code> in the console.')" class="[&_code]:rounded [&_code]:bg-black/40 [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs [&_code]:text-blue-300"></li>
                                    <li>{{ $t('3. It does not replace your own config. It sets its values on top of it.') }}</li>
                                </ol>
                            </div>
                        </div>

                        <p class="text-sm text-gray-400">
                            {{ $t('Not sure what your own settings say? Drop a demo into the checker at the bottom of this page. It reads the file and tells you, and keeps nothing.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end border-t border-white/10 px-6 py-4">
                        <button type="button" @click="$emit('close')"
                                class="rounded-lg border border-white/10 bg-gray-700/40 px-5 py-2.5 text-sm font-bold text-gray-300 transition hover:bg-gray-600/50 hover:text-white">
                            {{ $t('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
