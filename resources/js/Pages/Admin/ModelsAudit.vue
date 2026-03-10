<script setup>
import { ref, computed, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    report: Object,
});

const activeTab = ref('ZIP_NEEDS_CHECK');
const searchQuery = ref('');
const hideResolved = ref(true);

// Toast notifications
const toasts = ref([]);
let toastId = 0;
const showToast = (message, type = 'success') => {
    const id = ++toastId;
    toasts.value.push({ id, message, type });
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, 5000);
};

// Compare state - reactive objects for dynamic keys
const comparing = reactive({});
const compareStatus = reactive({});
const compareResults = reactive({});
const expandedCompare = reactive({});
const savingDesc = reactive({});
const savedDesc = reactive({});
const buildingExtras = reactive({});
const builtExtras = reactive({});
const resolving = reactive({});
const batchDownloading = ref(false);
const batchProgress = ref('');
const batchInspecting = ref(false);
const batchInspectProgress = ref('');

// Dry run state
const dryRunResults = ref(null); // array of { item, actions: [], skip_reason? }
const dryRunExecuting = ref(false);
const dryRunExecProgress = ref('');

const stats = computed(() => props.report?.stats || {});
const generatedAt = computed(() => props.report?.generated_at || 'N/A');

const tabs = [
    { key: 'ZIP_NEEDS_CHECK', label: 'ZIP Needs Check', color: 'yellow' },
    { key: 'MANUAL_REVIEW', label: 'Manual Review', color: 'orange' },
    { key: 'FAILED_MANUAL', label: 'Failed Manual', color: 'pink' },
    { key: 'MISMATCH', label: 'Mismatch', color: 'red' },
    { key: 'NOT_IN_DB', label: 'Not in DB', color: 'purple' },
    { key: 'OK', label: 'OK', color: 'green' },
    { key: 'NO_MD5', label: 'Scrape Error', color: 'gray' },
    { key: 'WS_DETAIL_CHECK', label: 'WS Detail Check', color: 'cyan' },
    { key: 'MISSING_SKIN_NAME', label: 'Missing Skin Name', color: 'amber' },
];

const allResults = computed(() => props.report?.results || []);

const filteredResults = computed(() => {
    let items;
    if (activeTab.value === 'FAILED_MANUAL') {
        items = allResults.value.filter(r => r.failed_manual);
    } else if (activeTab.value === 'MANUAL_REVIEW') {
        items = allResults.value.filter(r => r.manual_review && !r.failed_manual);
    } else {
        items = allResults.value.filter(r => r.status === activeTab.value && !r.manual_review && !r.failed_manual);
    }
    if (hideResolved.value) {
        items = items.filter(r => !r.resolved);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(r =>
            (r.name && r.name.toLowerCase().includes(q)) ||
            (r.download_file && r.download_file.toLowerCase().includes(q))
        );
    }
    return items;
});

const tabCount = (key) => {
    if (key === 'WS_DETAIL_CHECK') return wsDetailLoaded.value ? wsDetailItems.value.length : '?';
    if (key === 'MISSING_SKIN_NAME') return skinNameLoaded.value ? skinNameItems.value.length : '?';
    let all;
    if (key === 'FAILED_MANUAL') {
        all = allResults.value.filter(r => r.failed_manual);
    } else if (key === 'MANUAL_REVIEW') {
        all = allResults.value.filter(r => r.manual_review && !r.failed_manual);
    } else {
        all = allResults.value.filter(r => r.status === key && !r.manual_review && !r.failed_manual);
    }
    if (hideResolved.value) return all.filter(r => !r.resolved).length;
    return all.length;
};

const colorClass = (color) => ({
    yellow: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
    orange: 'bg-orange-500/20 text-orange-400 border-orange-500/30',
    red: 'bg-red-500/20 text-red-400 border-red-500/30',
    purple: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
    green: 'bg-green-500/20 text-green-400 border-green-500/30',
    pink: 'bg-pink-500/20 text-pink-400 border-pink-500/30',
    gray: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
    cyan: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30',
    amber: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
}[color] || 'bg-gray-500/20 text-gray-400 border-gray-500/30');

const activeColorClass = (color) => ({
    yellow: 'bg-yellow-500 text-white',
    orange: 'bg-orange-500 text-white',
    red: 'bg-red-500 text-white',
    purple: 'bg-purple-500 text-white',
    green: 'bg-green-500 text-white',
    pink: 'bg-pink-500 text-white',
    gray: 'bg-gray-500 text-white',
    cyan: 'bg-cyan-500 text-white',
    amber: 'bg-amber-500 text-white',
}[color] || 'bg-gray-500 text-white');

// WS Detail Check state
const wsDetailItems = ref([]);
const wsDetailLoading = ref(false);
const wsDetailLoaded = ref(false);
const wsDetailSearch = ref('');
const wsDetailFilter = ref('all'); // all, has_ws, no_ws
const scrapingWsDetail = reactive({});
const wsBatchProgress = ref('');

const filteredWsDetailItems = computed(() => {
    let items = wsDetailItems.value;
    if (wsDetailFilter.value === 'has_ws') items = items.filter(i => i.ws_detail_url || i.guessed_ws_url);
    else if (wsDetailFilter.value === 'no_ws') items = items.filter(i => !i.ws_detail_url && !i.guessed_ws_url);
    if (wsDetailSearch.value) {
        const q = wsDetailSearch.value.toLowerCase();
        items = items.filter(i =>
            (i.name && i.name.toLowerCase().includes(q)) ||
            (i.base_model && i.base_model.toLowerCase().includes(q)) ||
            (i.ws_name && i.ws_name.toLowerCase().includes(q))
        );
    }
    return items;
});

async function loadWsDetailCheck() {
    wsDetailLoading.value = true;
    try {
        const { data } = await axios.get('/admin/models-audit/ws-detail-check');
        wsDetailItems.value = data;
        wsDetailLoaded.value = true;
    } catch (e) {
        showToast('Failed to load WS detail check: ' + e.message, 'error');
    } finally {
        wsDetailLoading.value = false;
    }
}

function getWsUrl(item) {
    if (item.ws_detail_url) {
        return item.ws_detail_url.startsWith('http') ? item.ws_detail_url : 'https://ws.q3df.org' + item.ws_detail_url;
    }
    return item.guessed_ws_url || null;
}

