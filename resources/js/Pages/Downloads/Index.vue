<script setup>
import { ref, computed, watch, getCurrentInstance, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DownloadCategoryNode from '@/Components/DownloadCategoryNode.vue';
import DefragModPanel from '@/Components/Downloads/DefragModPanel.vue';
import ServerBundlePanel from '@/Components/Downloads/ServerBundlePanel.vue';
import FamilyFriendlyPanel from '@/Components/Downloads/FamilyFriendlyPanel.vue';
import ShelfPanel from '@/Components/Downloads/ShelfPanel.vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const props = defineProps({
    tree: Array,
    current: Object,
    // Set only for a locked category, which renders as an article instead of
    // a listing. When present, `downloads` is null.
    panel: Object,
    downloads: Object,
    filters: Object,
    totalCount: Number,
});

const search = ref(props.filters.q ?? '');
const sort = ref(props.filters.sort ?? 'newest');

// Ancestors of the selected category, so its branch renders already expanded.
const openIds = computed(() => (props.current?.breadcrumb ?? []).map((c) => c.id));

// The locked categories are the ones people actually come here for - the mod,
// the server bundle - and by position they sat at the bottom, under thirty
// folders of maps and sounds. They go on top, in their own group.
//
// The three curated shelves join them and go first. They live under Bundles
// and repacks in the tree, but each is a page in its own right, so they are
// hoisted out and their parent never renders: as a node it only ever hid them
// one click deep, holding more than half the files on the site while sitting
// last in the browsable list. Whatever else is under it - Useful PK3s, a plain
// list of unrelated pk3s - drops to the bottom of the ordinary tree, which is
// where a plain list belongs.
const BUNDLES_SLUG = 'bundles-and-repacks';
const SHELF_SLUGS = ['game-bundles', 'repacks', 'upscaled-textures'];

const bundlesNode = computed(() => props.tree.find((n) => n.slug === BUNDLES_SLUG));

const pinned = computed(() => [
    ...SHELF_SLUGS
        .map((slug) => (bundlesNode.value?.children ?? []).find((c) => c.slug === slug))
        .filter(Boolean),
    ...props.tree.filter((n) => n.is_locked || n.auto_source),
]);

const browsable = computed(() => [
    ...props.tree.filter((n) => n.slug !== BUNDLES_SLUG && ! n.is_locked && ! n.auto_source),
    ...(bundlesNode.value?.children ?? []).filter((c) => ! SHELF_SLUGS.includes(c.slug)),
]);

const baseUrl = computed(() =>
    props.current ? `/downloads/${props.current.id}/${props.current.slug}` : '/downloads'
);

