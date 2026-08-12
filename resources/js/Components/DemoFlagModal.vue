<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/70" @click="$emit('close')"></div>

            <!-- Modal -->
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-black/80 rounded-xl shadow-2xl max-w-md w-full border border-white/10">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-white/10">
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                            {{ $t('Flag Record') }}
                        </h3>
                        <p class="text-sm text-gray-400 mt-1">{{ $t('Select the validity issue for this record') }}</p>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4 space-y-4">
                        <!-- Flag chips -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in FLAG_TYPES"
                                :key="key"
                                type="button"
                                @click="selectedFlag = key"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium border transition-all"
                                :class="selectedFlag === key
                                    ? 'bg-red-500/30 text-red-300 border-red-500/70 ring-1 ring-red-500/50'
                                    : 'bg-white/5 text-gray-300 border-white/10 hover:bg-white/10 hover:text-white'"
                            >
                                {{ label }}
                            </button>
                        </div>

                        <!-- Optional note -->
                        <div v-if="selectedFlag">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                {{ $t('Note (optional)') }}
                            </label>
                            <textarea
                                v-model="note"
                                rows="2"
                                maxlength="500"
                                :placeholder="$t('Any additional details...')"
                                class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-red-500 focus:outline-none resize-none text-sm"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-white/10 flex justify-end gap-3">
                        <button
                            @click="$emit('close')"
                            type="button"
                            class="px-6 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg transition-colors"
                        >
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            @click="submitFlag"
                            type="button"
                            :disabled="!selectedFlag || submitting"
                            class="px-6 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ submitting ? $t('Submitting...') : $t('Submit Flag') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { t } from '@/utils/i18n';

const props = defineProps({
    show: Boolean,
    recordId: [Number, null],
    demoId: [Number, null],
});

const emit = defineEmits(['close']);

// The object key is the cvar, and that is what gets submitted - it is never
// translated. The label beside it is the plain-words version for whoever is
// clicking, so it is. The exceptions are the labels that ARE the cvar or the
// console command (timescale, fps, maxfps, pmove, msec, upmove, +left/+right):
// a player looks those up in the console, not in a dictionary.
//
// A computed, not a plain object: a plain one is built once at module load
// and would keep whichever language was loaded then.
const FLAG_TYPES = computed(() => ({
    'sv_cheats': t('cheats'),
    'tool_assisted': 'TAS',
    'client_finish': t('no finish'),
    'timescale': 'timescale',
    'g_speed': t('speed'),
    'g_gravity': t('gravity'),
    'sv_fps': 'fps',
    'com_maxfps': 'maxfps',
    'pmove_fixed': 'pmove',
    'pmove_msec': 'msec',
    'df_mp_interferenceoff': t('interference'),
    'left_right': '+left/+right',
    'scripts': t('scripts'),
    'upmove': 'upmove',
    'replay_cheat': t('replay'),
    'engine_cheat': t('engine'),
    'other': t('other'),
}));

const selectedFlag = ref(null);
const note = ref('');
const submitting = ref(false);

const submitFlag = () => {
    if (!selectedFlag.value) return;

    submitting.value = true;

    router.post(route('flags.store'), {
        record_id: props.recordId,
        demo_id: props.demoId,
        flag_type: selectedFlag.value,
        note: note.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            selectedFlag.value = null;
            note.value = '';
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};
</script>
