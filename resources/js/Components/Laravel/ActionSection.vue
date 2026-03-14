<script setup>
import SectionTitle from './SectionTitle.vue';
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-4">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-3 md:mt-0 md:col-span-2">
            <div class="px-4 py-4 sm:p-4 bg-white dark:bg-grayop-800 shadow sm:rounded-lg">
                <slot name="content" />
            </div>
        </div>
    </div>
</template>
