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

    const rankColorClass = computed(() => {
        if (props.record.oldtop) {
            return 'text-amber-400 group-hover:text-amber-300';
        }
        if (isMyRecord.value && !props.record.oldtop) {
            return 'text-emerald-400 group-hover:text-emerald-300';
        }
        if (!isMyRecord.value && !props.record.oldtop) {
            if (props.record.rank === 1) {
                return 'text-yellow-500 group-hover:text-yellow-400';
            }
            if (props.record.rank === 2) {
                return 'text-gray-300 group-hover:text-gray-200';
            }
            if (props.record.rank === 3) {
                return 'text-orange-600 group-hover:text-orange-500';
            }
            return 'text-gray-600 group-hover:text-white';
        }
        return 'text-gray-600 group-hover:text-white';
    });

</script>

<template>
    <div
        class="group relative flex items-center gap-2 px-2 py-1.5 rounded-md transition-all duration-200 hover:bg-white/10 hover:scale-[1.02] hover:shadow-lg"
        :class="{
            'bg-gradient-to-r from-amber-500/15 to-transparent border-l-2 border-amber-400 hover:from-amber-500/25 hover:border-amber-300': record.oldtop,
            'bg-gradient-to-r from-emerald-500/15 to-transparent border-l-2 border-emerald-400 hover:from-emerald-500/25 hover:border-emerald-300': isMyRecord && !record.oldtop,
            'border-l-2 border-transparent hover:border-blue-500/50': !isMyRecord && !record.oldtop
        }"
    >
        <!-- Rank Number - LARGE and prominent with pop animation -->
        <div
            class="font-black text-lg w-7 flex-shrink-0 text-right leading-none transition-all duration-200 group-hover:scale-125 group-hover:translate-x-1 group-hover:mr-1"
            :class="rankColorClass"
        >
            {{ record.rank }}
        </div>

        <!-- Player Info - Compact -->
        <Link :href="getRoute" class="flex items-center gap-2 min-w-0 flex-1 group/player transition-all duration-200 group-hover:ml-1">
            <img
                class="h-7 w-7 rounded-full object-cover flex-shrink-0 ring-1"
                :class="{
                    'ring-amber-400': record.oldtop,
                    'ring-emerald-400': isMyRecord && !record.oldtop,
                    'ring-gray-700': !isMyRecord && !record.oldtop
                }"
                :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                :alt="record.user?.name ?? record.name"
            >
            <img
                :src="`/images/flags/${bestrecordCountry}.png`"
                class="w-5 h-4 flex-shrink-0"
                onerror="this.src='/images/flags/_404.png'"
            >
            <span
                class="text-sm font-semibold truncate group-hover/player:text-blue-400 transition-colors"
                :class="{
                    'text-amber-200': record.oldtop,
                    'text-emerald-200': isMyRecord && !record.oldtop,
                    'text-gray-200': !isMyRecord && !record.oldtop
                }"
                v-html="q3tohtml(record.user?.name ?? record.name)"
            ></span>
        </Link>

        <!-- Time - MASSIVE and eye-catching -->
        <div class="text-right">
            <div
                class="font-black text-base tabular-nums leading-none"
                :class="{
                    'text-amber-300': record.oldtop,
                    'text-emerald-300': isMyRecord && !record.oldtop,
                    'text-white': !isMyRecord && !record.oldtop
                }"
            >
                {{ formatTime(record.time) }}
            </div>
            <div v-if="timeDiff" class="text-[10px] text-red-400 tabular-nums leading-none mt-0.5">
                -{{ formatTime(timeDiff) }}
            </div>
        </div>

        <!-- Date & Demo - More visible -->
        <div class="flex items-center gap-2 opacity-70 group-hover:opacity-100 transition-opacity">
            <div
                class="text-xs text-gray-300 whitespace-nowrap font-mono group-hover:text-gray-200"
                :title="record.date_set"
            >
                {{ new Date(record.date_set).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' }) }}
            </div>

            <a
                v-if="record.uploaded_demos && record.uploaded_demos.length > 0"
                :href="`/demos/${record.uploaded_demos[0].id}/download`"
                class="p-1 rounded transition-all hover:scale-110"
                :class="{
                    'bg-amber-500/20 text-amber-400': record.oldtop,
                    'bg-emerald-500/20 text-emerald-400': isMyRecord && !record.oldtop,
                    'bg-gray-700/50 text-gray-400': !isMyRecord && !record.oldtop
                }"
                title="Download demo"
                @click.stop
            >
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                </svg>
            </a>
        </div>

        <!-- Left side badges -->
        <div class="absolute -left-1 top-1/2 -translate-y-1/2 text-lg transition-all duration-200 group-hover:scale-110 group-hover:translate-x-0.5">
            <!-- Old Top Crown (only for top 3) -->
            <span v-if="record.oldtop && record.rank <= 3">👑</span>
            <!-- Top 3 Medals (for all top 3, including your records) -->
            <span v-else-if="record.rank === 1 && !record.oldtop">🥇</span>
            <span v-else-if="record.rank === 2 && !record.oldtop">🥈</span>
            <span v-else-if="record.rank === 3 && !record.oldtop">🥉</span>
        </div>
    </div>
</template>