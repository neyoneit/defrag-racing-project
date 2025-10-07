<script setup>
    import { computed } from 'vue';
    import { Head } from '@inertiajs/vue3';

    const props = defineProps({
        user_name: String,
        total_maps: {
            type: Number,
            default: 0
        },
        played_maps: {
            type: Number,
            default: 0
        },
        unplayed_maps: {
            type: Number,
            default: 0
        }
    });

    const completionPercentage = computed(() => {
        if (props.total_maps === 0) return 0;
        return ((props.played_maps / props.total_maps) * 100).toFixed(3);
    });
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-8" style="background-color: #00FF00;">
        <Head title="Map Progress" />

        <div class="w-full max-w-4xl">
            <!-- Progress Bar Container -->
            <div class="mb-6">
                <div class="mb-4">
                    <h2 class="text-2xl font-black text-white" v-html="q3tohtml(user_name)"></h2>
                    <p class="text-sm text-gray-400">Map Completionist Progress</p>
                </div>

                <!-- Stats above progress bar -->
                <div class="flex items-center justify-between mb-2 px-2">
                    <!-- Left: Maps completed -->
                    <span class="text-lg font-black text-white">Maps Completed</span>

                    <!-- Right: Maps count -->
                    <div class="text-2xl font-black text-white">
                        {{ played_maps }} / {{ total_maps }}
                    </div>
                </div>

                <!-- Animated Progress Bar -->
                <div class="relative">
                    <!-- Background track -->
                    <div class="h-16 rounded-full overflow-hidden border border-gray-600 shadow-inner" style="background-color: rgba(31, 41, 55, 0.8);">
                        <!-- Animated progress fill -->
                        <div
                            class="h-full relative overflow-hidden transition-all duration-1000 ease-out bg-blue-600"
                            :style="`width: ${completionPercentage}%`">

                            <!-- Animated moving stripes -->
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-blue-500/40 to-transparent bg-[length:200%_100%] animate-[shimmer_1.5s_ease-in-out_infinite]"></div>

                            <!-- Pulse effect -->
                            <div class="absolute inset-0 bg-white/10 animate-pulse"></div>

                            <!-- Sliding highlight -->
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent bg-[length:50%_100%] animate-[shimmer_2s_linear_infinite]"></div>
                        </div>

                        <!-- Particles/dots animation -->
                        <div class="absolute inset-y-0 left-0 right-0 pointer-events-none">
                            <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-blue-400 rounded-full blur-sm animate-[shimmer_3s_linear_infinite] opacity-50"></div>
                            <div class="absolute top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-300 rounded-full blur-sm animate-[shimmer_2.5s_linear_infinite] opacity-40" style="animation-delay: 0.5s"></div>
                        </div>
                    </div>

                    <!-- Stats inside progress bar -->
                    <div class="absolute inset-0 flex items-center justify-between px-6">
                        <!-- Left: Maps completed -->
                        <span class="text-sm font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                            {{ played_maps }} maps completed
                        </span>

                        <!-- Center: Percentage -->
                        <div class="text-3xl font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                            {{ completionPercentage }}%
                        </div>

                        <!-- Right: Maps remaining -->
                        <span class="text-sm font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                            {{ unplayed_maps }} remaining
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
