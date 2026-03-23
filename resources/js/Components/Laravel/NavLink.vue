<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600/30 rounded-lg transition-all border border-blue-500/40'
        : 'inline-flex items-center px-3 py-1.5 text-sm font-medium text-white hover:text-white hover:bg-white/10 rounded-lg transition-all border border-transparent';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
