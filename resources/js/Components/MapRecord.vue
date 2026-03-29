<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import axios from 'axios';
    import DemoReportModal from '@/Components/DemoReportModal.vue';
    import DemoFlagModal from '@/Components/DemoFlagModal.vue';
    import DemoRenderButton from '@/Components/DemoRenderButton.vue';

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
    const showFlagModal = ref(false);
    const showYoutubeEmbed = ref(false);
    const showYoutubeLinkModal = ref(false);
    const youtubeLinkInput = ref('');
    const youtubeLinkSaving = ref(false);
    const youtubeLinkError = ref(null);
    const actionsExpanded = ref(false);
    let actionsTimeout = null;
    const expandActions = () => {
        clearTimeout(actionsTimeout);
        actionsExpanded.value = true;
    };
    const collapseActions = () => {
        actionsTimeout = setTimeout(() => { actionsExpanded.value = false; }, 500);
    };

    const renderRequesting = ref(false);
    const renderRequested = ref(false);
    const renderError = ref(null);
    const showRenderConfirm = ref(false);

    const renderedVideo = computed(() => {
        if (props.record.rendered_videos && props.record.rendered_videos.length > 0) {
            return props.record.rendered_videos[0];
        }
        return null;
    });

    const canRequestRender = computed(() => {
        if (!isLoggedIn.value || renderedVideo.value || renderRequested.value) return false;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return true;
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return true;
        return false;
    });

    const confirmRender = () => {
        showRenderConfirm.value = true;
    };

    const cancelRender = () => {
        showRenderConfirm.value = false;
    };

    const requestRender = async () => {
        showRenderConfirm.value = false;
        if (renderRequesting.value || !canRequestRender.value) return;
        renderRequesting.value = true;
        renderError.value = null;
        try {
            const demoId = props.record.uploaded_demos?.[0]?.id || props.record.demo?.id;
            const recordId = props.record.id || props.record.record_id;
            await axios.post('/render/request', { record_id: recordId, demo_id: demoId });
            renderRequested.value = true;
            // Redirect to YouTube page after short delay
            setTimeout(() => {
                window.location.href = '/youtube';
            }, 500);
        } catch (e) {
            renderError.value = e.response?.data?.error || 'Failed';
        } finally {
            renderRequesting.value = false;
        }
    };

    const flagRecordId = computed(() => {
        if (isOfflineRecord.value || isOnlineDemo.value) return null;
        return props.record.id ?? null;
    });

    const flagDemoId = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return props.record.demo.id;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return props.record.uploaded_demos[0].id;
        return null;
    });
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const canReportDemo = computed(() => {
        return isLoggedIn.value && page.props.canReportDemos;
    });

    const isAdmin = computed(() => {
        return page.props.auth?.user?.is_admin || page.props.auth?.user?.admin;
    });

    const demoIdForYoutubeLink = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return props.record.demo.id;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return props.record.uploaded_demos[0].id;
        return null;
    });

    const linkYoutube = async () => {
        const input = youtubeLinkInput.value.trim();
        if (!input) return;

        // Extract video ID from URL or use directly
        let videoId = input;
        const match = input.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/);
        if (match) videoId = match[1];

        const demoId = demoIdForYoutubeLink.value;
        if (!demoId) { youtubeLinkError.value = 'No demo found'; return; }

        youtubeLinkSaving.value = true;
        youtubeLinkError.value = null;
        try {
            await axios.post(`/demos/${demoId}/link-youtube`, { youtube_video_id: videoId });
            showYoutubeLinkModal.value = false;
            youtubeLinkInput.value = '';
            window.location.reload();
        } catch (e) {
            youtubeLinkError.value = e.response?.data?.message || 'Failed to link';
        } finally {
            youtubeLinkSaving.value = false;
        }
    };

    const showValidityTooltip = ref(false);
    const validityTooltipPos = ref({ x: 0, y: 0 });
    const showVerifiedTooltip = ref(false);
    const verifiedTooltipPos = ref({ x: 0, y: 0 });
    const showRecTooltip = ref(false);
    const recTooltipPos = ref({ x: 0, y: 0 });
    const hoveredCommunityFlag = ref(null);
    const communityFlagTooltipPos = ref({ x: 0, y: 0 });

    const onCommunityFlagHover = (event, flag) => {
        hoveredCommunityFlag.value = flag;
        const rect = event.target.getBoundingClientRect();
        communityFlagTooltipPos.value = {
            x: rect.left + rect.width / 2,
            y: rect.top,
        };
    };

    const onValidityHover = (event) => {
        showValidityTooltip.value = true;
        const rect = event.target.getBoundingClientRect();
        validityTooltipPos.value = {
            x: rect.left + rect.width / 2,
            y: rect.top,
        };
    };

    const VALIDITY_FLAGS = {
        'sv_cheats=1.0': { short: 'cheats', desc: 'sv_cheats was enabled - cheat commands allowed' },
        'client_finish=false': { short: 'no finish', desc: 'Player did not cross the finish line properly' },
        'tool_assisted=true': { short: 'TAS', desc: 'Tool-assisted speedrun - not human input' },
        'timescale=': { short: 'timescale', desc: 'Game speed was altered with timescale', prefix: true },
        'g_speed=': { short: 'speed', desc: 'Player movement speed was modified', prefix: true, showValue: true },
        'g_gravity=': { short: 'gravity', desc: 'Gravity was modified from default value', prefix: true },
        'pmove_fixed=0.0': { short: 'pmove 0', desc: 'pmove_fixed=0 - physics not fixed to framerate' },
        'pmove_fixed=': { short: 'pmove', desc: 'Non-standard pmove_fixed value', prefix: true, showValue: true },
        'pmove_msec=': { short: 'msec', desc: 'Non-standard pmove_msec value', prefix: true, showValue: true },
        'sv_fps=': { short: 'fps', desc: 'Non-standard server framerate', prefix: true, showValue: true },
        'com_maxfps=': { short: 'maxfps', desc: 'Non-standard max framerate cap', prefix: true, showValue: true },
        'df_mp_interferenceoff=': { short: 'interf', desc: 'df_mp_interferenceoff - interference setting modified', prefix: true, showValue: true },
    };

    const formatValidityFlag = (flag) => {
        if (!flag) return { short: flag, original: flag, desc: '' };

        // Exact match first
        if (VALIDITY_FLAGS[flag]) {
            return { short: VALIDITY_FLAGS[flag].short, original: flag, desc: VALIDITY_FLAGS[flag].desc };
        }

        // Prefix match
        for (const [key, config] of Object.entries(VALIDITY_FLAGS)) {
            if (config.prefix && flag.startsWith(key)) {
                const value = flag.slice(key.length).replace(/\.0$/, '');
                const short = config.showValue ? `${config.short} ${value}` : config.short;
                return { short, original: flag, desc: `${config.desc} (${flag})` };
            }
        }

        // Unknown flag - just clean it up
        return { short: flag.replace(/=.*/, ''), original: flag, desc: flag };
    };

    const validityInfo = computed(() => {
        if (!props.record.verification_type) return null;
        if (['OFFLINE', 'ONLINE', 'verified'].includes(props.record.verification_type)) return null;
        return formatValidityFlag(props.record.verification_type);
    });

    // Community flags (approved by admin)
    const COMMUNITY_FLAG_LABELS = {
        'sv_cheats': { short: 'cheats', desc: 'sv_cheats was enabled - flagged by community' },
        'tool_assisted': { short: 'TAS', desc: 'Tool-assisted speedrun - flagged by community' },
        'client_finish': { short: 'no finish', desc: 'No proper finish - flagged by community' },
        'timescale': { short: 'timescale', desc: 'Game speed was altered - flagged by community' },
        'g_speed': { short: 'speed', desc: 'Movement speed modified - flagged by community' },
        'g_gravity': { short: 'gravity', desc: 'Gravity modified - flagged by community' },
        'sv_fps': { short: 'fps', desc: 'Non-standard server FPS - flagged by community' },
        'com_maxfps': { short: 'maxfps', desc: 'Non-standard max FPS - flagged by community' },
        'pmove_fixed': { short: 'pmove', desc: 'Non-standard pmove_fixed - flagged by community' },
        'pmove_msec': { short: 'msec', desc: 'Non-standard pmove_msec - flagged by community' },
        'df_mp_interferenceoff': { short: 'interf', desc: 'Interference setting modified - flagged by community' },
        'other': { short: 'flagged', desc: 'Flagged by community for validity issue' },
    };

    const communityFlags = computed(() => {
        const flags = props.record.approved_flags;
        if (!flags || flags.length === 0) return [];
        return flags.map(f => ({
            short: COMMUNITY_FLAG_LABELS[f.flag_type]?.short ?? f.flag_type,
            desc: COMMUNITY_FLAG_LABELS[f.flag_type]?.desc ?? f.flag_type,
            count: f.flag_count || 1,
            original: f.flag_type,
        }));
    });

    const demoDownloadUrl = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) {
            return `/demos/${props.record.demo.id}/download`;
        }
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) {
            return `/demos/${props.record.uploaded_demos[0].id}/download`;
        }
        return null;
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

    const hasValidityIssue = computed(() => {
        return !!validityInfo.value || communityFlags.value.length > 0;
    });

    const isVerified = computed(() => {
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return true;
        if (props.record.verification_type === 'verified') return true;
        return false;
    });

    const rankColorClass = computed(() => {
        if (hasValidityIssue.value) {
            return 'text-red-500/50';
        }
        if (props.record.rank === 1) {
            return 'text-yellow-200 !text-base [text-shadow:0_0_8px_#facc15,0_0_20px_#facc15,0_0_40px_#facc15,0_0_80px_#facc15,0_0_120px_rgba(250,204,21,0.5)]';
        }
        if (props.record.rank === 2) {
            return 'text-white !text-base [text-shadow:0_0_8px_#e2e8f0,0_0_20px_#e2e8f0,0_0_40px_#e2e8f0,0_0_80px_rgba(226,232,240,0.5)]';
        }
        if (props.record.rank === 3) {
            return 'text-amber-300 !text-base [text-shadow:0_0_8px_#f59e0b,0_0_20px_#f59e0b,0_0_40px_#f59e0b,0_0_80px_rgba(245,158,11,0.5)]';
        }
        if (isVerified.value) {
            return 'text-green-400 group-hover:text-green-300';
        }
        return 'text-gray-400';
    });

