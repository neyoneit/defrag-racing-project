<script setup>
    import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
    import { t } from '@/utils/i18n';

    // Time left, ticking. Comps hangs entirely on deadlines - a ballot closes
    // Saturday at 20:00 and a round ends Sunday at 20:00 - and a date printed
    // in a timezone the reader may not share is a worse answer than "1d 4h".
    const props = defineProps({
        until: { type: String, default: null },
        label: { type: String, default: '' },
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
    <span class="inline-flex items-baseline gap-1.5">
        <span v-if="label" class="text-xs uppercase tracking-wider text-gray-500">{{ label }}</span>
        <span v-if="done" class="text-sm font-bold text-gray-400">{{ $t('Closed') }}</span>
        <span v-else class="text-sm font-bold tabular-nums text-white">{{ parts }}</span>
    </span>
</template>
