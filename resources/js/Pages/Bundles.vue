<script setup>
    import { Head } from '@inertiajs/vue3';
    import Bundle from '@/Components/Bundle.vue';
    import { onMounted, ref } from 'vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        categories: Array,
        id: Number
    });

    const currentCategory = ref(null);

    const selectCategory = (category) => {
        currentCategory.value = category
    }

    const getUrl = (category) => {
        let slug = category.name.toLowerCase().trim();
        slug = slug.replaceAll(' ', '-');

        let url = `/bundles/${category.id}/${slug}`

        return url
    }

    const findCategory = () => {
        if (isNaN(props.id ) || props.id == 0 || props.id == -1) {
            return -1;
        }

        for(let i = 0; i < props.categories.length; i++) {
            if (props.categories[i].id == props.id) {
                return i;
            }
        }

        return -1;
    }

    onMounted(() => {
        if (props.categories.length > 0) {
            let category = findCategory();

            if (category == -1) {
                currentCategory.value = props.categories[0]
            } else {
                currentCategory.value = props.categories[category]
            }

        }
    })

</script>

<template>
    <div class="min-h-screen">
        <Head title="Bundles" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div>
                    <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Bundles</h1>
                    <p class="text-sm text-gray-400">{{ categories.length }} categories</p>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -22rem;">
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Categories Sidebar -->
                <div class="lg:w-56 flex-shrink-0">
                    <div class="bg-black/40 rounded-xl overflow-hidden border border-cyan-500/10 sticky top-[120px]">
                        <Link
                            v-for="category in categories"
                            :key="category.id"
                            :href="getUrl(category)"
                            @click="selectCategory(category)"
                            class="group flex items-center justify-between px-3 py-2.5 transition-all hover:bg-cyan-500/5 border-l-2"
                            :class="currentCategory?.id == category.id
                                ? 'bg-cyan-500/10 border-cyan-400'
                                : 'border-transparent hover:border-cyan-500/20'">
                            <span class="text-sm font-medium truncate" :class="currentCategory?.id == category.id ? 'text-cyan-300' : 'text-gray-400 group-hover:text-white'">
                                {{ category.name }}
                            </span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-2 flex-shrink-0"
                                :class="currentCategory?.id == category.id ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-600'">
                                {{ category.bundles.length }}
                            </span>
                        </Link>
                    </div>
                </div>

                <!-- Bundles Content -->
                <div class="flex-1">
                    <!-- Category Header -->
                    <div v-if="currentCategory" class="flex items-center gap-3 mb-3">
                        <h2 class="text-lg font-black text-white">{{ currentCategory.name }}</h2>
                        <span class="text-xs text-gray-500">{{ currentCategory.bundles.length }} bundle{{ currentCategory.bundles.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <!-- Bundles Grid -->
                    <div v-if="currentCategory && currentCategory.bundles.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <Bundle v-for="bundle in currentCategory?.bundles" :key="bundle.id" :bundle="bundle" />
                    </div>

                    <!-- Empty State -->
                    <div v-else class="bg-black/40 rounded-xl border border-white/5 p-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-gray-600 mb-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        <p class="text-sm text-gray-500">No bundles in this category yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
