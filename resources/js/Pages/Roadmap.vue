<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const activeVersion = ref(1);

// Version 2: All individual items from the comprehensive list
const flatItems = [
    'Implemented full donation tracking system with SiteDonation, SelfRaisedMoney, DonationGoal models',
    'Added database migrations for donation goals, site donations, self-raised money tracking',
    'Created donation approval workflow supporting pending, approved, rejected statuses',
    'Integrated currency conversion for EUR, USD, CZK using live exchange rates',
    'Developed DonationResource in admin panel for managing donations with approval workflow',
    'Built DonationGoalResource for setting and managing yearly donation goals',
    'Created SelfRaisedMoneyResource for tracking YouTube/Twitch revenue with full CRUD',
    'Launched public donations page (/donations) with currency selector for USD/EUR',
    'Added current year progress bar on donations page showing crowd-raised (green) and self-raised (purple) splits',
    'Included "Where Your Support Goes" section detailing ~€1,200/year operational costs',
    'Featured "A Brief History" section explaining development since 2021',
    'Enabled interactive year-by-year donation history with collapsible sections',
    'Displayed individual donation entries with color-coded borders (green donations, purple self-raised)',
    'Added all-time total progress bar with visual crowd/self-raised split',
    'Implemented highlight animation for "operational costs" link',
    'Added manual update disclaimer to donations page',
    'Created global DonationProgressBar footer component visible on all pages except /donations',
    'Designed footer progress bar with animated hover effects (scale, brightness) and call-to-action text',
    'Applied darker background (bg-black/60) to footer for better contrast',
    'Built DonationController@index endpoint to render donations page with all data',
    'Added DonationController@getProgress API returning current year progress splits, totals, goal, percentage',
    'Updated notification system with world record toggle in record notifications',
    'Added notification preview settings for unread_only and hide_old_wrs',
    'Enhanced notification processing to respect preview settings',
    'Improved SystemNotificationMenu with preview filtering',
    'Updated NotificationsView with better filtering and display',
    'Added GET /donations route for public donations page',
    'Added GET /api/donations/progress route for footer widget',
    'Applied consistent backdrop-blur-xl bg-black/40 styling matching Profile page boxes',
    'Used color-coded progress bars (green crowd-raised, purple self-raised)',
    'Added smooth animations and transitions throughout donations features',
    'Ensured responsive design with mobile support for donations page',
    'Incorporated interactive elements with hover states in donations UI',
    'Adopted transparent messaging about operational costs vs out-of-pocket expenses',
    'Included clear call-to-action for DefragLive spectating and YouTube support',
    'Used professional tone explaining development history and AI-assisted solo work',
    'Added links to GitHub (Defrag-racing org) and Twitch (DefragLive) on donations page',
    'Created OfflineRecord model with ranking for practice/offline demos (df/fs/fc gametypes)',
    'Added offline_records table with optimized composite indexes for leaderboard queries',
    'Implemented automatic rank calculation and updates on new record creation',
    'Added gametype field to uploaded_demos to distinguish online (mdf/mfs/mfc) vs offline (df/fs/fc)',
    'Included record_date tracking in uploaded_demos for accurate timestamps',
    'Wrote comprehensive OFFLINE_RECORDS.md documentation on architecture, performance, usage',
    'Enhanced DemoProcessorService to differentiate offline vs online demos',
    'Added createOfflineRecord() method to auto-create offline records from processed demos',
    'Improved Python demo processor (process_single_demo.py) for better metadata extraction',
    'Updated renamer.py with additional gametype detection logic',
    'Updated production storage documentation with Backblaze B2 configuration details',
    'Created ReassignDemosToRecords command for re-linking demos after record repopulation',
    'Built test-map-viewer.html with Three.js-based BSP map renderer',
    'Added texture extraction scripts (export_textures.py, parse_bsp_to_json.py)',
    'Included textures for pado and pornstar-cpmrun maps with skybox support',
    'Created TestMapViewerController for serving map viewer',
    'Implemented real-time 3D navigation with WASD controls and mouse look',
    'Enhanced MapRecord component with improved time display and demo download links',
    'Moved all navigation links to dedicated second row in MainLayout.vue for better organization',
    'Placed first row in header with Logo, Search, Notifications, Profile',
    'Added second row navigation links visible on desktop',
    'Removed progressive hiding of navigation items - all visible on md+ screens',
    'Improved mobile menu dropdown to show all navigation items',
    'Added border-b to first header row to separate from navigation row',
    'Applied similar header structure to HomeLayout.vue',
    'Improved shadow gradient fade effect on HomeLayout header',
    'Added comprehensive filtering system to maplists with sort options',
    'Enabled view toggling between Public/My Maplists/Liked maplists',
    'Improved maplist card UI with hover effects and better spacing',
    'Added inline editing for maplist name and description',
    'Added map count badges and like counters to maplists',
    'Improved modal styling with backdrop blur effects',
    'Added play later functionality integration to MapView',
    'Improved layout and spacing on MapView',
    'Added maplist quick-add UI to MapView',
    'Added consistent shadow gradient fade effects to all pages',
    'Applied backdrop-blur-xl bg-black/60 for consistent opacity across site',
    'Applied consistent box shadows and border styling across all pages',
    'Updated Modal.vue to improve backdrop blur and z-index layering',
    'Updated MaplistCard.vue to add interactive hover states and improved visual hierarchy',
    'Created Tag model and migration with name, display_name, category, usage_count fields',
    'Built TagController with full CRUD for tags on maps and maplists',
    'Added TagSeeder populating categories (Difficulty, Movement, Weapons, Items, Environment, Functions)',
    'Implemented tag filtering for maps with backend support',
    'Enabled tag adoption where public maplist tags propagate to all maps',
    'Ensured tag persistence on maps after maplist deletion',
    'Created tag dropdown UI with alphabetical sort, search, click-to-add',
    'Added visibility control for maplists with public/private toggle and confirmation dialog',
    'Implemented public warning modal noting permanent public status',
    'Added visibility badge (green "Public" or gray "Private") on maplist header',
    'Moved reorder button to top-right below Edit/Delete for better UX',
    'Improved maplist layout with reorganized title, stats, action buttons',
    'Added full tag CRUD on maplist detail page with real-time updates',
    'Used Vue Teleport for tag dropdown positioning to avoid z-index issues',
    'Set dropdown behavior to appear below input on focus, hide on blur',
    'Filtered tags in dropdown to show only unadded ones',
    'Removed category labels from tag buttons for cleaner UI',
    'Ensured responsive design for tag input and dropdown on all screens',
    'Created maplists table with public/private, Play Later flag, social counters',
    'Created maplist_maps pivot table for many-to-many with ordering',
    'Created maplist_likes and maplist_favorites tables for social interactions',
    'Added indexed columns for performance',
    'Built Maplist model with relationships and social helpers',
    'Created MaplistMap, MaplistLike, MaplistFavorite pivot models',
    'Updated User model to auto-create Play Later on registration',
    'Created CreatePlayLaterMaplists command for 660 existing users with duplicate checks',
    'Built full REST API in MaplistController',
    'Enforced privacy (Play Later hidden from public, always private, owner-only access)',
    'Integrated profile with user_maplists (top 3 most favorited)',
    'Created AddToMaplistModal.vue for adding maps from any page',
    'Listed user\'s maplists in modal with counts and on-the-fly creation',
    'Added real-time search in AddToMaplistModal',
    'Built MaplistCard.vue with 2x2 thumbnail preview of first 4 maps',
    'Added Q3 color-coded usernames using $q3tohtml() in MaplistCard',
    'Displayed social stats (likes, favorites, map count) in MaplistCard',
    'Fixed thumbnail paths to /images/thumbs/ with error handling',
    'Added Play Later badge in MaplistCard',
    'Created Maplists/Index.vue with public browser grid, sort by likes/favorites',
    'Redesigned Maplists/Show.vue with card-based grid using MapCard',
    'Added breadcrumb navigation to Maplists/Show',
    'Conditional UI for Play Later vs regular (hide social for Play Later)',
    'Added server selection dropdown for Play Later owners (online servers only)',
    'Added play button per map copying connect command',
    'Added remove button for map owners in Maplists/Show',
    'Q3 color-coded creator names in Maplists/Show',
    'Added empty state with call-to-action',
    'Integrated "Add to Maplist" button in MapView for authenticated users',
    'Displayed top 3 favorited public maplists in Profile.vue grid',
    'Added "Maplists" link to MainLayout navigation (desktop/mobile)',
    'Added "Play Later" quick access to user dropdown',
    'Added "All My Maplists" link to user dropdown',
    'Active state detection for maplist routes in MainLayout',
    'FileController.php for case-insensitive file serving on /storage/* and /baseq3/*',
    'Used strcasecmp() for directory scanning to resolve case mismatches',
    'Eliminated client-side 404 spam from multiple case variations',
    'Enhanced ScrapeQ3dfModels.php with better model type detection',
    'Improved classification of skin packs vs complete models',
    'Added better handling of base model detection from skin files',
    'Renumbered model IDs sequentially from 1-107',
    'Updated approval status filtering in ModelsController',
    'Added shader texture fallback in MD3Loader.js to baseq3 for missing PK3 textures',
    'Fixed duplicate model rendering by skipping PK3 override for base Q3 models',
    'Removed Wiki integration',
    'Fixed weapon mute button by handling weapon sounds',
    'Allowed users to download own uploads regardless of approval status',
    'Fixed myUploads boolean type',
    'Cleared approval_status URL param when not viewing "My Uploads"',
    'Allowed texture/shader-only weapon uploads without MD3 files',
    'Removed MD3 file requirement check',
    'Made main_file optional (NULL) for weapons without MD3',
    'Enabled texture/shader packs to override base weapon appearance',
    'Added sound loading/playback for all Q3 weapons',
    'Implemented weapon-specific sound configs',
    'Configured Lightning Gun sounds',
    'Configured Railgun sounds',
    'Added sound cleanup on model unload to prevent leaks',
    'Ensured sounds play to completion after fire button release',
    'Added fire() and fireWithHit() methods to weapon models',
    'Implemented weapon-specific fire rates',
    'Added keyboard (Ctrl) and mouse firing controls',
    'Enabled continuous firing for automatic weapons',
    'Added weapon kick/recoil animation on fire',
    'Created plasma projectile as blue sprite billboard with trail particles',
    'Created rocket projectile as missile model with exhaust flame',
    'Created railgun projectile as instant green beam trail',
    'Created lightning gun projectile as animated electric beam',
    'Created grenade projectile as metallic sphere with gravity arc',
    'Created BFG projectile as multi-layered green glowing sphere',
    'Created machinegun/shotgun projectiles as bullet tracers',
    'Created grapple projectile as rocket model with metallic texture',
    'Spawned projectiles from muzzle flash with proper direction',
    'Added automatic projectile cleanup and lifetime management',
    'Fixed railgun fire sound bug',
    'Used Web Audio API for independent railgun sound playback',
    'Created ImportBaseWeapons artisan command',
    'Handled non-standard filenames in ImportBaseWeapons',
    'Added main_file column to models table',
    'Added \'item\' category to model category enum',
    'Implemented loadWeaponModel() in MD3Loader.js for composite weapons',
    'Loaded main weapon body and found TAG points',
    'Auto-loaded and attached weapon parts',
    'Handled missing parts gracefully',
    'Added isWeapon prop to ModelViewer',
    'Implemented separate camera positioning for weapons',
    'Positioned weapons at different height than player models',
    'Updated modelFilePath to use main_file for non-standard weapons',
    'Passed isWeapon prop to ModelViewer for rendering',
    'Skipped skin file loading for weapons',
    'Loaded shaders from models.shader before model loading',
    'Supported embedded shader names in MD3 surfaces',
    'Added texture fallback chain',
    'Handled weapon structure with main MD3 and separate parts via TAGs',
    'Tested with grapple',
    'Marked shells as \'item\' category',
    'Ensured all weapon parts load/attach via TAG system',
    'Added single-click head icon generation (64x64 PNG)',
    'Auto-focused on head mesh for icon capture',
    'Used FOV-based camera for consistent framing',
    'Captured and uploaded 64x64 PNG screenshot',
    'Restored camera position after head icon capture',
    'Added browser-based GIF thumbnail generation (300x300 animated)',
    'Created 360° rotation animation (36 frames, 10° per frame)',
    'Auto-positioned camera for full-body framing',
    'Optimized GIF encoding',
    'Added progress tracking during GIF generation',
    'Combined upload of GIF + head icon in single request',
    'Added head_icon column to models table',
    'Added thumbnail_path column',
    'Added base_model_file_path column',
    'Fixed mass assignment adding head_icon, thumbnail_path, base_model_file_path to $fillable',
    'Added saveHeadIcon() endpoint',
    'Updated saveThumbnail() to handle GIF and head icon',
    'Consolidated head icon generation into single-click',
    'Added notification system for success/error in thumbnails',
    'Added progress indicators for GIF and head icon',
    'Added performance timing metrics',
    'Added "Go Back" button to model detail pages',
    'Cleaned unused code',
    'Used Web Workers for non-blocking GIF encoding',
    'FOV-based distance calculation',
    'Detected head mesh via Y-axis traversal',
    'Canvas-based resizing for optimal file sizes',
    'Multipart FormData upload for binary files',
    'Extended Q3ShaderMaterialSystem.loadTexture() with fallbackBaseUrl',
    'Auto-fallback shader textures to baseq3 when missing from PK3',
    'Matched MD3Loader texture fallback for consistency',
    'Enhanced loadTextureForMesh() with multi-extension fallback',
    'Added fallbackBaseUrl property to MD3Loader',
    'Updated ModelsController to create separate entries for each model in PK3',
    'Handled model+skin combinations with own database records',
    'Updated scraper for multi-model packs',
    'Added pagination support to scraper with --pages, --start-page, --reverse',
    'Implemented download history tracking',
    'Better error handling and progress reporting in scraper',
    'Added sort functionality (newest/oldest) to model index',
    'Improved model type badge styling',
    'Enhanced model viewer layout',
    'Added dynamic lighting controls to 3D model viewer',
    'Implemented ambient, directional, back light controls',
    'Added updateLightingFromScene() in Q3ShaderMaterial',
    'Exposed light control methods via ModelViewer API',
    'Adjusted default lighting for color accuracy',
    'Reorganized model detail layout',
    'Positioned light controls between animations and sounds',
    'Made animation buttons compact/modern',
    'Removed frame count display from animation buttons',
    'Made sound buttons compact',
    'Increased page container width from max-w-7xl to max-w-8xl',
    'Added collapsible light controls panel',
    'Added light references as module variables',
    'Created 8 light control methods',
    'Added getLightSettings()',
    'Implemented real-time shader uniform updates',
    'Updated shader animation loop',
    'Replaced multiple-material approach with single THREE.ShaderMaterial',
    'Implemented multi-stage shader blending in fragment shader',
    'Supported up to 4 stages with blend functions',
    'Added tcGen environment mapping',
    'Implemented tcMod scroll animations',
    'Added rgbGen lightingDiffuse',
    'Added static texture cache',
    'Implemented smart texture fallback',
    'Fixed transparency detection',
    'Supported texture format fallback',
    'Added automatic sarge sound fallback',
    'Eliminated 404 console spam for missing sounds',
    'Added success logging for loaded sounds',
    'Adjusted models per page from 24 to 12',
    'Fixed rendering issues with brandon\'s glowing hat, mynx\'s shiny surfaces, bones\' cyan hologram',
    'Improved performance significantly via shader rewrite',
    'Added base model detection',
    'Classified model types (complete, skin pack, sound pack, mixed)',
    'Built bulk upload interface',
    'Auto-extracted metadata',
    'Supported base Q3 files extracting pak0.pk3 & pak2.pk3',
    'Added model type badges in UI',
    'Updated production deployment',
    'Added base_model column',
    'Added model_type column',
    'Created SetupBaseModels.php command',
    'Added ScrapeQ3dfModels.php placeholder',
    'Added MODELS_DEPLOYMENT.md',
    'Updated PRODUCTION_DEPLOYMENT.template.md',
    'Updated deploy.py symlinking public/baseq3',
    'Updated .gitignore excluding pak files',
    'Replaced static image thumbnails with live 3D ModelViewer',
    'Displayed animated idle poses in thumbnails',
    'Added thumbnailMode prop to ModelViewer',
    'Removed debug console.log',
    'Configured separate camera positions for thumbnail vs detail',
    'Removed grid floor from 3D viewer',
    'Zoomed and centered models properly',
    'Added Q3ShaderParser',
    'Added Q3ShaderMaterial system',
    'Integrated shader loading/application in MD3Loader',
    'Fixed default cull mode to null',
    'Fixed additive shader rendering',
    'Supported shader features',
    'Fixed head see-through issue',
    'Fixed skateboard alpha channel',
    'Enabled Q3 shader effects',
    'Implemented Q3-style animation-to-sound events',
    'Mapped LEGS_JUMP/LEGS_JUMPB to jump1',
    'Mapped TORSO_GESTURE to taunt',
    'Mapped BOTH_DEATH1/2/3 to death sounds',
    'Used event-based triggering on animation change',
    'Tracked last played animation per category',
    'Respected sound enabled/disabled state',
    'Added console logging for Q3 events',
    'Auto-triggered jump sound',
    'Auto-triggered taunt sound',
    'Triggered mapped sounds on all button clicks',
    'Kept manual sound test buttons',
    'Applied rotation.x = -π/2',
    'Applied rotation.z = -π/2',
    'Fixed model centering at camera lookAt',
    'Updated matrix after rotation/scale',
    'Added debug logging for visual center',
    'Changed animation buttons to click-to-play',
    'Made animations loop endlessly on click',
    'Updated help text for click behavior',
    'Applied to LEGS, TORSO, BOTH categories',
    'Started idle animations on page load',
    'Rotated model x = -Math.PI/2',
    'Ensured continuous animation from load',
    'Implemented Q3 idle system (LEGS_IDLE + TORSO_STAND)',
    'Researched bg_pmove.c logic',
    'Always played animations',
    'Defaulted to LEGS_IDLE/LEGS_IDLECR + TORSO_STAND',
    'Switched to movement/action on buttons',
    'Returned to idle on release',
    'Updated UI text explaining continuous system',
    'Implemented Q3 frame offset logic from cg_players.c',
    'Calculated offset: skip = -63',
    'Applied -63 offset to LEGS animations',
    'Added playBothAnimation()',
    'Implemented hold-to-animate',
    'Added raw animation test buttons',
    'Updated animation manager checking both fallback',
    'Added debug logging for animation parsing',
    'Added MD3 support with vertex animation',
    'Implemented MD3Loader for multi-part players',
    'Created MD3AnimationManager',
    'Added animation.cfg parser',
    'Implemented tag-based attachments',
    'Fixed hierarchy upper follows legs via tag_torso',
    'Added per-frame tag updates',
    'Created BaseQuake3ModelsSeeder for 23 base Q3 models',
    'Added migration making zip_path nullable',
    'Updated Show.vue with combined animation controls',
    'Added animation state badges',
    'Loaded models from public/models/basequake3/players/',
    'Added /public/models/ to .gitignore',
    'Implemented full MD3 viewer with Three.js',
    'Parsed MD3 binary',
    'Loaded TGA textures',
    'Parsed skin files',
    'Assembled player models via tags',
    'Added interactive camera',
    'Parsed animation.cfg',
    'Frame-by-frame vertex animation',
    'Independent leg/torso animations',
    'Looping animation support',
    'UI controls for Walk, Run, Jump, Crouch, Gesture, Idle',
    'Web Audio API for WAV playback',
    '13 character sounds',
    'Auto sound-to-animation mapping/playback',
    'Volume slider and enable/disable toggle',
    'Individual sound test buttons',
    'PK3 upload support',
    'Model CRUD with categories',
    'Public browsing/download',
    'Created ModelsController.php',
    'Created PlayerModel.php',
    'Created ModelViewer.vue',
    'Created MD3Loader.js',
    'Created MD3AnimationManager.js',
    'Created MD3SoundManager.js',
    'Added Models pages: Index, Create, Show',
    'Added migration for player_models table',
    'Fixed dropdown backdrop click-away',
    'Added dynamic position calculation for teleported dropdowns',
    'Updated map hover indicators on Servers',
    'Removed unused imports',
    'Improved visibility of dates, ranks, times on MapView',
    'Added unclaimed profile tooltips',
    'Made players list always expanded default on Servers',
    'Updated server count badges',
    'Removed expand logic from map detail features',
    'Added glass-like effect to VQ3/CPM leaderboard tables',
    'Improved visibility of dates, ranks, "Your Best"',
    'Disabled MapRecordSmall',
    'Adjusted hover classes position in Servers.vue',
    'Synced player list animation timing',
    'Displayed player\'s personal best time on each server card',
    'Showed rank position with player times',
    'Added map features hover-to-expand',
    'Implemented player list hover-to-expand',
    'Added helper functions for weapon/item/function icons/names',
    'Subtler hover indicator animations',
    'Reduced glow effects/ring opacity',
    'Better text shadows for readability',
    'Improved gradient fades',
    'Enhanced glassmorphism',
    'Added myrank_position and myrank_total to server data',
    'Improved rank calculation/retrieval',
    'Added debug logging for rank data',
    'Added manual gametype classification for servers',
    'Implemented server type field',
    'Enhanced server scraping preserving gametype',
    'Improved DefragServer class',
    'Updated MapFilters component',
    'Enhanced OnlinePlayer component',
    'Redesigned servers page with modern card layout',
    'Added dual-layout system with cookie persistence',
    'Implemented filtering',
    'Added sorting by popularity/alphabetically',
    'Redesigned compact list with VQ3/CPM split columns',
    'Fixed black Q3 text visibility with white outline globally',
    'Improved filter controls',
    'Standardized page header sizes',
    'Updated Maps page title',
    'Added hover-to-expand player list with animation',
    'Fixed background image positioning',
    'Reduced header/filter spacing',
    'Redesigned PlayerSelect component',
    'Visual redesign of PlayerSelect',
    'Added search icon in PlayerSelect input',
    'Improved dropdown with backdrop blur/spacing',
    'Selected items show blue background/checkmark',
    'Enhanced hover states/transitions',
    'Added smooth expand/collapse animation to Maps filter panel',
    'Filter button icon rotates 180°',
    'Transition max-height with overflow-hidden',
    'Fixed missing ref import from Vue',
    'Replaced Vuetify sliders with custom HTML5 inputs',
    'Implemented exponential scaling (power 2.5)',
    'Most values in first 80% travel',
    'Modern slider styling with blue gradient thumbs',
    'Hover effects scale and glow shadows',
    'Reset button clears filters without closing',
    'Removed selected items display',
    'Visual redesign of MapFilters',
    'Rewrote ItemsSelect from dropdown to visual grid',
    'Single-click cycling: neutral → include → exclude',
    'Grid auto-fill 48px tiles',
    'State indicators: checkmark/X icons',
    'Header with counts/bulk buttons',
    'Hover scale color transitions',
    'No dropdown/search - direct visual',
    'Constant grid size preventing shift',
    'Redesigned MapCard with modern layout/aspect ratios',
    'Repositioned physics badge top-right',
    'Moved items/weapons/functions overlay bottom-right',
    'Compact 3x3 icon displays',
    'Improved copy/download button styles',
    'Better typography hierarchy',
    'Removed unused ref import in MapCard',
    'Increased pagination from 21 to 30 maps per page',
    'Updated index() and filters() methods',
    'Auto-generated Ziggy routes updates',
    'Exponential slider scaling',
    'CSS transitions with easing',
    'Modern color palette semi-transparent',
    'Consistent styling across selectors',
    'Keyboard support',
    'Updated controllers with OAuth, category ranking, validation',
    'Updated models with OAuth tokens, social, relationships/scopes',
    'Updated EventServiceProvider',
    'Updated Console Kernel',
    'Updated composer.json/lock',
    'Updated config/services.php',
    'Updated deploy.py',
    'Updated components modernizing UI/UX',
    'Updated layouts improving navigation/breakpoints',
    'Updated pages with OAuth, category filtering',
    'Deleted Changelog.vue',
    'Updated routes/api.php',
    'Updated routes/web.php',
    'Updated tailwind.config.js',
    'Updated public/images/svg/icons.svg',
    'Added ProfileProgressBar component',
    'Added Rust rating service (400x faster)',
    'Added build-rust.sh',
    'Created CalculateRatingsRust command',
    'Created CalculateRatingsFast',
    'Created UpdateRatingsActivity',
    'Added OAuthController',
    'Added TwitchService',
    'Added CheckTwitchLiveStatus command',
    'Added migrations for OAuth',
    'Added indexes to player_ratings',
    'Added category column to player_ratings',
    'Changed clan featured_stat to array',
    'Updated .gitignore',
    'Redesigned filter system replacing dropdowns',
    'Separated ranking type filters',
    'Added category filters with icons/colors',
    'Added LG category filter',
    'Reorganized category order',
    'Grouped gametypes into Run/CTF blocks',
    'Updated gametype labels',
    'Responsive filter layout',
    'Glassmorphism design',
    'Modernized page header',
    'Moved player counts to table headers',
    'Fixed "My VQ3/CPM Rating" heights',
    'Consistent height for rated/unrated users',
    'Color-coded borders',
    'Redesigned Rating component to list-style',
    'Added hover effects',
    'Medal emojis for top 3',
    'Improved mobile responsiveness',
    'Visual hierarchy',
    'Drop shadows for text readability',
    'Removed redundant data',
    'Added category param to ranking queries',
    'Supported weapon/function rankings',
    'Removed Dropdown dependency',
    'Explicit Tailwind bindings',
    'Improved state management',
    'Better semantic HTML',
    'Consistent Tailwind spacing',
    'Added Map Completionist section',
    'Created getUnplayedMaps()',
    'Displayed 10 maps/page with pagination',
    'Changed map names color to neutral gray',
    'Added Quake icons for weapons/items/functions',
    'Displayed all features with icons',
    'Consistent header styling from records',
    'Compact VQ3/CPM headers',
    'Changed map names from blue-400 to gray-300',
    'Updated Record component',
    'Improved date format dd/mm/yy',
    'Enhanced background blur',
    'Fixed deleted_at reference',
    'Added icon mappings',
    'Fixed fallback icons',
    'Completely redesigned responsive header',
    'Progressive menu hiding right-to-left',
    'Collapsed items into "More" dropdown',
    'Removed JS screen width detection',
    'Logo always visible left',
    'Search/notifications/profile always visible',
    'Implemented Teleport for dropdowns',
    'Fixed click-away closing outside',
    'Fixed interaction issues no overlay blocking',
    'Single Teleport wrapper',
    'Modern glassmorphism styling',
    'Redesigned search results with tabs',
    'Color-coded active states',
    'Simplified MapSearchItem/PlayerSearchItem',
    'Removed redundant borders',
    'Better hierarchy text sizing/colors',
    'Applied modern styling to notification dropdowns',
    'Fixed Teleport interaction issues',
    'Improved notification item layout',
    'Updated icon sizes/colors consistency',
    'Added transitions/hover effects to notifications',
    'Made clan boxes darker',
    'Updated Hall of Fame/leaderboards/member/rival cards',
    'Improved text visibility',
    'Updated hovers',
    'Darker stats bar/member bubble colors',
    'Avatar always visible, username/arrow hidden on small screens',
    'Rounded corners updates',
    'Teleport fix for profile dropdown',
    'Removed screenWidth reactive/resize listeners',
    'Removed onSearchBlur',
    'Removed duplicate mobile search bar',
    'Clean Tailwind responsive no arbitrary',
    'Fixed z-index Teleport to body',
    'Consistent overlay pattern',
    'Fixed clan statistics excluding soft-deleted members',
    'Fixed member name tooltips overflow',
    'Replaced missing jumppad icon',
    'Reorganized Hall of Fame weapon masters',
    'Added default text for empty member notes',
    'Improved roster box expansion',
    'Fixed clan name effects alignment',
    'Added Q3 color code support to clan tags',
    'Removed brackets from clan tag display',
    'Added "Save Changes" button top of Edit Clan form',
    'Implemented file limits',
    'Added profile photo upload section',
    'Added ShareInertiaData middleware',
    'Added GIF support notes',
    'Profile background uses background-size',
    'Added profile_background_path to users table',
    'Implemented background upload with vue-advanced-cropper',
    'Added SettingsController methods',
    'Displayed background on profile',
    'Background attached to menu',
    'Added avatar_effect, name_effect, color, avatar_border_color',
    'Implemented CSS effects system',
    'Added effect selection',
    'Added color picker',
    'Added avatar border color selection',
    'Added avatar_effect, name_effect, avatar_effect_color to clans',
    'Added featured_stat to clans',
    'Implemented clan featured stat display',
    'Moved WR/Top 3 badges right',
    'Added avatar effect rendering',
    'Redesigned profile header compact',
    'Added floating transparent elements',
    'Compact stats grid',
    'Sidebar navigation tabs',
    'Records table redesign',
    'Removed player info from records',
    'Epic hover effects',
    'Added background upload section',
    'Modernized settings UI',
    'Added avatar/name effect selectors',
    'Added color pickers',
    'Installed vue-advanced-cropper',
    'Added effects.css',
    'Updated rebuild-frontend.sh',
    'Refactored Profile.vue',
    'Added ClanStatisticsController',
    'Added ClanAchievements component',
    'Added filterable Untouchable WR section',
    'Added tooltips to stats boxes',
    'Compacted statistics sections',
    'Added UpdatePlayerRankingsCache command',
    'Fixed WR count to rank #1 only',
    'Added rankings:cache task',
    'Added cached_wr_count/cached_top3_count',
    'Added 10 animated clan name effects',
    'Added effect color picker',
    'Added effects to clan cards',
    'Added CSS keyframes in app.css',
    'Added clan sorting',
    'Added WR/top3 badges',
    'Added clan tag field',
    'Displayed clan name effects',
    'Kept edit modal open after save',
    'Compacted Featured Stats Cards',
    'Compacted Hall of Fame boxes',
    'Compacted Additional Stats Grid',
    'Icons and spacing improvements',
    'Migrations for indexes, tag/name_effect/effect_color',
    'Clan background upload with image cropper',
    'Changed "Member Notes" to "Member Details"',
    'Member config file upload/download/delete',
    'MemberNoteEditor with rich text',
    'Modernized pagination',
    'Enhanced player select dropdowns',
    'Migrations for backgrounds, member notes, configs',
    'Migration reminder in rebuild-frontend.sh',
    'Added 7z compression demos',
    'p7zip-full in Docker',
    'DEMO_COMPRESSION_FORMAT config',
    'Fixed directory permissions',
    'Updated DemoProcessorService multiple formats',
    'Backblaze B2 S3 storage integration',
    'league/flysystem-aws-s3-v3 package',
    'B2 configuration .env',
    'PRODUCTION_DEPLOYMENT.template.md docs',
    'PRODUCTION_STORAGE.md guide',
    '.gitignore protect credentials',
    'Typesense search',
    'Laravel Scout Searchable trait',
    'Configured Typesense schema',
    'scout:import commands',
    'Fixed SCOUT_QUEUE worker',
    'Composite database indexes records table',
    'Optimized ProfileController::recentlyBeaten (60s→0.15s)',
    'Removed correlated subqueries',
    'Fixed query indexed columns',
    'Fixed worker container',
    'Switched queue database→Redis',
    'Redis service docker-compose.yml',
    'deploy.py run migrations/scout imports',
    'Updated LOCAL_SETUP.md/README.md',
    'Complete rewrite ultra-compact Records page',
    'Map thumbnail as background',
    'Fades on hover',
    'Horizontal layout',
    'Background only',
    'Top 3 medals',
    'Physics VQ3/CPM icons',
    'Removed Mode badges',
    'Smaller elements',
    'Background to edges',
    'Smooth transitions',
    'Gamemode filtering',
    'Redesigned header',
    'Physics filter inline buttons',
    'Container styling',
    'Cleaner pagination',
    'Map backgrounds create context',
    'Subtle borders',
    'Hover reveals full image',
    'Text/icons color shifts',
    'First/last rounded corners',
    'Compact less vertical space',
    'Hero section full-width background MapView',
    'Blur effect fade gradient',
    'Compact modern record cards',
    'Emerald highlight user records',
    'Amber/gold old top records',
    'Sort button clear labels',
    'Fixed sort toggle',
    'Fixed horizontal scrollbar',
    'Fixed rank calculation per gametype',
    'Glassmorphism leaderboard containers',
    'Removed debug console.log',
    'rebuild-frontend.sh asset building',
    'Smart gametype tabs auto-default',
    'Hide empty tabs',
    'Record count on tabs',
    'Backend auto-detect gametype',
    'Calculate/pass gametype stats',
    'v-show directive hide 0 records',
    'Active tab blue background',
    'Simplified sort button',
    'CTF maps default CTF1',
    'Immediately see available gametypes',
    'No dropdown clicking',
    'All features with icons displayed MapView',
    'Custom VQ3/CPM SVG icons',
    'Custom haste.svg',
    'Active server display player counts',
    'Map download button improved',
    'Mobile physics toggle',
    'Map author clickable',
    'Fixed rank calculation preserve time-based',
    'Header consistency min-width',
    'Hide gametype buttons only one type',
    'MapRecordSmall match desktop',
    'Remove star badge personal records',
    'Top 3 medals all users',
    'Link component author clickability',
    'CPM purple color scheme',
];

