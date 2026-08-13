import { t } from '@/utils/i18n';

/**
 * The map's weapons, items and functions arrive as comma-separated codes -
 * `rl`, `ya`, `tele` - and every screen that shows them needs the same two
 * things: which icon, and what to call it.
 *
 * This used to be copied into `Servers.vue` and `MapView.vue`, one of them
 * carrying the comment "(from MapView.vue)". The copies had already drifted
 * three ways by the time they were merged here:
 *
 *   - `MapView` wrapped the weapon and item names in `t()` and `Servers` did
 *     not, so the same tooltip was translated on a map page and English on
 *     the server list.
 *   - `MapView` knew `jumppad` and `launchramp`; `Servers` did not, so a map
 *     with a jumppad drew the fallback timer icon and the raw word `jumppad`.
 *   - `MapCard` had no table at all and put the bare code in its tooltip, so
 *     hovering a card said `rl` where the map page said `Rocket Launcher`.
 *
 * The tables are built inside the functions, not at module load: `t()` reads
 * a ref, and an object built once at import would freeze whichever language
 * happened to be loaded at the time.
 *
 * Weapon and item names stay English on purpose - they are what the game
 * calls them, and a player looks them up under those names. The functions are
 * descriptions rather than proper nouns, so those are translated.
 */

const lookup = (table, abbr, fallback) => table[String(abbr).toLowerCase().trim()] ?? fallback;

export const getWeaponIcon = (abbr) => lookup({
    'gauntlet': '/images/weapons/iconw_gauntlet.svg',
    'gt': '/images/weapons/iconw_gauntlet.svg',
    'mg': '/images/weapons/iconw_machinegun.svg',
    'sg': '/images/weapons/iconw_shotgun.svg',
    'gl': '/images/weapons/iconw_grenade.svg',
    'rl': '/images/weapons/iconw_rocket.svg',
    'lg': '/images/weapons/iconw_lightning.svg',
    'rg': '/images/weapons/iconw_railgun.svg',
    'pg': '/images/weapons/iconw_plasma.svg',
    'bfg': '/images/weapons/iconw_bfg.svg',
    'grapple': '/images/weapons/iconw_grapple.svg',
    'hook': '/images/weapons/iconw_grapple.svg',
    'gh': '/images/weapons/iconw_grapple.svg',
}, abbr, '/images/weapons/iconw_gauntlet.svg');

export const getWeaponName = (abbr) => lookup({
    'gauntlet': t('Gauntlet'),
    'gt': t('Gauntlet'),
    'mg': t('Machine Gun'),
    'sg': t('Shotgun'),
    'gl': t('Grenade Launcher'),
    'rl': t('Rocket Launcher'),
    'lg': t('Lightning Gun'),
    'rg': t('Rail Gun'),
    'pg': t('Plasma Gun'),
    'bfg': t('BFG'),
    'grapple': t('Grappling Hook'),
    'hook': t('Grappling Hook'),
    'gh': t('Grappling Hook'),
}, abbr, String(abbr).toUpperCase());

export const getItemIcon = (abbr) => lookup({
    // Powerups
    'enviro': '/images/powerups/envirosuit.svg',
    'haste': '/images/powerups/haste.svg',
    'quad': '/images/powerups/quad.svg',
    'regen': '/images/powerups/regen.svg',
    'invis': '/images/powerups/invis.svg',
    'flight': '/images/powerups/flight.svg',
    // Health
    'health': '/images/items/iconh_yellow.svg',
    'smallhealth': '/images/items/iconh_green.svg',
    'bighealth': '/images/items/iconh_red.svg',
    'mega': '/images/items/iconh_mega.svg',
    'medkit': '/images/items/medkit.svg',
    // Armor
    'shard': '/images/items/iconr_shard.svg',
    'ya': '/images/items/iconr_yellow.svg',
    'ra': '/images/items/iconr_red.svg',
    // CTF
    'flag': '/images/items/iconf_blu2.svg',
}, abbr, '/images/items/iconh_yellow.svg');

export const getItemName = (abbr) => lookup({
    // Powerups
    'enviro': t('Battle Suit'),
    'haste': t('Haste'),
    'quad': t('Quad Damage'),
    'regen': t('Regeneration'),
    'invis': t('Invisibility'),
    'flight': t('Flight'),
    // Health
    'health': t('Health (+25)'),
    'smallhealth': t('Small Health (+5)'),
    'bighealth': t('Large Health (+50)'),
    'mega': t('Mega Health (+100)'),
    'medkit': t('Medkit'),
    // Armor
    'shard': t('Armor Shard (+5)'),
    'ya': t('Yellow Armor (+50)'),
    'ra': t('Red Armor (+100)'),
    // CTF
    'flag': t('Flag'),
}, abbr, abbr);

export const getFunctionIcon = (abbr) => lookup({
    'tele': '/images/functions/tele.svg',
    'teleporter': '/images/functions/teleporter.svg',
    'slick': '/images/functions/slick.svg',
    'timer': '/images/functions/timer.svg',
    'fog': '/images/functions/fog.svg',
    'water': '/images/functions/water.svg',
    'lava': '/images/functions/lava.svg',
    'moving': '/images/functions/moving.svg',
    'door': '/images/functions/door.svg',
    'button': '/images/functions/button.svg',
    'push': '/images/functions/push.svg',
    'jumppad': '/images/functions/push.svg',
    'launchramp': '/images/functions/push.svg',
    'break': '/images/functions/break.svg',
    'slime': '/images/functions/slime.svg',
    'shootergl': '/images/functions/shootergl.svg',
    'shooterpg': '/images/functions/shooterpg.svg',
    'shooterrl': '/images/functions/shooterrl.svg',
}, abbr, '/images/functions/timer.svg');

/**
 * The short names, his call: the same feature was `Slick` in the filter on
 * /maps and `Slick Surface` in the tooltip of the icon sitting next to it.
 * Two of the three places that name these already used the short form, and
 * the one place that is width-constrained is the filter sidebar with its
 * thirteen checkboxes, so the short form is what the other two adopt.
 *
 * `sound` has a name but no icon file, so it falls back like any code the
 * icon table does not know.
 */
export const getFunctionName = (abbr) => lookup({
    'tele': t('Teleporter'),
    'teleporter': t('Teleporter'),
    'slick': t('Slick'),
    'timer': t('Timer'),
    'fog': t('Fog'),
    'water': t('Water'),
    'lava': t('Lava'),
    'moving': t('Moving Object'),
    'door': t('Door'),
    'button': t('Button'),
    'sound': t('Sound'),
    'push': t('Push Trigger'),
    'jumppad': t('Jump Pad'),
    'launchramp': t('Launch Ramp'),
    'break': t('Breakable'),
    'slime': t('Slime'),
    'shootergl': t('Grenade Shooter'),
    'shooterpg': t('Plasma Shooter'),
    'shooterrl': t('Rocket Shooter'),
}, abbr, abbr);
