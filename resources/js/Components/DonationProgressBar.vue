<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const progress = ref(null);
const loading = ref(true);
const page = usePage();

// Hide on donations page
const isOnDonationsPage = computed(() => {
    return page.url.startsWith('/donations');
});

onMounted(async () => {
    try {
        const response = await fetch('/api/donations/progress');
        if (response.ok) {
            progress.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to load donation progress:', error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Link
        :href="route('donations.index')"
        v-if="!loading && progress && !isOnDonationsPage"
        class="block bg-black/60 backdrop-blur-sm border-t border-white/10 hover:bg-black/80 transition-all cursor-pointer group"
    >
        <div class="max-w-8xl mx-auto px-4 lg:px-8 py-3">
            <div class="flex items-center justify-between gap-4 mb-2">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    <span class="text-sm font-medium text-white">Support Defrag Racing {{ new Date().getFullYear() }}</span>
                </div>
                <div class="text-sm font-bold text-green-400">
                    €{{ progress.total }} / €{{ progress.goal }} ({{ progress.percentage }}%)
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative h-2 bg-gray-900/50 rounded-full overflow-hidden border border-white/10 group-hover:scale-105 transition-transform">
                <!-- Crowd-raised portion (green) -->
                <div
                    v-if="progress.donations > 0"
                    class="absolute h-full bg-green-500 transition-all duration-1000 group-hover:brightness-110"
                    :style="{ width: ((progress.donations / progress.goal) * 100) + '%' }"
                ></div>
                <!-- Self-raised portion (purple) -->
                <div
                    v-if="progress.selfRaised > 0"
                    class="absolute h-full bg-purple-500 transition-all duration-1000 group-hover:brightness-110"
                    :style="{ left: ((progress.donations / progress.goal) * 100) + '%', width: ((progress.selfRaised / progress.goal) * 100) + '%' }"
                ></div>
            </div>

            <div class="text-xs text-gray-400 mt-2 text-center group-hover:text-gray-300 transition-colors">
                Click to view donation history and support defrag.racing projects!
            </div>
        </div>
    </Link>
</template>
