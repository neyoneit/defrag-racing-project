<script>
export default {
    layout: null,
};
</script>

<script setup>
import { computed, ref, onMounted, onUnmounted, getCurrentInstance } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    user_name: String,
    total_maps: {
        type: Number,
        default: 0,
    },
    played_maps: {
        type: Number,
        default: 0,
    },
    unplayed_maps: {
        type: Number,
        default: 0,
    },
    widget_settings: {
        type: Object,
        default: null,
    },
});

// Auto-refresh data every 60 seconds for live streaming
let refreshInterval = null;
onMounted(() => {
    refreshInterval = setInterval(() => {
        router.reload({ only: ['total_maps', 'played_maps', 'unplayed_maps', 'widget_settings'] });
    }, 60000);
});
onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

const defaults = {
    bar_width: 600,
    bar_height: 48,
    bar_border_color: '#4b5563',
    bar_border_width: 1,
    bar_border_radius: 24,
    bar_bg_color: 'rgba(31, 41, 55, 0.8)',
    bar_fill_color: '#2563eb',
    bar_fill_color2: '#3b82f6',
    bar_animation: 'shimmer',
    bg_type: 'transparent',
    bg_color: '#00FF00',
    text_player_name: { visible: true, font: 'Inter', size: 20, color: '#ffffff', shadow: true, bold: true },
    text_subtitle: { visible: true, font: 'Inter', size: 12, color: '#9ca3af', shadow: false, bold: false },
    text_count: { visible: true, font: 'Inter', size: 20, color: '#ffffff', shadow: true, bold: true },
    text_label: { visible: true, font: 'Inter', size: 14, color: '#ffffff', shadow: true, bold: true },
    text_percentage: { visible: true, font: 'Inter', size: 24, color: '#ffffff', shadow: true, bold: true },
    text_remaining: { visible: true, font: 'Inter', size: 12, color: '#ffffff', shadow: true, bold: true },
    text_position: 'inside',
};

const s = computed(() => {
    if (!props.widget_settings) return defaults;
    const merged = { ...defaults, ...props.widget_settings };
    for (const key of ['text_player_name', 'text_subtitle', 'text_count', 'text_label', 'text_percentage', 'text_remaining']) {
        merged[key] = { ...defaults[key], ...(props.widget_settings[key] || {}) };
    }
    return merged;
});

const completionPercentage = computed(() => {
    if (props.total_maps === 0) return '0.000';
    return ((props.played_maps / props.total_maps) * 100).toFixed(3);
});

const pageBackground = computed(() => {
    if (s.value.bg_type === 'chroma') return '#00FF00';
    if (s.value.bg_type === 'custom') return s.value.bg_color;
    return 'transparent';
});

const barTrackStyle = computed(() => ({
    width: s.value.bar_width + 'px',
    height: s.value.bar_height + 'px',
    backgroundColor: s.value.bar_bg_color,
    borderRadius: s.value.bar_border_radius + 'px',
    borderWidth: s.value.bar_border_width + 'px',
    borderStyle: 'solid',
    borderColor: s.value.bar_border_color,
}));

const barFillStyle = computed(() => ({
    width: completionPercentage.value + '%',
    height: '100%',
    backgroundColor: s.value.bar_fill_color,
    borderRadius: s.value.bar_border_radius + 'px',
}));

const animationClass = computed(() => {
    const anim = s.value.bar_animation;
    if (anim === 'none') return '';
    return 'ppb-anim-' + anim;
});

const fillGradientVars = computed(() => ({
    '--ppb-fill-color': s.value.bar_fill_color,
    '--ppb-fill-color2': s.value.bar_fill_color2,
}));

function textStyle(cfg) {
    return {
        fontFamily: cfg.font + ', sans-serif',
        fontSize: cfg.size + 'px',
        color: cfg.color,
        fontWeight: cfg.bold ? 'bold' : 'normal',
        textShadow: cfg.shadow ? '0 2px 4px rgba(0,0,0,0.8)' : 'none',
    };
}

const fontImportUrl = computed(() => {
    const fonts = new Set();
    for (const key of ['text_player_name', 'text_subtitle', 'text_count', 'text_label', 'text_percentage', 'text_remaining']) {
        const font = s.value[key].font;
        if (font && font !== 'Inter') {
            fonts.add(font);
        }
    }
    if (fonts.size === 0) return null;
    const families = Array.from(fonts).map(f => 'family=' + f.replace(/ /g, '+')).join('&');
    return 'https://fonts.googleapis.com/css2?' + families + '&display=swap';
});
</script>