</script>

<template>
    <div
        class="group relative flex items-center gap-1.5 -mr-1 py-1.5 rounded-md transition-all duration-200 hover:bg-white/10 hover:scale-[1.02] hover:shadow-lg -ml-3"
        :class="{
            'bg-gradient-to-r from-emerald-500/15 to-transparent border-l-2 border-emerald-400 hover:from-emerald-500/25 hover:border-emerald-300': isMyRecord && !record.oldtop,
            'border-l-2 border-transparent hover:border-blue-500/50': !isMyRecord
        }"
    >
        <!-- Rank Number - LARGE and prominent with pop animation -->
        <div
            class="font-black text-sm w-8 flex-shrink-0 text-center leading-none transition-all duration-200 group-hover:scale-110"
            :class="rankColorClass"
            :title="isVerified ? 'Verified - demo attached' : ''"
        >
            <span v-if="hasValidityIssue || !record.rank" class="text-red-500/50" title="Flagged - does not count for ranking">&#x2715;</span>
            <template v-else>{{ record.rank }}</template>
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
                <!-- Primary chip: what type of entry is this (clickable with download icon if demo available) -->
                <span v-if="record.oldtop" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/50">
                    old record
                </span>
                <span
                    v-else-if="isOnlineDemo || (isOfflineRecord && record.is_online)"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-400"
                        :class="{ 'hover:bg-blue-500/30 hover:text-blue-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? 'Download online demo' : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        online
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="isOfflineRecord"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-gray-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-500/20 text-gray-400"
                        :class="{ 'hover:bg-gray-500/30 hover:text-gray-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? 'Download offline demo' : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        offline
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-gray-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="isVerified"
                    class="ml-2 relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/50 cursor-help"
                    @mouseenter="showVerifiedTooltip = true; verifiedTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mousemove="verifiedTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mouseleave="showVerifiedTooltip = false"
                >
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Verified
                    <Teleport to="body" v-if="showVerifiedTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: verifiedTooltipPos.y + 'px', left: verifiedTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-green-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-green-400 font-semibold text-xs">Verified Record</div>
                                <div class="text-gray-300 text-xs mt-0.5">Online record + online demo matches</div>
                            </div>
                        </div>
                    </Teleport>
                </span>
                <span
                    v-else
                    class="ml-2 relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/50 cursor-help"
                    @mouseenter="showRecTooltip = true; recTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mousemove="recTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mouseleave="showRecTooltip = false"
                >
                    rec.
                    <Teleport to="body" v-if="showRecTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: recTooltipPos.y + 'px', left: recTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-amber-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-amber-400 font-semibold text-xs">Online Record</div>
                                <div class="text-gray-300 text-xs mt-0.5">No demo attached - not verified</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Combined demo download + render chip -->
                <span
                    v-if="!record.oldtop && !isOfflineRecord && !isOnlineDemo && record.uploaded_demos && record.uploaded_demos.length > 0"
                    class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/30"
                >
                    <a
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 hover:text-blue-200 transition-colors"
                        :class="{ 'rounded-r': !canRequestRender || renderedVideo }"
                        title="Download demo"
                    >
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        online
                    </a>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/30"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>

                <!-- Secondary chip: validity flags (sv_cheats etc.) -->
                <span
                    v-if="validityInfo"
                    class="relative inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50 cursor-help"
                    @mouseenter="onValidityHover($event)"
                    @mouseleave="showValidityTooltip = false"
                >
                    {{ validityInfo.short }}
                    <Teleport to="body" v-if="showValidityTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: validityTooltipPos.y + 'px', left: validityTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-red-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-red-400 font-semibold text-xs">{{ validityInfo.original }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ validityInfo.desc }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Community flags (approved by admin) -->
                <span
                    v-for="cf in communityFlags"
                    :key="cf.original"
                    class="relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-500/20 text-orange-400 border border-orange-500/50 cursor-help"
                    @mouseenter="onCommunityFlagHover($event, cf)"
                    @mouseleave="hoveredCommunityFlag = null"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                    {{ cf.short }}
                    <span v-if="cf.count > 1" class="text-orange-300">({{ cf.count }})</span>
                </span>
            </template>

            <!-- Default view (no mixed mode) -->
            <template v-else>
                <!-- Record with demo attached: combined download + render chip -->
                <span v-if="!isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                    class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/30"
                >
                    <a
                        v-if="demoDownloadUrl"
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 hover:text-blue-200 transition-colors"
                        :class="{ 'rounded-r': !canRequestRender || renderedVideo }"
                        title="Download demo"
                    >
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        online
                    </a>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/30"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <!-- Offline demo chip (demos top) -->
                <span
                    v-else-if="record.verification_type === 'OFFLINE'"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-gray-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-500/20 text-gray-400"
                        :class="{ 'hover:bg-gray-500/30 hover:text-gray-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? 'Download offline demo' : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        offline
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-gray-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <!-- Online demo chip (demos top) -->
                <span
                    v-else-if="record.verification_type === 'ONLINE'"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-400"
                        :class="{ 'hover:bg-blue-500/30 hover:text-blue-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? 'Download online demo' : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        online
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="validityInfo"
                    class="relative ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50 cursor-help"
                    @mouseenter="onValidityHover($event)"
                    @mouseleave="showValidityTooltip = false"
                >
                    {{ validityInfo.short }}
                    <Teleport to="body" v-if="showValidityTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: validityTooltipPos.y + 'px', left: validityTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-red-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-red-400 font-semibold text-xs">{{ validityInfo.original }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ validityInfo.desc }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Community flags (approved by admin) -->
                <span
                    v-for="cf in communityFlags"
                    :key="cf.original"
                    class="relative ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-500/20 text-orange-400 border border-orange-500/50 cursor-help"
                    @mouseenter="onCommunityFlagHover($event, cf)"
                    @mouseleave="hoveredCommunityFlag = null"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                    {{ cf.short }}
                    <span v-if="cf.count > 1" class="text-orange-300">({{ cf.count }})</span>
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

        <!-- Community flag tooltip - TELEPORTED -->
        <Teleport to="body" v-if="hoveredCommunityFlag">
            <div class="fixed z-[9999] pointer-events-none" :style="{ top: communityFlagTooltipPos.y + 'px', left: communityFlagTooltipPos.x + 'px' }">
                <div class="bg-gray-900 border border-orange-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                    <div class="text-orange-400 font-semibold text-xs flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                        Community Flag
                    </div>
                    <div class="text-gray-300 text-xs mt-0.5">{{ hoveredCommunityFlag.desc }}</div>
                    <div v-if="hoveredCommunityFlag.count > 1" class="text-orange-300/70 text-xs mt-0.5">Flagged by {{ hoveredCommunityFlag.count }} users</div>
                </div>
            </div>
        </Teleport>

        <!-- Action Icons -->
        <div class="flex items-center justify-end flex-shrink-0 gap-0.5 ml-auto">
            <!-- Always visible: assign/match/reassign -->
            <button
                v-if="isLoggedIn && isOnlineDemo && !record.record_id && record.demo"
                @click.stop="emit('assign', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-green-400 hover:bg-green-500/10"
                title="Assign to online record"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="isLoggedIn && !isOnlineDemo && !isOfflineRecord && demoMatches.length > 0 && !(record.uploaded_demos && record.uploaded_demos.length > 0)"
                @click.stop="emit('assign-from-record', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-purple-500/20 text-purple-400 hover:text-purple-300 hover:bg-purple-500/30 animate-pulse"
                :title="`${demoMatches.length} possible demo match${demoMatches.length > 1 ? 'es' : ''}`"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="isLoggedIn && !isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                @click.stop="emit('reassign-record', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-yellow-400 hover:bg-yellow-500/10"
                title="Reassign demo"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>

            <!-- Collapsed: report/flag/youtube-link (expand on hover with 500ms delay) -->
            <div class="relative" @mouseenter="expandActions" @mouseleave="collapseActions">
                <div v-if="!actionsExpanded" class="flex items-center text-gray-500 hover:text-gray-300 cursor-pointer p-0.5 rounded bg-gray-700/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                </div>
                <div v-if="actionsExpanded" class="flex items-center gap-0.5">
                    <button
                        v-if="canReportDemo && !record.oldtop"
                        @click.stop="showFlagModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        title="Flag validity issue"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                        </svg>
                    </button>

                    <button
                        v-if="canReportDemo && getDemoForReport"
                        @click.stop="showReportModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        title="Report demo"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </button>

                    <button
                        v-if="isAdmin && demoIdForYoutubeLink"
                        @click.stop="showYoutubeLinkModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        title="Link YouTube video"
                    >
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Time - MASSIVE and eye-catching -->
        <div class="text-right flex-shrink-0 ml-2">
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

        <!-- Map Score -->
        <div class="w-10 sm:w-12 text-center flex-shrink-0 -ml-1 flex items-start">
            <div v-if="record.map_score" class="text-sm font-black tabular-nums text-yellow-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] w-full" style="line-height: 16px;">{{ Math.round(record.map_score) }}</div>
        </div>

        <!-- Date -->
        <div class="w-[50px] flex-shrink-0 opacity-90 group-hover:opacity-100 transition-opacity text-right">
            <div class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none">
                {{ new Date(record.date_set).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }) }}
            </div>
            <div class="text-[10px] text-gray-400 whitespace-nowrap font-mono group-hover:text-gray-300 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none mt-0.5">
                {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
            </div>
        </div>

    </div>

    <!-- Render Confirm Dialog -->
    <Teleport to="body" v-if="showRenderConfirm">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60" @click.self="cancelRender">
            <div class="bg-gray-900 border border-white/10 rounded-xl p-5 shadow-2xl max-w-sm mx-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-white">Render to YouTube?</h3>
                </div>
                <p class="text-xs text-gray-400 mb-4">This will queue the demo for rendering to a YouTube video. The process may take several minutes.</p>
                <div class="flex gap-2 justify-end">
                    <button @click="cancelRender" class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="requestRender" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        Render
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- YouTube Link Modal (admin) -->
    <Teleport to="body" v-if="showYoutubeLinkModal">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60" @click.self="showYoutubeLinkModal = false">
            <div class="bg-gray-900 border border-white/10 rounded-xl p-5 shadow-2xl max-w-sm mx-4">
                <h3 class="text-sm font-bold text-white mb-3">Link YouTube Video</h3>
                <input
                    v-model="youtubeLinkInput"
                    type="text"
                    placeholder="YouTube URL or video ID"
                    class="w-full px-3 py-2 text-sm bg-gray-800 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-red-500/50"
                    @keyup.enter="linkYoutube"
                >
                <p v-if="youtubeLinkError" class="text-red-400 text-xs mt-2">{{ youtubeLinkError }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showYoutubeLinkModal = false" class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="linkYoutube" :disabled="youtubeLinkSaving" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        {{ youtubeLinkSaving ? '...' : 'Link' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- YouTube Embed -->
    <div v-if="showYoutubeEmbed && renderedVideo" class="bg-black/60 border border-white/10 rounded-lg overflow-hidden mx-1 mb-1">
        <div class="aspect-video">
            <iframe
                :src="`https://www.youtube.com/embed/${renderedVideo.youtube_video_id}?autoplay=1`"
                class="w-full h-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    </div>

    <!-- Demo Report Modal -->
    <DemoReportModal
        :show="showReportModal"
        :demo="getDemoForReport"
        @close="showReportModal = false"
    />

    <!-- Demo Flag Modal -->
    <DemoFlagModal
        :show="showFlagModal"
        :record-id="flagRecordId"
        :demo-id="flagDemoId"
        @close="showFlagModal = false"
    />
</template>