<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-white/10 rounded-lg transition-all'
        : 'inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
