<?php

namespace Database\Seeders;

use App\Models\WikiPage;
use Illuminate\Database\Seeder;

class WikiSeeder extends Seeder
{
    public function run(): void
    {
        // Don't seed if pages already exist
        if (WikiPage::count() > 0) {
            $this->command->info('Wiki pages already exist, skipping seed.');
            return;
        }

        $pages = $this->getPages();

        // Create top-level pages first
        $parentMap = [];
        foreach ($pages as $i => $page) {
            if ($page['parent'] === null) {
                $created = WikiPage::create([
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'sort_order' => $page['sort_order'],
                    'created_by' => null,
                    'updated_by' => null,
                ]);
                $parentMap[$page['slug']] = $created->id;
            }
        }

        // Create child pages
        foreach ($pages as $page) {
            if ($page['parent'] !== null && isset($parentMap[$page['parent']])) {
                WikiPage::create([
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'parent_id' => $parentMap[$page['parent']],
                    'sort_order' => $page['sort_order'],
                    'created_by' => null,
                    'updated_by' => null,
                ]);
            }
        }

        $this->command->info('Wiki seeded with ' . WikiPage::count() . ' pages.');
    }

    private function getPages(): array
    {
        return [
            // =============================================
            // 1. GETTING STARTED (top-level)
            // =============================================
            [
                'slug' => 'getting-started',
                'title' => 'Getting Started',
                'parent' => null,
                'sort_order' => 1,
                'content' => $this->gettingStarted(),
            ],
            [
                'slug' => 'installation',
                'title' => 'Installation & Setup',
                'parent' => 'getting-started',
                'sort_order' => 1,
                'content' => $this->installation(),
            ],
            [
                'slug' => 'configuration',
                'title' => 'Configuration & Optimization',
                'parent' => 'getting-started',
                'sort_order' => 2,
                'content' => $this->configuration(),
            ],
            [
                'slug' => 'faq',
                'title' => 'FAQ',
                'parent' => 'getting-started',
                'sort_order' => 3,
                'content' => $this->faq(),
            ],

            // =============================================
            // 2. PHYSICS (top-level)
            // =============================================
            [
                'slug' => 'physics',
                'title' => 'Physics',
                'parent' => null,
                'sort_order' => 2,
                'content' => $this->physics(),
            ],

            // =============================================
            // 3. TECHNIQUES (top-level)
            // =============================================
            [
                'slug' => 'techniques',
                'title' => 'Techniques',
                'parent' => null,
                'sort_order' => 3,
                'content' => $this->techniques(),
            ],
            [
                'slug' => 'overbounce',
                'title' => 'Overbounce',
                'parent' => 'techniques',
                'sort_order' => 1,
                'content' => $this->overbounce(),
            ],
            [
                'slug' => 'groundboosting',
                'title' => 'Groundboosting',
                'parent' => 'techniques',
                'sort_order' => 2,
                'content' => $this->groundboosting(),
            ],

            // =============================================
            // 4. HUD & TOOLS (top-level)
            // =============================================
            [
                'slug' => 'hud-and-tools',
                'title' => 'HUD & Tools',
                'parent' => null,
                'sort_order' => 4,
                'content' => $this->hudAndTools(),
            ],

            // =============================================
            // 5. WEAPONS & ITEMS (top-level)
            // =============================================
            [
                'slug' => 'weapons-and-items',
                'title' => 'Weapons & Items',
                'parent' => null,
                'sort_order' => 5,
                'content' => $this->weaponsAndItems(),
            ],

            // =============================================
            // 6. GAME MODES (top-level)
            // =============================================
            [
                'slug' => 'game-modes',
                'title' => 'Game Modes',
                'parent' => null,
                'sort_order' => 6,
                'content' => $this->gameModes(),
            ],

            // =============================================
            // 7. CONSOLE COMMANDS (top-level)
            // =============================================
            [
                'slug' => 'console-commands',
                'title' => 'Console Commands & Variables',
                'parent' => null,
                'sort_order' => 7,
                'content' => $this->consoleCommands(),
            ],
            [
                'slug' => 'server-commands',
                'title' => 'Server Commands (mDd)',
                'parent' => 'console-commands',
                'sort_order' => 1,
                'content' => $this->serverCommands(),
            ],

            // =============================================
            // 8. MAPPING (top-level)
            // =============================================
            [
                'slug' => 'mapping',
                'title' => 'Mapping',
                'parent' => null,
                'sort_order' => 8,
                'content' => $this->mapping(),
            ],

            // =============================================
            // 9. ENGINES (top-level)
            // =============================================
            [
                'slug' => 'engines',
                'title' => 'Engines & Clients',
                'parent' => null,
                'sort_order' => 9,
                'content' => $this->engines(),
            ],

            // =============================================
            // 10. CUSTOMIZATION (top-level)
            // =============================================
            [
                'slug' => 'customization',
                'title' => 'Customization',
                'parent' => null,
                'sort_order' => 10,
                'content' => $this->customization(),
            ],

            // =============================================
            // 11. SERVER RULES (top-level)
            // =============================================
            [
                'slug' => 'server-rules',
                'title' => 'Server Rules',
                'parent' => null,
                'sort_order' => 11,
                'content' => $this->serverRules(),
            ],

            // =============================================
            // 12. GLOSSARY (top-level)
            // =============================================
            [
                'slug' => 'glossary',
                'title' => 'Glossary',
                'parent' => null,
                'sort_order' => 12,
                'content' => $this->glossary(),
            ],

            // =============================================
            // 13. HISTORY & CULTURE (top-level)
            // =============================================
            [
                'slug' => 'history-and-culture',
                'title' => 'History & Culture',
                'parent' => null,
                'sort_order' => 13,
                'content' => $this->historyAndCulture(),
            ],
            [
                'slug' => 'defrag-movies',
                'title' => 'DeFRaG Movies & Media',
                'parent' => 'history-and-culture',
                'sort_order' => 1,
                'content' => $this->movies(),
            ],
            [
                'slug' => 'competitions',
                'title' => 'Competitions & DFWC',
                'parent' => 'history-and-culture',
                'sort_order' => 2,
                'content' => $this->competitions(),
            ],

            // =============================================
            // 14. CHANGELOG (top-level)
            // =============================================
            [
                'slug' => 'changelog',
                'title' => 'DeFRaG Changelog',
                'parent' => null,
                'sort_order' => 14,
                'content' => $this->changelog(),
            ],

            // =============================================
            // 15. COMMUNITY LINKS (top-level)
            // =============================================
            [
                'slug' => 'community-links',
                'title' => 'Community Links',
                'parent' => null,
                'sort_order' => 15,
                'content' => $this->communityLinks(),
            ],

            // =============================================
            // 16. ANTI-CHEAT & DEMOS (top-level)
            // =============================================
            [
                'slug' => 'anti-cheat-and-demos',
                'title' => 'Anti-Cheat & Demo System',
                'parent' => null,
                'sort_order' => 16,
                'content' => $this->antiCheatAndDemos(),
            ],
        ];
    }

    // =========================================================================
    // CONTENT METHODS
    // =========================================================================

