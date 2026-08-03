// Prize amounts show up on the contest page, the site-wide banner and the OBS
// overlay, and the page breaks a pool down into base + carried-over parts, so
// the formatting has to work on arbitrary numbers rather than come ready-made
// from the server. Keep this list in step with DefragliveContest::CURRENCIES.
const SYMBOLS = {
    EUR: '€',
    USD: '$',
};

// An unknown currency keeps its code instead of losing the unit: a bad row
// should read oddly, not silently claim to be in whatever the reader assumes.
export const formatPrize = (amount, currency) => {
    const value = Number(amount || 0).toFixed(2);
    const symbol = SYMBOLS[currency];

    return symbol ? `${symbol}${value}` : `${value} ${currency || ''}`.trim();
};