const reload = () => {
    router.get(
        baseUrl.value,
        {
            q: search.value || undefined,
            sort: sort.value !== 'newest' ? sort.value : undefined,
            defrag: props.filters.defrag || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(reload, 300);
});
watch(sort, reload);

// A pending debounce firing after navigation would yank the user back to
// this page's URL; category and pagination Links remount the component.
onBeforeUnmount(() => clearTimeout(timer));

const toggleDefrag = () => {
    router.get(
        baseUrl.value,
        {
            q: search.value || undefined,
            sort: sort.value !== 'newest' ? sort.value : undefined,
            defrag: props.filters.defrag ? undefined : 1,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const formatSize = (bytes) => {
    if (!bytes) return '-';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatCount = (n) => (n >= 1000 ? (n / 1000).toFixed(1) + 'k' : n ?? 0);

const entryUrl = (d) => `/downloads/entry/${d.id}/${d.slug}`;

// External entries (the mod, repacks, the setup repo) link straight out; owned
// uploads go through the file route, which signs a link and counts the hit.
const downloadUrl = (d) => (d.external_url ? d.external_url : entryUrl(d));

// One part of a folded repack. Same rule as a whole entry, but a part has its
// own id and slug, so its detail page is still reachable.
const partUrl = (p) => (p.external_url ? p.external_url : `/downloads/entry/${p.id}/${p.slug}`);

const isNew = (d) => {
    if (!d.created_at) return false;
    return (Date.now() - new Date(d.created_at).getTime()) < 14 * 86400000;
};
</script>

<template>
    <div>
        <Head title="Downloads" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90 mb-2">Downloads</h1>
                        <p class="text-sm text-gray-400">
                            {{ totalCount }} files shared by the community
                        </p>
                    </div>
                    <Link
                        v-if="$page.props.auth?.user"
                        href="/downloads/upload"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-sm font-semibold hover:bg-cyan-500/25 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                        </svg>
                        Upload
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -22rem;">
            <div class="flex flex-col lg:flex-row gap-4">

                <!-- Category tree -->
                <div class="lg:w-64 flex-shrink-0">
                    <div class="bg-black/45 backdrop-blur-xl rounded-xl overflow-hidden border border-white/[0.08] sticky top-[120px]">
                        <Link
                            href="/downloads"
                            class="flex items-center justify-between px-3 py-2.5 border-l-2 transition-all"
                            :class="!current
                                ? 'bg-cyan-500/10 border-cyan-400 text-cyan-300'
                                : 'border-transparent text-gray-400 hover:bg-cyan-500/5 hover:text-white'">
                            <span class="text-sm font-semibold">All downloads</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                  :class="!current ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-600'">
                                {{ totalCount }}
                            </span>
                        </Link>

                        <!-- The things you came for: the mod, the bundle, the
                             launcher. Kept apart from the browsable folders. -->
                        <div class="border-t border-white/5 py-1 bg-amber-500/[0.03]">
                            <DownloadCategoryNode
                                v-for="node in pinned"
                                :key="node.id"
                                :node="node"
                                :current-id="current?.id"
                                :open-ids="openIds" />

                            <!-- The launcher is not a category - it has its own
                                 page - but this is where people look for it. -->
                            <a
                                href="/launcher"
                                class="group flex items-center border-l-2 border-transparent hover:bg-cyan-500/5 hover:border-cyan-500/20 transition-all">
                                <span class="flex-shrink-0 w-5"></span>
                                <span class="flex-1 flex items-center justify-between min-w-0 py-2 pr-3">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3 h-3 flex-shrink-0 text-cyan-400/70"
                                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                        <span class="text-sm truncate text-gray-400 group-hover:text-white transition-colors">
                                            Defrag Launcher
                                        </span>
                                    </span>
                                </span>
                            </a>
                        </div>

                        <div class="border-t border-white/10 max-h-[70vh] overflow-y-auto py-1">
                            <DownloadCategoryNode
                                v-for="node in browsable"
                                :key="node.id"
                                :node="node"
                                :current-id="current?.id"
                                :open-ids="openIds" />
                        </div>
                    </div>
                </div>

                <!-- Listing -->
                <div class="flex-1 min-w-0">

                    <!-- Toolbar. Hidden for a locked category: an article has
                         nothing to search, sort or filter. -->
                    <div v-if="!panel" class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-3 mb-3">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-600"
                                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search downloads..."
                                    class="w-full bg-black/40 border border-white/10 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-gray-600 focus:border-cyan-500/50 focus:ring-0 transition-colors" />
                            </div>

                            <button
                                @click="toggleDefrag"
                                class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap"
                                :class="filters.defrag
                                    ? 'bg-cyan-500/15 border-cyan-500/40 text-cyan-300'
                                    : 'bg-black/40 border-white/10 text-gray-500 hover:text-white'">
                                DeFRaG only
                            </button>

                            <select
                                v-model="sort"
                                class="bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-300 focus:border-cyan-500/50 focus:ring-0 transition-colors">
                                <!-- The popup is drawn by the OS and ignores the
                                     wrapper's styling, so the options carry
                                     their own dark background. -->
                                <option class="bg-gray-900" value="newest">Newest</option>
                                <option class="bg-gray-900" value="popular">Most downloaded</option>
                                <option class="bg-gray-900" value="name">Name</option>
                            </select>
                        </div>
                    </div>

                    <!-- Category header -->
                    <div v-if="current" class="mb-3">
                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                            <Link href="/downloads" class="hover:text-cyan-400 transition-colors">Downloads</Link>
                            <template v-for="crumb in current.breadcrumb" :key="crumb.id">
                                <span class="text-gray-700">/</span>
                                <Link :href="`/downloads/${crumb.id}/${crumb.slug}`" class="hover:text-cyan-400 transition-colors">
                                    {{ crumb.name }}
                                </Link>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black text-white">{{ current.name }}</h2>
                            <span v-if="current.is_locked"
                                  class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                AUTO
                            </span>
                        </div>
                        <p v-if="current.description" class="text-xs text-gray-500 mt-1 max-w-3xl">
                            {{ current.description }}
                        </p>
                    </div>

                    <!-- Locked category: a self-updating article, not a listing. -->
                    <DefragModPanel v-if="panel?.type === 'defrag_mod'" :panel="panel" />
                    <ServerBundlePanel v-else-if="panel?.type === 'server_bundle'" :panel="panel" />
                    <FamilyFriendlyPanel v-else-if="panel?.type === 'family_friendly'" :panel="panel" />
                    <ShelfPanel v-else-if="panel?.type === 'shelf'" :panel="panel" />

                    <!-- Table -->
                    <div v-else-if="downloads?.data.length > 0"
                         class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 bg-white/[0.03]">
                                        <th class="font-semibold px-4 py-2.5">Name</th>
                                        <th class="font-semibold px-3 py-2.5 hidden md:table-cell">Category</th>
                                        <th class="font-semibold px-3 py-2.5 hidden lg:table-cell">Author</th>
                                        <th class="font-semibold px-3 py-2.5 hidden sm:table-cell text-right">Size</th>
                                        <th class="font-semibold px-3 py-2.5 hidden lg:table-cell text-right">Date</th>
                                        <th class="font-semibold px-3 py-2.5 text-right">DL</th>
                                        <th class="px-3 py-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="d in downloads.data"
                                        :key="d.id"
                                        class="border-t border-white/5 hover:bg-cyan-500/[0.04] transition-colors group">

                                        <td class="px-4 py-2.5 max-w-md">
                                            <Link :href="entryUrl(d)" class="flex items-center gap-2 min-w-0">
                                                <span class="text-gray-200 group-hover:text-cyan-300 transition-colors truncate font-medium">
                                                    {{ d.name }}
                                                </span>
                                                <span v-if="isNew(d)"
                                                      class="flex-shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded bg-green-500/20 text-green-400">
                                                    NEW
                                                </span>
                                                <svg v-if="d.is_locked" class="w-3 h-3 flex-shrink-0 text-amber-500/60"
                                                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                                </svg>
                                            </Link>
                                            <p v-if="d.description" class="text-xs text-gray-600 truncate mt-0.5">
                                                {{ d.description }}
                                            </p>
                                        </td>

                                        <td class="px-3 py-2.5 hidden md:table-cell">
                                            <Link v-if="d.category"
                                                  :href="`/downloads/${d.category.id}/${d.category.slug}`"
                                                  class="text-xs text-gray-500 hover:text-cyan-400 transition-colors">
                                                {{ d.category.name }}
                                            </Link>
                                        </td>

                                        <td class="px-3 py-2.5 hidden lg:table-cell">
                                            <Link v-if="d.user" :href="`/profile/${d.user.id}`"
                                                  class="text-xs text-gray-500 hover:text-white transition-colors"
                                                  v-html="q3tohtml(d.user.name)"></Link>
                                            <span v-else class="text-xs text-gray-700">system</span>
                                        </td>

                                        <td class="px-3 py-2.5 hidden sm:table-cell text-right text-xs text-gray-500 whitespace-nowrap">
                                            {{ formatSize(d.total_size) }}
                                        </td>

                                        <td class="px-3 py-2.5 hidden lg:table-cell text-right text-xs text-gray-500 whitespace-nowrap">
                                            {{ formatDate(d.created_at) }}
                                        </td>

                                        <td class="px-3 py-2.5 text-right text-xs text-gray-500 whitespace-nowrap">
                                            {{ formatCount(d.downloads_count) }}
                                        </td>

                                        <td class="px-3 py-2.5 text-right">
                                            <!-- A repack split into parts gets a
                                                 button per part instead of one
                                                 row per part. -->
                                            <div v-if="d.parts" class="inline-flex items-center justify-end gap-1 flex-wrap">
                                                <a
                                                    v-for="p in d.parts"
                                                    :key="p.id"
                                                    :href="partUrl(p)"
                                                    :target="p.external_url ? '_blank' : '_self'"
                                                    rel="noopener"
                                                    :title="`Part ${p.label} - ${formatSize(p.size)}`"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-xs font-semibold text-gray-400 hover:bg-cyan-500/15 hover:border-cyan-500/40 hover:text-cyan-300 transition-all">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                    </svg>
                                                    {{ p.label }}
                                                </a>
                                            </div>

                                            <a
                                                v-else
                                                :href="downloadUrl(d)"
                                                :target="d.external_url ? '_blank' : '_self'"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-xs font-semibold text-gray-400 hover:bg-cyan-500/15 hover:border-cyan-500/40 hover:text-cyan-300 transition-all whitespace-nowrap">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                Get
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 p-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        <p class="text-sm text-gray-500">
                            {{ filters.q ? `Nothing matches "${filters.q}".` : 'Nothing here yet.' }}
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="downloads && downloads.last_page > 1" class="flex flex-wrap justify-center gap-1 mt-3">
                        <template v-for="(link, i) in downloads.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all"
                                :class="link.active
                                    ? 'bg-cyan-500/15 border-cyan-500/40 text-cyan-300'
                                    : 'bg-black/40 border-white/10 text-gray-500 hover:text-white'"
                                v-html="link.label" />
                            <span v-else
                                  class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/5 text-gray-700"
                                  v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-4"></div>
    </div>
</template>
