<script setup>
// Lightweight, dependency-free media showcase for the Launcher page.
// Rotates through `slides` (images and/or videos). Auto-advances images on a
// timer and videos when they finish playing; pauses on hover; supports
// prev/next, dots, ←/→ keys, and a click-to-zoom lightbox.
//
// Each slide: { type: 'image' | 'video', src, poster?, alt?, caption?, duration? }
//  - duration (ms) overrides the image dwell time, or acts as a safety timeout
//    for a video that never fires `ended`.

import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    // Default dwell per image slide (ms).
    interval: { type: Number, default: 5000 },
});

const index = ref(0);
const hovering = ref(false);
const lightbox = ref(false);
let timer = null;

const count = computed(() => props.slides.length);
const current = computed(() => props.slides[index.value] || null);

const clear = () => { if (timer) { clearTimeout(timer); timer = null; } };

const schedule = () => {
    clear();
    if (count.value <= 1 || hovering.value || lightbox.value) return;
    const cur = props.slides[index.value];
    // Videos advance on their `ended` event; keep a long safety timeout in case
    // the source loops or never ends.
    const dwell = cur?.type === 'video' ? (cur.duration || 30000) : (cur?.duration || props.interval);
    timer = setTimeout(next, dwell);
};

const go = (i) => {
    if (!count.value) return;
    index.value = (i % count.value + count.value) % count.value;
    schedule();
};
const next = () => go(index.value + 1);
const prev = () => go(index.value - 1);

// A non-looping video finished: move on immediately.
const onVideoEnded = () => next();

const onKey = (e) => {
    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'Escape') lightbox.value = false;
};

onMounted(() => {
    schedule();
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clear();
    window.removeEventListener('keydown', onKey);
});

watch([hovering, lightbox, index], schedule);
</script>

<template>
    <div v-if="count" class="select-none">
        <!-- Stage -->
        <div class="group relative rounded-2xl overflow-hidden border border-white/10 bg-black/60 shadow-2xl shadow-black/40 aspect-[1115/731]"
             @mouseenter="hovering = true" @mouseleave="hovering = false">

            <Transition name="dr-fade" mode="out-in">
                <div :key="index" class="absolute inset-0">
                    <img v-if="current.type !== 'video'"
                         :src="current.src" :alt="current.alt || ''" loading="lazy"
                         class="w-full h-full object-cover cursor-zoom-in"
                         @click="lightbox = true" />
                    <video v-else
                           :src="current.src" :poster="current.poster"
                           autoplay muted playsinline :loop="count === 1"
                           class="w-full h-full object-cover cursor-zoom-in"
                           @ended="onVideoEnded"
                           @click="lightbox = true"></video>
                </div>
            </Transition>

            <!-- Caption -->
            <div v-if="current && current.caption"
                 class="absolute inset-x-0 bottom-0 p-4 pt-10 bg-gradient-to-t from-black/80 to-transparent pointer-events-none">
                <span class="text-sm font-semibold text-white">{{ current.caption }}</span>
            </div>

            <!-- Prev / Next -->
            <template v-if="count > 1">
                <button type="button" @click.stop="prev" aria-label="Previous"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" @click.stop="next" aria-label="Next"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </template>
        </div>

        <!-- Dots -->
        <div v-if="count > 1" class="flex justify-center items-center gap-2 mt-3">
            <button v-for="(s, i) in slides" :key="i" type="button" @click="go(i)"
                    :aria-label="`Slide ${i + 1}`"
                    class="h-1.5 rounded-full transition-all"
                    :class="i === index ? 'w-6 bg-blue-400' : 'w-1.5 bg-white/25 hover:bg-white/50'"></button>
        </div>

        <!-- Lightbox -->
        <Teleport to="body">
            <div v-if="lightbox" class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm flex items-center justify-center p-4"
                 @click="lightbox = false">
                <img v-if="current.type !== 'video'" :src="current.src" :alt="current.alt || ''"
                     class="max-w-full max-h-full rounded-lg shadow-2xl" @click.stop />
                <video v-else :src="current.src" :poster="current.poster" autoplay muted loop playsinline controls
                       class="max-w-full max-h-full rounded-lg shadow-2xl" @click.stop></video>
                <button type="button" aria-label="Close"
                        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.dr-fade-enter-active,
.dr-fade-leave-active {
    transition: opacity 0.4s ease;
}
.dr-fade-enter-from,
.dr-fade-leave-to {
    opacity: 0;
}
</style>
