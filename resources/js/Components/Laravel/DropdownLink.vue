<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-3 py-2 text-start text-sm leading-5 text-gray-300 hover:text-white hover:bg-white/5 focus:outline-none focus:bg-white/5 transition-all rounded-lg">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-3 py-2 text-sm leading-5 text-gray-300 hover:text-white hover:bg-white/5 focus:outline-none focus:bg-white/5 transition-all rounded-lg">
            <slot />
        </a>

        <Link v-else :href="href" class="block px-3 py-2 text-sm leading-5 text-gray-300 hover:text-white hover:bg-white/5 focus:outline-none focus:bg-white/5 transition-all rounded-lg">
            <slot />
        </Link>
    </div>
</template>
