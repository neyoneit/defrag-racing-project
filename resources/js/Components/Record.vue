<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        record: Object
    });

    defineEmits(['scoreHover']);
    const page = usePage();
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
    const onResize = () => { windowWidth.value = window.innerWidth; };
    onMounted(() => window.addEventListener('resize', onResize));
    onUnmounted(() => window.removeEventListener('resize', onResize));

    const isCompact = computed(() => windowWidth.value < 500);

    const fmtDate = (dateStr) => {
        const d = new Date(dateStr);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yy = String(d.getFullYear()).slice(-2);
        const yyyy = String(d.getFullYear());
        const fmt = page.props.dateFormat;
        // Under 500px, always use short year
        if (isCompact.value) return `${dd}/${mm}/${yy}`;
        if (fmt === 'dmY') return `${dd}/${mm}/${yyyy}`;
        if (fmt === 'Ymd') return `${yyyy}/${mm}/${dd}`;
        if (fmt === 'dmy') return `${dd}/${mm}/${yy}`;
        return `${yy}/${mm}/${dd}`;
    };

    const dateColWidth = computed(() => {
        if (isCompact.value) return 'w-[50px]';
        const fmt = page.props.dateFormat;
        return (fmt === 'Ymd' || fmt === 'dmY') ? 'w-[72px]' : 'w-[50px]';
    });

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
    <Link :href="`/maps/${encodeURIComponent(record.mapname)}`" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.04] last:border-0 hover:drop-shadow-[0_0_12px_rgba(255,255,255,0.08)] overflow-hidden">
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
            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6 -ml-3">
                <span class="text-xs sm:text-sm font-black tabular-nums transition-all"
                    :class="{
                        'text-yellow-200 text-base [text-shadow:0_0_8px_#facc15,0_0_20px_#facc15,0_0_40px_#facc15,0_0_80px_#facc15,0_0_120px_rgba(250,204,21,0.5)]': record.rank === 1,
                        'text-white text-base [text-shadow:0_0_8px_#e2e8f0,0_0_20px_#e2e8f0,0_0_40px_#e2e8f0,0_0_80px_rgba(226,232,240,0.5)]': record.rank === 2,
                        'text-amber-300 text-base [text-shadow:0_0_8px_#f59e0b,0_0_20px_#f59e0b,0_0_40px_#f59e0b,0_0_80px_rgba(245,158,11,0.5)]': record.rank === 3,
                        'text-gray-400': record.rank > 3
                    }">
                    {{ record.rank }}
                </span>
            </div>

            <!-- Player Info -->
            <component
                :is="getRoute ? Link : 'div'"
                :href="getRoute"
                @click.stop
                :class="[
                    'flex items-center gap-1.5 sm:gap-2 flex-1 min-w-0 group/player -my-2 py-2 px-1 sm:px-2 -ml-1 sm:-ml-2 rounded transition-colors',
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

            <!-- Map + Time + Score + Date -->
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                <!-- Map Name -->
                <div class="w-16 min-[500px]:w-20 sm:w-40 flex-shrink-0">
                    <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-all truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ record.mapname }}</div>
                </div>
                <div class="flex items-center gap-0.5 ml-auto -mr-3">
                    <div class="w-[60px] min-[500px]:w-[80px] text-right">
                        <div class="text-xs sm:text-sm font-black tabular-nums text-white leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ formatTime(record.time) }}</div>
                    </div>
                    <div class="hidden sm:block w-8 sm:w-10 text-center flex-shrink-0">
                        <div v-if="record.map_score"
                            class="text-xs sm:text-sm font-black tabular-nums leading-none text-yellow-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] cursor-help" style="padding-left: 5px"
                            @mouseenter="$emit('scoreHover', { score: record.map_score, reltime: record.reltime, multiplier: record.multiplier, el: $event.target })"
                            @mouseleave="$emit('scoreHover', null)">{{ Math.round(record.map_score) }}</div>
                    </div>
                    <div :class="[dateColWidth, 'flex-shrink-0 text-right opacity-90 group-hover:opacity-100 transition-opacity']" v-if="windowWidth >= 400">
                        <div class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none">
                            {{ fmtDate(record.date_set) }}
                        </div>
                        <div class="text-[10px] text-gray-400 whitespace-nowrap font-mono group-hover:text-gray-300 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none mt-0.5">
                            {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template> 