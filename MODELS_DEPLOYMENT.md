# Models System Deployment Guide

This guide explains how to deploy the Q3 models system to production.

## Prerequisites

You need the following files from your Quake 3 installation:
- `pak0.pk3` (~47MB) - Base Q3 game data
- `pak2.pk3` (~0.7MB) - Team Arena additional content

These files are located in your Quake 3 `baseq3/` directory.

## Deployment Steps

### 1. Upload pak files to production server

Upload the pak files to the storage directory on your production server:

```bash
# Upload pak0.pk3 and pak2.pk3 to:
storage/app/pak0.pk3
storage/app/pak2.pk3
```

**Option A: Using SCP/SFTP**
```bash
scp pak0.pk3 your-server:/path/to/app/storage/app/
scp pak2.pk3 your-server:/path/to/app/storage/app/
```

**Option B: Using Laravel Forge/Panel**
- Upload through file manager to `storage/app/`

### 2. Extract pak files on production

SSH into your production server and run:

```bash
php artisan setup:base-q3
```

This command will:
- Extract `pak0.pk3` (3,539 files)
- Extract `pak2.pk3` (148 files)
- Install all files to `public/baseq3/`
- Display list of available base models (31 models)

Expected output:
```
Setting up base Q3 game data from pak files...
✓ Extracted 3539 files from pak0.pk3
✓ Extracted 148 files from pak2.pk3
Available base player models (31): anarki, biker, bitterman, bones, brandon, carmack, cash, crash, doom, grunt, hunter, keel, klesk, light, lucy, major, medium, mynx, orbb, paulj, ranger, razor, sarge, slash, sorlag, tankjr, tim, uriel, visor, xaero, xian
✓ Setup complete!
Files are now accessible at: /baseq3/*
```

### 3. Verify installation

Check that the base models are accessible:

```bash
ls -la public/baseq3/models/players/
# Should show 31 directories (one for each base model)

ls -la public/baseq3/sound/
# Should show sound directories
```

### 4. Set proper permissions

Ensure the web server can read the files:

```bash
chmod -R 755 public/baseq3/
chown -R www-data:www-data public/baseq3/
```

(Adjust user/group based on your server configuration)

## What Gets Installed

The `public/baseq3/` directory will contain:

```
baseq3/
├── models/
│   ├── players/        # 31 base character models (MD3 files + textures)
│   ├── weapons2/       # Weapon models
│   └── mapobjects/     # Map decoration models
├── sound/
│   ├── player/         # Player sounds (footsteps, pain, death, etc.)
│   ├── weapons/        # Weapon sounds
│   └── world/          # Ambient sounds
├── textures/           # All base textures
├── scripts/            # Shader definitions
├── gfx/                # UI graphics
├── menu/               # Menu assets
└── music/              # Music files
```

## File Structure Explanation

### Base Models (from pak0.pk3 & pak2.pk3)
- Location: `public/baseq3/models/players/{model_name}/`
- Contains: MD3 files (head.md3, upper.md3, lower.md3), textures, skins
- Used by: Skin-only custom model uploads as fallback

### User-Uploaded Models
- Location: `storage/app/public/models/extracted/{slug}/`
- Contains: Extracted PK3 contents
- Original PK3: `storage/app/models/pk3s/{slug}.pk3`

### How It Works

When a user uploads a **skin-only PK3** (no MD3 files):
1. System extracts the PK3 to `storage/app/public/models/extracted/`
2. System detects missing MD3 files
3. System sets `model_type = 'skin'` and `base_model = 'klesk'` (detected from path)
4. 3D viewer loads:
   - MD3 files from `/baseq3/models/players/klesk/` (base model)
   - Textures/skins from `/storage/models/extracted/{slug}/` (custom skin)
5. Result: Custom skin displayed on base model

When a user uploads a **complete custom model** (has MD3 files):
1. System extracts everything
2. System detects MD3 files present
3. System sets `model_type = 'complete'` and `base_model = {model_name}` (self-referencing)
4. 3D viewer loads everything from the extracted directory
5. Result: Fully custom model displayed

## Size Considerations

- **pak0.pk3**: ~47MB (compressed) → ~230MB (extracted)
- **pak2.pk3**: ~0.7MB (compressed) → ~3MB (extracted)
- **Total extracted**: ~233MB in `public/baseq3/`

Ensure your production server has sufficient disk space.

## Backup Recommendation

Since pak files are large and not in git, keep backups:

```bash
# Backup pak files
cp storage/app/pak0.pk3 /path/to/backup/
cp storage/app/pak2.pk3 /path/to/backup/

# Or create a tarball
tar -czf baseq3-pak-files.tar.gz storage/app/pak*.pk3
```

## Troubleshooting

### Command not found
If `php artisan setup:base-q3` fails:
```bash
php artisan list | grep setup
# Should show: setup:base-q3
```

### Extraction fails
Check disk space:
```bash
df -h
```

Check permissions:
```bash
ls -la storage/app/pak*.pk3
# Should be readable by web server user
```

### 3D viewer shows errors for skin-only models
Verify base models are extracted:
```bash
ls public/baseq3/models/players/
# Should show 31 model directories

# Test a specific model
ls public/baseq3/models/players/sarge/
# Should show: head.md3, upper.md3, lower.md3, etc.
```

## Production Checklist

- [ ] Upload pak0.pk3 to `storage/app/`
- [ ] Upload pak2.pk3 to `storage/app/`
- [ ] SSH into production server
- [ ] Run `php artisan setup:base-q3`
- [ ] Verify 31 base models are listed
- [ ] Check `public/baseq3/` exists and has files
- [ ] Set proper permissions (755 for directories, 644 for files)
- [ ] Test uploading a skin-only model
- [ ] Test uploading a complete model
- [ ] Verify 3D viewer works for both types

## Notes

- pak files are **NOT** in git (too large)
- `public/baseq3/` is **NOT** in git (generated from pak files)
- You must manually upload pak files and run extraction on each environment
- Extraction only needs to be done once per environment
- If you delete `public/baseq3/`, re-run `php artisan setup:base-q3`
