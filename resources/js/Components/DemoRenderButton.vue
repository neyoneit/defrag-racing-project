<script setup>
    import { ref } from 'vue';
    import { router } from '@inertiajs/vue3';

    const props = defineProps({
        renderedVideo: Object,
        record: Object,
        canRequestRender: Boolean,
        renderRequesting: Boolean,
        renderRequested: Boolean,
        renderError: String,
        borderColor: {
            type: String,
            default: 'border-blue-500/50'
        }
    });

    const formatTime = (ms) => {
        if (!ms) return '-';
        const totalSeconds = ms / 1000;
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = (totalSeconds % 60).toFixed(3);
        return `${String(minutes).padStart(2, '0')}.${seconds.padStart(6, '0')}`;
    };

    const recordSource = () => {
        if (!props.record) return '-';
        if (props.record.uploaded_demos?.length > 0) return 'Uploaded demo';
        if (props.record.demo) return 'Online demo';
        return 'Offline record';
    };

    const emit = defineEmits(['confirm-render', 'toggle-youtube']);

    const showReportModal = ref(false);
    const reportSending = ref(false);
    const reportSent = ref(false);
    const reportMessage = ref('');

    const videoStatus = () => props.renderedVideo?.status || null;

    const handleClick = () => {
        const status = videoStatus();
        if (status === 'completed') {
            emit('toggle-youtube');
        } else if (status === 'failed') {
            showReportModal.value = true;
        } else if (status === 'pending' || status === 'rendering' || status === 'uploading') {
            router.visit('/youtube');
        }
    };

    const statusTitle = () => {
        const status = videoStatus();
        if (status === 'completed') return 'Watch on YouTube';
        if (status === 'pending') return 'Video is in queue to be processed. Click to see more info.';
        if (status === 'rendering') return 'Video is currently being rendered. Click to see more info.';
        if (status === 'uploading') return 'Video is being uploaded to YouTube. Click to see more info.';
        if (status === 'failed') return 'Render failed. Click to report this issue.';
        return '';
    };

    const statusColors = () => {
        const status = videoStatus();
        if (status === 'completed') return 'bg-red-600/25 text-red-400 hover:bg-red-600/40 hover:text-red-300';
        if (status === 'failed') return 'bg-orange-600/25 text-orange-400 hover:bg-orange-600/40 hover:text-orange-300';
        return 'bg-yellow-600/20 text-yellow-400 hover:bg-yellow-600/30 hover:text-yellow-300';
    };

    const sendReport = async () => {
        reportSending.value = true;
        try {
            await axios.post('/api/rendered-videos/' + props.renderedVideo.id + '/report', {
                message: reportMessage.value,
                record_id: props.record?.id,
                source: recordSource(),
            });
            reportSent.value = true;
        } catch (e) {
            console.error('Failed to send report:', e);
        } finally {
            reportSending.value = false;
        }
    };

    const closeReport = () => {
        showReportModal.value = false;
        reportMessage.value = '';
    };

    const showTooltip = ref(false);
    const tooltipStyle = ref({});
    const buttonRef = ref(null);

    const onMouseEnter = () => {
        if (videoStatus() === 'completed') return;
        const el = buttonRef.value;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        tooltipStyle.value = {
            position: 'fixed',
            left: rect.left + rect.width / 2 + 'px',
            top: rect.top - 8 + 'px',
            transform: 'translate(-50%, -100%)',
            zIndex: 250,
        };
        showTooltip.value = true;
    };

    const onMouseLeave = () => {
        showTooltip.value = false;
    };
</script>

