<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    forums: Array,
    totalTopics: Number,
});

const expandedForum = ref(null);

const toggleForum = (id) => {
    expandedForum.value = expandedForum.value === id ? null : id;
};
</script>

<template>
    <div class="pb-4">
        <Head title="q3df.org Forum Archive" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <span class="text-gray-400">Forum Archive</span>
                </div>
                <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">q3df.org Forum Archive</h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Preserved copy of the original q3df.org community forum. {{ totalTopics }} archived topics.
                </p>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Warning banner -->
            <div class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg px-4 py-2.5 mb-4 flex items-center gap-3 text-sm text-yellow-400">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <span>Read-only archive retrieved from the Wayback Machine. Some content may be incomplete.</span>
            </div>

            <!-- phpBB-style forum list -->
            <div class="forum-board">
                <!-- Board header -->
                <div class="board-header">
                    <div class="board-header-cell forum-col">Forum</div>
                    <div class="board-header-cell topics-col">Topics</div>
                </div>

                <!-- Forum rows -->
                <div
                    v-for="forum in forums"
                    :key="forum.id"
                    class="forum-row-wrap"
                >
                    <div class="forum-row" @click="toggleForum(forum.id)">
                        <div class="forum-icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="expandedForum === forum.id ? 'text-blue-400' : 'text-gray-500'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-2.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </div>
                        <div class="forum-info">
                            <span class="forum-name" :class="{ 'text-blue-400': expandedForum === forum.id }">{{ forum.name }}</span>
                        </div>
                        <div class="forum-stats">
                            <span>{{ forum.topic_count }}</span>
                        </div>
                        <div class="forum-expand">
                            <svg class="w-4 h-4 text-gray-600 transition-transform" :class="{ 'rotate-180': expandedForum === forum.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Topic list -->
                    <div v-if="expandedForum === forum.id" class="topic-list">
                        <div class="topic-list-header">
                            <div class="topic-list-cell topic-col">Topic</div>
                            <div class="topic-list-cell replies-col">Replies</div>
                        </div>
                        <Link
                            v-for="topic in forum.topics"
                            :key="topic.id"
                            :href="'/forum-archive/topic/' + topic.id"
                            class="topic-row"
                        >
                            <div class="topic-icon">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                            </div>
                            <div class="topic-title">{{ topic.title }}</div>
                            <div class="topic-replies">{{ topic.post_count - 1 }}</div>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center text-xs text-gray-600">
                Archive sourced from the Wayback Machine. Original forum powered by phpBB, hosted by q3df.org.
            </div>
        </div>
    </div>
</template>

<style scoped>
.forum-board {
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    overflow: hidden;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.board-header {
    display: flex;
    background: rgba(30, 58, 95, 0.5);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 8px 16px;
}
.board-header-cell {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #8ba4c4;
}
.forum-col { flex: 1; }
.topics-col { width: 80px; text-align: center; }

.forum-row {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.forum-row:hover { background: rgba(255,255,255,0.03); }
.forum-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.04);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}
.forum-info { flex: 1; min-width: 0; }
.forum-name { color: #c8d6e5; font-weight: 600; font-size: 14px; transition: color 0.15s; }
.forum-stats { width: 80px; text-align: center; color: #666; font-size: 13px; }
.forum-expand { width: 24px; text-align: center; }

/* Topic list inside expanded forum */
.topic-list {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.04);
}
.topic-list-header {
    display: flex;
    padding: 6px 16px 6px 64px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.topic-list-cell {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #556;
}
.topic-col { flex: 1; }
.replies-col { width: 80px; text-align: center; }

.topic-row {
    display: flex;
    align-items: center;
    padding: 7px 16px 7px 48px;
    border-bottom: 1px solid rgba(255,255,255,0.02);
    transition: background 0.15s;
    text-decoration: none;
}
.topic-row:hover { background: rgba(255,255,255,0.03); }
.topic-icon { width: 28px; flex-shrink: 0; }
.topic-title {
    flex: 1;
    color: #8ab4f8;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.topic-row:hover .topic-title { color: #aecbfc; }
.topic-replies { width: 80px; text-align: center; color: #555; font-size: 12px; }
</style>
