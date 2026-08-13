<script setup>
    import { ref, onMounted } from 'vue';
    import axios from 'axios';
    import { t, currentLocale } from '@/utils/i18n';

    const tokens = ref([]);
    const loading = ref(true);
    const error = ref(null);

    // Fresh token returned by POST /user/launcher-tokens; plaintext is shown
    // once inline under the form, then the user is expected to copy it into
    // the launcher. We clear it as soon as the next action happens.
    const freshToken = ref(null);
    const copied = ref(false);

    const label = ref('');
    const creating = ref(false);
    const showForm = ref(false);
    const createError = ref(null);

    const fetchTokens = async () => {
        loading.value = true;
        try {
            const { data } = await axios.get(route('launcher-tokens.index'));
            tokens.value = data.tokens ?? [];
        } catch (e) {
            error.value = t('Failed to load launcher tokens');
        } finally {
            loading.value = false;
        }
    };

    onMounted(fetchTokens);

    const createToken = async () => {
        if (! label.value.trim()) return;
        creating.value = true;
        createError.value = null;
        try {
            const { data } = await axios.post(route('launcher-tokens.store'), { label: label.value.trim() });
            freshToken.value = data;
            tokens.value.unshift({
                id: data.id,
                label: data.label,
                created_at: data.created_at,
                last_used_at: null,
            });
            label.value = '';
            showForm.value = false;
        } catch (e) {
            createError.value = e.response?.data?.message || t('Failed to create token');
        } finally {
            creating.value = false;
        }
    };

    const revoke = async (tokenId) => {
        if (! confirm(t('Revoke this token?') + ' ' + t('The launcher using it will stop being able to upload until you paste a new one.'))) {
            return;
        }
        try {
            await axios.delete(route('launcher-tokens.destroy', tokenId));
            tokens.value = tokens.value.filter(t => t.id !== tokenId);
            if (freshToken.value?.id === tokenId) freshToken.value = null;
        } catch (e) {
            alert(t('Failed to revoke token'));
        }
    };

    const copyToken = async () => {
        try {
            await navigator.clipboard.writeText(freshToken.value.plain_text_token);
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        } catch {}
    };

    const formatDate = (iso) => {
        if (! iso) return '-';
        // currentLocale(), not the browser default: the sibling ApiTokensForm
        // does the same, and two panels in one settings page should not print
        // their dates in two different languages.
        return new Date(iso).toLocaleString(currentLocale());
    };

    // Parses our launcher's User-Agent string into a short label.
    // Format we send from the launcher: "defrag-launcher/0.1.9 (windows)".
    // Anything else (curl, postman, python-requests) we show verbatim so
    // the user notices "that doesn't look like the launcher".
    const formatAgent = (ua) => {
        if (! ua) return null;
        const m = ua.match(/^defrag-launcher\/(\S+)\s+\(([^)]+)\)/i);
        if (m) return `Launcher ${m[1]} - ${m[2]}`;
        return ua.length > 60 ? ua.slice(0, 60) + '...' : ua;
    };
</script>

