import { ref } from 'vue';

/**
 * How a run time is written, site-wide.
 *
 * Defrag's own timer prints MM:SS:mmm, and that is what the site has always
 * shown, but plenty of the community reads a colon before the milliseconds as
 * a third clock field and expects a decimal point there instead. It is a
 * preference rather than a correctness question, so it is one: `colon` keeps
 * the engine's own punctuation, `dot` writes minutes:seconds.milliseconds.
 *
 * A ref, not a plain variable, so a component that renders a time re-renders
 * when the preference changes rather than keeping the old punctuation until
 * the next full page load.
 */
const separator = ref(':');

/** `colon` (default, what the engine prints) or `dot`. */
export const setTimeFormat = (format) => {
    separator.value = format === 'dot' ? '.' : ':';
};

export const timeSeparator = () => separator.value;

const padZero = (n) => n.toString().padStart(2, '0');

/**
 * Milliseconds as the game writes them.
 *
 * Defrag's smallest practical run is sub-minute and the largest pushes a
 * couple of hours, but the engine only ever displays MM:SS.mmm - minutes roll
 * past 60 and there is never an "H:" prefix. Match that: a 1:30:45.123
 * hh:mm:ss display would confuse players used to reading "90:45.123" off the
 * in-game timer.
 */
export const formatTime = (milliseconds) => {
    milliseconds = Math.max(0, milliseconds);

    const minutes = Math.floor(milliseconds / 60000);
    milliseconds %= 60000;
    const seconds = Math.floor(milliseconds / 1000);
    milliseconds %= 1000;

    const head = minutes > 0 ? `${padZero(minutes)}:` : '';

    return `${head}${padZero(seconds)}${separator.value}${milliseconds.toString().padStart(3, '0')}`;
};
