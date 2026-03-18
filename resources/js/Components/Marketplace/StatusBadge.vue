<script setup>
const props = defineProps({
    status: String,
});

const statusConfig = {
    open: { label: 'Open', class: 'text-green-400 bg-green-500/20 border-green-500/30' },
    in_progress: { label: 'In Progress', class: 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30' },
    completed: { label: 'Completed', class: 'text-blue-400 bg-blue-500/20 border-blue-500/30' },
    cancelled: { label: 'Cancelled', class: 'text-gray-400 bg-gray-500/20 border-gray-500/30' },
};

const config = statusConfig[props.status] || statusConfig.open;
</script>

<template>
    <span :class="`px-2 py-1 text-xs font-semibold rounded-full border ${config.class}`">
        {{ config.label }}
    </span>
</template>
