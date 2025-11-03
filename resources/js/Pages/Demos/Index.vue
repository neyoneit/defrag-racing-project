<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
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
    sortBy: String,
    sortOrder: String,
    downloadLimitInfo: Object,
});
const fileInput = ref(null);
const selectedFiles = ref([]);
const uploading = ref(false);
const uploadErrors = ref([]);
const uploadSuccess = ref([]);
const processingDemos = ref([]);
const queueStats = ref({});
const statusPolling = ref(null);
const uploadProgress = ref(0);

// Filter state
const activeTab = ref('all'); // all, online, offline
const activeStatusFilter = ref('all'); // all, assigned, failed

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
    return props.demoCounts || {
        all: 0,
        online: 0,
        offline: 0,
        assigned: 0,
        failed: 0,
    };
});

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files || event.dataTransfer.files);
    const validFiles = files.filter(file => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        // Accept demo files like .dm_68 and archive files (.zip, .rar, .7z)
        return ext && (ext.match(/^dm_\d+$/) || ['zip', 'rar', '7z'].includes(ext));
    });

    if (validFiles.length !== files.length) {
        const skipped = files.length - validFiles.length;
        alert(`Found ${validFiles.length} demo files. Skipped ${skipped} non-demo file(s). Only demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.`);
    }

    selectedFiles.value = validFiles;
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

    const items = Array.from(e.dataTransfer.items);
    const allFiles = [];

    // Helper function to recursively read directory
    const readDirectory = async (entry) => {
        if (entry.isFile) {
            return new Promise((resolve) => {
                entry.file((file) => {
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    if (ext && (ext.match(/^dm_\d+$/) || ['zip', 'rar', '7z'].includes(ext))) {
                        allFiles.push(file);
                    }
                    resolve();
                });
            });
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            return new Promise((resolve) => {
                reader.readEntries(async (entries) => {
                    for (const entry of entries) {
                        await readDirectory(entry);
                    }
                    resolve();
                });
            });
        }
    };

    // Process all dropped items
    for (const item of items) {
        const entry = item.webkitGetAsEntry();
        if (entry) {
            await readDirectory(entry);
        }
    }

    if (allFiles.length > 0) {
        const totalFiles = items.length;
        if (allFiles.length < totalFiles) {
            const skipped = totalFiles - allFiles.length;
            alert(`Found ${allFiles.length} demo files. Skipped ${skipped} non-demo file(s). Only demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.`);
        }
        selectedFiles.value = [...selectedFiles.value, ...allFiles];
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

const uploadDemos = async () => {
    if (selectedFiles.value.length === 0) {
        alert('Please select demo files to upload');
        return;
    }

    uploading.value = true;
    uploadProgress.value = 0;
    uploadErrors.value = [];
    uploadSuccess.value = [];

    const formData = new FormData();
    selectedFiles.value.forEach(file => {
        formData.append('demos[]', file);
    });

    try {
        const response = await axios.post(route('demos.upload'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: function (progressEvent) {
                if (progressEvent.lengthComputable) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            }
        });

        if (response.data.success) {
            uploadSuccess.value = response.data.uploaded;
            uploadErrors.value = response.data.errors || [];

            // Clear selected files
            selectedFiles.value = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }

            // If upload returned demo IDs, start targeted polling for them
            const demoIds = (response.data.uploaded || []).map(d => d.id).filter(Boolean);
            if (demoIds.length > 0) {
                startStatusPolling(demoIds);
            } else {
                // Fallback: start global polling
                startStatusPolling();
            }

            // Immediately reload the demos list
            router.reload({ only: ['userDemos', 'publicDemos'] });

            // Clear success message after 10 seconds (longer for queue processing)
            setTimeout(() => {
                uploadSuccess.value = [];
            }, 10000);
        }
    } catch (error) {
        console.error('Upload error:', error);
        uploadErrors.value = ['Upload failed: ' + (error.response?.data?.message || error.message)];
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

const getStatusColor = (status) => {
    switch (status) {
        case 'uploaded': return 'text-yellow-500';
        case 'processing': return 'text-blue-500';
        case 'processed': return 'text-green-500';
        case 'assigned': return 'text-purple-500';
        case 'failed': return 'text-red-500';
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
            router.reload({ only: ['userDemos', 'publicDemos'] });
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
const startStatusPolling = (targetDemoIds = null) => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
    }

    statusPolling.value = setInterval(async () => {
        try {
            const params = {};
            if (targetDemoIds && targetDemoIds.length > 0) {
                params.demo_ids = targetDemoIds;
            }

            const response = await axios.get(route('demos.status'), { params });
            processingDemos.value = response.data.processing_demos;
            queueStats.value = response.data.queue_stats;

            // If we target specific demo ids, stop polling when none of them are queued/processing
            if (targetDemoIds && targetDemoIds.length > 0) {
                const stillProcessing = (response.data.processing_demos || []).filter(d => targetDemoIds.includes(d.id)).length;
                if (stillProcessing === 0) {
                    stopStatusPolling();
                    router.reload({ only: ['userDemos', 'publicDemos'] });
                }
            } else {
                // Stop polling if no demos are processing/queued (global)
                if ((response.data.processing_demos || []).length === 0) {
                    stopStatusPolling();
                    router.reload({ only: ['userDemos', 'publicDemos'] }); // Final reload when all done
                }
            }
        } catch (error) {
            console.error('Status polling error:', error);
        }
    }, 3000); // Poll every 3 seconds
};

const stopStatusPolling = () => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
        statusPolling.value = null;
    }
};

