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
    <Link :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
        <!-- Background Map Thumbnail (always visible, blurred) - extends to edges -->
        <div v-if="record.map" class="absolute -inset-[1px] transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
            <img
                :src="`/storage/${record.map.thumbnail}`"
                class="w-full h-full object-cover scale-110 blur-md group-hover:blur-none group-hover:scale-100 opacity-20 group-hover:opacity-60"
                :alt="record.mapname"
                onerror="this.src='/images/unknown.jpg'"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/70 group-hover:via-black/60 group-hover:to-black/70 transition-all duration-500"></div>
        </div>

        <!-- Content (relative to background) -->
        <div class="relative flex items-center gap-3 w-full">
            <!-- Rank -->
            <div class="w-8 flex-shrink-0 text-center">
                <span v-if="record.rank === 1" class="text-lg">🥇</span>
                <span v-else-if="record.rank === 2" class="text-lg">🥈</span>
                <span v-else-if="record.rank === 3" class="text-lg">🥉</span>
                <span v-else class="text-xs font-bold tabular-nums text-gray-500 group-hover:text-gray-400 transition-colors">{{ record.rank }}</span>
            </div>

            <!-- Map Name -->
            <div class="w-40 flex-shrink-0">
                <div class="text-sm font-bold text-blue-400 group-hover:text-blue-300 transition-colors truncate">{{ record.mapname }}</div>
            </div>

            <!-- Player Info -->
            <Link :href="getRoute" @click.stop class="flex items-center gap-2 flex-1 min-w-0 group/player hover:bg-white/5 -my-2 py-2 px-2 -ml-2 rounded transition-colors">
                <img
                    :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                    class="h-6 w-6 rounded-full object-cover ring-1 ring-white/10 group-hover/player:ring-blue-500/40 transition-all"
                    :alt="record.user?.name ?? record.name"
                />
                <div class="flex items-center gap-1.5 min-w-0">
                    <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-4 h-3 flex-shrink-0 opacity-70" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                    <span class="text-xs font-semibold text-gray-300 truncate group-hover/player:text-blue-400 transition-colors" v-html="q3tohtml(record.user?.name ?? record.name)"></span>
                </div>
            </Link>

            <!-- Time -->
            <div class="w-20 flex-shrink-0 text-right">
                <div class="text-sm font-bold tabular-nums text-white group-hover:text-blue-100 transition-colors">{{ formatTime(record.time) }}</div>
            </div>

            <!-- Physics Icon -->
            <div class="w-10 flex-shrink-0 text-center">
                <img v-if="record.physics.includes('cpm')" src="/images/cpm-icon.svg" class="w-4 h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity" alt="CPM" />
                <img v-else src="/images/vq3-icon.svg" class="w-4 h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity" alt="VQ3" />
            </div>

            <!-- Date -->
            <div class="w-16 flex-shrink-0 text-right">
                <div class="text-[10px] text-gray-500 group-hover:text-gray-400 font-mono transition-colors" :title="record.date_set">
                    {{ new Date(record.date_set).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit' }) }}
                </div>
            </div>
        </div>
    </Link>
</template> 