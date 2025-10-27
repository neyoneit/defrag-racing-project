<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';

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
    const showTooltip = ref(false);
    const showUploaderTooltip = ref(false);
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    // Check if this is an offline record (has demo_id instead of record_id)
    const isOfflineRecord = computed(() => {
        return !!props.record.demo_id && !props.record.record_id;
    });

    const isMyRecord = computed(() => {
        const userId = page.props.auth?.user?.id;
        if (!userId) return false;
        return props.record.user?.id === userId;
    });

    // For offline records, show player_name from demo. For online records, use user name.
    const displayName = computed(() => {
        if (isOfflineRecord.value) {
            return props.record.player_name || props.record.name;
        }
        return props.record.user?.name ?? props.record.name;
    });

    const bestrecordCountry = computed(() => {
        // For offline records, we don't have a country - show generic flag
        if (isOfflineRecord.value) {
            return '_404'; // Unknown/generic flag
        }
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
        // Offline records should never link to profiles
        if (isOfflineRecord.value) {
            return null;
        }

        // Only link to registered users with full profiles
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        // Players with only mdd_id have no proper profile page
        // Show tooltip to encourage registration
        return null;
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
        <component
            :is="getRoute ? Link : 'div'"
            :href="getRoute"
            :class="[
                'flex items-center gap-2 min-w-0 flex-1 group/player transition-all duration-200 group-hover:ml-1',
                !getRoute && isLoggedIn && !isOfflineRecord ? 'cursor-default opacity-70' : !getRoute && !isOfflineRecord ? 'cursor-help opacity-70' : !getRoute && isOfflineRecord ? 'cursor-default' : 'cursor-pointer'
            ]"
            @mouseenter="!getRoute && !isOfflineRecord && (showTooltip = true); isOfflineRecord && (showUploaderTooltip = true)"
            @mouseleave="!getRoute && !isOfflineRecord && (showTooltip = false); isOfflineRecord && (showUploaderTooltip = false)"
        >
            <div class="overflow-visible flex-shrink-0">
                <!-- For offline records, use plain avatar with no effects -->
                <div
                    :class="isOfflineRecord ? 'avatar-effect-none' : ('avatar-effect-' + (record.user?.avatar_effect || 'none'))"
                    :style="isOfflineRecord ? '--border-color: #6b7280; --orbit-radius: 16px' : `--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 16px`"
                >
                    <img
                        class="h-7 w-7 rounded-full object-cover border-2 relative"
                        :style="isOfflineRecord ? 'border-color: #6b7280' : `border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                        :src="isOfflineRecord ? '/images/null.jpg' : (record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg')"
                        :alt="displayName"
                    />
                </div>
            </div>
            <img
                :src="`/images/flags/${bestrecordCountry}.png`"
                class="w-5 h-4 flex-shrink-0"
                onerror="this.src='/images/flags/_404.png'"
            >
            <span
                :class="[
                    isOfflineRecord ? 'name-effect-none' : ('name-effect-' + (record.user?.name_effect || 'none')),
                    'text-sm font-semibold truncate group-hover/player:text-blue-400 transition-colors', {
                        'text-amber-200': record.oldtop,
                        'text-emerald-200': isMyRecord && !record.oldtop,
                        'text-gray-200': !isMyRecord && !record.oldtop
                    }
                ]"
                :style="isOfflineRecord ? '' : `--effect-color: ${record.user?.color || '#ffffff'}`"
                v-html="q3tohtml(displayName)"
            ></span>
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
        <Teleport to="body" v-if="!getRoute && showTooltip && isLoggedIn && !isOfflineRecord">
            <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
                <div class="bg-gray-900 border-2 border-gray-600 text-gray-300 px-4 py-2 rounded-lg text-sm font-semibold shadow-xl">
                    Not linked account
                </div>
            </div>
        </Teleport>

        <!-- Offline record uploader tooltip - TELEPORTED -->
        <Teleport to="body" v-if="isOfflineRecord && showUploaderTooltip && record.user">
            <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
                <div class="bg-gray-900 border-2 border-blue-500 text-gray-300 px-4 py-2 rounded-lg text-sm font-semibold shadow-xl">
                    Uploaded by: <span class="text-blue-400" v-html="q3tohtml(record.user.name)"></span>
                </div>
            </div>
        </Teleport>

        <!-- Time - MASSIVE and eye-catching -->
        <div class="text-right">
            <div
                class="font-black text-base tabular-nums leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                :class="{
                    'text-amber-300': record.oldtop,
                    'text-emerald-300': isMyRecord && !record.oldtop,
                    'text-white': !isMyRecord && !record.oldtop
                }"
            >
                {{ formatTime(record.time) }}
            </div>
            <div v-if="timeDiff" class="text-[10px] text-red-400 tabular-nums leading-none mt-0.5 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                -{{ formatTime(timeDiff) }}
            </div>
        </div>

        <!-- Date & Demo - More visible -->
        <div class="flex items-center gap-2 opacity-90 group-hover:opacity-100 transition-opacity">
            <div
                class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                :title="record.date_set"
            >
                {{ new Date(record.date_set).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' }) }}
            </div>

            <a
                v-if="(record.uploaded_demos && record.uploaded_demos.length > 0) || (isOfflineRecord && record.demo)"
                :href="isOfflineRecord ? `/demos/${record.demo.id}/download` : `/demos/${record.uploaded_demos[0].id}/download`"
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