<template>
    <div class="p-6 space-y-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ $t('Launcher tokens') }}</h3>
                <p class="mt-1 text-sm text-gray-400 max-w-2xl leading-relaxed [&_a]:text-blue-400 [&_a:hover]:underline [&_code]:text-xs [&_code]:bg-black/40 [&_code]:px-1 [&_code]:rounded"
                   v-html="$t('Personal access tokens for the <a href=https://github.com/Defrag-racing/defrag-racing-launcher target=_blank>Defrag Racing Launcher</a>. The launcher uses them to automatically back up demos from your <code>defrag/demos/</code> folder to your account. Revoke a token anytime to disconnect a device.')"></p>
            </div>
            <button
                v-if="!showForm"
                type="button"
                @click="showForm = true"
                class="px-3 py-1.5 text-sm rounded bg-blue-500/20 hover:bg-blue-500/30 text-blue-200 font-semibold"
            >{{ $t('+ New token') }}</button>
        </div>

        <!-- Create form -->
        <form v-if="showForm" @submit.prevent="createToken" class="flex gap-2 items-start bg-black/30 border border-white/[0.06] rounded-lg p-3">
            <div class="flex-1">
                <input
                    v-model="label"
                    type="text"
                    :placeholder="$t('Token label (e.g. Home PC, Laptop)')"
                    maxlength="50"
                    class="w-full bg-black/60 border border-white/10 rounded px-3 py-2 text-sm text-white placeholder:text-gray-500"
                />
                <p v-if="createError" class="mt-1 text-xs text-red-400">{{ createError }}</p>
                <p v-else class="mt-1 text-xs text-gray-500">{{ $t('A label so you remember which device uses this token.') }}</p>
            </div>
            <button
                type="submit"
                :disabled="creating || !label.trim()"
                class="px-4 py-2 rounded bg-blue-500/30 hover:bg-blue-500/40 disabled:opacity-50 text-blue-100 text-sm font-semibold"
            >{{ creating ? $t('Generating…') : $t('Generate') }}</button>
            <button
                type="button"
                @click="showForm = false; label = ''; createError = null;"
                class="px-4 py-2 rounded bg-white/5 hover:bg-white/10 text-gray-300 text-sm"
            >{{ $t('Cancel') }}</button>
        </form>

        <!-- Fresh token (one-time display) -->
        <div v-if="freshToken" class="border border-emerald-500/40 bg-emerald-500/5 rounded-lg p-4 space-y-3">
            <div class="flex items-center gap-2 text-emerald-300 font-semibold text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ $t('Token ":label" created — copy it now', { label: freshToken.label }) }}
            </div>
            <p class="text-xs text-gray-400">
                {{ $t('This is the only time we will show the token in plaintext. Paste it into the launcher\'s settings. If you lose it, revoke this one and generate a new one.') }}
            </p>
            <div class="flex gap-2">
                <input
                    type="text"
                    readonly
                    :value="freshToken.plain_text_token"
                    class="flex-1 bg-black/60 border border-white/10 rounded px-3 py-2 text-sm font-mono text-white select-all"
                    @focus="$event.target.select()"
                />
                <button
                    type="button"
                    @click="copyToken"
                    class="px-4 py-2 rounded bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 text-sm font-semibold whitespace-nowrap"
                >{{ copied ? $t('Copied!') : $t('Copy') }}</button>
            </div>
        </div>

        <!-- Existing tokens -->
        <div v-if="loading" class="text-sm text-gray-500 py-4">{{ $t('Loading tokens…') }}</div>
        <div v-else-if="error" class="text-sm text-red-400 py-4">{{ error }}</div>
        <div v-else-if="tokens.length" class="bg-black/30 border border-white/[0.06] rounded-lg divide-y divide-white/[0.04]">
            <div v-for="t in tokens" :key="t.id" class="px-4 py-3 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-white font-medium truncate">{{ t.label }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $t('Created :created · Last used :used', { created: formatDate(t.created_at), used: formatDate(t.last_used_at) }) }}
                    </div>
                    <div v-if="t.last_used_ip || t.last_used_user_agent" class="text-xs text-gray-500 mt-0.5">
                        <span v-if="formatAgent(t.last_used_user_agent)" class="text-gray-400">{{ formatAgent(t.last_used_user_agent) }}</span>
                        <span v-if="t.last_used_ip && formatAgent(t.last_used_user_agent)"> · </span>
                        <span v-if="t.last_used_ip" class="font-mono">{{ t.last_used_ip }}</span>
                    </div>
                </div>
                <button
                    type="button"
                    @click="revoke(t.id)"
                    class="px-3 py-1.5 text-xs rounded bg-red-500/15 hover:bg-red-500/25 text-red-300 flex-shrink-0"
                >{{ $t('Revoke') }}</button>
            </div>
        </div>
        <div v-else class="text-sm text-gray-500 py-4 text-center border border-dashed border-white/[0.06] rounded-lg">
            {{ $t('No tokens yet. Generate one to enable automatic demo backup from the launcher.') }}
        </div>
    </div>
</template>
