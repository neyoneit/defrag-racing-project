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

const columns = computed(() => [
    { key: 'web', label: t('Website'), repo: REPOS.web, items: props.commits || [] },
    { key: 'launcher', label: t('Launcher'), repo: REPOS.launcher, items: props.launcherCommits || [] },
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
                        <h3 class="text-lg font-bold text-green-400 flex items-center gap-2 mb-3">
                            <span class="text-xl">✅</span>
                            <span>{{ col.label }}</span>
                            <span class="text-xs font-mono text-gray-500 tabular-nums">{{ col.items.length }}</span>
                        </h3>

                        <div v-if="!col.items.length" class="ml-4 text-sm text-gray-500 italic py-6">
                            {{ $t('No commits to show here yet.') }}
                        </div>
                        <div v-else class="ml-4 space-y-2 max-h-[800px] overflow-y-auto pr-3">
                            <div v-for="(commit, index) in col.items" :key="commit.hash" class="relative pl-5 border-l-2 border-green-500/20">
                                <div class="absolute left-0 top-2 w-3 h-0.5 bg-green-500/20"></div>
                                <a :href="`${col.repo}/commit/${commit.hash}`" target="_blank" class="flex items-center gap-2 px-2 py-1.5 bg-gradient-to-r from-green-500/10 to-transparent hover:from-green-500/20 rounded cursor-pointer transition-all"
                                    @mouseenter="onCommitEnter($event, commit)"
                                    @mousemove="onCommitMove"
                                    @mouseleave="onCommitLeave"
                                >
                                    <span class="text-[10px] font-mono text-gray-500 w-6 text-right flex-shrink-0">{{ col.items.length - index }}</span>
                                    <div class="w-1.5 h-1.5 bg-green-400 rounded-full flex-shrink-0"></div>
                                    <span class="text-[10px] font-mono text-gray-500 flex-shrink-0 whitespace-nowrap">{{ commit.date }}</span>
                                    <span class="text-xs font-mono text-green-400/70 flex-shrink-0">{{ commit.hash }}</span>
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
