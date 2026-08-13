<script setup>
    import { computed } from 'vue';
    import { router, usePage } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';

    const page = usePage();

    const locales = computed(() => page.props.locales ?? {});

    const current = computed(() => page.props.locale ?? 'en');

    // Its own name, never a flag: a flag is a country and half the languages
    // here are spoken in several, so a reader ends up looking for a flag that
    // is not theirs.
    const currentName = computed(() => locales.value[current.value] ?? 'English');

    const shortName = computed(() => current.value.toUpperCase());

    const choose = (code) => {
        if (code === current.value) {
            return;
        }

        // The server answers with a redirect back to this page, so the new
        // shared props arrive and app.js swaps the language file in place.
        router.post(route('locale.update'), { locale: code }, { preserveScroll: true });
    };
</script>

<template>
    <!-- Hidden entirely while English is the only language installed, rather
         than offering a menu with one item in it. -->
    <Dropdown v-if="Object.keys(locales).length > 1" align="right" width="48">
        <template #trigger>
            <button
                class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 transition-all"
                :title="currentName"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.949 8.949 0 0 0 12 21Zm0-18c-2.485 0-4.5 4.03-4.5 9s2.015 9 4.5 9 4.5-4.03 4.5-9-2.015-9-4.5-9ZM3.6 9h16.8M3.6 15h16.8" />
                </svg>
                <span class="hidden sm:block text-xs font-semibold tracking-wide">{{ shortName }}</span>
            </button>
        </template>

        <template #content>
            <!-- The menu is fixed to the viewport, so anything past the bottom
                 edge cannot be scrolled to: the page scrolls underneath it and
                 the last languages stay out of reach. Ten of them are about
                 370px tall, which is more than a phone held sideways has, so
                 the height is capped here and the list scrolls on its own.
                 The cap is inline because `defrag-scrollbar` carries one of its
                 own at 450px, and only an inline style is certain to win. -->
            <div class="defrag-scrollbar" style="max-height: calc(100vh - 5rem)">
                <button
                    v-for="(name, code) in locales"
                    :key="code"
                    type="button"
                    @click="choose(code)"
                    class="flex w-full items-center justify-between px-4 py-2 text-sm leading-5 text-left transition-all hover:bg-white/5"
                    :class="code === current ? 'text-blue-400 font-semibold' : 'text-gray-300'"
                >
                    {{ name }}
                    <svg v-if="code === current" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </button>
            </div>
        </template>
    </Dropdown>
</template>
