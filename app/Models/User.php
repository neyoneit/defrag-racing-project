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
        'model_group_order',
        'donation_emails',
        'plain_name',
        'notification_settings',
        'created_at',
        'color',
        'about_me',
        'avatar_effect',
        'name_effect',
        'avatar_border_color',
        'avatar_effects_intensity',
        'name_effects_intensity',
        'avatar_effects_speed',
        'name_effects_speed',
        'defrag_news',
        'tournament_news',
        'map_news',
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
        'global_profile_preferences',
        'last_login_at',
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

        // A User is serialized into public page payloads wherever it hangs off
        // something else - a demo's uploader, a record's owner - and those
        // payloads are readable by anyone. Measured on production 2026-08-08:
        // /maps/2plyr with Demos Top on handed out 8 addresses and a live
        // Discord access + refresh token to an unauthenticated request. The
        // linked-account tokens are credentials; the address is nobody's
        // business. Own-account email is put back in HandleInertiaRequests,
        // which is the only place the settings page reads it from.
        'email',
        'discord_token',
        'discord_refresh_token',
        'twitch_token',
        'twitch_refresh_token',
        'twitter_token',
        'twitter_refresh_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'amnesty_blocked_at' => 'datetime',
        'preview_system' => 'array',
        'nsfw_confirmed' => 'boolean',
        'default_show_oldtop' => 'boolean',
        'default_show_offline' => 'boolean',
        'default_physics_order' => 'string',
        'profile_layout' => 'array',
        'global_profile_preferences' => 'array',
        'avatar_effects_intensity' => 'integer',
        'name_effects_intensity' => 'integer',
        'avatar_effects_speed' => 'integer',
        'name_effects_speed' => 'integer',
        'pinned_models' => 'array',
        'model_group_order' => 'array',
        'donation_emails' => 'array',
        'moderator_permissions' => 'array',
        'widget_settings' => 'array',
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

    public function getDonorEmails(): array {
        $emails = $this->donation_emails ?? [];
        // Include registration email (load if not selected)
        $email = $this->email ?? static::where('id', $this->id)->value('email');
        if ($email) {
            $emails[] = $email;
        }
        return array_unique(array_filter($emails));
    }

    public function isDonor(): bool {
        $emails = $this->getDonorEmails();
        if (empty($emails)) return false;
        return SiteDonation::whereIn('donor_email', $emails)->where('status', 'approved')->exists();
    }

    public function getDonationTotal(): array {
        $emails = $this->getDonorEmails();
        if (empty($emails)) return [];
        return SiteDonation::whereIn('donor_email', $emails)
            ->where('status', 'approved')
            ->selectRaw('SUM(amount) as total, currency')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();
    }

    public function getDonationTotalEur(): float {
        $totals = $this->getDonationTotal();
        if (empty($totals)) return 0;

        $rates = \Cache::get('exchange_rates_v4', [
            'EUR' => 1, 'USD' => 1.08, 'CZK' => 25.3, 'GBP' => 0.86, 'PLN' => 4.28,
        ]);

        $eur = 0;
        foreach ($totals as $currency => $amount) {
            if ($currency === 'EUR') {
                $eur += $amount;
            } elseif (isset($rates[$currency]) && $rates[$currency] > 0) {
                $eur += $amount / $rates[$currency];
            }
        }
        return round($eur, 2);
    }

    /**
     * What this person's donations paid towards running the site, in EUR.
     *
     * Not the same as what they gave. A donation can be earmarked for the
     * comps prize pool, and that money is promised to a winner rather than
     * spent on hosting - the progress bar has always split the two, and
     * anything the site hands out in return for paying its bills has to split
     * them the same way. One of the two people who funded the prize pool put
     * in 50 EUR of which every cent was prize money.
     */
    public function getSiteDonationTotalEur(): float
    {
        $emails = $this->getDonorEmails();
        if (empty($emails)) return 0;

        $rates = \Cache::get('exchange_rates_v4', [
            'EUR' => 1, 'USD' => 1.08, 'CZK' => 25.3, 'GBP' => 0.86, 'PLN' => 4.28,
        ]);

        $total = 0;

        foreach (SiteDonation::whereIn('donor_email', $emails)->where('status', 'approved')->get() as $donation) {
            $rate = $rates[$donation->currency] ?? null;
            if ($donation->currency !== 'EUR' && (!$rate || $rate <= 0)) {
                continue;
            }

            $eur = $donation->currency === 'EUR'
                ? (float) $donation->amount
                : (float) $donation->amount / $rate;

            // min(), because the earmark is stored in EUR and a donation made
            // in another currency can round to slightly less than it.
            $total += max(0, $eur - min((float) $donation->comps_amount, $eur));
        }

        return round($total, 2);
    }

    /** Demos a guest may download in a day. */
    public const DEMO_DOWNLOADS_GUEST = 1;

    /** Demos an account may download in a day. */
    public const DEMO_DOWNLOADS_MEMBER = 50;

    /** Demos a donor may download in a day. */
    public const DEMO_DOWNLOADS_DONOR = 500;

    /**
     * What a donation has to add up to, in EUR, before the download limit
     * goes up. Total across every approved donation, not one payment.
     */
    public const DEMO_DOWNLOADS_DONOR_EUR = 10;

    /**
     * How many demos this person may download today.
     *
     * The limit exists because every download is bandwidth we pay for, which
     * is also why the donor figure is a high ceiling rather than no limit at
     * all. Cached, because it is asked on every page load and every download,
     * and cleared whenever a donation is saved.
     */
    public function demoDownloadLimit(): int
    {
        return $this->raisedDemoDownloads()
            ? self::DEMO_DOWNLOADS_DONOR
            : self::DEMO_DOWNLOADS_MEMBER;
    }

    public function raisedDemoDownloads(): bool
    {
        return \Cache::remember(
            self::demoDownloadPerkKey($this->id),
            3600,
            fn () => $this->getSiteDonationTotalEur() >= self::DEMO_DOWNLOADS_DONOR_EUR
        );
    }

    public static function demoDownloadPerkKey(int $userId): string
    {
        return "user:{$userId}:demo_download_perk";
    }

    public function getDonorTier(): ?string {
        if (!$this->isDonor()) return null;
        $eur = $this->getDonationTotalEur();
        if ($eur >= 100) return 'diamond';
        if ($eur >= 50) return 'gold';
        return 'supporter';
    }

    public function getTagCount(): int {
        return \DB::table('map_tag')->where('user_id', $this->id)->count()
             + \DB::table('maplist_tag')->where('user_id', $this->id)->count();
    }

    public function getAssignedDemoCounts(): array {
        return \Illuminate\Support\Facades\Cache::remember("profile:assigned_demos:{$this->id}", 3600, function () {
            $counts = \DB::table('uploaded_demos')
                ->where('user_id', $this->id)
                ->where('manually_assigned', true)
                // Raw query: the comps global scope does not reach it, so a
                // demo held back for a running round has to be excluded here
                // by hand or the count gives it away.
                ->where(fn ($q) => $q->whereNull('comps_hidden_until')->orWhere('comps_hidden_until', '<=', now()))
                ->selectRaw("
                    SUM(CASE WHEN gametype NOT LIKE 'm%' THEN 1 ELSE 0 END) as offline,
                    SUM(CASE WHEN gametype LIKE 'm%' THEN 1 ELSE 0 END) as online
                ")
                ->first();

            return [
                'offline' => (int) ($counts->offline ?? 0),
                'online' => (int) ($counts->online ?? 0),
            ];
        });
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

    /**
     * An announcement reaches everybody. There is no opt-out.
     *
     * These carry rules, deadlines and changes to how the site works, and
     * somebody who muted them two years ago and forgot is exactly the person
     * a rule change catches out. The header strip already refuses to let an
     * announcement be removed from it - see SettingsController, which forces
     * `announcement` back into preview_system - so this was the last switch
     * that could hide one, and it was the wrong one to offer.
     *
     * Header Preview still decides how loudly it arrives.
     */
    public function systemNotifyAnnouncement($type, $before, $headline, $after, $url) {
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
     * Servers the user pinned to the top of the server list.
     */
    public function favoritedServers()
    {
        return $this->belongsToMany(Server::class, 'server_favorites')
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

        return true;
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
