<script setup>
    import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        activityData: {
            type: Object,
            default: () => ({})
        },
        activityYear: {
            type: Number,
            default: () => new Date().getFullYear()
        },
        activityYears: {
            type: Array,
            default: () => []
        },
        mddId: {
            type: [Number, String],
            required: true
        },
        isNew: {
            type: Boolean,
            default: false
        },
        customizeUrl: {
            type: String,
            default: ''
        }
    });

    const selectedYear = ref(props.activityYear);
    const currentData = ref(props.activityData);
    const hoveredDay = ref(null);
    const tooltipPos = ref({ x: 0, y: 0 });
    const loading = ref(false);

    watch(() => props.activityYear, (val) => {
        selectedYear.value = val;
        currentData.value = props.activityData;
    });

    watch(() => props.activityData, (val) => {
        currentData.value = val;
    });

    const cellGap = 3;
    const labelWidth = 32;
    const topPadding = 20;

    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // VQ3 = blue (hue 217), CPM = purple (hue 270)
    const VQ3_HUE = 217;
    const CPM_HUE = 270;

    // Generate all weeks/days for the selected year
    const weeks = computed(() => {
        const year = selectedYear.value;
        const result = [];
        let currentWeek = [];

        const startDate = new Date(year, 0, 1);
        const endDate = new Date(year, 11, 31);

        const startDay = (startDate.getDay() + 6) % 7;
        for (let i = 0; i < startDay; i++) {
            currentWeek.push(null);
        }

        const current = new Date(startDate);
        while (current <= endDate) {
            const dateStr = current.toISOString().split('T')[0];
            const dayData = currentData.value[dateStr] || { vq3: 0, cpm: 0 };
            const vq3 = dayData.vq3 || 0;
            const cpm = dayData.cpm || 0;
            const total = vq3 + cpm;
            const dayOfWeek = (current.getDay() + 6) % 7;

            currentWeek.push({
                date: dateStr,
                vq3: vq3,
                cpm: cpm,
                total: total,
                vq3Ratio: total > 0 ? vq3 / total : 0,
                cpmRatio: total > 0 ? cpm / total : 0,
                dayOfWeek: dayOfWeek,
                month: current.getMonth(),
                day: current.getDate()
            });

            if (dayOfWeek === 6) {
                result.push(currentWeek);
                currentWeek = [];
            }

            current.setDate(current.getDate() + 1);
        }

        if (currentWeek.length > 0) {
            result.push(currentWeek);
        }

        return result;
    });

    // Dynamic cell size
    const containerRef = ref(null);
    const containerWidth = ref(900);

    const cellSize = computed(() => {
        const availableWidth = containerWidth.value - labelWidth - 8;
        const numWeeks = weeks.value.length;
        const size = Math.floor((availableWidth - (numWeeks - 1) * cellGap) / numWeeks);
        return Math.max(8, Math.min(18, size));
    });

    const cellStep = computed(() => cellSize.value + cellGap);

    function updateContainerWidth() {
        if (containerRef.value) {
            containerWidth.value = containerRef.value.clientWidth;
        }
    }

    // Month label positions
    const monthPositions = computed(() => {
        const positions = [];
        let lastMonth = -1;

        weeks.value.forEach((week, weekIdx) => {
            for (const day of week) {
                if (day && day.month !== lastMonth) {
                    positions.push({
                        label: monthLabels[day.month],
                        x: labelWidth + weekIdx * cellStep.value
                    });
                    lastMonth = day.month;
                    break;
                }
            }
        });

        return positions;
    });

    const svgWidth = computed(() => labelWidth + weeks.value.length * cellStep.value + 4);
    const svgHeight = computed(() => topPadding + 7 * cellStep.value + 4);

    // Stats
    const totalRecords = computed(() => {
        return Object.values(currentData.value).reduce((sum, d) => sum + (d.vq3 || 0) + (d.cpm || 0), 0);
    });

    const totalVq3 = computed(() => {
        return Object.values(currentData.value).reduce((sum, d) => sum + (d.vq3 || 0), 0);
    });

    const totalCpm = computed(() => {
        return Object.values(currentData.value).reduce((sum, d) => sum + (d.cpm || 0), 0);
    });

    const activeDays = computed(() => {
        return Object.keys(currentData.value).length;
    });

    // Quartile thresholds for 4 intensity levels (like GitHub)
    const quartiles = computed(() => {
        const counts = Object.values(currentData.value)
            .map(d => (d.vq3 || 0) + (d.cpm || 0))
            .filter(c => c > 0)
            .sort((a, b) => a - b);

        if (counts.length === 0) return [1, 2, 3];

        const q1 = counts[Math.floor(counts.length * 0.25)] || 1;
        const q2 = counts[Math.floor(counts.length * 0.50)] || q1;
        const q3 = counts[Math.floor(counts.length * 0.75)] || q2;

        return [q1, q2, q3];
    });

    function getIntensityLevel(total) {
        const [q1, q2, q3] = quartiles.value;
        if (total <= q1) return 1;
        if (total <= q2) return 2;
        if (total <= q3) return 3;
        return 4;
    }

    const levelAlpha = [0.5, 0.65, 0.8, 1.0];

    function getPhysicsColor(hue, level) {
        const alpha = levelAlpha[level - 1];
        const lightness = 30 + level * 8;
        return `hsla(${hue}, 85%, ${lightness}%, ${alpha})`;
    }

    function onCellHover(event, day) {
        if (!day) return;
        hoveredDay.value = day;
        const rect = event.target.closest('.cell-group').getBoundingClientRect();
        const container = containerRef.value.getBoundingClientRect();
        tooltipPos.value = {
            x: rect.left - container.left + rect.width / 2,
            y: rect.top - container.top - 8
        };
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    async function switchYear(year) {
        if (year === selectedYear.value || loading.value) return;
        selectedYear.value = year;
        loading.value = true;
        try {
            const response = await fetch(`/api/profile/${props.mddId}/activity?year=${year}`);
            const data = await response.json();
            currentData.value = data.activity_data;
        } catch (e) {
            console.error('Failed to load activity data:', e);
        } finally {
            loading.value = false;
        }
    }

    let resizeObserver = null;

    onMounted(() => {
        updateContainerWidth();
        if (containerRef.value) {
            resizeObserver = new ResizeObserver(() => updateContainerWidth());
            resizeObserver.observe(containerRef.value);
        }
    });

    onUnmounted(() => {
        resizeObserver?.disconnect();
    });
</script>

<template>
    <div class="bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold text-white">Activity History</h3>
                <a v-if="isNew" :href="customizeUrl" class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-green-500/20 border border-green-500/30 hover:bg-green-500/30 transition-colors">
                    <span class="text-sm font-bold uppercase tracking-wide text-green-400">New!</span>
                    <span class="text-sm text-gray-300">Reorder or hide sections</span>
                    <svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-400">{{ totalRecords }} records</span>
                <span class="text-white/20">|</span>
                <span class="text-blue-400">{{ totalVq3 }} VQ3</span>
                <span class="text-purple-400">{{ totalCpm }} CPM</span>
                <span class="text-white/20">|</span>
                <span class="text-gray-400">{{ activeDays }} active days</span>
            </div>
        </div>

        <!-- Heatmap -->
        <div ref="containerRef" class="relative" @mouseleave="hoveredDay = null">
            <svg :width="svgWidth" :height="svgHeight" class="block w-full" :viewBox="`0 0 ${svgWidth} ${svgHeight}`">
                <!-- Month labels -->
                <text
                    v-for="mp in monthPositions"
                    :key="mp.label + mp.x"
                    :x="mp.x"
                    :y="12"
                    class="fill-gray-500"
                    font-size="11"
                    font-family="sans-serif"
                >{{ mp.label }}</text>

                <!-- Day labels -->
                <text :x="0" :y="topPadding + 1 * cellStep + cellSize - 2" class="fill-gray-600" font-size="10" font-family="sans-serif">Mon</text>
                <text :x="0" :y="topPadding + 3 * cellStep + cellSize - 2" class="fill-gray-600" font-size="10" font-family="sans-serif">Wed</text>
                <text :x="0" :y="topPadding + 5 * cellStep + cellSize - 2" class="fill-gray-600" font-size="10" font-family="sans-serif">Fri</text>

                <!-- Cells -->
                <template v-for="(week, weekIdx) in weeks" :key="weekIdx">
                    <g
                        v-for="(day, dayIdx) in week"
                        :key="weekIdx + '-' + dayIdx"
                        v-show="day"
                        class="cell-group cursor-pointer"
                        @mouseenter="onCellHover($event, day)"
                    >
                        <!-- Empty day background -->
                        <rect
                            v-if="day && day.total === 0"
                            :x="labelWidth + weekIdx * cellStep"
                            :y="topPadding + dayIdx * cellStep"
                            :width="cellSize"
                            :height="cellSize"
                            fill="rgba(255, 255, 255, 0.03)"
                            rx="2"
                            ry="2"
                        />

                        <!-- VQ3 portion (left side) -->
                        <rect
                            v-if="day && day.vq3 > 0"
                            :x="labelWidth + weekIdx * cellStep"
                            :y="topPadding + dayIdx * cellStep"
                            :width="day.cpm > 0 ? Math.max(3, cellSize * day.vq3Ratio) : cellSize"
                            :height="cellSize"
                            :fill="getPhysicsColor(VQ3_HUE, getIntensityLevel(day.total))"
                            :rx="day.cpm > 0 ? 0 : 2"
                            :ry="day.cpm > 0 ? 0 : 2"
                        />

                        <!-- CPM portion (right side) -->
                        <rect
                            v-if="day && day.cpm > 0"
                            :x="labelWidth + weekIdx * cellStep + (day.vq3 > 0 ? Math.max(3, cellSize * day.vq3Ratio) : 0)"
                            :y="topPadding + dayIdx * cellStep"
                            :width="day.vq3 > 0 ? cellSize - Math.max(3, cellSize * day.vq3Ratio) : cellSize"
                            :height="cellSize"
                            :fill="getPhysicsColor(CPM_HUE, getIntensityLevel(day.total))"
                            :rx="day.vq3 > 0 ? 0 : 2"
                            :ry="day.vq3 > 0 ? 0 : 2"
                        />

                        <!-- Rounded corners clip for split cells -->
                        <rect
                            v-if="day && day.vq3 > 0 && day.cpm > 0"
                            :x="labelWidth + weekIdx * cellStep"
                            :y="topPadding + dayIdx * cellStep"
                            :width="cellSize"
                            :height="cellSize"
                            fill="transparent"
                            rx="2"
                            ry="2"
                            stroke="rgba(0,0,0,0.3)"
                            stroke-width="0.5"
                        />

                        <!-- Hover outline -->
                        <rect
                            v-if="day && hoveredDay && hoveredDay.date === day.date"
                            :x="labelWidth + weekIdx * cellStep"
                            :y="topPadding + dayIdx * cellStep"
                            :width="cellSize"
                            :height="cellSize"
                            fill="transparent"
                            rx="2"
                            ry="2"
                            stroke="rgba(255,255,255,0.4)"
                            stroke-width="1"
                        />
                    </g>
                </template>
            </svg>

            <!-- Tooltip -->
            <div
                v-if="hoveredDay"
                class="absolute pointer-events-none bg-gray-900 border border-white/10 rounded-lg px-3 py-2 text-sm shadow-xl z-50 whitespace-nowrap"
                :style="{
                    left: tooltipPos.x + 'px',
                    top: tooltipPos.y + 'px',
                    transform: 'translate(-50%, -100%)'
                }"
            >
                <div class="text-gray-400 text-xs mb-1">{{ formatDate(hoveredDay.date) }}</div>
                <div class="flex items-center gap-3">
                    <span class="text-white font-medium">{{ hoveredDay.total }} total</span>
                    <span v-if="hoveredDay.vq3" class="text-blue-400">{{ hoveredDay.vq3 }} VQ3</span>
                    <span v-if="hoveredDay.cpm" class="text-purple-400">{{ hoveredDay.cpm }} CPM</span>
                </div>
            </div>
        </div>

        <!-- Legend and year selector -->
        <div class="flex items-center justify-between mt-3">
            <!-- Color legend -->
            <div class="flex items-center gap-3 text-xs text-gray-500">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-sm" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.06);"></div>
                    <span>None</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-sm" style="background: hsla(217, 85%, 38%, 0.65);"></div>
                    <span>VQ3</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-sm" style="background: hsla(270, 85%, 38%, 0.65);"></div>
                    <span>CPM</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-1.5 h-3 rounded-l-sm" style="background: hsla(217, 85%, 46%, 0.8);"></div><div class="w-1.5 h-3 rounded-r-sm" style="background: hsla(270, 85%, 46%, 0.8);"></div>
                    <span>Both</span>
                </div>
            </div>

            <!-- Year selector -->
            <div class="flex items-center gap-1 flex-wrap justify-end">
                <button
                    v-for="year in activityYears"
                    :key="year"
                    @click="switchYear(year)"
                    class="px-2 py-1 rounded text-xs transition-all"
                    :class="selectedYear === year
                        ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                        : 'text-gray-500 hover:text-gray-300 hover:bg-white/5'"
                >{{ year }}</button>
            </div>
        </div>
    </div>
</template>
