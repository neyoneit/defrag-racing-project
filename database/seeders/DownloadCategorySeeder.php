<?php

namespace Database\Seeders;

use App\Models\Download;
use App\Models\DownloadCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The default category tree.
 *
 * A .pk3 is a zip that the engine merges into one virtual filesystem rooted at
 * the game folder, so assets are found by path convention (textures/, models/,
 * sound/, ...) rather than by which pk3 they came from. Anything at one of
 * those paths can be overridden by a custom pk3, which is why the tree mirrors
 * the real layout of pak0-pak8 and every node names the path it maps to.
 *
 * Three areas are deliberately absent because the site already covers them and
 * a second home would only split the content:
 *   - playable maps            -> /maps
 *   - player models and skins  -> /models (category player)
 *   - weapon models            -> /models (category weapon)
 * Demos (demos/) have their own section too, and vm/*.qvm is compiled game
 * code rather than an asset, which the DeFRaG mod category covers.
 *
 * Safe to re-run: nodes are matched on (parent, slug) and updated in place, so
 * existing uploads keep their category. Nothing is ever deleted here.
 */
class DownloadCategorySeeder extends Seeder
{
    /**
     * (parent_id, slug) pairs this run is responsible for, collected while
     * walking the tree so the prune below knows what belongs.
     */
    private array $seeded = [];

    public function run(): void
    {
        foreach ($this->tree() as $position => $node) {
            $this->createNode($node, null, $position);
        }

        $this->prune();
    }

    /**
     * Drops default categories that the tree no longer defines, which is what
     * happens when a node gets renamed and its slug changes. Only ever touches
     * a category that is all of: not in the tree above, empty across its whole
     * subtree, and not created by a person (created_by is null). So renaming a
     * node cleans up after itself, while admin-made categories and anything
     * holding uploads survive untouched.
     */
    private function prune(): void
    {
        // "Bundles and repacks" is seeded as an empty shell and filled in by
        // downloads:migrate-bundles, so its children are legitimately absent
        // from the tree above. Without this they would survive only as long as
        // they held uploads, and emptying one would quietly delete it.
        $protectedRoots = DownloadCategory::whereNull('parent_id')
            ->whereIn('slug', ['bundles-and-repacks'])
            ->pluck('id')
            ->all();

        $stale = DownloadCategory::whereNull('created_by')
            ->get()
            ->reject(fn ($c) => in_array([$c->parent_id, $c->slug], $this->seeded, true))
            ->reject(fn ($c) => in_array($c->parent_id, $protectedRoots, true));

        foreach ($stale as $category) {
            if (! DownloadCategory::find($category->id)) {
                continue; // already removed as part of an ancestor's subtree
            }

            $ids = $category->descendantIds();

            if (Download::whereIn('category_id', $ids)->exists()) {
                continue;
            }

            // The created_by guard above only vetted the subtree ROOT; an
            // admin-made child nested under a renamed seeded node would
            // otherwise be deleted with it.
            if (DownloadCategory::whereIn('id', $ids)->whereNotNull('created_by')->exists()) {
                continue;
            }

            // Children first: parent_id is a real foreign key.
            DownloadCategory::whereIn('id', array_reverse($ids))->delete();
        }
    }

    private function createNode(array $node, ?int $parentId, int $position): void
    {
        $category = DownloadCategory::updateOrCreate(
            [
                'parent_id' => $parentId,
                'slug' => $node['slug'],
            ],
            [
                'name' => $node['name'],
                'description' => $node['description'] ?? null,
                'position' => $position,
                'is_locked' => $node['is_locked'] ?? false,
                'auto_source' => $node['auto_source'] ?? null,
            ]
        );

        $this->seeded[] = [$parentId, $node['slug']];

        foreach ($node['children'] ?? [] as $childPosition => $child) {
            $this->createNode($child, $category->id, $childPosition);
        }
    }

    private function node(string $name, string $description, array $children = [], array $extra = []): array
    {
        return array_merge([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'children' => $children,
        ], $extra);
    }

