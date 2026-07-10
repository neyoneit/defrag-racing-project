<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

// Sitewide nudge toward the contest while one is live, fed by the
// `defragliveContest` shared Inertia prop (cached). Three layouts:
//  - variant="floating" (default): dismissible fixed card, bottom-left.
//  - variant="bar": a full-width one-line strip to drop into the footer area.
//  - variant="hero": in-flow card with the prize and a segmented live
//    countdown, made for the homepage. Not dismissible - it always shows
//    the currently active contest (renders nothing when none is live).
const props = defineProps({
    variant: { type: String, default: 'floating' },
});

const page = usePage();
const contest = computed(() => page.props.defragliveContest || null);

// Only the floating card is dismissible (per-contest, via localStorage).
const dismissed = ref(false);
const storageKey = computed(() => contest.value ? `dl_contest_banner_dismissed_${contest.value.id}` : null);

const now = ref(Date.now());
let timer = null;
onMounted(() => {
    if (props.variant === 'floating' && storageKey.value && localStorage.getItem(storageKey.value) === '1') {
        dismissed.value = true;
    }
    timer = setInterval(() => { now.value = Date.now(); }, 1000);
});
onUnmounted(() => { if (timer) clearInterval(timer); });

const dismiss = () => {
    dismissed.value = true;
    if (storageKey.value) localStorage.setItem(storageKey.value, '1');
};

const timeLeft = computed(() => {
    if (!contest.value?.ends_at) return null;
    const ms = new Date(contest.value.ends_at).getTime() - now.value;
    if (ms <= 0) return null;
    const s = Math.floor(ms / 1000);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h left`;
    if (h > 0) return `${h}h ${m}m left`;
    return `${m}m left`;
});

const prize = computed(() => {
    if (!contest.value) return '';
    const c = contest.value;
    return (c.prize_currency === 'USD' ? '$' : '') + c.prize_amount + (c.prize_currency !== 'USD' ? ' ' + c.prize_currency : '');
});

// Segmented countdown for the hero variant's ticker boxes.
const countdown = computed(() => {
    if (!contest.value?.ends_at) return null;
    const ms = new Date(contest.value.ends_at).getTime() - now.value;
    if (ms <= 0) return null;
    const s = Math.floor(ms / 1000);
    return {
        d: Math.floor(s / 86400),
        h: Math.floor((s % 86400) / 3600),
        m: Math.floor((s % 3600) / 60),
        s: s % 60,
    };
});
</script>

<template>
    <!-- Homepage hero card: prize + segmented live countdown -->
    <Link v-if="variant === 'hero' && contest" href="/defraglive/contest"
        class="group relative z-10 flex items-center gap-4 flex-wrap justify-center sm:justify-between px-5 py-3.5 bg-purple-950/70 backdrop-blur-sm border border-[#9147ff]/50 rounded-xl hover:bg-purple-900/60 hover:border-[#9147ff]/80 hover:shadow-[0_0_50px_rgba(145,71,255,0.35)] transition-all shadow-[0_4px_20px_rgba(0,0,0,0.4),0_0_30px_rgba(145,71,255,0.2)]">
        <div class="absolute -top-2 -right-2 px-2.5 py-0.5 bg-gradient-to-r from-[#9147ff] to-fuchsia-500 rounded-full text-[10px] font-black text-white shadow-lg">LIVE</div>
        <div class="flex items-center gap-3 min-w-0">
            <span class="text-2xl leading-none">🏆</span>
            <div class="min-w-0">
                <div class="text-xs uppercase font-bold tracking-wider text-purple-300">DefragLive Contest</div>
                <div class="text-sm font-bold text-white truncate">
                    Most watched player wins <span class="text-emerald-400">{{ prize }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div v-if="countdown" class="flex gap-1.5 font-mono">
                <div v-for="part in [['d', countdown.d], ['h', countdown.h], ['m', countdown.m], ['s', countdown.s]]" :key="part[0]"
                    class="bg-black/40 rounded-lg px-2 py-1 text-center min-w-[42px]">
                    <div class="text-lg font-black text-white tabular-nums leading-none">{{ String(part[1]).padStart(2, '0') }}</div>
                    <div class="text-[9px] font-bold uppercase tracking-widest text-purple-300">{{ part[0] }}</div>
                </div>
            </div>
            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform flex-shrink-0 text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </Link>

    <!-- Full-width one-line strip (footer) -->
    <Link v-else-if="variant === 'bar' && contest" href="/defraglive/contest"
        class="group block border-y border-[#9147ff]/30 bg-[#9147ff]/10 hover:bg-[#9147ff]/20 transition-colors">
        <div class="max-w-8xl mx-auto px-4 py-2.5 flex items-center justify-center gap-x-3 gap-y-1 flex-wrap text-sm text-center">
            <span class="text-lg leading-none">🏆</span>
            <span class="font-bold text-white">DefragLive Contest is live</span>
            <span class="text-gray-300">- win <span class="text-emerald-400 font-bold">{{ prize }}</span> as the most watched player</span>
            <span v-if="timeLeft" class="text-amber-300 font-semibold">&middot; {{ timeLeft }}</span>
            <span class="text-[#bf94ff] font-semibold group-hover:underline">Enter now -&gt;</span>
        </div>
    </Link>

    <!-- Floating card (bottom-left, dismissible) -->
    <Transition v-else
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="translate-y-24 opacity-0"
        enter-to-class="translate-y-0 opacity-100">
        <Link v-if="contest && !dismissed" href="/defraglive/contest"
            class="group fixed bottom-4 left-4 z-[150] w-[260px] rounded-xl border border-[#9147ff]/40 bg-black/70 backdrop-blur-md shadow-2xl overflow-hidden hover:border-[#9147ff]/80 transition-colors">
            <div class="h-1 bg-[#9147ff]"></div>

            <button type="button" @click.prevent.stop="dismiss"
                class="absolute top-2 right-2 z-10 text-gray-500 hover:text-white w-5 h-5 flex items-center justify-center rounded hover:bg-white/10"
                title="Dismiss">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>

            <div class="p-3.5">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🏆</span>
                    <div class="text-[11px] uppercase tracking-widest font-bold text-purple-300">Contest live</div>
                </div>
                <div class="mt-1 font-black text-white leading-tight">
                    Win <span class="text-emerald-400">{{ prize }}</span> - most watched player
                </div>
                <div class="mt-1.5 flex items-center justify-between text-xs">
                    <span v-if="timeLeft" class="text-amber-300 font-semibold">{{ timeLeft }}</span>
                    <span class="text-[#bf94ff] font-semibold group-hover:underline ml-auto">Enter now -&gt;</span>
                </div>
            </div>
        </Link>
    </Transition>
</template>