    private function gettingStarted(): string
    {
        return <<<'MD'
## What is DeFRaG?

DeFRaG (from French "Defis Fragdome") is a free modification for Quake III Arena by id Software, focused entirely on player movement and trickjumping. It was publicly released around September/October 2000. In 2002, Planet Quake chose it as "Mod of the Week".

Unlike traditional FPS mods based on combat, DeFRaG is a platform for self-training, competitions (timed runs), online tricking, machinima, and trickjumping. It completely removes violence from FPS - weapons are used as movement tools, not destruction.

The mod includes: timers, speed meters, ghost mode, anti-cheat system, learning tools (CGazHUD, AccelMeter, JumpMeter), camera/machinima tools, and a demo-recording system.

There are 16,000+ community maps available.

## Quick Start

1. **Get Quake III Arena** - You need the original game files (pak0.pk3)
2. **Download DeFRaG mod** - Get the latest version from [q3defrag.org](https://q3defrag.org/files/defrag/)
3. **Download a modern engine** - [oDFe](https://github.com/JBustos22/oDFe/releases/tag/latest) is recommended
4. **Extract** - Place the mod and engine into your Q3 folder (next to baseq3, NOT inside it)
5. **Generate a config** - Use the [Online Config Generator](http://dimit.me/dfcfg/)
6. **Join a server** or start a local game - Maps will download automatically

Alternatively, visit [defrag.racing](https://defrag.racing) and download the complete standalone bundle from the Downloads section, which includes everything pre-configured.

## Your First Steps

- Start with training maps like `tr1ckhouse-beta3` or `df-spawnroom-example`
- Enable CGazHUD to see optimal strafe angles: `df_hud_cgaz 9`
- Enable speed meter: `df_drawSpeed 1`
- Practice circle-jumping and strafe-jumping first
- Watch the tutorial movie "Genesis: The beginning of..." for a comprehensive overview
MD;
    }

    private function installation(): string
    {
        return <<<'MD'
## Installation Methods

### Method 1: Standalone Bundle (Recommended)

Visit [defrag.racing](https://defrag.racing) Downloads section and get the complete bundle. It includes an optimized engine (oDFe), base game files, and starter maps. This eliminates complex setup.

### Method 2: Steam + Manual Setup

1. Find your Quake 3 Arena installation folder (e.g. `C:\Program Files (x86)\Steam\steamapps\common\Quake 3 Arena`)
2. Create a new folder called `defrag`
3. Download the mod from [q3defrag.org](https://q3defrag.org/files/defrag/) or [ModDB](https://www.moddb.com/mods/defrag) and extract into the `defrag` folder
4. Download a modern engine (iDFe.exe or oDFe) and place it in the main Quake 3 folder
5. Launch the game using the new engine executable
6. In the menu, select "MODS" and choose Defrag

### Method 3: Discord Bundle

Join the [DeFRaG Discord](https://discord.gg/ZG4dKNVQJu) and check the `#get-started` channel for an all-in-one package.

## Getting Maps

- **Automatic download** - Servers will auto-download maps when you connect. Set up fast HTTP download:
  ```
  seta cl_allowDownload 1
  seta cl_mapAutoDownload 1
  seta dl_source "http://ws.q3df.org/maps/download/%m"
  ```
- **Map packs** - [ModDB Map Pack](https://www.moddb.com/mods/defrag/addons/map-pack-111)
- **Worldspawn Archive** - [ws.q3df.org/maps/](https://ws.q3df.org/maps/) - Complete database of 16,000+ maps
- **defrag.racing** - Browse and download maps directly

## Recommended Engines

| Engine | Description | Download |
|--------|-------------|----------|
| **oDFe** | Modern, Vulkan support, recommended | [GitHub](https://github.com/JBustos22/oDFe/releases/tag/latest) |
| **iDFe** | Previous standard, stable | [Google Drive](https://drive.google.com/drive/folders/0BxHNb39hE49iTWh3TWpBMzdtOW8) |
| **CNQ3** | CPMA-focused but compatible | 64-bit, great console auto-complete |

See the [Engines & Clients](/wiki/engines) page for detailed comparison.

## Connecting to Servers

Join servers directly from the in-game browser, or use the server list at [defrag.racing](https://defrag.racing) to find active servers. Click the server address to join directly.

Community servers are available in 8+ regions: Oregon US, Sydney AU, Stockholm SE, Sao Paulo BR, Singapore, Tokyo JP, London UK, Cape Town ZA, and more.

### Linux: Protocol Handler

On Linux (Chrome), you can create a `defrag://` protocol handler to join servers from the browser:

1. Create a `.desktop` file for xdg-open
2. Register the `defrag://` protocol
3. Point it to your engine binary with `+connect` argument

See the original guide by [WWWD]newbrict for details.
MD;
    }

    private function configuration(): string
    {
        return <<<'MD'
## Essential Configuration (autoexec.cfg)

Create a file called `autoexec.cfg` in your `/defrag` folder with these settings:

### Core Settings

| CVar | Value | Purpose |
|------|-------|---------|
| `com_maxfps` | `125` | Critical. In id Tech 3, FPS affects jump height and air time. 125 is the standard. |
| `pmove_fixed` | `1` | Synchronizes movement with physics tick rate, eliminates FPS anomalies. |
| `g_synchronousclients` | `1` | Required for correct Overbounce in offline mode. Set to `0` for online play. |
| `com_hunkMegs` | `512` | RAM allocation for engine. Modern maps with HD textures need more memory. |
| `cl_maxpackets` | `125` | Network packets sent per second. Higher = smoother movement online. |
| `snaps` | `125` | Server update frequency received. Must match `sv_fps` on the server. |
| `rate` | `25000` | Maximum bandwidth for network communication. |
| `cg_fov` | `115-130` | Wider field of view for better peripheral vision. |

### Network Settings (Online Play)

```
seta g_synchronousclients 0
seta pmove_fixed 1
seta cl_maxpackets 125
seta snaps 125
seta rate 25000
seta cl_packetdup 1
```

### Network Settings (Offline Practice)

```
seta g_synchronousclients 1
seta pmove_fixed 0
```

### Recommended Binds

```
bind MOUSE2 "+moveup"    // jump with right mouse button
```

### HUD Tools for Training

```
seta df_hud_cgaz 9       // CGazHUD - shows optimal strafe angles
seta scr_hud_snap_draw 1 // SnapHUD - angle optimization for grid
seta df_drawSpeed 1      // Speed meter
```

## FPS and Physics

The Quake III engine uses discrete force integration, meaning player position calculation depends on frame duration. Higher stable FPS cause rounding differences in vertical vector calculations, allowing slightly higher jumps.

Preferred FPS values are divisors of 1000ms:
- **125 FPS** = 8ms per frame (standard for defrag)
- **250 FPS** = 4ms per frame
- **333 FPS** = 3ms per frame

The latest DeFRaG versions (1.91.27) include an experimental 1ms timer for higher precision.

## Config Generator

Use the [Online Config Generator](http://dimit.me/dfcfg/) by Newbrict to generate a complete configuration file tailored to your preferences.

## Network Prediction

| CVar | Default | Recommended | Effect |
|------|---------|-------------|--------|
| `cl_packetdup` | 1 | 0-1 | Duplicate packets. 0 for stable connections, 1-2 for packet loss. |
| `rate` | 3000 | 25000 | Bandwidth. Low values cause "teleportation" at high speeds. |
| `cl_maxpackets` | 30 | 125 | How often you send position to server. Critical for smooth strafing. |
| `snaps` | 20 | 125 | Server update frequency. Must align with server's sv_fps. |
| `cl_timenudge` | 0 | 0 | Latency compensation. Modern engines (iDFe) have adaptive algorithms. |
MD;
    }

    private function faq(): string
    {
        return <<<'MD'
## Frequently Asked Questions

### I can't connect to servers - "missing map" error

The map file might be corrupt or missing. Enable auto-download:
```
seta cl_allowDownload 1
seta dl_source "http://ws.q3df.org/maps/download/%m"
```
If a specific .pk3 file is corrupted, delete it from your `defrag/` folder and reconnect.

### I get "CL_ParseGamestate" error

This usually means your `com_hunkMegs` is too low. Increase it:
```
seta com_hunkMegs 512
```

### Missing textures on some maps

Some maps depend on texture packs from other maps. Check the map page on [ws.q3df.org](https://ws.q3df.org) to find required dependencies and download them.

### How do I save/load position for practice?

Use `savepos` command (requires `sv_cheats 1` in offline mode):
```
sv_cheats 1
bind F5 "savepos"   // saves current position
bind F9 "loadpos"   // loads saved position
```

### How do I log in to save records?

Use the in-game commands:
- `!login` - Log in to your mDd account
- `!logout` - Log out

### How do I check my records?

In-game server commands:
- `!my time <mapname>` - Your time on a map
- `!my besttimes` - Your best ranked times
- `!top <mapname>` - Top times for a map
- `!rank <mapname>` - Your rank on a map

### How do I find maps with specific features?

Use the `!find` command on mDd servers:
```
!find +rl +haste -jumppad    // maps with RL and Haste but no jumppads
```

### Email registration issues on q3df.org

Some email providers block automated emails. Try using a different provider (Gmail, Outlook) if you don't receive the verification email.

### How do I change my password?

Contact a q3df.org admin through Discord or the forums.
MD;
    }

    private function physics(): string
    {
        return <<<'MD'
## Physics Models in DeFRaG

DeFRaG features two fundamentally different physics models that drastically change movement rules and require different skill sets.

## VQ3 (Vanilla Quake 3)

The original Q3A physics. Based on rigid behavior where the player has almost no air control while holding the forward key. Acceleration in VQ3 is achieved almost exclusively through strafe-jumping, which requires mathematically precise mouse angles relative to the direction of movement.

VQ3 physics is perceived as more tactical and demanding on consistency in long passages.

### How Strafe-Jumping Works (VQ3)

The engine calculates maximum allowed speed (typically 320 UPS) in the direction of movement. If the player is already moving near max speed in the view direction, the engine blocks further acceleration.

However, by adding a strafe vector (A/D keys) and rotating the view so the movement vector creates an angle with the view axis, the engine "sees" the speed component in the new direction as lower than 320 UPS. This allows additional acceleration.

The player must maintain this angle in the "sweet spot" - a narrow range of degrees where acceleration is most effective.

### VQ3 Strafe-Jump Variants

- **Single-beat** - Alternating left/right strafe with each jump. Most universal for medium speeds.
- **Double-beat** - Changing strafe direction twice during one jump cycle (in air). Used for trajectory correction in tight corridors.
- **Half-beat** - Used at extremely high speeds (1000+ UPS) where acceleration zones are so narrow they nearly overlap. Minimal mouse movements with rapid key alternation.
- **Inverted strafe-jumping** - Moving the mouse against the direction of the pressed strafe key. Extremely demanding on muscle memory.

## CPM (Challenge ProMode)

Derived from the CPMA mod, combining elements from QuakeWorld and Quake 2. Features high-level air control - the player can smoothly turn 90+ degrees in the air without losing momentum, as long as they don't use the forward key but only strafe keys.

### CPM-Specific Features

- **Full bunny-hopping** - Maintains momentum from landing
- **Double-jumping** - Landing on a step or low ramp within 400ms of the previous jump grants a height bonus
- **Enhanced ramp-jumping** - Both height and directional boost using air control
- **Instant weapon switching** - No animation delay
- **Higher initial acceleration and mobility**

## Physics Comparison

| Feature | VQ3 (Vanilla) | CPM (Turbo) |
|---------|--------------|-------------|
| **Air Control** | Minimal, near zero with +forward | Extreme, allows turning in air |
| **Bunny Hopping** | Doesn't work (speed drops without strafing) | Fully functional, maintains momentum |
| **Double Jumping** | Not present | Present (400ms window on height difference) |
| **Weapon Switching** | Standard animation delay | Instant switch |
| **Ramp-Jumping** | Height boost only | Height + directional boost with air control |
| **Max Speed** | Theoretically unlimited, limited by input precision | Higher initial acceleration |
| **Ground Acceleration** | `pm_accelerate = 10.0` | `pm_accelerate = 15.0` |

## The Math Behind It

Movement in id Tech 3 is based on an anomaly in vector calculation. The engine tries to limit player speed to a defined value (320 UPS), but only in the direction of current movement. By adding a strafe vector and rotating the view with the mouse, a resultant vector is created that the engine doesn't consider as exceeding the limit on a given axis, allowing cumulative speed increase.

The acceleration is permitted only when the projection of the current velocity onto the wish direction is less than the maximum speed. This can be described as vector addition where the engine checks speed limits per-axis rather than on the resultant vector magnitude.
MD;
    }

    private function techniques(): string
    {
        return <<<'MD'
## Movement Techniques

### Circle-Jumping (CJ)

The fundamental starting technique for achieving high initial speed from standstill. The player stands sideways to the intended direction, performs a rapid mouse sweep (approximately 90 degrees) while holding forward + strafe keys, and jumps at the moment of peak ground speed.

A properly executed circle-jump can instantly catapult the player above 450 UPS.

### Strafe-Jumping (SJ)

The most important technique in trickjumping. Requires continuous jumping (bunny-hopping in the broad sense) with synchronized alternation of strafe keys and smooth mouse movement in the direction of the active strafe.

The engine is "fed" movement vectors that add to existing momentum, causing constant acceleration up to the theoretical engine limits.

### Rocket Jumping

A rocket explosion beneath the player's feet adds vertical and horizontal force vectors. In DeFRaG, player damage is often disabled or modified, making this a pure movement tool.

### Plasma Climbing

Rapid fire from the plasma gun against a wall at approximately 40-60 degree angle allows the player to literally "run up" vertical surfaces.

### Grenade / BFG Jumping

More difficult variants using the delayed grenade explosion or the massive BFG force for extreme bounces.

### Velocity Snapping (VSnapping)

Exploits rounding errors in the engine to maintain or gain speed at very high UPS (1625+). At these speeds, the engine's integer math creates predictable "snap" points where specific angles yield maximum acceleration.

### Skimming

Clipping through corners without losing speed. Requires precise angle and timing to slide past geometry that would normally block movement.

### Ramping / Step-up

An engine effect where the game "pulls" the player up onto the edge of a platform as if climbing stairs. Occurs at ledges between 0-18 units height.

### Wall Clipping

For several frames after jumping, the player can hit a wall without stopping. This brief window allows passing through tight gaps or maintaining speed near walls.

### Edgesliding

Sliding along an edge without friction. Very rare technique, famously used on maps like `w3sp-think9`.

### High Wall Jump

At low ledges (100-125 units), holding jump and re-tapping at the right moment produces a higher jump. Requires FPS locked to 75 or 125 (`com_maxfps`).

### Trimping (CPM)

Jumping at the end of a ramp for additional height. Combines ramp-jumping with CPM air control for maximum elevation gain.

### Ramp-Sliding (CPM)

Maintaining contact with a sloped surface to gain speed through gravity without losing it to friction. CPM-specific due to the enhanced air control needed for angle maintenance.

### Savepos

Built-in function for saving/loading positions during practice. Requires `sv_cheats 1`:
```
sv_cheats 1
bind F5 "savepos"
bind F9 "loadpos"
```
MD;
    }

    private function overbounce(): string
    {
        return <<<'MD'
## Overbounce (OB)

An engine bug where a player falling from a specific height onto a flat surface with zero or very low horizontal speed is bounced back to height as if hitting a bounce pad. This occurs due to a rounding error in the engine's collision detection.

The engine uses float variables for player position but rounds them for network transfer and certain physics checks. At specific fall heights, the calculated position at impact is exactly at floor level, but the engine fails to detect the surface collision that should zero out vertical speed. Instead, the speed is unchanged and in the next frame the player is "launched" back up.

## Types of Overbounce

### Vertical Overbounce (VOB)
Vertical bounce with 0 horizontal speed. The player is shot straight up, often higher than the original drop point.

### Horizontal Overbounce (HOB)
Fall energy is converted to horizontal movement. If the player has even minimal horizontal speed at impact, all falling energy transforms into extreme forward acceleration.

### Diagonal Overbounce (DOB)
Combination of vertical and horizontal - the player gains both upward and forward momentum.

### ZDOB / ZWOB (Zero-ups Diagonal/Weird OB)
DOB with near-zero speed at impact + mouse movement with +strafe. A very precise variant.

### SDOB (Slippery Diagonal OB)
On slick surfaces with very low speed (1-6 UPS). The slick surface prevents friction from absorbing the overbounce energy.

### Sticky Overbounce (SOB)
Triggering OB on the next jump from the same height. The player appears to "stick" to the surface with stored momentum.

### Weapon Overbounce
OB triggered by weapon knockback (plasma, rocket, plasma jump, rocket jump). The direction the player faces affects the result - try 0, 90, 180, 270 degrees.

### Quaded Variants
All above variants with Quad Damage powerup, multiplying the forces by 3.

## OB Detection in DeFRaG

DeFRaG has a built-in OB detector with 17 prediction types:
- **J** - Jump overbounce heights
- **G** - Go (walk-off) overbounce heights
- **B** - Both
- **p, P** - Plasma weapon OB (lowercase = normal, uppercase = jump variant)
- **r, R** - Rocket weapon OB
- **SOB** - Sticky overbounce
- **Quaded variants** of all the above

### OB-Related CVars

| CVar | Description |
|------|-------------|
| `df_ob_OffsetStartPosZ` | Offset for start position in OB detection |
| `df_ob_OffsetStopPosZ` | Offset for stop position |
| `df_ob_AllSlopes` | OB detection for all slopes |
| `df_ob_fast 0/1` | Fast OB detection mode (less framerate impact) |

## OB Heights Table

Overbounce heights depend on the frame time (8ms at 125 FPS). The full table of J (Jump) and G (Go/walk-off) overbounce heights is extensive, ranging from +48 down to -9999 units. Each entry has a specific height and offset value.

The complete OB heights table was originally compiled by 14K Inc. and is available on [q3df.org wiki](https://q3df.org/wiki?p=189).
MD;
    }

    private function groundboosting(): string
    {
        return <<<'MD'
## Groundboosting in the Code

*Originally documented by `<hk>` on q3df.org with actual Q3 source code analysis.*

Groundboosting is a technique where a player receives a speed boost from damage knockback while remaining on the ground. Understanding the code behind it reveals why it works.

## The Mechanism

When a player takes damage (e.g., from a rocket explosion), the engine sets the `PMF_TIME_KNOCKBACK` flag for a duration of 50-200ms. During this window:

1. The player's `pm_time` is set based on damage
2. `PM_WalkMove` is applied instead of `PM_AirMove` since the player never left the ground
3. **Friction is skipped** due to the knockback flag
4. The knockback velocity is preserved and the player slides along the ground at high speed

### Why It's Faster on CPM

CPM has `pm_accelerate = 15.0f` compared to VQ3's `10.0f`. This means during the knockback window, CPM physics applies more ground acceleration to the existing knockback velocity.

### Relevant Source Code

From `g_combat.c` - damage applies knockback:
- Knockback is calculated from damage amount
- `PMF_TIME_KNOCKBACK` flag is set on the player
- `pm_time` is set to the knockback duration

From `bg_pmove.c` - movement processing:
- If `PMF_TIME_KNOCKBACK` is active, friction calculation is bypassed
- `PM_WalkMove` still applies ground acceleration
- Result: player slides at knockback speed + ground accel without friction

## Teleporter Jumping

The same `PMF_TIME_KNOCKBACK` mechanism explains teleporter jumping. When a player exits a teleporter, the engine briefly sets the knockback flag to prevent the player from being "stuck" at the teleporter destination. During this window, the player can jump and retain the teleporter exit velocity without ground friction reducing it.

This is why timing your jump immediately after teleporting can give you a significant speed advantage.
MD;
    }

    private function hudAndTools(): string
    {
        return <<<'MD'
## HUD & Learning Tools

### CGazHUD (Camping Gaz Head-Up Display)

The most important training tool in DeFRaG. A graphical helper displaying optimal strafe angles in real-time.

Three bars on screen center show:
- Current direction
- Optimal angle
- Acceleration zones

**Enable:** `df_hud_cgaz 9` (recommended value - shows all info)

CGazHUD is essential for learning strafe-jumping. It visually shows you exactly where to point your mouse for maximum acceleration.

### SnapHUD

Optimizes angles relative to the engine's internal grid. Particularly useful at high speeds where the angle precision matters most.

**Enable:** `scr_hud_snap_draw 1`

### AccelMeter

Experimental strafe-jumping helper and efficiency meter. Displays a second crosshair at a horizontal distance from center, showing the gap between your chosen direction and the optimal strafing angle.

**Statistics shown:**
- Initial speed
- Acceleration efficiency percentage

**Note:** AccelMeter is designed for VQ3 acceleration. For CPM air acceleration (strafe-only without forward), it doesn't show the optimal angle but can still be useful as a reference point.

Documentation: [q3df.org/wiki?p=130](https://q3df.org/wiki?p=130)

### JumpMeter

Extension of AccelMeter. Displays history of the last 12 jumps with:
- Horizontal speed at landing
- Acceleration percentage
- Distance
- Height
- Maximum values for all 4 stats

**Enable:** `df_drawJumpmeters` with flag values:
- `1` = height meter
- `2` = distance meter
- `4` = only show high values
- Combine: `7` = all meters, all values

Documentation: [q3df.org/wiki?p=156](https://q3df.org/wiki?p=156)

### Speed Meter

`df_drawSpeed` - Display current speed:
- `0` = off
- `1` = standard display
- `2` = old-school conversion

### Crosshair Stats Display System (CHS)

Extensive system for displaying various data points around the crosshair: position, speed, OB prediction, ghost info, time, and more.

Two display slots (CHS0 and CHS1) can be configured to show different information simultaneously.

### HUD Customization

| CVar | Description |
|------|-------------|
| `df_hud_color 0-11` | HUD color |
| `df_hud_transparency 0.0-1.0` | HUD transparency |
| `df_hud_fontcolor 0-11` | Font color |
| `df_hud_fontshadow 0-1` | Font shadow |
| `df_hud_colorwarning 0-1` | Low values shown in red |
| `df_hud_forceteamcolors 0-1` | Team color for CTF FC |

### Demo Analysis Tools

- **DF Route Viewer** - Visualize and compare routes from multiple .dm_68 recordings. Identifies where speed is lost or where more efficient angles exist.
- **Uber Demo Tools (UDT)** - Suite for analyzing, cutting, and converting demo files between protocols. Can generate movement "heat maps" and auto-find interesting events.
- **Demo Cleaner** - Automatically deletes unsuccessful attempts, keeping only best times per map.
MD;
    }

    private function weaponsAndItems(): string
    {
        return <<<'MD'
## Weapons

All Quake III Arena 1.32 weapons. In DeFRaG, weapons are primarily movement tools, not combat weapons.

| Weapon | Abbrev | Damage | Fire Rate | Splash Damage | Splash Radius | Speed |
|--------|--------|--------|-----------|---------------|---------------|-------|
| **BFG10k** | bfg | 100 | 200ms | 100 | 120 | 2000 |
| **Gauntlet** | g | 50 | 400ms | - | - | melee |
| **Grappling Hook** | gh | 0 | - | - | - | 800 |
| **Grenade Launcher** | gl | 100 | 800ms | 100 | 150 | 700 |
| **Lightning Gun** | lg | 8 | 50ms | - | - | 768 range |
| **Machine Gun** | mg | 7 | 100ms | - | - | hitscan |
| **Plasma Gun** | pg | 20 | 100ms | 15 | 20 | 2000 |
| **Railgun** | rg | 100 | 1500ms | - | - | hitscan |
| **Rocket Launcher** | rl | 100 | 800ms | 100 | 120 | 900 |
| **Shotgun** | sg | 10x10 | 1000ms | - | - | hitscan |

**Notes:**
- Max ammo for all weapons: 200
- Knockback equals damage
- Self-damage is halved
- Quad Damage multiplier: 3x

### Movement Techniques per Weapon

| Weapon | Techniques |
|--------|-----------|
| Rocket Launcher | Rocket Jump, Rocket Climb |
| Plasma Gun | Plasma Climb, Plasma Jump |
| Grenade Launcher | Grenade Jump, Grenade Boost |
| BFG10k | BFG Jump (extreme force) |

## Items

### Health

| Item | Amount | Max | Notes |
|------|--------|-----|-------|
| Mega Health | +100 | 200 | Decays to 100 |
| Large Health | +50 | 100 | - |
| Medium Health | +25 | 100 | - |
| Small Health | +5 | over 100 | Can exceed 100 |

### Armor

| Item | Amount | Max | Notes |
|------|--------|-----|-------|
| Red Armor | +100 | 200 | Decays to 100 |
| Yellow Armor | +50 | 200 | Decays to 100 |
| Armor Shard | +5 | 200 | Can exceed 100 |

### Power-ups (30 second duration)

| Power-up | Effect |
|----------|--------|
| **Battle Suit** | Protection from splash damage and environmental hazards |
| **Flight** | Free flight movement |
| **Haste** | Doubled movement and fire speed |
| **Quad Damage** | 3x damage multiplier (affects knockback/jumps) |
| **Regeneration** | +5 HP per second |

### Holdables

| Item | Effect |
|------|--------|
| **Medikit** | Restores health to 100 (one-time use) |
MD;
    }

    private function gameModes(): string
    {
        return <<<'MD'
## Game Modes

### Run

The most common mode. A direct race to the finish line in the shortest possible time. Maps are linear paths from start trigger to end trigger.

Run maps are prefixed with `df_run` or simply `run`.

### Accuracy

The map is completed after hitting a certain number of targets with the Railgun (sniper rifle). Tests precision while moving.

Accuracy maps are prefixed with `df_acc`.

### Level

Similar to Run, but the map offers multiple alternative paths to the finish. Different routes may suit different skill levels or physics modes.

### Fast Capture (FC)

CTF (Capture the Flag) mode - fastest flag capture wins. Uses standard or modified Q3A CTF maps with DeFRaG movement physics.

Also known as "Fastcap" - tests both movement skill and route knowledge.

### Freestyle / Trick

Free style - emphasis on creativity of tricks, not time. Open arenas designed for performing tricks without a fixed goal. Players are judged on creativity and difficulty.

Freestyle maps are prefixed with `df_free`.

### Team Trick

Team trickjumping - cooperation of multiple players to perform tricks that are impossible solo. Requires coordination and communication.
MD;
    }

    private function consoleCommands(): string
    {
        return <<<'MD'
## Console Commands & Variables

This is a reference for the most important DeFRaG-related console commands and variables. For the complete list (6500+ lines), see the [q3df.org wiki](https://q3df.org/wiki?p=196).

### Essential Movement CVars

| CVar | Values | Description |
|------|--------|-------------|
| `com_maxfps` | 125 | Maximum FPS. Affects jump height and physics. |
| `pmove_fixed` | 0/1 | Fixed framerate physics simulation |
| `g_synchronousclients` | 0/1 | Sync mode (1 for offline, 0 for online) |
| `com_hunkMegs` | 128-512 | Memory allocation |

### HUD CVars

| CVar | Values | Description |
|------|--------|-------------|
| `df_hud_cgaz` | 0-9 | CGazHUD mode |
| `df_drawSpeed` | 0-2 | Speed meter |
| `df_drawJumpmeters` | 0-7 | Jump meters (flags: 1=height, 2=distance, 4=high only) |
| `df_hud_color` | 0-11 | HUD color |
| `df_hud_transparency` | 0.0-1.0 | HUD opacity |
| `df_hud_fontcolor` | 0-11 | Font color |
| `df_hud_fontshadow` | 0-1 | Font shadow |
| `df_hud_colorwarning` | 0-1 | Show low values in red |
| `scr_hud_snap_draw` | 0/1 | SnapHUD |

### Overbounce CVars

| CVar | Description |
|------|-------------|
| `df_ob_OffsetStartPosZ` | OB start position offset |
| `df_ob_OffsetStopPosZ` | OB stop position offset |
| `df_ob_AllSlopes` | Detect OB for all slopes |
| `df_ob_fast` | Fast OB detection (less FPS impact) |

### Network CVars

| CVar | Recommended | Description |
|------|-------------|-------------|
| `cl_maxpackets` | 125 | Packets per second to server |
| `snaps` | 125 | Updates per second from server |
| `rate` | 25000 | Max bandwidth |
| `cl_packetdup` | 0-1 | Duplicate packets for reliability |
| `cl_timenudge` | 0 | Latency compensation |
| `cl_allowDownload` | 1 | Enable map downloads |

### Renderer CVars

| CVar | Description |
|------|-------------|
| `r_picmip` | Texture quality (0=best, higher=lower) |
| `r_fullscreen` | Fullscreen mode |
| `r_mode` | Resolution mode |
| `r_gamma` | Gamma correction |
| `r_dynamiclight` | Dynamic lighting |
| `r_fastsky` | Simple sky rendering |

### General Commands

| Command | Description |
|---------|-------------|
| `savepos` / `loadpos` | Save/load position (sv_cheats 1) |
| `record` / `stoprecord` | Record/stop demo |
| `screenshot` | Take screenshot |
| `vid_restart` | Restart video |
| `snd_restart` | Restart sound |
| `exec <file>` | Execute config file |
| `bind <key> <command>` | Bind key |
| `toggle <cvar>` | Toggle boolean cvar |
| `vstr <name>` | Execute variable string |

### Modern Engine CVars (oDFe/CNQ3)

Some CVars have changed names in modern engines:

| Original Q3A | Modern (CNQ3/oDFe) | Description |
|-------------|---------------------|-------------|
| `sensitivity` | `m_speed` | Mouse sensitivity |
| `r_customwidth` | `r_width` | Render width |
| `r_customheight` | `r_height` | Render height |
| `r_overBrightBits` | `r_brightness` | Scene brightness |
| - | `in_mouse 1` | Raw mouse input (critical!) |

### oDFe-Specific CVars

| CVar | Description |
|------|-------------|
| `r_vbo 1` | Store static geometry in VRAM (big FPS boost) |
| `r_hdr 1` | HDR rendering |
| `in_lagged 0` | Disable input lag |
| `r_fbo 1` | Off-screen rendering for post-processing |
MD;
    }

    private function serverCommands(): string
    {
        return <<<'MD'
## mDd Server Commands

These commands work on q3df.org mDd-enabled servers. Type them in the in-game chat.

### Map Finding

| Command | Description |
|---------|-------------|
| `!find <keywords>` | Search maps by features. Use `+` for required, `-` for excluded. |
| `!find +rl +haste -jumppad` | Example: maps with RL and Haste, no jumppads |
| `!mapinfo <mapname>` | Detailed info about a map |
| `!random` | Load a random map |
| `!popular` | Show popular maps |

### Personal Records

| Command | Description |
|---------|-------------|
| `!login` | Log in to your mDd account |
| `!logout` | Log out |
| `!my time <map>` | Your time on a specific map |
| `!my besttimes` | Your best ranked times |
| `!my worsttimes` | Your worst ranked times |
| `!my bestranks` | Your best rankings |
| `!my timehistory <map>` | History of your times on a map |
| `!mytime <map>` | Quick time check |

### Leaderboards

| Command | Description |
|---------|-------------|
| `!top <map>` | Top times for a map |
| `!rank <map>` | Your rank on a map |
| `!ranktime <time> <map>` | What rank a given time would be |
| `!rankings` | Overall rankings |
| `!oldtop <map>` | Historical top times |
| `!recent` | Recently set records |

### Other

| Command | Description |
|---------|-------------|
| `!servers` | List of active servers |
| `!ratemap <1-5>` | Rate the current map |

*Credits: nightmare, hk, Pan, lovet, ruined, w3sp, marky, praet, arcaon*
MD;
    }

    private function mapping(): string
    {
        return <<<'MD'
## Mapping for DeFRaG

Creating maps for DeFRaG requires a specific approach to architecture focused on movement rather than combat.

## Editors

| Editor | Status | Notes |
|--------|--------|-------|
| **NetRadiant** | Recommended | Modern fork of GtkRadiant |
| **GtkRadiant 1.4/1.5/1.6** | Good | Classic, well-documented |
| **ZeroRadiant** | Alternative | - |
| **Q3Radiant** | Outdated | Original id Software editor |

## DeFRaG Mapping Basics

### Entity Files
Copy `defrag.def` / `defrag.ent` into your `baseq3/scripts` directory. These define DeFRaG-specific entities (timers, checkpoints, etc.).

### Timer Triggers
Maps must contain:
- **Start trigger** - Where the timer begins
- **Checkpoint triggers** - Optional intermediate timing points
- **Stop trigger** - Where the timer ends (finish line)

Place start triggers across the full width of the starting area so players cannot skip them.

### Map File Format
- Maps are `.pk3` files (ZIP archives containing .bsp + textures + shaders)
- BSP compiled from `.map` source files using Q3Map2
- Textures in `.jpg` or `.tga` format
- Texture structure: `textures/[pack_name]/texture.tga`
- Shaders in `scripts/` folder

### .defi Files
Each DeFRaG map should have an accompanying `.defi` file containing metadata:
- Map name
- Author
- Recommended physics (VQ3/CPM)
- Style (Run, Accuracy, etc.)

These allow the mod to properly categorize the map in menus.

## Workflow

1. **Conceptualize the route** - Create rough jump trajectory (white-boxing). Test if jumps are theoretically possible in both physics.
2. **Work in Radiant** - Define brushes and entities. Use grid size of 8 or 16 units to prevent collision errors.
3. **Compile with Q3Map2** - Convert `.map` to `.bsp`. Define lightmaps and visual effects. DeFRaG maps often prefer clean, minimalist textures ("fullbright" or "vertex light").
4. **Create .defi file** - Add metadata for the mod.
5. **Package as .pk3** - ZIP the .bsp, textures, shaders, and .defi into a .pk3 file.

## Mapping-Specific Techniques

### Slick Surfaces
Special shader (`func_slick`) eliminating friction. Players can't change direction on these but they serve as accelerators for strafe-jumps.

### Damage Boost Entities
Mappers can place entities that temporarily increase player self-damage, enabling extreme rocket-jumps.

### No-Vis Compilation (Nvid)
Compiling without visibility calculation. DeFRaG maps are often linear and in the void, so complex PVS (Potentially Visible Sets) calculation is unnecessary. This speeds up compilation and allows huge open spaces.

## Example Maps (Included with DeFRaG)

- `df-spawnroom-example` - Basic spawn room
- `df-accuracy-example` - Accuracy mode demo
- `df-level-example` - Level mode demo
- `df-shooter1` - Shooter mechanics
- `df-velopad1` - Velocity pad mechanics
- `df-black` - Minimal environment

## Mapping Resources

- [q3df.org wiki mapping guide](https://q3df.org/wiki?p=137)
- [Worldspawn level design tutorials](https://ws.q3df.org/level_design/)
- [Quake3World mapping resources](https://www.quake3world.com/forum/viewtopic.php?t=290)

## BSP File Structure

BSP (Binary Space Partitioning) files contain several lumps (data blocks) critical for DeFRaG:

| Lump | Name | Relevance |
|------|------|-----------|
| 4 | Leafs | Visible space definitions, affects performance at high speeds |
| 7 | Models | Geometry of solid objects for collision |
| 10 | Brushes | Basic building blocks, define collision zones |
| 13 | Vertices | All point coordinates, key for OB zone calculation |
MD;
    }

    private function engines(): string
    {
        return <<<'MD'
## Engines & Clients

The original quake3.exe has many limitations including a low map count limit (~1024) and outdated network code. The community has developed specialized engines.

## Recommended Engines

### oDFe (Open DeFRaG Engine)

The current recommended engine. Based on Quake3e, actively developed.

**Key Features:**
- **Vulkan Renderer** - 10-200% higher FPS stability compared to original OpenGL 1.1
- **Raw Mouse Input** - Automatically bypasses Windows mouse acceleration and pixel skipping
- **Extended File Limits** - Can handle all 15,000+ maps without crashes
- **Video Pipe** - Stream raw video data to ffmpeg for high-quality recording (4K@60fps)
- **Per-window gamma** - Important for streaming via OBS
- **HDR rendering** - Eliminates color banding on large surfaces
- **DoS protection** - Modern security against online attacks
- **QVM security fixes** - Patches critical vulnerabilities from 1999 code

**Developers:** |PsY|Jel, Runaos, sl1k

**Download:** [GitHub - Defrag-racing/oDFe](https://github.com/Defrag-racing/oDFe) or [JBustos22/oDFe](https://github.com/JBustos22/oDFe/releases/tag/latest)

### iDFe

Previous industry standard for DeFRaG. Contains integrated network communication fixes, improved .pk3 decompression support, and extended map limits (up to 20,000).

**Download:** [Google Drive](https://drive.google.com/drive/folders/0BxHNb39hE49iTWh3TWpBMzdtOW8)

### CNQ3

Primarily designed for CPMA but fully compatible with DeFRaG. Offers the best console auto-completion, 64-bit architecture, and integrated `/help` system.

### DFEngine (by ykram/Drakkar)

Older engine with HTTP download, console filters, window management, and raw input. Largely superseded by oDFe.

**GitHub:** [github.com/ykram/dfengine](https://github.com/ykram/dfengine)

### ioq3df (by Newbrict)

Fork of ioquake3 specifically for DeFRaG, with auto map download from external sources and dependency management.

## Open Source Projects

### OpenDF
Free version of the defrag mod.
**GitHub:** [github.com/oitzujoey/opendf](https://github.com/oitzujoey/opendf)

### Opensource Defrag (OSDF)
Complete rewrite under GPL v2 license, aiming for 1:1 compatibility with original DeFRaG mechanics while being fully independent of the Q3A SDK.
**GitHub:** [github.com/OpenQuake3/defrag](https://github.com/OpenQuake3/defrag)

## Related Projects

### Momentum Mod
Standalone game supporting DeFRaG physics (VQ3 and CPM). Unifies multiple movement mods (surf, bhop, defrag) on a modern platform.
- [Documentation](https://docs.momentum-mod.org/tags/defrag/)

## Demo Protocols

| Protocol | Quake Version | Notes |
|----------|--------------|-------|
| 66 | Q3A 1.29-1.30 | Very old |
| 67 | Q3A 1.31 | Transitional |
| **68** | Q3A 1.32 | Current standard |
| 90/91 | Quake Live | Not directly compatible, needs UDT conversion |

Tools like UDT_converter can transform old recordings from 2000 into modern protocol 68 for playback in current engines.

## Movie Making Tools

- **Q3MME (Quake 3 Movie Making Edition)** - Modified engine for smooth recording at high resolution/FPS regardless of real performance
- **VirtualDub** - Post-processing and frame assembly
- **WolfcamQL / Wolf Whisperer** - For Quake Live content
MD;
    }

    private function customization(): string
    {
        return <<<'MD'
## Customization

### Remove Teleporter Animation

Create a shader file `defrag/scripts/teleshader.shader` with:

```
textures/sfx/portal_sfx
{
    surfaceparm nomarks
    surfaceparm trans
    surfaceparm nolightmap
    {
        map $whiteimage
        blendFunc GL_ZERO GL_ONE
    }
}
```

This makes the teleporter visual effect invisible. Thanks to FM for this trick.

### Disable Multiplayer Sounds

To mute other players' sounds, use these console commands:

```
s_alMinDistance 0.01
s_useOpenAL 1
snd_restart
```

Requires an ioquake3-based engine (oDFe, iDFe, ioq3df).

### Miniview / Picture-in-Picture

Keybinds for spectating:
- **Left/Right arrows** - Cycle through team players
- **Up arrow** - Cycle camera modes
- **Down arrow** - Cycle miniview draw modes

### Modified Grenade Sound

Custom grenade timing sounds are available from the community. The most popular is by nlxajA (`zz-grenade_sound_xaj_v5.pk3`), which provides an audible countdown for grenade timing.

### Color Codes

DeFRaG supports Quake 3 color codes in names and chat:
- `^1` Red, `^2` Green, `^3` Yellow, `^4` Blue
- `^5` Cyan, `^6` Pink, `^7` White, `^0` Black

### Config Tool

DonPichol's ConfigTool is included with DeFRaG for quick configuration. For a web-based alternative, use Newbrict's [Online Config Generator](http://dimit.me/dfcfg/).
MD;
    }

    private function serverRules(): string
    {
        return <<<'MD'
## Server Rules

*Official q3df.org server rules. Last updated based on 2014 revision.*

### 1. Binary Cheats

**Prohibited:**
- Modified binaries / client code
- Memory modification tools
- Packet manipulation

**Allowed engines:**
- id Software quake3 (original)
- ioquake3
- OpenArena
- dfengine
- iodfe / iDFe
- cnq3
- oDFe

### 2. Scripting Cheats

No automated command sequences are allowed. This includes scripts that automatically execute timed sequences of movement commands.

### 3. Account Policy

- One account per person
- No sharing accounts
- Account sharing results in deletion of records

### 4. Not Acceptable Behavior

- No `+left` / `+right` (automated turning)
- No mousewheel jump binding
- Proper network settings required (see below)
- No wallbug exploitation

### 5. Recommended Network Settings

| CVar | Value |
|------|-------|
| `cl_maxpackets` | 125 |
| `snaps` | 125 |
| `rate` | 25000 |
| `cl_packetdup` | 2+ |

### 6. General Guidelines

- Be polite and respectful
- Speak English on international servers
- Help new players when possible

### Interpretation Notes

- **Trigger lagging**: Intentionally causing lag to manipulate timer triggers is considered cheating
- **Client code modification**: Even cosmetic modifications to the engine that could affect timing or physics are prohibited for competitive play
- **Mousewheel jumping**: Using mousewheel for jump input creates an unfair advantage due to the rapid input rate

### Speedrun.com Additional Rules

For records submitted to speedrun.com:
- Allowed engine versions: DeFRaG 1.91.23 - 1.91.25
- No scripting of sequences
- No external timing tools
- No mousewheel binds for movement
- View angles may only be changed via mouse
MD;
    }

    private function glossary(): string
    {
        return <<<'MD'
## Glossary

*Based on the q3df.org wiki glossary by `<hk>`.*

### Units & Timing

- **Frame** - One game tick. At 125 FPS = 8ms per frame.
- **Quake Unit** - The basic distance unit in the engine. 1 quake unit = approximately 2.54 cm.
- **UPS** - Units Per Second. Standard measurement of player speed.

### Techniques

- **Airstrafe** - Changing direction in the air using strafe keys + mouse movement
- **Bunny-hop (Bhop)** - Continuous jumping to maintain/gain speed
- **Circle-jump (CJ)** - Starting technique: 90-degree mouse sweep from standstill for high initial speed
- **Strafe-jump (SJ)** - Core acceleration technique: alternating strafe + mouse sync while jumping
- **Ramp-jump** - Using sloped surfaces for height/speed gain
- **Rocket-jump (RJ)** - Self-damage rocket for propulsion
- **Plasma climb** - Rapid plasma fire against wall for vertical movement
- **Groundboost** - Speed gained from knockback while staying on ground (friction skipped)
- **HeroHit** - Specific knockback technique
- **Skimming** - Clipping past corners without speed loss
- **Ramping (Step-up)** - Engine pulls player onto ledge edges
- **Edgesliding** - Friction-free sliding along edges
- **Velocity Snapping** - Exploiting rounding at 1625+ UPS for speed

### Overbounce Types

- **VOB** - Vertical Overbounce
- **HOB** - Horizontal Overbounce
- **DOB** - Diagonal Overbounce
- **ZDOB / ZWOB** - Zero-ups Diagonal / Weird OB
- **SDOB** - Slippery Diagonal OB
- **SOB** - Sticky Overbounce
- **b2b / o2b** - Specific pad lane references (bliss-beta2a)

### Map Terminology

- **Run** - Linear time trial map
- **Accuracy (Acc)** - Target-shooting map
- **Freestyle (Free)** - Open trick arena
- **Fastcap (FC)** - CTF speed capture
- **Level** - Multi-path run map
- **Slick** - Frictionless surface
- **Trigger** - Invisible zone activating timers/events

### Technical

- **Scripting** - Automated command sequences (prohibited in competition)
- **Code modification** - Altering engine binary (prohibited)
- **CHS** - Crosshair Stats display system
- **CGaz** - Camping Gaz HUD (strafe angle helper)
- **mDd** - Mod Defrag Daemon (server-side record system)
- **BSP** - Binary Space Partitioning (map file format)
- **PK3** - ZIP archive containing map assets
- **dm_68** - Demo file format (protocol 68)
- **savepos** - Save/load position feature

### Physics Modes

- **VQ3** - Vanilla Quake 3 physics
- **CPM** - Challenge ProMode physics (also called Turbo)
- **CPMA** - Challenge ProMode Arena (the original mod CPM physics came from)
MD;
    }

    private function historyAndCulture(): string
    {
        return <<<'MD'
## History & Culture

### Origins

DeFRaG was created in fall 2000 as a modification for Quake III Arena. The concept and name came from user **Belzel**, while the majority of programming was done by **Cyril "cgg" Gantin**. Other key contributors included **Cliff "m00m1n" Rowley** and **John "Ozone-Junkie" Mason**, who added ghost mode, cheat detection, and advanced timers.

Two iconic figures shaped the mod's culture:
- **CGAZ** - The "hacker" who developed the CGazHUD for visualizing acceleration angles
- **w3sp (2337)** - Pianist whose precise mouse control pushed human limits of game physics mastery

### Cultural Impact

DeFRaG transforms an FPS from a violent game into "virtual gymnastics." Henry Lowood (Stanford University) called it "transformative high-performance play." The machinima tradition dates back to the speedrun community of original Quake.

Mainstream media has used DeFRaG as a counterpoint to stereotypes about violent games.

### Version History Highlights

| Version | Date | Significance |
|---------|------|-------------|
| 1.43 | January 2002 | Early version defining basic run mechanics |
| 1.50 | March 2002 | First mass adoption by trickjumping community |
| 1.70 | August 2002 | Advanced meters and indicators |
| 1.80 | October 2002 | Optimized ghost mode for real-time comparison |
| 1.91 | April 2004 | Stable version, standard for many years |
| 1.91.20 | October 2009 | Most widespread stable version for a long time |
| 1.91.21 | January 2013 | Modern revision fixing filesystem compatibility |
| 1.91.25 | - | Last widely used stable version |
| 1.91.27 | - | Latest with experimental 1ms timer |

### The w3sp Scandal

One of the most famous players, **w3sp**, held the most records for a long time. It was eventually discovered that he assembled runs offline and used replay software to record them as if played online. Administrator **hk** deleted all his records and those of other cheaters discovered in the investigation.

### The defrag.racing Project

Modern platform developed by **Neyo (|PsY|Jel)** with 3,000+ hours of solo development:
- Demo processing pipeline (600k+ throughput)
- Rust-based map and ranking systems
- DefragLive bot (Python + WebSocket + Twitch extension)
- oDFe engine maintenance
- **GitHub:** [github.com/Defrag-racing](https://github.com/Defrag-racing)

### Community Specializations

The DeFRaG community is organized around:
- **Trickjumping** - The core discipline
- **Movie making** - Artistic video production
- **Map making** - Architecture for movement
- **Engine development** - Maintaining and improving clients
- **Web portals** - Community infrastructure
- **Online leaderboards** - Competitive record tracking

### Live Streams

- **DefragLive** ([defrag.tv](https://defrag.tv) / [twitch.tv/defraglive](https://twitch.tv/defraglive)) - 24/7 interactive spectator bot with Twitch extension
- **Defrag Legends** ([twitch.tv/defraglegends](https://twitch.tv/defraglegends)) - 24/7 world record stream
- Hosted by neiTem [gt]

### Related Games

| Game | Relationship |
|------|-------------|
| **Momentum Mod** | Standalone with DeFRaG physics (VQ3 + CPM) |
| **Diabotical** | Arena FPS with announced defrag support |
| **Warsow / Warfork** | Indie FPS with physics quirks |
| **Reflex Arena** | Arena FPS with CPM physics |
| **Quake Live** | Official F2P Quake with race mode (OB removed) |
| **OpenArena** | Free Q3A clone compatible with DeFRaG mod |
MD;
    }

    private function movies(): string
    {
        return <<<'MD'
## DeFRaG Movies & Media

DeFRaG videos represent a unique machinima genre combining high technical difficulty with artistic expression. They were crucial for promoting the mod before social media existed.

## Award-Winning Productions

| Movie | Creator | Awards |
|-------|---------|--------|
| **Tricking iT2** | jrb (Jethro Brewin) | 5 Golden Llamas 2004: Best Picture, Audio, Tech, Editing, Quake Movie |
| **Reaching Aural Nirvana** | mrks | Golden Llamas 2005, Best Audio |
| **defragged** | Margit Nobis | Vienna Independent Shorts 2005 (art-house machinima) |

## Iconic Films

| Movie | Creator | Description |
|-------|---------|-------------|
| **Get Quaked 3** | Shaolin Productions | 2003 revolution in editing technique. Dynamic cameras, music-synced cuts. |
| **f33l** | w3sp / gluecks | Cult classic. CPM movement aesthetics in castle maps. Boards of Canada soundtrack became synonymous with DeFRaG. |
| **Genesis: The beginning of...** | Quan-Time | Most important tutorial video ever. Taught generations circle-jumping and plasma climbing. |
| **Kaleidoscope** | - | Highly rated community film |
| **Aerodynamic / Aerodynamic 2** | - | Community favorite |
| **Maze - Hud** | - | - |
| **Defragging Is Not A Crime** | D-F Production | - |
| **Edge of the Earth** | rEnk | - |
| **DeFRaG World Cup 2008** | trixo | DFWC highlight reel |
| **Destiny** | - | - |
| **Defragez-vous?** | - | - |
| **Celestia** | - | - |
| **DeFRaG Revolution** | - | - |
| **Run Elite trilogy** | - | Series |
| **w3sp strafes** | w3sp | Legendary strafe videos |
| **AU_DESSUS** | Riviere Francois | - |
| **Reach the Moon** | - | Often cited as having the most difficult technical runs in history |
| **Event Horizon (series)** | - | World record captures with high information value |

## Notable Producers

- **Shaolin Productions** - "Bible" of the genre. Dynamic camera following players from unusual angles, music-synced editing.
- **w3sp (gluecks)** - Player and editor. Brought "flow" and beauty of movement to DeFRaG videos.
- **jrb** - Tricking iT2 creator
- **mrks** - Reaching Aural Nirvana
- **m1tsu, rEnk, trixo** - Community moviemakers
- **Kabcorp** - Modern producer, manages DFWC archives, high-quality montages with electronic music (DnB, liquid)

## Video Production Tools

| Tool | Purpose |
|------|---------|
| **Q3MME** | Modified engine for smooth high-res/FPS recording |
| **VirtualDub** | Post-processing and frame assembly |
| **GtkRadiant** | Map tweaks for cleaner cinematic shots |
| **ffmpeg** | Video encoding (with oDFe video pipe) |

## Video Archives

- [ESReality Movies](https://www.esreality.com/?a=movies)
- [The Movie Vault](https://themovievault.net/browse/quake-3)
- [iGMDb](https://www.igmdb.org/?page=search&game_id=29)

## Frag Movies (Community Overlap)

These aren't pure DeFRaG but represent the broader Quake movie culture:
- Singularity
- The Contenders 2 (CPM)
- Mercurial
- Forever 2
- dorftrottel
MD;
    }

    private function competitions(): string
    {
        return <<<'MD'
## Competitions & DFWC

### DeFRaG World Cup (DFWC)

The main worldwide competition, held approximately once per year. The format consists of 7 rounds, with new secret maps released weekly. Players have 7 days to achieve the best possible time on each map.

**Website:** [dfwc.q3df.org](https://dfwc.q3df.org)

### DFWC History

| Year | Mode | Players | Prize Pool | Winner | Runner-up | 3rd |
|------|------|---------|-----------|--------|-----------|-----|
| 2014 | VQ3 | 127 | $738 | DeX | Strangeland | yotoon |
| 2021 | VQ3 | 140 | $3,884 | Strangeland | DeX | Frog |
| 2021 | CPM | 225 | $3,884 | Bazz | w00dy | annh |

The 2021 DFWC also awarded prizes to map creators, stimulating quality map design.

### Other Competitions

- **dfcomps.ru** - Regular online defrag competitions
  - [dfcomps.ru](https://dfcomps.ru)
- **Speedrun.com** - DeFRaG category with community-maintained rules
  - [speedrun.com/defrag](https://www.speedrun.com/defrag)

### Online Records System

The primary record system runs through **q3df.org** with mDd (Mod Defrag Daemon) integration:
- Players upload demos which are verified (anti-cheat, BSP checksum)
- Automatic recording of every completed run
- Demos serve as primary proof of achievement
- Records are verified before appearing on leaderboards

**defrag.racing** provides an alternative modern leaderboard interface with demo processing and Rust-based ranking system.

### Notable Players

**VQ3 Legends:**
Strangeland, DeX, Shio, Goper, Enter, Jel, SenTineL, kiccel, lithz

**CPM Legends:**
Bazz, w00dy, annh, goblin, Icarus, hox

**All-time Legends:**
ZyaX, ifoo, arcaeon, KiD (#1 US), Kiddy (rocket maps), CeTuS, Infinite Trajectory, xas, glm

### Liquipedia

DFWC events are documented on Liquipedia:
- [DFWC 2014 VQ3](https://liquipedia.net/arenafps/DeFrag_World_Cup/2014/VQ3)
- [DFWC 2021 VQ3](https://liquipedia.net/arenafps/DeFrag_World_Cup/2021/VQ3)
- [DFWC 2021 CPM](https://liquipedia.net/arenafps/DeFrag_World_Cup/2021/CPM)
MD;
    }

    private function changelog(): string
    {
        return <<<'MD'
## DeFRaG Changelog

*Compiled from the official DeFRaG changelog on q3df.org.*

### Latest Stable Versions

**1.91.27** (Latest)
- Experimental 1ms timer (`df_timer_ms 1`)
- `cg_noDamageKick` - Disable screen shake from damage
- `df_cl_fastRespawn` - Instant respawn
- `varCycleOnce` command

**1.91.25**
- Standard stable release widely used for competition
- Download: [q3defrag.org](https://q3defrag.org/files/defrag/defrag_1.91.25.zip)

**1.91.21** (January 2013)
- Modern revision fixing filesystem compatibility issues
- Improved Unicode path support

**1.91.20** (October 2009)
- Long-term most widespread stable version
- Basis for most competition records

### Historical Versions

| Version | Date | Key Changes |
|---------|------|-------------|
| 1.91.09+ | 2005+ | Improved anti-cheat systems |
| 1.92.01 | July 2009 | Experimental unstable branch (stalled) |
| 1.91 | April 2004 | Became the standard for years |
| 1.80 | October 2002 | Optimized ghost mode |
| 1.70 | August 2002 | Advanced meters and indicators |
| 1.50 | March 2002 | First mass adoption |
| 1.43 | January 2002 | Early version with basic mechanics |

### Download

All versions available at: [q3defrag.org/files/defrag/](https://q3defrag.org/files/defrag/)

The experimental 1.92.xx branch tested new features but development stalled. All modern development focuses on the 1.91.xx stable branch and engine improvements (oDFe).
MD;
    }

    private function communityLinks(): string
    {
        return <<<'MD'
## Community Links

### Main Websites

| Website | URL | Description |
|---------|-----|-------------|
| **q3df.org** | [q3df.org](https://q3df.org) | Main community portal - servers, leaderboards, wiki, forum |
| **defrag.racing** | [defrag.racing](https://defrag.racing) | Modern platform - records, maps, demos, rankings |
| **Worldspawn** | [ws.q3df.org](https://ws.q3df.org) | Q3 map/model/skin archive (since 2006) |
| **dfcomps.ru** | [dfcomps.ru](https://dfcomps.ru) | Online defrag competitions |
| **DFWC** | [dfwc.q3df.org](https://dfwc.q3df.org) | DeFRaG World Cup website |
| **speedcapture.com** | [speedcapture.com](https://speedcapture.com) | CTF fast capture records |

### Social & Community

| Platform | Link |
|----------|------|
| **Discord** (main) | [discord.gg/ZG4dKNVQJu](https://discord.gg/ZG4dKNVQJu) |
| **Twitch** (DefragLive) | [twitch.tv/defraglive](https://twitch.tv/defraglive) - 24/7 interactive bot |
| **Twitch** (Legends) | [twitch.tv/defraglegends](https://twitch.tv/defraglegends) - 24/7 WR stream |
| **Facebook** | [facebook.com/quake3defrag](https://www.facebook.com/quake3defrag/) |
| **Reddit** | r/QuakeDefrag, r/ArenaFPS |
| **Steam Group** | Official DeFRaG Steam Group |

### Downloads

| Resource | Link |
|----------|------|
| **DeFRaG mod** (latest) | [q3defrag.org/files/defrag/](https://q3defrag.org/files/defrag/defrag_1.91.25.zip) |
| **oDFe engine** | [GitHub](https://github.com/JBustos22/oDFe/releases/tag/latest) |
| **Maps (Worldspawn)** | [ws.q3df.org/maps/](https://ws.q3df.org/maps/) |
| **Map pack (ModDB)** | [ModDB](https://www.moddb.com/mods/defrag/addons/map-pack-111) |
| **Config generator** | [dimit.me/dfcfg/](http://dimit.me/dfcfg/) |

### GitHub Repositories

| Repository | Description |
|-----------|-------------|
| [Defrag-racing](https://github.com/Defrag-racing) | Organization (oDFe, DefragLive, defrag-racing-project, launcher) |
| [Defrag-racing/oDFe](https://github.com/Defrag-racing/oDFe) | oDFe main fork |
| [JBustos22/oDFe](https://github.com/JBustos22/oDFe) | oDFe (Jelle fork) |
| [ykram/dfengine](https://github.com/ykram/dfengine) | DFEngine (older) |
| [OpenQuake3/defrag](https://github.com/OpenQuake3/defrag) | Opensource Defrag |
| [oitzujoey/opendf](https://github.com/oitzujoey/opendf) | OpenDF |
| [Quake3e](https://github.com/ec-/Quake3e) | Quake3e (oDFe upstream) |

### Archives & Historical

| Website | Description |
|---------|-------------|
| [ESReality](https://www.esreality.com) | Quake community + movies section |
| [ModDB DeFRaG](https://www.moddb.com/mods/defrag) | Mod page with downloads |
| [defrag.fandom.com](https://defrag.fandom.com/wiki/DeFRaG_Wiki) | Fandom wiki |
| [Wikipedia: DeFRaG](https://en.wikipedia.org/wiki/DeFRaG) | Wikipedia article |
| [Grokipedia](https://grokipedia.com/page/DeFRaG) | Encyclopedia article |
| [TeamLiquid thread](https://tl.net/forum/games/420979-quake-3-defrag-parkour-for-nerds) | "Parkour for nerds" |

### Setup Guides

| Guide | Link |
|-------|------|
| Mike Martin's setup + vsnapping | [mikemartin.co](https://www.mikemartin.co/mods/quake3/defrag_setup) |
| Newbrict's install guide | [GitHub Gist](https://gist.github.com/Newbrict/a98463c166a3b4572fe77ac9b08bbcb4) |
| Speedrun.com rules | [speedrun.com/defrag/guides/0xv5y](https://www.speedrun.com/defrag/guides/0xv5y) |

### Video Tutorial Resources

| Resource | Description |
|----------|-------------|
| **Genesis: The beginning of...** | Comprehensive tutorial film by Quan-Time |
| **Ryodox tutorials** | Short but comprehensive with demos |
| **cpm skillz** (eS) | CPM-specific techniques |
| **TrickingQ3 tutorials** | Extensive tutorials with demos |
| **MrR's basics & tricks** | Short tutorials organized by map |
| **breakdown-hq.com** | Trickjumping techniques + training maps |

### Chinese Community

| Platform | Link |
|----------|------|
| Bilibili | [bilibili.com/read/readlist/rl133663](https://www.bilibili.com/read/readlist/rl133663) |
MD;
    }

    private function antiCheatAndDemos(): string
    {
        return <<<'MD'
## Anti-Cheat & Demo System

### DeFRaG Built-in Anti-Cheat

DeFRaG includes several anti-cheat measures:

- **CVar Restrictions** - Certain settings are enforced and monitored during competitive play
- **BSP Checksum Verification** - Map file integrity is verified against known checksums to prevent modified maps
- **Timer Encryption** - Times are encrypted to prevent tampering
- **Authentication System** - Player identity verification for record submission

### Demo Recording

The mod automatically records a demo of every completed run. These demos are the primary proof of achievement and are used for:

- **Record verification** - Demos are verified before times appear on leaderboards
- **Analysis** - Players study demos to learn routes and techniques
- **Movie making** - Demos are the source material for DeFRaG videos
- **Dispute resolution** - If a time is questioned, the demo provides evidence

### Demo File Format

Demos use the `.dm_68` extension (protocol 68 for Q3A 1.32). They contain:
- Complete player input history
- Server state snapshots
- Map and mod version information
- Timing data

### Demo Management

**Recording:**
```
record <demoname>     // Start recording
stoprecord            // Stop recording
```

DeFRaG's auto-record feature (`df_autorecord`) automatically starts recording when a timer starts and saves the demo when a run is completed.

### Demo Analysis Tools

| Tool | Purpose |
|------|---------|
| **DF Route Viewer** | Visualize and compare routes from multiple demos |
| **Uber Demo Tools (UDT)** | Analyze, cut, convert between protocols, generate heat maps |
| **Demo Cleaner** | Auto-delete failed attempts, keep only best times |
| **UDT_converter** | Convert old protocol demos to modern format |

### Protocol Compatibility

| Protocol | Version | Compatibility |
|----------|---------|--------------|
| 66 | Q3A 1.29-1.30 | Convertible via UDT |
| 67 | Q3A 1.31 | Convertible via UDT |
| **68** | Q3A 1.32 | Current standard |
| 90/91 | Quake Live | Requires conversion |
MD;
    }
}
