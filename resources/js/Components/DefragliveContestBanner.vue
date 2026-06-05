<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

// Sitewide nudge toward the contest while one is live, fed by the
// `defragliveContest` shared Inertia prop (cached). Two layouts:
//  - variant="floating" (default): dismissible fixed card, bottom-left.
//  - variant="bar": a full-width one-line strip to drop into the footer area.
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
</script>

<template>
    <!-- Full-width one-line strip (footer) -->
    <Link v-if="variant === 'bar' && contest" href="/defraglive/contest"
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
