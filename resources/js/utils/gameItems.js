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
 * What you pick up keeps its English name; what the map does gets translated.
 * A rocket launcher and a quad are what the game calls them and what players
 * call them out loud in any language, and the filter on /maps has listed the
 * items in English all along. A door and some fog are ordinary words that
 * happen to describe the map, and reading them in English helps nobody.
 *
 * Those names are plain strings rather than t() calls, so lang:sync never
 * offers them to a translator in the first place. They were t() before, which
 * left every one of them sitting in nine language files as an invitation:
 * `Regeneration` had been translated in seven of them, `Invisibility` in five,
 * and the tooltip on a map page disagreed with the checkbox in the filter
 * beside it. Discipline was not going to hold that line, so the key is gone.
 *
 * `Flag` looked like it should be the exception, being an ordinary word rather
 * than a name from the game, right up until the nine translations were read
 * side by side: six of them say "report". The word reached a translator on its
 * own, with no hint that it names the thing you carry in CTF, and "flag" is a
 * far commoner verb in a user interface than it is a noun. So the tooltip on
 * the flag icon read Melden, Signaler, Пожаловаться. It is English with the
 * rest of them now, and the key is gone.
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
    'gauntlet': 'Gauntlet',
    'gt': 'Gauntlet',
    'mg': 'Machine Gun',
    'sg': 'Shotgun',
    'gl': 'Grenade Launcher',
    'rl': 'Rocket Launcher',
    'lg': 'Lightning Gun',
    'rg': 'Rail Gun',
    'pg': 'Plasma Gun',
    'bfg': 'BFG',
    'grapple': 'Grappling Hook',
    'hook': 'Grappling Hook',
    'gh': 'Grappling Hook',
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
    'enviro': 'Battle Suit',
    'haste': 'Haste',
    'quad': 'Quad Damage',
    'regen': 'Regeneration',
    'invis': 'Invisibility',
    'flight': 'Flight',
    // Health
    'health': 'Health (+25)',
    'smallhealth': 'Small Health (+5)',
    'bighealth': 'Large Health (+50)',
    'mega': 'Mega Health (+100)',
    'medkit': 'Medkit',
    // Armor
    'shard': 'Armor Shard (+5)',
    'ya': 'Yellow Armor (+50)',
    'ra': 'Red Armor (+100)',
    // CTF
    'flag': 'Flag',
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
