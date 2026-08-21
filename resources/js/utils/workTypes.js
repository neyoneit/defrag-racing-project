// Work types come from the database now, so their colour arrives as a palette
// key rather than as CSS. Tailwind only keeps class names it can read as
// whole words, so every combination has to be spelled out here.
//
// The keys must match App\Models\MarketplaceWorkType::COLORS.

const BADGE = {
    emerald: 'text-emerald-400 bg-emerald-500/20 border-emerald-500/30',
    purple: 'text-purple-400 bg-purple-500/20 border-purple-500/30',
    orange: 'text-orange-400 bg-orange-500/20 border-orange-500/30',
    cyan: 'text-cyan-400 bg-cyan-500/20 border-cyan-500/30',
    blue: 'text-blue-400 bg-blue-500/20 border-blue-500/30',
    pink: 'text-pink-400 bg-pink-500/20 border-pink-500/30',
    amber: 'text-amber-400 bg-amber-500/20 border-amber-500/30',
    teal: 'text-teal-400 bg-teal-500/20 border-teal-500/30',
    rose: 'text-rose-400 bg-rose-500/20 border-rose-500/30',
    indigo: 'text-indigo-400 bg-indigo-500/20 border-indigo-500/30',
    lime: 'text-lime-400 bg-lime-500/20 border-lime-500/30',
    gray: 'text-gray-300 bg-gray-500/20 border-gray-500/30',
};

const CHIP = {
    emerald: 'text-emerald-400 bg-emerald-500/15',
    purple: 'text-purple-400 bg-purple-500/15',
    orange: 'text-orange-400 bg-orange-500/15',
    cyan: 'text-cyan-400 bg-cyan-500/15',
    blue: 'text-blue-400 bg-blue-500/15',
    pink: 'text-pink-400 bg-pink-500/15',
    amber: 'text-amber-400 bg-amber-500/15',
    teal: 'text-teal-400 bg-teal-500/15',
    rose: 'text-rose-400 bg-rose-500/15',
    indigo: 'text-indigo-400 bg-indigo-500/15',
    lime: 'text-lime-400 bg-lime-500/15',
    gray: 'text-gray-300 bg-gray-500/15',
};

/** Badge classes for a listing's work type. A colour we do not know reads as grey. */
export const workTypeBadge = (color) => BADGE[color] || BADGE.gray;

/** The same, without a border - used on creator cards. */
export const workTypeChip = (color) => CHIP[color] || CHIP.gray;

/** Look one type up in a list the server sent. */
export const findWorkType = (types, value) => (types || []).find((t) => t.value === value) || null;
