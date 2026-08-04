/**
 * Says what a demo actually is, in words.
 *
 * Everything here was already in the database - the browse list just showed
 * the raw column, so `VQ3.2` sat there meaning nothing to anyone who did not
 * already know the format. A player asked to see what a demo is before
 * spending a download on it, and this is the decoding half of that.
 */

const GAMETYPES = {
    df: { mode: 'Defrag run', online: false },
    mdf: { mode: 'Defrag run', online: true },
    fc: { mode: 'Fastcap', online: false },
    mfc: { mode: 'Fastcap', online: true },
    fs: { mode: 'Freestyle', online: false },
    mfs: { mode: 'Freestyle', online: true },
    unlagged: { mode: 'Unlagged', online: true },
};

/**
 * `gametype` is the mode word, with an `m` prefix for the online variant.
 * Unknown values keep their own spelling rather than being dropped - a demo
 * from a mode we have not seen is still worth listing.
 */
export const describeGametype = (gametype) => {
    if (!gametype) {
        return null;
    }

    const key = gametype.toLowerCase();
    const known = GAMETYPES[key];

    if (!known) {
        return {
            mode: gametype.toUpperCase(),
            online: key.startsWith('m'),
            label: gametype.toUpperCase(),
        };
    }

    return {
        ...known,
        label: `${known.mode}, ${known.online ? 'online' : 'offline'}`,
    };
};

/**
 * `physics` carries more than the physics. The parser writes the demo's
 * `<mode>.<physics>[.<variant>]` string and we keep it from the physics
 * onwards, so `CPM.TR` is a teamrun and `VQ3.2` is capture mode 2.
 */
export const describePhysics = (physics) => {
    if (!physics) {
        return null;
    }

    const [base, ...rest] = physics.split('.');
    const variant = rest.join('.').toUpperCase();
    const ctf = /^\d+$/.test(variant) ? Number(variant) : null;

    return {
        base: base.toUpperCase(),
        teamrun: variant === 'TR',
        ctf,
        raw: physics,
    };
};

/**
 * What the parser noticed about the run. Deliberately descriptive rather than
 * a verdict: the notes name the setting, and the panel around them says these
 * are the things that were flagged. Which of them voids a record is a rule,
 * and rules live on /rules, not in a tooltip.
 */
const VALIDITY_NOTES = {
    sv_cheats: 'Cheats were enabled on the server',
    pmove_fixed: 'pmove_fixed setting',
    sv_fps: 'Server tickrate',
    com_maxfps: 'Client framerate cap',
    client_finish: 'The client never registered the finish',
};

export const describeValidity = (validity) => {
    if (!validity || typeof validity !== 'object') {
        return [];
    }

    return Object.entries(validity).map(([key, value]) => ({
        key,
        // The parser stores everything as a float, so 1.0 and 120.0 come back
        // for what are conceptually a flag and a whole number.
        value: String(value).replace(/\.0$/, ''),
        note: VALIDITY_NOTES[key] ?? null,
    }));
};
