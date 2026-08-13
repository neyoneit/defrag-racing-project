<script setup>
import { ref, getCurrentInstance } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { currentLocale } from '@/utils/i18n';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    download: Object,
    canModerate: Boolean,
});

const lightbox = ref(null);

const formatSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(currentLocale() === 'en' ? 'en-GB' : currentLocale(), { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
</script>

<template>
    <div>
        <Head :title="download.name" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-xs text-gray-400 mb-2 flex-wrap">
                    <Link href="/downloads" class="hover:text-cyan-400 transition-colors">{{ $t('Downloads') }}</Link>
                    <template v-for="crumb in download.category?.breadcrumb ?? []" :key="crumb.id">
                        <span class="text-gray-700">/</span>
                        <Link :href="`/downloads/${crumb.id}/${crumb.slug}`" class="hover:text-cyan-400 transition-colors">
                            {{ crumb.name }}
                        </Link>
                    </template>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ download.name }}</h1>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -22rem;">
            <div class="flex flex-col lg:flex-row gap-4">

                <!-- Main -->
                <div class="flex-1 min-w-0 space-y-3">

                    <div v-if="download.status !== 'published'"
                         class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-2.5 text-xs text-amber-300"
                         v-html="$t('This entry is <strong>:status</strong> and is not publicly listed.', { status: download.status })"></div>

                    <!-- Description -->
                    <div v-if="download.description"
                         class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
                        <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-line">{{ download.description }}</p>
                    </div>

                    <!-- YouTube -->
                    <div v-if="download.youtube_id"
                         class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
                        <div class="relative w-full" style="padding-bottom: 56.25%;">
                            <iframe
                                class="absolute inset-0 w-full h-full"
                                :src="`https://www.youtube-nocookie.com/embed/${download.youtube_id}`"
                                :title="$t('YouTube video')"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                        </div>
                    </div>

                    <!-- Screenshots -->
                    <div v-if="download.screenshots.length > 0"
                         class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-4">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-3">{{ $t('Screenshots') }}</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button
                                v-for="shot in download.screenshots"
                                :key="shot.id"
                                @click="lightbox = shot.url"
                                class="group relative aspect-video rounded-lg overflow-hidden border border-white/10 hover:border-cyan-500/50 transition-all">
                                <img :src="shot.url" alt="" loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            </button>
                        </div>
                    </div>

                    <!-- Files -->
                    <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-semibold px-4 py-3 border-b border-white/5">
                            {{ $t('Files') }}
                        </h2>

                        <table v-if="download.files.length > 0" class="w-full text-sm">
                            <tbody>
                                <tr v-for="file in download.files" :key="file.id"
                                    class="border-b border-white/5 last:border-0 hover:bg-cyan-500/[0.04] transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="text-gray-200 font-medium break-all">{{ file.original_name }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right text-xs text-gray-500 whitespace-nowrap">
                                        {{ formatSize(file.size) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <a :href="file.url"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-xs font-semibold text-cyan-300 hover:bg-cyan-500/25 transition-all whitespace-nowrap">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            {{ $t('Download') }}
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Entries that only point elsewhere carry no files of their own. -->
                        <div v-else-if="download.external_url" class="p-4">
                            <a :href="download.external_url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-sm font-semibold text-cyan-300 hover:bg-cyan-500/25 transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                {{ $t('Download') }}
                            </a>
                            <p class="text-xs text-gray-600 mt-2 break-all">{{ download.external_url }}</p>
                        </div>

                        <p v-else class="p-4 text-sm text-gray-600">{{ $t('No files attached.') }}</p>
                    </div>
                </div>

                <!-- Meta sidebar -->
                <div class="lg:w-64 flex-shrink-0">
                    <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-4 sticky top-[120px] space-y-3">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-600 font-semibold mb-1">{{ $t('Author') }}</div>
                            <Link v-if="download.user" :href="`/profile/${download.user.id}`"
                                  class="text-sm text-gray-300 hover:text-cyan-400 transition-colors"
                                  v-html="q3tohtml(download.user.name)"></Link>
                            <span v-else class="text-sm text-gray-600">{{ $t('system') }}</span>
                        </div>

                        <div v-if="download.category">
                            <div class="text-[10px] uppercase tracking-wider text-gray-600 font-semibold mb-1">{{ $t('Category') }}</div>
                            <Link :href="`/downloads/${download.category.id}/${download.category.slug}`"
                                  class="text-sm text-gray-300 hover:text-cyan-400 transition-colors">
                                {{ download.category.name }}
                            </Link>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-600 font-semibold mb-1">{{ $t('Added') }}</div>
                            <div class="text-sm text-gray-300">{{ formatDate(download.created_at) }}</div>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-600 font-semibold mb-1">{{ $t('Downloads') }}</div>
                            <div class="text-sm text-gray-300">{{ download.downloads_count ?? 0 }}</div>
                        </div>

                        <div v-if="download.defrag_only || download.is_locked" class="flex flex-wrap gap-1.5 pt-1">
                            <span v-if="download.defrag_only"
                                  class="text-[10px] font-bold px-2 py-0.5 rounded bg-cyan-500/15 text-cyan-400 border border-cyan-500/30">
                                {{ $t('DeFRaG only') }}
                            </span>
                            <span v-if="download.is_locked"
                                  class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                {{ $t('AUTO') }}
                            </span>
                        </div>

                        <a v-if="canModerate" :href="`/admin/downloads/${download.id}/edit`"
                           class="block w-full text-center px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-xs font-semibold text-gray-400 hover:text-white transition-all">
                            {{ $t('Edit in admin') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <div v-if="lightbox" @click="lightbox = null"
             class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm flex items-center justify-center p-6 cursor-zoom-out">
            <img :src="lightbox" alt="" class="max-w-full max-h-full rounded-lg" />
        </div>

        <div class="h-4"></div>
    </div>
</template>
