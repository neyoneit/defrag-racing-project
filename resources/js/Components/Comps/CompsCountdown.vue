<script setup>
    import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
    import { t, currentLocale } from '@/utils/i18n';

    // Time left, ticking. Comps hangs entirely on deadlines - a ballot closes
    // Saturday at 20:00 and a round ends Sunday at 20:00 - and a date printed
    // in a timezone the reader may not share is a worse answer than "1d 4h".
    //
    // Which is why the clock time is printed under it rather than instead of
    // it: "1d 17h" is what you want at a glance, and the wall clock is what you
    // need to plan an evening around. Both timezones are named, because the one
    // thing worse than a time in the wrong zone is a time in an unnamed one.
    const props = defineProps({
        until: { type: String, default: null },
        label: { type: String, default: '' },
        // The ballot deadline is the one clock on this page you lose something
        // by missing, so it gets to be read from across the room. The round's
        // own end is information; this is a deadline.
        emphasis: { type: Boolean, default: false },
    });

    // Comps runs on central European time, so that is the authority and it is
    // printed first. Prague rather than a fixed +1: the schedule follows the
    // clock people set their alarms by, summer time included.
    const SITE_TZ = 'Europe/Prague';

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

    /** A zone's offset from UTC at a given moment, in minutes. */
    const offsetMinutes = (date, timeZone) => {
        // Two renderings of the same instant, one in the target zone and one in
        // UTC, differ by exactly that zone's offset. Intl has no API that hands
        // the number over directly.
        const asUtc = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
        const asZone = new Date(date.toLocaleString('en-US', { timeZone }));
        return Math.round((asZone - asUtc) / 60000);
    };

    /**
     * CET or CEST, worked out rather than assumed.
     *
     * Intl would rather say "GMT+2", and the site says CET everywhere else -
     * but printing "CET" through the summer would be wrong for seven months of
     * the year, and a deadline is the wrong place to be an hour out.
     */
    const siteZoneName = (date) => (offsetMinutes(date, SITE_TZ) === 120 ? 'CEST' : 'CET');

    const timeIn = (date, timeZone) =>
        new Intl.DateTimeFormat(currentLocale(), {
            timeZone,
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(date);

    const clock = computed(() => {
        if (!props.until) return null;

        const at = new Date(props.until);
        const localZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        const site = `${timeIn(at, SITE_TZ)} ${siteZoneName(at)}`;

        // Nothing to add for somebody already on central European time, and a
        // bracket repeating the line before it would just be noise.
        if (offsetMinutes(at, localZone) === offsetMinutes(at, SITE_TZ)) {
            return { site, local: null };
        }

        return {
            site,
            local: new Intl.DateTimeFormat(currentLocale(), {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }).format(at),
        };
    });
</script>

<template>
    <span class="inline-flex flex-col items-start">
        <span class="inline-flex items-baseline gap-2">
            <span class="uppercase tracking-wider"
                  :class="emphasis ? 'text-[11px] font-bold text-blue-300/80' : 'text-xs text-gray-500'"
                  v-if="label">{{ label }}</span>
            <span v-if="done" class="font-bold text-gray-400" :class="emphasis ? 'text-lg' : 'text-sm'">{{ $t('Closed') }}</span>
            <span v-else
                  class="tabular-nums"
                  :class="emphasis ? 'text-xl md:text-2xl font-black text-blue-200' : 'text-sm font-bold text-white'">{{ parts }}</span>
        </span>

        <span v-if="clock && !done" class="text-[11px] text-gray-500 tabular-nums">
            {{ clock.site }}<template v-if="clock.local"> ({{ $t(':time your time', { time: clock.local }) }})</template>
        </span>
    </span>
</template>