<template>
    <Head title="Map Progress" />
    <component v-if="fontImportUrl" is="link" rel="stylesheet" :href="fontImportUrl" />

    <div class="ppb-page" :style="{ backgroundColor: pageBackground }">
        <!-- Header row: player name + subtitle on left, count on right -->
        <div class="ppb-header" :style="{ width: s.bar_width + 'px' }">
            <div class="ppb-header-left">
                <div
                    v-if="s.text_player_name.visible"
                    class="ppb-player-name"
                    :style="textStyle(s.text_player_name)"
                    v-html="q3tohtml(user_name)"
                ></div>
                <div
                    v-if="s.text_subtitle.visible"
                    :style="textStyle(s.text_subtitle)"
                >Map Completionist Progress</div>
            </div>
            <div
                v-if="s.text_count.visible"
                class="ppb-header-right"
                :style="textStyle(s.text_count)"
            >{{ played_maps }} / {{ total_maps }}</div>
        </div>

        <!-- Text above bar -->
        <div
            v-if="s.text_position === 'above'"
            class="ppb-stats-row"
            :style="{ width: s.bar_width + 'px' }"
        >
            <span v-if="s.text_label.visible" :style="textStyle(s.text_label)">{{ played_maps }} maps completed</span>
            <span v-if="s.text_percentage.visible" :style="textStyle(s.text_percentage)">{{ completionPercentage }}%</span>
            <span v-if="s.text_remaining.visible" :style="textStyle(s.text_remaining)">{{ unplayed_maps }} remaining</span>
        </div>

        <!-- Progress bar -->
        <div class="ppb-bar-wrapper" :style="barTrackStyle">
            <div
                class="ppb-bar-fill"
                :class="animationClass"
                :style="[barFillStyle, fillGradientVars]"
            >
                <!-- Shimmer overlay -->
                <div v-if="s.bar_animation === 'shimmer'" class="ppb-shimmer-overlay"></div>
                <!-- Stripes overlay -->
                <div v-if="s.bar_animation === 'stripes'" class="ppb-stripes-overlay"></div>
                <!-- Gradient overlay -->
                <div v-if="s.bar_animation === 'gradient'" class="ppb-gradient-overlay"></div>
                <!-- Wave overlay -->
                <div v-if="s.bar_animation === 'wave'" class="ppb-wave-overlay"></div>
            </div>

            <!-- Inside text -->
            <div
                v-if="s.text_position === 'inside'"
                class="ppb-stats-inside"
            >
                <span v-if="s.text_label.visible" :style="textStyle(s.text_label)">{{ played_maps }} maps completed</span>
                <span v-if="s.text_percentage.visible" :style="textStyle(s.text_percentage)">{{ completionPercentage }}%</span>
                <span v-if="s.text_remaining.visible" :style="textStyle(s.text_remaining)">{{ unplayed_maps }} remaining</span>
            </div>
        </div>

        <!-- Text below bar -->
        <div
            v-if="s.text_position === 'below'"
            class="ppb-stats-row"
            :style="{ width: s.bar_width + 'px' }"
        >
            <span v-if="s.text_label.visible" :style="textStyle(s.text_label)">{{ played_maps }} maps completed</span>
            <span v-if="s.text_percentage.visible" :style="textStyle(s.text_percentage)">{{ completionPercentage }}%</span>
            <span v-if="s.text_remaining.visible" :style="textStyle(s.text_remaining)">{{ unplayed_maps }} remaining</span>
        </div>
    </div>
</template>

<style>
/* Hide debugbar on widget page */
div[id^="phpdebugbar"] { display: none !important; }

/* Page */
.ppb-page {
    min-height: 100vh;
    padding: 16px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
}

/* Header */
.ppb-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 8px;
}

.ppb-header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ppb-header-right {
    flex-shrink: 0;
}

.ppb-player-name .q3c-0,
.ppb-player-name .q3c-1,
.ppb-player-name .q3c-2,
.ppb-player-name .q3c-3,
.ppb-player-name .q3c-4,
.ppb-player-name .q3c-5,
.ppb-player-name .q3c-6,
.ppb-player-name .q3c-7,
.ppb-player-name .q3c-8,
.ppb-player-name .q3c-9 {
    font-weight: inherit;
    font-size: inherit;
    font-family: inherit;
}

/* Stats row (above/below) */
.ppb-stats-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 8px;
}

/* Bar */
.ppb-bar-wrapper {
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
}

.ppb-bar-fill {
    position: relative;
    overflow: hidden;
    transition: width 1s ease-out;
}

/* Inside stats overlay */
.ppb-stats-inside {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    pointer-events: none;
}

/* ===================== */
/* ANIMATION: shimmer    */
/* ===================== */
.ppb-shimmer-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: ppb-shimmer 2s linear infinite;
}

@keyframes ppb-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* ===================== */
/* ANIMATION: pulse      */
/* ===================== */
.ppb-anim-pulse {
    animation: ppb-pulse 2s ease-in-out infinite;
}

@keyframes ppb-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* ===================== */
/* ANIMATION: stripes    */
/* ===================== */
.ppb-stripes-overlay {
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 8px,
        rgba(255, 255, 255, 0.15) 8px,
        rgba(255, 255, 255, 0.15) 16px
    );
    background-size: 32px 100%;
    animation: ppb-stripes 1s linear infinite;
}

@keyframes ppb-stripes {
    0% { background-position: 0 0; }
    100% { background-position: 32px 0; }
}

/* ===================== */
/* ANIMATION: gradient   */
/* ===================== */
.ppb-gradient-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        var(--ppb-fill-color) 0%,
        var(--ppb-fill-color2) 25%,
        var(--ppb-fill-color) 50%,
        var(--ppb-fill-color2) 75%,
        var(--ppb-fill-color) 100%
    );
    background-size: 300% 100%;
    animation: ppb-gradient 3s ease-in-out infinite;
}

@keyframes ppb-gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* ===================== */
/* ANIMATION: wave       */
/* ===================== */
.ppb-wave-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.2) 10%,
        transparent 20%,
        rgba(255, 255, 255, 0.15) 30%,
        transparent 40%,
        rgba(255, 255, 255, 0.2) 50%,
        transparent 60%,
        rgba(255, 255, 255, 0.15) 70%,
        transparent 80%,
        rgba(255, 255, 255, 0.2) 90%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: ppb-wave 2.5s ease-in-out infinite;
}

@keyframes ppb-wave {
    0% { background-position: 0% 0; }
    100% { background-position: 200% 0; }
}
</style>
