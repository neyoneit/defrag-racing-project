<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MapperClaim extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    /**
     * Separators that indicate collaboration between authors.
     * If a claim name is surrounded by these (or start/end of string), it's a valid match.
     * Examples: "ghost & pornstar", "ghost-pornstar", "ghost@lala"
     * NOT: "sgtghost" (no separator between "sgt" and "ghost")
     */
    private static $separatorPattern = '[[:space:]&,+\\-_@|/\\\\.]';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exclusions()
    {
        return $this->hasMany(MapperClaimExclusion::class);
    }

    public function excludedMapIds(): array
    {
        return $this->exclusions()->pluck('map_id')->toArray();
    }

    /**
     * Build a MySQL REGEXP pattern that matches the claim name as a whole word,
     * separated by collaboration separators (not as a substring of another word).
     *
     * Matches: exact "ghost", "ghost & pornstar", "pornstar-ghost", "ghost@lala"
     * Does NOT match: "sgtghost", "ghostly"
     */
    public static function authorRegexp(string $name): string
    {
        $escaped = preg_quote($name, '/');
        // MySQL REGEXP: name must be at start/end of string OR preceded/followed by a separator
        $sep = self::$separatorPattern;
        return "(^|{$sep}){$escaped}($|{$sep})";
    }

    /**
     * Apply author matching scope to a query.
     * Matches the claim name as a whole word separated by collaboration separators.
     */
    public static function scopeAuthorMatch(Builder $query, string $name): Builder
    {
        return $query->where('author', 'REGEXP', self::authorRegexp($name));
    }

    /**
     * Get maps matching this claim, respecting exclusions and more-specific claims by other users.
     */
    public function getMatchingMapsQuery()
    {
        $query = Map::where('visible', true)
            ->where('author', 'REGEXP', self::authorRegexp($this->name));

        $excludedIds = $this->excludedMapIds();
        if (!empty($excludedIds)) {
            $query->whereNotIn('id', $excludedIds);
        }

        // Exclude maps where another user has a more specific (longer) claim that also matches
        $moreSpecificClaims = self::where('type', $this->type)
            ->where('user_id', '!=', $this->user_id)
            ->whereRaw('LENGTH(name) > ?', [strlen($this->name)])
            ->where('name', 'LIKE', '%' . $this->name . '%')
            ->pluck('name');

        foreach ($moreSpecificClaims as $specificName) {
            $query->where('author', 'NOT REGEXP', self::authorRegexp($specificName));
        }

        return $query;
    }
}
