<script setup>
/**
 * Confirmation step between picking a server and being dropped onto it.
 *
 * Shared by the map detail page and the Play Later list so both describe the
 * same thing the same way - the server you are about to join, whether anyone
 * is already on it, and the exact callvote that will be waiting on the
 * clipboard.
 */
import { ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    server: Object,
    mapName: String,
});

const emit = defineEmits(['close']);

const copied = ref(false);

const command = computed(() => `/cv map ${props.mapName}`);

const playerCount = computed(() => props.server?.online_players?.length || 0);

/**
 * Copy first, connect second. The clipboard write is asynchronous and
 * navigating away can cancel it, which would land you on the server with
 * nothing to paste - the one thing this exists to prevent.
 */
const connect = async () => {
    if (!props.server) {
        return;
    }

    try {
        await navigator.clipboard.writeText(command.value);
        copied.value = true;
    } catch (error) {
        console.error('Failed to copy the callvote command:', error);
    }

    window.location.href = `defrag://${props.server.ip}:${props.server.port}`;

    setTimeout(() => {
        copied.value = false;
        emit('close');
    }, 1500);
};
</script>

<template>
    <!-- Teleported: the page's transformed and overflow-hidden containers
         would otherwise clip this or trap it behind later sections. -->
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[200] flex items-center justify-center p-4" @click.self="emit('close')">
            <div class="fixed inset-0 bg-black/60"></div>

            <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-white mb-1">{{ $t('Connect and load the map?') }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ mapName }}</p>

                <div class="bg-white/5 border border-white/10 rounded-lg p-3 mb-4">
                    <span v-html="q3tohtml(server?.name)" class="font-semibold block"></span>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ server?.ip }}:{{ server?.port }}
                        <span v-if="server?.location"> - {{ server.location }}</span>
                    </div>
                    <div v-if="playerCount > 0" class="text-xs text-yellow-400 mt-2">
                        {{ playerCount }} {{ playerCount === 1 ? 'player is' : 'players are' }} on this server - your callvote may not pass.
                    </div>
                </div>

                <p class="text-gray-300 text-sm mb-2">
                    {{ $t('The game opens and this lands on your clipboard, ready to paste into the console:') }}
                </p>
                <code class="block bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm text-green-400 mb-4 break-all">{{ command }}</code>

                <div class="flex gap-3">
                    <button
                        @click="connect"
                        class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ copied ? 'Copied - connecting...' : 'Copy and connect' }}
                    </button>
                    <button
                        @click="emit('close')"
                        class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                    >
                        {{ $t('Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