// Version 3: By 55 commits (complete list from all_commits_full.txt)
const commitItems = [
    { hash: '843ebd3', title: 'Add comprehensive donations system with admin management and public page', description: 'Complete donation tracking with models, migrations, approval workflow, currency conversion (EUR/USD/CZK), admin panel (DonationResource, DonationGoalResource, SelfRaisedMoneyResource), public /donations page with progress bars, global footer widget, notification enhancements' },
    { hash: 'd066c39', title: 'Add comprehensive offline records system, 3D map viewer, and demo processing enhancements', description: 'OfflineRecord model for df/fs/fc, offline_records table, automatic rank calculation, enhanced DemoProcessorService, Three.js BSP map renderer, WASD navigation, frontend component updates' },
    { hash: '097befb', title: 'Reorganize header navigation and improve UI layout across all pages', description: 'Two-row header layout, all nav links visible on md+, improved mobile dropdown, shadow gradient fades on all pages, consistent backdrop-blur-xl bg-black/60, maplist filtering/sorting' },
    { hash: '3d3e082', title: 'Add comprehensive tagging system and maplist enhancements', description: 'Tag model with categories (Difficulty, Movement, Weapons, Items, Environment, Functions), TagController CRUD, tag filtering, tag adoption from public maplists, public/private visibility, Vue Teleport for dropdowns' },
    { hash: '040b6c9', title: 'Major improvements: case-insensitive file serving, shader fallbacks, and cleanup', description: 'FileController for case-insensitive serving, eliminated 404 spam, enhanced ScrapeQ3dfModels, shader texture fallback to baseq3, fixed duplicate model rendering, removed Wiki integration' },
    { hash: '49b1c1e', title: 'Fix weapon mute button and allow users to download their own uploads', description: 'Fixed weapon sound muting in ModelViewer, setSoundsEnabled now handles weapon sounds properly, allow users to download their own uploads regardless of approval status' },
    { hash: 'cc8159a', title: 'Fix myUploads boolean type and clear approval_status on public view', description: 'Convert my_uploads to proper boolean using filter_var, clear approval_status when not in "My Uploads" mode to prevent confusing URL states' },
    { hash: 'a074f68', title: 'Allow texture/shader-only weapon uploads without MD3 files', description: 'Removed MD3 file requirement for weapons, made main_file optional (NULL), texture/shader packs can now use fallback to base Q3 weapon models' },
    { hash: '3d97693', title: 'Implement complete weapon system with sounds, projectiles, and fix railgun fire sound bug', description: 'Complete weapon system: sound loading/playback for all Q3 weapons, weapon-specific configs, sound cleanup, fire() and fireWithHit() methods, weapon-specific fire rates, keyboard/mouse controls, continuous/single-shot firing, recoil animation, projectile system (plasma/rocket/railgun/lightning/grenade/BFG/machinegun/shotgun/grapple), shader system, fixed railgun sound bug (reordered isRailgun before isLightningGun check), model approval system (enum approval_status), UI improvements' },
    { hash: 'f52d065', title: 'Remove Wiki integration', description: 'Cleanup: removed WikiController.php, WIKI_SETUP.md, wiki proxy routes, wiki/extensions/.gitkeep' },
    { hash: '71fe035', title: 'Implement composite weapon model loading with all parts', description: 'ImportBaseWeapons command, main_file column for non-standard filenames, item category enum, loadWeaponModel() in MD3Loader for composite loading (barrel/hand/flash via TAGs), weapon-specific camera positioning, modelFilePath uses main_file, skip skin loading for weapons, shader loading from models.shader, embedded shader names support, texture fallback chain, tested with grapple/shells' },
    { hash: 'c5c8817', title: 'Add complete YouTube-style maplist feature with Play Later functionality', description: 'Comprehensive maplist system: database tables (maplists, maplist_maps pivot with ordering, maplist_likes, maplist_favorites), Maplist model with relationships, auto-create Play Later on registration, CreatePlayLaterMaplists for 660 users, MaplistController full REST API, privacy enforcement (Play Later hidden/private/owner-only/non-deletable), AddToMaplistModal, MaplistCard with 2x2 thumbnails, Maplists/Index browser, Maplists/Show with MapCard grid, server selection dropdown, play button copying connect command, profile integration, navigation links, routes, social features (like/favorite with counter caching), performance optimizations' },
    { hash: 'd9a9f0b', title: 'Add 3D model head icon and GIF thumbnail generation features', description: 'Single-click 64x64 PNG head icon generation with FOV-based camera, browser-based 300x300 GIF generation (36 frames, 360° rotation) using gif.js Web Workers, progress tracking, combined upload, head_icon/thumbnail_path/base_model_file_path columns, fixed mass assignment, saveHeadIcon() endpoint, updated saveThumbnail(), notifications, progress indicators, performance timing, "Go Back" button, cleanup' },
    { hash: '814f3d2', title: 'Add baseq3 texture fallback for shader materials and multi-model PK3 support', description: 'Extended loadTexture() with fallbackBaseUrl, shader textures auto-fallback to baseq3, enhanced loadTextureForMesh() with multi-extension fallback (TGA/JPG/JPEG/PNG), ModelsController detects/creates separate entries per model in PK3, scraper pagination (--pages, --start-page, --reverse), download history tracking, sort newest/oldest, model type badge styling, *.sql to .gitignore' },
    { hash: '0ef5614', title: 'Add dynamic lighting controls and improve model viewer UI layout', description: 'Dynamic lighting controls for 3D viewer (ambient/directional/back intensity/color/position), updateLightingFromScene() in Q3ShaderMaterial, exposed light control methods via ModelViewer, adjusted defaults (ambient 0.9, directional 0.65, back 0.2), reorganized layout (Description/Technical Details left, Animation/Sound/Light controls right), compact animation buttons (flexbox wrap, smaller size), compact sound buttons, page width max-w-7xl to max-w-8xl, collapsible light controls, 8 light control methods, getLightSettings(), real-time shader uniform updates' },
    { hash: 'd1cd4b7', title: 'Rewrite Q3 shader system with custom GLSL for multi-stage rendering', description: 'Major rewrite: single THREE.ShaderMaterial with custom GLSL replacing multiple-material approach, proper multi-stage blending in fragment shader (fixes flickering), up to 4 stages with blend functions (additive/alpha/multiply), tcGen environment mapping, tcMod scroll animations, rgbGen lightingDiffuse, static texture cache shared across surfaces, smart fallback (black additive, white opaque), fixed transparency detection (base stage only), texture format fallback (.tga→.TGA→.jpg→.png), automatic sarge sound fallback, eliminated 404 spam, success logging, models per page 24→12, fixed rendering issues (brandon glowing hat, mynx shiny surfaces, bones cyan hologram), significant performance improvement' },
    { hash: 'b06b834', title: 'Add Q3 models system with base model detection and bulk upload', description: 'Base model detection (sarge/klesk/etc.), model type classification (complete/skin/sound/mixed), bulk upload interface for admins, automatic metadata extraction, pak0.pk3 & pak2.pk3 extraction to public/baseq3/, base_model and model_type columns, BulkUpload.vue, SetupBaseModels command, ScrapeQ3dfModels placeholder, MODELS_DEPLOYMENT.md, updated PRODUCTION_DEPLOYMENT.template.md, deploy.py symlinks public/baseq3, .gitignore excludes pak files/extracted base files' },
    { hash: 'eebed8d', title: 'Add live 3D model thumbnails with idle animations', description: 'Replaced static images with live 3D ModelViewer in listing, animated idle poses (LEGS_IDLE, TORSO_STAND), thumbnailMode prop, removed debug console.log from MD3Loader/Q3ShaderMaterial, separate camera positions thumbnail vs detail, removed grid floor, zoomed/centered models' },
    { hash: '722c5c4', title: 'Add Q3 shader support for MD3 models', description: 'Q3ShaderParser for .shader files, Q3ShaderMaterial system for advanced rendering, integrated in MD3Loader, fixed default cull mode (removed back, use null), fixed additive shader rendering (prevent emissive gray on transparent), support: additive/subtractive/multiply blending, texture animations (stretch/rotate/scroll), RGB generation (identity/lightingDiffuse), environment mapping (chrome), multi-stage, fixed head see-through (DoubleSide default), fixed skateboard alpha (proper transparent cutout), .gitignore excludes *.pk3, /storage/models/, /public/test/' },
    { hash: '9a04ede', title: 'Implement Q3-style animation-to-sound event system', description: 'Researched ioq3 cg_event.c/cg_players.c, event-based sound triggering: LEGS_JUMP/LEGS_JUMPB→jump1 (EV_JUMP), TORSO_GESTURE→taunt (EV_TAUNT), BOTH_DEATH1/2/3→death1/2/3 (EV_DEATH), triggers only on animation change, tracks last played per category (legs/torso/both), respects sound enabled/disabled, console logging "🔊 Q3 Event: ANIM_NAME → sound.wav", jump/gesture buttons auto-trigger sounds, all LEGS/TORSO/BOTH buttons trigger mapped sounds, manual test buttons remain' },
    { hash: 'fe0138f', title: 'Fix MD3 model orientation and centering in 3D viewer', description: 'Applied rotation.x = -π/2 (Z-up to Y-up), rotation.z = -π/2 (face camera), fixed centering at camera lookAt (0, 30, 0), matrix updates after rotation/scale for accurate bounding box, debug logging for visual center, animation UI: buttons changed to click-to-play (loop endlessly, no hold), updated help text, applied to LEGS/TORSO/BOTH' },
    { hash: '7247d7f', title: 'Fix model orientation and start idle animations on page load', description: 'Model starts with idle animations (LEGS_IDLE + TORSO_STAND) on load, added updateAnimations() call in onAnimationsReady(), rotation.x = -Math.PI/2 for upright, continuous animation from load matching Q3, Q3 coordinate system (+X forward, +Z up, +Y left) to Three.js (+X right, +Y up, +Z backward)' },
    { hash: '5dac65c', title: 'Implement Q3 idle animation system (LEGS_IDLE + TORSO_STAND)', description: 'Q3-style default idle animations play continuously when no input, researched bg_pmove.c:PM_Footsteps() and PM_TorsoAnimation(), animations ALWAYS play (animMgr.playing = true, never stop), no buttons: LEGS_IDLE (or LEGS_IDLECR if crouch) + TORSO_STAND, buttons pressed: switches to movement/action, buttons released: returns to idle (not frozen), updated UI text explaining continuous system, Q3 loop: every frame calls PM_Footsteps()/PM_TorsoAnimation(), PM_Footsteps() checks movement (no movement + xyspeed < 5 → LEGS_IDLE/LEGS_IDLECR, moving + crouch → LEGS_WALKCR/LEGS_BACKCR, moving + run → LEGS_RUN/LEGS_BACK), PM_TorsoAnimation() checks weapon (ready → TORSO_STAND/TORSO_STAND2, attacking → TORSO_ATTACK, gesturing → TORSO_GESTURE with torsoTimer lock)' },
    { hash: '4a83056', title: 'Fix MD3 animation frame offsets to match Q3 engine behavior', description: 'Implemented Q3 cg_players.c:210-214 frame offset logic, animation.cfg defines frames for combined model but MD3s split differently (lower.md3/upper.md3), Q3-style calculation: skip = LEGS_WALKCR.firstFrame - TORSO_GESTURE.firstFrame, applied offset -63 to LEGS animations for lower.md3 local frames, added playBothAnimation() for deaths on both legs/torso simultaneously, hold-to-animate (mousedown/mouseup) all buttons, raw animation test buttons showing all with frame ranges, animation manager checks both category as fallback for deaths, comprehensive debug logging, frame mapping: animation.cfg BOTH(0-82)/TORSO(83-145)/LEGS(146-294), lower.md3 232 frames BOTH(0-82)/LEGS(83-231) offset -63, upper.md3 146 frames BOTH(0-82)/TORSO(83-145) no offset' },
    { hash: 'c45bf90', title: 'Add Quake 3 MD3 player model viewer with animations', description: 'MD3 format support with vertex animation, MD3Loader for multi-part (head/upper/lower), MD3AnimationManager for independent legs/torso, animation.cfg parser, tag-based attachment, fixed hierarchy upper follows legs via tag_torso, per-frame tag position updates, BaseQuake3ModelsSeeder for 23 base Q3 models, migration zip_path nullable for base models, Show.vue combined animation controls (legs + torso), animation state badges, improved UI layout, models load from public/models/basequake3/players/, /public/models/ to .gitignore, known issue: some models have mismatched animation.cfg and MD3 frame layouts' },
    { hash: '79f551a', title: 'Add complete Quake 3 MD3 model viewer with animations and sound', description: 'Full-featured viewer: 3D visualization with Three.js, MD3 binary parser (frames/tags/surfaces), TGA texture loading with UV mapping, skin file parsing for texture-to-surface, full player assembly (head+upper+lower) via tags, interactive camera (rotate/pan/zoom), animation system: animation.cfg parser, frame-by-frame vertex animation with timing, independent leg/torso animations, looping, UI controls (Walk/Run/Jump/Crouch/Gesture/Idle), sound system: Web Audio API for WAV, 13 character sounds (jump/taunt/death/pain/fall/etc.), automatic sound-to-animation mapping/playback, volume slider and enable/disable toggle, individual test buttons, backend: PK3 upload support, automatic extraction/organization, model CRUD with categories, public browsing/download, files added: ModelsController.php, PlayerModel.php, ModelViewer.vue, MD3Loader.js, MD3AnimationManager.js, MD3SoundManager.js, Models pages (Index/Create/Show), migration for player_models table' },
    { hash: '6f171ec', title: 'Fix dropdown backdrop click-away functionality and improve UI elements', description: 'Fixed all dropdowns (search/notifications/profile) close when clicking outside by teleporting backdrop and dropdowns to body with proper z-index stacking, dynamic position calculation for teleported dropdowns on resize/scroll, map hover indicators Servers page only on map box hover, removed unused imports (Popper/copyState) Servers.vue, improved visibility dates/ranks/times MapView with drop shadows/better colors, unclaimed profile tooltips (different styles logged-in vs non) Rating/MapRecord/Record, players list Servers always expanded default, removed redundant title, server count badges glassmorphism' },
    { hash: '8b8598a', title: 'Improve map detail page styling and leaderboard visibility', description: 'Removed expand logic map detail features (weapons/items/functions always visible), glass-like effect VQ3/CPM leaderboard tables matching Servers, improved visibility dates/ranks/"Your Best" in leaderboards, disabled MapRecordSmall (use MapRecord all sizes, backed up as _obsolete), adjusted hover classes position Servers.vue better expand, synced player list animation timing with map features' },
    { hash: '0d373bc', title: 'Improve servers page with player time display and less obtrusive hover indicators', description: 'Added: player\'s personal best time on each server card, rank position display "Rank 5/150" alongside times, map features hover-to-expand (weapons/items/functions), player list hover-to-expand with "show more" indicators, helper functions weapon/item/function icons/names. Visual: subtler hover indicator animations (2.5s cycle, 2px movement), reduced glow effects/ring opacity less distraction, better text shadows readability over dynamic backgrounds, improved gradient fades thumbnails/player lists, enhanced glassmorphism buttons/containers. Backend: myrank_position and myrank_total to server data, improved rank calculation/retrieval, debug logging rank data' },
    { hash: '564980a', title: 'Add server gametype filtering and improve server data handling', description: 'Manual gametype classification for servers (run/ctf/freestyle/teamrun), implemented server type field in database/admin panel, enhanced scraping preserve gametype, improved DefragServer class handle server type info, updated MapFilters component better filter handling, enhanced OnlinePlayer component styling' },
    { hash: 'aab3e0d', title: 'Improve servers page UI and fix black text visibility', description: 'Redesigned with modern card layout and compact list view, dual-layout system (large cards / compact list) with cookie persistence, comprehensive filtering (gametype/physics/hide empty), sorting by popularity/alphabetically with toggle, redesigned compact list with VQ3/CPM split columns, fixed black Q3 text (^0 color code) visibility with white outline globally, improved filter controls inline labels/optimized spacing, standardized page header sizes Maps/Servers, updated Maps title match site typography, hover-to-expand player list smooth animation, fixed background image positioning with fade gradient, reduced header/filter spacing cleaner layout' },
    { hash: '4433df1', title: 'Redesign map filters with modern UI and improved UX', description: 'PlayerSelect: show dropdown only when search.length > 0, complete visual redesign glassmorphism, search icon in input, improved dropdown backdrop blur/spacing, selected items blue background/checkmark, enhanced hover states/transitions. Maps filter animation: smooth expand/collapse, button icon rotates 180°, max-height transition 500ms enter/300ms leave, overflow-hidden prevents shifts. MapFilters: CRITICAL FIX added missing ref import from Vue, replaced Vuetify sliders with custom HTML5, exponential scaling (power 2.5) most values first 80% travel fine control last 20%, modern slider styling blue gradient thumbs, hover scale 1.05-1.15 glow shadows, Reset clears all without closing using local resetFilters(), removed selected items display prevent shift, complete visual redesign glassmorphism. ItemsSelect: complete rewrite dropdown→visual grid, single-click cycling neutral→include green→exclude red→neutral, grid auto-fill 48px tiles with sprites, visual state indicators (checkmark green/X red/neutral gray), header with counts and bulk buttons (Clear/Include All green/Exclude All red), hover scale 1.05 color transitions, no dropdown/search direct interaction, constant grid no shift. MapCard: complete visual redesign modern layout, cleaner spacing/aspect ratios, physics badge top-right, items/weapons/functions overlay bottom-right backdrop blur, smaller compact 3x3 icons instead of 4x4, improved copy/download button styles, better typography hierarchy, removed unused ref. MapsController: pagination 21→30 maps per page both index()/filters(). Technical: exponential slider scaling, CSS transitions proper easing, modern color palette semi-transparent, consistent component styling, better keyboard support (Enter submit search)' },
    { hash: '2a695ee', title: 'Update backend controllers, models, and frontend components', description: 'Backend: Updated Clans/Profile/Ranking/Records/Web controllers (OAuth integration, category-based ranking, improved data handling/validation), updated User/Clan/Record models (OAuth token fields, social media integration, relationships/scopes), updated EventServiceProvider (new event listeners), updated Console Kernel (scheduled ratings/Twitch status commands). Config & Dependencies: composer.json/lock (OAuth/Twitch packages), config/services.php (Twitch/Steam/Twitter credentials), deploy.py (improved deployment). Frontend: Updated components Pagination/Bundle/Dropdown/Forms/Navigation/Maps/Notifications/Players/Rounds/Tabs (modernized UI/UX, responsive, new interactive features), updated layouts HomeLayout/MainLayout (improved navigation, better responsive breakpoints), updated pages Auth/Announcements/Bundles/Clans/Demos/Home/Maps/Notifications/Profile/Records/Servers/Tournaments (OAuth login, category filtering, profile/tournament improvements), deleted Changelog.vue. Routes & Config: updated routes/api.php (OAuth endpoints), routes/web.php (categories/OAuth routes), tailwind.config.js (new colors/utilities). Assets: updated public/images/svg/icons.svg (new weapon/function icons)' },
    { hash: '1faa899', title: 'Add ProfileProgressBar component', description: 'Component for displaying progress bars on profile pages' },
    { hash: '7258242', title: 'Add Rust rating optimization, OAuth integration, and database migrations', description: 'Rust Performance: Rust rating calculation service in rust-rating-service/ (massive improvement over PHP, handles complex ELO-style efficiently, source code only build artifacts excluded), build-rust.sh compile script, console commands: CalculateRatingsRust (use Rust service), CalculateRatingsFast (fast calculation), UpdateRatingsActivity (update player activity). OAuth & Social: OAuthController for third-party auth, TwitchService for API integration, CheckTwitchLiveStatus command, migrations: OAuth tokens/Steam/Twitter support to users table. Database Schema: indexes to player_ratings for performance, category column player_ratings (weapon/function-specific rankings), clan featured_stat to array (multiple statistics). Gitignore: excluded unused icon libraries (5000+ SVGs), excluded Rust build artifacts (target/ - build on server)' },
    { hash: '8228b7b', title: 'Redesign ranking page with modern UI, weapon icons, and improved UX', description: 'Major UI Overhaul - Filter System: replaced dropdowns with modern inline button groups, separated ranking type filters (Active/All Players) dedicated block larger buttons, added weapon/function category filters with Quake icons/colors (All orange, Strafe sky blue, Slick yellow, Tele cyan, RL red, PG purple, GL green, LG white, BFG blue), added LG category filter, reorganized category order All→Strafe→Slick→Tele→RL→PG→GL→LG→BFG, grouped gametypes Run/CTF blocks with visual separation, updated gametype labels "CTF" prefix for CTF 1-7, responsive filter layout (mobile vertical stack horizontal Active/All, tablet vertical stack vertical Active/All, desktop horizontal filters right). Visual: glassmorphism backdrop blur/gradient overlays/subtle borders, modernized page header gradient/typography, moved player counts from below title to table headers "VQ3 Rankings (X)", fixed "My VQ3/CPM Rating" section heights min-height 60px flex center (consistent whether user has rating or not, handles active_players and all_players types), color-coded borders matching physics (blue VQ3/purple CPM). Rating Component: replaced old card with modern list-style interactive rows, hover effects with blurred profile photo background sharpens on hover, medal emojis top 3 (🥇🥈🥉), improved mobile responsive adaptive fonts/spacing, visual hierarchy physics icons/compact dd/mm/yy dates, drop shadows text readability over dynamic backgrounds, removed redundant data (physics/mode pills) cleaner icon-based. Backend: added category param to ranking queries, support weapon/function-specific rankings (overall/strafe/slick/tele/rocket/plasma/grenade/lg/bfg), removed Dropdown component dependency RankingView. Technical: explicit Tailwind class bindings category colors (JIT-compatible), improved state management proper watchEffect route params, better semantic HTML flex layouts/responsive breakpoints, consistent spacing Tailwind utilities' },
    { hash: '952b38b', title: 'Add map features display and UI improvements', description: 'Profile Features: Map Completionist section showing unplayed maps with pagination, backend getUnplayedMaps() in ProfileController, frontend display 10 maps/page thumbnails/names/authors, improved pagination Prev/Next buttons ellipsis for large page counts, changed map names color from physics-specific (blue/purple) to neutral gray. Map Detail Page: Quake icons for weapons/items/functions, display all map features with proper icons (weapons: gauntlet/mg/sg/gl/rl/lg/rg/pg/bfg/grapple, items: powerups enviro/haste/quad/regen/invis/flight, health pickups health/smallhealth/bighealth/mega, armor shard/ya/ra, functions: tele/slick/timer/fog/water/lava/moving/door/button/etc.), adopted consistent header styling from records page VQ3/CPM tables, updated VQ3/CPM headers text-lg/colored titles (blue-400/purple-400)/smaller icons. Records Page: changed map names from blue-400 to neutral gray-300 (white on hover), updated Record component consistent neutral map name styling, improved date format dd/mm/yy readability, enhanced background blur effects cards. Bug Fixes: fixed deleted_at column reference maps table query, added proper icon mappings all Quake weapons/items/functions, fixed fallback icons appropriate defaults' },
    { hash: '7ae35c7', title: 'Redesign header navigation with modern responsive behavior and visual improvements', description: 'Header Navigation: completely redesigned responsive header clean Tailwind breakpoints, progressive menu hiding right-to-left (Tournaments→Clans→Bundles→Demos→Records→Ranking→Maps→Servers→News), menu items collapse into "More" dropdown using standard breakpoints (2XL/XL/LG/MD/SM), removed old JS screen width detection logic, logo always visible left, search bar/notifications/profile avatar always visible all sizes. Dropdown Components: implemented Teleport pattern all dropdowns (search/notifications/profile/more menu), fixed click-away behavior close when clicking outside, fixed interaction issues users can click inside without overlay blocking, wrapped overlay and dropdown in single Teleport proper z-index stacking, updated styling modern glassmorphism (backdrop-blur-xl/bg-gray-900/95). Search Dropdown: redesigned search results popup with category tabs (All/Maps/Players), increased width 550px better spacing/layout, color-coded active states (blue/green/purple), simplified MapSearchItem/PlayerSearchItem components cleaner horizontal layouts, removed redundant borders/improved hover states, better visual hierarchy proper text sizing/colors. Notification Menus: applied modern styling both notification dropdowns (records/system), fixed interaction issues Teleport pattern, improved notification item layout better spacing, updated icon sizes/colors consistency, added proper transitions/hover effects. Clan Detail Page: made all boxes darker (bg-white/5→bg-black/20) match profile page design, updated Hall of Fame/leaderboards/member cards/rival cards backgrounds, improved text visibility (text-gray-500→text-gray-300), updated hover states (hover:bg-white/10→hover:bg-black/30), darker stats bar/member bubble colors. Profile Dropdown: avatar always visible all sizes, username/arrow hidden small screens (<MD), rounded corners updated (rounded-md→rounded-xl/rounded-lg), applied Teleport fix proper click-away. Technical: removed screenWidth reactive state/resize event listeners, removed onSearchBlur function (replaced Teleport overlay pattern), removed duplicate mobile search bar, used clean Tailwind responsive classes no arbitrary breakpoints, fixed z-index stacking context issues Teleport to body, all dropdowns consistent overlay pattern z-index 2000' },
    { hash: '6a8fa4e', title: 'Add comprehensive clan and profile improvements', description: 'Fixed clan statistics exclude soft-deleted members (correct WR/record counts), fixed member name tooltips overflow on clan cards, replaced missing jumppad icon with push.svg, reorganized Hall of Fame weapon masters (Plasma/Rocket/Grenade/BFG) same row, added default text empty member notes in roster, improved roster box expansion smooth max-height animation, fixed clan name effects alignment after tag addition, added Q3 color code support to clan tags, removed brackets from clan tag display, added "Save Changes" button top of Edit Clan form, implemented file size limits (1MB avatars/5MB backgrounds), added profile photo upload section, added ShareInertiaData middleware for Jetstream features, added GIF support notes and file size descriptions, profile background uses background-size proper 1920px scaling' },
    { hash: 'a7b7d06', title: 'Add comprehensive profile and clan customization features with epic redesign', description: 'Profile Background: profile_background_path field to users, background upload with vue-advanced-cropper (4.8 aspect 1920x400), SettingsController upload/deletion methods, displayed background on profile with edge fades, attached to menu max 400px height/1920px width. User Avatar & Name Effects: avatar_effect/name_effect/color/avatar_border_color fields to users, implemented CSS effects system (effects.css) animated avatars/names, avatar/name effect selection in profile preferences, color picker for effect customization, avatar border color selection. Clan Visual: avatar_effect/name_effect/avatar_effect_color to clans, featured_stat field (replaces featured_player_id), clan featured stat display (Total WRs/CPM WRs/VQ3 WRs/Top 3s), moved World Records/Top 3 badges right side clan cards, avatar effect rendering clan avatars. Profile Page Complete Redesign: redesigned header compact 280px horizontal layout, floating transparent elements glassmorphism, compact stats grid 8 cards (4 cols mobile/8 cols desktop) showing both CPM/VQ3 values, sidebar navigation tabs left sidebar (sticky 1/4 width), records table redesign inline Record component from RecordsView, removed player info from records table (profile context redundant), epic hover effects gradient glows/animations. Settings Page: added profile background upload section with cropper, modernized settings UI glassmorphism effects, avatar/name effect selectors, color pickers for effect customization. Technical: installed vue-advanced-cropper package, added effects.css with animation keyframes, updated rebuild-frontend.sh better cache clearing, refactored Profile.vue stats grid/sidebar tabs/inline records rendering' },
    { hash: 'cea6b5c', title: 'Add clan features: statistics, rankings cache, name effects, and UI improvements', description: 'Clan Statistics & Achievements: ClanStatisticsController comprehensive stats calculation, ClanAchievements component with leaderboards/hall of fame/special sections, filterable Untouchable World Records section (All/Solo/Tied tabs), tooltips to all stats boxes explaining metrics, compacted all sections/tables better space. Player Rankings Cache: UpdatePlayerRankingsCache command cache WR/top3 counts for players, fixed WR count calculation only count rank #1 positions (not tied records), rankings:cache scheduled task every 20 minutes, cached_wr_count/cached_top3_count columns to users. Clan Name Visual Effects: 10 animated clan name effects (particles/orbs/lines/matrix/glitch/wave/neon/rgb split/flicker/hologram), effect color picker hex color support, effects to clan list cards/detail page/edit modal preview, CSS keyframe animations all effects in app.css. Clan List Enhancements: sorting functionality clans (by name/members/world records/top 3), WR/top 3 count badges clan cards using cached statistics, clan tag field to clans table/management, display clan name effects on clan cards list. UI/UX Improvements: keep edit clan modal open after saving changes (preserve state), compact Featured Stats Cards horizontal layout, compact Hall of Fame boxes/fix layout distribution, compact Additional Stats Grid boxes (Top 10/Avg WR Age/Map Coverage/Physics), icons and improved spacing throughout statistics sections. Database Migrations: indexes to records table for performance, tag/name_effect/effect_color columns to clans, cached ranking columns to users' },
    { hash: '6bb2d63', title: 'Add clan management enhancements: backgrounds, member details, and config uploads', description: 'Added clan background image upload with image cropper, changed "Member Notes" to "Member Details" throughout UI, added member config file (.cfg) upload/download/delete functionality, added MemberNoteEditor component with rich text editor for member details, modernized pagination component with ultra-sleek design, enhanced player select dropdowns with modern styling, added migrations for clan backgrounds/member notes/config files, included migration reminder in rebuild-frontend.sh, fixed clan detail page to display member notes and config files' },
    { hash: 'd7f65e2', title: 'Redesign Records page with modern, sleek UI', description: 'Major overhaul Records page for cleaner modern aesthetic. Record.vue Complete Rewrite: removed old chunky card layout borders/shadows, added map thumbnail as background (always visible blurred), background fades in on hover with unblur effect (opacity 20%→60%, blur-md→blur-none), implemented ultra-compact horizontal layout Rank|Map Name|Player|Time|Physics|Date, removed separate map thumbnail from row now only background, top 3 ranks show medals (🥇🥈🥉) instead of colored numbers, replaced physics badges with clean VQ3/CPM icons, removed Mode badges (CTF/RUN) cleaner look, smaller compact elements (6x6 avatars/xs text/tighter gaps), background extends to edges with rounded corners matching container, smooth 500ms transitions all hover effects. RecordsView.vue: removed auto-update button functionality, added gamemode filtering (ALL/RUN/CTF) with color-coded buttons, redesigned header with gradient background and modern filter buttons, changed physics filter from dropdown to inline buttons with icons, updated container styling bg-black/40 border-white/5, cleaner pagination styling matching overall dark theme. Visual Improvements: map backgrounds create visual context each record, subtle borders (border-white/[0.02]) instead of chunky gray lines, hover reveals full map image behind row with darker→lighter overlay transition, all text/icons get subtle color shifts on hover, first/last rows have rounded corners match parent container, overall more compact less vertical space per record' },
    { hash: '6d81b27', title: 'Enhance MapView with custom icons, server integration, and mobile physics toggle', description: 'Replaced emoji icons with custom SVG icons for VQ3/CPM physics, added custom haste.svg icon for fastest sorting mode, implemented active server display with player counts and defrag:// protocol links, added map download button with improved visibility, created mobile physics toggle to switch between VQ3/CPM leaderboards on small screens, made map author clickable to filter maps by author, fixed rank calculation to preserve time-based ranks when sorting by date, improved header consistency with min-width and placeholder for missing records, hid gametype buttons when only one gametype has records, updated MapRecordSmall component to match desktop ultra-compact design, removed star badge for personal records outside top 3, ensured top 3 medals display for all users including personal records, added Link component import for author clickability, changed CPM color scheme to purple throughout UI' },
    { hash: '6dde59d', title: 'Redesign MapView record cards with ultra-compact modern layout', description: 'Made record cards much more compact and visually appealing, emphasized rank numbers and times with larger bolder fonts, added top 3 rank colors and medals (🥇🥈🥉), old top records show crown icon (👑) for ranks 1-3 only, player names and flags more visible with better contrast, dates shown in uniform MM/DD/YYYY format with monospace font, added smart hover effects: row highlights/rank scales-pops/badges animate, demo download and date info subtle by default visible on hover, made VQ3/CPM headers more compact, fixed Old Top toggle state persistence across page refreshes, added localStorage sync for toggle state, fixed ToggleButton to properly watch for prop changes' },
    { hash: 'cdbe405', title: 'Redesign MapView UI with modern hero layout and compact records', description: 'Added hero section with full-width map thumbnail background, implemented blur effect and fade gradient on map background, created compact modern record card design with improved spacing, added emerald highlight for user\'s personal records, added amber/gold highlight for old top records with visible styling, improved sort button with clear labels (⚡ Fastest / 📅 Newest), fixed sort functionality to properly toggle between time and date, fixed horizontal scrollbar bug from w-screen overflow, fixed rank calculation to show correct ranks per gametype (CTF1/CTF2/etc.), added glassmorphism effects to leaderboard containers, removed debug console.log statements from Footer, added rebuild-frontend.sh script for asset building with Octane reload' },
    { hash: 'aa8ff1d', title: 'Add automatic browserslist update to postinstall script', description: 'Automatically updates browser compatibility database after npm install, runs on both local development and production deployments, eliminates "browsers data is 6 months old" warning, fails gracefully with || true to not break builds' },
    { hash: '4b76106', title: 'Improve map details UI with smart gametype tabs', description: 'UI/UX Improvements: replaced hidden dropdown menus with visible horizontal tabs for gametypes, auto-detect and default to most populated gametype instead of always "run", hide empty gametype tabs (only show tabs with records), display record count on each gametype tab for better visibility. Backend Changes: MapsController auto-detect most populated gametype when not specified, calculate and pass gametype statistics to frontend, query optimization using SUBSTRING_INDEX to group by base gametype. Frontend Changes: replaced Dropdown components with horizontal tab buttons, added v-show directive to hide tabs with 0 records, improved visual hierarchy with active tab highlighting (blue background), simplified sort button (combined date/time toggle). User Experience Impact: CTF maps now default to CTF1 instead of empty RUN gametype, users immediately see which gametypes are available, no more clicking through dropdowns to find populated gametypes, cleaner more intuitive interface. Example: q3w4 map now shows only CTF tabs (ctf1:204, ctf2:40, etc.) instead of all 8 gametypes with most showing (0)' },
    { hash: '7cd8e80', title: 'Add 7z compression, Backblaze B2 storage, Typesense search, and major performance improvements', description: 'Demo Processing Enhancements: 7z compression support (10-20% better vs ZIP), p7zip-full in Docker container, DEMO_COMPRESSION_FORMAT config option (zip/7z), fixed directory permissions (0755) processed demos storage, updated DemoProcessorService multiple compression formats. Cloud Storage Integration: Backblaze B2 S3-compatible storage, league/flysystem-aws-s3-v3 package for S3 support, B2 configuration .env (credentials/endpoint/bucket), PRODUCTION_DEPLOYMENT.template.md docs, PRODUCTION_STORAGE.md storage options guide, .gitignore protect credential files. Search Infrastructure: Typesense search support Maps/MddProfile (players)/Demos, Laravel Scout Searchable trait Demo model, configured Typesense schema all searchable models scout.php, scout:import commands deploy.py for production auto-indexing, fixed SCOUT_QUEUE issue worker now processes all queues not just "demos". Performance Optimization (400x improvement): composite database indexes records table ((mapname/gametype/time) recently beaten queries, (mapname/gametype/mdd_id) tied ranks, (mdd_id/date_set) latest records, (mdd_id/rank) best/worst ranks), optimized ProfileController::recentlyBeaten query (60s→0.15s), removed correlated subqueries use pre-calculated rank column, fixed query use indexed columns with proper join conditions. Infrastructure & Deployment: fixed worker container configuration (remove /bin/sh wrapper, add SUPERVISOR_PHP_USER), switched queue driver database→Redis better performance, added Redis service docker-compose.yml, updated deploy.py run migrations/scout imports automatically, updated LOCAL_SETUP.md/README.md new features' },
    { hash: 'fb6faa3', title: 'fix(demos): fallback download + metadata guard', description: 'Fixed demos download fallback and metadata guard' },
    { hash: '54840c5', title: 'Add scrape:servers start/finish logging', description: 'Added logging for server scraping start and finish' },
    { hash: '87aacd0', title: 'Restore scrape:servers schedule; split online/offline cadence', description: 'Restored server scraping schedule and split online/offline cadence' },
    { hash: '2a3e336', title: 'Add local debug upload detection routes and form; improve demo upload archive detection', description: 'Added local debug upload detection routes and form, improved demo upload archive detection' },
    { hash: '1b1a362', title: 'Rename \'Latest Servers\' to \'Popular Servers\' on home page', description: 'Updated server section heading to "Popular Servers" to better reflect sorting by player count rather than recency' },
    { hash: '390b461', title: 'Fix WorldSpawn scraper: accept all maps + handle compressed responses', description: 'Changed shouldGetMap() to return true for all maps (not just defrag), added compression handling with CURLOPT_ENCODING, added proper headers and timeout for reliable scraping, tested: successfully scraped 550+ maps in minutes' }
];
</script>

