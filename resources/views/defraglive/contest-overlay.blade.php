<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>DefragLive Contest Overlay</title>
<style>
    /* OBS Browser Source widget: transparent page, one dark glass card.
       Recommended source size: 400 x 190. */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { background: transparent; overflow: hidden; }
    body {
        font-family: 'Segoe UI', 'Noto Sans', Arial, sans-serif;
        color: #fff;
        padding: 8px;
    }
    #card {
        background: rgba(10, 12, 18, .82);
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 12px;
        padding: 10px 12px;
        max-width: 380px;
        display: none;
    }
    #head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }
    #head .t {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #a78bfa;
        white-space: nowrap;
    }
    #head .p {
        font-size: 11px;
        color: #9ca3af;
        white-space: nowrap;
    }
    .row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
    }
    .rank {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
        color: #0b0d12;
    }
    .r1 { background: #fbbf24; }
    .r2 { background: #d1d5db; }
    .r3 { background: #d97706; }
    .name {
        flex: 1;
        min-width: 0;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-shadow: 0 1px 2px rgba(0,0,0,.9);
    }
    .stat {
        flex: none;
        text-align: right;
        font-size: 12px;
        color: #d1d5db;
        font-variant-numeric: tabular-nums;
    }
    .stat b { color: #fff; font-size: 13px; }
    .stat .odds { color: #34d399; }
    /* Quake 3 name colours (^0-^8); black gets a readable fallback. */
    .q0 { color: #6b7280; } .q1 { color: #ff4d4d; } .q2 { color: #4dff4d; }
    .q3 { color: #ffe14d; } .q4 { color: #4d6bff; } .q5 { color: #4de1ff; }
    .q6 { color: #ff4dff; } .q7 { color: #ffffff; } .q8 { color: #ff8c1a; }
</style>
</head>
<body>
<div id="card">
    <div id="head">
        <span class="t">Watch contest &middot; Top 3</span>
        <span class="p" id="prize"></span>
    </div>
    <div id="rows"></div>
</div>
<script>
    // ^^ escapes a caret; ^X switches colour. Same algorithm as the site's
    // q3tohtml, self-contained so the overlay needs no app bundle.
    function q3name(name) {
        let out = '', color = '7', buf = '';
        const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const flush = () => {
            if (buf) out += '<span class="q' + (/^[0-8]$/.test(color) ? color : '7') + '">' + esc(buf) + '</span>';
            buf = '';
        };
        for (let i = 0; i < name.length; i++) {
            if (name[i] === '^') {
                if (name[i + 1] === '^') { buf += '^'; i++; }
                else { flush(); color = name[i + 1] ?? '7'; i++; }
            } else buf += name[i];
        }
        flush();
        return out;
    }

    function fmt(s) {
        const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60);
        return (h ? h + 'h ' : '') + m + 'm';
    }

    async function tick() {
        try {
            const r = await fetch('/defraglive/contest/overlay.json', { cache: 'no-store' });
            const d = await r.json();
            const card = document.getElementById('card');
            if (!d.contest || !d.top.length) { card.style.display = 'none'; return; }
            document.getElementById('prize').textContent =
                d.contest.prize_amount ? (d.contest.prize_label + ' prize') : '';
            document.getElementById('rows').innerHTML = d.top.map((e, i) =>
                '<div class="row">'
                + '<div class="rank r' + (i + 1) + '">' + (i + 1) + '</div>'
                + '<div class="name">' + q3name(e.name || '') + '</div>'
                + '<div class="stat"><b>' + fmt(e.seconds) + '</b> &middot; '
                + e.tickets + ' t &middot; <span class="odds">' + e.odds + '%</span></div>'
                + '</div>').join('');
            card.style.display = 'block';
        } catch (e) { /* keep the last good render; retry on the next tick */ }
    }

    tick();
    setInterval(tick, 20000);
</script>
</body>
</html>
