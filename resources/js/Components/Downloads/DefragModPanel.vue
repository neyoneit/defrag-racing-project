<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    panel: Object,
});

const formatSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};
</script>

<template>
    <div class="space-y-3">

        <!-- Intro + latest -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="max-w-2xl">
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Official DeFRaG mod releases, mirrored from
                        <a :href="panel.source_url" target="_blank" rel="noopener"
                           class="text-cyan-400 hover:text-cyan-300 transition-colors">q3defrag.org</a>.
                        This page updates itself, so it always shows every release that is actually out there.
                    </p>
                </div>

                <div v-if="panel.latest"
                     class="flex-shrink-0 bg-gradient-to-br from-cyan-500/15 to-cyan-500/5 border border-cyan-500/30 rounded-xl px-4 py-3 min-w-[190px]">
                    <div class="text-[10px] uppercase tracking-wider text-cyan-500/80 font-bold mb-1">Latest stable</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-white">{{ panel.latest.version }}</span>
                        <span class="text-[11px] text-gray-500">{{ panel.latest.date }}</span>
                    </div>
                    <a :href="panel.latest.url" target="_blank" rel="noopener"
                       class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/20 border border-cyan-500/40 text-xs font-bold text-cyan-300 hover:bg-cyan-500/30 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download {{ formatSize(panel.latest.size) }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Stable -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
            <div class="px-4 py-3 border-b border-white/5">
                <h2 class="text-sm font-black text-white">Stable releases</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 bg-white/[0.03]">
                            <th class="font-semibold px-4 py-2.5">Version</th>
                            <th class="font-semibold px-3 py-2.5 hidden sm:table-cell">Date</th>
                            <th class="font-semibold px-3 py-2.5 text-right">Size</th>
                            <th class="font-semibold px-3 py-2.5">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in panel.stable" :key="r.id"
                            class="border-t border-white/5 hover:bg-cyan-500/[0.04] transition-colors">
                            <td class="px-4 py-2.5">
                                <span class="font-bold text-gray-200">{{ r.version }}</span>
                                <span v-if="r.is_latest"
                                      class="ml-2 text-[9px] font-black px-1.5 py-0.5 rounded bg-green-500 text-black">
                                    LATEST
                                </span>
                            </td>
                            <td class="px-3 py-2.5 hidden sm:table-cell text-xs text-gray-500 whitespace-nowrap">{{ r.date }}</td>
                            <td class="px-3 py-2.5 text-right text-xs text-gray-500 whitespace-nowrap">{{ formatSize(r.size) }}</td>
                            <td class="px-3 py-2.5">
                                <a :href="r.url" target="_blank" rel="noopener"
                                   class="text-xs text-cyan-400 hover:text-cyan-300 hover:underline transition-colors break-all">
                                    {{ r.filename }}
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Beta -->
        <div v-if="panel.beta.length > 0"
             class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
            <div class="px-4 py-3 border-b border-white/5">
                <h2 class="text-sm font-black text-white">Beta / unstable releases</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Experimental builds with new features. Not recommended for competitive play.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 bg-white/[0.03]">
                            <th class="font-semibold px-4 py-2.5">Version</th>
                            <th class="font-semibold px-3 py-2.5 hidden sm:table-cell">Date</th>
                            <th class="font-semibold px-3 py-2.5 text-right">Size</th>
                            <th class="font-semibold px-3 py-2.5">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in panel.beta" :key="r.id"
                            class="border-t border-white/5 hover:bg-amber-500/[0.04] transition-colors">
                            <td class="px-4 py-2.5">
                                <span class="font-bold text-gray-300">{{ r.version }}</span>
                                <span class="ml-2 text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                    BETA
                                </span>
                            </td>
                            <td class="px-3 py-2.5 hidden sm:table-cell text-xs text-gray-500 whitespace-nowrap">{{ r.date }}</td>
                            <td class="px-3 py-2.5 text-right text-xs text-gray-500 whitespace-nowrap">{{ formatSize(r.size) }}</td>
                            <td class="px-3 py-2.5">
                                <a :href="r.url" target="_blank" rel="noopener"
                                   class="text-xs text-amber-400/90 hover:text-amber-300 hover:underline transition-colors break-all">
                                    {{ r.filename }}
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Install -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <h2 class="text-sm font-black text-white mb-2">Installation</h2>
            <p class="text-sm text-gray-400 leading-relaxed">
                Download the latest .zip and extract the
                <code class="bg-black/60 text-cyan-300 px-1.5 py-0.5 rounded text-xs">defrag</code>
                folder into your Quake III Arena directory, next to
                <code class="bg-black/60 text-cyan-300 px-1.5 py-0.5 rounded text-xs">baseq3</code>,
                <strong class="text-gray-200">not inside it</strong>.
                See the
                <Link href="/wiki/installation" class="text-cyan-400 hover:text-cyan-300 transition-colors">
                    Installation &amp; Setup
                </Link>
                guide for the full walkthrough.
            </p>
        </div>
    </div>
</template>
