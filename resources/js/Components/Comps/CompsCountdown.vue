<script setup>
    import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
    import { t } from '@/utils/i18n';

    // Time left, ticking. Comps hangs entirely on deadlines - a ballot closes
    // Saturday at 20:00 and a round ends Sunday at 20:00 - and a date printed
    // in a timezone the reader may not share is a worse answer than "1d 4h".
    const props = defineProps({
        until: { type: String, default: null },
        label: { type: String, default: '' },
        // The ballot deadline is the one clock on this page you lose something
        // by missing, so it gets to be read from across the room. The round's
        // own end is information; this is a deadline.
        emphasis: { type: Boolean, default: false },
    });

    const now = ref(Date.now());
    let timer = null;

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), 1000);
    });

    onBeforeUnmount(() => clearInterval(timer));

    const left = computed(() => {
        if (!props.until) return null;
        return new Date(props.until).getTime() - now.value;
    });

    const done = computed(() => left.value !== null && left.value <= 0);

    const parts = computed(() => {
        if (left.value === null || left.value <= 0) return null;

        const s = Math.floor(left.value / 1000);
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;

        // Seconds only matter once it is close enough to matter.
        if (d > 0) return `${d}d ${h}h`;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m ${sec}s`;
    });
</script>

<template>
    <span class="inline-flex items-baseline gap-2">
        <span class="uppercase tracking-wider"
              :class="emphasis ? 'text-[11px] font-bold text-blue-300/80' : 'text-xs text-gray-500'"
              v-if="label">{{ label }}</span>
        <span v-if="done" class="font-bold text-gray-400" :class="emphasis ? 'text-lg' : 'text-sm'">{{ $t('Closed') }}</span>
        <span v-else
              class="tabular-nums"
              :class="emphasis ? 'text-xl md:text-2xl font-black text-blue-200' : 'text-sm font-bold text-white'">{{ parts }}</span>
    </span>
</template>
