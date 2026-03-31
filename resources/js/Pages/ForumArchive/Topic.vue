<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    topic: Object,
    forumName: String,
});
</script>

<template>
    <div class="pb-4">
        <Head :title="topic.title + ' - Forum Archive'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <Link href="/forum-archive" class="hover:text-gray-300 transition">Forum Archive</Link>
                    <span>/</span>
                    <span class="text-gray-500">{{ forumName }}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ topic.title }}</h1>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- phpBB-style topic view -->
            <div class="topic-container">
                <!-- Topic header bar -->
                <div class="topic-header">
                    <h2>{{ topic.title }}</h2>
                    <span class="post-count">{{ topic.posts.length }} posts</span>
                </div>

                <!-- Posts -->
                <div
                    v-for="(post, index) in topic.posts"
                    :key="index"
                    class="post-row"
                    :class="index % 2 === 0 ? 'post-bg1' : 'post-bg2'"
                >
                    <!-- Left: author panel -->
                    <div class="post-author-panel">
                        <div class="author-avatar">
                            {{ post.author ? post.author.charAt(0).toUpperCase() : '?' }}
                        </div>
                        <div class="author-name">{{ post.author || 'Guest' }}</div>
                    </div>

                    <!-- Right: post content -->
                    <div class="post-content-panel">
                        <div class="post-meta">
                            <span class="post-date">{{ post.date || 'Unknown date' }}</span>
                            <span class="post-number">#{{ index + 1 }}</span>
                        </div>
                        <div class="post-body" v-html="post.content"></div>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="topic-footer">
                    <Link href="/forum-archive" class="back-link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Return to Forum Archive
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.topic-container {
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    overflow: hidden;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.topic-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(30, 58, 95, 0.5);
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.topic-header h2 {
    color: #c8d6e5;
    font-size: 15px;
    font-weight: 700;
    margin: 0;
}
.post-count { color: #667; font-size: 12px; }

/* Post row - phpBB two-column layout */
.post-row {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    min-height: 100px;
}
.post-bg1 { background: rgba(255,255,255,0.01); }
.post-bg2 { background: rgba(255,255,255,0.025); }

/* Author panel (left side) */
.post-author-panel {
    width: 140px;
    flex-shrink: 0;
    padding: 12px;
    border-right: 1px solid rgba(255,255,255,0.04);
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.author-avatar {
    width: 48px;
    height: 48px;
    border-radius: 4px;
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6ea8fe;
    font-weight: 700;
    font-size: 18px;
}
.author-name {
    color: #8ab4f8;
    font-weight: 600;
    font-size: 12px;
    word-break: break-word;
}

/* Content panel (right side) */
.post-content-panel {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.post-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    background: rgba(0,0,0,0.15);
}
.post-date { color: #666; font-size: 11px; }
.post-number { color: #555; font-size: 11px; font-weight: 600; }

.post-body {
    padding: 12px 14px;
    color: #b4b4bc;
    font-size: 13px;
    line-height: 1.65;
    word-wrap: break-word;
    overflow: hidden;
}

/* Topic footer */
.topic-footer {
    padding: 10px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.2);
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #8ab4f8;
    font-size: 13px;
    text-decoration: none;
    transition: color 0.15s;
}
.back-link:hover { color: #aecbfc; }

/* Responsive */
@media (max-width: 640px) {
    .post-row { flex-direction: column; }
    .post-author-panel {
        width: 100%;
        flex-direction: row;
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        padding: 8px 12px;
        gap: 10px;
    }
    .author-avatar { width: 32px; height: 32px; font-size: 14px; }
}
</style>

<style>
/* Post content - unscoped for v-html */
.post-body a { color: #60a5fa; }
.post-body a:hover { text-decoration: underline; }
.post-body img { max-width: 100%; height: auto; border-radius: 4px; margin: 6px 0; }
.post-body blockquote {
    background: rgba(0,0,0,0.25);
    border-left: 3px solid rgba(59,130,246,0.35);
    padding: 8px 12px;
    margin: 8px 0;
    border-radius: 0 4px 4px 0;
    color: #888;
    font-size: 12px;
}
.post-body blockquote cite {
    color: #8ab4f8;
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
    font-style: normal;
    font-size: 11px;
}
.post-body .codebox, .post-body pre {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 4px;
    padding: 8px 10px;
    margin: 6px 0;
    overflow-x: auto;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    color: #67e8f9;
}
.post-body code {
    background: rgba(0,0,0,0.2);
    padding: 1px 4px;
    border-radius: 3px;
    font-family: 'Consolas', monospace;
    font-size: 12px;
    color: #67e8f9;
}
.post-body .inline-attachment { color: #555; font-size: 11px; font-style: italic; }
</style>
