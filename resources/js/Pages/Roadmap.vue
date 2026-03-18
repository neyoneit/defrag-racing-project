<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    commits: {
        type: Array,
        default: () => [],
    },
});

const hoveredCommit = ref(null);
const commitTooltipPos = ref({ x: 0, y: 0 });
const onCommitEnter = (e, commit) => { hoveredCommit.value = commit; commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
const onCommitMove = (e) => { commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
const onCommitLeave = () => { hoveredCommit.value = null; };

const commitItems = props.commits;
</script>

<template>
    <Head title="Roadmap - Defrag Racing" />

    <div>
        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white mb-4">Development Roadmap</h1>
                    <p class="text-xl text-gray-400">{{ commitItems.length }} commits of progress - hover for details</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 pb-12" style="margin-top: -22rem;">

            <!-- Future & Ongoing -->
            <div class="bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-5">
                <!-- FUTURE PLANNED -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-purple-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🔮</span> Future Planned
                    </h3>
                    <div class="ml-8 text-sm text-gray-400 italic">
                        <p>Future features will be determined based on community feedback, priorities, and available development time</p>
                    </div>
                </div>

                <!-- ONGOING -->
                <div>
                    <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚡</span> Ongoing
                    </h3>
                    <div class="ml-8 text-sm text-gray-400 italic">
                        <p>Current development priorities are determined by community feedback and active needs</p>
                    </div>
                </div>
            </div>

            <!-- DONE — Commits from git -->
            <div class="bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-8">
                <div>
                    <h3 class="text-xl font-bold text-green-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">✅</span> Done — {{ commitItems.length }} Commits
                    </h3>
                    <div class="ml-8 space-y-2 max-h-[800px] overflow-y-auto pr-4">
                        <div v-for="(commit, index) in commitItems" :key="commit.hash" class="relative pl-6 border-l-2 border-green-500/20">
                            <div class="absolute left-0 top-2 w-4 h-0.5 bg-green-500/20"></div>
                            <div>
                                <div class="flex items-center gap-2 px-2 py-1.5 bg-gradient-to-r from-green-500/10 to-transparent hover:from-green-500/20 rounded cursor-help transition-all"
                                    @mouseenter="onCommitEnter($event, commit)"
                                    @mousemove="onCommitMove"
                                    @mouseleave="onCommitLeave"
                                >
                                    <span class="text-[10px] font-mono text-gray-500 w-5 text-right flex-shrink-0">{{ commitItems.length - index }}</span>
                                    <div class="w-1.5 h-1.5 bg-green-400 rounded-full flex-shrink-0"></div>
                                    <span class="text-[10px] font-mono text-gray-500 w-16 flex-shrink-0">{{ commit.date }}</span>
                                    <span class="text-xs font-mono text-green-400/70">{{ commit.hash }}</span>
                                    <span class="text-xs text-gray-300 truncate flex-1">{{ commit.title }}</span>
                                    <span class="text-[10px] text-gray-500 flex-shrink-0">{{ commit.author }}</span>
                                </div>
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
