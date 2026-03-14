<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tabs: Array,
        activeTab: String,
        onClick: Function,
        condition: {
            type: Boolean,
            default: true
        }
    });

    const toggleTab = (tab) => {
        props.onClick(tab.name);
    };
</script>

<template>
    <div class="border-b border-white/10" v-if="condition">
        <ul class="flex flex-wrap -mb-px">
            <li v-for="tab in tabs" :key="tab.name">
                <Link :href="route(tab.route, tab.params)" v-if="tab.link">
                    <button
                        :class="{
                            'text-blue-400 border-blue-400': activeTab === tab.name,
                            'text-gray-400 border-transparent hover:text-gray-300 hover:border-gray-300': activeTab !== tab.name
                        }"
                        class="inline-block px-6 py-3 border-b-2 font-medium transition-colors"
                    >
                        {{ tab.label }}
                    </button>
                </Link>

                <button
                    v-else
                    @click="toggleTab(tab)"
                    :class="{
                        'text-blue-400 border-blue-400': activeTab === tab.name,
                        'text-gray-400 border-transparent hover:text-gray-300 hover:border-gray-300': activeTab !== tab.name
                    }"
                    class="inline-block px-6 py-3 border-b-2 font-medium transition-colors"
                >
                    {{ tab.label }}
                </button>
            </li>
        </ul>
    </div>
</template>