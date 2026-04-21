<script setup>
    import { ref, computed } from 'vue';
    import axios from 'axios';
    import MapRecord from '@/Components/MapRecord.vue';

    const props = defineProps({
        mapname: { type: String, required: true },
        demoId: { type: [Number, String], required: true },
        physics: { type: String, required: true }, // 'vq3' | 'cpm'
        attemptsCount: { type: Number, default: null },
        // Pre-computed signal strength from client-side grouping (0..3).
        // Used as an initial value before the drawer is opened; the API
        // response may refine it once loaded.
        signalsCount: { type: Number, default: 0 },
    });

    const expanded = ref(false);
    const loading = ref(false);
    const loaded = ref(false);
    const error = ref(null);
    const history = ref([]);
    const seedDemoId = ref(null);
    const signals = ref(props.signalsCount || 0);

    const toggle = async () => {
        expanded.value = !expanded.value;
        if (expanded.value && !loaded.value && !loading.value) {
            loading.value = true;
            error.value = null;
            try {
                const { data } = await axios.get(`/maps/${encodeURIComponent(props.mapname)}/time-history`, {
                    params: {
                        demo_id: props.demoId,
                        physics: props.physics.toLowerCase(),
                    },
                });
                history.value = data.history || [];
                seedDemoId.value = data.seed_demo_id;
                signals.value = data.signals || 0;
                loaded.value = true;
            } catch (e) {
                error.value = e.response?.data?.error || 'Failed to load time history';
            } finally {
                loading.value = false;
            }
        }
    };

    const signalBadge = computed(() => {
        if (signals.value >= 3) return { label: '3/3', color: 'bg-emerald-500/20 text-emerald-300' };
        if (signals.value === 2) return { label: '2/3', color: 'bg-cyan-500/20 text-cyan-300' };
        if (signals.value === 1) return { label: '1/3', color: 'bg-yellow-500/20 text-yellow-300' };
        return null;
    });

    // Custom tooltip (Teleported to body to avoid clipping and match the
    // app's tooltip style from MapRecord). Delayed 200ms to avoid flicker.
    const showTooltip = ref(false);
    const tooltipStyle = ref({});
    const buttonRef = ref(null);
    let hoverTimeout = null;

    const onMouseEnter = () => {
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => {
            const el = buttonRef.value;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            tooltipStyle.value = {
                position: 'fixed',
                left: rect.left + rect.width / 2 + 'px',
                top: rect.top - 8 + 'px',
                transform: 'translate(-50%, -100%)',
                zIndex: 9999,
            };
            showTooltip.value = true;
        }, 200);
    };

    const onMouseLeave = () => {
        clearTimeout(hoverTimeout);
        showTooltip.value = false;
    };
</script>

<template>
    <div class="time-history-wrapper">
        <!-- Half-line toggle (always present, subtle) -->
        <button
            ref="buttonRef"
            @click.stop="toggle"
            @mouseenter="onMouseEnter"
            @mouseleave="onMouseLeave"
            class="w-full flex items-center justify-center gap-1.5 py-1 text-[10px] text-gray-600 hover:text-gray-300 hover:bg-white/5 transition-colors border-t border-white/[0.03]"
        >
            <svg class="w-2.5 h-2.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
            <span class="uppercase tracking-wider">time history</span>
            <span v-if="signalBadge" :class="['px-1 py-[1px] rounded text-[9px] font-bold', signalBadge.color]">
                {{ signalBadge.label }}
            </span>
            <span v-if="loaded && history.length" class="text-gray-500">· {{ history.length }}</span>
            <span v-else-if="!loaded && attemptsCount && attemptsCount > 1" class="text-gray-500">· {{ attemptsCount }}</span>
        </button>

        <!-- Custom tooltip -->
        <Teleport to="body">
            <div
                v-if="showTooltip"
                :style="tooltipStyle"
                class="pointer-events-none"
            >
                <div class="bg-gray-900/95 backdrop-blur-sm border border-white/20 rounded-lg shadow-2xl px-3 py-2.5 max-w-[280px]">
                    <div class="flex items-center gap-2 mb-1.5">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span class="text-sm font-bold text-white">Time History</span>
                    </div>
                    <p class="text-[11px] text-gray-300 leading-snug mb-2">
                        All demo attempts by this player on this map, reconstructed by matching
                        <span class="text-gray-100 font-semibold">player name</span>,
                        <span class="text-gray-100 font-semibold">q3df login (colored)</span>
                        and <span class="text-gray-100 font-semibold">q3df login (plain)</span>.
                    </p>

                    <!-- Signal strength legend -->
                    <div v-if="signalBadge && loaded" class="border-t border-white/10 pt-1.5 mt-1.5">
                        <div class="flex items-center gap-1.5">
                            <span :class="['px-1 py-[1px] rounded text-[9px] font-bold', signalBadge.color]">{{ signalBadge.label }}</span>
                            <span class="text-[10px] text-gray-400">signals matched</span>
                        </div>
                        <div class="text-[10px] text-gray-500 leading-snug mt-1">
                            <template v-if="signals >= 3">All three fields match exactly — strongest confidence.</template>
                            <template v-else-if="signals === 2">Two of three fields match — high confidence.</template>
                            <template v-else-if="signals === 1">Only one field matches (typically legacy player name for pre-q3df-login demos).</template>
                        </div>
                    </div>

                    <!-- Count -->
                    <div v-if="(loaded && history.length) || (attemptsCount && attemptsCount > 1)" class="border-t border-white/10 pt-1.5 mt-1.5">
                        <div class="text-[10px] text-gray-400">
                            <span class="text-white font-bold">{{ loaded ? history.length : attemptsCount }}</span>
                            demos grouped as the same virtual player
                        </div>
                    </div>

                    <div v-if="!loaded" class="border-t border-white/10 pt-1.5 mt-1.5 text-[10px] text-gray-500 italic">
                        Click to load & expand
                    </div>
                </div>

                <!-- Triangle pointer -->
                <div class="absolute left-1/2 -translate-x-1/2 top-full w-0 h-0 border-l-[6px] border-r-[6px] border-t-[6px] border-l-transparent border-r-transparent border-t-white/20"></div>
            </div>
        </Teleport>

        <!-- Expanded content — reuses <MapRecord> so all chips, render button,
             YouTube, report/flag actions behave identically to the leaderboard rows. -->
        <div v-if="expanded" class="bg-black/20 pl-3">
            <div v-if="loading" class="h-[24px] flex items-center justify-center gap-1.5 text-[10px] text-gray-500">
                <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading
            </div>

            <div v-else-if="error" class="h-[24px] flex items-center justify-center text-[10px] text-red-400">{{ error }}</div>

            <div v-else-if="!history.length" class="h-[24px] flex items-center justify-center text-[10px] text-gray-500">No matching demos found</div>

            <div v-else>
                <div
                    v-for="entry in history"
                    :key="entry.demo_id"
                    :class="{ 'bg-white/[0.02]': entry.demo_id === seedDemoId }"
                >
                    <MapRecord
                        :record="entry"
                        :physics="physics.toUpperCase()"
                        :showSourceChips="true"
                        :hideRank="true"
                        :compact="true"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