async function scrapeWsForModel(item) {
    const wsUrl = getWsUrl(item);
    if (!wsUrl) return;
    scrapingWsDetail[item.id] = true;
    try {
        const { data } = await axios.post(`/models/${item.id}/scrape-ws-metadata`, { url: wsUrl });
        if (data.updated) {
            // Update local state
            if (data.updated.author) item.author = data.updated.author;
            if (data.updated.name) item.name = data.updated.name;
            showToast(`Updated #${item.id}: ${JSON.stringify(data.updated)}`);
        }
    } catch (e) {
        showToast('Scrape failed: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        scrapingWsDetail[item.id] = false;
    }
}

async function batchScrapeWsDetail() {
    const items = filteredWsDetailItems.value.filter(i => getWsUrl(i) && !i.author);
    if (!items.length) { showToast('No items with WS URL to scrape', 'error'); return; }
    for (let i = 0; i < items.length; i++) {
        wsBatchProgress.value = `Scraping ${i + 1}/${items.length}: ${items[i].name}...`;
        await scrapeWsForModel(items[i]);
        await new Promise(r => setTimeout(r, 500));
    }
    wsBatchProgress.value = '';
    showToast(`Scraped ${items.length} models`);
}

// Missing Skin Name state
const skinNameItems = ref([]);
const skinNameLoading = ref(false);
const skinNameLoaded = ref(false);
const skinNameSearch = ref('');
const fixingName = reactive({});

const filteredSkinNameItems = computed(() => {
    let items = skinNameItems.value;
    if (skinNameSearch.value) {
        const q = skinNameSearch.value.toLowerCase();
        items = items.filter(i =>
            (i.name && i.name.toLowerCase().includes(q)) ||
            (i.base_model && i.base_model.toLowerCase().includes(q)) ||
            (i.skin_name && i.skin_name.toLowerCase().includes(q))
        );
    }
    return items;
});

async function loadMissingSkinNames() {
    skinNameLoading.value = true;
    try {
        const { data } = await axios.get('/admin/models-audit/missing-skins-in-name');
        skinNameItems.value = data;
        skinNameLoaded.value = true;
    } catch (e) {
        showToast('Failed to load: ' + e.message, 'error');
    } finally {
        skinNameLoading.value = false;
    }
}

async function fixSkinName(item, newName) {
    fixingName[item.id] = true;
    try {
        const { data } = await axios.post('/admin/models-audit/fix-model-name', { id: item.id, new_name: newName });
        item.name = data.new_name;
        // Remove from list since it's fixed
        skinNameItems.value = skinNameItems.value.filter(i => i.id !== item.id);
        showToast(`#${item.id}: "${data.old_name}" → "${data.new_name}"`);
    } catch (e) {
        showToast('Fix failed: ' + (e.response?.data?.message || e.message), 'error');
    } finally {
        fixingName[item.id] = false;
    }
}

async function batchFixSkinNames() {
    const items = [...filteredSkinNameItems.value];
    if (!items.length) return;
    for (let i = 0; i < items.length; i++) {
        batchProgress.value = `Fixing ${i + 1}/${items.length}: ${items[i].name}...`;
        await fixSkinName(items[i], items[i].expected_name);
        await new Promise(r => setTimeout(r, 100));
    }
    batchProgress.value = '';
    showToast(`Fixed ${items.length} model names`);
}

const getCompareKey = (item) => item.download_file;

const downloadWithProgress = (item, key) => {
    return new Promise((resolve, reject) => {
        const params = new URLSearchParams({
            download_url: item.download_url,
            download_file: item.download_file,
        });
        const es = new EventSource(`/admin/models-audit/download?${params}`);

        es.addEventListener('start', (e) => {
            const d = JSON.parse(e.data);
            compareStatus[key] = `Downloading... 0 / ${d.total_human}`;
        });

        es.addEventListener('progress', (e) => {
            const d = JSON.parse(e.data);
            if (d.percent !== null) {
                compareStatus[key] = `Downloading... ${d.downloaded_human} / ${d.percent}%`;
            } else {
                compareStatus[key] = `Downloading... ${d.downloaded_human}`;
            }
        });

        es.addEventListener('done', (e) => {
            es.close();
            resolve(JSON.parse(e.data));
        });

        es.addEventListener('error', (e) => {
            es.close();
            try {
                const d = JSON.parse(e.data);
                reject(new Error(d.error || 'Download failed'));
            } catch {
                reject(new Error('Download connection lost'));
            }
        });

        es.onerror = () => {
            es.close();
            reject(new Error('Download connection lost'));
        };
    });
};

const compareItem = async (item) => {
    const key = getCompareKey(item);
    comparing[key] = true;

    try {
        // Phase 1: Download with live progress
        compareStatus[key] = 'Connecting...';
        const dlData = await downloadWithProgress(item, key);

        if (dlData.already_cached) {
            compareStatus[key] = `Cached (${dlData.size_human}), inspecting...`;
        } else {
            compareStatus[key] = `Downloaded (${dlData.size_human}), inspecting...`;
        }

        // Phase 2: Inspect
        const { data } = await axios.post('/admin/models-audit/compare', {
            download_file: item.download_file,
            local_path: item.local_path || null,
            detail_url: item.detail_url || null,
        });
        compareResults[key] = data;
        expandedCompare[key] = true;

        showToast(data.all_identical
            ? `All files identical to DB (${data.identical_db_ids})`
            : `Inspected "${item.download_file}" successfully`
        );

        // Auto-import missing PK3s (NOT IN DB)
        const missingPk3s = [];
        (data.ws_file?.contents || []).forEach(entry => {
            if (entry.models_found?.length && !entry.is_extra) {
                const hasNew = entry.models_found.some(mf => !mf.all_in_db && !mf.error && !mf.info);
                if (hasNew) missingPk3s.push(entry.name);
            }
        });

        if (missingPk3s.length) {
            for (const pk3Name of missingPk3s) {
                compareStatus[key] = `Auto-importing ${pk3Name}...`;
                try {
                    const { data: importData } = await axios.post('/admin/models-audit/import-pk3', {
                        download_file: item.download_file,
                        pk3_name: pk3Name,
                        detail_url: item.detail_url || null,
                    });
                    showToast('Auto-imported: ' + importData.models.map(m => `#${m.id} ${m.name}`).join(', '));
                } catch (e) {
                    showToast(`Auto-import ${pk3Name} failed: ${e.response?.data?.error || e.message}`, 'error');
                }
            }

            // Re-inspect after imports
            compareStatus[key] = 'Re-inspecting after import...';
            const { data: refreshed } = await axios.post('/admin/models-audit/compare', {
                download_file: item.download_file,
                local_path: item.local_path || null,
                detail_url: item.detail_url || null,
            });
            compareResults[key] = refreshed;
        }

        // Auto-actions: save description + build extras (after imports if any)
        const finalResult = compareResults[key];
        const hasTexts = finalResult.ws_file?.contents?.some(e => e.text_content || e.pk3_text_files);
        const hasExtras = finalResult.ws_file?.contents?.some(e => e.is_extra);

        if (hasTexts) {
            compareStatus[key] = 'Saving description...';
            await saveTextToDescription(item, true);
        }
        if (hasExtras) {
            compareStatus[key] = 'Building extras ZIP...';
            await buildExtrasZip(item, true);
        }
    } catch (e) {
        compareResults[key] = {
            error: e.response?.data?.error || e.response?.data?.message || e.message || 'Unknown error',
        };
        expandedCompare[key] = true;
        showToast('Inspect failed: ' + (e.message || 'Unknown error'), 'error');
    } finally {
        comparing[key] = false;
        delete compareStatus[key];
    }
};

const toggleCompare = (item) => {
    const key = getCompareKey(item);
    expandedCompare[key] = !expandedCompare[key];
};

const hasCompareResult = (item) => {
    return getCompareKey(item) in compareResults;
};

const isComparing = (item) => {
    return !!comparing[getCompareKey(item)];
};

const isExpanded = (item) => {
    return !!expandedCompare[getCompareKey(item)];
};

const getCompareResult = (item) => {
    return compareResults[getCompareKey(item)];
};

const importing = reactive({});
const importingPk3 = reactive({});

// Classify file type for display
const fileCategory = (name) => {
    const ext = name.split('.').pop().toLowerCase();
    if (['pk3'].includes(ext)) return 'pk3';
    if (['zip'].includes(ext)) return 'archive';
    if (['txt', 'readme', 'doc', 'nfo', 'cfg'].includes(ext) || name.toLowerCase().includes('readme')) return 'junk';
    if (['jpg', 'jpeg', 'png', 'tga', 'bmp', 'gif'].includes(ext)) return 'image';
    if (['skin', 'shader', 'md3', 'wav', 'mp3'].includes(ext)) return 'model-asset';
    return 'other';
};

const resolveItem = async (item, resolution, note = '') => {
    const key = item.download_file;
    resolving[key] = true;

    try {
        await axios.post('/admin/models-audit/resolve', {
            download_file: item.download_file,
            resolution,
            note,
        });
        item.resolved = true;
        item.resolution = resolution;
        item.resolution_note = note;
        showToast(`Resolved "${item.download_file}" as ${resolution}`);
    } catch (e) {
        showToast('Failed to resolve: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        resolving[key] = false;
    }
};

const importItem = async (item) => {
    const key = item.download_file;
    if (!confirm(`Import "${item.name}" (${item.download_file}) into DB?`)) return;

    importing[key] = true;

    try {
        const { data } = await axios.post('/admin/models-audit/import', {
            download_file: item.download_file,
            name: item.name,
        });
        item.resolved = true;
        item.resolution = 'imported';
        item.resolution_note = 'Imported as: ' + data.models.map(m => `#${m.id} ${m.name}`).join(', ');
        showToast('Imported: ' + data.models.map(m => `#${m.id} ${m.name}`).join(', '));
    } catch (e) {
        showToast('Import failed: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        importing[key] = false;
    }
};

const importPk3 = async (item, pk3Name) => {
    const key = `${item.download_file}:${pk3Name}`;
    if (!confirm(`Import PK3 "${pk3Name}" from "${item.download_file}" into DB?`)) return;

    importingPk3[key] = true;

    try {
        const { data } = await axios.post('/admin/models-audit/import-pk3', {
            download_file: item.download_file,
            pk3_name: pk3Name,
            detail_url: item.detail_url || null,
        });
        showToast('Imported: ' + data.models.map(m => `#${m.id} ${m.name}`).join(', ') + (data.bundle_uuid ? ' (bundled)' : ''));

        // Re-inspect to refresh the result
        const compareKey = getCompareKey(item);
        const { data: refreshed } = await axios.post('/admin/models-audit/compare', {
            download_file: item.download_file,
            local_path: item.local_path || null,
        });
        compareResults[compareKey] = refreshed;
    } catch (e) {
        showToast('Import failed: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        importingPk3[key] = false;
    }
};

const buildExtrasZip = async (item, auto = false) => {
    const key = getCompareKey(item);
    const result = getCompareResult(item);
    if (!result) return;

    // Collect model IDs from inspect result
    const modelIds = [];
    result.ws_file.contents.forEach(entry => {
        if (entry.models_found) {
            entry.models_found.forEach(mf => {
                (mf.skins || []).forEach(sk => {
                    if (sk.db_id) modelIds.push(sk.db_id);
                });
            });
        }
    });

    // Count extra files
    const extraFiles = result.ws_file.contents.filter(e => e.is_extra);
    if (!extraFiles.length) {
        if (!auto) showToast('No extra files found in ZIP', 'error');
        return;
    }

    if (!modelIds.length) {
        if (!auto) showToast('No DB models found to assign extras to', 'error');
        return;
    }

    if (!auto && !confirm(`Build extras ZIP from ${extraFiles.length} file(s) and assign to ${modelIds.length} model(s) (${modelIds.map(id => '#' + id).join(', ')})?`)) return;

    buildingExtras[key] = true;
    try {
        const { data } = await axios.post('/admin/models-audit/build-extras-zip', {
            download_file: item.download_file,
            model_ids: modelIds,
        });
        builtExtras[key] = true;
        const msg = [`Extras ZIP (${data.file_count} files)`];
        if (data.updated.length) msg.push(`→ ${data.updated.join(', ')}`);
        if (data.skipped?.length) msg.push(`Already set: ${data.skipped.join(', ')}`);
        showToast(msg.join(' | '));
    } catch (e) {
        showToast('Failed: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        buildingExtras[key] = false;
    }
};

const saveTextToDescription = async (item, auto = false) => {
    const key = getCompareKey(item);
    const result = getCompareResult(item);
    if (!result) return;

    // Collect all text contents from ZIP entries AND from inside PK3s (deduplicated by content)
    const seenContent = new Set();
    const textParts = [];
    const addText = (label, content) => {
        const trimmed = content.trim();
        if (!trimmed || seenContent.has(trimmed)) return;
        seenContent.add(trimmed);
        textParts.push(`[${label}]\n${trimmed}`);
    };
    result.ws_file.contents.forEach(e => {
        if (e.text_content) addText(e.name, e.text_content);
        if (e.pk3_text_files) {
            e.pk3_text_files.forEach(tf => addText(`${e.name} → ${tf.name}`, tf.text_content));
        }
    });
    const texts = textParts.join('\n\n');

    if (!texts) return;

    // Collect all DB model IDs from the inspect result
    const modelIds = [];
    result.ws_file.contents.forEach(entry => {
        if (entry.models_found) {
            entry.models_found.forEach(mf => {
                (mf.skins || []).forEach(sk => {
                    if (sk.db_id) modelIds.push(sk.db_id);
                });
            });
        }
    });

    if (!modelIds.length) {
        if (!auto) showToast('No DB models found to save to', 'error');
        return;
    }

    if (!auto && !confirm(`Save text content to description of ${modelIds.length} model(s) (${modelIds.map(id => '#' + id).join(', ')})?`)) return;

    savingDesc[key] = true;
    try {
        const { data } = await axios.post('/admin/models-audit/save-description', {
            model_ids: modelIds,
            text: texts,
            append: true,
        });
        savedDesc[key] = true;
        const msg = [];
        if (data.cleaned?.length) msg.push(`Cleaned duplicates: ${data.cleaned.join(', ')}`);
        if (data.updated.length) msg.push(`Saved to ${data.updated.join(', ')}`);
        if (data.skipped?.length) msg.push(`Already has text: ${data.skipped.join(', ')}`);
        showToast(msg.join(' | ') || 'No changes needed');
    } catch (e) {
        showToast('Failed: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        savingDesc[key] = false;
    }
};

const batchPredownload = async () => {
    const items = filteredResults.value.filter(i => i.download_url && !hasCompareResult(i));
    if (!items.length) return;

    batchDownloading.value = true;
    let done = 0;
    batchProgress.value = `0/${items.length}`;

    // Download in batches of 4 concurrent
    const batchSize = 4;
    for (let i = 0; i < items.length; i += batchSize) {
        const batch = items.slice(i, i + batchSize);
        await Promise.allSettled(batch.map(async (item) => {
            const key = getCompareKey(item);
            try {
                compareStatus[key] = 'Pre-downloading...';
                await downloadWithProgress(item, key);
            } catch (e) {
                // ignore individual failures
            } finally {
                delete compareStatus[key];
                done++;
                batchProgress.value = `${done}/${items.length}`;
            }
        }));
    }

    batchDownloading.value = false;
    batchProgress.value = '';
    showToast(`Pre-downloaded ${done}/${items.length} files`);
};

const batchInspect = async () => {
    // First, fetch list of cached files from server
    let cachedFiles;
    try {
        const { data } = await axios.get('/admin/models-audit/cached-files');
        cachedFiles = new Set(data);
    } catch (e) {
        showToast('Failed to fetch cached files list', 'error');
        return;
    }

    // Only inspect cached, unresolved, not-yet-inspected items
    // On MANUAL_REVIEW tab, include manual_review items; on other tabs exclude them
    const isManualTab = activeTab.value === 'MANUAL_REVIEW';
    const isFailedManualTab = activeTab.value === 'FAILED_MANUAL';
    const allCached = filteredResults.value.filter(i => i.download_file && cachedFiles.has(i.download_file) && !hasCompareResult(i) && !i.resolved && (isManualTab || isFailedManualTab || !i.manual_review));
    if (!allCached.length) {
        showToast('No cached (pre-downloaded) items to inspect.', 'error');
        return;
    }

    batchInspecting.value = true;
    let done = 0;
    let identical = 0;
    let manualReview = 0;
    const limit = 50;
    let stopped = false;
    batchInspectProgress.value = `0/?`;

    const processResult = async (item) => {
        const result = getCompareResult(item);
        if (result && !result.error && result.all_identical) {
            identical++;
            if (!isManualTab && identical >= limit) stopped = true;
        } else if (isManualTab) {
            // On manual review tab, items that still fail → mark as failed_manual
            manualReview++;
            try {
                await axios.post('/admin/models-audit/mark-failed-manual', {
                    download_file: item.download_file,
                });
                item.failed_manual = true;
            } catch (e) { /* ignore */ }
        } else {
            // On other tabs, mark as manual_review
            manualReview++;
            try {
                await axios.post('/admin/models-audit/mark-manual-review', {
                    download_file: item.download_file,
                });
                item.manual_review = true;
            } catch (e) { /* ignore */ }
        }
        done++;
        batchInspectProgress.value = `${done} done (${identical} identical, ${manualReview} manual review)`;
    };

    // Inspect in batches of 4 concurrent
    const batchSize = 4;
    for (let i = 0; i < allCached.length && !stopped; i += batchSize) {
        const batch = allCached.slice(i, i + batchSize);
        await Promise.allSettled(batch.map(async (item) => {
            if (stopped) return;
            await compareItem(item);
            await processResult(item);
        }));
    }

    batchInspecting.value = false;
    const remaining = allCached.length - done;
    showToast(`Inspected ${done}: ${identical} identical, ${manualReview} manual review${remaining > 0 ? `, ${remaining} remaining` : ''}`);
};

// Dry run: analyze already-inspected items and report what actions would be taken
const batchDryRun = () => {
    // Only look at items that already have an inspect result
    const items = filteredResults.value.filter(i => !i.resolved && hasCompareResult(i));
    if (!items.length) {
        showToast('No inspected items to analyze. Run Inspect first.', 'error');
        return;
    }

    const results = [];

    for (const item of items) {
        const result = getCompareResult(item);
        if (!result || result.error) {
            results.push({ item, actions: [], skip_reason: result?.error || 'Inspect failed' });
            continue;
        }

        if (!result.all_identical) {
            results.push({ item, actions: [], skip_reason: 'Not all identical to DB' });
            continue;
        }

        const actions = [];

        // Check if has texts to save
        const hasTexts = result.ws_file.contents.some(e => e.text_content || e.pk3_text_files);
        if (hasTexts) {
            actions.push({ type: 'save_desc', label: 'Save text to description' });
        }

        // Check if has extras to build
        const hasExtras = result.ws_file.contents.some(e => e.is_extra);
        if (hasExtras) {
            const extraFiles = result.ws_file.contents.filter(e => e.is_extra).map(e => e.name);
            actions.push({ type: 'build_extras', label: `Build extras ZIP (${extraFiles.length} files)`, files: extraFiles });
        }

        // Always mark identical
        actions.push({ type: 'mark_identical', label: `Mark identical (DB: ${result.identical_db_ids})` });

        results.push({ item, actions, db_ids: result.identical_db_ids });
    }

    dryRunResults.value = results;

    const actionable = results.filter(r => r.actions.length > 0);
    const skipped = results.filter(r => r.skip_reason);
    showToast(`Dry run: ${actionable.length} actionable, ${skipped.length} skipped`);
};

// Execute dry run actions for all actionable items
const executeDryRun = async () => {
    if (!dryRunResults.value) return;

    const actionable = dryRunResults.value.filter(r => r.actions.length > 0);
    if (!actionable.length) return;

    dryRunExecuting.value = true;
    let done = 0;
    dryRunExecProgress.value = `0/${actionable.length}`;

    for (const entry of actionable) {
        const item = entry.item;
        const actions = entry.actions;

        // Save description (auto mode)
        if (actions.some(a => a.type === 'save_desc')) {
            await saveTextToDescription(item, true);
        }

        // Build extras ZIP (auto mode)
        if (actions.some(a => a.type === 'build_extras')) {
            await buildExtrasZip(item, true);
        }

        // Mark identical
        if (actions.some(a => a.type === 'mark_identical')) {
            const result = getCompareResult(item);
            await resolveItem(item, 'identical', 'All files identical to DB: ' + (result?.identical_db_ids || ''));
        }

        done++;
        dryRunExecProgress.value = `${done}/${actionable.length}`;
    }

    dryRunExecuting.value = false;
    dryRunResults.value = null;
    showToast(`Executed ${done} items successfully`);
};
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white">
        <Head title="Models Audit" />

        <!-- Toast notifications -->
        <div class="fixed top-4 right-4 z-50 space-y-2">
            <transition-group name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'px-4 py-3 rounded-lg shadow-lg text-sm font-medium max-w-md border',
                        toast.type === 'error'
                            ? 'bg-red-900/90 text-red-200 border-red-500/50'
                            : 'bg-green-900/90 text-green-200 border-green-500/50'
                    ]"
                >
                    {{ toast.message }}
                </div>
            </transition-group>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-black mb-2">Models Audit Report</h1>
                <p class="text-gray-400 text-sm">Generated: {{ generatedAt }}</p>
            </div>

            <!-- Stats (from report totals) -->
            <div class="flex flex-wrap gap-3 mb-4 text-xs">
                <span class="px-2 py-1 rounded bg-green-500/10 border border-green-500/20"><span class="font-bold text-green-400">{{ stats.ok || 0 }}</span> <span class="text-gray-400">OK</span></span>
                <span class="px-2 py-1 rounded bg-yellow-500/10 border border-yellow-500/20"><span class="font-bold text-yellow-400">{{ stats.zip_needs_check || 0 }}</span> <span class="text-gray-400">ZIP Check</span></span>
                <span class="px-2 py-1 rounded bg-red-500/10 border border-red-500/20"><span class="font-bold text-red-400">{{ stats.mismatch || 0 }}</span> <span class="text-gray-400">Mismatch</span></span>
                <span class="px-2 py-1 rounded bg-purple-500/10 border border-purple-500/20"><span class="font-bold text-purple-400">{{ stats.not_in_db || 0 }}</span> <span class="text-gray-400">Not in DB</span></span>
                <span class="px-2 py-1 rounded bg-gray-500/10 border border-gray-500/20"><span class="font-bold text-gray-400">{{ stats.scrape_error || 0 }}</span> <span class="text-gray-400">Errors</span></span>
                <span class="px-2 py-1 rounded bg-blue-500/10 border border-blue-500/20"><span class="font-bold text-blue-400">{{ (stats.ok || 0) + (stats.zip_needs_check || 0) + (stats.mismatch || 0) + (stats.not_in_db || 0) + (stats.scrape_error || 0) }}</span> <span class="text-gray-400">Total</span></span>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="[
                        'px-4 py-2 rounded-lg font-semibold text-sm transition-all border',
                        activeTab === tab.key
                            ? activeColorClass(tab.color)
                            : colorClass(tab.color) + ' hover:opacity-80'
                    ]"
                >
                    {{ tab.label }} ({{ tabCount(tab.key) }})
                </button>
            </div>

            <!-- Search + Hide resolved -->
            <div class="mb-6 flex items-center gap-4">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Filter by name or filename..."
                    class="w-full max-w-md bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50"
                />
                <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer whitespace-nowrap">
                    <input type="checkbox" v-model="hideResolved" class="rounded bg-white/10 border-white/20 text-blue-500" />
                    Hide resolved
                </label>
            </div>

            <!-- Results count + batch actions -->
            <div class="mb-4 flex items-center gap-3 text-sm text-gray-500">
                <span>{{ filteredResults.length }} results</span>
                <button
                    v-if="['ZIP_NEEDS_CHECK', 'OK', 'MANUAL_REVIEW', 'FAILED_MANUAL'].includes(activeTab) && filteredResults.some(i => i.download_url && !hasCompareResult(i))"
                    @click="batchPredownload"
                    :disabled="batchDownloading"
                    class="px-3 py-1 bg-yellow-500/20 text-yellow-400 rounded text-xs hover:bg-yellow-500/30 transition disabled:opacity-50 font-semibold"
                >
                    {{ batchDownloading ? `Downloading... (${batchProgress})` : `Pre-download All (${filteredResults.filter(i => i.download_url && !hasCompareResult(i)).length})` }}
                </button>
                <button
                    v-if="['ZIP_NEEDS_CHECK', 'OK', 'MANUAL_REVIEW', 'FAILED_MANUAL'].includes(activeTab) && filteredResults.some(i => i.download_url && !hasCompareResult(i))"
                    @click="batchInspect"
                    :disabled="batchInspecting"
                    class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded text-xs hover:bg-blue-500/30 transition disabled:opacity-50 font-semibold"
                >
                    {{ batchInspecting ? `Inspecting... (${batchInspectProgress})` : `Inspect All (${filteredResults.filter(i => i.download_url && !hasCompareResult(i)).length})` }}
                </button>
                <button
                    v-if="['ZIP_NEEDS_CHECK', 'OK', 'MANUAL_REVIEW', 'FAILED_MANUAL'].includes(activeTab) && filteredResults.some(i => i.download_url && !i.resolved)"
                    @click="batchDryRun"
                    :disabled="dryRunExecuting"
                    class="px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs hover:bg-emerald-500/30 transition disabled:opacity-50 font-semibold"
                >
                    {{ `Auto-Resolve Dry Run (${filteredResults.filter(i => !i.resolved && hasCompareResult(i)).length} inspected)` }}
                </button>
            </div>

            <!-- Dry Run Results Panel -->
            <div v-if="dryRunResults" class="mb-6 p-4 bg-gray-900/80 border border-emerald-500/30 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-emerald-400 font-bold text-sm">Dry Run Report</h3>
                    <div class="flex gap-2">
                        <button
                            @click="executeDryRun"
                            :disabled="dryRunExecuting || !dryRunResults.some(r => r.actions.length)"
                            class="px-3 py-1.5 bg-emerald-500/30 text-emerald-300 rounded text-xs hover:bg-emerald-500/40 transition disabled:opacity-50 font-bold"
                        >
                            {{ dryRunExecuting ? `Executing... (${dryRunExecProgress})` : `Execute All (${dryRunResults.filter(r => r.actions.length).length})` }}
                        </button>
                        <button
                            @click="dryRunResults = null"
                            class="px-3 py-1.5 bg-gray-500/20 text-gray-400 rounded text-xs hover:bg-gray-500/30 transition"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>

                <!-- Actionable items -->
                <div v-if="dryRunResults.some(r => r.actions.length)" class="mb-4">
                    <div class="text-emerald-300 text-xs font-semibold mb-2">Will auto-resolve ({{ dryRunResults.filter(r => r.actions.length).length }}):</div>
                    <div class="space-y-1.5 max-h-[400px] overflow-y-auto">
                        <div v-for="(entry, i) in dryRunResults.filter(r => r.actions.length)" :key="'dr-ok-'+i"
                            class="flex items-start gap-3 p-2 bg-emerald-500/5 border border-emerald-500/10 rounded text-xs">
                            <span class="text-white font-medium min-w-[200px] shrink-0">{{ entry.item.name }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(action, ai) in entry.actions" :key="'dr-a-'+ai"
                                    :class="[
                                        'px-1.5 py-0.5 rounded text-[10px] font-semibold',
                                        action.type === 'save_desc' ? 'bg-cyan-500/20 text-cyan-400' :
                                        action.type === 'build_extras' ? 'bg-purple-500/20 text-purple-400' :
                                        'bg-green-500/20 text-green-400'
                                    ]"
                                    :title="action.files ? action.files.join(', ') : ''"
                                >
                                    {{ action.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skipped items -->
                <div v-if="dryRunResults.some(r => r.skip_reason)">
                    <div class="text-gray-500 text-xs font-semibold mb-2">Skipped ({{ dryRunResults.filter(r => r.skip_reason).length }}):</div>
                    <div class="space-y-1 max-h-[200px] overflow-y-auto">
                        <div v-for="(entry, i) in dryRunResults.filter(r => r.skip_reason)" :key="'dr-skip-'+i"
                            class="flex items-center gap-3 p-1.5 text-xs text-gray-500">
                            <span class="min-w-[200px] shrink-0">{{ entry.item.name }}</span>
                            <span class="text-gray-600">{{ entry.skip_reason }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WS Detail Check Tab -->
            <div v-if="activeTab === 'WS_DETAIL_CHECK'" class="mb-6">
                <div v-if="!wsDetailLoaded" class="text-center py-12">
                    <button @click="loadWsDetailCheck" :disabled="wsDetailLoading"
                        class="px-6 py-3 bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 text-white rounded-lg font-semibold transition-colors">
                        {{ wsDetailLoading ? 'Loading...' : 'Load Models Missing Metadata' }}
                    </button>
                </div>
                <template v-else>
                    <div class="flex items-center gap-4 mb-4">
                        <input v-model="wsDetailSearch" type="text" placeholder="Search by name or base model..."
                            class="w-full max-w-md bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50" />
                        <select v-model="wsDetailFilter"
                            class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none">
                            <option value="all">All ({{ wsDetailItems.length }})</option>
                            <option value="has_ws">Has WS URL ({{ wsDetailItems.filter(i => i.ws_detail_url || i.guessed_ws_url).length }})</option>
                            <option value="no_ws">No WS URL ({{ wsDetailItems.filter(i => !i.ws_detail_url && !i.guessed_ws_url).length }})</option>
                        </select>
                        <button @click="batchScrapeWsDetail"
                            class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold whitespace-nowrap transition-colors cursor-pointer">
                            {{ wsBatchProgress || 'Batch Scrape All with WS URL' }}
                        </button>
                        <span class="text-sm text-gray-500">{{ filteredWsDetailItems.length }} results</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-gray-400">
                                    <th class="py-3 px-4 font-semibold w-16">#</th>
                                    <th class="py-3 px-4 font-semibold">Model</th>
                                    <th class="py-3 px-4 font-semibold">Base / Type</th>
                                    <th class="py-3 px-4 font-semibold">Author</th>
                                    <th class="py-3 px-4 font-semibold">WS Match</th>
                                    <th class="py-3 px-4 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in filteredWsDetailItems" :key="'ws-'+item.id"
                                    class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                    <td class="py-2 px-4 text-gray-500 font-mono text-xs">{{ item.id }}</td>
                                    <td class="py-2 px-4">
                                        <a :href="item.local_url" target="_blank" class="text-blue-400 hover:text-blue-300 font-medium">
                                            {{ item.name }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-4 text-gray-400 text-xs">
                                        <span class="font-mono">{{ item.base_model }}</span>
                                        <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                            :class="item.model_type === 'complete' ? 'bg-green-500/20 text-green-400' : item.model_type === 'skin' ? 'bg-blue-500/20 text-blue-400' : 'bg-yellow-500/20 text-yellow-400'">
                                            {{ item.model_type }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-4">
                                        <span v-if="item.author" class="text-green-400">{{ item.author }}</span>
                                        <span v-else class="text-red-400 text-xs">missing</span>
                                    </td>
                                    <td class="py-2 px-4">
                                        <template v-if="item.ws_detail_url">
                                            <a :href="(item.ws_detail_url.startsWith('http') ? '' : 'https://ws.q3df.org') + item.ws_detail_url"
                                                target="_blank" class="text-cyan-400 hover:text-cyan-300 text-xs">
                                                {{ item.ws_name || item.ws_download_file || 'WS page' }}
                                            </a>
                                        </template>
                                        <template v-else-if="item.guessed_ws_url">
                                            <a :href="item.guessed_ws_url" target="_blank" class="text-yellow-400 hover:text-yellow-300 text-xs">
                                                {{ item.guessed_ws_url.replace('https://ws.q3df.org', '') }}
                                            </a>
                                            <span class="text-gray-600 text-[10px] ml-1">(guessed)</span>
                                        </template>
                                        <span v-else class="text-gray-600 text-xs">no match</span>
                                    </td>
                                    <td class="py-2 px-4">
                                        <button v-if="getWsUrl(item)"
                                            @click="scrapeWsForModel(item)"
                                            :disabled="scrapingWsDetail[item.id]"
                                            class="px-3 py-1 bg-cyan-600/50 hover:bg-cyan-500 disabled:opacity-50 text-white rounded text-xs font-semibold transition-colors">
                                            {{ scrapingWsDetail[item.id] ? 'Scraping...' : 'Scrape WS' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredWsDetailItems.length === 0">
                                    <td colspan="6" class="py-8 text-center text-gray-500">No results</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <!-- Missing Skin Name Tab -->
            <div v-if="activeTab === 'MISSING_SKIN_NAME'" class="mb-6">
                <div v-if="!skinNameLoaded" class="text-center py-12">
                    <button @click="loadMissingSkinNames" :disabled="skinNameLoading"
                        class="px-6 py-3 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-lg font-semibold transition-colors">
                        {{ skinNameLoading ? 'Loading...' : 'Load Models Missing Skin in Name' }}
                    </button>
                </div>
                <template v-else>
                    <div class="flex items-center gap-4 mb-4">
                        <input v-model="skinNameSearch" type="text" placeholder="Search by name, base model, or skin..."
                            class="w-full max-w-md bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-amber-500/50" />
                        <button @click="batchFixSkinNames" :disabled="batchProgress || !filteredSkinNameItems.length"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-lg text-sm font-semibold whitespace-nowrap transition-colors">
                            {{ batchProgress || `Batch Fix All (${filteredSkinNameItems.length})` }}
                        </button>
                        <span class="text-sm text-gray-500">{{ filteredSkinNameItems.length }} results</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-gray-400">
                                    <th class="py-3 px-4 font-semibold w-16">#</th>
                                    <th class="py-3 px-4 font-semibold">Current Name</th>
                                    <th class="py-3 px-4 font-semibold">Skin</th>
                                    <th class="py-3 px-4 font-semibold">Expected Name</th>
                                    <th class="py-3 px-4 font-semibold">Type</th>
                                    <th class="py-3 px-4 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in filteredSkinNameItems" :key="'sn-'+item.id"
                                    class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                    <td class="py-2 px-4 text-gray-500 font-mono text-xs">{{ item.id }}</td>
                                    <td class="py-2 px-4">
                                        <a :href="item.local_url" target="_blank" class="text-blue-400 hover:text-blue-300 font-medium">
                                            {{ item.name }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-4 text-amber-400 font-mono text-xs">{{ item.skin_name }}</td>
                                    <td class="py-2 px-4 text-green-400 text-xs">{{ item.expected_name }}</td>
                                    <td class="py-2 px-4">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                            :class="item.model_type === 'complete' ? 'bg-green-500/20 text-green-400' : item.model_type === 'skin' ? 'bg-blue-500/20 text-blue-400' : 'bg-yellow-500/20 text-yellow-400'">
                                            {{ item.model_type }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-4 flex gap-2">
                                        <button @click="fixSkinName(item, item.expected_name)"
                                            :disabled="fixingName[item.id]"
                                            class="px-3 py-1 bg-green-600/50 hover:bg-green-500 disabled:opacity-50 text-white rounded text-xs font-semibold transition-colors">
                                            {{ fixingName[item.id] ? 'Fixing...' : 'Fix' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredSkinNameItems.length === 0">
                                    <td colspan="6" class="py-8 text-center text-gray-500">No results</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <!-- Results Table -->
            <div v-if="activeTab !== 'WS_DETAIL_CHECK' && activeTab !== 'MISSING_SKIN_NAME'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-gray-400">
                            <th class="py-3 px-4 font-semibold">#</th>
                            <th class="py-3 px-4 font-semibold">Name</th>
                            <th class="py-3 px-4 font-semibold">File</th>
                            <th class="py-3 px-4 font-semibold">WS MD5</th>
                            <th v-if="activeTab === 'MISMATCH'" class="py-3 px-4 font-semibold">Local MD5</th>
                            <th v-if="activeTab === 'MISMATCH'" class="py-3 px-4 font-semibold">Local Path</th>
                            <th v-if="activeTab === 'MISMATCH'" class="py-3 px-4 font-semibold">DB ID</th>
                            <th v-if="activeTab === 'OK'" class="py-3 px-4 font-semibold">Local Path</th>
                            <th class="py-3 px-4 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(item, index) in filteredResults" :key="index">
                            <tr :class="['border-b border-white/5 hover:bg-white/5 transition', item.resolved ? 'opacity-50' : '']">
                                <td class="py-3 px-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="py-3 px-4 font-medium text-white">
                                    {{ item.name }}
                                    <span v-if="item.resolved" class="ml-2 px-1.5 py-0.5 bg-green-500/20 text-green-400 rounded text-xs">{{ item.resolution }}</span>
                                    <span v-if="item.manual_review" class="ml-2 px-1.5 py-0.5 bg-orange-500/20 text-orange-400 rounded text-xs">manual review</span>
                                </td>
                                <td class="py-3 px-4 text-gray-300 font-mono text-xs">{{ item.download_file }}</td>
                                <td class="py-3 px-4 text-gray-500 font-mono text-xs">{{ item.ws_md5 || '-' }}</td>
                                <td v-if="activeTab === 'MISMATCH'" class="py-3 px-4 text-red-400 font-mono text-xs">{{ item.local_md5 || '-' }}</td>
                                <td v-if="activeTab === 'MISMATCH'" class="py-3 px-4 text-gray-400 font-mono text-xs">{{ item.local_path || '-' }}</td>
                                <td v-if="activeTab === 'MISMATCH'" class="py-3 px-4">
                                    <a v-if="item.db_model_id" :href="`/models/${item.db_model_id}`" target="_blank" class="text-blue-400 hover:text-blue-300">#{{ item.db_model_id }}</a>
                                </td>
                                <td v-if="activeTab === 'OK'" class="py-3 px-4 text-gray-400 font-mono text-xs">{{ item.local_path || '-' }}</td>
                                <td class="py-3 px-4">
                                    <div class="flex gap-2 flex-wrap">
                                        <!-- Compare button -->
                                        <button
                                            v-if="item.download_url && !hasCompareResult(item)"
                                            @click="compareItem(item)"
                                            :disabled="isComparing(item)"
                                            class="px-2 py-1 bg-orange-500/20 text-orange-400 rounded text-xs hover:bg-orange-500/30 transition disabled:opacity-50"
                                        >
                                            <template v-if="isComparing(item)">
                                                {{ compareStatus[getCompareKey(item)] || 'Working...' }}
                                            </template>
                                            <template v-else>
                                                Inspect
                                            </template>
                                        </button>
                                        <!-- Toggle result -->
                                        <button
                                            v-if="hasCompareResult(item)"
                                            @click="toggleCompare(item)"
                                            class="px-2 py-1 bg-orange-500/20 text-orange-400 rounded text-xs hover:bg-orange-500/30 transition"
                                        >
                                            {{ isExpanded(item) ? 'Hide' : 'Show' }} Result
                                        </button>
                                        <a v-if="item.download_url" :href="item.download_url" target="_blank" class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs hover:bg-blue-500/30 transition">
                                            Download
                                        </a>
                                        <a v-if="item.detail_url" :href="item.detail_url" target="_blank" class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs hover:bg-gray-500/30 transition">
                                            WS Page
                                        </a>
                                        <!-- Resolve buttons -->
                                        <template v-if="!item.resolved">
                                            <button
                                                v-if="hasCompareResult(item) && getCompareResult(item).all_identical"
                                                @click="resolveItem(item, 'identical', 'All files identical to DB: ' + getCompareResult(item).identical_db_ids)"
                                                :disabled="!!resolving[item.download_file] || !!savingDesc[getCompareKey(item)] || !!buildingExtras[getCompareKey(item)]"
                                                class="px-2 py-1 bg-green-500/30 text-green-300 rounded text-xs hover:bg-green-500/40 transition disabled:opacity-50 font-semibold"
                                            >
                                                {{ savingDesc[getCompareKey(item)] || buildingExtras[getCompareKey(item)] ? 'Wait...' : 'Mark Identical' }}
                                            </button>
                                            <button
                                                @click="resolveItem(item, 'duplicate', 'Inner PK3 contains same content as loose files')"
                                                :disabled="!!resolving[item.download_file]"
                                                class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs hover:bg-green-500/30 transition disabled:opacity-50"
                                            >
                                                Mark Duplicate
                                            </button>
                                            <button
                                                @click="resolveItem(item, 'ok', 'Verified correct')"
                                                :disabled="!!resolving[item.download_file]"
                                                class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs hover:bg-emerald-500/30 transition disabled:opacity-50"
                                            >
                                                Mark OK
                                            </button>
                                            <!-- Import button for NOT_IN_DB items that have been inspected -->
                                            <button
                                                v-if="activeTab === 'NOT_IN_DB' && hasCompareResult(item)"
                                                @click="importItem(item)"
                                                :disabled="!!importing[item.download_file]"
                                                class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded text-xs hover:bg-purple-500/30 transition disabled:opacity-50"
                                            >
                                                {{ importing[item.download_file] ? 'Importing...' : 'Import to DB' }}
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <!-- Compare Result Row -->
                            <tr v-if="hasCompareResult(item) && isExpanded(item)">
                                <td :colspan="activeTab === 'MISMATCH' ? 9 : activeTab === 'OK' ? 7 : 6" class="p-0">
                                    <div class="bg-gray-900/80 border-l-4 border-orange-500/50 p-4 m-2 rounded">
                                        <!-- Error -->
                                        <div v-if="getCompareResult(item).error" class="text-red-400 text-sm">
                                            Error: {{ getCompareResult(item).error }}
                                        </div>

                                        <template v-else>
                                            <!-- Status Banner -->
                                            <div v-if="getCompareResult(item).all_identical" class="mb-3 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
                                                <div class="text-green-400 font-bold">ALL MODELS IDENTICAL TO DB</div>
                                                <div class="text-green-300/70 text-xs mt-1">DB IDs: {{ getCompareResult(item).identical_db_ids }}</div>
                                            </div>
                                            <div v-else class="mb-3 p-3 bg-orange-500/10 border border-orange-500/30 rounded-lg">
                                                <div class="text-orange-400 font-bold">NOT ALL IDENTICAL</div>
                                                <div class="text-xs mt-1 space-y-0.5">
                                                    <template v-for="(entry, ei) in getCompareResult(item).ws_file.contents" :key="'banner-'+ei">
                                                        <template v-if="entry.models_found">
                                                            <template v-for="(mf, mi) in entry.models_found" :key="'banner-mf-'+mi">
                                                                <div v-if="mf.model_name">
                                                                    <span class="font-mono">{{ mf.model_name }}</span>:
                                                                    <span v-if="mf.all_identical" class="text-green-400">identical</span>
                                                                    <span v-else-if="mf.all_in_db" class="text-yellow-400">in DB but files differ</span>
                                                                    <span v-else class="text-orange-400">
                                                                        <template v-for="sk in mf.skins" :key="sk.skin">
                                                                            <span v-if="!sk.in_db" class="mr-1">{{ sk.skin }} = NOT IN DB</span>
                                                                            <span v-else-if="sk.file_comparison && sk.file_comparison.status !== 'identical'" class="mr-1">{{ sk.skin }} = differs</span>
                                                                        </template>
                                                                    </span>
                                                                </div>
                                                            </template>
                                                        </template>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- ZIP Contents Overview -->
                                            <div class="mb-3 p-3 bg-gray-800/60 border border-gray-700/50 rounded-lg text-xs">
                                                <div class="text-gray-300 font-semibold mb-2">ZIP contains {{ getCompareResult(item).ws_file.contents.length }} entries ({{ getCompareResult(item).ws_file.size_human }}):</div>
                                                <div class="space-y-1">
                                                    <div v-for="(entry, ei) in getCompareResult(item).ws_file.contents" :key="'ov-'+ei">
                                                        <div class="flex items-center gap-2 font-mono">
                                                            <span v-if="entry.type === 'archive'" class="text-yellow-400 font-semibold">{{ entry.name }}</span>
                                                            <span v-else class="text-gray-300">{{ entry.name }}</span>
                                                            <span class="text-gray-400">{{ entry.size_human }}</span>
                                                            <span v-if="entry.type === 'archive' && entry.is_extra" class="text-purple-400 font-sans">— extra (bot/support PK3)</span>
                                                            <span v-else-if="entry.type === 'archive' && entry.models_found" class="text-green-400 font-sans">— analyzed, {{ entry.models_found.length }} model(s) found</span>
                                                            <span v-else-if="entry.type === 'archive'" class="text-gray-400 font-sans">— analyzed</span>
                                                            <span v-else-if="entry.text_content" class="text-cyan-400 font-sans">— text file</span>
                                                            <span v-else-if="fileCategory(entry.name) === 'junk'" class="text-gray-400 font-sans">— ignored</span>
                                                            <span v-else-if="entry.is_extra" class="text-purple-400 font-sans">— extra file (not in PK3)</span>
                                                            <span v-else class="text-gray-400 font-sans">— not analyzed</span>
                                                        </div>
                                                        <!-- Text file content preview -->
                                                        <div v-if="entry.text_content" class="ml-4 mt-1 mb-2 p-2 bg-black/40 rounded border border-cyan-500/20 max-h-40 overflow-y-auto">
                                                            <pre class="text-cyan-200/80 text-[11px] whitespace-pre-wrap font-mono">{{ entry.text_content }}</pre>
                                                        </div>
                                                        <!-- Text files found inside PK3 -->
                                                        <div v-if="entry.pk3_text_files" v-for="tf in entry.pk3_text_files" :key="tf.name" class="ml-4 mt-1 mb-2">
                                                            <div class="text-cyan-400 text-[10px] font-semibold mb-1">{{ tf.name }} (inside PK3)</div>
                                                            <div class="p-2 bg-black/40 rounded border border-cyan-500/20 max-h-40 overflow-y-auto">
                                                                <pre class="text-cyan-200/80 text-[11px] whitespace-pre-wrap font-mono">{{ tf.text_content }}</pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Save to description button -->
                                                <div v-if="getCompareResult(item).ws_file.contents.some(e => e.text_content || e.pk3_text_files) && getCompareResult(item).all_identical" class="mt-3 pt-2 border-t border-gray-700/50">
                                                    <button
                                                        @click="saveTextToDescription(item)"
                                                        :disabled="!!savingDesc[getCompareKey(item)]"
                                                        class="px-3 py-1 bg-cyan-500/20 text-cyan-400 rounded text-xs hover:bg-cyan-500/30 transition disabled:opacity-50"
                                                    >
                                                        {{ savingDesc[getCompareKey(item)] ? 'Saving...' : 'Save text to model description' }}
                                                    </button>
                                                    <span v-if="savedDesc[getCompareKey(item)]" class="ml-2 text-green-400 text-xs">Saved!</span>
                                                </div>
                                                <!-- Build extras ZIP button -->
                                                <div v-if="getCompareResult(item).ws_file.contents.some(e => e.is_extra) && getCompareResult(item).all_identical" class="mt-2 pt-2 border-t border-gray-700/50">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <button
                                                            @click="buildExtrasZip(item)"
                                                            :disabled="!!buildingExtras[getCompareKey(item)]"
                                                            class="px-3 py-1 bg-purple-500/20 text-purple-400 rounded text-xs hover:bg-purple-500/30 transition disabled:opacity-50"
                                                        >
                                                            {{ buildingExtras[getCompareKey(item)] ? 'Building...' : 'Build extras ZIP (source files)' }}
                                                        </button>
                                                        <span v-if="builtExtras[getCompareKey(item)]" class="text-green-400 text-xs">Built!</span>
                                                    </div>
                                                    <div class="mt-1 text-gray-500 text-[10px]">
                                                        {{ getCompareResult(item).ws_file.contents.filter(e => e.is_extra).length }} non-PK3 file(s):
                                                        {{ getCompareResult(item).ws_file.contents.filter(e => e.is_extra).map(e => e.name).join(', ') }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Model Details -->
                                            <div class="mb-4">
                                                <h4 class="text-orange-400 font-semibold text-sm mb-2">Model Analysis</h4>

                                                <div class="mt-2">
                                                    <template v-for="(entry, ei) in getCompareResult(item).ws_file.contents" :key="'ws-'+ei">
                                                        <!-- Only show PK3 model analysis (skip extras like lowres variants) -->
                                                        <template v-if="entry.models_found && entry.models_found.length && !entry.is_extra">
                                                            <div class="mb-3 p-2 bg-gray-800/60 rounded border border-gray-700/50">
                                                                <div class="text-xs text-yellow-400 font-semibold mb-1">{{ entry.name }}</div>
                                                                <template v-for="(mf, mi) in entry.models_found" :key="'mf-'+mi">
                                                                    <div v-if="mf.error" class="text-red-400 text-xs">{{ mf.error }}</div>
                                                                    <div v-else-if="mf.info" class="text-gray-400 text-xs">{{ mf.info }}</div>
                                                                    <div v-else class="text-xs mb-1">
                                                                        <span class="font-semibold" :class="mf.all_identical ? 'text-green-400' : mf.all_in_db ? 'text-yellow-400' : 'text-orange-400'">
                                                                            {{ mf.model_name }}
                                                                        </span>
                                                                        <span v-if="mf.all_identical" class="ml-1 px-1 bg-green-500/20 text-green-400 rounded text-[10px]">identical</span>
                                                                        <span v-else-if="mf.all_in_db" class="ml-1 px-1 bg-yellow-500/20 text-yellow-400 rounded text-[10px]">in DB but differs</span>
                                                                        <span v-else class="ml-1 px-1 bg-orange-500/20 text-orange-400 rounded text-[10px]">new</span>
                                                                        <button
                                                                            v-if="!mf.all_in_db"
                                                                            @click="importPk3(item, entry.name)"
                                                                            :disabled="!!importingPk3[item.download_file + ':' + entry.name]"
                                                                            class="ml-2 px-1.5 py-0.5 bg-purple-500/20 text-purple-400 rounded text-[10px] hover:bg-purple-500/30 transition disabled:opacity-50 font-semibold"
                                                                        >
                                                                            {{ importingPk3[item.download_file + ':' + entry.name] ? 'Importing...' : 'Import PK3' }}
                                                                        </button>
                                                                        <div class="ml-4">
                                                                            <div v-for="(sk, si) in mf.skins" :key="'sk-'+si" class="mb-1">
                                                                                <span :class="sk.in_db ? 'text-green-400' : 'text-orange-300'">{{ sk.skin }}</span>
                                                                                <a v-if="sk.db_id" :href="'/models/' + sk.db_id" target="_blank" class="text-blue-400 hover:text-blue-300 ml-0.5">#{{ sk.db_id }} {{ sk.db_name }}</a>
                                                                                <span v-if="sk.file_comparison" class="ml-1" :class="sk.file_comparison.status === 'identical' ? 'text-green-500' : 'text-red-400'">
                                                                                    [{{ sk.file_comparison.status }}]
                                                                                </span>
                                                                                <div v-if="sk.file_comparison && sk.file_comparison.status !== 'identical' && sk.file_comparison.files" class="ml-4 mt-0.5">
                                                                                    <div v-for="(fc, fi) in sk.file_comparison.files" :key="'fc-'+fi" class="text-[11px] font-mono">
                                                                                        <span :class="{
                                                                                            'text-green-500': fc.status === 'identical',
                                                                                            'text-red-400': fc.status === 'differs',
                                                                                            'text-orange-400': fc.status === 'only_in_pk3',
                                                                                            'text-blue-400': fc.status === 'only_local',
                                                                                        }">
                                                                                            {{ fc.status === 'identical' ? '=' : fc.status === 'differs' ? '~' : fc.status === 'only_in_pk3' ? '+' : '-' }}
                                                                                        </span>
                                                                                        <span class="text-gray-400 ml-1">{{ fc.file }}</span>
                                                                                        <span v-if="fc.status === 'differs'" class="text-gray-600 ml-1">
                                                                                            (pk3:{{ fc.pk3_md5 }} local:{{ fc.local_md5 }})
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Local File Info -->
                                            <div v-if="getCompareResult(item).local_file" class="mb-4">
                                                <h4 class="text-green-400 font-semibold text-sm mb-2">Local File</h4>
                                                <div v-if="getCompareResult(item).local_file.error" class="text-red-400 text-xs">
                                                    {{ getCompareResult(item).local_file.error }}
                                                </div>
                                                <template v-else>
                                                    <div class="text-xs text-gray-400 space-y-1">
                                                        <div>Path: <span class="text-gray-300 font-mono">{{ getCompareResult(item).local_file.path }}</span></div>
                                                        <div>Size: <span class="text-gray-300">{{ getCompareResult(item).local_file.size_human }}</span></div>
                                                        <div>MD5: <span class="text-gray-300 font-mono">{{ getCompareResult(item).local_file.md5 }}</span></div>
                                                    </div>

                                                    <div class="mt-2">
                                                        <h5 class="text-gray-400 text-xs font-semibold mb-1">Contents:</h5>
                                                        <div class="bg-black/40 rounded p-2 max-h-[600px] overflow-y-auto">
                                                            <template v-for="(entry, ei) in getCompareResult(item).local_file.contents" :key="'local-'+ei">
                                                                <div class="text-xs font-mono py-0.5 flex justify-between">
                                                                    <span :class="entry.type === 'archive' ? 'text-yellow-400' : 'text-gray-300'">
                                                                        {{ entry.type === 'archive' ? '[ ' + entry.name + ' ]' : entry.name }}
                                                                    </span>
                                                                    <span class="text-gray-500 ml-4">{{ entry.size_human }}</span>
                                                                </div>
                                                                <template v-if="entry.contents">
                                                                    <div v-for="(nested, ni) in entry.contents" :key="'local-n-'+ni" class="text-xs font-mono py-0.5 flex justify-between pl-6">
                                                                        <span :class="nested.type === 'archive' ? 'text-yellow-400' : 'text-blue-300'">
                                                                            {{ nested.type === 'archive' ? '[ ' + nested.name + ' ]' : nested.name }}
                                                                        </span>
                                                                        <span class="text-gray-500 ml-4">{{ nested.size_human }}</span>
                                                                    </div>
                                                                </template>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Differences -->
                                            <div v-if="getCompareResult(item).differences && getCompareResult(item).differences.length > 0">
                                                <h4 class="text-red-400 font-semibold text-sm mb-2">Differences ({{ getCompareResult(item).differences.length }})</h4>
                                                <div class="bg-black/40 rounded p-2 max-h-[600px] overflow-y-auto">
                                                    <div v-for="(diff, di) in getCompareResult(item).differences" :key="'diff-'+di" class="text-xs font-mono py-1 border-b border-white/5 last:border-0">
                                                        <span v-if="diff.type === 'only_in_ws'" class="text-orange-400">+ WS only:</span>
                                                        <span v-else-if="diff.type === 'only_in_local'" class="text-green-400">- Local only:</span>
                                                        <span v-else class="text-red-400">~ Different:</span>
                                                        <span class="text-gray-300 ml-2">{{ diff.file }}</span>
                                                        <div v-if="diff.type === 'different'" class="pl-4 text-gray-500 mt-0.5">
                                                            WS: {{ diff.ws_size }}B ({{ diff.ws_md5 }}) | Local: {{ diff.local_size }}B ({{ diff.local_md5 }})
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else-if="getCompareResult(item).local_file && !getCompareResult(item).local_file.error && getCompareResult(item).differences">
                                                <span class="text-green-400 text-sm font-semibold">No differences found - files are identical!</span>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="filteredResults.length === 0">
                            <td colspan="8" class="py-8 text-center text-gray-500">No results found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { opacity: 0; transform: translateX(100px); }
.toast-leave-to { opacity: 0; transform: translateX(100px); }
</style>
