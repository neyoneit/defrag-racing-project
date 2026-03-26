<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';

    const props = defineProps({
        record: Object
    });

    const page = usePage();
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;
        return (country == 'XX') ? '_404' : country;
    });

    const getRoute = computed(() => {
        // Linked users with full profiles
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        // Fallback to mdd profile for unlinked accounts
        if (props.record.mdd_id) {
            return route('profile.mdd', props.record.mdd_id);
        }

        return null;
    });
</script>

<template>
    <Link :href="`/maps/${encodeURIComponent(record.mapname)}`" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 hover:drop-shadow-[0_0_12px_rgba(255,255,255,0.08)] overflow-hidden">
        <!-- Background Map Thumbnail (always visible, blurred) - extends to edges with overflow-hidden -->
        <div v-if="record.map?.thumbnail" class="absolute inset-0 transition-all duration-500 overflow-hidden">
            <img
                :src="`/storage/${record.map.thumbnail}`"
                loading="lazy"
                decoding="async"
                class="w-full h-full object-cover scale-100 group-hover:scale-105 opacity-0 group-hover:opacity-100 transition-all duration-500"
                :alt="record.mapname"
                onerror="this.src='/images/unknown.jpg'"
            />
            <div class="absolute inset-0 bg-transparent group-hover:bg-black/30 transition-all duration-500"></div>
        </div>

        <!-- Content (relative to background) -->
        <div class="relative flex items-center gap-2 sm:gap-3 w-full transition-all duration-300">
            <!-- Rank -->
            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                <span class="text-[10px] sm:text-xs font-bold tabular-nums drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)] transition-all"
                    :class="record.rank === 1 ? 'text-yellow-400' : record.rank === 2 ? 'text-gray-300' : record.rank === 3 ? 'text-amber-600' : 'text-gray-400 group-hover:text-white'">
                    {{ record.rank }}
                </span>
            </div>

            <!-- Map Name -->
            <div class="w-28 sm:w-40 flex-shrink-0">
                <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-all truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ record.mapname }}</div>
            </div>

            <!-- Player Info -->
            <component
                :is="getRoute ? Link : 'div'"
                :href="getRoute"
                @click.stop
                :class="[
                    'flex items-center gap-1.5 sm:gap-2 flex-1 min-w-0 group/player hover:bg-white/5 -my-2 py-2 px-1 sm:px-2 -ml-1 sm:-ml-2 rounded transition-colors',
                    !getRoute && isLoggedIn ? 'cursor-default opacity-70' : !getRoute ? 'cursor-help opacity-70' : 'cursor-pointer'
                ]"
            >
                <div class="overflow-visible flex-shrink-0">
                    <div :class="'avatar-effect-' + (record.user?.avatar_effect || 'none')" :style="`--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 14px`">
                        <img
                            :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                            loading="lazy"
                            decoding="async"
                            class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                            :style="`border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                            :alt="record.user?.name ?? record.name"
                            onerror="this.src='/images/null.jpg'"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-1 sm:gap-1.5 min-w-0">
                    <img :src="`/images/flags/${bestrecordCountry}.png`" loading="lazy" decoding="async" class="w-3.5 h-2.5 sm:w-4 sm:h-3 flex-shrink-0 opacity-90 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                    <span :class="'name-effect-' + (record.user?.name_effect || 'none')" :style="`--effect-color: ${record.user?.color || '#ffffff'}`" class="text-[11px] sm:text-sm font-semibold text-gray-300 group-hover:text-white whitespace-nowrap overflow-visible group-hover/player:text-blue-200 transition-all" v-html="q3tohtml(record.user?.name ?? record.name)"></span>
                </div>
            </component>


            <!-- Time + Date -->
            <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0 ml-auto">
                <div class="text-right">
                    <div class="text-xs sm:text-sm font-black tabular-nums text-white leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ formatTime(record.time) }}</div>
                </div>
                <div class="text-right opacity-90 group-hover:opacity-100 transition-opacity">
                    <div class="text-[10px] sm:text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]" :title="record.date_set">
                        {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }} {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template> 