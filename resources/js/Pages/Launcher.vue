<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const props = defineProps({
    version: { type: String, default: null },
    published: { type: String, default: null },
    assets: { type: Object, default: () => ({ windows: [], macos: [], linux: [] }) },
    releaseUrl: { type: String, default: 'https://github.com/Defrag-racing/defrag-racing-launcher/releases/latest' },
    changelogUrl: { type: String, default: 'https://github.com/Defrag-racing/defrag-racing-launcher/blob/main/CHANGELOG.md' },
});

const detectedOS = ref(null);
const detectedArm = ref(false);

onMounted(() => {
    const ua = (navigator.userAgent || '').toLowerCase();
    if (ua.includes('windows')) detectedOS.value = 'windows';
    else if (ua.includes('mac')) detectedOS.value = 'macos';
    else if (ua.includes('linux') && !ua.includes('android')) detectedOS.value = 'linux';

    if (detectedOS.value === 'macos') {
        const platform = (navigator.userAgentData?.platform || navigator.platform || '').toLowerCase();
        const arch = navigator.userAgentData?.getHighEntropyValues
            ? null
            : (ua.includes('arm') || platform.includes('arm') ? 'arm' : 'intel');
        if (arch === 'arm') detectedArm.value = true;
        navigator.userAgentData?.getHighEntropyValues?.(['architecture']).then(v => {
            if ((v.architecture || '').toLowerCase() === 'arm') detectedArm.value = true;
        }).catch(() => {});
    }
});

const platforms = [
    { id: 'windows', label: 'Windows', tagline: 'Windows 10 / 11 (64-bit)' },
    { id: 'macos', label: 'macOS', tagline: 'Intel + Apple Silicon' },
    { id: 'linux', label: 'Linux', tagline: 'AppImage / .deb / .rpm' },
];

const primaryAsset = computed(() => {
    if (!detectedOS.value) return null;
    const list = props.assets?.[detectedOS.value] || [];
    if (!list.length) return null;
    if (detectedOS.value === 'macos') {
        const wanted = detectedArm.value ? 'dmg-arm' : 'dmg-intel';
        return list.find(a => a.kind === wanted) || list[0];
    }
    return list.find(a => a.recommended) || list[0];
});

const primaryLabel = computed(() => {
    if (!detectedOS.value) return '';
    return platforms.find(p => p.id === detectedOS.value)?.label || '';
});

const formatSize = (bytes) => {
    if (!bytes) return '';
    const mb = bytes / (1024 * 1024);
    return mb.toFixed(1) + ' MB';
};

const publishedLabel = computed(() => {
    if (!props.published) return '';
    const d = new Date(props.published);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
});

const features = [
    {
        title: 'Auto-backup your demos',
        body: 'Drops a watcher on your Defrag demos folder and uploads new runs to defrag.racing in the background. You never lose a demo, even on a crash.',
        icon: 'cloud-upload',
    },
    {
        title: 'defrag:// Connect that works',
        body: 'Every Connect button on this site opens your Quake 3 engine and joins the server. No copy-pasting /connect into the console.',
        icon: 'plug',
    },
    {
        title: 'Native server browser',
        body: 'Browse the same server list as the website, with the same filters, from a small desktop window. Quick-launch a server in two clicks.',
        icon: 'list',
    },
    {
        title: 'Records + notifications on the desktop',
        body: 'Bell-badge notifications when someone breaks your record. Records and Maps tabs let you check times without opening a browser.',
        icon: 'bell',
    },
];
</script>

