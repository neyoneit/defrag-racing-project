<script setup>
import { onMounted, ref, computed, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

// Plotly is ~3 MB; loading it from a CDN keeps the main app bundle lean
// and the browser caches it across pages. The page is graceful while
// Plotly is in flight — empty containers are placeholders.
const plotlyReady = ref(false);
const plotlyError = ref(null);

const ensurePlotly = () => new Promise((resolve, reject) => {
    if (window.Plotly) return resolve(window.Plotly);
    const existing = document.querySelector('script[data-plotly]');
    if (existing) {
        existing.addEventListener('load', () => resolve(window.Plotly));
        existing.addEventListener('error', reject);
        return;
    }
    const s = document.createElement('script');
    s.src = 'https://cdn.plot.ly/plotly-2.35.2.min.js';
    s.async = true;
    s.dataset.plotly = '1';
    s.onload = () => resolve(window.Plotly);
    s.onerror = () => reject(new Error('Failed to load Plotly from CDN'));
    document.head.appendChild(s);
});

const fmtTime = (ms) => {
    const m = Math.floor(ms / 60000);
    const s = Math.floor((ms % 60000) / 1000);
    const msr = ms % 1000;
    return (m > 0 ? String(m).padStart(2, '0') + ':' : '') +
        String(s).padStart(2, '0') + '.' + String(msr).padStart(3, '0');
};

// Theme tokens used across all charts. Matches the rest of the site
// (dark slate, purple/blue accents).
const DARK = {
    paper_bgcolor: 'rgba(0,0,0,0)',
    plot_bgcolor: 'rgba(0,0,0,0)',
    font: { color: '#cbd5e1', family: 'ui-sans-serif, system-ui' },
    // Tight margins — defaults reserve generous padding for axis titles
    // & legend that left huge dead bands around every chart. Pulling
    // them in claws back ~30% of vertical space for actual data.
    xaxis: { gridcolor: 'rgba(148,163,184,0.12)', zerolinecolor: 'rgba(148,163,184,0.2)', automargin: true },
    yaxis: { gridcolor: 'rgba(148,163,184,0.12)', zerolinecolor: 'rgba(148,163,184,0.2)', automargin: true },
    margin: { t: 4, r: 8, b: 38, l: 50 },
    legend: { orientation: 'h', y: 1.06, x: 0, xanchor: 'left', yanchor: 'bottom' },
    hovermode: 'closest',
};

const CFG = { responsive: true, displaylogo: false, modeBarButtonsToRemove: ['lasso2d', 'select2d'] };

// Plotly's default log-axis labelling shows minor ticks (2, 5) between
// powers of ten, but it strips the decade context — so a "2" between
// "10" and "100" actually means 20, "5" means 500. Confusing as hell.
// `dtick: 1` (one log10 step per major tick) plus a `,d` integer format
// gives clean 1 / 10 / 100 / 1000 labels with no minor noise.
//
// Minor gridlines fill in the "empty" space between 1–10 — without them
// the eye reads the gap as missing data, when in fact the log curve is
// just compressing 2/3/.../9 into a small visual band near the top of
// each decade. Subtle gridlines let the user see *where* those ticks
// land without re-introducing the misleading short labels.
const LOG_AXIS = {
    type: 'log',
    dtick: 1,
    tickformat: ',d',
    minor: {
        tickmode: 'auto',
        nticks: 9,
        showgrid: true,
        gridcolor: 'rgba(148,163,184,0.18)',
        gridwidth: 1,
        ticks: 'outside',
        ticklen: 4,
        tickcolor: 'rgba(148,163,184,0.5)',
    },
};

const summary = computed(() => props.stats?.summary || {});

const renderAll = async () => {
    let Plotly;
    try { Plotly = await ensurePlotly(); }
    catch (e) { plotlyError.value = e.message; return; }
    plotlyReady.value = true;
    await nextTick();

    const data = props.stats;
    if (!data) return;

    const buildHover = pts => pts.map(p =>
        `<b>${p.map}</b><br>WR ${fmtTime(p.wr_ms)} · ${p.holder || '?'}<br>released ${p.date_added}<br>finishers ${p.finishers}`
    );

    // 1. Histogram WR per physics — pre-bucket in JS rather than letting
    // Plotly histogram + log X handle it. Plotly bins in linear space
    // before applying the log transform, which produces empty bars when
    // the data spans 0.5s–1200s. Build log-spaced buckets ourselves.
    const wrBuckets = [
        [0,    1,    'sub 1s'],
        [1,    2,    '1–2s'],
        [2,    5,    '2–5s'],
        [5,    10,   '5–10s'],
        [10,   20,   '10–20s'],
        [20,   30,   '20–30s'],
        [30,   45,   '30–45s'],
        [45,   60,   '45–60s'],
        [60,   90,   '1–1.5m'],
        [90,   120,  '1.5–2m'],
        [120,  180,  '2–3m'],
        [180,  300,  '3–5m'],
        [300,  600,  '5–10m'],
        [600,  1200, '10–20m'],
        [1200, Infinity, '20m+'],
    ];
    const bucketCounts = (pts) => {
        const counts = wrBuckets.map(() => 0);
        for (const p of pts) {
            const s = p.wr_ms / 1000;
            for (let i = 0; i < wrBuckets.length; i++) {
                if (s >= wrBuckets[i][0] && s < wrBuckets[i][1]) { counts[i]++; break; }
            }
        }
        return counts;
    };
    const labels = wrBuckets.map(b => b[2]);
    Plotly.newPlot('chart-hist', [
        { x: labels, y: bucketCounts(data.cpm), type: 'bar', name: 'CPM',
          marker: { color: '#a855f7' }, opacity: 0.85 },
        { x: labels, y: bucketCounts(data.vq3), type: 'bar', name: 'VQ3',
          marker: { color: '#3b82f6' }, opacity: 0.85 },
    ], { ...DARK, barmode: 'group',
         xaxis: { ...DARK.xaxis, title: 'WR time bucket' },
         yaxis: { ...DARK.yaxis, title: 'maps' } }, CFG);

    // 2. Maps per year
    Plotly.newPlot('chart-yearly', [
        { x: data.maps_per_year.map(d => d.year), y: data.maps_per_year.map(d => d.count),
          type: 'bar', marker: { color: '#22c55e' } }
    ], { ...DARK, xaxis: { ...DARK.xaxis, title: 'year' },
         yaxis: { ...DARK.yaxis, title: 'maps released' } }, CFG);

    // 3. Active players per year
    Plotly.newPlot('chart-active-players', [
        { x: data.active_players_year.map(d => d.year), y: data.active_players_year.map(d => d.players),
          type: 'bar', marker: { color: '#f59e0b' } }
    ], { ...DARK, xaxis: { ...DARK.xaxis, title: 'year' },
         yaxis: { ...DARK.yaxis, title: 'distinct active players' } }, CFG);

    // 4. Top authors
    const authors = data.top_authors.slice(0, 25).reverse();
    Plotly.newPlot('chart-authors', [
        { y: authors.map(a => a.author), x: authors.map(a => a.count),
          type: 'bar', orientation: 'h', marker: { color: '#ec4899' } }
    ], { ...DARK, xaxis: { ...DARK.xaxis, title: 'maps' },
         yaxis: { ...DARK.yaxis, automargin: true },
         margin: { ...DARK.margin, l: 130 },
         height: Math.max(400, authors.length * 22) }, CFG);

    // 5. WR concentration (Pareto)
    const wrc = data.wr_concentration;
    Plotly.newPlot('chart-pareto', [
        { x: wrc.map((p, i) => i + 1), y: wrc.map(p => p.wrs),
          customdata: wrc.map(p => p.name),
          hovertemplate: 'Rank %{x}: %{customdata}<br>%{y} WRs<extra></extra>',
          type: 'bar', marker: { color: '#06b6d4' }, name: 'WRs' },
        { x: wrc.map((p, i) => i + 1), y: wrc.map(p => p.cumulative_pct),
          type: 'scatter', mode: 'lines+markers', name: 'cumulative %',
          yaxis: 'y2', line: { color: '#facc15' } },
    ], { ...DARK,
         xaxis: { ...DARK.xaxis, title: 'rank (top 30 WR holders)' },
         yaxis: { ...DARK.yaxis, title: 'WRs held' },
         yaxis2: { title: 'cumulative %', overlaying: 'y', side: 'right',
                   gridcolor: 'transparent', range: [0, 100] },
    }, CFG);

    // 6. WRs by country (choropleth)
    Plotly.newPlot('chart-countries', [
        { type: 'choropleth', locationmode: 'ISO-3', locations: data.wrs_by_country.map(c => iso2to3(c.country)),
          z: data.wrs_by_country.map(c => c.wrs),
          text: data.wrs_by_country.map(c => `${c.country}: ${c.wrs} WRs`),
          colorscale: [[0, '#1e293b'], [0.2, '#1e40af'], [0.5, '#7c3aed'], [1, '#f43f5e']],
          showscale: true,
          marker: { line: { color: 'rgba(148,163,184,0.3)', width: 0.4 } } }
    ], { ...DARK,
         geo: { showframe: false, showcoastlines: true, coastlinecolor: 'rgba(148,163,184,0.2)',
                projection: { type: 'natural earth' }, bgcolor: 'rgba(0,0,0,0)',
                landcolor: 'rgba(30,41,59,0.4)' } }, CFG);

    // 7. Activity heatmap (records set per month)
    const activity = data.activity_by_month;
    const yrs = [...new Set(activity.map(a => a.ym.slice(0, 4)))].sort();
    const mos = ['01','02','03','04','05','06','07','08','09','10','11','12'];
    const z = yrs.map(y => mos.map(m => {
        const row = activity.find(a => a.ym === `${y}-${m}`);
        return row ? row.count : 0;
    }));
    Plotly.newPlot('chart-heatmap', [
        { z, x: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
          y: yrs, type: 'heatmap',
          colorscale: [[0, '#0b1020'], [0.1, '#1e293b'], [0.3, '#1e40af'], [0.7, '#a855f7'], [1, '#f43f5e']],
          hovertemplate: '%{y} %{x}: %{z} records<extra></extra>' }
    ], { ...DARK, height: Math.max(400, yrs.length * 22),
         xaxis: { ...DARK.xaxis, side: 'top' },
         yaxis: { ...DARK.yaxis, autorange: 'reversed' } }, CFG);

    // 8. Records per player histogram
    Plotly.newPlot('chart-records-per-player', [
        { x: data.records_per_player.map(b => b.bucket),
          y: data.records_per_player.map(b => b.players),
          type: 'bar', marker: { color: '#14b8a6' },
          hovertemplate: '%{x} records: %{y} players<extra></extra>' }
    ], { ...DARK, xaxis: { ...DARK.xaxis, title: 'records per player' },
         yaxis: { ...DARK.yaxis, title: 'players (log)', ...LOG_AXIS } }, CFG);

    // 9. Weapons per year — line chart (not stacked, since a single
    // map can carry multiple weapons; stacking would double-count).
    // Each line shows the % of that year's new maps that include
    // the given weapon.
    const wy = data.weapons_by_year;
    const pct = (key) => wy.map(w => w.total ? (w[key] / w.total * 100) : 0);
    const weaponSeries = [
        ['rocket',     '#ef4444'],
        ['plasma',     '#22c55e'],
        ['grenade',    '#f59e0b'],
        ['rail',       '#facc15'],
        ['shotgun',    '#fb923c'],
        ['bfg',        '#8b5cf6'],
        ['lg',         '#06b6d4'],
        ['hook',       '#ec4899'],
        ['gauntlet',   '#a3a3a3'],
        ['machinegun', '#14b8a6'],
    ];
    Plotly.newPlot('chart-weapons',
        weaponSeries.map(([key, color]) => ({
            x: wy.map(w => w.year),
            y: pct(key),
            name: key,
            type: 'scatter',
            mode: 'lines',
            line: { color, width: 2 },
            hovertemplate: `${key}: %{y:.1f}%<extra></extra>`,
        })),
        { ...DARK, xaxis: { ...DARK.xaxis, title: 'year' },
          yaxis: { ...DARK.yaxis, title: '% of new maps including this weapon', ticksuffix: '%' } },
        CFG
    );

    // 10. Scatter: release date × WR time (the big one — webgl-backed).
    //
    // Floor at 1s (sub-second WRs are demo/scoreboard bugs) AND cap
    // at the 99th percentile to keep the meaningful 1–100s mass from
    // being squashed by the handful of clearly-broken 10000s+ entries
    // (3+ hour "WRs" = abandoned runs, scoreboard bugs). The cap is
    // rounded up to the next decade so axis labels stay clean.
    const allWrSec = [...data.cpm, ...data.vq3]
        .map(p => p.wr_ms / 1000)
        .filter(v => v >= 1)
        .sort((a, b) => a - b);
    const wrP99 = allWrSec[Math.floor(allWrSec.length * 0.99)] ?? 1000;
    const wrCapSec = Math.pow(10, Math.ceil(Math.log10(wrP99)));
    Plotly.newPlot('chart-release-vs-wr', ['cpm','vq3'].map(phys => ({
        x: data[phys].map(p => p.date_added),
        y: data[phys].map(p => Math.min(wrCapSec, Math.max(1, p.wr_ms / 1000))),
        customdata: buildHover(data[phys]),
        hovertemplate: '%{customdata}<extra></extra>',
        mode: 'markers', type: 'scattergl', name: phys.toUpperCase(),
        marker: { size: 4, opacity: 0.55, color: phys === 'cpm' ? '#a855f7' : '#3b82f6' },
    })), { ...DARK,
           xaxis: { ...DARK.xaxis, title: 'map release date', type: 'date' },
           yaxis: { ...DARK.yaxis, title: `WR time (seconds, log; capped at ${wrCapSec})`, ...LOG_AXIS,
                    range: [0, Math.log10(wrCapSec) + 0.02], autorange: false } }, CFG);

    // 11. Scatter: release × #finishers
    //
    // Finisher counts are integers, so plain log scale draws hard
    // bands at 1, 2, 3, ... — sharp horizontal stripes that mask
    // overplot density. Solve it with a multiplicative jitter that's
    // constant-width in log space (±0.18 log10), so a "1" smears to
    // [1.0, 1.51], a "2" to [1.32, 3.02], etc., with the next
    // integer's smear overlapping the previous one. Bands dissolve
    // into clouds at every magnitude.
    //
    // Clamp the floor to 1.0 so jittered values never fall below the
    // bottom axis label (avoids a phantom "gap" between "1" and "2"
    // when the smear of "1" spills below into the 0.x decade).
    const logJitter = (v) => {
        if (!v || v < 1) return 1;
        const offset = (Math.random() * 2 - 1) * 0.18;
        return Math.max(1, v * Math.pow(10, offset));
    };
    const finishersMax = Math.max(
        ...data.cpm.map(p => p.finishers || 1),
        ...data.vq3.map(p => p.finishers || 1)
    );
    Plotly.newPlot('chart-release-vs-finishers', ['cpm','vq3'].map(phys => ({
        x: data[phys].map(p => p.date_added),
        y: data[phys].map(p => logJitter(p.finishers)),
        customdata: buildHover(data[phys]),
        hovertemplate: '%{customdata}<extra></extra>',
        mode: 'markers', type: 'scattergl', name: phys.toUpperCase(),
        marker: { size: 4, opacity: 0.55, color: phys === 'cpm' ? '#a855f7' : '#3b82f6' },
    })), { ...DARK,
           xaxis: { ...DARK.xaxis, title: 'map release date', type: 'date' },
           yaxis: { ...DARK.yaxis, title: 'unique finishers (log)', ...LOG_AXIS,
                    range: [0, Math.log10(finishersMax) + 0.05], autorange: false } }, CFG);

    // 12. Scatter: release × WR set date
    Plotly.newPlot('chart-release-vs-wrdate', ['cpm','vq3'].map(phys => ({
        x: data[phys].map(p => p.date_added),
        y: data[phys].map(p => p.date_set),
        customdata: buildHover(data[phys]),
        hovertemplate: '%{customdata}<extra></extra>',
        mode: 'markers', type: 'scattergl', name: phys.toUpperCase(),
        marker: { size: 4, opacity: 0.55, color: phys === 'cpm' ? '#a855f7' : '#3b82f6' },
    })), { ...DARK,
           xaxis: { ...DARK.xaxis, title: 'map release date', type: 'date' },
           yaxis: { ...DARK.yaxis, title: 'WR set date', type: 'date' } }, CFG);
};

// Plotly's choropleth wants ISO-3 codes; the records table has ISO-2.
// This is a tiny stub for the most common Defrag countries — anything
// missing falls through and is silently skipped from the map (it'll
// still show in the country leaderboard if present).
const ISO_MAP = {
    AT:'AUT',AU:'AUS',BE:'BEL',BG:'BGR',BR:'BRA',BY:'BLR',CA:'CAN',CH:'CHE',CL:'CHL',CN:'CHN',
    CZ:'CZE',DE:'DEU',DK:'DNK',EE:'EST',ES:'ESP',FI:'FIN',FR:'FRA',GB:'GBR',GR:'GRC',HR:'HRV',
    HU:'HUN',ID:'IDN',IE:'IRL',IL:'ISR',IT:'ITA',JP:'JPN',KR:'KOR',KZ:'KAZ',LT:'LTU',LV:'LVA',
    MD:'MDA',MX:'MEX',NL:'NLD',NO:'NOR',NZ:'NZL',PE:'PER',PH:'PHL',PL:'POL',PT:'PRT',RO:'ROU',
    RS:'SRB',RU:'RUS',SE:'SWE',SG:'SGP',SI:'SVN',SK:'SVK',TH:'THA',TR:'TUR',TW:'TWN',UA:'UKR',
    US:'USA',VN:'VNM',ZA:'ZAF',
};
const iso2to3 = (c) => ISO_MAP[c] || c;

onMounted(renderAll);
</script>

<template>
    <Head title="Map Statistics" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-black text-white mb-2">Map Statistics</h1>
            <p class="text-gray-400 mb-6 text-sm">
                Aggregates across the whole defrag.racing dataset. Updated every six hours
                from the live records database.
            </p>

            <!-- Top-level summary chips -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-2xl font-bold text-white">{{ summary.total_maps?.toLocaleString() }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Maps</div>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-2xl font-bold text-white">{{ summary.total_records?.toLocaleString() }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Records</div>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-2xl font-bold text-white">{{ summary.total_wrs?.toLocaleString() }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Top-1 runs</div>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-2xl font-bold text-white">{{ summary.total_authors?.toLocaleString() }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Mappers</div>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-2xl font-bold text-white">{{ summary.total_players?.toLocaleString() }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Players</div>
                </div>
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <div class="text-sm font-bold text-white">{{ summary.first_record }}</div>
                    <div class="text-xs text-gray-400 uppercase tracking-wider">First record</div>
                </div>
            </div>

            <div v-if="plotlyError" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-red-300 mb-6">
                Failed to load charts: {{ plotlyError }}. Check your network connection or try refreshing.
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Histograms / bar charts in 2-col -->
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <h2 class="text-base font-bold text-white mb-1">WR time distribution</h2>
                    <p class="text-xs text-gray-500 mb-2">Per-map fastest run, log-scale X. CPM (purple) vs VQ3 (blue).</p>
                    <div id="chart-hist" style="height: 360px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <h2 class="text-base font-bold text-white mb-1">Maps released per year</h2>
                    <p class="text-xs text-gray-500 mb-2">When was the map library built?</p>
                    <div id="chart-yearly" style="height: 360px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <h2 class="text-base font-bold text-white mb-1">Distinct active players per year</h2>
                    <p class="text-xs text-gray-500 mb-2">Players who set at least one record that year.</p>
                    <div id="chart-active-players" style="height: 360px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl">
                    <h2 class="text-base font-bold text-white mb-1">Records per player</h2>
                    <p class="text-xs text-gray-500 mb-2">Long-tail distribution — most players have a handful, a few have thousands.</p>
                    <div id="chart-records-per-player" style="height: 360px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Weapon presence in new maps</h2>
                    <p class="text-xs text-gray-500 mb-2">% of maps released each year that include each weapon.</p>
                    <div id="chart-weapons" style="height: 360px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Top mappers</h2>
                    <p class="text-xs text-gray-500 mb-2">25 most prolific authors by visible map count.</p>
                    <div id="chart-authors" style="min-height: 600px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">WR concentration (top 30)</h2>
                    <p class="text-xs text-gray-500 mb-2">Bar = WRs held, line = cumulative % of all WRs.</p>
                    <div id="chart-pareto" style="height: 420px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">WRs by country</h2>
                    <p class="text-xs text-gray-500 mb-2">Choropleth of map-best-time holders.</p>
                    <div id="chart-countries" style="height: 480px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Activity heatmap</h2>
                    <p class="text-xs text-gray-500 mb-2">Records set per month, per year. GitHub-style — the brighter the busier.</p>
                    <div id="chart-heatmap" style="min-height: 600px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Map release date × WR time</h2>
                    <p class="text-xs text-gray-500 mb-2">Each dot is one map. Hover for details.</p>
                    <div id="chart-release-vs-wr" style="height: 540px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Map release date × number of finishers</h2>
                    <p class="text-xs text-gray-500 mb-2">Older maps tend to have more finishers, but viral newer maps stand out.</p>
                    <div id="chart-release-vs-finishers" style="height: 540px"></div>
                </div>

                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-3 shadow-2xl lg:col-span-2">
                    <h2 class="text-base font-bold text-white mb-1">Map release date × WR set date</h2>
                    <p class="text-xs text-gray-500 mb-2">Diagonal-ish — outliers above the line are late discoveries (WR set years after release).</p>
                    <div id="chart-release-vs-wrdate" style="height: 540px"></div>
                </div>
            </div>

        <p class="text-center text-xs text-gray-600 mt-8">
            Generated {{ summary.last_record }} · cache refresh every 6h
        </p>
    </div>
</template>
