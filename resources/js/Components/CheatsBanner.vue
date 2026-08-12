<script setup>
// Shown when a server reports sv_cheats 1.
//
// Deliberately across the full width of the card rather than a small corner
// badge: a time set where cheats are enabled is worth nothing, and somebody
// scanning the list should not have to hunt for that.
//
// Renders nothing for false OR null. Null means the server never told us -
// only an engine new enough to put sv_cheats in its getdfstatus reply does,
// because the cvar is systeminfo and never reaches a server browser otherwise.
// Silence there must not be read as "cheats are off".
defineProps({
    cheats: { type: Boolean, default: null },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        v-if="cheats === true"
        :class="[
            'absolute top-0 inset-x-0 z-20 flex items-center justify-center gap-2',
            'bg-red-600/90 backdrop-blur-sm border-b border-red-300/40 pointer-events-none',
            compact ? 'py-0.5' : 'py-1.5',
        ]"
        :title="$t('This server runs with sv_cheats enabled - times set here do not count')"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
             :class="['text-white flex-shrink-0', compact ? 'w-3 h-3' : 'w-4 h-4']">
            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
        </svg>
        <span :class="['font-black uppercase text-white tracking-widest', compact ? 'text-[10px]' : 'text-xs']">
            {{ $t('Cheats enabled') }}
        </span>
    </div>
</template>
