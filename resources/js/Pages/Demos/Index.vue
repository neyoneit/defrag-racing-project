<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
}
</script>

<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const $page = usePage();

const props = defineProps({
    demos: Object,
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

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files || event.dataTransfer.files);
    const validFiles = files.filter(file => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        // Accept demo files like .dm_68 and archive files (.zip, .rar, .7z)
        return ext && (ext.match(/^dm_\d+$/) || ['zip', 'rar', '7z'].includes(ext));
    });

    if (validFiles.length !== files.length) {
        alert('Some files were not recognized as demo files and were not added. Archives (.zip, .rar, .7z) are supported.');
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

const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    isDragOver.value = false;
    dragCounter.value = 0;

    const files = Array.from(e.dataTransfer.files);
    const validFiles = files.filter(file => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        return ext && (ext.match(/^dm_\d+$/) || ['zip', 'rar', '7z'].includes(ext));
    });

    if (validFiles.length !== files.length) {
        alert('Some files were not recognized as demo files and were not added. Archives (.zip, .rar, .7z) are supported.');
    }

    selectedFiles.value = [...selectedFiles.value, ...validFiles];
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
            router.reload({ only: ['demos'] });

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
            router.reload({ only: ['demos'] });
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
        router.reload({ only: ['demos'] });
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

    props.demos.data.forEach(demo => {
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
                    router.reload({ only: ['demos'] });
                }
            } else {
                // Stop polling if no demos are processing/queued (global)
                if ((response.data.processing_demos || []).length === 0) {
                    stopStatusPolling();
                    router.reload({ only: ['demos'] }); // Final reload when all done
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
            router.reload({ only: ['demos'] });
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
            router.reload({ only: ['demos'] });
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

        <div class="py-6 overflow-x-hidden">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-200">Demos</h2>
                    <p class="text-gray-400 mt-2">
                        <span>Upload and manage demo files. If you're not logged in, you can still upload demos from this page — they will be stored as guest uploads and cannot be deleted by you. Logging in later will not automatically link guest uploads to your account.</span>
                    </p>
                </div>

                <!-- Upload Section (visible to all users; guests will have restricted actions) -->
                <div class="bg-gray-800 rounded-xl p-8 mb-8 shadow-2xl border border-gray-700">
                    <h3 class="text-2xl font-bold text-gray-100 mb-6 flex items-center">
                        <svg class="w-8 h-8 mr-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            'relative border-2 border-dashed rounded-xl p-8 text-center transition-all duration-300 ease-in-out',
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
                        <div class="space-y-4">
                            <div class="flex justify-center">
                                <svg :class="[
                                    'w-16 h-16 transition-all duration-300',
                                    isDragOver ? 'text-blue-400 scale-110' : 'text-gray-400'
                                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>

                            <div>
                                <p :class="[
                                    'text-xl font-semibold transition-colors duration-300',
                                    isDragOver ? 'text-blue-300' : 'text-gray-200'
                                ]">
                                    {{ isDragOver ? 'Drop demo files here' : 'Drag demo files here' }}
                                </p>
                                <p class="text-gray-400 mt-2">
                                    Or click to choose files (.dm_68, .dm_66, .dm_67, .dm_73) or upload an archive (.zip, .rar, .7z)
                                </p>
                            </div>

                            <!-- Hidden file input -->
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                accept=".dm_68,.dm_66,.dm_67,.dm_73,.zip,.rar,.7z"
                                @change="handleFileSelect"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            />

                            <!-- Browse button -->
                            <button
                                type="button"
                                class="relative inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                                @click="fileInput.click()"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Browse files
                            </button>
                        
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
                <div v-if="processingDemos.length > 0 || Object.keys(queueStats).length > 0" class="bg-gray-800 rounded-xl p-6 mb-8 shadow-xl border border-gray-700">
                    <h3 class="text-xl font-semibold text-gray-200 mb-4">Processing Status</h3>

                    <!-- Queue Statistics -->
                    <div v-if="Object.keys(queueStats).length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-blue-600/20 to-blue-700/10 rounded-xl p-4 text-center border border-blue-600/30">
                            <div class="text-3xl font-bold text-blue-400 mb-1">{{ queueStats.user_queued || 0 }}</div>
                            <div class="text-sm text-blue-300">Your Queued</div>
                        </div>
                        <div class="bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-xl p-4 text-center border border-yellow-600/30">
                            <div class="text-3xl font-bold text-yellow-400 mb-1">{{ queueStats.user_processing || 0 }}</div>
                            <div class="text-sm text-yellow-300">Your Processing</div>
                        </div>
                        <div class="bg-gradient-to-br from-gray-600/20 to-gray-700/10 rounded-xl p-4 text-center border border-gray-600/30">
                            <div class="text-3xl font-bold text-gray-300 mb-1">{{ queueStats.total_queued || 0 }}</div>
                            <div class="text-sm text-gray-400">Total Queued</div>
                        </div>
                        <div class="bg-gradient-to-br from-green-600/20 to-green-700/10 rounded-xl p-4 text-center border border-green-600/30">
                            <div class="text-3xl font-bold text-green-400 mb-1">{{ queueStats.total_processing || 0 }}</div>
                            <div class="text-sm text-green-300">Total Processing</div>
                        </div>
                    </div>

                    <!-- Processing Demos List -->
                    <div v-if="processingDemos.length > 0" class="space-y-2">
                        <h4 class="text-lg font-semibold text-gray-300">Currently Processing:</h4>
                        <div v-for="demo in processingDemos" :key="demo.id" class="flex items-center justify-between bg-gray-700/50 rounded-xl p-4 border border-gray-600/50">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div>
                                    <div class="text-gray-200 font-medium">{{ demo.original_filename }}</div>
                                    <div class="text-sm text-gray-400">Status: <span class="text-yellow-400">{{ demo.status }}</span></div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="flex space-x-1">
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                </div>
                                <span class="text-yellow-400 text-sm font-medium">Processing...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Demos List -->
                <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700">
                    <h3 class="text-xl font-semibold text-gray-200 mb-4">
                        <span v-if="$page.props.auth.user">Your Uploaded Demos</span>
                        <span v-else>Recent Demos</span>
                    </h3>

                    <div v-if="demos.data.length === 0" class="text-gray-400">
                        <span v-if="$page.props.auth.user">You haven't uploaded any demos yet.</span>
                        <span v-else>No demos available yet.</span>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Filename
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        Map
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
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
                                <tr v-for="demo in demos.data" :key="demo.id" class="hover:bg-gray-700/30 transition-colors duration-200">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-300">
                                        {{ demo.map_name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-300">
                                        <span v-if="demo.physics" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="demo.physics === 'VQ3' ? 'bg-blue-900/50 text-blue-200' : 'bg-purple-900/50 text-purple-200'">
                                            {{ demo.physics }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-300 font-mono">
                                        {{ formatTime(demo.time_ms) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                                                'bg-yellow-900/50 text-yellow-200': demo.status === 'uploaded',
                                                'bg-blue-900/50 text-blue-200': demo.status === 'processing',
                                                'bg-green-900/50 text-green-200': demo.status === 'processed',
                                                'bg-purple-900/50 text-purple-200': demo.status === 'assigned',
                                                'bg-red-900/50 text-red-200': demo.status === 'failed',
                                                'bg-gray-900/50 text-gray-200': !['uploaded', 'processing', 'processed', 'assigned', 'failed'].includes(demo.status)
                                            }">
                                                {{ demo.status }}
                                            </span>
                                            <span v-if="demo.record_id" class="text-purple-400 text-xs">
                                                (<Link :href="demo.record?.mapname ? route('maps.map', demo.record.mapname) : route('records')" class="text-purple-400 hover:text-purple-300 underline transition-colors duration-200" :title="demo.record ? `${demo.record.mapname} - ${demo.record.user?.name} - ${formatTime(demo.record.time)}` : 'View record details'">Record #{{ demo.record_id }}</Link>)
                                            </span>
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
                    <div v-if="demos.links && demos.links.length > 3" class="mt-4 flex justify-center">
                        <nav class="flex space-x-2">
                            <Link
                                v-for="link in demos.links"
                                :key="link.label"
                                :href="link.url"
                                :class="[
                                    'px-3 py-2 rounded',
                                    link.active ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600',
                                    !link.url && 'opacity-50 cursor-not-allowed'
                                ]"
                                v-html="link.label"
                                :disabled="!link.url"
                            />
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Assignment Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeAssignModal">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto" @click.stop>
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
</template>