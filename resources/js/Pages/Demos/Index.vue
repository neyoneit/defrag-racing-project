<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
    inheritAttrs: false,
}
</script>

<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Pagination from '@/Components/Basic/Pagination.vue';

const $page = usePage();

const props = defineProps({
    userDemos: Object,
    publicDemos: Object,
    demoCounts: Object,
    browseCounts: Object,
    sortBy: String,
    sortOrder: String,
    browseTab: String,
    browseStatus: String,
    browseSearch: String,
    userSearch: String,
    downloadLimitInfo: Object,
    confidenceFilter: String,
    showOtherUserMatches: Boolean,
    uploadedBy: String,
});
const fileInput = ref(null);
const selectedFiles = ref([]);
const uploading = ref(false);
const uploadErrors = ref([]);
const uploadSuccess = ref([]);
const uploadSummary = ref(null); // { total_sent, total_received, queued, duplicates, errors, skipped_frontend }
const errorsExpanded = ref(false);
const successExpanded = ref(false);

const categorizedErrors = computed(() => {
    const cats = {};
    uploadErrors.value.forEach(err => {
        let category = 'Other';
        if (err.includes('Duplicate file content')) category = 'Duplicates';
        else if (err.includes('Invalid demo file format')) category = 'Invalid Format';
        else if (err.includes('Filename already uploaded')) category = 'Duplicate Filename';
        else if (err.includes('Upload failed') || err.includes('Failed to queue')) category = 'Upload Failed';
        if (!cats[category]) cats[category] = [];
        cats[category].push(err);
    });
    return cats;
});
// Upload info modal state
const showUploadInfo = ref(false);
const uploadInfoTitle = ref('');
const uploadInfoMessage = ref('');
const uploadInfoType = ref('info'); // 'info', 'warning', 'error'

const showUploadInfoModal = (title, message, type = 'info') => {
    uploadInfoTitle.value = title;
    uploadInfoMessage.value = message;
    uploadInfoType.value = type;
    showUploadInfo.value = true;
};

const processingDemos = ref([]);
const queueStats = ref({});
const statusPolling = ref(null);
const uploadProgress = ref(0);
const trackingDemoIds = ref([]);

const activelyProcessingDemos = computed(() => (processingDemos.value || []).filter(d => d.status === 'processing'));
const queuedDemoCount = computed(() => (queueStats.value?.total_queued || 0));
const reprocessingFailed = ref(false);
const recentlyProcessed = ref([]);
const reprocessMessage = ref('');
const actionStartedAt = ref(null);
const showReprocessConfirm = ref(false);
const processingStartTime = ref(null);
const processingDuration = ref(null);
const processingResultsExpanded = ref(false);
const globalQueueExpanded = ref(true);

const processingSummary = computed(() => {
    if (recentlyProcessed.value.length === 0) return null;
    const groups = {
        assigned: { label: 'Assigned', color: 'green', demos: [] },
        'fallback-assigned': { label: 'Fallback', color: 'blue', demos: [] },
        processed: { label: 'Processed', color: 'green', demos: [] },
        failed: { label: 'Failed', color: 'red', demos: [] },
        'failed-validity': { label: 'Invalid', color: 'orange', demos: [] },
        'unsupported-version': { label: 'Unsupported', color: 'purple', demos: [] },
    };
    recentlyProcessed.value.forEach(d => {
        if (groups[d.status]) groups[d.status].demos.push(d);
        else if (groups.failed) groups.failed.demos.push(d);
    });
    // Only return groups that have demos
    const active = {};
    for (const [key, group] of Object.entries(groups)) {
        if (group.demos.length > 0) active[key] = group;
    }
    const successCount = (groups.assigned.demos.length + groups['fallback-assigned'].demos.length + groups.processed.demos.length);
    const failCount = recentlyProcessed.value.length - successCount;
    return { groups: active, total: recentlyProcessed.value.length, success: successCount, fail: failCount };
});

const reprocessAllFailed = async () => {
    showReprocessConfirm.value = false;
    reprocessingFailed.value = true;
    reprocessMessage.value = '';
    try {
        const response = await axios.post(route('demos.reprocessAllFailed'));
        reprocessMessage.value = response.data.message;
        recentlyProcessed.value = [];
        processingDuration.value = null;
        processingStartTime.value = Date.now();
        actionStartedAt.value = new Date();
        startStatusPolling();
        router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
    } catch (error) {
        reprocessMessage.value = 'Failed: ' + (error.response?.data?.message || error.message);
    } finally {
        reprocessingFailed.value = false;
    }
};

// Filter state (for Your Uploads section)
const activeTab = ref('all'); // all, online, offline
const activeStatusFilter = ref('all'); // all, assigned, failed

// Browse filter state (for Browse All Demos section)
const activeBrowseTab = ref(props.browseTab || 'all'); // all, online, offline
const activeBrowseStatus = ref(props.browseStatus || 'all'); // all, assigned, processed
const browseSearchQuery = ref(props.browseSearch || '');
const userSearchQuery = ref(props.userSearch || '');

// Advanced filter state (admin only)
const confidenceFilterValue = ref(props.confidenceFilter || '');
const showOtherUserMatchesValue = ref(props.showOtherUserMatches || false);
const uploadedByValue = ref(props.uploadedBy || '');

// Tooltip state
const hoveredDemo = ref(null);
const tooltipPosition = ref({ x: 0, y: 0 });

const showTooltip = (demo, event) => {
    hoveredDemo.value = demo;
    updateTooltipPosition(event);
};

const hideTooltip = () => {
    hoveredDemo.value = null;
};

const updateTooltipPosition = (event) => {
    tooltipPosition.value = {
        x: event.clientX,
        y: event.clientY
    };
};

// Manual assignment state
const showAssignModal = ref(false);
const assigningDemo = ref(null);
const searchQuery = ref('');
const availableMaps = ref([]);
const selectedMap = ref('');
const selectedPhysics = ref('VQ3');
const availableRecords = ref([]);
const selectedRecord = ref('');
const loadingMaps = ref(false);
const loadingRecords = ref(false);

// Drag and drop state
const isDragOver = ref(false);
const dragCounter = ref(0);

const form = useForm({
    demos: [],
});

// Initialize filter state from URL parameters
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('tab')) {
    activeTab.value = urlParams.get('tab');
}
if (urlParams.has('status')) {
    activeStatusFilter.value = urlParams.get('status');
}

// No client-side filtering needed - server handles it
const filteredDemos = computed(() => {
    return props.userDemos?.data || [];
});

// Upload restrictions
const canUpload = computed(() => {
    return $page.props.canUploadDemos || false;
});

const uploadRestrictionMessage = computed(() => {
    const user = $page.props.auth.user;
    if (!user) return '';

    if (user.upload_restricted) {
        return 'Your account has been restricted from uploading demos. Please contact an administrator.';
    }

    const recordsCount = $page.props.recordsCount || 0;
    const needed = 30 - recordsCount;
    return `You need at least 30 records to upload demos. You currently have ${recordsCount} record(s). ${needed} more needed.`;
});

// Function to change tab filter (ONLINE/OFFLINE/ALL)
const changeTabFilter = (tab) => {
    activeTab.value = tab;
    const currentUrl = new URL(window.location.href);

    if (tab === 'all') {
        currentUrl.searchParams.delete('tab');
    } else {
        currentUrl.searchParams.set('tab', tab);
    }

    // Reset to page 1 when changing filters
    currentUrl.searchParams.delete('userPage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['userDemos', 'demoCounts'],
    });
};

// Function to change status filter (ASSIGNED/FAILED/ALL)
const changeStatusFilter = (status) => {
    activeStatusFilter.value = status;
    const currentUrl = new URL(window.location.href);

    if (status === 'all') {
        currentUrl.searchParams.delete('status');
    } else {
        currentUrl.searchParams.set('status', status);
    }

    // Reset to page 1 when changing filters
    currentUrl.searchParams.delete('userPage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['userDemos', 'demoCounts'],
    });
};

// Use server-provided counts instead of counting current page
const demoCountsComputed = computed(() => {
    const counts = props.demoCounts || {
        all: 0,
        online: 0,
        offline: 0,
        assigned: 0,
        fallback_assigned: 0,
        processed: 0,
        failed_validity: 0,
        failed: 0,
        unsupported_version: 0,
        online_assigned: 0,
        online_fallback_assigned: 0,
        online_processed: 0,
        online_failed_validity: 0,
        online_failed: 0,
        offline_assigned: 0,
        offline_fallback_assigned: 0,
        offline_processed: 0,
        offline_failed_validity: 0,
        offline_failed: 0,
    };

    // Return counts based on active tab
    if (activeTab.value === 'online') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.online_assigned,
            fallback_assigned: counts.online_fallback_assigned,
            processed: counts.online_processed,
            failed_validity: counts.online_failed_validity,
            failed: counts.online_failed,
            unsupported_version: counts.unsupported_version,
        };
    } else if (activeTab.value === 'offline') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.offline_assigned,
            fallback_assigned: counts.offline_fallback_assigned,
            processed: counts.offline_processed,
            failed_validity: counts.offline_failed_validity,
            failed: counts.offline_failed,
            unsupported_version: counts.unsupported_version,
        };
    } else {
        // 'all' tab - show total counts
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.assigned,
            fallback_assigned: counts.fallback_assigned,
            processed: counts.processed,
            failed_validity: counts.failed_validity,
            failed: counts.failed,
            unsupported_version: counts.unsupported_version,
        };
    }
});

