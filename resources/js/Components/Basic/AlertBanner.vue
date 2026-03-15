<script setup>
    import { ref, watch, onMounted } from 'vue';

    const show = ref(false);
    let timeout = null;

    const props = defineProps({
        styling: String,
        random: Number
    });

    const dismiss = () => {
        show.value = false;
        if (timeout) clearTimeout(timeout);
    };

    const showToast = () => {
        show.value = true;
        if (timeout) clearTimeout(timeout);
        if (props.styling === 'success') {
            timeout = setTimeout(() => {
                show.value = false;
            }, 4000);
        }
    };

    onMounted(() => {
        showToast();
    });

    watch(() => props.random, () => {
        showToast();
    });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div v-if="show" class="fixed bottom-6 right-6 z-[60] max-w-sm">
                <div
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl border backdrop-blur-sm',
                        styling === 'success' ? 'bg-green-900/90 border-green-500/30' : 'bg-red-900/90 border-red-500/30'
                    ]"
                >
                    <svg v-if="styling === 'success'" class="w-5 h-5 text-green-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-red-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>

                    <p class="text-sm font-medium text-white flex-1">
                        <slot />
                    </p>

                    <button
                        type="button"
                        @click="dismiss"
                        class="shrink-0 p-1 rounded-lg hover:bg-white/10 transition-colors"
                    >
                        <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
