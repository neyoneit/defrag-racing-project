<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        record: Object
    });

    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;
        return (country == 'XX') ? '_404' : country;
    });

    const getRoute = computed(() => {
        return route(props.record.user ? 'profile.index' : 'profile.mdd', props.record.user ? props.record.user.id : props.record.mdd_id);
    });
</script>

<template>
    <Link :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
        <!-- Background Map Thumbnail (always visible, blurred) - extends to edges -->
        <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
            <img
                :src="`/storage/${record.map.thumbnail}`"
                class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-20 group-hover:opacity-100 transition-all duration-500"
                :alt="record.mapname"
                onerror="this.src='/images/unknown.jpg'"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
        </div>

        <!-- Content (relative to background) -->
        <div class="relative flex items-center gap-2 sm:gap-3 w-full">
            <!-- Rank -->
            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                <span v-if="record.rank === 1" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥇</span>
                <span v-else-if="record.rank === 2" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥈</span>
                <span v-else-if="record.rank === 3" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥉</span>
                <span v-else class="text-[10px] sm:text-xs font-bold tabular-nums text-gray-500 group-hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.rank }}</span>
            </div>

            <!-- Map Name -->
            <div class="w-28 sm:w-40 flex-shrink-0">
                <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
            </div>

            <!-- Player Info -->
            <Link :href="getRoute" @click.stop class="flex items-center gap-1.5 sm:gap-2 flex-1 min-w-0 group/player hover:bg-white/5 -my-2 py-2 px-1 sm:px-2 -ml-1 sm:-ml-2 rounded transition-colors">
                <img
                    :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                    class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover ring-1 ring-white/10 group-hover/player:ring-blue-500/60 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]"
                    :alt="record.user?.name ?? record.name"
                />
                <div class="flex items-center gap-1 sm:gap-1.5 min-w-0">
                    <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-3.5 h-2.5 sm:w-4 sm:h-3 flex-shrink-0 opacity-90 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                    <span class="text-[10px] sm:text-xs font-semibold text-gray-300 group-hover:text-white truncate group-hover/player:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" v-html="q3tohtml(record.user?.name ?? record.name)"></span>
                </div>
            </Link>

            <!-- Time -->
            <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
            </div>

            <!-- Physics Icon -->
            <div class="w-5 sm:w-6 flex-shrink-0 text-center">
                <img v-if="record.physics.includes('cpm')" src="/images/modes/cpm-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="CPM" />
                <img v-else src="/images/modes/vq3-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="VQ3" />
            </div>

            <!-- Date -->
            <div class="w-14 sm:w-20 flex-shrink-0 text-right">
                <div class="text-[8px] sm:text-[10px] text-gray-500 group-hover:text-gray-300 font-mono transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]" :title="record.date_set">
                    {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }}
                </div>
            </div>
        </div>
    </Link>
</template> 