// Browse counts computed
const browseCountsComputed = computed(() => {
    const counts = props.browseCounts || {
        all: 0,
        online: 0,
        offline: 0,
        assigned: 0,
        fallback_assigned: 0,
        processed: 0,
        failed_validity: 0,
        failed: 0,
        unsupported_version: 0,
        online_assigned: 0,
        online_fallback_assigned: 0,
        online_processed: 0,
        online_failed_validity: 0,
        online_failed: 0,
        offline_assigned: 0,
        offline_fallback_assigned: 0,
        offline_processed: 0,
        offline_failed_validity: 0,
        offline_failed: 0,
    };

    // Return counts based on active browse tab
    if (activeBrowseTab.value === 'online') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.online_assigned,
            fallback_assigned: counts.online_fallback_assigned,
            processed: counts.online_processed,
            failed_validity: counts.online_failed_validity,
            failed: counts.online_failed,
        };
    } else if (activeBrowseTab.value === 'offline') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.offline_assigned,
            fallback_assigned: counts.offline_fallback_assigned,
            processed: counts.offline_processed,
            failed_validity: counts.offline_failed_validity,
            failed: counts.offline_failed,
        };
    } else {
        // 'all' tab - show total counts
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.assigned,
            fallback_assigned: counts.fallback_assigned,
            processed: counts.processed,
            failed_validity: counts.failed_validity,
            failed: counts.failed,
        };
    }
});

// Function to change browse tab filter (ONLINE/OFFLINE/ALL)
const changeBrowseTabFilter = (tab) => {
    activeBrowseTab.value = tab;
    const currentUrl = new URL(window.location.href);

    if (tab === 'all') {
        currentUrl.searchParams.delete('browse_tab');
    } else {
        currentUrl.searchParams.set('browse_tab', tab);
    }

    // Reset to page 1 when changing filters
    currentUrl.searchParams.delete('browsePage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['publicDemos', 'browseCounts'],
    });
};

// Function to change browse status filter (ASSIGNED/PROCESSED/ALL)
const changeBrowseStatusFilter = (status) => {
    activeBrowseStatus.value = status;
    const currentUrl = new URL(window.location.href);

    if (status === 'all') {
        currentUrl.searchParams.delete('browse_status');
    } else {
        currentUrl.searchParams.set('browse_status', status);
    }

    // Reset to page 1 when changing filters
    currentUrl.searchParams.delete('browsePage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['publicDemos', 'browseCounts'],
    });
};

// Function to handle browse search
const handleBrowseSearch = () => {
    const currentUrl = new URL(window.location.href);

    if (browseSearchQuery.value.trim() === '') {
        currentUrl.searchParams.delete('browse_search');
    } else {
        currentUrl.searchParams.set('browse_search', browseSearchQuery.value.trim());
    }

    // Reset to page 1 when searching
    currentUrl.searchParams.delete('browsePage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['publicDemos', 'browseCounts'],
    });
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files || event.dataTransfer.files);
    const validFiles = files.filter(file => {
        // Get full filename for pattern matching
        const fileName = file.name.toLowerCase();
        // Check if it's a demo file (.dm_68, .dm_66, etc.) or archive (.zip, .rar, .7z)
        return fileName.match(/\.dm_\d+$/) || fileName.endsWith('.zip') || fileName.endsWith('.rar') || fileName.endsWith('.7z');
    });

    if (validFiles.length !== files.length) {
        const skipped = files.length - validFiles.length;
        showUploadInfoModal(
            'Files Filtered',
            `Found ${validFiles.length} demo file(s). Skipped ${skipped} non-demo file(s).\n\nOnly demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.`,
            'info'
        );
    }

    selectedFiles.value = [...selectedFiles.value, ...validFiles];
};

// Drag and drop handlers
const handleDragEnter = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value++;
    isDragOver.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value--;
    if (dragCounter.value === 0) {
        isDragOver.value = false;
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = async (e) => {
    e.preventDefault();
    e.stopPropagation();
    isDragOver.value = false;
    dragCounter.value = 0;

    // Use dataTransfer.files directly for simple file drops
    const files = Array.from(e.dataTransfer.files);

    if (files.length > 0) {
        const validFiles = files.filter(file => {
            const fileName = file.name.toLowerCase();
            return fileName.match(/\.dm_\d+$/) || fileName.endsWith('.zip') || fileName.endsWith('.rar') || fileName.endsWith('.7z');
        });

        if (validFiles.length !== files.length) {
            const skipped = files.length - validFiles.length;
            showUploadInfoModal(
                'Files Filtered',
                `Found ${validFiles.length} demo file(s). Skipped ${skipped} non-demo file(s).\n\nOnly demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.`,
                'info'
            );
        }

        if (validFiles.length > 0) {
            selectedFiles.value = [...selectedFiles.value, ...validFiles];
        }
    }
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const clearAllFiles = () => {
    selectedFiles.value = [];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const BATCH_SIZE = 100;

const uploadDemos = async () => {
    if (selectedFiles.value.length === 0) {
        showUploadInfoModal('No Files Selected', 'Please select demo files to upload.', 'warning');
        return;
    }

    uploading.value = true;
    uploadProgress.value = 0;
    uploadErrors.value = [];
    uploadSuccess.value = [];
    uploadSummary.value = null;

    const uploadStartTime = Date.now();
    const files = [...selectedFiles.value];
    const totalFiles = files.length;
    const totalBatches = Math.ceil(totalFiles / BATCH_SIZE);
    let allUploaded = [];
    let allErrors = [];
    let allDemoIds = [];
    let totalReceived = 0;
    let totalQueued = 0;
    let totalDuplicates = 0;
    let totalOtherErrors = 0;
    let totalReplaced = 0;
    let pollingStarted = false;

    // Prepare tracking state before upload loop so polling can work during upload
    recentlyProcessed.value = [];
    processingDuration.value = null;
    processingStartTime.value = Date.now();
    actionStartedAt.value = new Date();

    try {
        for (let i = 0; i < totalBatches; i++) {
            const batchFiles = files.slice(i * BATCH_SIZE, (i + 1) * BATCH_SIZE);
            const formData = new FormData();
            batchFiles.forEach(file => {
                formData.append('demos[]', file);
            });

            console.log(`[Upload] Batch ${i + 1}/${totalBatches} sending ${batchFiles.length} files...`);
            try {
                const response = await axios.post(route('demos.upload'), formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                    timeout: 120000, // 2 min timeout per batch
                    onUploadProgress: function (progressEvent) {
                        if (progressEvent.lengthComputable) {
                            const batchProgress = (progressEvent.loaded / progressEvent.total);
                            const overallProgress = ((i + batchProgress) / totalBatches) * 100;
                            uploadProgress.value = Math.round(overallProgress);
                        }
                    }
                });

                if (response.data.success) {
                    console.log(`[Upload] Batch ${i + 1}/${totalBatches} done: queued=${response.data.summary?.queued || 0}, dupes=${response.data.summary?.duplicates || 0}`);
                    allUploaded.push(...(response.data.uploaded || []));
                    allErrors.push(...(response.data.errors || []));
                    const demoIds = (response.data.uploaded || []).map(d => d.id).filter(Boolean);
                    allDemoIds.push(...demoIds);

                    // Add new IDs to tracking immediately so polling picks them up
                    trackingDemoIds.value = [...allDemoIds];

                    // Start polling after first successful batch (shows queue status during upload)
                    if (!pollingStarted) {
                        pollingStarted = true;
                        startStatusPolling();
                    }

                    // Accumulate summary from server
                    if (response.data.summary) {
                        totalReceived += response.data.summary.total_received || 0;
                        totalQueued += response.data.summary.queued || 0;
                        totalDuplicates += response.data.summary.duplicates || 0;
                        totalOtherErrors += response.data.summary.errors || 0;
                        totalReplaced += response.data.summary.replaced || 0;
                    }

                    // Update upload summary live after each batch
                    uploadSummary.value = {
                        total_selected: totalFiles,
                        total_sent: totalReceived,
                        queued: totalQueued,
                        replaced: totalReplaced,
                        duplicates: totalDuplicates,
                        errors: totalOtherErrors,
                        skipped_frontend: 0,
                        duration: ((Date.now() - uploadStartTime) / 1000).toFixed(1),
                        batch_progress: `${i + 1}/${totalBatches}`,
                    };
                    uploadSuccess.value = allUploaded;
                    uploadErrors.value = allErrors;
                } else {
                    console.error(`[Upload] Batch ${i + 1}/${totalBatches} returned success=false`, response.data);
                }
            } catch (batchError) {
                console.error(`[Upload] Batch ${i + 1}/${totalBatches} FAILED:`, batchError.message, batchError.code);
                allErrors.push(`Batch ${i + 1} failed: ${batchError.message}`);
                // Continue with next batch instead of aborting entire upload
            }
        }

        console.log(`[Upload] All batches done. Total queued=${totalQueued}, dupes=${totalDuplicates}, errors=${allErrors.length}`);
        uploadSuccess.value = allUploaded;
        uploadErrors.value = allErrors;

        // Build upload summary
        const skippedFrontend = totalFiles - totalReceived;
        const uploadDuration = ((Date.now() - uploadStartTime) / 1000).toFixed(1);
        uploadSummary.value = {
            total_selected: totalFiles,
            total_sent: totalReceived,
            queued: totalQueued,
            replaced: totalReplaced,
            duplicates: totalDuplicates,
            errors: totalOtherErrors,
            skipped_frontend: skippedFrontend > 0 ? skippedFrontend : 0,
            duration: uploadDuration,
        };

        // Clear selected files
        selectedFiles.value = [];
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        // Ensure final tracking IDs are set (polling already started during upload)
        trackingDemoIds.value = allDemoIds;
        if (!pollingStarted) {
            startStatusPolling();
        }

        // Immediately reload the demos list
        router.reload({ only: ['userDemos', 'publicDemos'] });

        // Results stay visible until user dismisses them
    } catch (error) {
        console.error('Upload error:', error);
        uploadErrors.value = [...allErrors, 'Upload failed: ' + (error.response?.data?.message || error.message)];
    } finally {
        uploading.value = false;
        setTimeout(() => {
            uploadProgress.value = 0;
        }, 800);
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const sortColumn = (column) => {
    const newOrder = props.sortBy === column && props.sortOrder === 'asc' ? 'desc' : 'asc';
    router.get(route('demos.index'), {
        sort: column,
        order: newOrder,
        userPage: props.userDemos?.current_page || 1,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatTime = (ms) => {
    if (!ms) return '-';
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const milliseconds = ms % 1000;
    return `${minutes}:${String(seconds).padStart(2, '0')}.${String(milliseconds).padStart(3, '0')}`;
};

// Watch for user search query changes and trigger server-side search
let searchTimeout = null;
watch(userSearchQuery, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const currentUrl = new URL(window.location.href);

        if (newValue.trim() === '') {
            currentUrl.searchParams.delete('search');
        } else {
            currentUrl.searchParams.set('search', newValue.trim());
        }

        // Reset to page 1 when searching
        currentUrl.searchParams.delete('userPage');

        router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
            preserveScroll: true,
            preserveState: true,
            only: ['userDemos', 'demoCounts'],
        });
    }, 300); // 300ms debounce
});

// Watch for confidence filter changes
watch(confidenceFilterValue, (newValue) => {
    const currentUrl = new URL(window.location.href);

    if (!newValue || newValue === '') {
        currentUrl.searchParams.delete('confidence');
    } else {
        currentUrl.searchParams.set('confidence', newValue);
    }

    // Reset to page 1 when filtering
    currentUrl.searchParams.delete('userPage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['userDemos', 'demoCounts'],
    });
});

// Watch for show other user matches toggle
watch(showOtherUserMatchesValue, (newValue) => {
    const currentUrl = new URL(window.location.href);

    if (newValue) {
        currentUrl.searchParams.set('other_user_matches', '1');
    } else {
        currentUrl.searchParams.delete('other_user_matches');
    }

    // Reset to page 1 when filtering
    currentUrl.searchParams.delete('userPage');

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only: ['userDemos', 'demoCounts'],
    });
});