<template>
    <Head title="Roadmap - Defrag Racing" />

    <div>
        <!-- Header -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white mb-4">Development Roadmap</h1>
                    <p class="text-xl text-gray-400">55 commits of progress - hover for details</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 pb-12" style="margin-top: -22rem;">

            <!-- Version Selector (Hidden - versions 2 & 3 moved to Ongoing) -->
            <!-- <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 mb-5">
                <div class="flex gap-3 justify-center items-center flex-wrap">
                    <button @click="activeVersion = 1" :class="activeVersion === 1 ? 'bg-green-600' : 'bg-gray-700'" class="px-8 py-3 rounded-lg text-white font-bold text-lg transition-colors hover:bg-green-500 shadow-lg">
                        Roadmap
                    </button>
                    <span class="text-gray-400 text-sm">or view as:</span>
                    <button @click="activeVersion = 2" :class="activeVersion === 2 ? 'bg-blue-600' : 'bg-gray-700'" class="px-4 py-1.5 rounded text-white text-xs transition-colors hover:bg-blue-500">
                        740 Points
                    </button>
                    <button @click="activeVersion = 3" :class="activeVersion === 3 ? 'bg-purple-600' : 'bg-gray-700'" class="px-4 py-1.5 rounded text-white text-xs transition-colors hover:bg-purple-500">
                        55 GitHub Commits
                    </button>
                </div>
            </div> -->

            <!-- VERSION 1: Future / Ongoing / Done -->
            <div v-show="activeVersion === 1" class="backdrop-blur-xl bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-8">
                <!-- FUTURE PLANNED -->
                <div class="mb-12">
                    <h3 class="text-xl font-bold text-purple-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🔮</span> Future Planned
                    </h3>
                    <div class="ml-8 text-sm text-gray-400 italic">
                        <p>Future features will be determined based on community feedback, priorities, and available development time</p>
                    </div>
                </div>

                <!-- ONGOING -->
                <div class="mb-12">
                    <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚡</span> Ongoing
                    </h3>
                    <div class="ml-8 space-y-6">
                        <!-- Beta 3D Map Preview -->
                        <div class="relative pl-6 border-l-2 border-yellow-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-yellow-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-yellow-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-yellow-300">Beta 3D Map Preview</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Built test-map-viewer.html with Three.js-based BSP map renderer</p>
                                    <p>• Implemented real-time 3D navigation with WASD controls and mouse look</p>
                                    <p>• Added texture extraction scripts (export_textures.py, parse_bsp_to_json.py)</p>
                                    <p>• Included textures for pado and pornstar-cpmrun maps with skybox support</p>
                                    <p>• Created TestMapViewerController for serving map viewer</p>
                                </div>
                            </div>
                        </div>

                        <!-- Demos Integration with Leaderboards -->
                        <div class="relative pl-6 border-l-2 border-yellow-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-yellow-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-yellow-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-yellow-300">Demos Integration with Leaderboards</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Created OfflineRecord model with ranking for practice/offline demos (df/fs/fc gametypes)</p>
                                    <p>• Added offline_records table with optimized composite indexes for leaderboard queries</p>
                                    <p>• Implemented automatic rank calculation and updates on new record creation</p>
                                    <p>• Added gametype field to uploaded_demos to distinguish online vs offline</p>
                                    <p>• Created ReassignDemosToRecords command for re-linking demos after record repopulation</p>
                                    <p>• Enhanced DemoProcessorService to differentiate offline vs online demos</p>
                                    <p>• Improved Python demo processor for better metadata extraction</p>
                                    <p>• Added 7z compression for demos (10-20% better than ZIP)</p>
                                    <p>• Enhanced MapRecord component with improved time display and demo download links</p>
                                </div>
                            </div>
                        </div>

                        <!-- Finish Connecting GitHub Repo with Roadmap -->
                        <div class="relative pl-6 border-l-2 border-yellow-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-yellow-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-yellow-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-yellow-300">Finish Connecting GitHub Repo with Roadmap</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Alternative view showing all 740 individual achievement points in flat tree structure</p>
                                    <p>• Alternative view organizing development by 55 GitHub commits with full descriptions</p>
                                    <p>• Interactive toggle between grouped roadmap view and detailed breakdowns</p>
                                    <p>• Hover tooltips showing commit details and implementation specifics</p>
                                    <p>• Full coverage of all development work from September-October 2025</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DONE -->
                <div>
                    <h3 class="text-xl font-bold text-green-400 mb-4 flex items-center gap-2">
                        <span class="text-2xl">✅</span> Done
                    </h3>
                    <div class="ml-8 space-y-6">

                        <!-- Ranking System Reworked -->
                        <div class="relative pl-6 border-l-2 border-green-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-green-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-green-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-green-300">Ranking System Reworked Drastically</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added Rust rating service (60s→0.15s, 400x faster ELO calculations)</p>
                                    <p>• Created CalculateRatingsRust, CalculateRatingsFast, UpdateRatingsActivity commands</p>
                                    <p>• Added indexes to player_ratings for performance</p>
                                    <p>• Added category column to player_ratings for weapon/function-specific rankings</p>
                                    <p>• Redesigned filter system replacing dropdowns with inline button groups</p>
                                    <p>• Separated ranking type filters (Active/All Players) with dedicated larger buttons</p>
                                    <p>• Added category filters with Quake icons/colors (All/Strafe/Slick/Tele/RL/PG/GL/LG/BFG)</p>
                                    <p>• Grouped gametypes into Run/CTF blocks with visual separation</p>
                                    <p>• Glassmorphism design with backdrop blur/gradients/borders</p>
                                    <p>• Modernized page header with gradient/typography</p>
                                    <p>• Moved player counts to table headers "VQ3 Rankings (X)"</p>
                                    <p>• Fixed "My VQ3/CPM Rating" heights with min-height 60px flex center</p>
                                    <p>• Color-coded borders (blue VQ3/purple CPM)</p>
                                    <p>• Redesigned Rating component to list-style interactive rows</p>
                                    <p>• Added hover effects with blurred/sharpened profile photos</p>
                                    <p>• Medal emojis for top 3 (🥇🥈🥉)</p>
                                    <p>• Improved mobile responsiveness with adaptive fonts/spacing</p>
                                    <p>• Visual hierarchy with physics icons/compact dd/mm/yy dates</p>
                                    <p>• Drop shadows for text readability over dynamic backgrounds</p>
                                    <p>• Added category param to ranking queries supporting weapon/function rankings</p>
                                    <p>• Removed Dropdown dependency for cleaner code</p>
                                    <p>• Improved state management with proper watchEffect route params</p>
                                    <p>• Better semantic HTML with flex layouts/responsive breakpoints</p>
                                </div>
                            </div>
                        </div>

                        <!-- Maplists Function -->
                        <div class="relative pl-6 border-l-2 border-green-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-green-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-green-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-green-300">Maplists Function</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Created maplists table with public/private, Play Later flag, social counters</p>
                                    <p>• Created maplist_maps pivot table for many-to-many with ordering</p>
                                    <p>• Created maplist_likes and maplist_favorites tables</p>
                                    <p>• Built Maplist model with relationships and social helpers</p>
                                    <p>• Updated User model to auto-create Play Later on registration</p>
                                    <p>• Created CreatePlayLaterMaplists command for 660 existing users</p>
                                    <p>• Built full REST API in MaplistController</p>
                                    <p>• Enforced privacy (Play Later hidden, always private, owner-only, non-deletable)</p>
                                    <p>• Created AddToMaplistModal.vue with quick "Add to Play Later" shortcut</p>
                                    <p>• Listed user's maplists in modal with counts and on-the-fly creation</p>
                                    <p>• Added real-time search in AddToMaplistModal</p>
                                    <p>• Built MaplistCard.vue with 2x2 thumbnail preview of first 4 maps</p>
                                    <p>• Added Q3 color-coded usernames using $q3tohtml()</p>
                                    <p>• Displayed social stats (likes, favorites, map count)</p>
                                    <p>• Added Play Later badge in MaplistCard</p>
                                    <p>• Created Maplists/Index.vue with public browser grid, sort by likes/favorites</p>
                                    <p>• Redesigned Maplists/Show.vue with card-based grid using MapCard</p>
                                    <p>• Added breadcrumb navigation to Maplists/Show</p>
                                    <p>• Conditional UI for Play Later vs regular (hide social for Play Later)</p>
                                    <p>• Added server selection dropdown for Play Later owners (online servers only)</p>
                                    <p>• Added play button per map copying connect command with callvote</p>
                                    <p>• Added remove button for map owners</p>
                                    <p>• Integrated "Add to Maplist" button in MapView</p>
                                    <p>• Displayed top 3 favorited public maplists in Profile.vue grid</p>
                                    <p>• Added "Maplists" link to MainLayout navigation</p>
                                    <p>• Added "Play Later" and "All My Maplists" to user dropdown</p>
                                    <p>• Performance: eager loading, counter caching, database indexes, pagination</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tag System -->
                        <div class="relative pl-6 border-l-2 border-green-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-green-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-green-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-green-300">Tag System</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Created Tag model and migration with name, display_name, category, usage_count</p>
                                    <p>• Built TagController with full CRUD for tags on maps and maplists</p>
                                    <p>• Added TagSeeder populating categories (Difficulty, Movement, Weapons, Items, Environment, Functions)</p>
                                    <p>• Implemented tag filtering for maps with backend support</p>
                                    <p>• Enabled tag adoption where public maplist tags propagate to all maps</p>
                                    <p>• Ensured tag persistence on maps after maplist deletion</p>
                                    <p>• Created tag dropdown UI with alphabetical sort, search, click-to-add</p>
                                    <p>• Added visibility control for maplists with public/private toggle</p>
                                    <p>• Implemented public warning modal noting permanent public status</p>
                                    <p>• Added visibility badge (green "Public" or gray "Private")</p>
                                    <p>• Moved reorder button to top-right below Edit/Delete</p>
                                    <p>• Added full tag CRUD on maplist detail page with real-time updates</p>
                                    <p>• Used Vue Teleport for tag dropdown positioning (no z-index issues)</p>
                                    <p>• Filtered tags in dropdown to show only unadded ones</p>
                                    <p>• Removed category labels from tag buttons for cleaner UI</p>
                                    <p>• Ensured responsive design for all screens</p>
                                </div>
                            </div>
                        </div>

                        <!-- Clans Reworked Drastically -->
                        <div class="relative pl-6 border-l-2 border-green-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-green-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-green-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-green-300">Clans Reworked Drastically</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added ClanStatisticsController with comprehensive stats calculation</p>
                                    <p>• Added ClanAchievements component with leaderboards/hall of fame/special sections</p>
                                    <p>• Added filterable Untouchable WR section (All/Solo/Tied tabs)</p>
                                    <p>• Added tooltips to all stats boxes explaining metrics</p>
                                    <p>• Compacted all sections/tables for better space usage</p>
                                    <p>• Added UpdatePlayerRankingsCache command caching WR/top3 counts</p>
                                    <p>• Fixed WR count calculation to only count rank #1 positions</p>
                                    <p>• Added rankings:cache scheduled task every 20 minutes</p>
                                    <p>• Added cached_wr_count/cached_top3_count columns to users</p>
                                    <p>• Added 10 animated clan name effects (particles/orbs/lines/matrix/glitch/wave/neon/rgb split/flicker/hologram)</p>
                                    <p>• Added effect color picker with hex color support</p>
                                    <p>• Added effects to clan list cards/detail page/edit modal preview</p>
                                    <p>• Added CSS keyframe animations for all effects in app.css</p>
                                    <p>• Added clan sorting by name/members/world records/top 3</p>
                                    <p>• Added WR/top3 count badges on clan cards using cached statistics</p>
                                    <p>• Added clan tag field to clans table/management</p>
                                    <p>• Displayed clan name effects on clan cards list</p>
                                    <p>• Kept edit clan modal open after saving changes (preserve state)</p>
                                    <p>• Compacted Featured Stats Cards with horizontal layout</p>
                                    <p>• Compacted Hall of Fame boxes and fix layout distribution</p>
                                    <p>• Compacted Additional Stats Grid boxes (Top 10/Avg WR Age/Map Coverage/Physics)</p>
                                    <p>• Added icons and improved spacing throughout statistics sections</p>
                                    <p>• Migrations for indexes, tag/name_effect/effect_color columns, cached rankings</p>
                                    <p>• Added clan background image upload with image cropper</p>
                                    <p>• Changed "Member Notes" to "Member Details" throughout UI</p>
                                    <p>• Added member config file (.cfg) upload/download/delete functionality</p>
                                    <p>• Added MemberNoteEditor component with rich text editor</p>
                                    <p>• Modernized pagination component with ultra-sleek design</p>
                                    <p>• Enhanced player select dropdowns with modern styling</p>
                                    <p>• Fixed clan statistics to exclude soft-deleted members</p>
                                    <p>• Fixed member name tooltips overflow on clan cards</p>
                                    <p>• Replaced missing jumppad icon with push.svg</p>
                                    <p>• Reorganized Hall of Fame weapon masters to same row</p>
                                    <p>• Added default text for empty member notes in roster</p>
                                    <p>• Improved roster box expansion with smooth max-height animation</p>
                                    <p>• Fixed clan name effects alignment after tag addition</p>
                                    <p>• Added Q3 color code support to clan tags</p>
                                    <p>• Removed brackets from clan tag display</p>
                                    <p>• Added "Save Changes" button at top of Edit Clan form</p>
                                    <p>• Implemented file size limits (1MB avatars/5MB backgrounds)</p>
                                    <p>• Added ShareInertiaData middleware for Jetstream features</p>
                                    <p>• avatar_effect/name_effect/avatar_effect_color to clans</p>
                                    <p>• Changed featured_stat to array for multiple statistics</p>
                                    <p>• Added avatar effect rendering for clan avatars</p>
                                </div>
                            </div>
                        </div>

                        <!-- Models with 3D Preview -->
                        <div class="relative pl-6 border-l-2 border-blue-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-blue-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-blue-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-blue-300">Models with 3D Preview</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Implemented full MD3 viewer with Three.js 3D visualization</p>
                                    <p>• Parsed MD3 binary format (frames/tags/surfaces)</p>
                                    <p>• Loaded TGA textures with UV mapping</p>
                                    <p>• Parsed skin files for texture-to-surface mapping</p>
                                    <p>• Assembled full player models (head+upper+lower) via TAG attachments</p>
                                    <p>• Added interactive camera (rotate/pan/zoom)</p>
                                    <p>• Parsed animation.cfg for frame-by-frame vertex animation</p>
                                    <p>• Implemented independent leg/torso animations with timing</p>
                                    <p>• Added looping animation support</p>
                                    <p>• UI controls for Walk/Run/Jump/Crouch/Gesture/Idle</p>
                                    <p>• Web Audio API for 13 character sounds (jump/taunt/death/pain/fall)</p>
                                    <p>• Automatic sound-to-animation mapping/playback</p>
                                    <p>• Volume slider and enable/disable toggle</p>
                                    <p>• Individual sound test buttons</p>
                                    <p>• PK3 upload support with automatic extraction/organization</p>
                                    <p>• Model CRUD with categories and public browsing/download</p>
                                    <p>• Implemented Q3-accurate frame offsets matching cg_players.c:210-214</p>
                                    <p>• Applied -63 offset to LEGS animations for lower.md3 local frames</p>
                                    <p>• Added playBothAnimation() for deaths on both legs/torso simultaneously</p>
                                    <p>• Implemented Q3 idle animation system (LEGS_IDLE + TORSO_STAND)</p>
                                    <p>• Researched bg_pmove.c:PM_Footsteps() and PM_TorsoAnimation() logic</p>
                                    <p>• Animations ALWAYS play (animMgr.playing = true, never stop)</p>
                                    <p>• Implemented Q3-style animation-to-sound event system</p>
                                    <p>• Researched ioq3 cg_event.c/cg_players.c for event-based sound triggering</p>
                                    <p>• Mapped LEGS_JUMP/LEGS_JUMPB→jump1 (EV_JUMP)</p>
                                    <p>• Mapped TORSO_GESTURE→taunt (EV_TAUNT)</p>
                                    <p>• Mapped BOTH_DEATH1/2/3→death1/2/3 (EV_DEATH)</p>
                                    <p>• Added Q3ShaderParser for .shader files</p>
                                    <p>• Added Q3ShaderMaterial system for advanced rendering</p>
                                    <p>• Integrated shader loading/application in MD3Loader</p>
                                    <p>• Fixed default cull mode (removed back, use null)</p>
                                    <p>• Supported additive/subtractive/multiply blending, texture animations, environment mapping</p>
                                    <p>• Fixed head see-through issue (DoubleSide default)</p>
                                    <p>• Fixed skateboard alpha channel (proper transparent cutout)</p>
                                    <p>• Replaced static image thumbnails with live 3D ModelViewer</p>
                                    <p>• Displayed animated idle poses (LEGS_IDLE, TORSO_STAND) in thumbnails</p>
                                    <p>• Added thumbnailMode prop to ModelViewer</p>
                                    <p>• Configured separate camera positions for thumbnail vs detail view</p>
                                    <p>• Removed grid floor from 3D viewer</p>
                                    <p>• Zoomed and centered models properly</p>
                                    <p>• Replaced multiple-material approach with single THREE.ShaderMaterial</p>
                                    <p>• Implemented multi-stage shader blending in fragment shader (fixes flickering)</p>
                                    <p>• Supported up to 4 stages with blend functions</p>
                                    <p>• Added tcGen environment mapping and tcMod scroll animations</p>
                                    <p>• Added rgbGen lightingDiffuse</p>
                                    <p>• Added static texture cache shared across surfaces</p>
                                    <p>• Implemented smart fallback (black for additive, white for opaque)</p>
                                    <p>• Fixed transparency detection (base stage only)</p>
                                    <p>• Supported texture format fallback (.tga→.TGA→.jpg→.png)</p>
                                    <p>• Added automatic sarge sound fallback</p>
                                    <p>• Eliminated 404 console spam for missing sounds</p>
                                    <p>• Fixed rendering issues (brandon's glowing hat, mynx's shiny surfaces, bones' cyan hologram)</p>
                                    <p>• Significant performance improvement via shader rewrite</p>
                                    <p>• Added dynamic lighting controls (ambient/directional/back intensity/color/position)</p>
                                    <p>• Added updateLightingFromScene() in Q3ShaderMaterial</p>
                                    <p>• Exposed light control methods via ModelViewer API</p>
                                    <p>• Adjusted default lighting (ambient 0.9, directional 0.65, back 0.2)</p>
                                    <p>• Reorganized layout (Description/Technical Details left, Animation/Sound/Light controls right)</p>
                                    <p>• Made animation/sound buttons compact with flexbox wrap</p>
                                    <p>• Added collapsible light controls panel with 8 control methods</p>
                                    <p>• Implemented real-time shader uniform updates</p>
                                    <p>• Extended loadTexture() with fallbackBaseUrl</p>
                                    <p>• Auto-fallback shader textures to baseq3 when missing from PK3</p>
                                    <p>• Enhanced loadTextureForMesh() with multi-extension fallback (TGA/JPG/JPEG/PNG)</p>
                                    <p>• Updated ModelsController to detect/create separate entries per model in PK3</p>
                                    <p>• Added pagination support to scraper (--pages, --start-page, --reverse)</p>
                                    <p>• Implemented download history tracking</p>
                                    <p>• Added sort functionality (newest/oldest) to model index</p>
                                    <p>• Improved model type badge styling</p>
                                    <p>• Added single-click 64x64 PNG head icon generation with FOV-based camera</p>
                                    <p>• Added browser-based 300x300 GIF generation (36 frames, 360° rotation) using gif.js Web Workers</p>
                                    <p>• Added progress tracking and combined upload</p>
                                    <p>• Added head_icon/thumbnail_path/base_model_file_path columns</p>
                                    <p>• Added base model detection (sarge/klesk/etc.)</p>
                                    <p>• Classified model types (complete/skin pack/sound pack/mixed)</p>
                                    <p>• Built bulk upload interface for admins</p>
                                    <p>• Auto-extracted metadata</p>
                                    <p>• Supported base Q3 files extracting pak0.pk3 & pak2.pk3 to public/baseq3/</p>
                                    <p>• Added base_model and model_type columns</p>
                                    <p>• Created SetupBaseModels.php command</p>
                                    <p>• Created BaseQuake3ModelsSeeder for 23 base Q3 models</p>
                                    <p>• Created ImportBaseWeapons artisan command</p>
                                    <p>• Implemented loadWeaponModel() in MD3Loader for composite weapons</p>
                                    <p>• Loaded main weapon body and auto-attached parts (barrel/hand/flash via TAGs)</p>
                                    <p>• Added main_file column for non-standard filenames</p>
                                    <p>• Added 'item' category to model category enum</p>
                                    <p>• Implemented separate camera positioning for weapons</p>
                                    <p>• Skipped skin file loading for weapons</p>
                                    <p>• Loaded shaders from models.shader before model loading</p>
                                    <p>• Added complete weapon system with sounds/projectiles/firing for all 10 Q3 weapons</p>
                                    <p>• Implemented weapon-specific fire rates</p>
                                    <p>• Added keyboard (Ctrl) and mouse controls for firing</p>
                                    <p>• Enabled continuous firing for automatic weapons</p>
                                    <p>• Added weapon kick/recoil animation on fire</p>
                                    <p>• Created projectiles: plasma sprites with trail, rocket with exhaust, railgun beam, lightning beam, grenade with gravity, BFG glow, bullet tracers, grapple</p>
                                    <p>• Spawned projectiles from muzzle flash with proper direction</p>
                                    <p>• Added automatic projectile cleanup and lifetime management</p>
                                    <p>• Fixed railgun fire sound bug (reordered isRailgun before isLightningGun check)</p>
                                    <p>• Model approval workflow (pending/approved/rejected/hidden)</p>
                                    <p>• Allowed texture/shader-only weapon uploads without MD3 files</p>
                                    <p>• Fixed weapon mute button handling weapon sounds properly</p>
                                    <p>• Allowed users to download own uploads regardless of approval status</p>
                                    <p>• FileController.php for case-insensitive file serving eliminating 404 spam</p>
                                    <p>• Created ModelsController.php, PlayerModel.php, ModelViewer.vue, MD3Loader.js, MD3AnimationManager.js, MD3SoundManager.js</p>
                                    <p>• Added Models pages: Index, Create, Show</p>
                                    <p>• Migration for player_models table</p>
                                </div>
                            </div>
                        </div>

                        <!-- Demos Integration and Upload -->
                        <div class="relative pl-6 border-l-2 border-blue-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-blue-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-blue-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-blue-300">Demos Integration and Upload</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added gametype field to uploaded_demos to distinguish online (mdf/mfs/mfc) vs offline (df/fs/fc)</p>
                                    <p>• Included record_date tracking in uploaded_demos for accurate timestamps</p>
                                    <p>• Wrote comprehensive OFFLINE_RECORDS.md documentation</p>
                                    <p>• Enhanced DemoProcessorService to differentiate offline vs online demos</p>
                                    <p>• Added createOfflineRecord() method to auto-create offline records from processed demos</p>
                                    <p>• Improved Python demo processor (process_single_demo.py) for better metadata extraction</p>
                                    <p>• Updated renamer.py with additional gametype detection logic</p>
                                    <p>• Updated production storage documentation with Backblaze B2 configuration</p>
                                    <p>• Added 7z compression support for demos (10-20% better than ZIP)</p>
                                    <p>• Added p7zip-full in Docker container</p>
                                    <p>• DEMO_COMPRESSION_FORMAT config option (zip/7z)</p>
                                    <p>• Fixed directory permissions (0755) for processed demos storage</p>
                                    <p>• Updated DemoProcessorService for multiple compression formats</p>
                                    <p>• Backblaze B2 S3-compatible storage integration</p>
                                    <p>• league/flysystem-aws-s3-v3 package for S3 support</p>
                                    <p>• B2 configuration in .env (credentials/endpoint/bucket)</p>
                                    <p>• PRODUCTION_DEPLOYMENT.template.md and PRODUCTION_STORAGE.md documentation</p>
                                    <p>• Enhanced MapRecord component with improved time display and demo download links</p>
                                </div>
                            </div>
                        </div>

                        <!-- Serverlist Reworked -->
                        <div class="relative pl-6 border-l-2 border-purple-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-purple-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-purple-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-purple-300">Serverlist Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Redesigned servers page with modern card layout and compact list view</p>
                                    <p>• Dual-layout system (large cards / compact list) with cookie persistence</p>
                                    <p>• Comprehensive filtering (gametype/physics/hide empty)</p>
                                    <p>• Sorting by popularity/alphabetically with toggle</p>
                                    <p>• Redesigned compact list with VQ3/CPM split columns</p>
                                    <p>• Fixed black Q3 text (^0 color code) visibility with white outline globally</p>
                                    <p>• Improved filter controls with inline labels/optimized spacing</p>
                                    <p>• Standardized page header sizes for Maps/Servers</p>
                                    <p>• Hover-to-expand player list with smooth animation</p>
                                    <p>• Fixed background image positioning with fade gradient</p>
                                    <p>• Reduced header/filter spacing for cleaner layout</p>
                                    <p>• Displayed player's personal best time on each server card</p>
                                    <p>• Showed rank position "Rank 5/150" alongside times</p>
                                    <p>• Added map features hover-to-expand (weapons/items/functions)</p>
                                    <p>• Implemented player list hover-to-expand with "show more" indicators</p>
                                    <p>• Added helper functions for weapon/item/function icons/names</p>
                                    <p>• Subtler hover indicator animations (2.5s cycle, 2px movement)</p>
                                    <p>• Reduced glow effects/ring opacity for less distraction</p>
                                    <p>• Better text shadows for readability over dynamic backgrounds</p>
                                    <p>• Improved gradient fades on thumbnails/player lists</p>
                                    <p>• Enhanced glassmorphism on buttons/containers</p>
                                    <p>• Added myrank_position and myrank_total to server data</p>
                                    <p>• Improved rank calculation/retrieval</p>
                                    <p>• Added debug logging for rank data</p>
                                    <p>• Added manual gametype classification for servers (run/ctf/freestyle/teamrun)</p>
                                    <p>• Implemented server type field in database/admin panel</p>
                                    <p>• Enhanced scraping to preserve gametype</p>
                                    <p>• Improved DefragServer class to handle server type info</p>
                                    <p>• Updated MapFilters component for better filter handling</p>
                                    <p>• Enhanced OnlinePlayer component styling</p>
                                    <p>• Removed expand logic from map detail features (always visible)</p>
                                    <p>• Added glass-like effect to VQ3/CPM leaderboard tables matching Servers</p>
                                    <p>• Improved visibility of dates/ranks/"Your Best" in leaderboards</p>
                                    <p>• Made players list always expanded by default on Servers</p>
                                    <p>• Added server count badges with glassmorphism</p>
                                    <p>• Fixed dropdown backdrop click-away functionality</p>
                                    <p>• Added dynamic position calculation for teleported dropdowns on resize/scroll</p>
                                    <p>• Map hover indicators on Servers page only on map box hover</p>
                                </div>
                            </div>
                        </div>

                        <!-- Maps Reworked -->
                        <div class="relative pl-6 border-l-2 border-purple-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-purple-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-purple-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-purple-300">Maps Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Redesigned PlayerSelect: show dropdown only when search.length > 0</p>
                                    <p>• Complete visual redesign with glassmorphism</p>
                                    <p>• Added search icon in input</p>
                                    <p>• Improved dropdown with backdrop blur/spacing</p>
                                    <p>• Selected items show blue background/checkmark</p>
                                    <p>• Enhanced hover states/transitions</p>
                                    <p>• Added smooth expand/collapse filter animation</p>
                                    <p>• Filter button icon rotates 180°</p>
                                    <p>• Transition max-height 500ms enter/300ms leave with overflow-hidden</p>
                                    <p>• CRITICAL FIX: added missing ref import from Vue</p>
                                    <p>• Replaced Vuetify sliders with custom HTML5 inputs</p>
                                    <p>• Implemented exponential scaling (power 2.5) - most values in first 80% travel, fine control last 20%</p>
                                    <p>• Modern slider styling with blue gradient thumbs</p>
                                    <p>• Hover effects: scale 1.05-1.15 with glow shadows</p>
                                    <p>• Reset button clears all filters without closing using local resetFilters()</p>
                                    <p>• Removed selected items display to prevent shift</p>
                                    <p>• Complete visual redesign of MapFilters with glassmorphism</p>
                                    <p>• Rewrote ItemsSelect from dropdown to visual grid</p>
                                    <p>• Single-click cycling: neutral→include green→exclude red→neutral</p>
                                    <p>• Grid auto-fill 48px tiles with sprites</p>
                                    <p>• Visual state indicators (checkmark green/X red/neutral gray)</p>
                                    <p>• Header with counts and bulk buttons (Clear/Include All green/Exclude All red)</p>
                                    <p>• Hover scale 1.05 with color transitions</p>
                                    <p>• No dropdown/search - direct visual interaction</p>
                                    <p>• Constant grid size preventing layout shift</p>
                                    <p>• Redesigned MapCard with modern layout/aspect ratios</p>
                                    <p>• Physics badge repositioned top-right</p>
                                    <p>• Items/weapons/functions overlay bottom-right with backdrop blur</p>
                                    <p>• Compact 3x3 icon displays instead of 4x4</p>
                                    <p>• Improved copy/download button styles</p>
                                    <p>• Better typography hierarchy</p>
                                    <p>• Increased pagination from 21 to 30 maps per page in both index() and filters()</p>
                                    <p>• Exponential slider scaling for fine control</p>
                                    <p>• CSS transitions with proper easing</p>
                                    <p>• Modern color palette with semi-transparent elements</p>
                                    <p>• Consistent component styling</p>
                                    <p>• Better keyboard support (Enter to submit search)</p>
                                    <p>• Hero section with full-width map thumbnail background on MapView</p>
                                    <p>• Blur effect and fade gradient on map background</p>
                                    <p>• Compact modern record card design with improved spacing</p>
                                    <p>• Emerald highlight for user's personal records</p>
                                    <p>• Amber/gold highlight for old top records with visible styling</p>
                                    <p>• Improved sort button with clear labels (⚡ Fastest / 📅 Newest)</p>
                                    <p>• Fixed sort functionality to properly toggle between time and date</p>
                                    <p>• Fixed horizontal scrollbar bug from w-screen overflow</p>
                                    <p>• Fixed rank calculation to show correct ranks per gametype (CTF1/CTF2/etc.)</p>
                                    <p>• Added glassmorphism effects to leaderboard containers</p>
                                    <p>• Smart gametype tabs: auto-detect and default to most populated gametype</p>
                                    <p>• Hide empty gametype tabs (only show tabs with records)</p>
                                    <p>• Display record count on each gametype tab for better visibility</p>
                                    <p>• Backend auto-detect most populated gametype when not specified</p>
                                    <p>• Calculate and pass gametype statistics to frontend</p>
                                    <p>• Query optimization using SUBSTRING_INDEX to group by base gametype</p>
                                    <p>• Replaced Dropdown components with horizontal tab buttons</p>
                                    <p>• Added v-show directive to hide tabs with 0 records</p>
                                    <p>• Improved visual hierarchy with active tab highlighting (blue background)</p>
                                    <p>• Simplified sort button (combined date/time toggle)</p>
                                    <p>• CTF maps now default to CTF1 instead of empty RUN gametype</p>
                                    <p>• Users immediately see which gametypes are available</p>
                                    <p>• No more clicking through dropdowns to find populated gametypes</p>
                                    <p>• Added Quake icons for weapons/items/functions</p>
                                    <p>• Display all map features with proper icons</p>
                                    <p>• Adopted consistent header styling from records page for VQ3/CPM tables</p>
                                    <p>• Compact headers with text-lg colored titles and smaller icons</p>
                                    <p>• Changed map names from blue-400 to neutral gray-300 (white on hover)</p>
                                    <p>• Improved date format dd/mm/yy for readability</p>
                                    <p>• Enhanced background blur effects on cards</p>
                                    <p>• Fixed deleted_at column reference in maps table query</p>
                                    <p>• Added proper icon mappings for all Quake weapons/items/functions</p>
                                    <p>• Fixed fallback icons with appropriate defaults</p>
                                    <p>• Replaced emoji icons with custom SVG icons for VQ3/CPM physics</p>
                                    <p>• Added custom haste.svg icon for fastest sorting mode</p>
                                    <p>• Implemented active server display with player counts and defrag:// protocol links</p>
                                    <p>• Added map download button with improved visibility</p>
                                    <p>• Created mobile physics toggle to switch between VQ3/CPM leaderboards on small screens</p>
                                    <p>• Made map author clickable to filter maps by author</p>
                                    <p>• Fixed rank calculation to preserve time-based ranks when sorting by date</p>
                                    <p>• Improved header consistency with min-width and placeholder for missing records</p>
                                    <p>• Hid gametype buttons when only one gametype has records</p>
                                    <p>• Updated MapRecordSmall component to match desktop ultra-compact design</p>
                                    <p>• Removed star badge for personal records outside top 3</p>
                                    <p>• Ensured top 3 medals display for all users including personal records</p>
                                    <p>• Changed CPM color scheme to purple throughout UI</p>
                                    <p>• Made record cards much more compact and visually appealing</p>
                                    <p>• Emphasized rank numbers and times with larger bolder fonts</p>
                                    <p>• Added top 3 rank colors and medals (🥇🥈🥉)</p>
                                    <p>• Old top records show crown icon (👑) for ranks 1-3 only</p>
                                    <p>• Player names and flags more visible with better contrast</p>
                                    <p>• Dates shown in uniform MM/DD/YYYY format with monospace font</p>
                                    <p>• Added smart hover effects: row highlights/rank scales-pops/badges animate</p>
                                    <p>• Demo download and date info subtle by default, visible on hover</p>
                                    <p>• Made VQ3/CPM headers more compact</p>
                                    <p>• Fixed Old Top toggle state persistence across page refreshes</p>
                                    <p>• Added localStorage sync for toggle state</p>
                                    <p>• Fixed ToggleButton to properly watch for prop changes</p>
                                </div>
                            </div>
                        </div>

                        <!-- Records Reworked -->
                        <div class="relative pl-6 border-l-2 border-purple-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-purple-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-purple-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-purple-300">Records Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Complete rewrite of Record.vue: removed old chunky card layout with borders/shadows</p>
                                    <p>• Added map thumbnail as background (always visible, blurred)</p>
                                    <p>• Background fades in on hover with unblur effect (opacity 20%→60%, blur-md→blur-none)</p>
                                    <p>• Implemented ultra-compact horizontal layout: Rank|Map Name|Player|Time|Physics|Date</p>
                                    <p>• Removed separate map thumbnail from row - now only background</p>
                                    <p>• Top 3 ranks show medals (🥇🥈🥉) instead of colored numbers</p>
                                    <p>• Replaced physics badges with clean VQ3/CPM icons</p>
                                    <p>• Removed Mode badges (CTF/RUN) for cleaner look</p>
                                    <p>• Smaller compact elements (6x6 avatars/xs text/tighter gaps)</p>
                                    <p>• Background extends to edges with rounded corners matching container</p>
                                    <p>• Smooth 500ms transitions on all hover effects</p>
                                    <p>• Removed auto-update button functionality from RecordsView.vue</p>
                                    <p>• Added gamemode filtering (ALL/RUN/CTF) with color-coded buttons</p>
                                    <p>• Redesigned header with gradient background and modern filter buttons</p>
                                    <p>• Changed physics filter from dropdown to inline buttons with icons</p>
                                    <p>• Updated container styling: bg-black/40 border-white/5</p>
                                    <p>• Cleaner pagination styling matching overall dark theme</p>
                                    <p>• Map backgrounds create visual context for each record</p>
                                    <p>• Subtle borders (border-white/[0.02]) instead of chunky gray lines</p>
                                    <p>• Hover reveals full map image behind row with darker→lighter overlay transition</p>
                                    <p>• All text/icons get subtle color shifts on hover</p>
                                    <p>• First/last rows have rounded corners to match parent container</p>
                                    <p>• Overall more compact with less vertical space per record</p>
                                    <p>• Updated Record component with consistent neutral map name styling</p>
                                    <p>• Improved date format dd/mm/yy for readability</p>
                                    <p>• Enhanced background blur effects on cards</p>
                                </div>
                            </div>
                        </div>

                        <!-- Main Page Reworked -->
                        <div class="relative pl-6 border-l-2 border-orange-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-orange-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-orange-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-orange-300">Main Page Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Updated Home.vue to use MainLayout instead of separate layout</p>
                                    <p>• Applied consistent shadow gradient fade effects</p>
                                    <p>• Improved shadow gradient fade effect on HomeLayout header</p>
                                    <p>• Added backdrop-blur-xl bg-black/60 for consistent opacity</p>
                                    <p>• Better shadow gradients fading from header to content</p>
                                    <p>• More consistent spacing/padding throughout</p>
                                    <p>• Improved readability with better contrast/blur effects</p>
                                    <p>• Enhanced hovers/transitions</p>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Page Reworked -->
                        <div class="relative pl-6 border-l-2 border-orange-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-orange-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-orange-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-orange-300">Profile Page Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added profile_background_path field to users table</p>
                                    <p>• Implemented background upload with vue-advanced-cropper (4.8 aspect ratio, 1920x400px)</p>
                                    <p>• Added SettingsController upload/deletion methods for backgrounds</p>
                                    <p>• Displayed background on profile with edge fades</p>
                                    <p>• Background attached to menu with max 400px height/1920px width</p>
                                    <p>• Added avatar_effect, name_effect, color, avatar_border_color fields to users</p>
                                    <p>• Implemented CSS effects system (effects.css) for animated avatars/names</p>
                                    <p>• Added avatar/name effect selection in profile preferences</p>
                                    <p>• Added color picker for effect customization</p>
                                    <p>• Added avatar border color selection</p>
                                    <p>• Redesigned profile header: compact 280px horizontal layout</p>
                                    <p>• Added floating transparent elements with glassmorphism</p>
                                    <p>• Compact stats grid: 8 cards (4 cols mobile/8 cols desktop) showing both CPM/VQ3 values</p>
                                    <p>• Sidebar navigation tabs: left sidebar (sticky, 1/4 width)</p>
                                    <p>• Records table redesign: inline Record component from RecordsView</p>
                                    <p>• Removed player info from records table (profile context makes it redundant)</p>
                                    <p>• Epic hover effects with gradient glows/animations</p>
                                    <p>• Added Map Completionist section showing unplayed maps with pagination</p>
                                    <p>• Backend getUnplayedMaps() in ProfileController</p>
                                    <p>• Frontend display: 10 maps/page with thumbnails/names/authors</p>
                                    <p>• Improved pagination with Prev/Next buttons and ellipsis for large page counts</p>
                                    <p>• Changed map names color from physics-specific (blue/purple) to neutral gray</p>
                                    <p>• Installed vue-advanced-cropper package</p>
                                    <p>• Added effects.css with animation keyframes</p>
                                    <p>• Updated rebuild-frontend.sh with better cache clearing</p>
                                    <p>• Refactored Profile.vue: stats grid/sidebar tabs/inline records rendering</p>
                                    <p>• Added ProfileProgressBar component</p>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Settings Reworked -->
                        <div class="relative pl-6 border-l-2 border-orange-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-orange-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-orange-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-orange-300">Profile Settings Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added profile background upload section with vue-advanced-cropper</p>
                                    <p>• Modernized settings UI with glassmorphism effects</p>
                                    <p>• Added avatar/name effect selectors in preferences</p>
                                    <p>• Added color pickers for effect customization</p>
                                    <p>• Avatar border color selection interface</p>
                                    <p>• Improved form layouts and spacing</p>
                                    <p>• Added file size notes (1MB avatars/5MB backgrounds)</p>
                                    <p>• GIF support notes and descriptions</p>
                                    <p>• Profile background uses background-size for proper 1920px scaling</p>
                                </div>
                            </div>
                        </div>

                        <!-- Notification System Reworked -->
                        <div class="relative pl-6 border-l-2 border-orange-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-orange-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-orange-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-orange-300">Notification System Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Updated notification system with world record toggle in record notifications</p>
                                    <p>• Added notification preview settings for unread_only and hide_old_wrs</p>
                                    <p>• Enhanced notification processing to respect preview settings</p>
                                    <p>• Improved SystemNotificationMenu with preview filtering</p>
                                    <p>• Updated NotificationsView with better filtering and display</p>
                                    <p>• Applied modern styling to both notification dropdowns (records/system)</p>
                                    <p>• Fixed interaction issues with Teleport pattern</p>
                                    <p>• Improved notification item layout with better spacing</p>
                                    <p>• Updated icon sizes/colors for consistency</p>
                                    <p>• Added proper transitions/hover effects to notifications</p>
                                </div>
                            </div>
                        </div>

                        <!-- Header Reworked -->
                        <div class="relative pl-6 border-l-2 border-orange-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-orange-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-orange-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-orange-300">Header Reworked</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Completely redesigned responsive header with clean Tailwind breakpoints</p>
                                    <p>• Two-row responsive layout (Logo/Search/Notifications/Profile in row 1, all nav links in row 2)</p>
                                    <p>• Progressive menu hiding right-to-left into "More" dropdown (Tournaments→Clans→Bundles→Demos→Records→Ranking→Maps→Servers→News)</p>
                                    <p>• Menu items collapse into "More" dropdown using standard breakpoints (2XL/XL/LG/MD/SM)</p>
                                    <p>• Removed old JS screen width detection logic</p>
                                    <p>• Logo always visible on left</p>
                                    <p>• Search bar/notifications/profile avatar always visible at all sizes</p>
                                    <p>• Implemented Teleport pattern for all dropdowns (search/notifications/profile/more menu)</p>
                                    <p>• Fixed click-away behavior: dropdowns close when clicking outside</p>
                                    <p>• Fixed interaction issues: users can click inside without overlay blocking</p>
                                    <p>• Wrapped overlay and dropdown in single Teleport for proper z-index stacking</p>
                                    <p>• Updated styling with modern glassmorphism (backdrop-blur-xl/bg-gray-900/95)</p>
                                    <p>• Redesigned search results popup with category tabs (All/Maps/Players)</p>
                                    <p>• Increased search width to 550px for better spacing/layout</p>
                                    <p>• Color-coded active states (blue/green/purple)</p>
                                    <p>• Simplified MapSearchItem/PlayerSearchItem components with cleaner horizontal layouts</p>
                                    <p>• Removed redundant borders/improved hover states</p>
                                    <p>• Better visual hierarchy with proper text sizing/colors</p>
                                    <p>• Avatar always visible at all sizes, username/arrow hidden on small screens (&lt;MD)</p>
                                    <p>• Rounded corners updated (rounded-md→rounded-xl/rounded-lg)</p>
                                    <p>• Applied Teleport fix for proper click-away on profile dropdown</p>
                                    <p>• Removed screenWidth reactive state/resize event listeners</p>
                                    <p>• Removed onSearchBlur function (replaced with Teleport overlay pattern)</p>
                                    <p>• Removed duplicate mobile search bar</p>
                                    <p>• Used clean Tailwind responsive classes with no arbitrary breakpoints</p>
                                    <p>• Fixed z-index stacking context issues by using Teleport to body</p>
                                    <p>• All dropdowns use consistent overlay pattern with z-index 2000</p>
                                    <p>• Moved all navigation links to dedicated second row in MainLayout.vue</p>
                                    <p>• Placed first row with Logo, Search, Notifications, Profile</p>
                                    <p>• All navigation links visible on md+ screens (no progressive hiding from old design)</p>
                                    <p>• Improved mobile menu dropdown to show all navigation items</p>
                                    <p>• Added border-b to first header row to separate from navigation row</p>
                                    <p>• Applied similar header structure to HomeLayout.vue</p>
                                </div>
                            </div>
                        </div>

                        <!-- Background Changed -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">Background Changed</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added consistent shadow gradient fade effects to all pages (Announcements/Servers/RankingView/Maps/Maplists/Models/RecordsView/Demos/Bundles/Clans/Tournaments/Profile Settings)</p>
                                    <p>• Applied backdrop-blur-xl bg-black/60 for consistent opacity across entire site</p>
                                    <p>• Applied consistent box shadows and border styling across all pages</p>
                                    <p>• Updated Modal.vue to improve backdrop blur and z-index layering</p>
                                    <p>• Updated MaplistCard.vue to add interactive hover states and improved visual hierarchy</p>
                                    <p>• Optimized pattern.svg for background patterns</p>
                                    <p>• Better shadow gradients fading from header to content areas</p>
                                    <p>• More consistent spacing/padding throughout all pages</p>
                                    <p>• Improved readability with better contrast/blur effects</p>
                                    <p>• Enhanced hovers/transitions throughout the site</p>
                                    <p>• Made clan boxes darker (bg-white/5→bg-black/20) to match profile page design</p>
                                    <p>• Updated Hall of Fame/leaderboards/member cards/rival cards backgrounds</p>
                                    <p>• Improved text visibility (text-gray-500→text-gray-300)</p>
                                    <p>• Updated hover states (hover:bg-white/10→hover:bg-black/30)</p>
                                    <p>• Darker stats bar/member bubble colors for consistency</p>
                                </div>
                            </div>
                        </div>

                        <!-- OAuth & Third-Party Integration -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">OAuth & Third-Party Integration</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added OAuthController for third-party authentication integration</p>
                                    <p>• Created TwitchService for Twitch API integration</p>
                                    <p>• Added CheckTwitchLiveStatus command for monitoring live streams</p>
                                    <p>• Added migrations for OAuth tokens to users table</p>
                                    <p>• Added Steam integration support to users table</p>
                                    <p>• Added Twitter integration support to users table</p>
                                    <p>• Updated config/services.php with Twitch/Steam/Twitter credentials</p>
                                    <p>• Added OAuth/Twitch packages to composer.json/lock</p>
                                    <p>• Updated routes/api.php with OAuth endpoints</p>
                                    <p>• Updated routes/web.php with categories/OAuth routes</p>
                                    <p>• Updated Auth pages with OAuth login integration</p>
                                </div>
                            </div>
                        </div>

                        <!-- Performance & Infrastructure (400x Faster) -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">Performance & Infrastructure (400x Faster)</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Added Rust rating service for ELO calculations (60s→0.15s, 400x faster)</p>
                                    <p>• Created build-rust.sh compilation script</p>
                                    <p>• Composite database indexes on records table (mapname/gametype/time, mdd_id/date_set, mdd_id/rank)</p>
                                    <p>• Optimized ProfileController::recentlyBeaten query from 60s to 0.15s</p>
                                    <p>• Removed correlated subqueries, use pre-calculated rank column</p>
                                    <p>• Fixed queries to use indexed columns with proper join conditions</p>
                                    <p>• Switched queue driver from database to Redis for better performance</p>
                                    <p>• Added Redis service to docker-compose.yml</p>
                                    <p>• Typesense search integration for Maps/Players/Demos</p>
                                    <p>• Added Laravel Scout Searchable trait to Demo model</p>
                                    <p>• Configured Typesense schema in scout.php</p>
                                    <p>• Added scout:import commands in deploy.py for auto-indexing in production</p>
                                    <p>• Fixed SCOUT_QUEUE issue - worker now processes all queues not just "demos"</p>
                                    <p>• Fixed worker container configuration (remove /bin/sh wrapper, add SUPERVISOR_PHP_USER)</p>
                                    <p>• Updated LOCAL_SETUP.md/README.md with new features documentation</p>
                                    <p>• Updated deploy.py to run migrations/scout imports automatically</p>
                                </div>
                            </div>
                        </div>

                        <!-- Backend Architecture Updates -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">Backend Architecture Updates</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Updated Clans/Profile/Ranking/Records/Web controllers with OAuth integration</p>
                                    <p>• Updated controllers for category-based ranking support</p>
                                    <p>• Improved data handling and validation across all controllers</p>
                                    <p>• Updated User/Clan/Record models with OAuth token fields</p>
                                    <p>• Added social media integration fields to models</p>
                                    <p>• Updated model relationships and scopes</p>
                                    <p>• Updated EventServiceProvider with new event listeners</p>
                                    <p>• Updated Console Kernel with scheduled ratings/Twitch status commands</p>
                                    <p>• Updated composer.json/lock with OAuth/Twitch packages</p>
                                    <p>• Updated deploy.py with improved deployment process</p>
                                    <p>• Updated tailwind.config.js with new colors/utilities</p>
                                    <p>• Updated public/images/svg/icons.svg with new weapon/function icons</p>
                                    <p>• Updated .gitignore excluding icon libraries (5000+ SVGs) and Rust build artifacts</p>
                                </div>
                            </div>
                        </div>

                        <!-- Scraping & Automation -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">Scraping & Automation</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Fixed WorldSpawn scraper to accept all maps (not just defrag)</p>
                                    <p>• Added compression handling with CURLOPT_ENCODING for reliable scraping</p>
                                    <p>• Added proper headers and timeout configuration</p>
                                    <p>• Successfully scraped 550+ maps in minutes</p>
                                    <p>• Enhanced ScrapeQ3dfModels with better model type detection</p>
                                    <p>• Added pagination support to scraper (--pages, --start-page, --reverse)</p>
                                    <p>• Implemented download history tracking</p>
                                    <p>• Better error handling and progress reporting in scraper</p>
                                    <p>• Added scrape:servers start/finish logging</p>
                                    <p>• Restored scrape:servers schedule and split online/offline cadence</p>
                                    <p>• Enhanced server scraping to preserve gametype classification</p>
                                </div>
                            </div>
                        </div>

                        <!-- Donations Transparency Added -->
                        <div class="relative pl-6 border-l-2 border-red-500/30">
                            <div class="absolute left-0 top-3 w-6 h-0.5 bg-red-500/30"></div>
                            <div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-red-500/10 to-transparent rounded mb-3">
                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                    <span class="text-sm font-semibold text-red-300">Donations Transparency Added</span>
                                </div>
                                <div class="ml-3 text-xs text-gray-300 space-y-1">
                                    <p>• Implemented full donation tracking system with SiteDonation, SelfRaisedMoney, DonationGoal models</p>
                                    <p>• Added database migrations for donation goals, site donations, self-raised money tracking</p>
                                    <p>• Created donation approval workflow supporting pending, approved, rejected statuses</p>
                                    <p>• Integrated currency conversion for EUR, USD, CZK using live exchange rates</p>
                                    <p>• Developed DonationResource in admin panel for managing donations with approval workflow</p>
                                    <p>• Built DonationGoalResource for setting and managing yearly donation goals</p>
                                    <p>• Created SelfRaisedMoneyResource for tracking YouTube/Twitch revenue with full CRUD</p>
                                    <p>• Launched public donations page (/donations) with currency selector for USD/EUR</p>
                                    <p>• Added current year progress bar showing crowd-raised (green) and self-raised (purple) splits</p>
                                    <p>• Included "Where Your Support Goes" section detailing ~€1,200/year operational costs</p>
                                    <p>• Featured "A Brief History" section explaining development since 2021</p>
                                    <p>• Enabled interactive year-by-year donation history with collapsible sections</p>
                                    <p>• Displayed individual donation entries with color-coded borders</p>
                                    <p>• Added all-time total progress bar with visual crowd/self-raised split</p>
                                    <p>• Created global DonationProgressBar footer component visible on all pages except /donations</p>
                                    <p>• Built DonationController@index endpoint and DonationController@getProgress API</p>
                                    <p>• Applied consistent backdrop-blur-xl bg-black/40 styling</p>
                                    <p>• Used professional tone explaining development history and AI-assisted solo work</p>
                                    <p>• Added links to GitHub (Defrag-racing org) and Twitch (DefragLive)</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- VERSION 2: All Items Flat (Scrollable Long List) -->
            <div v-show="activeVersion === 2" class="backdrop-blur-xl bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-8">
                <h2 class="text-2xl font-bold text-blue-400 mb-6">Version 2: Every Feature as Separate Node</h2>
                <p class="text-sm text-gray-400 mb-4">Scroll to see all individual achievements from your comprehensive list</p>

                <div class="ml-8 space-y-0.5 max-h-[800px] overflow-y-auto pr-4">
                    <div v-for="(item, index) in flatItems" :key="index" class="relative pl-6 border-l-2 border-blue-500/20">
                        <div class="absolute left-0 top-2 w-4 h-0.5 bg-blue-500/20"></div>
                        <div class="flex items-center gap-2 px-2 py-1 hover:bg-blue-500/10 rounded transition-all text-xs text-gray-300">
                            <div class="w-1 h-1 bg-blue-300 rounded-full flex-shrink-0"></div>
                            <span>{{ item }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VERSION 3: By 55 Commits (all commits with hover descriptions) -->
            <div v-show="activeVersion === 3" class="backdrop-blur-xl bg-black/40 rounded-xl p-8 shadow-2xl border border-white/5 mb-8">
                <h2 class="text-2xl font-bold text-purple-400 mb-6">Version 3: Organized by 55 Original Commits</h2>
                <p class="text-sm text-gray-400 mb-4">Scroll to see all commits - hover over each to see full details</p>

                <div class="ml-8 space-y-2 max-h-[800px] overflow-y-auto pr-4">
                    <div v-for="commit in commitItems" :key="commit.hash" class="relative pl-6 border-l-2 border-purple-500/20">
                        <div class="absolute left-0 top-2 w-4 h-0.5 bg-purple-500/20"></div>
                        <div class="group relative">
                            <div class="flex items-center gap-2 px-2 py-1.5 bg-gradient-to-r from-purple-500/10 to-transparent hover:from-purple-500/20 rounded cursor-help transition-all">
                                <div class="w-1.5 h-1.5 bg-purple-400 rounded-full flex-shrink-0"></div>
                                <span class="text-xs font-mono text-purple-400">{{ commit.hash }}</span>
                                <span class="text-xs text-gray-300 truncate">{{ commit.title }}</span>
                            </div>
                            <div v-if="commit.description" class="absolute left-0 top-full mt-2 w-[600px] bg-black backdrop-blur-xl border border-purple-500/30 rounded-lg p-4 shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 text-xs text-gray-300 leading-relaxed max-h-96 overflow-y-auto">
                                <strong class="text-purple-400 block mb-2">{{ commit.title }}</strong>
                                <p>{{ commit.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.group:hover {
    z-index: 100;
}
</style>
