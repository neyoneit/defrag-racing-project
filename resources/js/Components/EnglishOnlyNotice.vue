<script setup>
    /**
     * One line asking that whatever is being written here be written in
     * English. It belongs above any field whose text other players will read:
     * a wish, a wiki page, a tournament comment, a map list, a model
     * description. Not above a note only an admin sees - there is nothing to
     * gain by asking two Czechs to write to each other in English.
     *
     * The text itself goes through $t(), so it arrives in the reader's own
     * language. Telling a Polish player in Polish that the site's shared
     * content is written in English is the whole point: the ones who need
     * asking are exactly the ones not reading English by choice.
     *
     * Deliberately quiet - a hint, not a warning. Nothing is rejected for
     * being in another language; language detection is unreliable on the
     * short, jargon-heavy text people write here ("rl boost on the 3rd
     * floor" is barely English either), and refusing a real contribution
     * over a bad guess costs more than a moderator tidying up later.
     */
    defineProps({
        /** Sits under a label by default; pass false when it leads a form. */
        compact: {
            type: Boolean,
            default: true,
        },
    });
</script>

<template>
    <p
        class="flex items-start gap-1.5 text-xs text-gray-500"
        :class="compact ? 'mb-1' : 'mb-3'"
    >
        <svg class="w-3.5 h-3.5 mt-px flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.949 8.949 0 0 0 12 21Zm0-18c-2.485 0-4.5 4.03-4.5 9s2.015 9 4.5 9 4.5-4.03 4.5-9-2.015-9-4.5-9ZM3.6 9h16.8M3.6 15h16.8" />
        </svg>
        <span>{{ $t('Please write this in English so the whole community can read it.') }}</span>
    </p>
</template>
