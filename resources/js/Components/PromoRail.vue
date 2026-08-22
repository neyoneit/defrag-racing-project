<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatPrize } from '@/utils/currency';
import { t } from '@/utils/i18n';
import CompsBanner from '@/Components/CompsBanner.vue';

// What is running right now and pays money, parked in the corner of the page
// people actually sit on. The servers list is where somebody is when they are
// about to play, which is the moment a weekly map or a watch contest is worth
// knowing about - the comps page only reaches people already going there.
//
// Both cards are fed by shared Inertia props, so this costs the page nothing
// and renders nothing at all when neither is on. Each is dismissed on its own
// and comes back for the next round or contest, since the key carries the id.
//
// The comps card is CompsBanner: the homepage shows the same round as a hero,
// and one card living in two shapes beats two cards that drift apart.
const page = usePage();

const comps = computed(() => page.props.compsBanner || null);
const contest = computed(() => page.props.defragliveContest || null);

const now = ref(Date.now());
let timer = null;

// The cross means "not now", not "never again". A contest runs for a fortnight,
// so a permanent dismissal would hide the prize for the whole thing over one
// click at the wrong moment - and the money is the point. It holds for three
// days; a new contest brings the card straight back anyway, because the key
// carries the id.
const DISMISS_DAYS = 3;

// Starts hidden and is let through on mount, so somebody who dismissed the card
// does not watch it flash up and vanish on every page load.
const dismissedContest = ref(true);

const contestKey = computed(() => contest.value ? `promo_contest_dismissed_${contest.value.id}` : null);

// When it was dismissed, not that it was. Anything unreadable - including the
// bare "1" the older sitewide banner wrote - simply counts as not dismissed.
const isDismissed = (key) => {
    if (!key) return false;

    const at = Number(localStorage.getItem(key));

    return at > 0 && Date.now() - at < DISMISS_DAYS * 86400000;
};

onMounted(() => {
    dismissedContest.value = isDismissed(contestKey.value);

    timer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => { if (timer) clearInterval(timer); });

const dismissContest = () => {
    dismissedContest.value = true;

    if (contestKey.value) localStorage.setItem(contestKey.value, String(Date.now()));
};

// One key with the whole "3d 7h" already in it. Splitting it per unit would
// glue the d/h/m letters onto the placeholder names, which survives t() and
// then quietly traps whoever translates the line.
const contestLeft = computed(() => {
    if (!contest.value?.ends_at) return null;

    const ms = new Date(contest.value.ends_at).getTime() - now.value;
    if (ms <= 0) return null;

    const s = Math.floor(ms / 1000);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);

    if (d > 0) return t(':time left', { time: `${d}d ${h}h` });
    if (h > 0) return t(':time left', { time: `${h}h ${m}m` });

    return t(':time left', { time: `${m}m` });
});

const contestPrize = computed(() => contest.value
    ? formatPrize(contest.value.prize_amount, contest.value.prize_currency)
    : '');

const showContest = computed(() => contest.value && !dismissedContest.value);
</script>

<template>
    <!-- Hidden below xl: a fixed card over a two-column server grid covers the
         thing the visitor came for. -->
    <div v-if="comps || contest"
        class="hidden xl:flex fixed bottom-4 right-4 z-[150] w-[268px] flex-col gap-3">

        <CompsBanner variant="card" />

        <Transition
            enter-active-class="transition duration-500 ease-out delay-150"
            enter-from-class="translate-y-24 opacity-0"
            enter-to-class="translate-y-0 opacity-100">
            <Link v-if="showContest" href="/defraglive/contest"
                class="group relative rounded-xl border border-[#9147ff]/40 bg-black/70 backdrop-blur-md shadow-2xl overflow-hidden hover:border-[#9147ff]/80 transition-colors">
                <div class="h-1 bg-[#9147ff]"></div>

                <button type="button" @click.prevent.stop="dismissContest"
                    class="absolute top-2 right-2 z-10 text-gray-500 hover:text-white w-5 h-5 flex items-center justify-center rounded hover:bg-white/10"
                    :title="$t('Dismiss')">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>

                <div class="p-3.5">
                    <div class="flex items-center gap-2">
                        <span class="text-xl leading-none">🏆</span>
                        <div class="text-[11px] uppercase tracking-widest font-bold text-purple-300">{{ $t('Contest live') }}</div>
                    </div>

                    <div class="mt-1 font-black text-white leading-tight">{{ $t('Most watched player wins') }}</div>

                    <div class="mt-2 text-2xl font-black tabular-nums text-emerald-300 leading-none">{{ contestPrize }}</div>

                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span v-if="contestLeft" class="text-amber-300 font-semibold">{{ contestLeft }}</span>
                        <span class="text-[#bf94ff] font-semibold group-hover:underline ml-auto">{{ $t('Enter now ->') }}</span>
                    </div>
                </div>
            </Link>
        </Transition>
    </div>
</template>
