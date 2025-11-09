
<script setup>
    import { ref, watchEffect, onMounted, onUnmounted } from 'vue';
    import { router, Link } from '@inertiajs/vue3';

    const props = defineProps({
        last_page: Number,
        current_page: Number,
        link: String,
        pageName: {
            type: String,
            default: 'page'
        },
        only: {
            type: Array,
            default: () => ['userDemos', 'publicDemos']
        }
    });

    const newPage = ref(props.current_page);

    const before = ref([]);
    const after = ref([]);

    const screenWidth = ref(window.innerWidth);

    const getLabel = () => {
        after.value = []
        before.value = []

        let surroundingPages = 2

        for(let i = 1; i <= surroundingPages; i++) {
            if (props.current_page + i <= props.last_page) {
                after.value.push({
                    label: (props.current_page + i).toString(),
                    url: getUrl(props.current_page + i)
                })
            }
        }

        for(let i = surroundingPages; i >= 1; i--) {
            if (props.current_page - i > 0) {
                before.value.push({
                    label: (props.current_page - i).toString(),
                    url: getUrl(props.current_page - i)
                })
            }
        }
    }

    const goToPage = (page) => {
        newPage.value = page;
        const url = getUrl(page);

        // Save current scroll position
        const scrollPosition = window.scrollY;

        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
            only: props.only,
            onSuccess: () => {
                // Restore scroll position after navigation
                window.scrollTo(0, scrollPosition);
            }
        });
    }

    const changePage = () => {
        goToPage(newPage.value);
    }

    const incrementPage = () => {
        if (newPage.value < props.last_page) {
            newPage.value = parseInt(newPage.value) + 1
            changePage()
        }
    }

    const decrementPage = () => {
        if (newPage.value > 1) {
            newPage.value = parseInt(newPage.value) - 1
            changePage()
        }
    }

    const getUrl = (page) => {
        // Build URL with the page parameter
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set(props.pageName, page);
        return currentUrl.pathname + '?' + currentUrl.searchParams.toString();
    }

    watchEffect(() => {
        getLabel()
        newPage.value = props.current_page
    })


    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }


    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>


<template>
    <div class="flex items-center justify-center gap-1">
        <!-- Spacer for balance (same width as total pages indicator) -->
        <div class="w-14"></div>

        <!-- First & Previous Group - Fixed Width -->
        <div class="flex items-center gap-1 mr-1 w-20 justify-end">
            <button v-if="current_page != 1" @click="goToPage(1)"
                class="group relative h-10 w-10 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500 group-hover:text-blue-400 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                </svg>
            </button>
            <div v-else class="h-10 w-10"></div>

            <button v-if="current_page != 1" @click="goToPage(current_page - 1)"
                class="group relative h-10 w-10 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500 group-hover:text-blue-400 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>
            <div v-else class="h-10 w-10"></div>
        </div>

        <!-- Page Numbers Before (Desktop) - Fixed Width Container -->
        <div v-if="screenWidth >= 640" class="flex items-center gap-1 w-20 justify-end">
            <button v-for="page in before" :key="page.label" @click="goToPage(page.label)"
                class="group relative h-10 min-w-10 px-3 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <span class="text-sm font-bold text-gray-400 group-hover:text-white transition-colors">{{ page.label }}</span>
            </button>
        </div>

        <!-- Current Page - Input Only -->
        <div class="relative mx-1">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-purple-500 blur-md opacity-30"></div>
            <div class="relative h-10 w-16 flex items-center justify-center bg-gradient-to-br from-blue-600/30 to-purple-600/30 border border-blue-400/30 rounded-xl backdrop-blur-sm">
                <input
                    v-model="newPage"
                    @change="changePage"
                    type="number"
                    class="w-full h-full px-2 bg-transparent border-0 text-blue-300 font-black text-center text-sm focus:ring-0 focus:outline-none placeholder-blue-400/40 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    :placeholder="current_page.toString()"
                />
            </div>
        </div>

        <!-- Page Numbers After (Desktop) - Fixed Width Container -->
        <div v-if="screenWidth >= 640" class="flex items-center gap-1 w-20 justify-start">
            <button v-for="page in after" :key="page.label" @click="goToPage(page.label)"
                class="group relative h-10 min-w-10 px-3 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <span class="text-sm font-bold text-gray-400 group-hover:text-white transition-colors">{{ page.label }}</span>
            </button>
        </div>

        <!-- Next & Last Group - Fixed Width -->
        <div class="flex items-center gap-1 ml-1 w-20 justify-start">
            <button v-if="current_page < last_page" @click="goToPage(current_page + 1)"
                class="group relative h-10 w-10 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500 group-hover:text-blue-400 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
            <div v-else class="h-10 w-10"></div>

            <button v-if="current_page < last_page" @click="goToPage(last_page)"
                class="group relative h-10 w-10 flex items-center justify-center rounded-xl bg-gradient-to-br from-white/5 to-white/[0.02] hover:from-blue-600/20 hover:to-purple-600/20 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:scale-110">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500 group-hover:text-blue-400 transition-colors">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
            <div v-else class="h-10 w-10"></div>
        </div>

        <!-- Total Pages Indicator -->
        <div class="ml-1 px-2 py-1 rounded-full bg-white/5 border border-white/10 whitespace-nowrap">
            <span class="text-xs font-semibold text-gray-500">/ {{ last_page }}</span>
        </div>
    </div>
</template>