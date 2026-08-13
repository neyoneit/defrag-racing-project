<script setup>
import { currentLocale } from '@/utils/i18n';

defineProps({
    panel: Object,
});

const formatSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const formatDate = (value) => {
    if (!value) return null;
    return new Date(value).toLocaleDateString(currentLocale() === 'en' ? 'en-GB' : currentLocale(), { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="space-y-3">

        <!-- Intro -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <p class="text-sm text-gray-400 leading-relaxed max-w-3xl">
                {{ $t('Quake III ships with gore and adult artwork. These pk3s swap it for clean alternatives, which is what you want when you stream, play at work, or hand the game to a kid. Physics, timing and records are untouched, so anything you run with these is still a valid demo.') }}
            </p>
        </div>

        <!-- Files -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
            <div class="px-4 py-3 border-b border-white/5">
                <h2 class="text-sm font-black text-white">{{ $t('The pack') }}</h2>
            </div>

            <div v-if="panel.files.length > 0">
                <div v-for="f in panel.files" :key="f.id"
                     class="flex items-start justify-between gap-4 px-4 py-3.5 border-t border-white/5 first:border-t-0 hover:bg-cyan-500/[0.04] transition-colors flex-wrap">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-gray-200">{{ f.name }}</span>
                            <code class="text-[11px] text-cyan-400/80 bg-black/50 px-1.5 py-0.5 rounded">{{ f.filename }}</code>
                            <span class="text-[11px] text-gray-500">{{ formatSize(f.size) }}</span>
                            <span v-if="f.updated_at" class="text-[11px] text-gray-600">
                                {{ $t('updated :date', { date: formatDate(f.updated_at) }) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 max-w-2xl leading-relaxed">{{ f.description }}</p>
                    </div>
                    <a :href="f.url" target="_blank" rel="noopener"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-xs font-bold text-cyan-300 hover:bg-cyan-500/25 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ $t('Download') }}
                    </a>
                </div>
            </div>

            <p v-else class="px-4 py-6 text-sm text-gray-600">{{ $t('Nothing published yet.') }}</p>
        </div>

        <!-- Install -->
        <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
            <h2 class="text-sm font-black text-white mb-2">{{ $t('Installation') }}</h2>
            <p class="text-sm text-gray-400 leading-relaxed [&_code]:bg-black/60 [&_code]:text-cyan-300 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-xs [&_strong]:text-gray-200"
               v-html="$t('Drop the .pk3 files into your <code>baseq3</code> folder. Quake loads pk3s in alphabetical order and the last one wins, so the <code>zzz</code> prefix is what lets them override the stock artwork. <strong>Do not rename them.</strong> To undo it, delete the files again.')"></p>
        </div>
    </div>
</template>
