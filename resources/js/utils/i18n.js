import { ref } from 'vue';
import moment from 'moment';

/**
 * Translation, with the English sentence as its own key.
 *
 * `$t('Player Ranking')` renders "Player Ranking" until somebody writes
 * `"Player Ranking": "Žebříček hráčů"` into lang/cs.json, and renders it again
 * the moment that line is removed. Nothing to invent, nothing to keep in sync,
 * and - the point of the whole design - a translation that covers a tenth of
 * the site leaves the other nine tenths in readable English rather than blank
 * or showing a key. That is what makes it safe to hand a language file to the
 * community half-finished.
 *
 * The cost is that editing an English string orphans its translation. That is
 * the right way round: the orphan shows up as English, which is correct, where
 * a shared key would keep showing a translation of a sentence we no longer
 * write.
 */

// Only the file for the locale being read is ever fetched - Vite gives each
// one its own chunk, so a third language costs returning readers nothing.
const files = import.meta.glob('../../../lang/*.json');

/**
 * Relative dates ("2 days ago") are printed by moment, not by us, and moment
 * has its own translations - but it ships one file per language and loads
 * none of them by default. A new language needs its line here, or its readers
 * get a fully translated page with English dates in it. English is moment's
 * built-in and needs no entry.
 */
const MOMENT_LOCALES = {
    cs: () => import('moment/dist/locale/cs'),
    ru: () => import('moment/dist/locale/ru'),
    pl: () => import('moment/dist/locale/pl'),
    de: () => import('moment/dist/locale/de'),
    fr: () => import('moment/dist/locale/fr'),
    es: () => import('moment/dist/locale/es'),
    uk: () => import('moment/dist/locale/uk'),
    nl: () => import('moment/dist/locale/nl'),
    sv: () => import('moment/dist/locale/sv'),
};

const messages = ref({});

const locale = ref('en');

const read = async (next) => {
    const file = files[`../../../lang/${next}.json`];

    if (!file) {
        return {};
    }

    try {
        const loaded = await file();

        return loaded.default ?? loaded ?? {};
    } catch (e) {
        // A missing or broken language file must not take the page with it.
        return {};
    }
};

/** English needs no file: the keys are already English. */
export const setLocale = async (next) => {
    const target = next || 'en';

    if (target === locale.value) {
        return;
    }

    const loaded = target === 'en' ? {} : await read(target);

    const momentLocale = MOMENT_LOCALES[target];

    if (momentLocale) {
        try {
            await momentLocale();
        } catch (e) {
            // Dates in English beat a page that does not render.
        }
    }

    moment.locale(target);

    locale.value = target;
    messages.value = loaded;
};

export const currentLocale = () => locale.value;

/**
 * Placeholders are Laravel's `:name`, so one file works for both sides - the
 * same line can be read by `__()` in a mail and by `$t()` in a template.
 */
export const t = (key, replacements = {}) => {
    const line = messages.value[key];

    // An empty string is an untranslated line, not a translation to an empty
    // one - a translator working through the file leaves blanks behind.
    let out = typeof line === 'string' && line !== '' ? line : key;

    for (const [name, value] of Object.entries(replacements)) {
        out = out.split(`:${name}`).join(value);
    }

    return out;
};

/**
 * Czech counts in three: 1 server, 2 servery, 5 serverů. English counts in
 * two. Chinese counts in one.
 *
 * **A language added to config/locales.php without its branch here silently
 * loses its last form** - the clamp in tChoice hides the mistake, so it reads
 * as a wrong ending rather than as an error. Russian and Polish shipped that
 * way and printed "5 mapy" where Polish wants "5 map".
 *
 * German, Dutch, Swedish and Spanish count the way English does and need no
 * branch. French does: it takes the singular for zero as well as for one.
 *
 * The three-form Slavic languages do not share one rule. Czech and Slovak use
 * the plain 1 / 2-4 / rest. Russian, Ukrainian and Polish repeat the middle
 * form at 22, 23, 24 and the last one through the teens, and they disagree at
 * 21: Russian takes the singular ("21 карта"), Polish does not ("21 map").
 */
const pluralIndex = (code, count) => {
    const n = Math.abs(count);

    // No grammatical number at all - one form covers every count.
    if (code === 'zh' || code === 'ja' || code === 'ko') {
        return 0;
    }

    if (code === 'cs' || code === 'sk') {
        if (n === 1) return 0;
        if (n >= 2 && n <= 4) return 1;

        return 2;
    }

    // French counts zero as singular: "0 point", "1 point", "2 points".
    // English does not, so the default rule below would print "0 points".
    if (code === 'fr') {
        return n <= 1 ? 0 : 1;
    }

    if (code === 'ru' || code === 'uk' || code === 'pl') {
        const last = n % 10;
        const lastTwo = n % 100;

        // Polish keeps the singular for a bare 1 only; Russian and Ukrainian
        // take it again at 21, 31, 41...
        if (code === 'pl' ? n === 1 : (last === 1 && lastTwo !== 11)) return 0;
        if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 1;

        return 2;
    }

    return n === 1 ? 0 : 1;
};

/** Forms separated by a pipe, singular first, then the count as the argument. */
export const tChoice = (key, count, replacements = {}) => {
    const forms = t(key, { count, ...replacements }).split('|').map((form) => form.trim());

    if (forms.length === 1) {
        return forms[0];
    }

    // An English source has two forms and Czech needs three: clamp rather
    // than fall off the end, so an untranslated line still prints something.
    return forms[Math.min(pluralIndex(locale.value, count), forms.length - 1)];
};
