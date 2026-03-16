<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/70" @click="$emit('close')"></div>

            <!-- Modal -->
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-black/80 rounded-xl shadow-2xl max-w-2xl w-full border border-white/10">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-white/10">
                        <h3 class="text-xl font-bold text-white">Report Demo</h3>
                        <p class="text-sm text-gray-400 mt-1">{{ demo?.processed_filename || demo?.original_filename }}</p>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submitReport" class="px-6 py-4 space-y-4">
                        <!-- Report Type Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Report Type</label>
                            <div class="space-y-2">
                                <!-- Wrong Assignment -->
                                <label class="flex items-start gap-3 bg-white/5 p-4 rounded-lg cursor-pointer hover:bg-white/10 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="wrong_assignment"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">Wrong Assignment</div>
                                        <div class="text-sm text-gray-400">This demo is assigned to the wrong player/record</div>
                                    </div>
                                </label>

                                <!-- Bad Demo -->
                                <label class="flex items-start gap-3 bg-white/5 p-4 rounded-lg cursor-pointer hover:bg-white/10 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="bad_demo"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">Bad Demo</div>
                                        <div class="text-sm text-gray-400">Demo is corrupted, fake, spam, or inappropriate</div>
                                    </div>
                                </label>

                                <!-- False Flag -->
                                <label class="flex items-start gap-3 bg-white/5 p-4 rounded-lg cursor-pointer hover:bg-white/10 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="false_flag"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">False Flag</div>
                                        <div class="text-sm text-gray-400">This record/demo was incorrectly flagged for a validity issue</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Reason Selection -->
                        <div v-if="form.report_type">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                Reason <span class="text-red-400">*</span>
                            </label>
                            <select
                                v-model="form.reason_type"
                                class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:outline-none [&>option]:text-black [&>option]:bg-white"
                                required
                            >
                                <option value="" class="text-gray-500">Select a reason...</option>
                                <option
                                    v-for="(label, value) in getReasonOptions()"
                                    :key="value"
                                    :value="value"
                                    class="text-black bg-white"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Additional Details -->
                        <div v-if="form.report_type">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                Additional Details (Optional)
                            </label>
                            <textarea
                                v-model="form.reason_details"
                                rows="3"
                                maxlength="1000"
                                placeholder="Provide any additional information that might help..."
                                class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:outline-none resize-none"
                            ></textarea>
                            <div class="text-xs text-gray-500 mt-1">{{ form.reason_details?.length || 0 }}/1000</div>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-white/10 flex justify-end gap-3">
                        <button
                            @click="$emit('close')"
                            type="button"
                            class="px-6 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitReport"
                            type="button"
                            :disabled="!canSubmit || submitting"
                            class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ submitting ? 'Submitting...' : 'Submit Report' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    demo: Object,
});

const emit = defineEmits(['close']);

const form = ref({
    report_type: '',
    reason_type: '',
    reason_details: '',
    suggested_record_id: null,
});

const recordSearch = ref('');
const timeFilter = ref('');
const searchResults = ref([]);
const selectedRecord = ref(null);
const submitting = ref(false);
const searching = ref(false);
let searchTimeout = null;

const WRONG_ASSIGNMENT_REASONS = {
    'wrong_player': 'Wrong player - name doesn\'t match',
    'wrong_map': 'Wrong map',
    'wrong_time': 'Wrong time',
    'duplicate': 'Duplicate demo',
    'cheated': 'Cheated/Modified demo',
    'other': 'Other',
};

const BAD_DEMO_REASONS = {
    'corrupted': 'Corrupted demo file',
    'fake': 'Fake/modified demo',
    'spam': 'Spam upload',
    'inappropriate': 'Inappropriate content',
    'duplicate': 'Duplicate of existing demo',
    'other': 'Other',
};

const FALSE_FLAG_REASONS = {
    'legitimate': 'Record is legitimate - flag is incorrect',
    'wrong_flag': 'Wrong flag type was applied',
    'resolved': 'Issue was already resolved',
    'other': 'Other',
};

