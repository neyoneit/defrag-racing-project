<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { t } from '@/utils/i18n';
import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';

const props = defineProps({
    show: Boolean,
    // Approved types, as the server localised them.
    workTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

// The value the picker sends when none of the offered types fit. It has to
// match MarketplaceController::OTHER.
const OTHER = '__other__';

const page = usePage();

// The author can hand us their own language along with the English, which
// saves an admin translating it later. Nothing is asked of an English reader.
const ownLocale = computed(() => page.props.locale || 'en');
const ownLanguage = computed(() => page.props.locales?.[ownLocale.value] || ownLocale.value);
const asksForTranslation = computed(() => ownLocale.value !== 'en');

const form = useForm({
    listing_type: 'request',
    work_type: props.workTypes[0]?.value || 'map',
    title: '',
    description: '',
    budget: '',
    custom_label: '',
    custom_description: '',
    custom_label_local: '',
});

const isOther = computed(() => form.work_type === OTHER);

const canSubmit = computed(() => {
    if (form.processing || !form.title.trim() || !form.description.trim()) {
        return false;
    }

    return !isOther.value || form.custom_label.trim().length > 0;
});

const submit = () => {
    form.post(route('marketplace.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const close = () => {
    if (!form.processing) {
        emit('close');
    }
};

// Errors from a previous attempt should not sit on a dialog that was closed
// and opened again.
watch(() => props.show, (open) => {
    if (open) {
        form.clearErrors();
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="close">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="close"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-xl border border-white/10 bg-gradient-to-br from-gray-900 to-gray-950 shadow-2xl">

                    <!-- Header -->
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $t('Create Listing') }}</h3>
                            <p class="mt-1 text-sm text-gray-400">{{ $t('Post a commission request or offer your services') }}</p>
                        </div>
                        <button
                            type="button"
                            @click="close"
                            class="rounded-lg p-1.5 text-gray-500 transition hover:bg-white/5 hover:text-white"
                            :aria-label="$t('Cancel')"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">

                        <!-- What is it -->
                        <div>
                            <label class="mb-2 block text-sm font-bold text-white">{{ $t('What are you posting?') }}</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="form.listing_type = 'request'"
                                    :class="form.listing_type === 'request' ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 hover:border-white/20'"
                                    class="rounded-xl border-2 p-3 text-left transition-all"
                                >
                                    <div class="font-bold text-white">{{ $t('Request') }}</div>
                                    <div class="text-xs text-gray-400">{{ $t('I\'m looking for someone to create something') }}</div>
                                </button>
                                <button
                                    type="button"
                                    @click="form.listing_type = 'offer'"
                                    :class="form.listing_type === 'offer' ? 'border-green-500 bg-green-500/10' : 'border-white/10 hover:border-white/20'"
                                    class="rounded-xl border-2 p-3 text-left transition-all"
                                >
                                    <div class="font-bold text-white">{{ $t('Offer') }}</div>
                                    <div class="text-xs text-gray-400">{{ $t('I\'m offering my creation services') }}</div>
                                </button>
                            </div>
                        </div>

                        <!-- Work type -->
                        <div>
                            <label class="mb-2 block text-sm font-bold text-white">{{ $t('Type of work') }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="wt in workTypes"
                                    :key="wt.value"
                                    type="button"
                                    @click="form.work_type = wt.value"
                                    :class="form.work_type === wt.value ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 hover:border-white/20'"
                                    class="rounded-xl border-2 p-3 text-left transition-all"
                                >
                                    <div class="text-sm font-bold text-white">{{ wt.label }}</div>
                                    <div class="text-xs text-gray-400">{{ wt.desc }}</div>
                                </button>

                                <button
                                    type="button"
                                    @click="form.work_type = OTHER"
                                    :class="isOther ? 'border-blue-500 bg-blue-500/10' : 'border-dashed border-white/15 hover:border-white/25'"
                                    class="rounded-xl border-2 p-3 text-left transition-all"
                                >
                                    <div class="text-sm font-bold text-white">{{ $t('Something else') }}</div>
                                    <div class="text-xs text-gray-400">{{ $t('Name it yourself') }}</div>
                                </button>
                            </div>
                            <div v-if="form.errors.work_type" class="mt-2 text-sm text-red-400">{{ form.errors.work_type }}</div>
                        </div>

                        <!-- The type they named themselves -->
                        <div v-if="isOther" class="space-y-3 rounded-xl border border-blue-500/20 bg-blue-500/5 p-4">
                            <p class="text-xs text-gray-400">
                                {{ $t('Your listing goes up straight away. An admin checks the wording of the new type afterwards.') }}
                            </p>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-white">{{ $t('Name of the work type (English)') }}</label>
                                <input
                                    v-model="form.custom_label"
                                    type="text"
                                    :placeholder="$t('e.g., Sound design')"
                                    maxlength="60"
                                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <div v-if="form.errors.custom_label" class="mt-1 text-sm text-red-400">{{ form.errors.custom_label }}</div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-white [&_span]:font-normal [&_span]:text-gray-500"
                                       v-html="$t('Short description <span>(optional)</span>')"></label>
                                <input
                                    v-model="form.custom_description"
                                    type="text"
                                    :placeholder="$t('e.g., Custom sounds and music')"
                                    maxlength="160"
                                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <div v-if="form.errors.custom_description" class="mt-1 text-sm text-red-400">{{ form.errors.custom_description }}</div>
                            </div>

                            <div v-if="asksForTranslation">
                                <label class="mb-1 block text-xs font-bold text-white [&_span]:font-normal [&_span]:text-gray-500"
                                       v-html="t('The same name in :language <span>(optional)</span>', { language: ownLanguage })"></label>
                                <input
                                    v-model="form.custom_label_local"
                                    type="text"
                                    maxlength="60"
                                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ $t('Leave it empty and everyone sees the English name.') }}</p>
                                <div v-if="form.errors.custom_label_local" class="mt-1 text-sm text-red-400">{{ form.errors.custom_label_local }}</div>
                            </div>
                        </div>

                        <!-- The listing itself -->
                        <EnglishOnlyNotice :compact="true" />

                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-white">{{ $t('Title') }}</label>
                            <input
                                v-model="form.title"
                                type="text"
                                :placeholder="$t('e.g., Looking for a strafe training map')"
                                maxlength="255"
                                class="w-full rounded-lg border border-white/10 bg-black/40 px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <div v-if="form.errors.title" class="mt-1 text-sm text-red-400">{{ form.errors.title }}</div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-white">{{ $t('Description') }}</label>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                :placeholder="$t('Describe what you need in detail...')"
                                maxlength="5000"
                                class="w-full resize-none rounded-lg border border-white/10 bg-black/40 px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            ></textarea>
                            <div class="mt-1 flex justify-between">
                                <div v-if="form.errors.description" class="text-sm text-red-400">{{ form.errors.description }}</div>
                                <div class="ml-auto text-xs text-gray-500">{{ form.description.length }}/5000</div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-white [&_span]:font-normal [&_span]:text-gray-500"
                                   v-html="$t('Budget <span>(optional)</span>')"></label>
                            <input
                                v-model="form.budget"
                                type="text"
                                :placeholder="$t('e.g., $50, negotiable, open to offers...')"
                                maxlength="255"
                                class="w-full rounded-lg border border-white/10 bg-black/40 px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <div v-if="form.errors.budget" class="mt-1 text-sm text-red-400">{{ form.errors.budget }}</div>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 border-t border-white/10 px-6 py-4">
                        <button
                            type="button"
                            @click="close"
                            class="rounded-lg border border-white/10 bg-gray-700/40 px-5 py-2.5 text-sm font-bold text-gray-300 transition hover:bg-gray-600/50 hover:text-white"
                        >
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="!canSubmit"
                            class="rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-2.5 text-sm font-bold text-white shadow-lg transition-all duration-300 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl disabled:from-gray-700 disabled:to-gray-700 disabled:text-gray-500"
                        >
                            {{ form.processing ? $t('Creating...') : $t('Create Listing') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