<template>
    <!-- Existing rendered video (any status) -->
    <div v-if="renderedVideo" class="relative inline-flex">
        <button
            ref="buttonRef"
            @click.stop.prevent="handleClick" @mousedown.stop.prevent
            @mouseenter="onMouseEnter"
            @mouseleave="onMouseLeave"
            class="inline-flex items-center gap-0.5 py-0.5 px-1.5 border-l transition-all"
            :class="[borderColor, statusColors()]"
        >
        <!-- YouTube icon for completed -->
        <svg v-if="videoStatus() === 'completed'" class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>

        <!-- Spinner for pending/rendering/uploading -->
        <svg v-else-if="videoStatus() !== 'failed'" class="w-3.5 h-3.5 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>

        <!-- Warning icon for failed -->
        <svg v-else class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>

        <span v-if="videoStatus() === 'completed'" class="text-[10px] font-bold">YouTube</span>
    </button>

        <!-- Tooltip teleported to body (avoids overflow:hidden clipping) -->
        <Teleport to="body">
            <div
                v-if="showTooltip"
                :style="tooltipStyle"
                class="px-2.5 py-1.5 bg-gray-900/95 border border-white/20 rounded-lg text-[11px] text-gray-200 whitespace-nowrap shadow-xl pointer-events-none"
            >
                {{ statusTitle() }}
            </div>
        </Teleport>
    </div>

    <!-- No rendered video - request render -->
    <button
        v-else-if="canRequestRender"
        @click.stop.prevent="emit('confirm-render')" @mousedown.stop.prevent
        :disabled="renderRequesting"
        class="inline-flex items-center gap-0 py-0.5 px-1.5 border-l transition-all"
        :class="[borderColor, renderRequested ? 'bg-green-500/15 text-green-400' : renderError ? 'bg-red-500/15 text-red-400' : 'bg-red-500/10 text-red-400 hover:bg-red-500/20 hover:text-red-300']"
        :title="renderError || 'Request YouTube render'"
    >
        <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
        <span class="max-w-0 group-hover:max-w-[60px] overflow-hidden transition-all duration-200 whitespace-nowrap" :class="{ '!max-w-[60px]': renderRequested || renderError }">
            <span class="ml-1">{{ renderRequesting ? '...' : renderRequested ? 'queued' : renderError ? 'error' : 'render' }}</span>
        </span>
    </button>

    <!-- Failed render report modal -->
    <Teleport to="body">
        <div v-if="showReportModal" class="fixed inset-0 z-[300] flex items-center justify-center" @click.self="closeReport">
            <div class="fixed inset-0 bg-black/60" @click="closeReport"></div>
            <div class="relative z-10 bg-gray-800 border border-gray-600 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
                <template v-if="!reportSent">
                    <h3 class="text-lg font-bold text-white mb-2">Video Render Failed</h3>
                    <p class="text-gray-300 text-sm mb-4">
                        The video render for this record failed during processing.
                        You can notify the admin to investigate and fix the issue.
                    </p>

                    <div class="bg-gray-900/50 rounded-lg p-3 mb-4 text-xs text-gray-400 space-y-1">
                        <div><span class="text-gray-500">Map:</span> {{ renderedVideo?.map_name }}</div>
                        <div><span class="text-gray-500">Player:</span> {{ renderedVideo?.player_name }}</div>
                        <div v-if="renderedVideo?.physics"><span class="text-gray-500">Physics:</span> {{ renderedVideo?.physics?.toUpperCase() }}</div>
                        <div v-if="renderedVideo?.time_ms"><span class="text-gray-500">Time:</span> {{ formatTime(renderedVideo.time_ms) }}</div>
                        <div><span class="text-gray-500">Source:</span> {{ recordSource() }}</div>
                        <div v-if="record?.date_set"><span class="text-gray-500">Date:</span> {{ record.date_set }}</div>
                    </div>

                    <label class="block text-sm text-gray-400 mb-1">Additional info (optional)</label>
                    <textarea
                        v-model="reportMessage"
                        class="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 resize-none"
                        rows="2"
                        placeholder="Any extra details..."
                    ></textarea>

                    <div class="flex gap-3 mt-4">
                        <button
                            @click="sendReport"
                            :disabled="reportSending"
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            {{ reportSending ? 'Sending...' : 'Send Report to Admin' }}
                        </button>
                        <button
                            @click="closeReport"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="text-center py-4">
                        <svg class="w-12 h-12 text-green-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="text-lg font-bold text-white mb-1">Report Sent</h3>
                        <p class="text-gray-400 text-sm">The admin has been notified and will look into it.</p>
                        <button
                            @click="closeReport"
                            class="mt-4 px-6 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
