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
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <!-- Title -->
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Bundles</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                            </svg>
                            <span class="text-sm font-semibold">{{ categories.length }} categories</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-10">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Categories Sidebar -->
                <div class="lg:w-80 flex-shrink-0">
                    <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5 sticky top-6">
                        <div class="p-4 border-b border-white/5 bg-black/20">
                            <h3 class="text-lg font-bold text-white">Categories</h3>
                        </div>
                        <div class="divide-y divide-white/5">
                            <Link
                                v-for="category in categories"
                                :key="category.id"
                                :href="getUrl(category)"
                                @click="selectCategory(category)"
                                class="group flex items-center justify-between px-4 py-3 transition-all duration-300 hover:bg-white/5"
                                :class="{
                                    'bg-white/10 border-l-4 border-white/50': currentCategory?.id == category.id,
                                    'hover:border-l-4 hover:border-white/20': currentCategory?.id != category.id
                                }">
                                <span class="font-semibold transition-colors" :class="currentCategory?.id == category.id ? 'text-white' : 'text-gray-400 group-hover:text-white'">
                                    {{ category.name }}
                                </span>
                                <span class="text-xs font-bold px-2 py-1 rounded-full transition-all"
                                    :class="currentCategory?.id == category.id ? 'bg-white/20 text-white' : 'bg-white/5 text-gray-500 group-hover:bg-white/10 group-hover:text-gray-300'">
                                    {{ category.bundles.length }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Bundles Content -->
                <div class="flex-1">
                    <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">
                        <!-- Category Header -->
                        <div v-if="currentCategory" class="p-6 border-b border-white/5 bg-black/20">
                            <h2 class="text-2xl font-black text-white mb-1">{{ currentCategory.name }}</h2>
                            <p class="text-sm text-gray-400">{{ currentCategory.bundles.length }} bundle{{ currentCategory.bundles.length !== 1 ? 's' : '' }} available</p>
                        </div>

                        <!-- Bundles List -->
                        <div v-if="currentCategory && currentCategory.bundles.length > 0" class="p-4">
                            <div class="grid gap-4">
                                <Bundle v-for="bundle in currentCategory?.bundles" :key="bundle.id" :bundle="bundle" />
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="p-12 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto text-gray-600 mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">No bundles available</h3>
                            <p class="text-gray-500">This category doesn't have any bundles yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
