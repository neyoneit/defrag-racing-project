<script setup>
defineProps({
    panel: Object,
});

const formatSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const formatDate = (value) => {
    if (!value) return null;
    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <div class="space-y-3">

        <!-- Intro + build state -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="max-w-2xl">
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Everything needed to run a DeFRaG server on Linux: the oDFe dedicated engine, the
                        DeFRaG mod, the record system modules and the stock Quake III paks. The core archive
                        rebuilds itself whenever a new engine build or mod release lands, so a fresh install
                        is always current.
                    </p>
                </div>

                <div v-if="panel.built_at"
                     class="flex-shrink-0 bg-gradient-to-br from-cyan-500/15 to-cyan-500/5 border border-cyan-500/30 rounded-xl px-4 py-3 min-w-[210px]">
                    <div class="text-[10px] uppercase tracking-wider text-cyan-500/80 font-bold mb-1">Last built</div>
                    <div class="text-sm font-bold text-white">{{ formatDate(panel.built_at) }}</div>
                    <dl class="mt-2 space-y-0.5 text-[11px]">
                        <div v-if="panel.mod" class="flex justify-between gap-3">
                            <dt class="text-gray-500">Mod</dt>
                            <dd class="text-gray-300 font-medium">{{ panel.mod }}</dd>
                        </div>
                        <div v-if="panel.engine" class="flex justify-between gap-3">
                            <dt class="text-gray-500">Engine</dt>
                            <dd class="text-gray-300 font-medium">{{ panel.engine.slice(0, 10) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Quick install -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <h2 class="text-sm font-black text-white mb-1">Quick install</h2>
            <p class="text-xs text-gray-500 mb-3">
                The installer pulls the core archive every time and the baseq3 assets only when they are missing.
            </p>
            <pre class="bg-black/60 border border-white/5 rounded-lg p-3 overflow-x-auto text-xs text-gray-300"><code>git clone {{ panel.repo_url }}
cd defrag-server-bundle
./download_defrag.sh</code></pre>
            <a :href="panel.repo_url" target="_blank" rel="noopener"
               class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-cyan-400 hover:text-cyan-300 transition-colors">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 .3a12 12 0 00-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 0-.8.4-1.3.7-1.6-2.7-.3-5.5-1.3-5.5-6 0-1.2.5-2.3 1.3-3.1-.2-.4-.6-1.6.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 016 0c2.3-1.5 3.3-1.2 3.3-1.2.7 1.6.2 2.8.1 3.2.8.8 1.3 1.9 1.3 3.2 0 4.6-2.8 5.6-5.5 5.9.5.4.9 1.1.9 2.3v3.3c0 .3.1.7.8.6A12 12 0 0012 .3" />
                </svg>
                Setup guide and full instructions on GitHub
            </a>
        </div>

        <!-- Archives -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
            <div class="px-4 py-3 border-b border-white/5">
                <h2 class="text-sm font-black text-white">Archives</h2>
            </div>

            <div v-if="panel.archives.length > 0">
                <div v-for="a in panel.archives" :key="a.id"
                     class="flex items-start justify-between gap-4 px-4 py-3.5 border-t border-white/5 first:border-t-0 hover:bg-cyan-500/[0.04] transition-colors flex-wrap">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-gray-200">{{ a.name }}</span>
                            <code class="text-[11px] text-cyan-400/80 bg-black/50 px-1.5 py-0.5 rounded">{{ a.filename }}</code>
                            <span class="text-[11px] text-gray-500">{{ formatSize(a.size) }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 max-w-2xl leading-relaxed">{{ a.description }}</p>
                    </div>
                    <a :href="a.url" target="_blank" rel="noopener"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-xs font-bold text-cyan-300 hover:bg-cyan-500/25 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                    </a>
                </div>
            </div>

            <p v-else class="px-4 py-6 text-sm text-gray-600">
                No archives published yet. They appear once the bundle has been built.
            </p>
        </div>
    </div>
</template>
