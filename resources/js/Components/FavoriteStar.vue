<script setup>
    // The star that pins a server to the top of the list. The same button
    // in all three server views, so switching layout does not move it into
    // a different shape.
    //
    // A star that is not set stays out of the way until the pointer is on
    // the card - forty faint stars would compete with everything the card
    // is actually for. On a phone there is no hover, so there it is always
    // faintly visible.
    defineProps({
        active: { type: Boolean, default: false },
        // 'sm' for the two card layouts, 'xs' for the dense compact rows.
        size: { type: String, default: 'sm' },
    });

    defineEmits(['toggle']);
</script>

<template>
    <button
        type="button"
        :title="active ? $t('Remove from favorites') : $t('Add to favorites')"
        :aria-label="active ? $t('Remove from favorites') : $t('Add to favorites')"
        :aria-pressed="active"
        :class="[
            'flex-shrink-0 rounded transition-all duration-200 focus:outline-none focus-visible:ring-1 focus-visible:ring-amber-300/70',
            active
                ? 'text-amber-300 hover:text-amber-200 drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)]'
                : 'text-gray-400 hover:text-amber-300 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 max-md:opacity-70',
        ]"
        @click.stop.prevent="$emit('toggle')"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            :fill="active ? 'currentColor' : 'none'"
            stroke="currentColor"
            stroke-width="2"
            :class="size === 'xs' ? 'w-4 h-4' : 'w-5 h-5'"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11c.09.2.28.35.5.36l5.52.44c.5.04.7.67.32 1l-4.2 3.6a.56.56 0 0 0-.19.59l1.28 5.39c.12.49-.41.88-.84.62l-4.73-2.89a.56.56 0 0 0-.58 0l-4.73 2.89c-.43.26-.96-.13-.84-.62l1.28-5.39a.56.56 0 0 0-.19-.59l-4.2-3.6c-.38-.33-.18-.96.32-1l5.52-.44c.22-.01.41-.16.5-.36l2.13-5.11Z"
            />
        </svg>
    </button>
</template>
