<script setup>
// The checker on a page of its own, for a link somebody can be handed - in a
// refusal, in Discord, in an answer to "why did my run not count". The comps
// page unrolls the same component under its upload, which is where most people
// meet it; this is for everyone who arrives already asking the question.
import { Head, Link } from '@inertiajs/vue3';
import DemoSettingsCheck from '@/Components/DemoSettingsCheck.vue';
</script>

<template>
    <div>
        <Head :title="$t('Check your demo settings')" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Demo validator') }}</h1>
                        <p class="mt-1 max-w-2xl text-sm text-gray-400">
                            {{ $t('Drop a demo here and this tells you whether the settings in it would count. Nothing is saved - the file is read and thrown away.') }}
                        </p>
                    </div>

                    <Link :href="route('comps.index')"
                          class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-lg border border-white/15 bg-white/[0.06] px-2.5 py-1 text-sm font-bold text-gray-200 transition-colors hover:border-white/25 hover:bg-white/10">
                        {{ $t('Weekly comps') }}
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12">
            <div class="mx-auto max-w-3xl">
                <DemoSettingsCheck />
            </div>
        </div>
    </div>
</template>
