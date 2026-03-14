<script setup>
    import { Link } from '@inertiajs/vue3';

    defineProps({
        tabs: Array,
        activeTab: String,
        onClick: Function
    });
</script>

<template>
    <div class="border-b border-white/10">
        <ul class="flex flex-wrap -mb-px overflow-x-auto">
            <li v-for="tab in tabs" :key="tab.name">
                <Link
                    v-if="tab.link && tab.condition"
                    :href="route(tab.route, tab.params)"
                    :class="{
                        'text-blue-400 border-blue-400': activeTab === tab.name,
                        'text-gray-400 border-transparent hover:text-gray-300 hover:border-gray-300': activeTab !== tab.name
                    }"
                    class="inline-block px-4 py-3 border-b-2 font-medium transition-colors whitespace-nowrap"
                >
                    {{ tab.label }}
                </Link>
            </li>
        </ul>
    </div>
</template>