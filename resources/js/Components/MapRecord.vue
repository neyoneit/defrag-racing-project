<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        record: Object,
        cpmrecord: Object,
        vq3record: Object,
        physics: String,
        oldtop: {
            type: Boolean,
            default: false
        }
    });

    const page = usePage();

    const isMyRecord = computed(() => {
        const userId = page.props.auth?.user?.id;
        if (!userId) return false;
        return props.record.user?.id === userId;
    });

    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;

        return (country == 'XX') ? '_404' : country;
    });

    const timeDiff =  computed(() => {
        if (! props.record.besttime === -1) {
            return null;
        }

        return Math.abs(props.record.besttime - props.record.time)
    });

    const getRoute = computed(() => {
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        if (props.record.mdd_id) {
            return route('profile.mdd', props.record.mdd_id);
        }

        return '#'
    })

</script>

<template>
    <div
        class="group relative flex justify-between items-center px-3 py-2 rounded-lg transition-all duration-200"
        :class="{
            'bg-gradient-to-r from-amber-500/20 via-amber-500/10 to-transparent border-l-2 border-amber-400': record.oldtop,
            'bg-gradient-to-r from-emerald-500/20 via-emerald-500/10 to-transparent border-l-2 border-emerald-400 ring-1 ring-emerald-500/30': isMyRecord && !record.oldtop,
            'hover:bg-white/5': !isMyRecord && !record.oldtop
        }"
        :title="record.oldtop ? '👑 Old Top Record' : (isMyRecord ? '⭐ Your Record' : '')"
    >
        <!-- Rank & Player Info -->
        <div class="flex items-center gap-3 min-w-0 flex-1">
            <!-- Rank -->
            <div
                class="font-bold text-base w-8 flex-shrink-0 text-center"
                :class="{
                    'text-amber-400': record.oldtop,
                    'text-emerald-400': isMyRecord && !record.oldtop,
                    'text-gray-400': !isMyRecord && !record.oldtop
                }"
            >
                {{ record.rank }}
            </div>

            <!-- Avatar -->
            <img
                class="h-8 w-8 rounded-full object-cover flex-shrink-0 ring-2"
                :class="{
                    'ring-amber-400/50': record.oldtop,
                    'ring-emerald-400/50': isMyRecord && !record.oldtop,
                    'ring-gray-700': !isMyRecord && !record.oldtop
                }"
                :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                :alt="record.user?.name ?? record.name"
            >

            <!-- Name & Date -->
            <div class="min-w-0 flex-1">
                <Link class="flex items-center gap-2" :href="getRoute">
                    <img
                        :src="`/images/flags/${bestrecordCountry}.png`"
                        class="w-4 h-3 flex-shrink-0"
                        onerror="this.src='/images/flags/_404.png'"
                        :title="bestrecordCountry"
                    >
                    <span
                        class="font-semibold text-sm truncate hover:text-blue-400 transition-colors"
                        :class="{
                            'text-amber-300': record.oldtop,
                            'text-emerald-300': isMyRecord && !record.oldtop,
                            'text-white': !isMyRecord && !record.oldtop
                        }"
                        v-html="q3tohtml(record.user?.name ?? record.name)"
                    ></span>
                </Link>
                <div
                    class="text-xs mt-0.5"
                    :class="{
                        'text-amber-400/70': record.oldtop,
                        'text-emerald-400/70': isMyRecord && !record.oldtop,
                        'text-gray-500': !isMyRecord && !record.oldtop
                    }"
                    :title="record.date_set"
                >
                    {{ timeSince(record.date_set) }}
                </div>
            </div>
        </div>

        <!-- Time & Demo -->
        <div class="flex items-center gap-3">
            <div class="text-right">
                <div
                    class="text-base font-bold tabular-nums"
                    :class="{
                        'text-amber-300': record.oldtop,
                        'text-emerald-300': isMyRecord && !record.oldtop,
                        'text-gray-300': !isMyRecord && !record.oldtop
                    }"
                >
                    {{ formatTime(record.time) }}
                </div>
                <div class="text-xs text-red-400 tabular-nums" v-if="timeDiff">-{{ formatTime(timeDiff) }}</div>
            </div>

            <!-- Demo Download Icon -->
            <a
                v-if="record.uploaded_demos && record.uploaded_demos.length > 0"
                :href="`/demos/${record.uploaded_demos[0].id}/download`"
                class="p-1.5 rounded-md transition-all hover:scale-110"
                :class="{
                    'bg-amber-500/20 text-amber-400 hover:bg-amber-500/30': record.oldtop,
                    'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30': isMyRecord && !record.oldtop,
                    'bg-gray-700 text-gray-400 hover:bg-gray-600': !isMyRecord && !record.oldtop
                }"
                title="Download demo"
            >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                </svg>
            </a>
        </div>
    </div>
</template>