// Watch for uploaded by filter changes
let uploadedByTimeout = null;
watch(uploadedByValue, (newValue) => {
    clearTimeout(uploadedByTimeout);
    uploadedByTimeout = setTimeout(() => {
        const currentUrl = new URL(window.location.href);

        if (newValue.trim() === '') {
            currentUrl.searchParams.delete('uploaded_by');
        } else {
            currentUrl.searchParams.set('uploaded_by', newValue.trim());
        }

        // Reset to page 1 when filtering
        currentUrl.searchParams.delete('userPage');

        router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
            preserveScroll: true,
            preserveState: true,
            only: ['userDemos', 'demoCounts'],
        });
    }, 300); // 300ms debounce
});

const getStatusColor = (status) => {
    switch (status) {
        case 'uploaded': return 'text-yellow-500';
        case 'processing': return 'text-blue-500';
        case 'processed': return 'text-green-500';
        case 'assigned': return 'text-purple-500';
        case 'failed-validity': return 'text-orange-500';
        case 'failed': return 'text-red-500';
        case 'unsupported-version': return 'text-purple-500';
        default: return 'text-gray-500';
    }
};

const reprocessDemo = async (demoId) => {
    if (!confirm('Are you sure you want to reprocess this demo?')) {
        return;
    }

    try {
        const response = await axios.post(route('demos.reprocess', demoId));
        if (response.data.success) {
            recentlyProcessed.value = [];
            actionStartedAt.value = new Date();
            startStatusPolling();
            router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
        }
    } catch (error) {
        alert('Failed to reprocess demo: ' + (error.response?.data?.message || error.message));
    }
};

const deleteDemo = async (demoId) => {
    if (!confirm('Are you sure you want to delete this demo?')) {
        return;
    }

    try {
        await axios.delete(route('demos.destroy', demoId));
        router.reload({ only: ['userDemos', 'publicDemos'] });
    } catch (error) {
        alert('Failed to delete demo: ' + (error.response?.data?.message || error.message));
    }
};

// Group demos by status
const groupedDemos = computed(() => {
    const groups = {
        assigned: [],
        processed: [],
        'failed-validity': [],
        failed: [],
        processing: [],
        pending: [],
        uploaded: []
    };

    if (!props.userDemos || !props.userDemos.data) return groups;

    props.userDemos.data.forEach(demo => {
        if (groups[demo.status]) {
            groups[demo.status].push(demo);
        } else {
            groups.failed.push(demo); // Default to failed if unknown status
        }
    });

    // Sort each group by created_at desc (newest first)
    Object.keys(groups).forEach(status => {
        groups[status].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    });

    return groups;
});

// Status polling functions
let pollInFlight = false;
const pollOnce = async () => {
    if (pollInFlight) return; // prevent stacking requests
    pollInFlight = true;
    try {
        let response;
        if (trackingDemoIds.value.length > 0) {
            // Use POST to avoid URL length limits with large tracking arrays
            response = await axios.post(route('demos.status'), { tracking_ids: trackingDemoIds.value });
        } else {
            response = await axios.get(route('demos.status'));
        }
        processingDemos.value = response.data.processing_demos;
        queueStats.value = response.data.queue_stats;

        // Backend returns recently completed demos (last 5 min + tracked IDs)
        const completed = response.data.completed_demos || [];
        if (completed.length > 0) {
            let newCompleted;
            if (trackingDemoIds.value.length > 0) {
                const trackedSet = new Set(trackingDemoIds.value);
                newCompleted = completed.filter(d => trackedSet.has(d.id));
            } else if (actionStartedAt.value) {
                newCompleted = completed.filter(d => new Date(d.updated_at) >= actionStartedAt.value);
            } else {
                newCompleted = completed;
            }

            // Accumulate results (merge new into existing) to prevent race conditions
            // with fast polling where out-of-order responses could overwrite results
            const existingMap = new Map(recentlyProcessed.value.map(d => [d.id, d]));
            newCompleted.forEach(d => existingMap.set(d.id, d)); // update or add
            recentlyProcessed.value = Array.from(existingMap.values());
        }

        // Stop polling when nothing is processing/queued globally (but never during active upload)
        const timeSinceAction = actionStartedAt.value ? (Date.now() - actionStartedAt.value.getTime()) : Infinity;
        const globalRemaining = (response.data.queue_stats?.total_queued || 0) + (response.data.queue_stats?.total_processing || 0);
        if (!uploading.value && (response.data.processing_demos || []).length === 0 && globalRemaining === 0 && timeSinceAction > 5000) {
            if (processingStartTime.value) {
                processingDuration.value = ((Date.now() - processingStartTime.value) / 1000).toFixed(1);
                processingStartTime.value = null;
            }
            trackingDemoIds.value = [];
            stopStatusPolling();
            router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
        }
    } catch (error) {
        console.error('Status polling error:', error);
    } finally {
        pollInFlight = false;
    }
};

const startStatusPolling = () => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
    }

    // Immediate first poll
    pollOnce();

    statusPolling.value = setInterval(() => pollOnce(), 2000);
};

const stopStatusPolling = () => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
        statusPolling.value = null;
    }
};

// Start polling on component mount if there are processing demos or saved tracking
const checkForProcessingDemos = async () => {
    if ($page.props.auth.user) {
        try {
            const response = await axios.get(route('demos.status'));
            processingDemos.value = response.data.processing_demos;
            queueStats.value = response.data.queue_stats;

            const hasWork = response.data.processing_demos.length > 0
                || (response.data.queue_stats.total_queued || 0) > 0
                || (response.data.queue_stats.total_processing || 0) > 0;
            if (hasWork) {
                startStatusPolling();
            }
        } catch (error) {
            console.error('Initial status check error:', error);
        }
    }
};

// Lifecycle hooks
// Lifecycle hooks are imported at the top of this <script setup>
onMounted(() => {
    checkForProcessingDemos();
});

onUnmounted(() => {
    stopStatusPolling();
});

// Manual assignment functions
const openAssignModal = (demo) => {
    assigningDemo.value = demo;
    showAssignModal.value = true;

    // Pre-fill physics from demo metadata
    if (demo.physics) {
        selectedPhysics.value = demo.physics;
    }

    // Pre-fill map from demo metadata and auto-select + load records
    if (demo.map_name) {
        searchQuery.value = demo.map_name;
        selectedMap.value = demo.map_name;
        loadRecords();
    } else {
        searchMaps();
    }
};

// Suggested matches — records sorted by time distance to demo's time
const suggestedRecords = computed(() => {
    if (!assigningDemo.value || availableRecords.value.length === 0) return [];
    const demoTime = assigningDemo.value.time_ms;
    if (!demoTime) return [];
    return [...availableRecords.value]
        .map(r => ({ ...r, timeDiff: Math.abs(r.time - demoTime) }))
        .sort((a, b) => a.timeDiff - b.timeDiff)
        .slice(0, 5);
});

const closeAssignModal = () => {
    showAssignModal.value = false;
    assigningDemo.value = null;
    searchQuery.value = '';
    availableMaps.value = [];
    selectedMap.value = '';
    availableRecords.value = [];
    selectedRecord.value = '';
};

const searchMaps = async () => {
    loadingMaps.value = true;
    try {
        const response = await axios.get(route('demos.maps'), {
            params: { search: searchQuery.value }
        });
        availableMaps.value = response.data;
    } catch (error) {
        console.error('Error searching maps:', error);
    } finally {
        loadingMaps.value = false;
    }
};

const selectMap = async (mapname) => {
    selectedMap.value = mapname;
    loadRecords();
};

const loadRecords = async () => {
    if (!selectedMap.value) return;

    loadingRecords.value = true;
    try {
        const response = await axios.get(route('demos.records', selectedMap.value), {
            params: { physics: selectedPhysics.value }
        });
        availableRecords.value = response.data;
    } catch (error) {
        console.error('Error loading records:', error);
    } finally {
        loadingRecords.value = false;
    }
};

const assignDemo = async () => {
    if (!selectedRecord.value) return;

    try {
        const response = await axios.post(route('demos.assign', assigningDemo.value.id), {
            record_id: selectedRecord.value
        });

        if (response.data.success) {
            closeAssignModal();
            router.reload({ only: ['userDemos', 'publicDemos'] });
        }
    } catch (error) {
        console.error('Error assigning demo:', error);
        alert('Failed to assign demo. Please try again.');
    }
};

const unassignDemo = async (demo) => {
    if (!confirm('Are you sure you want to remove the assignment from this demo?')) {
        return;
    }

    try {
        const response = await axios.post(route('demos.unassign', demo.id));

        if (response.data.success) {
            router.reload({ only: ['userDemos', 'publicDemos'] });
        }
    } catch (error) {
        console.error('Error unassigning demo:', error);
        alert('Failed to unassign demo. Please try again.');
    }
};