    private function tree(): array
    {
        return [
            $this->node('Textures', 'Surface images applied to map geometry (textures/*.tga, *.jpg).', [
                $this->node('World and architecture', 'Walls, floors, trims and lights. The base and gothic sets (textures/base_*, textures/gothic_*).'),
                $this->node('Effects and SFX', 'Glows, flares, decals and animated surfaces (textures/sfx/, textures/effects/).'),
                $this->node('Liquids', 'Water, slime and lava surfaces (textures/liquids/).'),
                $this->node('Skies and environment', 'Skybox faces and environment maps (textures/skies/, env/).'),
                $this->node('Nature and stone', 'Organic, stone and terrain surfaces (textures/organics/, textures/stone/).'),
                $this->node('CTF', 'Red and blue team surfaces (textures/ctf/).'),
                $this->node('Common and editor', 'Caulk, clip, trigger and hint. Compile-time surfaces (textures/common/).'),
            ]),

            $this->node('Shaders', 'Scripts defining how a surface renders: blending, transparency, scroll, deform, sky (scripts/*.shader).'),

            $this->node('Mapping', 'Resources for building maps rather than the finished maps themselves. Playable maps live in the Maps section.', [
                $this->node('Map models and prefabs', 'Decorative meshes placed in maps, and reusable prefab pieces (models/mapobjects/).'),
                $this->node('Map templates and sources', 'Editable .map sources and starter templates to build on.'),
                $this->node('Levelshots', 'Loading-screen preview images (levelshots/<map>.jpg).'),
                $this->node('Bot navigation', 'Bot routing data for maps that ship without it (maps/*.aas).'),
                $this->node('Arena files', 'Map metadata shown in menus: longname, gametype, bots (scripts/*.arena).'),
                $this->node('Editor and compiler', 'Radiant configs, compiler settings and mapping tools.'),
            ]),

            $this->node('Models', 'In-game meshes. Player and weapon models have their own home in the Models section.', [
                $this->node('Item and pickup models', 'Health, armor, ammo, powerups and flags (models/powerups/, models/ammo/, models/flags/).'),
                $this->node('Gibs and impact effects', 'Gore chunks and weapon impact meshes (models/gibs/, models/weaphits/).'),
                $this->node('Misc models', 'Everything else under models/misc/.'),
            ]),

            $this->node('Sounds and music', 'Everything audible (sound/*.wav, *.ogg, music/).', [
                $this->node('Player sounds', 'Pain, jump, taunt and death per model (sound/player/).'),
                $this->node('Weapon sounds', 'Fire, impact and explosion (sound/weapons/).'),
                $this->node('World and movers', 'Doors, platforms, teleporters and ambient loops (sound/world/, sound/movers/).'),
                $this->node('Item sounds', 'Pickup and powerup cues (sound/items/).'),
                $this->node('Announcer and feedback', 'Countdown, timer and checkpoint cues (sound/feedback/).'),
                $this->node('Teamplay', 'Team orders and callouts (sound/teamplay/).'),
                $this->node('Music', 'Background tracks for maps and menus (music/).'),
            ]),

            $this->node('HUDs and configs', 'The in-game interface and cvar setups. DeFRaG physics is compiled into the mod and no pk3 can change it, so only its display and cvars are configurable here.', [
                $this->node('Defrag HUDs', 'HUD configs driving the timer, speed meter, checkpoints and CGaZ or snap overlays.'),
                $this->node('HUD graphics', 'Speed bars, timer backgrounds and accel overlays referenced by a HUD.'),
                $this->node('Player configs', 'autoexec.cfg, sensitivity, video and network settings.'),
                $this->node('Binds and scripts', 'Shareable bind scripts and movement helpers.'),
            ]),

            $this->node('Menus and UI', 'The out-of-game front end and text rendering.', [
                $this->node('Menu art', 'Menu backgrounds, widgets and screens (menu/art/).'),
                $this->node('Medals and awards', 'Award icons shown after a match (menu/medals/).'),
                $this->node('Fonts', 'Bitmap fonts and glyph metrics used by menus and the HUD.'),
            ]),

            $this->node('GFX and 2D', 'Flat screen-space imagery not tied to world geometry (gfx/).', [
                $this->node('Crosshairs', 'Selectable crosshair images (gfx/2d/crosshair*.tga).'),
                $this->node('Screen overlays', 'Damage flash, vignette, HUD frame art and numbers (gfx/2d/, gfx/damage/).'),
                $this->node('Icons', 'Weapon, item and ammo pickup icons (icons/).'),
                $this->node('Sprites and explosions', 'Animated sprite sheets for effects (sprites/).'),
                $this->node('Colors and misc', 'Palette strips and leftover 2D art (gfx/colors/, gfx/misc/).'),
            ]),

            $this->node('Botfiles', 'Bot AI behavior, weights, chats and rosters (botfiles/, botfiles/bots/).'),

            $this->node('Videos', 'In-game cinematics and end-of-match videos (video/*.RoQ).'),

            // Home for the pre-hub downloads. They are curated collections
            // rather than asset types (a repack holds maps, textures and
            // shaders at once), so they keep their own branch instead of
            // being torn apart across the tree above. Children are attached
            // by downloads:migrate-bundles.
            $this->node('Bundles and repacks', 'Curated collections that bundle many assets into a single pk3 or archive.'),

            // The two locked categories. Neither is a listing: each renders as
            // its own article that keeps itself up to date, so they have no
            // subcategories and no upload button. Their entries come from
            // downloads:sync-defrag-mod and downloads:sync-server-bundle.
            $this->node('DeFRaG mod', 'Official DeFRaG mod releases, mirrored automatically from q3defrag.org.', [], [
                'is_locked' => true,
                'auto_source' => DownloadCategory::SOURCE_DEFRAG_MOD,
            ]),

            $this->node('DeFRaG server bundle', 'Everything needed to run a DeFRaG server, built automatically from the latest engine and mod.', [], [
                'is_locked' => true,
                'auto_source' => DownloadCategory::SOURCE_SERVER_BUNDLE,
            ]),

            $this->node('Family-Friendly defrag', 'Replaces the gore and adult artwork with safe alternatives, for streaming, work and younger players.', [], [
                'is_locked' => true,
                'auto_source' => DownloadCategory::SOURCE_FAMILY_FRIENDLY,
            ]),
        ];
    }
}
