<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';

    const props = defineProps({
        record: Object
    });

    const page = usePage();
    const showTooltip = ref(false);
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;
        return (country == 'XX') ? '_404' : country;
    });

    const getRoute = computed(() => {
        // Only link to registered users with full profiles
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        // Players with only mdd_id have no proper profile page
        // Show tooltip to encourage registration
        return null;
    });
</script>

<template>
    <Link :href="route('maps.map', record.mapname)" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 first:rounded-t-[10px] last:rounded-b-[10px]">
        <!-- Background Map Thumbnail (always visible, blurred) - extends to edges with overflow-hidden -->
        <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px] overflow-hidden">
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
            <component
                :is="getRoute ? Link : 'div'"
                :href="getRoute"
                @click.stop
                :class="[
                    'flex items-center gap-1.5 sm:gap-2 flex-1 min-w-0 group/player hover:bg-white/5 -my-2 py-2 px-1 sm:px-2 -ml-1 sm:-ml-2 rounded transition-colors',
                    !getRoute && isLoggedIn ? 'cursor-default opacity-70' : !getRoute ? 'cursor-help opacity-70' : 'cursor-pointer'
                ]"
                @mouseenter="!getRoute && (showTooltip = true)"
                @mouseleave="!getRoute && (showTooltip = false)"
            >
                <div class="overflow-visible flex-shrink-0">
                    <div :class="'avatar-effect-' + (record.user?.avatar_effect || 'none')" :style="`--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 14px`">
                        <img
                            :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                            class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                            :style="`border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                            :alt="record.user?.name ?? record.name"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-1 sm:gap-1.5 min-w-0">
                    <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-3.5 h-2.5 sm:w-4 sm:h-3 flex-shrink-0 opacity-90 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                    <span :class="'name-effect-' + (record.user?.name_effect || 'none')" :style="`--effect-color: ${record.user?.color || '#ffffff'}`" class="text-[10px] sm:text-xs font-semibold text-gray-300 group-hover:text-white truncate group-hover/player:text-blue-200 transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" v-html="q3tohtml(record.user?.name ?? record.name)"></span>
                </div>
            </component>

            <!-- Unclaimed Profile Tooltip (Teleported to body) - Only show if not logged in -->
            <Teleport to="body" v-if="!getRoute && showTooltip && !isLoggedIn">
                <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
                    <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white px-8 py-5 rounded-2xl shadow-[0_20px_60px_rgb(249,115,22,0.9)] border-4 border-orange-300 w-[500px] animate-pulse">
                        <div class="flex items-center gap-4 mb-3">
                            <span class="text-5xl animate-bounce">🔓</span>
                            <div class="text-left flex-1">
                                <div class="text-2xl font-black leading-tight">Unclaimed Profile</div>
                                <div class="text-xl font-bold text-orange-100">Is it yours?</div>
                            </div>
                        </div>
                        <div class="text-orange-50 font-bold text-base leading-relaxed text-left">
                            Register now and link your q3df.org account to see detailed profile page and much more!
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Subtle "Not linked account" tooltip for logged-in users - TELEPORTED -->
            <Teleport to="body" v-if="!getRoute && showTooltip && isLoggedIn">
                <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
                    <div class="bg-gray-900 border-2 border-gray-600 text-gray-300 px-4 py-2 rounded-lg text-sm font-semibold shadow-xl">
                        Not linked account
                    </div>
                </div>
            </Teleport>

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