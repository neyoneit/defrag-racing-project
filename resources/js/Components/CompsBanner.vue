<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { t } from '@/utils/i18n';

// What the weekly is doing right now, fed by the `compsBanner` shared Inertia
// prop (cached). Two layouts:
//  - variant="card" (default): the dismissible corner card the servers page
//    puts in its promo rail.
//  - variant="hero": in-flow card with the prize, the maps and a segmented
//    live countdown, made for the homepage. Not dismissible, the same way the
//    contest hero is not: it is one card in the flow of the page rather than
//    something parked over what you came to read.
//
// Renders nothing at all when no weekly round is running.
const props = defineProps({
    variant: { type: String, default: 'card' },
});

const page = usePage();
const comps = computed(() => page.props.compsBanner || null);

const now = ref(Date.now());
let timer = null;

// The cross means "not now", not "never again". A round runs for the better
// part of a week, so a permanent dismissal would hide the prize for the whole
// thing over one click at the wrong moment - and the money is the point. It
// holds for three days; a new round brings the card straight back anyway,
// because the key carries the id.
const DISMISS_DAYS = 3;

// Starts hidden and is let through on mount, so somebody who dismissed the
// card does not watch it flash up and vanish on every page load. The hero is
// never dismissed, so it starts visible.
const dismissed = ref(props.variant === 'card');

// The key the promo rail has always written, so a card dismissed before this
// component existed stays dismissed.
const storageKey = computed(() => comps.value ? `promo_comps_dismissed_${comps.value.round_id}` : null);

onMounted(() => {
    if (props.variant === 'card') {
        // When it was dismissed, not that it was. Anything unreadable -
        // including the bare "1" the older sitewide banner wrote - simply
        // counts as not dismissed.
        const at = Number(storageKey.value ? localStorage.getItem(storageKey.value) : 0);

        dismissed.value = at > 0 && Date.now() - at < DISMISS_DAYS * 86400000;
    }

    timer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => { if (timer) clearInterval(timer); });

const dismiss = () => {
    dismissed.value = true;

    if (storageKey.value) localStorage.setItem(storageKey.value, String(Date.now()));
};

const msLeft = computed(() => {
    if (!comps.value?.until) return null;

    const ms = new Date(comps.value.until).getTime() - now.value;

    return ms > 0 ? ms : null;
});

// One key with the whole "3d 7h" already in it. Splitting it per unit would
// glue the d/h/m letters onto the placeholder names, which survives t() and
// then quietly traps whoever translates the line.
const timeLeft = computed(() => {
    if (msLeft.value === null) return null;

    const s = Math.floor(msLeft.value / 1000);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);

    if (d > 0) return t(':time left', { time: `${d}d ${h}h` });
    if (h > 0) return t(':time left', { time: `${h}h ${m}m` });

    return t(':time left', { time: `${m}m` });
});

// Segmented countdown for the hero's ticker boxes.
const countdown = computed(() => {
    if (msLeft.value === null) return null;

    const s = Math.floor(msLeft.value / 1000);

    return {
        d: Math.floor(s / 86400),
        h: Math.floor((s % 86400) / 3600),
        m: Math.floor((s % 3600) / 60),
        s: s % 60,
    };
});

// A ballot that has closed but whose round has not flipped to active yet still
// has the voting status, and a countdown that ran out reads as broken. The card
// stays up either way - what it pays is the point, the timer is a detail.
const votingOpen = computed(() => comps.value?.state === 'voting' && timeLeft.value !== null);

const maps = computed(() => comps.value?.maps || {});
const hasMaps = computed(() => Object.keys(maps.value).length > 0);

const show = computed(() => comps.value && !dismissed.value);
</script>

