<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;

use Carbon\Carbon;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'username',
        'country',
        'admin',
        'is_moderator',
        'moderator_permissions',
        'oldhash',
        'mdd_id',
        'twitter_name',
        'twitch_name',
        'discord_name',
        'model',
        'pinned_models',
        'plain_name',
        'notification_settings',
        'created_at',
        'color',
        'avatar_effect',
        'name_effect',
        'avatar_border_color',
        'avatar_effects_intensity',
        'name_effects_intensity',
        'avatar_effects_speed',
        'name_effects_speed',
        'defrag_news',
        'tournament_news',
        'clan_notifications',
        'records_vq3',
        'records_cpm',
        'preview_records',
        'preview_system',
        'discord_id',
        'discord_token',
        'discord_refresh_token',
        'discord_token_expires_at',
        'twitch_id',
        'twitch_token',
        'twitch_refresh_token',
        'twitch_token_expires_at',
        'is_live',
        'live_status_checked_at',
        'steam_id',
        'steam_name',
        'steam_avatar',
        'twitter_id',
        'twitter_token',
        'twitter_refresh_token',
        'twitter_token_expires_at',
        'nsfw_confirmed',
        'default_show_oldtop',
        'default_show_offline',
        'default_physics_order',
        'profile_layout',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'oldhash',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'preview_system' => 'array',
        'nsfw_confirmed' => 'boolean',
        'default_show_oldtop' => 'boolean',
        'default_show_offline' => 'boolean',
        'default_physics_order' => 'string',
        'profile_layout' => 'array',
        'avatar_effects_intensity' => 'integer',
        'name_effects_intensity' => 'integer',
        'avatar_effects_speed' => 'integer',
        'name_effects_speed' => 'integer',
        'pinned_models' => 'array',
        'moderator_permissions' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    public function username () {
        return 'username';
    }

    public function generateSubstrings($name) {
        $inputString = mb_convert_encoding($name, 'Windows-1252', "auto");
        $length = strlen($inputString);
        $result = [];
    
        for ($i = 0; $i <= $length; $i++) {
            $sub = substr($inputString, $i);

            if (strlen($sub) < 3) {
                break;
            }

            $result[] = $sub;
        }

        if (count($result) == 0) {
            $result[] = $inputString;
        }
    
        return $result;
    }

    public function toSearchableArray () {
        $substrings = $this->generateSubstrings($this->plain_name);

        // Also index linked MDD profile name (stripped of color codes) for search
        if ($this->mdd_id) {
            $mddProfile = MddProfile::find($this->mdd_id);
            if ($mddProfile) {
                $mddPlain = preg_replace('/\^[\dA-Fa-f]/', '', $mddProfile->name);
                $mddSubstrings = $this->generateSubstrings($mddPlain);
                $substrings = array_values(array_unique(array_merge($substrings, $mddSubstrings)));
            }
        }

        // Also index approved aliases
        $aliases = $this->aliases()->where('is_approved', true)->pluck('alias');
        foreach ($aliases as $alias) {
            $aliasPlain = preg_replace('/\^[\dA-Fa-f]/', '', $alias);
            $aliasSubstrings = $this->generateSubstrings($aliasPlain);
            $substrings = array_values(array_unique(array_merge($substrings, $aliasSubstrings)));
        }

        return [
            'id' => (string) $this->id,
            'plain_name' => $substrings,
            'created_at' => Carbon::parse($this->created_at)->timestamp,
        ];
    }

    public function canAccessPanel(Panel $panel): bool {
        return $this->admin || $this->is_moderator;
    }

    public function isAdmin(): bool {
        return (bool) $this->admin;
    }

    public function isModerator(): bool {
        return (bool) $this->is_moderator;
    }

    public function hasModeratorPermission(string $permission): bool {
        if ($this->isAdmin()) return true;
        if (!$this->isModerator()) return false;
        $perms = $this->moderator_permissions ?? [];
        return in_array($permission, $perms);
    }

    public function isDonor(): bool {
        return SiteDonation::where('user_id', $this->id)->where('status', 'approved')->exists();
    }

    public function getTagCount(): int {
        return \DB::table('map_tag')->where('user_id', $this->id)->count()
             + \DB::table('maplist_tag')->where('user_id', $this->id)->count();
    }

    public function getFilamentAvatarUrl(): ?string {
        return $this->profile_photo_path;
    }

    public function getFilamentName(): string {
        $pattern = '/\^\w/';

        $plainName = preg_replace($pattern, '', $this->name);

        return $plainName;
    }

    public function mdd_profile() {
        return $this->hasOne(MddProfile::class, 'id', 'mdd_id');
    }

    public function clan () {
        return $this->hasOneThrough(Clan::class, ClanPlayer::class, 'user_id', 'id', 'id', 'clan_id');
    }

    public function team() {
        return $this->hasOne(Team::class, 'cpm_player_id', 'id') ?? $this->hasOne(Team::class, 'vq3_player_id', 'id');
    }

    public function teamInvites() {
        return $this->hasMany(TeamInvite::class, 'user_id', 'id');
    }

    public function tournamentNotify($type, $before, $headline, $after, $url) {
        if (! $this->tournament_news) {
            return;
        }

        if (Notification::where('user_id', $this->id)->where('type', $type)->where('headline', $headline)->exists()) {
            return;
        }

        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function systemNotify($type, $before, $headline, $after, $url) {
        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function systemNotifyAnnouncement($type, $before, $headline, $after, $url) {
        if (! $this->defrag_news) {
            return;
        }

        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function recordsNotify() {
        
    }

    public function demos() {
        return $this->hasMany(Demo::class);
    }

    public function check_demos($round_id) {
        $demos = $this->demos()
            ->where('round_id', $round_id)
            ->where('rejected', false)
            ->where('physics', 'vq3')
            ->orderBy('time', 'asc');

        $demos->update(['best' => false]);

        $best_demo = $demos->first();

        if ($best_demo) {
            $best_demo->best = true;
            $best_demo->save();
        }

        $demos = $this->demos()
            ->where('round_id', $round_id)
            ->where('rejected', false)
            ->where('physics', 'cpm')
            ->orderBy('time', 'asc');

        $demos->update(['best' => false]);

        $best_demo = $demos->first();

        if ($best_demo) {
            $best_demo->best = true;
            $best_demo->save();
        }
    }

    /**
     * Get user's maplists
     */
    public function maplists()
    {
        return $this->hasMany(Maplist::class);
    }

    /**
     * Get user's "Play Later" maplist
     */
    public function playLaterMaplist()
    {
        return $this->hasOne(Maplist::class)->where('is_play_later', true);
    }

    /**
     * Get maplists the user has liked
     */
    public function likedMaplists()
    {
        return $this->belongsToMany(Maplist::class, 'maplist_likes')
            ->withTimestamps();
    }

    /**
     * Get maplists the user has favorited
     */
    public function favoritedMaplists()
    {
        return $this->belongsToMany(Maplist::class, 'maplist_favorites')
            ->withTimestamps();
    }

    /**
     * Boot method to create "Play Later" maplist for new users
     */
    protected static function booted()
    {
        static::created(function ($user) {
            Maplist::create([
                'user_id' => $user->id,
                'name' => 'Play Later',
                'description' => 'Save maps to play later',
                'is_public' => false,
                'is_play_later' => true,
            ]);
        });
    }

    /**
     * Get user's mapper claims
     */
    public function mapperClaims()
    {
        return $this->hasMany(MapperClaim::class);
    }

    /**
     * Get maps claimed by this user (via mapper claims)
     */
    public function getClaimedMapsQuery()
    {
        $claims = $this->mapperClaims()->where('type', 'map')->with('exclusions')->get();
        $claimNames = $claims->pluck('name');

        if ($claimNames->isEmpty()) {
            return Map::where('id', 0); // empty query
        }

        $excludedMapIds = $claims->flatMap(fn($c) => $c->exclusions->pluck('map_id'))->unique()->toArray();

        $query = Map::where('visible', true)->where(function ($q) use ($claimNames) {
            foreach ($claimNames as $name) {
                $q->orWhere('author', 'REGEXP', MapperClaim::authorRegexp($name));
            }
        });

        if (!empty($excludedMapIds)) {
            $query->whereNotIn('id', $excludedMapIds);
        }

        return $query;
    }

    /**
     * Check if user has any mapper claims with matching maps
     */
    public function hasMapperProfile(): bool
    {
        return $this->getClaimedMapsQuery()->exists();
    }

    /**
     * Check if user has any model claims with matching models
     */
    public function hasModelerProfile(): bool
    {
        $claimNames = $this->mapperClaims()->where('type', 'model')->pluck('name');

        if ($claimNames->isEmpty()) {
            return false;
        }

        return \App\Models\PlayerModel::where('approval_status', 'approved')
            ->where(function ($q) use ($claimNames) {
                foreach ($claimNames as $name) {
                    $q->orWhere('author', 'REGEXP', MapperClaim::authorRegexp($name));
                }
            })->exists();
    }

    /**
     * Get user's aliases
     */
    public function aliases()
    {
        return $this->hasMany(UserAlias::class);
    }

    /**
     * Get alias suggestions received by this user
     */
    public function aliasSuggestions()
    {
        return $this->hasMany(AliasSuggestion::class, 'user_id');
    }

    /**
     * Get alias suggestions made by this user
     */
    public function suggestedAliases()
    {
        return $this->hasMany(AliasSuggestion::class, 'suggested_by_user_id');
    }

    /**
     * Get user's uploaded demos
     */
    public function uploadedDemos()
    {
        return $this->hasMany(UploadedDemo::class, 'user_id');
    }

    /**
     * Get user's records
     */
    public function records()
    {
        return $this->hasMany(Record::class, 'user_id');
    }

    public function creatorProfile()
    {
        return $this->hasOne(MarketplaceCreatorProfile::class);
    }

    public function marketplaceListings()
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function marketplaceReviewsReceived()
    {
        return $this->hasMany(MarketplaceReview::class, 'reviewee_id');
    }

    public function canPostOnMarketplace(): bool
    {
        return $this->records()->count() >= 50;
    }

    /**
     * Get user's top 5 most downloaded demos
     */
    public function topDownloadedDemos()
    {
        return $this->uploadedDemos()
            ->orderBy('download_count', 'desc')
            ->limit(5);
    }

    /**
     * Check if user can upload demos
     * Requires 30 records and not being upload-restricted
     */
    public function canUploadDemos()
    {
        if ($this->upload_restricted) {
            return false;
        }

        // Must have at least 30 records
        return $this->records()->count() >= 30;
    }

    /**
     * Check if user can assign/reassign demos
     * Requires 30 records and not being assignment-restricted
     */
    public function canAssignDemos()
    {
        if ($this->assignment_restricted) {
            return false;
        }

        // Must have at least 30 records
        return $this->records()->count() >= 30;
    }

    /**
     * Check if user can report demos/aliases
     * Requires 30 records
     */
    public function canReportDemos()
    {
        // Must have at least 30 records
        return $this->records()->count() >= 30;
    }
}
