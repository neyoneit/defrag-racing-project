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
        default: () => ['p-2', 'bg-gray-950', 'backdrop-blur-xl', 'border', 'border-white/10'],
    },
});

let open = ref(false);
const triggerRef = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, right: 'auto' });

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const updatePosition = () => {
    if (triggerRef.value && open.value) {
        nextTick(() => {
            const rect = triggerRef.value.getBoundingClientRect();
            dropdownPosition.value = {
                top: rect.bottom + 8,
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
});

const widthClass = computed(() => {
    return {
        '48': 'w-48'
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
    <div class="relative">
        <div ref="triggerRef" @click="open = ! open">
            <slot name="trigger" />
        </div>

        <!-- Backdrop -->
        <Teleport to="body">
            <div v-show="open" class="fixed inset-0 z-[60] pointer-events-auto" @click="open = false"></div>
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
                <div v-show="open" class="fixed rounded-xl shadow-2xl z-[70]"
                     :class="[widthClass, alignmentClasses]"
                     :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left !== 'auto' ? dropdownPosition.left + 'px' : 'auto', right: dropdownPosition.right !== 'auto' ? dropdownPosition.right + 'px' : 'auto' }"
                     @click.stop>
                    <div class="rounded-xl ring-1 ring-white/10" :class="contentClasses">
                        <slot name="content" />
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
