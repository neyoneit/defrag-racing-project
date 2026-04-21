<script setup>
    import { ref, computed, onMounted } from 'vue';
    import axios from 'axios';
    import MapRecord from '@/Components/MapRecord.vue';

    // Drawer-only component. The trigger icon lives in MapRecord's chip row
    // and the parent (MapView) renders this only when the row is expanded.
    // Auto-loads history on mount so opening the drawer feels instant.
    const props = defineProps({
        mapname: { type: String, required: true },
        demoId: { type: [Number, String], default: null },
        userId: { type: [Number, String], default: null },
        mddId: { type: [Number, String], default: null },
        physics: { type: String, required: true }, // 'vq3' | 'cpm'
        // Hint of how many attempts will appear — used for the header count
        // before the request resolves.
        attemptsCount: { type: Number, default: null },
        signalsCount: { type: Number, default: 0 },
    });

    const loading = ref(true);
    const loaded = ref(false);
    const error = ref(null);
    const history = ref([]);
    const seedDemoId = ref(null);
    const signals = ref(props.signalsCount || 0);

    const signalBadge = computed(() => {
        if (signals.value >= 3) return { label: '3/3', color: 'bg-emerald-500/20 text-emerald-300' };
        if (signals.value === 2) return { label: '2/3', color: 'bg-cyan-500/20 text-cyan-300' };
        if (signals.value === 1) return { label: '1/3', color: 'bg-yellow-500/20 text-yellow-300' };
        return null;
    });

    const load = async () => {
        loading.value = true;
        error.value = null;
        try {
            const params = { physics: props.physics.toLowerCase() };
            if (props.demoId) params.demo_id = props.demoId;
            else if (props.userId) params.user_id = props.userId;
            else if (props.mddId) params.mdd_id = props.mddId;
            const { data } = await axios.get(`/maps/${encodeURIComponent(props.mapname)}/time-history`, { params });
            history.value = data.history || [];
            seedDemoId.value = data.seed_demo_id;
            signals.value = data.signals || 0;
            loaded.value = true;
        } catch (e) {
            error.value = e.response?.data?.error || 'Failed to load time history';
        } finally {
            loading.value = false;
        }
    };

    onMounted(load);
</script>

<template>
    <div class="bg-black/25 border-l-2 border-blue-500/30">
        <!-- Inline explanation header so the match source is obvious without
             hovering anywhere. Used to be a floating tooltip — now it's
             always visible inside the opened drawer. -->
        <div class="px-3 py-2 border-b border-white/[0.04] flex flex-wrap items-center gap-2 text-[10px] text-gray-400">
            <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="font-bold text-white uppercase tracking-wider">Time History</span>

            <!-- Match source badge + description are only meaningful once
                 the API response is in — initial prop values can disagree
                 with what the server actually returns (e.g. badge would
                 flash 1/3 and then settle on ALIAS). Keep the header quiet
                 during load, so nothing misleading is ever shown. -->
            <template v-if="loaded">
                <span
                    v-if="signalBadge"
                    :class="['px-1 py-[1px] rounded text-[9px] font-bold', signalBadge.color]"
                    title="On-demo field matches"
                >{{ signalBadge.label }}</span>
                <span
                    v-else
                    class="px-1 py-[1px] rounded text-[9px] font-bold bg-teal-500/20 text-teal-300"
                    title="Linked only via approved profile aliases"
                >ALIAS</span>

                <span v-if="history.length" class="text-gray-500">
                    · {{ history.length }} demo{{ history.length === 1 ? '' : 's' }}
                </span>

                <span class="text-gray-500 ml-auto leading-snug max-w-[60%] text-right">
                    <template v-if="signals >= 3">
                        All three on-demo fields (player name + q3df colored + q3df plain) match.
                    </template>
                    <template v-else-if="signals === 2">
                        Two of three on-demo fields match; the rest uses approved aliases.
                    </template>
                    <template v-else-if="signals === 1">
                        One on-demo field matches; the rest uses approved aliases.
                    </template>
                    <template v-else>
                        Grouped via approved profile aliases on defrag.racing.
                    </template>
                </span>
            </template>
        </div>

        <!-- Body: loading / error / empty / list -->
        <div class="pl-3">
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