// Watch for search query changes
watch(searchQuery, () => {
    if (searchQuery.value.length >= 2) {
        searchMaps();
    }
});

// Watch for physics changes
watch(selectedPhysics, () => {
    if (selectedMap.value) {
        loadRecords();
    }
});
</script>

<template>
    <div>
        <Head title="Demo Upload" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Demos</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            <span class="text-sm font-semibold">Upload and manage demo files</span>
                        </div>
                    </div>

                    <!-- Download Limit Info (Compact, Right Side) -->
                    <div v-if="downloadLimitInfo" class="backdrop-blur-xl rounded-lg px-4 py-2 shadow-xl border" :class="downloadLimitInfo.isGuest ? 'bg-blue-900/20 border-blue-500/30' : downloadLimitInfo.remaining === 0 ? 'bg-red-900/20 border-red-500/30' : 'bg-gray-900/40 border-white/5'">
                        <div class="flex items-center gap-2">
                            <svg v-if="downloadLimitInfo.isGuest" class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <svg v-else-if="downloadLimitInfo.remaining === 0" class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div v-if="downloadLimitInfo.isGuest" class="text-xs">
                                <span class="text-blue-200 font-semibold">{{ downloadLimitInfo.remaining }}/{{ downloadLimitInfo.limit }}</span>
                                <span class="text-blue-300/80 ml-1">downloads left</span>
                            </div>
                            <div v-else-if="downloadLimitInfo.remaining === 0" class="text-xs">
                                <span class="text-red-200 font-semibold">Limit reached</span>
                            </div>
                            <div v-else class="text-xs">
                                <span class="text-green-400 font-semibold">{{ downloadLimitInfo.remaining }}</span>
                                <span class="text-gray-300 mx-1">/</span>
                                <span class="text-gray-300">{{ downloadLimitInfo.limit }}</span>
                                <span class="text-gray-400 ml-1">downloads left</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-hidden" style="margin-top: -22rem;">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Upload Section (visible to all users; guests will have restricted actions) -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 mb-6 shadow-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-gray-100 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload New Demos
                    </h3>

                    <!-- Not logged in: full clickable login overlay -->
                    <div v-if="!$page.props.auth.user" class="relative">
                        <div class="border-2 border-dashed border-gray-600 rounded-xl p-4 text-center blur-[2px] opacity-40 pointer-events-none">
                            <div class="space-y-2">
                                <div class="flex justify-center">
                                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                </div>
                                <p class="text-base font-semibold text-gray-200">Drag demo files or folders here</p>
                                <p class="text-gray-400 mt-1 text-sm">Or use buttons below to select files or folders</p>
                                <div class="flex gap-2 justify-center">
                                    <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg">Select Files</span>
                                    <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-lg">Select Folder</span>
                                </div>
                            </div>
                        </div>
                        <Link
                            :href="route('login')"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 rounded-xl border-2 border-dashed border-white/10 hover:border-blue-500/50 transition-all duration-300 cursor-pointer group"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-400 group-hover:text-blue-400 transition-colors mb-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            <span class="text-gray-300 group-hover:text-blue-300 font-semibold text-lg transition-colors">Log in to upload demos</span>
                            <span class="text-gray-500 text-sm mt-1">Click here to sign in</span>
                        </Link>
                    </div>

                    <!-- Logged in but can't upload (restricted / not enough records) -->
                    <div v-else-if="!canUpload" class="bg-red-500/10 border border-red-500/50 rounded-lg p-4 mb-4">
                        <p class="text-red-400">
                            {{ uploadRestrictionMessage }}
                        </p>
                    </div>

                    <!-- Drag and Drop Zone (authenticated users with upload permission) -->
                    <div
                        v-else
                        @dragenter="handleDragEnter"
                        @dragleave="handleDragLeave"
                        @dragover="handleDragOver"
                        @drop="handleDrop"
                        :class="[
                            'relative border-2 border-dashed rounded-xl p-4 text-center transition-all duration-300 ease-in-out',
                            isDragOver
                                ? 'border-blue-400 bg-blue-900/20 scale-[1.02]'
                                : 'border-gray-600 hover:border-gray-500 hover:bg-gray-700/30'
                        ]"
                    >
                        <!-- strong overlay shown while dragging files -->
                        <div v-if="isDragOver" class="absolute inset-0 bg-blue-900/60 rounded-xl flex items-center justify-center z-10 pointer-events-none">
                            <div class="text-center text-white">
                                <svg class="w-16 h-16 mx-auto mb-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <div class="text-2xl font-semibold">Drop demo files to upload</div>
                                <div class="text-sm text-blue-100 mt-1">Release to upload your selected demos</div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-center">
                                <svg :class="[
                                    'w-10 h-10 transition-all duration-300',
                                    isDragOver ? 'text-blue-400 scale-110' : 'text-gray-400'
                                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>

                            <div>
                                <p :class="[
                                    'text-base font-semibold transition-colors duration-300',
                                    isDragOver ? 'text-blue-300' : 'text-gray-200'
                                ]">
                                    {{ isDragOver ? 'Drop demo files or folders here' : 'Drag demo files or folders here' }}
                                </p>
                                <p class="text-gray-400 mt-1 text-sm">
                                    Or use buttons below to select files or folders
                                </p>
                            </div>

                            <!-- Hidden file inputs -->
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                accept=".dm_68,.dm_66,.dm_67,.dm_73,.zip,.rar,.7z"
                                @change="handleFileSelect"
                                class="hidden"
                            />
                            <input
                                ref="folderInput"
                                type="file"
                                webkitdirectory
                                directory
                                @change="handleFileSelect"
                                class="hidden"
                            />

                            <!-- Browse buttons -->
                            <div class="flex gap-2 justify-center">
                                <button
                                    type="button"
                                    class="relative inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                                    @click="$refs.fileInput.click()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Select Files
                                </button>
                                <button
                                    type="button"
                                    class="relative inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-lg shadow-lg hover:from-green-700 hover:to-green-800 transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                                    @click="$refs.folderInput.click()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                    </svg>
                                    Select Folder
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Files List -->
                    <div v-if="selectedFiles.length > 0" class="mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-200 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ selectedFiles.length }} file(s) selected
                            </h4>
                            <button
                                @click="clearAllFiles"
                                class="text-sm text-red-400 hover:text-red-300 transition-colors duration-200"
                            >
                                Clear all
                            </button>
                        </div>

                        <div class="grid gap-2 max-h-60 overflow-y-auto">
                            <div
                                v-for="(file, index) in selectedFiles"
                                :key="file.name + index"
                                class="flex items-center justify-between bg-gray-700/50 rounded-lg px-3 py-2 border border-gray-600/50 hover:bg-gray-700 transition-colors duration-200"
                            >
                                <div class="flex items-center space-x-2 min-w-0 flex-1">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-200 truncate">{{ file.name }}</p>
                                    <p class="text-xs text-gray-400 flex-shrink-0">{{ formatFileSize(file.size) }}</p>
                                </div>
                                <button
                                    @click="removeFile(index)"
                                    class="flex-shrink-0 p-1 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition-colors duration-200 ml-2"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Button -->
                    <div v-if="selectedFiles.length > 0" class="mt-6 flex justify-center">
                        <button
                            @click="uploadDemos"
                            :disabled="uploading || selectedFiles.length === 0"
                            class="group relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold text-lg rounded-xl shadow-xl hover:from-green-700 hover:to-green-800 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                        >
                            <svg v-if="!uploading" class="w-6 h-6 mr-3 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <div v-else class="w-6 h-6 mr-3 animate-spin border-2 border-white border-t-transparent rounded-full"></div>
                            <span v-if="uploading">Uploading {{ selectedFiles.length }} demo(s)...</span>
                            <span v-else>Upload {{ selectedFiles.length }} demo(s)</span>
                        </button>
                    </div>

                    <!-- Global upload progress -->
                    <div v-if="uploading || uploadProgress > 0" class="mt-4">
                        <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden border border-gray-600/50">
                            <div :style="{ width: uploadProgress + '%' }" class="h-3 bg-green-500 transition-all duration-200"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">Progress: {{ uploadProgress }}%</div>
                    </div>

                    <!-- Upload Summary -->
                    <div v-if="uploadSummary" class="mt-6 p-4 bg-gradient-to-r from-blue-900/30 to-blue-800/20 rounded-xl border border-blue-700/50">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-blue-300 font-semibold">Upload Summary</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-xs">updates per batch</span>
                                <button v-if="!uploading" @click="uploadSummary = null; uploadErrors = []; uploadSuccess = []" class="text-gray-400 hover:text-white transition-colors" title="Dismiss all results">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                            <div class="bg-gray-800/50 rounded-lg px-3 py-2 text-center">
                                <div class="text-gray-400 text-xs">Selected</div>
                                <div class="text-white font-bold text-lg">{{ uploadSummary.total_selected.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.skipped_frontend > 0" class="bg-yellow-900/30 rounded-lg px-3 py-2 text-center border border-yellow-700/30">
                                <div class="text-yellow-400 text-xs">Skipped (non-demo)</div>
                                <div class="text-yellow-300 font-bold text-lg">{{ uploadSummary.skipped_frontend.toLocaleString() }}</div>
                            </div>
                            <div class="bg-green-900/30 rounded-lg px-3 py-2 text-center border border-green-700/30">
                                <div class="text-green-400 text-xs">Queued</div>
                                <div class="text-green-300 font-bold text-lg">{{ uploadSummary.queued.toLocaleString() }}</div>
                                <div v-if="uploadSummary.replaced > 0" class="text-[10px] text-cyan-400 mt-0.5">({{ uploadSummary.replaced }} replaced failed)</div>
                            </div>
                            <div v-if="uploadSummary.duplicates > 0" class="bg-orange-900/30 rounded-lg px-3 py-2 text-center border border-orange-700/30">
                                <div class="text-orange-400 text-xs">Duplicates</div>
                                <div class="text-orange-300 font-bold text-lg">{{ uploadSummary.duplicates.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.errors > 0" class="bg-red-900/30 rounded-lg px-3 py-2 text-center border border-red-700/30">
                                <div class="text-red-400 text-xs">Errors</div>
                                <div class="text-red-300 font-bold text-lg">{{ uploadSummary.errors.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.batch_progress && uploading" class="bg-blue-900/30 rounded-lg px-3 py-2 text-center border border-blue-700/30">
                                <div class="text-blue-400 text-xs">Batch</div>
                                <div class="text-blue-300 font-bold text-lg">{{ uploadSummary.batch_progress }}</div>
                            </div>
                            <div class="bg-gray-800/50 rounded-lg px-3 py-2 text-center">
                                <div class="text-gray-400 text-xs">Duration</div>
                                <div class="text-white font-bold text-lg">{{ uploadSummary.duration }}s</div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Results -->
                    <div v-if="uploadErrors.length > 0" class="mt-6 p-4 bg-gradient-to-r from-red-900/30 to-red-800/20 rounded-xl border border-red-700/50">
                        <button @click="errorsExpanded = !errorsExpanded" class="w-full flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-red-300 font-semibold">Upload Errors</span>
                                <span class="ml-2 px-2 py-0.5 bg-red-500/30 text-red-300 text-xs rounded-full">{{ uploadErrors.length }}</span>
                            </div>
                            <svg class="w-4 h-4 text-red-400 transition-transform" :class="{ 'rotate-180': errorsExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="errorsExpanded" class="mt-3 space-y-2 max-h-60 overflow-y-auto pr-1">
                            <details v-for="(errors, category) in categorizedErrors" :key="category" class="group">
                                <summary class="cursor-pointer flex items-center gap-2 text-sm text-red-300/80 hover:text-red-200 py-1">
                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    {{ category }}
                                    <span class="px-1.5 py-0.5 bg-red-500/20 text-red-400 text-[10px] rounded-full">{{ errors.length }}</span>
                                </summary>
                                <div class="ml-5 mt-1 space-y-1">
                                    <div v-for="error in errors" :key="error" class="flex items-start space-x-1.5">
                                        <svg class="w-3 h-3 text-red-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <span class="text-red-200/70 text-xs">{{ error }}</span>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>

                    <div v-if="uploadSuccess.length > 0" class="mt-4 p-4 bg-gradient-to-r from-green-900/30 to-green-800/20 rounded-xl border border-green-700/50">
                        <button @click="successExpanded = !successExpanded" class="w-full flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-green-300 font-semibold">Successfully Uploaded</span>
                                <span class="ml-2 px-2 py-0.5 bg-green-500/30 text-green-300 text-xs rounded-full">{{ uploadSuccess.length }}</span>
                            </div>
                            <svg class="w-4 h-4 text-green-400 transition-transform" :class="{ 'rotate-180': successExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="successExpanded" class="mt-3 space-y-1 max-h-60 overflow-y-auto pr-1">
                            <div v-for="demo in uploadSuccess" :key="demo.id" class="flex items-start space-x-1.5">
                                <svg class="w-3 h-3 text-green-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <span class="text-green-200/80 text-xs">{{ demo.processed_filename || demo.original_filename }}</span>
                                    <span v-if="demo.record_id" class="text-purple-300 text-[10px] ml-1">auto-assigned</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Processing Results (shows after demos finish processing) -->
                <div v-if="processingSummary" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 mb-4 shadow-2xl border border-cyan-500/30">
                    <!-- Summary header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <h3 class="text-base font-semibold text-cyan-300">Processing Results</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 text-xs">updates every 2s</span>
                            <button @click="recentlyProcessed = []; processingDuration = null;" class="text-gray-500 hover:text-gray-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                    <!-- Summary stats grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                        <div class="bg-gray-800/50 rounded-lg px-3 py-2 text-center">
                            <div class="text-gray-400 text-xs">Total</div>
                            <div class="text-white font-bold text-lg">{{ processingSummary.total.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingSummary.success > 0" class="bg-green-900/30 rounded-lg px-3 py-2 text-center border border-green-700/30">
                            <div class="text-green-400 text-xs">Success</div>
                            <div class="text-green-300 font-bold text-lg">{{ processingSummary.success.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingSummary.fail > 0" class="bg-red-900/30 rounded-lg px-3 py-2 text-center border border-red-700/30">
                            <div class="text-red-400 text-xs">Failed</div>
                            <div class="text-red-300 font-bold text-lg">{{ processingSummary.fail.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingDuration" class="bg-gray-800/50 rounded-lg px-3 py-2 text-center">
                            <div class="text-gray-400 text-xs">Duration</div>
                            <div class="text-white font-bold text-lg">{{ processingDuration }}s</div>
                        </div>
                        <div v-else-if="processingStartTime" class="bg-gray-800/50 rounded-lg px-3 py-2 text-center">
                            <div class="text-gray-400 text-xs">Duration</div>
                            <div class="text-yellow-300 font-bold text-lg animate-pulse">running...</div>
                        </div>
                    </div>

                    <!-- Grouped details (collapsible) -->
                    <div class="mt-4">
                        <button @click="processingResultsExpanded = !processingResultsExpanded" class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-cyan-900/20 to-cyan-800/10 rounded-xl border border-cyan-700/30">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-cyan-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                <span class="text-cyan-300 font-semibold text-sm">Details</span>
                                <span class="ml-2 px-2 py-0.5 bg-cyan-500/20 text-cyan-300 text-xs rounded-full">{{ processingSummary.total }}</span>
                            </div>
                            <svg class="w-4 h-4 text-cyan-400 transition-transform" :class="{ 'rotate-180': processingResultsExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="processingResultsExpanded" class="mt-3 space-y-2 max-h-80 overflow-y-auto pr-1">
                            <details v-for="(group, status) in processingSummary.groups" :key="status" class="group">
                                <summary class="cursor-pointer flex items-center gap-2 text-sm py-1.5 px-2 rounded-lg hover:bg-white/5"
                                    :class="{
                                        'text-green-300': ['assigned', 'fallback-assigned', 'processed'].includes(status),
                                        'text-red-300': status === 'failed',
                                        'text-orange-300': status === 'failed-validity',
                                        'text-purple-300': status === 'unsupported-version',
                                    }">
                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    {{ group.label }}
                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full"
                                        :class="{
                                            'bg-green-500/20 text-green-400': ['assigned', 'fallback-assigned', 'processed'].includes(status),
                                            'bg-red-500/20 text-red-400': status === 'failed',
                                            'bg-orange-500/20 text-orange-400': status === 'failed-validity',
                                            'bg-purple-500/20 text-purple-400': status === 'unsupported-version',
                                        }">{{ group.demos.length }}</span>
                                </summary>
                                <div class="ml-5 mt-1 space-y-1">
                                    <div v-for="demo in group.demos" :key="demo.id" class="flex items-start space-x-1.5">
                                        <svg v-if="['assigned', 'fallback-assigned', 'processed'].includes(demo.status)" class="w-3 h-3 text-green-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3 text-red-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs text-gray-300">{{ demo.processed_filename || demo.original_filename }}</span>
                                            <span v-if="demo.map_name" class="text-[10px] text-gray-500 ml-1">{{ demo.map_name }}</span>
                                            <span v-if="demo.processing_output" class="text-[10px] text-gray-500 ml-1">— {{ demo.processing_output }}</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- Global Processing Status (always visible) -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 mb-4 shadow-2xl border border-white/5">
                    <button @click="globalQueueExpanded = !globalQueueExpanded" class="w-full flex items-center justify-between mb-3">
                        <h3 class="text-base font-semibold text-gray-200">Global Queue Status</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 text-xs">updates every 2s</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': globalQueueExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>

                    <!-- Queue Statistics -->
                    <div v-show="globalQueueExpanded" class="grid grid-cols-2 gap-2">
                        <div v-if="$page.props.auth.user" class="relative group bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-lg p-3 text-center border border-yellow-600/30 cursor-help">
                            <div class="text-2xl font-bold text-yellow-400">{{ (queueStats.user_queued || 0) + (queueStats.user_processing || 0) }}</div>
                            <div class="text-xs text-yellow-300 mt-1">Your Remaining</div>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 border border-gray-600 rounded-lg text-xs text-gray-200 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                Your demos not yet finished: {{ queueStats.user_queued || 0 }} waiting in queue, {{ queueStats.user_processing || 0 }} being processed right now
                            </div>
                        </div>
                        <div class="relative group bg-gradient-to-br from-green-600/20 to-green-700/10 rounded-lg p-3 text-center border border-green-600/30 cursor-help">
                            <div class="text-2xl font-bold text-green-400">{{ (queueStats.total_queued || 0) + (queueStats.total_processing || 0) }}</div>
                            <div class="text-xs text-green-300 mt-1">Total Remaining</div>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 border border-gray-600 rounded-lg text-xs text-gray-200 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                Demos from all users not yet finished: {{ queueStats.total_queued || 0 }} waiting in queue, {{ queueStats.total_processing || 0 }} being processed right now
                            </div>
                        </div>
                    </div>

                    <!-- Actively Processing (only demos currently being worked on by workers) -->
                    <div v-if="activelyProcessingDemos.length > 0" v-show="globalQueueExpanded" class="mt-4 space-y-2">
                        <h4 class="text-sm font-semibold text-blue-300">Actively Processing ({{ activelyProcessingDemos.length }}):</h4>
                        <div v-for="demo in activelyProcessingDemos" :key="demo.id" class="flex items-center justify-between bg-blue-900/20 rounded-lg p-2 border border-blue-600/30">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-400 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <div>
                                    <div class="text-gray-200 font-medium text-sm truncate max-w-[700px]">{{ demo.original_filename }}</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="flex space-x-1">
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></div>
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queued count (just a summary, not the full list) -->
                    <div v-if="queuedDemoCount > 0" v-show="globalQueueExpanded" class="mt-3">
                        <div class="text-xs text-gray-400">{{ queuedDemoCount }} demo(s) waiting in queue</div>
                    </div>
                </div>

                <!-- Your Uploads Section (authenticated users only) -->
                <div v-if="$page.props.auth.user && userDemos" class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5 mb-8">
                    <!-- Show message when no demos uploaded at all -->
                    <div v-if="demoCountsComputed.all === 0" class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-400 text-lg">You haven't uploaded any demos yet.</p>
                    </div>

                    <!-- Show title, filters and table when demos exist -->
                    <template v-else>
                        <h3 class="text-xl font-semibold text-gray-200 mb-4">
                            Your Uploaded Demos
                        </h3>

                        <!-- Filter Tabs -->
                        <div class="mb-4 space-y-2">
                            <!-- Row 1: Category + Status Filters -->
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    @click="changeTabFilter('all')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeTab === 'all'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                                >
                                    All <span class="opacity-75">({{ demoCountsComputed.all }})</span>
                                </button>
                                <button
                                    @click="changeTabFilter('online')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeTab === 'online'
                                        ? 'bg-green-600 text-white'
                                        : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                                >
                                    Online <span class="opacity-75">({{ demoCountsComputed.online }})</span>
                                </button>
                                <button
                                    @click="changeTabFilter('offline')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeTab === 'offline'
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                                >
                                    Offline <span class="opacity-75">({{ demoCountsComputed.offline }})</span>
                                </button>

                                <span class="border-l border-gray-600/50 mx-1"></span>

                                <button
                                    @click="changeStatusFilter('all')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'all'
                                        ? 'bg-gray-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    All Status
                                </button>
                                <button
                                    @click="changeStatusFilter('assigned')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'assigned'
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Assigned <span class="opacity-75">({{ demoCountsComputed.assigned }})</span>
                                </button>
                                <button
                                    @click="changeStatusFilter('fallback-assigned')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'fallback-assigned'
                                        ? 'bg-orange-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Fallback <span class="opacity-75">({{ demoCountsComputed.fallback_assigned || 0 }})</span>
                                </button>
                                <button
                                    v-if="(demoCountsComputed.uploaded || 0) > 0"
                                    @click="changeStatusFilter('uploaded')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'uploaded'
                                        ? 'bg-cyan-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Uploaded <span class="opacity-75">({{ demoCountsComputed.uploaded || 0 }})</span>
                                </button>
                                <button
                                    @click="changeStatusFilter('processed')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'processed'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Processed <span class="opacity-75">({{ demoCountsComputed.processed }})</span>
                                </button>
                                <button
                                    @click="changeStatusFilter('failed-validity')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'failed-validity'
                                        ? 'bg-orange-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Invalid <span class="opacity-75">({{ demoCountsComputed.failed_validity || 0 }})</span>
                                </button>
                                <button
                                    @click="changeStatusFilter('failed')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'failed'
                                        ? 'bg-red-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Failed <span class="opacity-75">({{ demoCountsComputed.failed }})</span>
                                </button>
                                <button
                                    @click="changeStatusFilter('unsupported-version')"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                    :class="activeStatusFilter === 'unsupported-version'
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                                >
                                    Unsupported <span class="opacity-75">({{ demoCountsComputed.unsupported_version || 0 }})</span>
                                </button>
                                <button
                                    v-if="$page.props.auth.user?.admin && (demoCountsComputed.failed || 0) > 0"
                                    @click="showReprocessConfirm = true"
                                    :disabled="reprocessingFailed"
                                    class="px-2.5 py-1 rounded text-xs font-medium transition-all bg-orange-700/30 text-orange-300 hover:bg-orange-700/50 border border-orange-600/30 disabled:opacity-50"
                                >
                                    {{ reprocessingFailed ? 'Reprocessing...' : 'Reprocess All Failed' }}
                                </button>
                                <span v-if="reprocessMessage" class="text-xs text-yellow-300 ml-2">{{ reprocessMessage }}</span>
                            </div>

                            <!-- Row 2: Search Input -->
                            <div class="relative">
                                <input
                                    v-model="userSearchQuery"
                                    type="text"
                                    placeholder="Search by filename..."
                                    class="w-full px-4 py-1.5 bg-gray-700/50 border border-gray-600/50 rounded-lg text-sm text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                <div class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Advanced Filters (Admin Only) -->
                            <div v-if="$page.props.auth.user && ($page.props.auth.user.is_admin || $page.props.auth.user.admin)" class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-gray-600/50">
                                <!-- Confidence Filter -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Confidence Range</label>
                                    <select
                                        v-model="confidenceFilterValue"
                                        class="w-full px-3 py-1.5 bg-gray-700/50 border border-gray-600/50 rounded-lg text-sm text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option value="">All Confidence Levels</option>
                                        <option value="90-99">90-99%</option>
                                        <option value="80-89">80-89%</option>
                                        <option value="70-79">70-79%</option>
                                        <option value="60-69">60-69%</option>
                                        <option value="50-59">50-59%</option>
                                        <option value="below-50">Below 50%</option>
                                    </select>
                                </div>

                                <!-- 100% Match Other User Filter -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Match Type</label>
                                    <button
                                        @click="showOtherUserMatchesValue = !showOtherUserMatchesValue"
                                        :class="[
                                            'w-full px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200',
                                            showOtherUserMatchesValue
                                                ? 'bg-purple-600/30 text-purple-300 border-2 border-purple-500/50 hover:bg-purple-600/40'
                                                : 'bg-gray-700/50 text-gray-300 border border-gray-600/50 hover:bg-gray-700'
                                        ]"
                                    >
                                        <div class="flex items-center justify-center gap-2">
                                            <svg v-if="showOtherUserMatchesValue" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span>100% Match (Other User)</span>
                                        </div>
                                    </button>
                                </div>

                                <!-- Uploaded By Filter -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Uploaded By</label>
                                    <input
                                        v-model="uploadedByValue"
                                        type="text"
                                        placeholder="Username..."
                                        class="w-full px-3 py-1.5 bg-gray-700/50 border border-gray-600/50 rounded-lg text-sm text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredDemos.length === 0" class="text-gray-400 text-center py-8">
                            No demos match the selected filters.
                        </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('id')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>ID</span>
                                            <svg v-if="sortBy === 'id'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('original_filename')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>Filename</span>
                                            <svg v-if="sortBy === 'original_filename'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('created_at')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>Uploaded</span>
                                            <svg v-if="sortBy === 'created_at'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('map_name')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>Map</span>
                                            <svg v-if="sortBy === 'map_name'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-2 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Physics
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('time_ms')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>Time</span>
                                            <svg v-if="sortBy === 'time_ms'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('status')" class="flex items-center gap-2 hover:text-blue-400 transition-colors">
                                            <span>Status</span>
                                            <svg v-if="sortBy === 'status'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                <tr v-for="demo in filteredDemos" :key="demo.id" class="hover:bg-gray-700/30 transition-colors duration-200">
                                    <td class="px-4 py-4 text-sm text-gray-400 font-mono">
                                        {{ demo.id }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <div>
                                                <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>

                                                <!-- Confidence Badge -->
                                                <div v-if="demo.name_confidence !== null" class="mt-1 flex items-center gap-2">
                                                    <span
                                                        :class="[
                                                            'text-xs px-2 py-0.5 rounded',
                                                            demo.name_confidence === 100 ? 'bg-green-500/20 text-green-400' :
                                                            demo.name_confidence >= 90 ? 'bg-blue-500/20 text-blue-400' :
                                                            demo.name_confidence >= 70 ? 'bg-yellow-500/20 text-yellow-400' :
                                                            'bg-red-500/20 text-red-400'
                                                        ]"
                                                    >
                                                        {{ demo.name_confidence }}% confidence
                                                    </span>

                                                    <span v-if="demo.suggested_user" class="text-xs text-gray-400">
                                                        → <span v-html="q3tohtml(demo.suggested_user.name)"></span>
                                                    </span>

                                                    <span v-if="demo.manually_assigned" class="text-xs bg-purple-500/20 text-purple-400 px-2 py-0.5 rounded">
                                                        Manually Assigned
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400">
                                        <div class="flex flex-col">
                                            <span class="text-gray-300">{{ new Date(demo.created_at).toLocaleDateString() }}</span>
                                            <span class="text-xs text-gray-500">{{ new Date(demo.created_at).toLocaleTimeString() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-gray-300">
                                        <Link v-if="demo.map_name" :href="`/maps/${encodeURIComponent(demo.map_name)}`" class="text-blue-400 hover:text-blue-300 underline transition-colors duration-200 truncate block max-w-[120px]" :title="demo.map_name">
                                            {{ demo.map_name }}
                                        </Link>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-2 py-4 text-xs text-gray-300">
                                        <span v-if="demo.gametype" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase" :class="demo.gametype.startsWith('m') ? 'bg-green-900/50 text-green-200' : 'bg-purple-900/50 text-purple-200'" :title="demo.gametype.startsWith('m') ? 'Online' : 'Offline'">
                                            {{ demo.gametype }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-2 py-4 text-xs text-gray-300">
                                        <span v-if="demo.physics" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium" :class="demo.physics === 'VQ3' ? 'bg-blue-900/50 text-blue-200' : 'bg-purple-900/50 text-purple-200'">
                                            {{ demo.physics }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-300 font-mono">
                                        {{ formatTime(demo.time_ms) }}
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <div class="flex flex-col space-y-2">
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium relative"
                                                    :class="{
                                                        'bg-yellow-900/50 text-yellow-200': demo.status === 'uploaded',
                                                        'bg-blue-900/50 text-blue-200': demo.status === 'processing',
                                                        'bg-green-900/50 text-green-200': demo.status === 'processed',
                                                        'bg-purple-900/50 text-purple-200 hover:bg-purple-800/50 cursor-help': demo.status === 'assigned',
                                                        'bg-orange-900/50 text-orange-200 hover:bg-orange-800/50 cursor-help': demo.status === 'fallback-assigned' || demo.status === 'failed-validity',
                                                        'bg-red-900/50 text-red-200 hover:bg-red-800/50 cursor-help': demo.status === 'failed',
                                                        'bg-purple-900/50 text-purple-200 hover:bg-purple-800/50 cursor-help': demo.status === 'unsupported-version',
                                                        'bg-gray-900/50 text-gray-200': !['uploaded', 'processing', 'processed', 'assigned', 'fallback-assigned', 'failed-validity', 'failed', 'unsupported-version'].includes(demo.status)
                                                    }"
                                                    @mouseenter="(demo.status === 'failed' && demo.processing_output) || (demo.status === 'failed-validity' && demo.validity) || (demo.status === 'unsupported-version' && demo.processing_output) || (demo.status === 'assigned' && (demo.record || demo.offline_record)) || (demo.status === 'fallback-assigned' && demo.offline_record) ? showTooltip(demo, $event) : null"
                                                    @mouseleave="hideTooltip"
                                                    @mousemove="hoveredDemo?.id === demo.id ? updateTooltipPosition($event) : null"
                                                >
                                                    {{ demo.status }}
                                                    <svg v-if="demo.status === 'unsupported-version' && demo.processing_output" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'failed' && demo.processing_output" class="w-3 h-3 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'assigned' && (demo.record || demo.offline_record)" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'fallback-assigned' && demo.offline_record" class="w-3 h-3 ml-1 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-sm">
                                        <div class="flex flex-wrap gap-1">
                                            <a
                                                :href="route('demos.download', demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-blue-600/20 text-blue-300 text-[11px] font-medium rounded hover:bg-blue-600/30 transition-colors"
                                                title="Download"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                DL
                                            </a>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin)"
                                                @click="reprocessDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-yellow-600/20 text-yellow-300 text-[11px] font-medium rounded hover:bg-yellow-600/30 transition-colors"
                                                title="Reprocess"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && (demo.status === 'processed' || demo.status === 'failed' || demo.status === 'fallback-assigned') && !demo.record_id"
                                                @click="openAssignModal(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-green-600/20 text-green-300 text-[11px] font-medium rounded hover:bg-green-600/30 transition-colors"
                                                title="Assign"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && demo.record_id"
                                                @click="unassignDemo(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-orange-600/20 text-orange-300 text-[11px] font-medium rounded hover:bg-orange-600/30 transition-colors"
                                                title="Unassign"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && !demo.record_id"
                                                @click="deleteDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-red-600/20 text-red-300 text-[11px] font-medium rounded hover:bg-red-600/30 transition-colors"
                                                title="Delete"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                        <!-- Pagination -->
                        <div v-if="userDemos.last_page > 1" class="mt-6">
                            <Pagination
                                :last_page="userDemos.last_page"
                                :current_page="userDemos.current_page"
                                :link="userDemos.path + '?'"
                                pageName="userPage"
                            />
                        </div>
                    </template>
                </div>

                <!-- Browse All Demos Section (for everyone) -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
                    <h3 class="text-xl font-semibold text-gray-200 mb-4">
                        Browse All Demos
                    </h3>

                    <!-- Browse Filter Tabs and Search -->
                    <div class="mb-4 space-y-2">
                        <!-- Row 1: Category + Status Filters -->
                        <div class="flex flex-wrap gap-1.5 items-center">
                            <button
                                @click="changeBrowseTabFilter('all')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseTab === 'all'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                All <span class="opacity-75">({{ browseCountsComputed.all }})</span>
                            </button>
                            <button
                                @click="changeBrowseTabFilter('online')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseTab === 'online'
                                    ? 'bg-green-600 text-white'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                Online <span class="opacity-75">({{ browseCountsComputed.online }})</span>
                            </button>
                            <button
                                @click="changeBrowseTabFilter('offline')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseTab === 'offline'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                Offline <span class="opacity-75">({{ browseCountsComputed.offline }})</span>
                            </button>

                            <span class="border-l border-gray-600/50 mx-1"></span>

                            <button
                                @click="changeBrowseStatusFilter('all')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'all'
                                    ? 'bg-gray-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                All Status
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('assigned')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'assigned'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Assigned <span class="opacity-75">({{ browseCountsComputed.assigned }})</span>
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('fallback-assigned')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'fallback-assigned'
                                    ? 'bg-orange-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Fallback <span class="opacity-75">({{ browseCountsComputed.fallback_assigned || 0 }})</span>
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('processed')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'processed'
                                    ? 'bg-yellow-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Processed <span class="opacity-75">({{ browseCountsComputed.processed }})</span>
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('failed-validity')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'failed-validity'
                                    ? 'bg-orange-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Invalid <span class="opacity-75">({{ browseCountsComputed.failed_validity || 0 }})</span>
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('failed')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'failed'
                                    ? 'bg-red-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Failed <span class="opacity-75">({{ browseCountsComputed.failed }})</span>
                            </button>
                            <button
                                @click="changeBrowseStatusFilter('unsupported-version')"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                                :class="activeBrowseStatus === 'unsupported-version'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Unsupported <span class="opacity-75">({{ browseCountsComputed.unsupported_version || 0 }})</span>
                            </button>
                        </div>

                        <!-- Row 2: Search Bar -->
                        <div class="flex flex-wrap gap-2 items-center">
                            <div class="flex-grow max-w-md">
                                <div class="relative">
                                    <input
                                        v-model="browseSearchQuery"
                                        @keyup.enter="handleBrowseSearch"
                                        type="text"
                                        placeholder="Search by filename..."
                                        class="w-full px-4 py-1.5 bg-gray-700/50 border border-gray-600/50 rounded-lg text-sm text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    <button
                                        @click="handleBrowseSearch"
                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-200 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!publicDemos.data || publicDemos.data.length === 0" class="text-gray-400">
                        No demos available yet.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Filename
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Uploaded By
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Map
                                    </th>
                                    <th class="px-2 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-2 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Physics
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Time
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                <tr v-for="demo in publicDemos.data" :key="demo.id" class="hover:bg-gray-700/30 transition-colors duration-200">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-300">
                                        <span v-if="demo.user" v-html="q3tohtml(demo.user.name)"></span>
                                        <span v-else class="text-gray-500">Guest</span>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-gray-300">
                                        <Link v-if="demo.map_name" :href="`/maps/${encodeURIComponent(demo.map_name)}`" class="text-blue-400 hover:text-blue-300 underline transition-colors duration-200 truncate block max-w-[120px]" :title="demo.map_name">
                                            {{ demo.map_name }}
                                        </Link>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-2 py-4 text-xs text-gray-300">
                                        <span v-if="demo.gametype" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase" :class="demo.gametype.startsWith('m') ? 'bg-green-900/50 text-green-200' : 'bg-purple-900/50 text-purple-200'" :title="demo.gametype.startsWith('m') ? 'Online' : 'Offline'">
                                            {{ demo.gametype }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-2 py-4 text-xs text-gray-300">
                                        <span v-if="demo.physics" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium" :class="demo.physics === 'VQ3' ? 'bg-blue-900/50 text-blue-200' : 'bg-purple-900/50 text-purple-200'">
                                            {{ demo.physics }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-300 font-mono">
                                        {{ formatTime(demo.time_ms) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium"
                                            :class="{
                                                'bg-purple-900/50 text-purple-200': demo.status === 'assigned',
                                                'bg-orange-900/50 text-orange-200': demo.status === 'fallback-assigned',
                                                'bg-yellow-900/50 text-yellow-200': demo.status === 'processed',
                                                'bg-red-900/50 text-red-200': demo.status === 'failed',
                                                'bg-gray-900/50 text-gray-300': !['assigned', 'fallback-assigned', 'processed', 'failed'].includes(demo.status)
                                            }">
                                            {{ demo.status ? demo.status.charAt(0).toUpperCase() + demo.status.slice(1) : '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-sm">
                                        <div class="flex items-center space-x-1">
                                            <a
                                                :href="route('demos.download', demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-blue-600/20 text-blue-300 text-[11px] font-medium rounded hover:bg-blue-600/30 transition-colors"
                                                title="Download"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                DL
                                            </a>
                                            <button
                                                v-if="$page.props.auth.user && !demo.record_id && ['processed', 'fallback-assigned', 'failed'].includes(demo.status)"
                                                @click="openAssignModal(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-green-600/20 text-green-300 text-[11px] font-medium rounded hover:bg-green-600/30 transition-colors"
                                                title="Assign to online record"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination for Browse All Demos -->
                    <div v-if="publicDemos.last_page > 1" class="mt-6">
                        <Pagination
                            :last_page="publicDemos.last_page"
                            :current_page="publicDemos.current_page"
                            :link="publicDemos.path + '?'"
                            pageName="browsePage"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Assignment Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50" @click="closeAssignModal">
            <div class="backdrop-blur-xl bg-gray-900/95 rounded-xl p-8 w-full max-w-3xl max-h-[85vh] overflow-y-auto border border-white/10 shadow-2xl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-100">Assign Demo to Online Record</h3>
                    <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Demo info -->
                <div v-if="assigningDemo" class="mb-6 p-4 bg-gray-800/60 rounded-lg border border-white/5">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-sm"><span class="text-gray-500">Demo:</span> <span class="text-gray-200 font-medium truncate block">{{ assigningDemo.processed_filename || assigningDemo.original_filename }}</span></div>
                        <div v-if="assigningDemo.physics" class="text-sm"><span class="text-gray-500">Physics:</span> <span class="font-medium" :class="assigningDemo.physics === 'CPM' ? 'text-purple-400' : 'text-blue-400'">{{ assigningDemo.physics }}</span></div>
                        <div v-if="assigningDemo.map_name" class="text-sm"><span class="text-gray-500">Map:</span> <span class="text-gray-200 font-medium">{{ assigningDemo.map_name }}</span></div>
                        <div v-if="assigningDemo.time_ms" class="text-sm"><span class="text-gray-500">Time:</span> <span class="text-gray-200 font-mono font-medium">{{ formatTime(assigningDemo.time_ms) }}</span></div>
                    </div>
                </div>

                <!-- Physics Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Physics</label>
                    <select v-model="selectedPhysics" @change="selectedMap && loadRecords()" class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white">
                        <option value="VQ3">VQ3</option>
                        <option value="CPM">CPM</option>
                    </select>
                </div>

                <!-- Map Search -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Search Map</label>
                    <input
                        v-model="searchQuery"
                        @input="searchMaps"
                        type="text"
                        placeholder="Type map name..."
                        class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500"
                    />
                </div>

                <!-- Available Maps -->
                <div v-if="availableMaps.length > 0 && !selectedMap" class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Available Maps</label>
                    <div class="max-h-32 overflow-y-auto border border-gray-700/50 rounded-lg">
                        <button
                            v-for="map in availableMaps"
                            :key="map"
                            @click="selectMap(map)"
                            :class="[
                                'w-full text-left px-4 py-2.5 hover:bg-white/5 border-b border-gray-800/50 last:border-b-0 transition-colors',
                                selectedMap === map ? 'bg-blue-600/30 text-white' : 'text-gray-300'
                            ]"
                        >
                            {{ map }}
                        </button>
                    </div>
                </div>

                <!-- Selected map indicator -->
                <div v-if="selectedMap" class="mb-4 flex items-center gap-2">
                    <span class="text-sm text-gray-400">Map:</span>
                    <span class="text-sm font-medium text-gray-200 bg-gray-800 px-3 py-1 rounded-lg">{{ selectedMap }}</span>
                    <button @click="selectedMap = ''; availableRecords = []; selectedRecord = ''" class="text-xs text-gray-500 hover:text-gray-300">(change)</button>
                </div>

                <!-- Loading indicator for maps -->
                <div v-if="loadingMaps" class="mb-4 text-center">
                    <div class="text-gray-400">Loading maps...</div>
                </div>

                <!-- Suggested matches -->
                <div v-if="selectedMap && !loadingRecords && suggestedRecords.length > 0" class="mb-5">
                    <label class="block text-sm font-medium text-green-400 mb-2">
                        Closest time matches
                    </label>
                    <div class="border border-green-700/30 rounded-lg bg-green-900/10 overflow-hidden">
                        <button
                            v-for="record in suggestedRecords"
                            :key="'suggested-' + record.id"
                            @click="selectedRecord = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-green-800/20 border-b border-green-800/20 last:border-b-0 transition-all',
                                selectedRecord === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs" :class="record.timeDiff === 0 ? 'text-green-400 font-bold' : 'text-gray-500'">
                                        {{ record.timeDiff === 0 ? 'EXACT' : (record.timeDiff < 1000 ? record.timeDiff + 'ms' : formatTime(record.timeDiff)) + ' diff' }}
                                    </span>
                                    <span class="text-sm font-mono" :class="selectedRecord === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- All Records -->
                <div v-if="selectedMap && !loadingRecords && availableRecords.length > 0" class="mb-6">
                    <label class="block text-sm font-medium text-gray-400 mb-3">
                        All records ({{ availableRecords.length }})
                    </label>
                    <div class="max-h-[400px] overflow-y-auto border border-gray-700/50 rounded-lg">
                        <button
                            v-for="record in availableRecords"
                            :key="record.id"
                            @click="selectedRecord = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-white/5 border-b border-gray-800/50 last:border-b-0 transition-all',
                                selectedRecord === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <span class="text-sm font-mono" :class="selectedRecord === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Loading indicator for records -->
                <div v-if="loadingRecords" class="mb-4 text-center py-4">
                    <div class="text-gray-400">Loading records...</div>
                </div>

                <!-- No records found -->
                <div v-if="selectedMap && !loadingRecords && availableRecords.length === 0" class="mb-6 text-center text-gray-400 py-8">
                    No records found for {{ selectedMap }} ({{ selectedPhysics }})
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="closeAssignModal" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="assignDemo"
                        :disabled="!selectedRecord"
                        :class="[
                            'px-5 py-2.5 rounded-lg text-white font-medium transition-colors',
                            selectedRecord ? 'bg-green-600 hover:bg-green-500' : 'bg-gray-600 cursor-not-allowed'
                        ]"
                    >
                        Assign Demo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Tooltip for Failed and Assigned Demos -->
    <Teleport to="body">
        <!-- Failed Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'unsupported-version' && hoveredDemo.processing_output"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '500px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Unparseable Demo
                </div>
                <div class="text-[11px] text-purple-200/80 leading-relaxed">
                    This demo file could not be parsed. It may be corrupted or recorded with an incompatible engine version.
                </div>
            </div>
        </div>

        <!-- Failed Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'failed' && hoveredDemo.processing_output"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '500px'
            }"
        >
            <div class="bg-gray-900 border border-red-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-red-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Error Details:
                </div>
                <div class="font-mono text-[11px] text-red-200 whitespace-pre-wrap break-words max-h-60 overflow-y-auto">{{ hoveredDemo.processing_output }}</div>
            </div>
        </div>

        <!-- Failed-Validity Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'failed-validity' && hoveredDemo.validity"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-orange-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-orange-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Invalid Settings:
                </div>
                <div class="space-y-1 text-orange-200">
                    <div v-for="(value, key) in (typeof hoveredDemo.validity === 'string' ? JSON.parse(hoveredDemo.validity) : hoveredDemo.validity)" :key="key">
                        <span class="font-semibold">{{ key }}:</span> <span class="font-mono">{{ value }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Demo Tooltip (Online Record) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'assigned' && hoveredDemo.record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Online Record Details:
                </div>
                <div class="space-y-1 text-gray-300">
                    <div><span class="text-gray-400">Record ID:</span> <span class="font-semibold text-purple-300">#{{ hoveredDemo.record_id }}</span></div>
                    <div><span class="text-gray-400">Map:</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.record.mapname }}</span></div>
                    <div v-if="hoveredDemo.record.user"><span class="text-gray-400">Player:</span> <span class="font-semibold text-green-300">{{ hoveredDemo.record.user.name }}</span></div>
                    <div><span class="text-gray-400">Time:</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.record.time) }}</span></div>
                    <div v-if="hoveredDemo.record.date_set"><span class="text-gray-400">Date:</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.record.date_set).toLocaleDateString() }}</span></div>
                </div>
            </div>
        </div>

        <!-- Assigned Demo Tooltip (Offline Record) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'assigned' && hoveredDemo.offline_record && !hoveredDemo.record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Offline Record Details:
                </div>
                <div class="space-y-1 text-gray-300">
                    <div><span class="text-gray-400">Record ID:</span> <span class="font-semibold text-purple-300">#{{ hoveredDemo.offline_record.id }}</span></div>
                    <div><span class="text-gray-400">Map:</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.offline_record.map_name }}</span></div>
                    <div><span class="text-gray-400">Player:</span> <span class="font-semibold text-green-300">{{ hoveredDemo.offline_record.player_name }}</span></div>
                    <div><span class="text-gray-400">Time:</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.offline_record.time_ms) }}</span></div>
                    <div><span class="text-gray-400">Rank:</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.rank }}</span></div>
                    <div><span class="text-gray-400">Gametype:</span> <span class="font-semibold text-cyan-300 uppercase">{{ hoveredDemo.offline_record.gametype }}</span></div>
                    <div v-if="hoveredDemo.offline_record.date_set"><span class="text-gray-400">Date:</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.offline_record.date_set).toLocaleDateString() }}</span></div>
                </div>
            </div>
        </div>

        <!-- Fallback-Assigned Demo Tooltip (Offline Record - Rematchable) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'fallback-assigned' && hoveredDemo.offline_record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-orange-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-orange-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Fallback Offline Record (Rematchable):
                </div>
                <div class="space-y-1 text-gray-300 mb-2">
                    <div><span class="text-gray-400">Record ID:</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.id }}</span></div>
                    <div><span class="text-gray-400">Map:</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.offline_record.map_name }}</span></div>
                    <div><span class="text-gray-400">Player:</span> <span class="font-semibold text-green-300">{{ hoveredDemo.offline_record.player_name }}</span></div>
                    <div><span class="text-gray-400">Time:</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.offline_record.time_ms) }}</span></div>
                    <div><span class="text-gray-400">Rank:</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.rank }}</span></div>
                    <div><span class="text-gray-400">Gametype:</span> <span class="font-semibold text-cyan-300 uppercase">{{ hoveredDemo.offline_record.gametype }}</span></div>
                    <div v-if="hoveredDemo.offline_record.date_set"><span class="text-gray-400">Date:</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.offline_record.date_set).toLocaleDateString() }}</span></div>
                </div>
                <div class="text-[10px] text-orange-200/70 border-t border-orange-600/30 pt-2 mt-2">
                    ⚠️ This online demo created a fallback offline record but can still be matched to an online record later.
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Reprocess Confirm Modal -->
    <Teleport to="body">
        <div v-if="showReprocessConfirm" class="fixed inset-0 z-[60] flex items-center justify-center" @click.self="showReprocessConfirm = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative bg-gray-800 border border-gray-600/50 rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-orange-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-100">Reprocess Failed Demos</h3>
                        <p class="text-sm text-gray-400">{{ demoCountsComputed.failed }} demo(s) will be queued</p>
                    </div>
                </div>
                <p class="text-sm text-gray-300 mb-6">All failed demos will be sent back to the processing queue. You can track the progress in the queue status above.</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showReprocessConfirm = false" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-700 hover:bg-gray-600 border border-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button @click="reprocessAllFailed" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-orange-600 hover:bg-orange-500 transition-colors">
                        Reprocess All
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Upload Info Modal -->
    <Teleport to="body">
        <div v-if="showUploadInfo" class="fixed inset-0 z-[60] flex items-center justify-center" @click.self="showUploadInfo = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative bg-gray-800 border border-gray-600/50 rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                        :class="{
                            'bg-blue-500/20': uploadInfoType === 'info',
                            'bg-yellow-500/20': uploadInfoType === 'warning',
                            'bg-red-500/20': uploadInfoType === 'error'
                        }"
                    >
                        <svg v-if="uploadInfoType === 'info'" class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <svg v-else-if="uploadInfoType === 'warning'" class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">{{ uploadInfoTitle }}</h3>
                </div>
                <p class="text-sm text-gray-300 mb-6 whitespace-pre-line">{{ uploadInfoMessage }}</p>
                <div class="flex justify-end">
                    <button @click="showUploadInfo = false" class="px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                        :class="{
                            'bg-blue-600 hover:bg-blue-500': uploadInfoType === 'info',
                            'bg-yellow-600 hover:bg-yellow-500': uploadInfoType === 'warning',
                            'bg-red-600 hover:bg-red-500': uploadInfoType === 'error'
                        }"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>