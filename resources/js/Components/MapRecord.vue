<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import DemoReportModal from '@/Components/DemoReportModal.vue';

    const props = defineProps({
        record: Object,
        cpmrecord: Object,
        vq3record: Object,
        physics: String,
        oldtop: {
            type: Boolean,
            default: false
        },
        demoMatches: {
            type: Array,
            default: () => []
        },
        showSourceChips: {
            type: Boolean,
            default: false
        }
    });

    const emit = defineEmits(['assign', 'assign-from-record', 'reassign-record']);

    const page = usePage();
    const showUploaderTooltip = ref(false);
    const showReportModal = ref(false);
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const canReportDemo = computed(() => {
        return isLoggedIn.value && page.props.canReportDemos;
    });

    const getDemoForReport = computed(() => {
        if (isOfflineRecord.value && props.record.demo) {
            return props.record.demo;
        }
        if (isOnlineDemo.value && props.record.demo) {
            return props.record.demo;
        }
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) {
            return props.record.uploaded_demos[0];
        }
        return null;
    });

    // Check if this is an offline record (has demo_id instead of record_id)
    const isOfflineRecord = computed(() => {
        return !!props.record.demo_id && !props.record.record_id;
    });

    // Check if this is an online demo shown in Demos Top
    const isOnlineDemo = computed(() => {
        return props.record.is_online === true;
    });

    const isMyRecord = computed(() => {
        const userId = page.props.auth?.user?.id;
        if (!userId) return false;
        return props.record.user?.id === userId;
    });

    // For offline records and online demos in Demos Top, ALWAYS show player_name from demo file
    // For regular online records, use user's login name
    const displayName = computed(() => {
        // ALWAYS use player_name for offline records (from demo file)
        if (isOfflineRecord.value) {
            return props.record.player_name || props.record.name;
        }
        // For online demos assigned to records, ALWAYS use the 'name' field from backend
        // Backend sets this to record owner's name (either user.name or record.name)
        if (isOnlineDemo.value && props.record.record_id && props.record.name) {
            return props.record.name;
        }
        // For online demos NOT assigned to records (fallback-assigned), use player_name from demo
        if (isOnlineDemo.value) {
            return props.record.player_name || props.record.name;
        }
        // For regular online records, use user's login name
        return props.record.user?.name ?? props.record.name;
    });

    const bestrecordCountry = computed(() => {
        // Get country from user (uploader/record owner)
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

        // Linked users with full profiles
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        // Fallback to mdd profile for unlinked accounts
        if (props.record.mdd_id) {
            return route('profile.mdd', props.record.mdd_id);
        }

        return null;
    })

    const rankColorClass = computed(() => {
        if (isMyRecord.value) {
            return 'text-emerald-400 group-hover:text-emerald-300';
        }
        if (props.record.rank === 1) {
            return 'text-yellow-400 group-hover:text-yellow-300';
        }
        if (props.record.rank === 2) {
            return 'text-gray-200 group-hover:text-gray-100';
        }
        if (props.record.rank === 3) {
            return 'text-orange-400 group-hover:text-orange-300';
        }
        return 'text-gray-300 group-hover:text-white';
    });

</script>

