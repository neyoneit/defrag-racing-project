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

const downloadUrl = (item) => (item.external_url ? item.external_url : entryUrl(item));
</script>

<template>
    <div class="space-y-3">

        <!-- What this shelf is, and what to know before downloading from it. -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <p class="text-sm text-gray-400 leading-relaxed max-w-3xl">{{ panel.intro }}</p>

            <ul v-if="panel.notes.length" class="mt-3 space-y-1.5 max-w-3xl">
                <li v-for="(note, i) in panel.notes" :key="i" class="flex gap-2 text-xs text-gray-500 leading-relaxed">
                    <span class="text-cyan-500/50 flex-shrink-0">-</span>
                    <span>{{ note }}</span>
                </li>
            </ul>
        </div>

        <!-- The featured entry, where the parts are the actual choice: pick
             your platform. Big buttons, because that is the whole page. -->
        <div v-if="panel.feature"
             class="bg-gradient-to-br from-cyan-500/[0.07] to-transparent backdrop-blur-xl rounded-xl border border-cyan-500/25 p-5">
            <div class="flex items-baseline gap-2 flex-wrap">
                <Link :href="entryUrl(panel.feature)"
                      class="text-base font-black text-white hover:text-cyan-300 transition-colors">
                    {{ panel.feature.name }}
                </Link>
                <span v-if="formatSize(panel.feature.size)" class="text-[11px] text-gray-500">
                    {{ formatSize(panel.feature.size) }}
                </span>
            </div>

            <p v-if="panel.feature.description" class="text-xs text-gray-400 mt-1.5 max-w-2xl leading-relaxed">
                {{ panel.feature.description }}
            </p>

            <div class="mt-4 flex items-center gap-2 flex-wrap">
                <a
                    v-for="part in panel.feature.parts"
                    :key="part.id"
                    :href="partUrl(part)"
                    :target="part.external_url ? '_blank' : '_self'"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-cyan-500/15 border border-cyan-500/35 text-xs font-black text-cyan-300 hover:bg-cyan-500/30 hover:text-white transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ part.label }}
                </a>

                <a
                    v-if="!panel.feature.parts"
                    :href="downloadUrl(panel.feature)"
                    :target="panel.feature.external_url ? '_blank' : '_self'"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-cyan-500/15 border border-cyan-500/35 text-xs font-black text-cyan-300 hover:bg-cyan-500/30 transition-all">
                    Download
                </a>
            </div>
        </div>

        <!-- Everything else, grouped. -->
        <div
            v-for="(group, gi) in panel.groups"
            :key="gi"
            class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">

            <div v-if="group.name" class="px-4 py-3 border-b border-white/5">
                <h2 class="text-sm font-black text-white">{{ group.name }}</h2>
            </div>

            <div
                v-for="item in group.items"
                :key="item.id"
                class="flex items-start justify-between gap-4 px-4 py-3.5 border-t border-white/5 first:border-t-0 hover:bg-cyan-500/[0.04] transition-colors flex-wrap">

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
                            :title="`Part ${part.label}`"
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
                        :href="downloadUrl(item)"
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
</template>