// Start polling on component mount if there are processing demos
const checkForProcessingDemos = async () => {
    if ($page.props.auth.user) {
        try {
            const response = await axios.get(route('demos.status'));
            processingDemos.value = response.data.processing_demos;
            queueStats.value = response.data.queue_stats;

            if (response.data.processing_demos.length > 0) {
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
    searchMaps();
};

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

                    <!-- Drag and Drop Zone -->
                    <div
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
                        
                        <!-- Guest notice -->
                        <div v-if="!$page.props.auth.user" class="mt-4 text-sm text-yellow-300">
                            You are not logged in. Uploaded demos will be public and you will not be able to delete them from the UI.
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

                        <div class="grid gap-3 max-h-60 overflow-y-auto">
                            <div
                                v-for="(file, index) in selectedFiles"
                                :key="file.name + index"
                                class="flex items-center justify-between bg-gray-700/50 rounded-lg p-4 border border-gray-600/50 hover:bg-gray-700 transition-colors duration-200"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">{{ file.name }}</p>
                                        <p class="text-xs text-gray-400">{{ formatFileSize(file.size) }}</p>
                                    </div>
                                </div>
                                <button
                                    @click="removeFile(index)"
                                    class="flex-shrink-0 p-1 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition-colors duration-200"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                    <!-- Upload Results -->
                    <div v-if="uploadErrors.length > 0" class="mt-6 p-6 bg-gradient-to-r from-red-900/30 to-red-800/20 rounded-xl border border-red-700/50">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 h-6 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h4 class="text-red-300 font-semibold text-lg">Upload Errors</h4>
                        </div>
                        <div class="space-y-2">
                            <div v-for="error in uploadErrors" :key="error" class="flex items-start space-x-2">
                                <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span class="text-red-200 text-sm">{{ error }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="uploadSuccess.length > 0" class="mt-6 p-6 bg-gradient-to-r from-green-900/30 to-green-800/20 rounded-xl border border-green-700/50">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 h-6 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h4 class="text-green-300 font-semibold text-lg">Successfully Uploaded</h4>
                        </div>
                        <div class="space-y-2">
                            <div v-for="demo in uploadSuccess" :key="demo.id" class="flex items-start space-x-2">
                                <svg class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <span class="text-green-200 text-sm font-medium">{{ demo.processed_filename || demo.original_filename }}</span>
                                    <span v-if="demo.record_id" class="block text-purple-300 text-xs mt-1">
                                        ✨ Auto-assigned to record
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Processing Status (real-time) -->
                <div v-if="processingDemos.length > 0 || Object.keys(queueStats).length > 0" class="backdrop-blur-xl bg-black/40 rounded-xl p-3 mb-4 shadow-2xl border border-white/5">
                    <h3 class="text-base font-semibold text-gray-200 mb-2">Processing Status</h3>

                    <!-- Queue Statistics -->
                    <div v-if="Object.keys(queueStats).length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                        <div class="bg-gradient-to-br from-blue-600/20 to-blue-700/10 rounded-lg p-2 text-center border border-blue-600/30">
                            <div class="text-lg font-bold text-blue-400">{{ queueStats.user_queued || 0 }}</div>
                            <div class="text-xs text-blue-300">Your Queued</div>
                        </div>
                        <div class="bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-lg p-2 text-center border border-yellow-600/30">
                            <div class="text-lg font-bold text-yellow-400">{{ queueStats.user_processing || 0 }}</div>
                            <div class="text-xs text-yellow-300">Your Processing</div>
                        </div>
                        <div class="bg-gradient-to-br from-gray-600/20 to-gray-700/10 rounded-lg p-2 text-center border border-gray-600/30">
                            <div class="text-lg font-bold text-gray-300">{{ queueStats.total_queued || 0 }}</div>
                            <div class="text-xs text-gray-400">Total Queued</div>
                        </div>
                        <div class="bg-gradient-to-br from-green-600/20 to-green-700/10 rounded-lg p-2 text-center border border-green-600/30">
                            <div class="text-lg font-bold text-green-400">{{ queueStats.total_processing || 0 }}</div>
                            <div class="text-xs text-green-300">Total Processing</div>
                        </div>
                    </div>

                    <!-- Processing Demos List -->
                    <div v-if="processingDemos.length > 0" class="space-y-2">
                        <h4 class="text-sm font-semibold text-gray-300">Currently Processing:</h4>
                        <div v-for="demo in processingDemos" :key="demo.id" class="flex items-center justify-between bg-gray-700/50 rounded-lg p-2 border border-gray-600/50">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div>
                                    <div class="text-gray-200 font-medium text-sm">{{ demo.original_filename }}</div>
                                    <div class="text-xs text-gray-400">Status: <span class="text-yellow-400">{{ demo.status }}</span></div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="flex space-x-1">
                                    <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse"></div>
                                    <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                    <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                </div>
                                <span class="text-yellow-400 text-xs font-medium">Processing...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Your Uploads Section (authenticated users only) -->
                <div v-if="$page.props.auth.user && userDemos" class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5 mb-8">
                    <h3 class="text-xl font-semibold text-gray-200 mb-4">
                        Your Uploaded Demos
                    </h3>

                    <!-- Filter Tabs -->
                    <div class="mb-6 space-y-4">
                        <!-- Category Tabs (Online/Offline) -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                @click="changeTabFilter('all')"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                                :class="activeTab === 'all'
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/50'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                All Demos <span class="ml-1 text-xs opacity-75">({{ demoCountsComputed.all }})</span>
                            </button>
                            <button
                                @click="changeTabFilter('online')"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                                :class="activeTab === 'online'
                                    ? 'bg-green-600 text-white shadow-lg shadow-green-600/50'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                Online <span class="ml-1 text-xs opacity-75">({{ demoCountsComputed.online }})</span>
                            </button>
                            <button
                                @click="changeTabFilter('offline')"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                                :class="activeTab === 'offline'
                                    ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/50'
                                    : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
                            >
                                Offline <span class="ml-1 text-xs opacity-75">({{ demoCountsComputed.offline }})</span>
                            </button>
                        </div>

                        <!-- Status Filters -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                @click="changeStatusFilter('all')"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                                :class="activeStatusFilter === 'all'
                                    ? 'bg-gray-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                All Status
                            </button>
                            <button
                                @click="changeStatusFilter('assigned')"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                                :class="activeStatusFilter === 'assigned'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Assigned <span class="ml-1 opacity-75">({{ demoCountsComputed.assigned }})</span>
                            </button>
                            <button
                                @click="changeStatusFilter('failed')"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                                :class="activeStatusFilter === 'failed'
                                    ? 'bg-red-600 text-white'
                                    : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
                            >
                                Failed <span class="ml-1 opacity-75">({{ demoCountsComputed.failed }})</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!userDemos.data || userDemos.data.length === 0" class="text-gray-400">
                        You haven't uploaded any demos yet.
                    </div>

                    <div v-else-if="filteredDemos.length === 0" class="text-gray-400 text-center py-8">
                        No demos match the selected filters.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
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
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
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
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>
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
                                    <td class="px-3 py-4 text-xs text-gray-300">
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
                                                        'bg-red-900/50 text-red-200 hover:bg-red-800/50 cursor-help': demo.status === 'failed',
                                                        'bg-gray-900/50 text-gray-200': !['uploaded', 'processing', 'processed', 'assigned', 'failed'].includes(demo.status)
                                                    }"
                                                    @mouseenter="(demo.status === 'failed' && demo.processing_output) || (demo.status === 'assigned' && (demo.record || demo.offline_record)) ? showTooltip(demo, $event) : null"
                                                    @mouseleave="hideTooltip"
                                                    @mousemove="hoveredDemo?.id === demo.id ? updateTooltipPosition($event) : null"
                                                >
                                                    {{ demo.status }}
                                                    <svg v-if="demo.status === 'failed' && demo.processing_output" class="w-3 h-3 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'assigned' && (demo.record || demo.offline_record)" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-2">
                                            <a
                                                :href="route('demos.download', demo.id)"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-600/20 text-blue-300 text-xs font-medium rounded-md hover:bg-blue-600/30 transition-colors duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Download
                                            </a>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin)"
                                                @click="reprocessDemo(demo.id)"
                                                class="inline-flex items-center px-3 py-1.5 bg-yellow-600/20 text-yellow-300 text-xs font-medium rounded-md hover:bg-yellow-600/30 transition-colors duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                                Reprocess
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && demo.status === 'processed' && !demo.record_id"
                                                @click="openAssignModal(demo)"
                                                class="inline-flex items-center px-3 py-1.5 bg-green-600/20 text-green-300 text-xs font-medium rounded-md hover:bg-green-600/30 transition-colors duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                                Assign
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && demo.record_id"
                                                @click="unassignDemo(demo)"
                                                class="inline-flex items-center px-3 py-1.5 bg-orange-600/20 text-orange-300 text-xs font-medium rounded-md hover:bg-orange-600/30 transition-colors duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                                Unassign
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && !demo.record_id"
                                                @click="deleteDemo(demo.id)"
                                                class="inline-flex items-center px-3 py-1.5 bg-red-600/20 text-red-300 text-xs font-medium rounded-md hover:bg-red-600/30 transition-colors duration-200"
                                            >
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Delete
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
                </div>

                <!-- Browse All Demos Section (for everyone) -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl p-6 shadow-2xl border border-white/5">
                    <h3 class="text-xl font-semibold text-gray-200 mb-4">
                        Browse All Demos
                    </h3>

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
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Physics
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Time
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
                                        <span v-if="demo.user">{{ demo.user.name }}</span>
                                        <span v-else class="text-gray-500">Guest</span>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-gray-300">
                                        <Link v-if="demo.map_name" :href="`/maps/${encodeURIComponent(demo.map_name)}`" class="text-blue-400 hover:text-blue-300 underline transition-colors duration-200 truncate block max-w-[120px]" :title="demo.map_name">
                                            {{ demo.map_name }}
                                        </Link>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-gray-300">
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
                                        <a
                                            :href="route('demos.download', demo.id)"
                                            class="inline-flex items-center px-3 py-1.5 bg-blue-600/20 text-blue-300 text-xs font-medium rounded-md hover:bg-blue-600/30 transition-colors duration-200"
                                        >
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            Download
                                        </a>
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
        <div v-if="showAssignModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeAssignModal">
            <div class="backdrop-blur-xl bg-black/40 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-white/5" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-200">Assign Demo to Record</h3>
                    <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div v-if="assigningDemo" class="mb-4 p-3 bg-gray-700 rounded">
                    <p class="text-sm text-gray-300">
                        <strong>Demo:</strong> {{ assigningDemo.processed_filename || assigningDemo.original_filename }}
                    </p>
                    <p v-if="assigningDemo.map_name" class="text-sm text-gray-300">
                        <strong>Detected Map:</strong> {{ assigningDemo.map_name }}
                    </p>
                    <p v-if="assigningDemo.physics" class="text-sm text-gray-300">
                        <strong>Detected Physics:</strong> {{ assigningDemo.physics }}
                    </p>
                    <p v-if="assigningDemo.time_ms" class="text-sm text-gray-300">
                        <strong>Demo Time:</strong> {{ formatTime(assigningDemo.time_ms) }}
                    </p>
                </div>

                <!-- Physics Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Physics</label>
                    <select v-model="selectedPhysics" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white">
                        <option value="VQ3">VQ3</option>
                        <option value="CPM">CPM</option>
                    </select>
                </div>

                <!-- Map Search -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Search Map</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Type map name..."
                        class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400"
                    />
                </div>

                <!-- Available Maps -->
                <div v-if="availableMaps.length > 0" class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Available Maps</label>
                    <div class="max-h-32 overflow-y-auto border border-gray-600 rounded">
                        <button
                            v-for="map in availableMaps"
                            :key="map"
                            @click="selectMap(map)"
                            :class="[
                                'w-full text-left px-3 py-2 hover:bg-gray-600 border-b border-gray-600 last:border-b-0',
                                selectedMap === map ? 'bg-blue-600 text-white' : 'text-gray-300'
                            ]"
                        >
                            {{ map }}
                        </button>
                    </div>
                </div>

                <!-- Loading indicator for maps -->
                <div v-if="loadingMaps" class="mb-4 text-center">
                    <div class="text-gray-400">Loading maps...</div>
                </div>

                <!-- Available Records -->
                <div v-if="selectedMap && availableRecords.length > 0" class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Records for {{ selectedMap }} ({{ selectedPhysics }})
                    </label>
                    <div class="max-h-64 overflow-y-auto border border-gray-600 rounded">
                        <button
                            v-for="record in availableRecords"
                            :key="record.id"
                            @click="selectedRecord = record.id"
                            :class="[
                                'w-full text-left px-3 py-2 hover:bg-gray-600 border-b border-gray-600 last:border-b-0',
                                selectedRecord === record.id ? 'bg-green-600 text-white' : 'text-gray-300'
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <span>#{{ record.rank }} - {{ record.player_name }}</span>
                                <span class="text-sm">{{ record.formatted_time }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Loading indicator for records -->
                <div v-if="loadingRecords" class="mb-4 text-center">
                    <div class="text-gray-400">Loading records...</div>
                </div>

                <!-- No records found -->
                <div v-if="selectedMap && !loadingRecords && availableRecords.length === 0" class="mb-4 text-center text-gray-400">
                    No records found for {{ selectedMap }} ({{ selectedPhysics }})
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button
                        @click="closeAssignModal"
                        class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500"
                    >
                        Cancel
                    </button>
                    <button
                        @click="assignDemo"
                        :disabled="!selectedRecord"
                        :class="[
                            'px-4 py-2 rounded text-white',
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
    </Teleport>
</template>