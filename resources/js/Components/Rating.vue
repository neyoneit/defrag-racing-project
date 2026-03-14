<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';

    const props = defineProps({
        rating: {
            type: Object,
            required: true
        },
        rank: {
            type: Number,
            default: () => ranking.rank
        }
    });

    const page = usePage();
    const showTooltip = ref(false);
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const showDateTooltip = ref(false);
    const dateTooltipPos = ref({ x: 0, y: 0 });
    const onDateEnter = (e) => { showDateTooltip.value = true; dateTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onDateMove = (e) => { dateTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onDateLeave = () => { showDateTooltip.value = false; };

    const bestratingCountry = computed(() => {
        let country = props.rating.user?.country ?? props.rating.country;

        return (country == 'XX') ? '_404' : country;
    });

    const getRoute = computed(() => {
        // Only link if user exists and has valid ID
        if (props.rating.user?.id) {
            return route('profile.index', props.rating.user.id);
        }

        // Rankings typically don't have MDD profiles, only registered users
        // If there's no user, don't create a link
        return null;
    });

</script>

<template>
    <component
        :is="getRoute ? Link : 'div'"
        :href="getRoute"
        :class="[
            'group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 first:rounded-t-[10px] last:rounded-b-[10px] hover:drop-shadow-[0_0_12px_rgba(255,255,255,0.08)]',
            !getRoute && isLoggedIn ? 'cursor-default opacity-60' : !getRoute ? 'cursor-help opacity-60' : 'cursor-pointer'
        ]"
        @mouseenter="!getRoute && (showTooltip = true)"
        @mouseleave="!getRoute && (showTooltip = false)"
    >
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
        <!-- Background Profile Photo (always visible, blurred) -->
        <div class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px] overflow-hidden">
            <img
                :src="rating.user?.profile_photo_path ? '/storage/' + rating.user?.profile_photo_path : '/images/null.jpg'"
                class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-20 group-hover:opacity-100 transition-all duration-500"
                :alt="rating.user?.name ?? rating.name"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
        </div>

        <!-- Content (relative to background) -->
        <div class="relative flex items-center gap-2 sm:gap-3 w-full transition-all duration-300">
            <!-- Rank -->
            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                <span v-if="rank === 1" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥇</span>
                <span v-else-if="rank === 2" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥈</span>
                <span v-else-if="rank === 3" class="text-sm sm:text-base leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">🥉</span>
                <span v-else class="text-[10px] sm:text-xs font-bold tabular-nums text-gray-300 group-hover:text-white transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ rank }}</span>
            </div>

            <!-- Profile Photo -->
            <div class="flex-shrink-0 overflow-visible">
                <div :class="'avatar-effect-' + (rating.user?.avatar_effect || 'none')" :style="`--effect-color: ${rating.user?.color || '#ffffff'}; --border-color: ${rating.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 14px`">
                    <img
                        :src="rating.user?.profile_photo_path ? '/storage/' + rating.user?.profile_photo_path : '/images/null.jpg'"
                        class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                        :style="`border-color: ${rating.user?.avatar_border_color || '#6b7280'}`"
                        :alt="rating.user?.name ?? rating.name"
                    />
                </div>
            </div>

            <!-- Player Info -->
            <div class="flex items-center gap-1 sm:gap-1.5 flex-1 min-w-0">
                <img :src="`/images/flags/${bestratingCountry}.png`" class="w-3.5 h-2.5 sm:w-4 sm:h-3 flex-shrink-0 opacity-90 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" onerror="this.src='/images/flags/_404.png'" :title="bestratingCountry">
                <span :class="'name-effect-' + (rating.user?.name_effect || 'none')" :style="`--effect-color: ${rating.user?.color || '#ffffff'}`" class="text-[11px] sm:text-sm font-semibold text-gray-300 group-hover:text-white truncate group-hover:text-blue-200 transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]" v-html="q3tohtml(rating.user?.name ?? rating.name)"></span>
            </div>

            <!-- Rating Score -->
            <div class="w-16 sm:w-20 flex-shrink-0 text-right">
                <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ rating.player_rating.toFixed(3) }}</div>
            </div>

            <!-- Physics Icon -->
            <!-- <div class="w-5 sm:w-6 flex-shrink-0 text-center">
                <img v-if="rating.physics.includes('cpm')" src="/images/modes/cpm-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="CPM" />
                <img v-else src="/images/modes/vq3-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="VQ3" />
            </div> -->

            <!-- Last Activity -->
            <div class="w-14 sm:w-20 flex-shrink-0 text-right"
                @mouseenter="onDateEnter"
                @mousemove="onDateMove"
                @mouseleave="onDateLeave"
            >
                <div class="text-[8px] sm:text-[10px] text-gray-300 group-hover:text-white font-mono transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">
                    {{ new Date(rating.last_activity).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }}
                </div>
            </div>
        </div>

        <!-- Last Activity Tooltip -->
        <Teleport to="body">
            <div
                v-if="showDateTooltip"
                class="fixed z-50 pointer-events-none"
                :style="{ left: dateTooltipPos.x + 15 + 'px', top: dateTooltipPos.y - 40 + 'px' }"
            >
                <div class="bg-gray-900 border border-white/10 text-gray-200 rounded-lg px-3 py-1.5 shadow-2xl text-xs font-semibold whitespace-nowrap">
                    <span class="text-gray-500">Last Active:</span> {{ new Date(rating.last_activity).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                </div>
            </div>
        </Teleport>
    </component>
</template>
