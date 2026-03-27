<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: Array,
        default: () => ['p-1.5', 'bg-gray-800/90', 'backdrop-blur-xl', 'border', 'border-white/15'],
    },
    hoverable: {
        type: Boolean,
        default: false,
    },
});

let open = ref(false);
const triggerRef = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, right: 'auto' });
let hoverTimeout = null;

// Global registry - close other hoverable dropdowns when one opens
if (!window.__hoverableDropdowns) window.__hoverableDropdowns = new Set();
const instanceId = Symbol();

const isTouchDevice = () => window.matchMedia('(pointer: coarse)').matches;

const closeOtherHoverables = () => {
    window.__hoverableDropdowns.forEach(closer => {
        if (closer.id !== instanceId) closer.close();
    });
};

const onMouseEnter = () => {
    if (!props.hoverable || isTouchDevice()) return;
    clearTimeout(hoverTimeout);
    closeOtherHoverables();
    open.value = true;
};

const onMouseLeave = () => {
    if (!props.hoverable || isTouchDevice()) return;
    hoverTimeout = setTimeout(() => { open.value = false; }, 150);
};

const hoverEntry = props.hoverable ? { id: instanceId, close: () => { clearTimeout(hoverTimeout); open.value = false; } } : null;
if (hoverEntry) window.__hoverableDropdowns.add(hoverEntry);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const updatePosition = () => {
    if (triggerRef.value && open.value) {
        nextTick(() => {
            const rect = triggerRef.value.getBoundingClientRect();
            const gap = props.hoverable ? 0 : 8;
            dropdownPosition.value = {
                top: rect.bottom + gap,
                left: props.align === 'left' ? rect.left : 'auto',
                right: props.align === 'right' ? window.innerWidth - rect.right : 'auto',
            };
        });
    }
};

watch(open, (newVal) => {
    if (newVal) {
        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
    } else {
        window.removeEventListener('resize', updatePosition);
        window.removeEventListener('scroll', updatePosition, true);
    }
});

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', updatePosition);
    window.removeEventListener('scroll', updatePosition, true);
    if (hoverEntry) window.__hoverableDropdowns.delete(hoverEntry);
});

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '56': 'w-56'
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    }

    if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    return 'origin-top';
});
</script>

<template>
    <div class="relative" @mouseenter="onMouseEnter" @mouseleave="onMouseLeave">
        <div ref="triggerRef" @click="open = ! open">
            <slot name="trigger" :open="open" />
        </div>

        <!-- Backdrop (disabled for hoverable - closing handled by mouseleave) -->
        <Teleport to="body">
            <div v-show="open && !hoverable" class="fixed inset-0 z-[210] pointer-events-auto" @click="open = false"></div>
        </Teleport>

        <!-- Dropdown -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
            >
                <div v-show="open" class="fixed z-[220]"
                     :class="[widthClass, alignmentClasses]"
                     :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left !== 'auto' ? dropdownPosition.left + 'px' : 'auto', right: dropdownPosition.right !== 'auto' ? dropdownPosition.right + 'px' : 'auto' }"
                     @click.stop
                     @mouseenter="onMouseEnter"
                     @mouseleave="onMouseLeave">
                    <div :class="hoverable ? 'pt-2' : ''">
                        <div class="rounded-xl shadow-[0_8px_40px_rgba(0,0,0,0.5)] ring-1 ring-white/15" :class="contentClasses">
                            <slot name="content" />
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