const getReasonOptions = () => {
    if (form.value.report_type === 'wrong_assignment') return WRONG_ASSIGNMENT_REASONS;
    if (form.value.report_type === 'bad_demo') return BAD_DEMO_REASONS;
    if (form.value.report_type === 'false_flag') return FALSE_FLAG_REASONS;
    return {};
};

const canSubmit = computed(() => {
    if (!form.value.report_type || !form.value.reason_type) return false;
    return true;
});

const searchRecords = () => {
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    if (recordSearch.value.length < 2) {
        searchResults.value = [];
        searching.value = false;
        return;
    }

    searching.value = true;

    // Debounce: wait 300ms after user stops typing
    searchTimeout = setTimeout(() => {
        const searchQuery = recordSearch.value;
        const timeFilterValue = timeFilter.value;
        console.log('Searching for:', searchQuery, 'with time filter:', timeFilterValue);

        // Make API call to search records
        const params = { q: searchQuery };
        if (timeFilterValue && timeFilterValue.trim() !== '') {
            // Parse time input - supports multiple formats
            const timeInMs = parseTimeInput(timeFilterValue);
            if (timeInMs !== null) {
                params.time = timeInMs;
                console.log('Parsed time filter:', timeFilterValue, '→', timeInMs, 'ms');
            }
        }

        axios.get('/api/records/search', {
            params: params,
            timeout: 10000 // 10 second timeout
        }).then(response => {
            console.log('Search results:', response.data);
            searchResults.value = response.data;
            searching.value = false;
        }).catch(error => {
            console.error('Error searching records:', error);
            console.error('Error details:', error.response?.data || error.message);
            searchResults.value = [];
            searching.value = false;
        });
    }, 300);
};

const selectRecord = (record) => {
    selectedRecord.value = record;
    form.value.suggested_record_id = record.id;
    searchResults.value = [];
    recordSearch.value = '';
};

const submitReport = () => {
    if (!canSubmit.value) return;

    submitting.value = true;

    router.post(route('demos.report', props.demo.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            // Reset form
            form.value = {
                report_type: '',
                reason_type: '',
                reason_details: '',
                suggested_record_id: null,
            };
            selectedRecord.value = null;
            recordSearch.value = '';
            timeFilter.value = '';
        },
        onFinish: () => {
            submitting.value = false;
        }
    });
};

/**
 * Parse time input in various formats and convert to milliseconds
 * Supports:
 * - Plain number (treated as seconds): "25" → 25000ms
 * - MM:SS format: "1:30" → 90000ms
 * - MM:SS.mmm format: "1:30.500" → 90500ms
 * - SS.mmm format: "30.500" → 30500ms
 */
const parseTimeInput = (input) => {
    if (!input || input.trim() === '') return null;

    const trimmed = input.trim();

    // Check for MM:SS.mmm or MM:SS format
    if (trimmed.includes(':')) {
        const parts = trimmed.split(':');
        if (parts.length === 2) {
            const minutes = parseInt(parts[0]);
            const secondsPart = parts[1];

            // Check if seconds part has milliseconds (SS.mmm)
            if (secondsPart.includes('.')) {
                const [seconds, milliseconds] = secondsPart.split('.');
                return (minutes * 60000) + (parseInt(seconds) * 1000) + parseInt(milliseconds.padEnd(3, '0'));
            } else {
                // Just MM:SS
                const seconds = parseFloat(secondsPart);
                return (minutes * 60000) + (seconds * 1000);
            }
        }
    }

    // Check for SS.mmm format (seconds with decimal)
    if (trimmed.includes('.')) {
        const [seconds, milliseconds] = trimmed.split('.');
        return (parseInt(seconds) * 1000) + parseInt(milliseconds.padEnd(3, '0'));
    }

    // Plain number - treat as seconds
    const num = parseFloat(trimmed);
    if (!isNaN(num)) {
        return Math.round(num * 1000);
    }

    return null;
};

const formatTime = (ms) => {
    if (!ms) return '-';
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const milliseconds = ms % 1000;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${milliseconds.toString().padStart(3, '0')}`;
};
</script>
