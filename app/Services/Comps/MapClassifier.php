<?php

namespace App\Services\Comps;

/**
 * Sorts a map into strafe, weapon or combo for the comps pool.
 *
 * Only the guns you can move with count. A rocket, plasma, grenade, lightning
 * or BFG changes how a map is run; a railgun or a shotgun does not, because
 * neither has splash and neither will push you anywhere. Gauntlet and
 * machinegun are handed to you on spawn nearly everywhere and so say nothing
 * about the map at all, and the hook is movement rather than a weapon in this
 * sense.
 *
 * The `strafe` tag beats the weapons column outright. That column is filled
 * from the entities in the .bsp, which records what is placed in the map, not
 * what you can reach or use. `dfwc2017-6` lists every gun in the game and is a
 * strafe map; somebody tagged it, and the tag is the only thing that knows.
 *
 * Which is also the honest limit of this class: as of writing, 16 maps in the
 * database are tagged strafe while carrying weapons. Every other map like it -
 * and there are certainly hundreds - classifies as combo, because nobody has
 * tagged it yet. Nothing here can fix that. Tagging can.
 */
class MapClassifier
{
    /**
     * The guns that decide the category, in the order they are offered when a
     * weapon round draws one.
     */
    public const COUNTED = ['rl', 'pg', 'gl', 'lg', 'bfg'];

    public const STRAFE = 'strafe';
    public const WEAPON = 'weapon';
    public const COMBO = 'combo';

    /**
     * @param  string|null  $weapons  the map's comma-separated weapons column
     * @param  bool  $hasStrafeTag  whether the map carries the `strafe` tag
     * @return array{category: string, weapon: string|null}
     */
    public function classify(?string $weapons, bool $hasStrafeTag): array
    {
        if ($hasStrafeTag) {
            return ['category' => self::STRAFE, 'weapon' => null];
        }

        $counted = $this->countedWeapons($weapons);

        if (count($counted) === 0) {
            return ['category' => self::STRAFE, 'weapon' => null];
        }

        if (count($counted) === 1) {
            return ['category' => self::WEAPON, 'weapon' => $counted[0]];
        }

        return ['category' => self::COMBO, 'weapon' => null];
    }

    /**
     * The counted guns present in the column, ignoring everything else in it.
     */
    public function countedWeapons(?string $weapons): array
    {
        $listed = array_filter(array_map('trim', explode(',', (string) $weapons)));

        return array_values(array_intersect($listed, self::COUNTED));
    }
}