<template>
    <!-- Homepage hero card: prize, maps and a segmented live countdown. Built
         to sit under the contest hero and read as its sibling, so it keeps that
         card's shape and swaps purple for the green comps already uses. -->
    <Link v-if="variant === 'hero' && comps" href="/comps"
        class="group relative z-10 flex items-center gap-4 flex-wrap justify-center sm:justify-between px-5 py-3.5 bg-emerald-950/70 backdrop-blur-sm border border-emerald-400/50 rounded-xl hover:bg-emerald-900/60 hover:border-emerald-400/80 hover:shadow-[0_0_50px_rgba(52,211,153,0.35)] transition-all shadow-[0_4px_20px_rgba(0,0,0,0.4),0_0_30px_rgba(52,211,153,0.2)]">
        <div class="absolute -top-2 -right-2 px-2.5 py-0.5 bg-gradient-to-r from-emerald-400 to-teal-400 rounded-full text-[10px] font-black text-emerald-950 shadow-lg">{{ $t('LIVE') }}</div>

        <div class="flex items-center gap-3 min-w-0">
            <span class="text-2xl leading-none">🏁</span>
            <div class="min-w-0">
                <div class="text-xs uppercase font-bold tracking-wider text-emerald-300">{{ $t('Weekly comps') }}</div>
                <div class="text-sm font-bold text-white truncate">
                    {{ votingOpen ? $t('Vote on the next map') : $t('Playing now') }}
                </div>
            </div>
        </div>

        <!-- The maps are the reason to click while a round is being played:
             knowing there is a comp on is not the same as knowing whether it is
             on a map you like. A ballot has none yet, which is why the payload
             sends an empty list rather than last week's. -->
        <div v-if="hasMaps" class="flex items-center gap-2 flex-wrap justify-center min-w-0">
            <span v-for="(map, physics) in maps" :key="physics"
                class="inline-flex items-center gap-1.5 rounded-lg bg-black/40 px-2.5 py-1 text-xs min-w-0">
                <span class="uppercase font-black text-emerald-300/70 flex-shrink-0">{{ physics }}</span>
                <span class="text-gray-200 font-semibold truncate">{{ map }}</span>
            </span>
        </div>

        <div class="flex items-center gap-3">
            <div v-if="comps.total > 0" class="text-right leading-none">
                <div class="text-lg font-black tabular-nums text-emerald-300">{{ comps.total }} EUR</div>
                <div class="mt-0.5 text-[9px] text-emerald-100/50 tabular-nums">{{ $t('(:amount EUR per physics)', { amount: comps.per_physics }) }}</div>
            </div>

            <div v-if="countdown" class="flex gap-1.5 font-mono">
                <div v-for="part in [['d', countdown.d], ['h', countdown.h], ['m', countdown.m], ['s', countdown.s]]" :key="part[0]"
                    class="bg-black/40 rounded-lg px-2 py-1 text-center min-w-[42px]">
                    <div class="text-lg font-black text-white tabular-nums leading-none">{{ String(part[1]).padStart(2, '0') }}</div>
                    <div class="text-[9px] font-bold uppercase tracking-widest text-emerald-300">{{ part[0] }}</div>
                </div>
            </div>

            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform flex-shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </Link>

    <!-- Corner card (promo rail, dismissible) -->
    <Transition v-else
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="translate-y-24 opacity-0"
        enter-to-class="translate-y-0 opacity-100">
        <Link v-if="show" href="/comps"
            class="group relative rounded-xl border border-emerald-400/40 bg-black/70 backdrop-blur-md shadow-2xl overflow-hidden hover:border-emerald-400/80 transition-colors">
            <div class="h-1 bg-emerald-400"></div>

            <button type="button" @click.prevent.stop="dismiss"
                class="absolute top-2 right-2 z-10 text-gray-500 hover:text-white w-5 h-5 flex items-center justify-center rounded hover:bg-white/10"
                :title="$t('Dismiss')">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>

            <div class="p-3.5">
                <div class="flex items-center gap-2">
                    <span class="text-xl leading-none">🏁</span>
                    <div class="text-[11px] uppercase tracking-widest font-bold text-emerald-300">{{ $t('Weekly comps') }}</div>
                </div>

                <div class="mt-1 font-black text-white leading-tight">
                    {{ votingOpen ? $t('Vote on the next map') : $t('Playing now') }}
                </div>

                <div v-if="comps.total > 0" class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-black tabular-nums text-emerald-300 leading-none">{{ comps.total }} EUR</span>
                    <span class="text-[10px] text-emerald-100/50 tabular-nums">{{ $t('(:amount EUR per physics)', { amount: comps.per_physics }) }}</span>
                </div>

                <div v-if="hasMaps" class="mt-2 space-y-0.5">
                    <div v-for="(map, physics) in maps" :key="physics"
                        class="flex items-center gap-1.5 text-[11px] min-w-0">
                        <span class="uppercase font-bold text-gray-500 w-7 flex-shrink-0">{{ physics }}</span>
                        <span class="text-gray-300 truncate">{{ map }}</span>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                    <span v-if="timeLeft" class="text-amber-300 font-semibold">{{ timeLeft }}</span>
                    <span class="text-emerald-300 font-semibold group-hover:underline ml-auto">
                        {{ votingOpen ? $t('Vote now ->') : $t('Enter now ->') }}
                    </span>
                </div>
            </div>
        </Link>
    </Transition>
</template>
