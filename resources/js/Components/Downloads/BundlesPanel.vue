<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    panel: Object,
});

const formatSize = (bytes) => {
    if (!bytes) return null;
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const entryUrl = (item) => `/downloads/entry/${item.id}/${item.slug}`;

const partUrl = (part) => (part.external_url ? part.external_url : `/downloads/entry/${part.id}/${part.slug}`);
</script>

<template>
    <div class="space-y-3">

        <!-- One card per shelf. A repack split into parts is a single line with
             a button per part, so "RUN maps" reads as one thing you install
             rather than four rows that look like four choices. -->
        <div
            v-for="section in panel.sections"
            :key="section.id"
            class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">

            <div class="px-4 py-3 border-b border-white/5 flex items-baseline justify-between gap-3 flex-wrap">
                <h2 class="text-sm font-black text-white">{{ section.name }}</h2>
                <Link
                    :href="`/downloads/${section.id}/${section.slug}`"
                    class="text-[11px] font-semibold text-gray-600 hover:text-cyan-400 transition-colors">
                    {{ section.items.length }} {{ section.items.length === 1 ? 'entry' : 'entries' }}
                </Link>
            </div>

            <p v-if="section.description" class="px-4 pt-3 text-xs text-gray-500 max-w-3xl leading-relaxed">
                {{ section.description }}
            </p>

            <div>
                <div
                    v-for="item in section.items"
                    :key="item.id"
                    class="flex items-start justify-between gap-4 px-4 py-3 border-t border-white/5 hover:bg-cyan-500/[0.04] transition-colors flex-wrap">

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <Link :href="entryUrl(item)"
                                  class="text-sm font-bold text-gray-200 hover:text-cyan-300 transition-colors">
                                {{ item.name }}
                            </Link>
                            <span v-if="formatSize(item.size)" class="text-[11px] text-gray-500">
                                {{ formatSize(item.size) }}
                            </span>
                            <span v-if="item.parts"
                                  class="text-[10px] uppercase tracking-wider font-bold text-gray-500 bg-white/5 px-1.5 py-0.5 rounded">
                                {{ item.parts.length }} parts
                            </span>
                        </div>
                        <p v-if="item.description" class="text-xs text-gray-500 mt-1 max-w-2xl leading-relaxed line-clamp-2">
                            {{ item.description }}
                        </p>
                    </div>

                    <div class="flex-shrink-0 flex items-center gap-1 flex-wrap justify-end">
                        <template v-if="item.parts">
                            <a
                                v-for="part in item.parts"
                                :key="part.id"
                                :href="partUrl(part)"
                                :target="part.external_url ? '_blank' : '_self'"
                                rel="noopener"
                                :title="formatSize(part.size) ? `${part.label} - ${formatSize(part.size)}` : part.label"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/25 text-xs font-bold text-cyan-300/90 hover:bg-cyan-500/25 hover:text-cyan-200 transition-all">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                {{ part.label }}
                            </a>
                        </template>

                        <a
                            v-else
                            :href="item.external_url ? item.external_url : entryUrl(item)"
                            :target="item.external_url ? '_blank' : '_self'"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-xs font-bold text-cyan-300 hover:bg-cyan-500/25 transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="panel.sections.length === 0" class="bg-black/40 rounded-xl border border-white/5 p-12 text-center text-sm text-gray-500">
            Nothing here yet.
        </p>
    </div>
</template>