<template>
    <div
        class="group relative flex items-center gap-2 px-2 py-1.5 rounded-md transition-all duration-200 hover:bg-white/10 hover:scale-[1.02] hover:shadow-lg"
        :class="{
            'bg-gradient-to-r from-emerald-500/15 to-transparent border-l-2 border-emerald-400 hover:from-emerald-500/25 hover:border-emerald-300': isMyRecord && !record.oldtop,
            'border-l-2 border-transparent hover:border-blue-500/50': !isMyRecord
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
                'flex items-center gap-2 min-w-0 flex-1 overflow-visible group/player transition-all duration-200 group-hover:ml-1',
                !getRoute && isLoggedIn && !isOfflineRecord ? 'cursor-default opacity-70' : !getRoute && !isOfflineRecord ? 'cursor-help opacity-70' : !getRoute && isOfflineRecord ? 'cursor-default' : 'cursor-pointer'
            ]"
            @mouseenter="isOfflineRecord && (showUploaderTooltip = true)"
            @mouseleave="isOfflineRecord && (showUploaderTooltip = false)"
        >
            <div class="overflow-visible flex-shrink-0">
                <!-- Show user's avatar with effects -->
                <div
                    :class="'avatar-effect-' + (record.user?.avatar_effect || 'none')"
                    :style="`--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 16px`"
                >
                    <img
                        class="h-7 w-7 rounded-full object-cover border-2 relative"
                        :style="`border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                        :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
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
                    'name-effect-' + (record.user?.name_effect || 'none'),
                    'text-sm font-semibold whitespace-nowrap overflow-visible group-hover/player:text-blue-400 transition-colors', {
                        'text-emerald-200': isMyRecord,
                        'text-gray-200': !isMyRecord
                    }
                ]"
                :style="`--effect-color: ${record.user?.color || '#ffffff'}`"
                v-html="q3tohtml(displayName)"
            ></span>

            <!-- Source type chips (shown in mixed views) -->
            <template v-if="showSourceChips">
                <!-- Primary chip: what type of entry is this -->
                <span v-if="record.oldtop" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/50">
                    OLD RECORD
                </span>
                <span v-else-if="isOnlineDemo || (isOfflineRecord && record.is_online)" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/50">
                    ONLINE DEMO
                </span>
                <span v-else-if="isOfflineRecord" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500/20 text-gray-400 border border-gray-500/50">
                    OFFLINE DEMO
                </span>
                <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/50">
                    RECORD
                </span>

                <!-- Secondary chip: demo attached to a record -->
                <span v-if="!record.oldtop && !isOfflineRecord && !isOnlineDemo && record.uploaded_demos && record.uploaded_demos.length > 0" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-500/10 text-blue-300 border border-blue-500/30">
                    ONLINE DEMO
                </span>

                <!-- Secondary chip: validity flags (sv_cheats etc.) -->
                <span v-if="record.verification_type && record.verification_type !== 'OFFLINE' && record.verification_type !== 'ONLINE' && record.verification_type !== 'verified'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50">
                    {{ record.verification_type }}
                </span>
            </template>

            <!-- Default view (no mixed mode): just show verified badge -->
            <template v-else>
                <span
                    v-if="!isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                    class="ml-1 flex-shrink-0 text-green-400"
                    title="Verified — demo attached"
                >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <!-- Validity flags in demos top without mixed mode -->
                <span v-if="record.verification_type === 'OFFLINE'" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500/20 text-gray-400 border border-gray-500/50">
                    OFFLINE
                </span>
                <span v-else-if="record.verification_type === 'ONLINE'" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/50">
                    ONLINE
                </span>
                <span v-else-if="record.verification_type && record.verification_type !== 'verified'" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50">
                    {{ record.verification_type }}
                </span>
            </template>
        </component>

        <!-- Offline record uploader tooltip - TELEPORTED -->
        <Teleport to="body" v-if="isOfflineRecord && showUploaderTooltip && record.user">
            <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
                <div class="bg-gray-900 border-2 border-blue-500 text-gray-300 px-4 py-2 rounded-lg text-sm font-semibold shadow-xl">
                    Uploaded by: <span class="text-blue-400" v-html="q3tohtml(record.user.name)"></span>
                </div>
            </div>
        </Teleport>

        <!-- Action Icons - Fixed width to keep time/date columns aligned -->
        <div class="flex items-center gap-1 w-16 justify-end flex-shrink-0">
            <a
                v-if="(record.uploaded_demos && record.uploaded_demos.length > 0) || (isOfflineRecord && record.demo) || (isOnlineDemo && record.demo)"
                :href="(isOfflineRecord || isOnlineDemo) ? `/demos/${record.demo.id}/download` : `/demos/${record.uploaded_demos[0].id}/download`"
                class="p-1 rounded transition-all hover:scale-110"
                :class="{
                    'bg-emerald-500/20 text-emerald-400': isMyRecord,
                    'bg-gray-700/50 text-gray-400': !isMyRecord
                }"
                title="Download demo"
                @click.stop
            >
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                </svg>
            </a>

            <button
                v-if="isLoggedIn && isOnlineDemo && !record.record_id && record.demo"
                @click.stop="emit('assign', record)"
                class="p-1 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-green-400 hover:bg-green-500/10"
                title="Assign to online record"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="isLoggedIn && !isOnlineDemo && !isOfflineRecord && demoMatches.length > 0 && !(record.uploaded_demos && record.uploaded_demos.length > 0)"
                @click.stop="emit('assign-from-record', record)"
                class="p-1 rounded transition-all hover:scale-110 bg-purple-500/20 text-purple-400 hover:text-purple-300 hover:bg-purple-500/30 animate-pulse"
                :title="`${demoMatches.length} possible demo match${demoMatches.length > 1 ? 'es' : ''}`"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="isLoggedIn && !isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                @click.stop="emit('reassign-record', record)"
                class="p-1 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-yellow-400 hover:bg-yellow-500/10"
                title="Reassign demo"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>

            <button
                v-if="canReportDemo && getDemoForReport"
                @click.stop="showReportModal = true"
                class="p-1 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                title="Report demo"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </button>
        </div>

        <!-- Time - MASSIVE and eye-catching -->
        <div class="text-right flex-shrink-0 min-w-[70px]">
            <div
                class="font-black text-base tabular-nums leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                :class="{
                    'text-emerald-300': isMyRecord,
                    'text-white': !isMyRecord
                }"
            >
                {{ formatTime(record.time) }}
            </div>
            <div v-if="timeDiff" class="text-[10px] text-red-400 tabular-nums leading-none mt-0.5 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                -{{ formatTime(timeDiff) }}
            </div>
        </div>

        <!-- Date -->
        <div class="flex-shrink-0 opacity-90 group-hover:opacity-100 transition-opacity">
            <div
                class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                :title="record.date_set"
            >
                {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }} {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
            </div>
        </div>

        <!-- Left side badges -->
        <div class="absolute -left-1 top-1/2 -translate-y-1/2 text-lg transition-all duration-200 group-hover:scale-110 group-hover:translate-x-0.5">
            <span v-if="record.rank === 1">🥇</span>
            <span v-else-if="record.rank === 2">🥈</span>
            <span v-else-if="record.rank === 3">🥉</span>
        </div>
    </div>

    <!-- Demo Report Modal -->
    <DemoReportModal
        :show="showReportModal"
        :demo="getDemoForReport"
        @close="showReportModal = false"
    />
</template>