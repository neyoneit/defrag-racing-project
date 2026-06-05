<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, getCurrentInstance } from 'vue';

const props = defineProps({
    contest: Object,
    leaderboard: Array,
    totalTickets: Number,
    myEntry: Object,
    nowWatching: Object,
    pastWinners: Array,
    hallOfFame: Array,
});

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const TWITCH_CHANNEL = 'defraglive';

// Live countdown to the end of the period.
const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => { if (timer) clearInterval(timer); });

const timeLeft = computed(() => {
    if (!props.contest?.ends_at) return null;
    let ms = new Date(props.contest.ends_at).getTime() - now.value;
    if (ms <= 0) return { d: 0, h: 0, m: 0, s: 0, ended: true };
    const s = Math.floor(ms / 1000);
    return {
        d: Math.floor(s / 86400),
        h: Math.floor((s % 86400) / 3600),
        m: Math.floor((s % 3600) / 60),
        s: s % 60,
        ended: false,
    };
});

const fmtWatch = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s % 60}s`;
    return `${s}s`;
};

const maxSeconds = computed(() => Math.max(1, ...(props.leaderboard || []).map(e => e.seconds || 0)));
const odds = (tickets) => props.totalTickets > 0 ? (tickets / props.totalTickets * 100) : 0;
const photo = (u) => u?.profile_photo_path ? '/storage/' + u.profile_photo_path : '/images/null.jpg';
const rankColor = (i) => i === 0 ? 'text-yellow-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-amber-600' : 'text-gray-500';
</script>

<template>
    <Head title="DefragLive Watch Contest" />

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-8">
        <!-- Evergreen intro: what this is + live stream. Independent of any
             single contest, so the explainer is never duplicated per period. -->
        <div class="rounded-2xl border border-purple-500/20 bg-black/40 backdrop-blur-sm p-6 md:p-8 mb-6 shadow-2xl">
            <div class="grid lg:grid-cols-3 gap-6 lg:gap-8 items-start">
                <div class="lg:col-span-2">
                    <div class="text-xs uppercase tracking-widest text-purple-300/80 font-semibold mb-1">DefragLive Watch Contest</div>
                    <a :href="`https://twitch.tv/${TWITCH_CHANNEL}`" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#a970ff] hover:text-[#bf94ff] hover:underline mb-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.64 5.93h1.43v4.28h-1.43m3.93-4.28H17v4.28h-1.43M7 2L3.43 5.57v12.86h4.28V22l3.58-3.57h2.85L20.57 12V2m-1.43 9.29l-2.85 2.85h-2.86l-2.5 2.5v-2.5H7.71V3.43h11.43z"/></svg>
                        twitch.tv/{{ TWITCH_CHANNEL }}
                    </a>
                    <h1 class="text-3xl md:text-4xl font-black text-white">Get watched, get rewarded</h1>
                    <div class="text-gray-400 mt-3 text-sm space-y-2 leading-relaxed">
                        <p>
                            <span class="text-gray-200 font-semibold">DefragLive</span> streams live defrag runs to Twitch around the clock,
                            so anyone can experience defrag from anywhere - no install, no setup, just watch the action unfold.
                        </p>
                        <p>
                            This contest is a <span class="text-gray-200 font-semibold">reward for the players who make that possible</span>:
                            everyone who lets the bot spectate them is the one putting on the show for viewers around the world.
                            The more the bot watches you, the more raffle tickets you earn - but anyone with a ticket can win.
                        </p>
                        <p>
                            Curious what's been on? Browse the
                            <Link href="/defraglive/maps" class="text-[#a970ff] hover:text-[#bf94ff] font-semibold hover:underline">full map log</Link>
                            - every map the bot streamed, from when to when, and who it spectated.
                        </p>
                    </div>
                    <div class="mt-3 flex items-start gap-2 text-sm text-amber-100 bg-amber-500/20 border border-amber-400/40 rounded-lg px-3.5 py-2.5">
                        <svg class="w-5 h-5 shrink-0 mt-px text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>This prize comes <strong class="text-white">straight out of my own pocket</strong> - it is <strong class="text-white">not</strong> funded by the donations you send to support the site. 100% a personal gift to the community.</span>
                    </div>
                </div>

                <!-- Watch-live card - the whole thing links to Twitch. The
                     interactive DefragLive overlay (player list, controls) only
                     renders on twitch.tv itself (Twitch never shows extensions
                     inside an embedded player), so we show the current map +
                     who's being spectated from our own data and send people to
                     Twitch for the full experience. -->
                <a :href="`https://twitch.tv/${TWITCH_CHANNEL}`" target="_blank" rel="noopener"
                    class="group block rounded-xl border border-[#9147ff]/30 hover:border-[#9147ff]/70 bg-black/40 backdrop-blur-sm shadow-2xl overflow-hidden transition-colors">
                    <!-- Map thumbnail backdrop with the spectated player on top -->
                    <div class="relative aspect-video bg-gray-950">
                        <img :src="nowWatching?.map_thumbnail ? `/storage/${nowWatching.map_thumbnail}` : '/images/unknown.jpg'"
                            class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition duration-300"
                            onerror="this.onerror=null;this.src='/images/unknown.jpg'" alt="">
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

                        <div class="absolute top-3 left-3 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-red-400 bg-black/60 rounded px-2 py-1">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            </span>
                            Live now
                        </div>

                        <!-- Play affordance on hover -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                            <div class="w-16 h-16 rounded-full bg-[#9147ff]/90 flex items-center justify-center shadow-2xl">
                                <svg class="w-7 h-7 text-white ml-1" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                        </div>

                        <div class="absolute bottom-0 inset-x-0 p-4">
                            <template v-if="nowWatching">
                                <div class="text-[11px] uppercase tracking-wide text-gray-300">Now spectating</div>
                                <div class="text-2xl md:text-3xl font-black leading-tight" v-html="q3tohtml(nowWatching.name)"></div>
                                <div v-if="nowWatching.mapname" class="text-sm text-gray-300 mt-0.5">on <span class="font-semibold text-white">{{ nowWatching.mapname }}</span></div>
                            </template>
                            <template v-else>
                                <div class="text-xl font-black text-white">DefragLive</div>
                                <div class="text-sm text-gray-300">streaming defrag 24/7</div>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-col items-center text-center gap-2 p-4">
                        <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#9147ff] group-hover:bg-[#772ce8] text-white font-bold transition-colors w-full justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.64 5.93h1.43v4.28h-1.43m3.93-4.28H17v4.28h-1.43M7 2L3.43 5.57v12.86h4.28V22l3.58-3.57h2.85L20.57 12V2m-1.43 9.29l-2.85 2.85h-2.86l-2.5 2.5v-2.5H7.71V3.43h11.43z"/></svg>
                            Watch on Twitch
                        </span>
                        <div class="text-[11px] text-gray-500">The interactive overlay (player list, controls) works on Twitch.</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Current contest strip: the only part that rotates each period. -->
        <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm p-5 md:p-6 mb-6 shadow-2xl">
            <div v-if="contest" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Current contest</div>
                    <h2 class="text-xl md:text-2xl font-black text-white">{{ contest.title }}</h2>
                    <div v-if="nowWatching" class="mt-2 flex items-center gap-2 text-sm">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        </span>
                        <span class="text-gray-400">Now spectating</span>
                        <span class="font-bold" v-html="q3tohtml(nowWatching.name)"></span>
                        <span v-if="nowWatching.mapname" class="text-gray-500">on {{ nowWatching.mapname }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-6 shrink-0">
                    <div class="text-right">
                        <div class="text-3xl md:text-4xl font-black text-emerald-400">
                            {{ contest.prize_currency === 'USD' ? '$' : '' }}{{ contest.prize_amount }}{{ contest.prize_currency !== 'USD' ? ' ' + contest.prize_currency : '' }}
                        </div>
                        <div class="text-sm font-bold uppercase tracking-widest text-gray-300">prize</div>
                    </div>
                    <div v-if="timeLeft && !timeLeft.ended" class="flex gap-2.5 font-mono">
                        <div v-for="part in [['d', timeLeft.d], ['h', timeLeft.h], ['m', timeLeft.m], ['s', timeLeft.s]]" :key="part[0]"
                            class="bg-black/40 rounded-xl px-3.5 py-2 text-center min-w-[60px]">
                            <div class="text-3xl md:text-4xl font-black text-white tabular-nums leading-none">{{ String(part[1]).padStart(2, '0') }}</div>
                            <div class="text-sm font-bold uppercase tracking-widest text-gray-300 mt-1">{{ part[0] }}</div>
                        </div>
                    </div>
                    <div v-else-if="timeLeft" class="text-base text-amber-400 font-semibold">Period ended<br>awaiting draw</div>
                </div>
            </div>

            <div v-else class="text-center py-2 text-gray-400">
                <span class="font-semibold text-gray-300">No contest running right now.</span>
                The next one starts soon - keep getting spectated and you'll be in it.
            </div>
        </div>

        <!-- My odds -->
        <div v-if="myEntry" class="rounded-xl border border-emerald-500/25 bg-black/40 backdrop-blur-sm p-4 mb-6 flex flex-wrap items-center gap-x-8 gap-y-2">
            <div>
                <div class="text-xs uppercase text-gray-500">Your rank</div>
                <div class="text-2xl font-black text-white">#{{ myEntry.rank }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">Watched</div>
                <div class="text-2xl font-black text-white">{{ fmtWatch(myEntry.seconds) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">Tickets</div>
                <div class="text-2xl font-black text-white">{{ myEntry.tickets }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">Win chance</div>
                <div class="text-2xl font-black text-emerald-400">{{ myEntry.odds }}%</div>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden mb-8 shadow-2xl">
            <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
                <h2 class="font-bold text-gray-200">Most watched this period</h2>
                <span class="text-xs text-gray-500">{{ totalTickets }} tickets in the pool</span>
            </div>

            <div v-if="leaderboard.length" class="divide-y divide-white/5">
                <div v-for="(e, i) in leaderboard" :key="i" class="flex items-center gap-3 px-4 py-3 hover:bg-white/5">
                    <div class="w-7 text-center font-black text-lg" :class="rankColor(i)">{{ i + 1 }}</div>

                    <img :src="photo(e.user)" class="w-8 h-8 rounded-full object-cover shrink-0" alt="">
                    <img v-if="e.user?.country" :src="`/images/flags/${e.user.country}.png`" class="w-5 h-3.5 shrink-0" onerror="this.style.display='none'">

                    <div class="min-w-0 flex-1">
                        <component :is="e.user ? Link : 'span'" :href="e.user ? `/profile/${e.user.id}` : undefined"
                            class="font-semibold truncate block" :class="e.user ? 'hover:underline' : ''"
                            v-html="q3tohtml(e.name)"></component>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-emerald-400"
                                :style="{ width: Math.max(3, (e.seconds / maxSeconds) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="font-bold text-white tabular-nums">{{ fmtWatch(e.seconds) }}</div>
                        <div class="text-xs text-gray-500">{{ e.tickets }} tickets &middot; {{ odds(e.tickets).toFixed(1) }}%</div>
                    </div>
                </div>
            </div>

            <div v-else class="px-4 py-12 text-center text-gray-500">
                No watch time recorded yet this period. Hop on a server the bot is spectating!
            </div>
        </div>

        <!-- Past winners -->
        <div v-if="pastWinners.length">
            <h2 class="font-bold text-gray-300 mb-3">Past winners</h2>
            <div class="grid gap-2 sm:grid-cols-2">
                <div v-for="(w, i) in pastWinners" :key="i"
                    class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3">
                    <div class="min-w-0">
                        <component :is="w.winner_user_id ? Link : 'span'" :href="w.winner_user_id ? `/profile/${w.winner_user_id}` : undefined"
                            class="font-semibold truncate block" v-html="q3tohtml(w.winner_name)"></component>
                        <div class="text-xs text-gray-500 truncate">{{ w.title }}</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-emerald-400">{{ w.prize_currency === 'USD' ? '$' : '' }}{{ w.prize_amount }}</div>
                        <div class="text-[11px] uppercase" :class="w.status === 'paid' ? 'text-emerald-500' : 'text-amber-500'">{{ w.status }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hall of Fame - all-time winners -->
        <div v-if="hallOfFame.length" class="mt-8">
            <h2 class="font-bold text-gray-300 mb-3 flex items-center gap-2">
                <span>🏆</span> Hall of Fame <span class="text-xs font-normal text-gray-500">all-time winners</span>
            </h2>
            <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden shadow-2xl divide-y divide-white/5">
                <div v-for="(h, i) in hallOfFame" :key="i" class="flex items-center gap-3 px-4 py-3 hover:bg-white/5">
                    <div class="w-7 text-center font-black text-lg" :class="rankColor(i)">{{ i + 1 }}</div>
                    <img :src="photo(h.user)" class="w-8 h-8 rounded-full object-cover shrink-0" alt="">
                    <img v-if="h.user?.country" :src="`/images/flags/${h.user.country}.png`" class="w-5 h-3.5 shrink-0" onerror="this.style.display='none'">
                    <component :is="h.user ? Link : 'span'" :href="h.user ? `/profile/${h.user.id}` : undefined"
                        class="min-w-0 flex-1 font-semibold truncate" :class="h.user ? 'hover:underline' : ''"
                        v-html="q3tohtml(h.name)"></component>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-white">{{ h.wins }} {{ h.wins === 1 ? 'win' : 'wins' }}</div>
                        <div class="text-xs text-emerald-400">{{ h.currency === 'USD' ? '$' : '' }}{{ h.total.toFixed(2) }}{{ h.currency !== 'USD' ? ' ' + h.currency : '' }} won</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