<template>
    <Head title="Download Launcher" />

    <div class="relative pt-10 pb-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-300 text-[11px] font-semibold uppercase tracking-wider mb-4">
                    Desktop launcher
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white mb-4 leading-tight">
                    Defrag Racing <span class="text-blue-500">Launcher</span>
                </h1>
                <p class="text-base text-gray-400 max-w-2xl mx-auto mb-8">
                    Auto-backup your demos, browse servers natively, and make every
                    <code class="text-blue-300 bg-blue-500/10 px-1.5 py-0.5 rounded text-sm">defrag://</code>
                    connect button on this site work.
                </p>

                <div v-if="primaryAsset" class="flex flex-col items-center gap-3">
                    <a :href="primaryAsset.url"
                       class="inline-flex items-center gap-3 px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 active:bg-blue-700 transition-colors text-white text-base font-bold shadow-2xl shadow-blue-500/20">
                        <svg v-if="detectedOS === 'windows'" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.5l9.6-1.3v9.3H0V3.5zm0 17l9.6 1.3v-9.2H0v7.9zM10.7 22.0l12.8 1.8V12.5H10.7v9.5zm0-19.9V11.3h12.8V0L10.7 2.1z"/></svg>
                        <svg v-else-if="detectedOS === 'macos'" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 12.04c.03-2.94 2.4-4.36 2.51-4.43-1.37-2-3.5-2.28-4.26-2.31-1.81-.18-3.54 1.07-4.46 1.07-.94 0-2.34-1.05-3.85-1.02-1.98.03-3.81 1.15-4.83 2.92-2.06 3.57-.53 8.85 1.48 11.74.98 1.42 2.14 3 3.66 2.95 1.47-.06 2.02-.95 3.8-.95 1.77 0 2.27.95 3.82.92 1.58-.03 2.58-1.44 3.55-2.86 1.12-1.64 1.58-3.23 1.6-3.31-.04-.02-3.05-1.17-3.08-4.64M14.13 3.84c.81-.99 1.36-2.36 1.21-3.74-1.17.05-2.6.78-3.44 1.77-.75.87-1.41 2.27-1.23 3.61 1.31.1 2.65-.66 3.46-1.64"/></svg>
                        <svg v-else-if="detectedOS === 'linux'" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12.504 0c-.155 0-.315.008-.48.021-4.226.333-3.105 4.807-3.17 6.298-.076 1.092-.3 1.953-1.05 3.02-.885 1.051-2.127 2.75-2.716 4.521-.278.832-.41 1.684-.287 2.489.117.766.422 1.484.974 2.077-.046.135-.078.27-.099.402-.077.473.011.852.156 1.218.292.732.881 1.327 1.622 1.683.741.357 1.55.589 2.31.677.752.087 1.45.025 1.92-.227.376-.202.61-.516.71-.86l.027-.044-.001-.04c.131-.34.182-.69.215-1.012.027-.26.04-.518.07-.768.13-1.054.45-2.118.92-3.144 1.094-2.41 3.057-3.81 3.91-5.156.842-1.328 1.052-2.412.952-3.317-.245-2.207-.86-3.516-2.137-4.226-.78-.434-1.62-.624-2.49-.624-.74 0-1.49.137-2.234.351-.27-.054-.572-.139-.85-.27-.6-.275-1.078-.71-1.355-1.243-.18-.353-.27-.74-.27-1.142 0-.378.075-.74.244-1.082.183-.367.515-.74.93-.92.413-.18.85-.18 1.314 0z"/></svg>
                        Download for {{ primaryLabel }}
                    </a>
                    <div class="text-xs text-gray-500">
                        <span v-if="version">v{{ version }}</span>
                        <span v-if="version && primaryAsset.label"> - </span>
                        <span>{{ primaryAsset.label }}</span>
                        <span v-if="primaryAsset.size"> ({{ formatSize(primaryAsset.size) }})</span>
                    </div>
                </div>
                <div v-else-if="version" class="text-sm text-gray-500">
                    Pick your platform below. Current version: <span class="text-gray-300 font-semibold">v{{ version }}</span>
                </div>
                <div v-else class="text-sm text-gray-500">
                    Pick your platform below.
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                <div v-for="p in platforms" :key="p.id"
                     class="bg-black/40 backdrop-blur-sm rounded-2xl p-5 border border-white/10 hover:border-blue-500/40 transition-all">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center">
                            <svg v-if="p.id === 'windows'" class="w-7 h-7 text-blue-300" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.5l9.6-1.3v9.3H0V3.5zm0 17l9.6 1.3v-9.2H0v7.9zM10.7 22.0l12.8 1.8V12.5H10.7v9.5zm0-19.9V11.3h12.8V0L10.7 2.1z"/></svg>
                            <svg v-else-if="p.id === 'macos'" class="w-7 h-7 text-gray-200" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 12.04c.03-2.94 2.4-4.36 2.51-4.43-1.37-2-3.5-2.28-4.26-2.31-1.81-.18-3.54 1.07-4.46 1.07-.94 0-2.34-1.05-3.85-1.02-1.98.03-3.81 1.15-4.83 2.92-2.06 3.57-.53 8.85 1.48 11.74.98 1.42 2.14 3 3.66 2.95 1.47-.06 2.02-.95 3.8-.95 1.77 0 2.27.95 3.82.92 1.58-.03 2.58-1.44 3.55-2.86 1.12-1.64 1.58-3.23 1.6-3.31-.04-.02-3.05-1.17-3.08-4.64M14.13 3.84c.81-.99 1.36-2.36 1.21-3.74-1.17.05-2.6.78-3.44 1.77-.75.87-1.41 2.27-1.23 3.61 1.31.1 2.65-.66 3.46-1.64"/></svg>
                            <svg v-else-if="p.id === 'linux'" class="w-7 h-7 text-yellow-300" viewBox="0 0 24 24" fill="currentColor"><path d="M12.504 0c-.155 0-.315.008-.48.021-4.226.333-3.105 4.807-3.17 6.298-.076 1.092-.3 1.953-1.05 3.02-.885 1.051-2.127 2.75-2.716 4.521-.278.832-.41 1.684-.287 2.489.117.766.422 1.484.974 2.077-.046.135-.078.27-.099.402-.077.473.011.852.156 1.218.292.732.881 1.327 1.622 1.683.741.357 1.55.589 2.31.677.752.087 1.45.025 1.92-.227.376-.202.61-.516.71-.86l.027-.044-.001-.04c.131-.34.182-.69.215-1.012.027-.26.04-.518.07-.768.13-1.054.45-2.118.92-3.144 1.094-2.41 3.057-3.81 3.91-5.156.842-1.328 1.052-2.412.952-3.317-.245-2.207-.86-3.516-2.137-4.226-.78-.434-1.62-.624-2.49-.624-.74 0-1.49.137-2.234.351-.27-.054-.572-.139-.85-.27-.6-.275-1.078-.71-1.355-1.243-.18-.353-.27-.74-.27-1.142 0-.378.075-.74.244-1.082.183-.367.515-.74.93-.92.413-.18.85-.18 1.314 0z"/></svg>
                        </div>
                        <div>
                            <div class="text-white font-bold text-lg leading-tight">{{ p.label }}</div>
                            <div class="text-xs text-gray-500">{{ p.tagline }}</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <a v-for="a in (assets?.[p.id] || [])" :key="a.name"
                           :href="a.url"
                           class="flex items-center justify-between gap-2 p-3 rounded-lg bg-white/5 hover:bg-blue-500/10 border border-transparent hover:border-blue-500/40 transition-all group">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-white font-semibold truncate">{{ a.label }}</span>
                                    <span v-if="a.recommended" class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-500/20 text-blue-300 tracking-wider shrink-0">Best</span>
                                </div>
                                <div class="text-xs text-gray-500">{{ formatSize(a.size) }}</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-500 group-hover:text-blue-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                        <div v-if="!assets?.[p.id]?.length" class="text-xs text-gray-500 italic px-3 py-2">
                            Not available in this release.
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-12 text-sm text-gray-500">
                Need another format or an older version? See
                <a :href="releaseUrl" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300 underline">all assets on GitHub</a>.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-12">
                <div v-for="f in features" :key="f.title"
                     class="bg-black/30 backdrop-blur-sm rounded-2xl p-5 border border-white/10">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-10 h-10 rounded-lg bg-blue-500/15 flex items-center justify-center text-blue-300">
                            <svg v-if="f.icon === 'cloud-upload'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l-4-4-4 4"/><path d="M12 12v9"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                            <svg v-else-if="f.icon === 'plug'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v6"/><path d="M15 2v6"/><path d="M6 8h12v4a6 6 0 0 1-12 0V8z"/><path d="M12 18v4"/></svg>
                            <svg v-else-if="f.icon === 'list'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1.5"/><circle cx="3.5" cy="12" r="1.5"/><circle cx="3.5" cy="18" r="1.5"/></svg>
                            <svg v-else-if="f.icon === 'bell'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </div>
                        <div>
                            <div class="text-white font-semibold mb-1">{{ f.title }}</div>
                            <div class="text-sm text-gray-400 leading-relaxed">{{ f.body }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center text-sm text-gray-500 space-x-2">
                <a :href="changelogUrl" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300 underline">
                    What's new<span v-if="version"> in v{{ version }}</span>
                </a>
                <span v-if="publishedLabel">- released {{ publishedLabel }}</span>
            </div>
        </div>
    </div>
</template>
