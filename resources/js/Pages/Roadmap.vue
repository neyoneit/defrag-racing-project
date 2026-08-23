<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/utils/i18n';

const props = defineProps({
    commits: {
        type: Array,
        default: () => [],
    },
    launcherCommits: {
        type: Array,
        default: () => [],
    },
});

const hoveredCommit = ref(null);
const commitTooltipPos = ref({ x: 0, y: 0 });
const onCommitEnter = (e, commit) => { hoveredCommit.value = commit; commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
const onCommitMove = (e) => { commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
const onCommitLeave = () => { hoveredCommit.value = null; };

// Web (this repo, read from local git log) and Launcher (separate repo,
// fetched from the GitHub API in the controller). Side by side rather than
// behind a tab: they are two different things being built, and a tab made you
// click to find out whether the other one had moved at all.
const REPOS = {
    web: 'https://github.com/defrag-racing/defrag-racing-project',
    launcher: 'https://github.com/Defrag-racing/defrag-racing-launcher',
};
const totalCommits = computed(() => (props.commits?.length ?? 0) + (props.launcherCommits?.length ?? 0));

// Each column carries its own colours as whole class names. Tailwind reads the
// source for literals, so a class glued together at runtime is one it never
// generates. Website keeps the green the commit list has always had; the
// launcher takes the blue it wears everywhere else on the site.
const THEME = {
    web: {
        bar: 'bg-emerald-500/10 border-emerald-400/25',
        title: 'text-emerald-300',
        line: 'border-emerald-500/30',
        tick: 'bg-emerald-500/30',
        dot: 'bg-emerald-400',
        hash: 'text-emerald-400/80',
        row: 'from-emerald-500/10 hover:from-emerald-500/20',
    },
    launcher: {
        bar: 'bg-blue-500/10 border-blue-400/25',
        title: 'text-blue-300',
        line: 'border-blue-500/30',
        tick: 'bg-blue-500/30',
        dot: 'bg-blue-400',
        hash: 'text-blue-400/80',
        row: 'from-blue-500/10 hover:from-blue-500/20',
    },
};

const columns = computed(() => [
    { key: 'web', label: t('Website'), repo: REPOS.web, items: props.commits || [], c: THEME.web },
    { key: 'launcher', label: t('Launcher'), repo: REPOS.launcher, items: props.launcherCommits || [], c: THEME.launcher },
]);
</script>

<template>
    <Head :title="$t('Roadmap - Defrag Racing')" />

    <div>
        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="text-center">
                    <h1 class="text-2xl md:text-3xl font-bold text-white mb-4">{{ $t('Development Roadmap') }}</h1>
                    <p class="text-xl text-gray-400">{{ $tc(':count commit of progress - hover for details|:count commits of progress - hover for details', totalCommits) }}</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" style="margin-top: -22rem;">

            <!-- Future & Ongoing -->
            <div class="bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-5">
                <!-- FUTURE PLANNED -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-purple-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🔮</span> {{ $t('Future Planned') }}
                    </h3>
                    <div class="ml-8 text-sm text-gray-400 italic">
                        <p>{{ $t('Future features will be determined based on community feedback, priorities, and available development time') }}</p>
                    </div>
                </div>

                <!-- ONGOING -->
                <div>
                    <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚡</span> {{ $t('Ongoing') }}
                    </h3>
                    <div class="ml-8 text-sm text-gray-400 italic">
                        <p>{{ $t('Current development priorities are determined by community feedback and active needs') }}</p>
                    </div>
                </div>
            </div>

            <!-- DONE - Commits from git -->
            <div class="bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-8">
                <!-- Website on the left, launcher on the right. -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <div v-for="col in columns" :key="col.key" class="min-w-0">
                        <div class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 mb-3" :class="col.c.bar">
                            <svg class="w-5 h-5 flex-shrink-0" :class="col.c.title" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <template v-if="col.key === 'web'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></template>
                                <template v-else><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></template>
                            </svg>
                            <span class="text-lg font-black" :class="col.c.title">{{ col.label }}</span>
                            <span class="ml-auto text-xs font-mono text-gray-400 tabular-nums">{{ col.items.length }}</span>
                        </div>

                        <div v-if="!col.items.length" class="ml-4 text-sm text-gray-500 italic py-6">
                            {{ $t('No commits to show here yet.') }}
                        </div>
                        <div v-else class="ml-4 space-y-2 max-h-[800px] overflow-y-auto pr-3">
                            <div v-for="(commit, index) in col.items" :key="commit.hash" class="relative pl-5 border-l-2" :class="col.c.line">
                                <div class="absolute left-0 top-2 w-3 h-0.5" :class="col.c.tick"></div>
                                <a :href="`${col.repo}/commit/${commit.hash}`" target="_blank" class="flex items-center gap-2 px-2 py-1.5 bg-gradient-to-r to-transparent rounded cursor-pointer transition-all" :class="col.c.row"
                                    @mouseenter="onCommitEnter($event, commit)"
                                    @mousemove="onCommitMove"
                                    @mouseleave="onCommitLeave"
                                >
                                    <span class="text-[10px] font-mono text-gray-500 w-6 text-right flex-shrink-0">{{ col.items.length - index }}</span>
                                    <div class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="col.c.dot"></div>
                                    <span class="text-[10px] font-mono text-gray-500 flex-shrink-0 whitespace-nowrap">{{ commit.date }}</span>
                                    <span class="text-xs font-mono flex-shrink-0" :class="col.c.hash">{{ commit.hash }}</span>
                                    <span class="text-xs text-gray-300 truncate flex-1">{{ commit.title }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commit Tooltip -->
            <Teleport to="body">
                <div
                    v-if="hoveredCommit && hoveredCommit.description"
                    class="fixed z-[9999] pointer-events-none"
                    :style="{ left: commitTooltipPos.x + 20 + 'px', top: commitTooltipPos.y - 10 + 'px' }"
                >
                    <div class="bg-gray-900 border border-green-500/30 text-gray-300 rounded-lg px-4 py-3 shadow-2xl max-w-[500px] text-xs leading-relaxed">
                        <strong class="text-green-400 block mb-1.5">{{ hoveredCommit.title }}</strong>
                        <p class="whitespace-pre-line">{{ hoveredCommit.description }}</p>
                    </div>
                </div>
            </Teleport>

        </div>
    </div>
</template>

<style scoped>
</style>
