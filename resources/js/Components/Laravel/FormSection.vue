<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const slots = useSlots();
const hasActions = computed(() => !! slots.actions);
const hasTopActions = computed(() => !! slots.topActions);
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
            <form @submit.prevent="$emit('submitted')">
                <div
                    class="px-4 py-4 bg-grayop-800 sm:p-4 shadow sm:rounded-md"
                >
                    <div v-if="hasTopActions" class="flex items-center justify-end mb-4">
                        <slot name="topActions" />
                    </div>
                    <div class="grid grid-cols-6 gap-3">
                        <slot name="form" />
                    </div>
                </div>

                <div v-if="hasActions" class="flex items-center justify-end px-4 py-2 bg-grayop-800 text-end sm:px-4 shadow sm:rounded-bl-md sm:rounded-br-md">
                    <slot name="actions" />
                </div>
            </form>
        </div>
    </div>
</template>
