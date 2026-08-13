<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { t } from '@/utils/i18n';
    import { formatTime } from '@/utils/time';

    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';

    // A finished comp, opened from the history list. Standings only - the
    // ballot, the countdown and the upload form all belonged to a week that is
    // over.
    defineProps({
        comp: { type: Object, required: true },
    });

    const PHYSICS = ['cpm', 'vq3'];

    const CATEGORY_LABELS = {
        strafe: () => t('Strafe'),
        weapon: () => t('Weapon'),
        combo: () => t('Combo'),
    };

    const categoryLabel = (category) => (CATEGORY_LABELS[category] ?? (() => category))();

    const decidedLabel = (by) => ({
        wildcard: () => t('Chosen with a wildcard'),
        carried: () => t('Nobody voted in this physics, so it took the other one\'s map'),
        random: () => t('Nobody voted at all, so it was drawn at random'),
    }[by] ?? (() => t('Chosen by vote')))();
</script>

<template>
    <Head :title="comp.title" />

    <div class="">
        <!-- Same header shape as the hub and the rest of the site. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('comps.index')" class="text-sm text-gray-400 hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                    &larr; {{ $t('Comps') }}
                </Link>
                <h1 class="mt-2 text-2xl md:text-3xl font-black text-gray-300/90">{{ comp.title }}</h1>
            </div>
        </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-8" style="margin-top: -22rem;">

        <section v-for="round in comp.rounds" :key="round.id" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-white/10 bg-white/5 backdrop-blur-sm px-5 py-3">
                <span v-if="comp.type === 'season'" class="rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-gray-300">
                    {{ $t('Round :n', { n: round.index }) }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ categoryLabel(round.category) }}<template v-if="round.weapon"> ({{ round.weapon }})</template>
                </span>
            </div>

            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div v-for="physics in PHYSICS" :key="physics">
                    <div class="mb-3">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ physics }}</div>
                        <Link
                            v-if="round.maps?.[physics]?.name"
                            :href="route('maps.map', round.maps[physics].name)"
                            class="font-bold text-white hover:text-blue-300 transition-colors"
                        >
                            {{ round.maps[physics].name }}
                        </Link>
                        <span v-else class="text-gray-600">-</span>
                        <div v-if="round.maps?.[physics]" class="text-[11px] text-gray-600">
                            {{ decidedLabel(round.maps[physics].decided_by) }}
                        </div>
                    </div>

                    <table v-if="round.results?.[physics]?.length" class="w-full text-sm">
                        <thead class="text-[10px] uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-1 text-left font-bold w-8">#</th>
                                <th class="py-1 text-left font-bold">{{ $t('Player') }}</th>
                                <th class="py-1 text-right font-bold">{{ $t('Time') }}</th>
                                <th v-if="comp.type === 'season'" class="py-1 text-right font-bold w-14">{{ $t('Points') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(row, i) in round.results[physics]" :key="i">
                                <td class="py-1.5 font-bold tabular-nums" :class="row.rank === 1 ? 'text-amber-300' : 'text-gray-500'">
                                    {{ row.rank }}
                                </td>
                                <td class="py-1.5"><CompsPlayer :player="row.user" size="sm" /></td>
                                <td class="py-1.5 text-right font-bold tabular-nums text-white">{{ formatTime(row.time) }}</td>
                                <td v-if="comp.type === 'season'" class="py-1.5 text-right tabular-nums text-gray-400">{{ row.points }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-600">{{ $t('Nobody entered.') }}</p>
                </div>
            </div>
        </section>
        </div>
    </div>
</template>
