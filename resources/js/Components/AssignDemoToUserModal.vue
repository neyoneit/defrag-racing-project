<script setup>
    import { ref, watch, computed } from 'vue';
    import axios from 'axios';
    import { t } from '@/utils/i18n';

    // Attributing a freestyle or trick demo to an account. Those demos hang off
    // no record, so nothing else on the site can say whose they are, and the
    // alias resolver only reaches one in four.
    const props = defineProps({
        show: { type: Boolean, default: false },
        demo: { type: Object, default: null },
    });

    const emit = defineEmits(['close', 'assigned']);

    const query = ref('');
    const results = ref([]);
    const searching = ref(false);
    const saving = ref(false);
    const error = ref(null);
    const applyToNick = ref(false);
    const nick = computed(() => props.demo?.player_name || '');

    let searchTimer = null;

    watch(query, (value) => {
        clearTimeout(searchTimer);
        error.value = null;

        if (value.trim().length < 2) {
            results.value = [];
            return;
        }

        searchTimer = setTimeout(async () => {
            searching.value = true;
            try {
                const { data } = await axios.get('/demos/search-players', { params: { q: value.trim() } });
                results.value = data;
            } catch (e) {
                error.value = e.response?.status === 403
                    ? t('Only staff can do this.')
                    : t('Could not search accounts.');
            } finally {
                searching.value = false;
            }
        }, 250);
    });

    watch(() => props.show, (open) => {
        if (open) {
            query.value = '';
            results.value = [];
            error.value = null;
            applyToNick.value = false;
        }
    });

    const assign = async (userId) => {
        if (! props.demo || saving.value) return;

        saving.value = true;
        error.value = null;

        try {
            const { data } = await axios.post(`/demos/${props.demo.demo_id || props.demo.id}/assign-user`, {
                user_id: userId,
                apply_to_nick: userId ? applyToNick.value : false,
            });
            emit('assigned', data);
            emit('close');
        } catch (e) {
            error.value = e.response?.data?.message || t('Could not save that.');
        } finally {
            saving.value = false;
        }
    };
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="emit('close')">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-md bg-gray-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10">
                <h3 class="font-bold text-white">{{ $t('Attribute this demo to an account') }}</h3>
                <p class="text-xs text-gray-400 mt-1">
                    <span class="text-gray-300">{{ demo?.demo_label || demo?.demo?.original_filename }}</span>
                    <span v-if="nick"> {{ $t('- recorded as') }} <span class="text-gray-300">{{ nick }}</span></span>
                </p>
            </div>

            <div class="p-4 space-y-3">
                <input
                    v-model="query"
                    type="text"
                    :placeholder="$t('Search accounts by name...')"
                    class="w-full bg-gray-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-teal-500/50 focus:ring-0"
                    autofocus
                />

                <label v-if="nick" class="flex items-start gap-2 text-xs text-gray-300 cursor-pointer">
                    <input v-model="applyToNick" type="checkbox" class="mt-0.5 rounded bg-gray-800 border-white/20 text-teal-500 focus:ring-0" />
                    <span>
                        {{ $t('Do the same for every other demo recorded as') }} <span class="font-semibold text-white">{{ nick }}</span>.
                        <span class="text-gray-500">{{ $t('Exact nick only, so it never picks up a similar one.') }}</span>
                    </span>
                </label>

                <div v-if="error" class="text-xs text-red-400">{{ error }}</div>
                <div v-if="searching" class="text-xs text-gray-500">{{ $t('Searching...') }}</div>

                <div v-if="results.length" class="max-h-64 overflow-y-auto divide-y divide-white/[0.04] border border-white/10 rounded-lg">
                    <button
                        v-for="user in results"
                        :key="user.id"
                        @click="assign(user.id)"
                        :disabled="saving"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-white/[0.04] transition-colors disabled:opacity-50"
                    >
                        <img
                            class="h-6 w-6 rounded-full object-cover border border-gray-600"
                            :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'"
                            :alt="user.plain_name"
                        />
                        <span class="text-sm text-gray-200 truncate">{{ user.plain_name }}</span>
                        <span class="ml-auto text-[10px] text-gray-500">#{{ user.id }}</span>
                    </button>
                </div>

                <div v-else-if="query.trim().length >= 2 && ! searching" class="text-xs text-gray-500">{{ $t('No account matches that.') }}</div>
            </div>

            <div class="px-4 py-3 border-t border-white/10 flex items-center justify-between gap-2">
                <button
                    v-if="demo?.assigned_user_id"
                    @click="assign(null)"
                    :disabled="saving"
                    class="text-xs text-red-400 hover:text-red-300 disabled:opacity-50"
                >{{ $t('Remove the current assignment') }}</button>
                <span v-else></span>
                <button @click="emit('close')" class="px-3 py-1.5 rounded-lg text-sm bg-gray-800 text-gray-300 hover:bg-gray-700">{{ $t('Cancel') }}</button>
            </div>
        </div>
    </div>
</template>
