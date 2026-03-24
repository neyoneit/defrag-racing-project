<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-3 py-2 text-start text-sm leading-5 transition-all rounded-lg border" :class="active ? 'text-white bg-blue-600/30 border-blue-500/30 font-semibold' : 'text-gray-300 hover:text-white hover:bg-blue-500/40 hover:border-blue-400/40 hover:shadow-[0_0_10px_rgba(59,130,246,0.15)] border-transparent'">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-3 py-2 text-sm leading-5 transition-all rounded-lg border" :class="active ? 'text-white bg-blue-600/30 border-blue-500/30 font-semibold' : 'text-gray-300 hover:text-white hover:bg-blue-500/40 hover:border-blue-400/40 hover:shadow-[0_0_10px_rgba(59,130,246,0.15)] border-transparent'">
            <slot />
        </a>

        <Link v-else :href="href" class="block px-3 py-2 text-sm leading-5 transition-all rounded-lg border" :class="active ? 'text-white bg-blue-600/30 border-blue-500/30 font-semibold' : 'text-gray-300 hover:text-white hover:bg-blue-500/40 hover:border-blue-400/40 hover:shadow-[0_0_10px_rgba(59,130,246,0.15)] border-transparent'">
            <slot />
        </Link>
    </div>
</template>
