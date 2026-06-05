<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, getCurrentInstance } from 'vue';

const props = defineProps({
    blocks: Array,
});

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

// Tick so the open (live) block's duration keeps counting up.
const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => { if (timer) clearInterval(timer); });

const photo = (u) => u?.profile_photo_path ? '/storage/' + u.profile_photo_path : '/images/null.jpg';

const fmtDur = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s % 60}s`;
    return `${s}s`;
};

const time = (iso) => iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
const day = (iso) => iso ? new Date(iso).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '';

const liveDur = (b) => b.live
    ? Math.max(0, Math.floor((now.value - new Date(b.started_at).getTime()) / 1000))
    : b.duration;

// Group blocks under day headers for a cleaner timeline.
const byDay = computed(() => {
    const out = [];
    let cur = null;
    for (const b of props.blocks || []) {
        const d = day(b.started_at);
        if (!cur || cur.day !== d) {
            cur = { day: d, blocks: [] };
            out.push(cur);
        }
        cur.blocks.push(b);
    }
    return out;
});
</script>

<template>
    <Head title="DefragLive Map Log" />

    <div class="max-w-5xl mx-auto px-4 md:px-6 py-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="text-xs uppercase tracking-widest text-purple-300/80 font-semibold mb-1">DefragLive</div>
            <h1 class="text-3xl md:text-4xl font-black text-white">Map log</h1>
            <p class="text-gray-400 mt-2 text-sm leading-relaxed max-w-2xl">
                What the bot has been streaming, map by map - which map ran from when to when,
                and the players it spectated along the way. Each block is one continuous stretch on a map.
            </p>
            <Link href="/defraglive/contest"
                class="inline-flex items-center gap-1.5 mt-3 text-sm font-semibold text-[#a970ff] hover:text-[#bf94ff] hover:underline">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to the Watch Contest
            </Link>
        </div>

        <div v-if="!blocks || blocks.length === 0"
            class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm p-10 text-center text-gray-500">
            Nothing logged yet.
        </div>

        <div v-else class="space-y-8">
            <div v-for="grp in byDay" :key="grp.day">
                <div class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 pl-1">{{ grp.day }}</div>

                <div class="space-y-3">
                    <div v-for="(b, i) in grp.blocks" :key="grp.day + '-' + i"
                        class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden shadow-lg hover:border-white/20 transition-colors">
                        <div class="flex flex-col sm:flex-row">
                            <!-- Map thumbnail -->
                            <div class="relative sm:w-56 shrink-0 aspect-video sm:aspect-auto bg-gray-950">
                                <img :src="b.map_thumbnail ? `/storage/${b.map_thumbnail}` : '/images/unknown.jpg'"
                                    class="absolute inset-0 w-full h-full object-cover opacity-80"
                                    onerror="this.onerror=null;this.src='/images/unknown.jpg'" alt="">
                                <div class="absolute inset-0 bg-gradient-to-t sm:bg-gradient-to-r from-black/80 via-black/20 to-transparent"></div>
                                <div v-if="b.live"
                                    class="absolute top-2 left-2 inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-red-400 bg-black/60 rounded px-1.5 py-0.5">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </span>
                                    Live
                                </div>
                                <div class="absolute bottom-2 left-3 right-3 sm:hidden">
                                    <div class="font-bold text-white truncate drop-shadow">{{ b.map }}</div>
                                </div>
                            </div>

                            <!-- Detail -->
                            <div class="flex-1 p-4 min-w-0">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <a :href="`/maps/${b.map}`"
                                            class="hidden sm:block font-bold text-white hover:text-[#a970ff] truncate transition-colors">{{ b.map }}</a>
                                        <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                                            <span>{{ time(b.started_at) }}</span>
                                            <span class="text-gray-600">&rarr;</span>
                                            <span v-if="b.live" class="text-red-400 font-semibold">now</span>
                                            <span v-else>{{ time(b.ended_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-sm font-bold text-white tabular-nums">{{ fmtDur(liveDur(b)) }}</div>
                                        <div class="text-[10px] uppercase tracking-wider text-gray-500">{{ b.players.length }} player{{ b.players.length === 1 ? '' : 's' }}</div>
                                    </div>
                                </div>

                                <!-- Players the bot spectated on this map -->
                                <div class="flex flex-wrap gap-1.5">
                                    <component :is="p.user ? Link : 'span'" v-for="(p, pi) in b.players" :key="pi"
                                        :href="p.user ? `/profile/${p.user.id}` : undefined"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-white/5 border border-white/10 pl-1.5 pr-2.5 py-1 text-sm"
                                        :class="p.user ? 'hover:bg-white/10 hover:border-white/20 transition-colors' : ''">
                                        <img v-if="p.user" :src="photo(p.user)" class="w-5 h-5 rounded-full object-cover" alt="">
                                        <img v-if="p.user?.country" :src="`/images/flags/${p.user.country}.png`" class="w-4 h-3 shrink-0" onerror="this.style.display='none'">
                                        <span v-html="q3tohtml(p.name)"></span>
                                        <span class="text-xs text-gray-500 tabular-nums">{{ fmtDur(p.seconds) }}</span>
                                    </component>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
