# Local Development Setup for Defrag Racing

## New Features

### 7z Compression for Demos
- Demos are now compressed using **7z format** instead of zip (10-20% better compression)
- Configured via `.env`: `DEMO_COMPRESSION_FORMAT=7z`
- Can be switched back to `zip` if needed
- Package `p7zip-full` installed in Docker container

### Backblaze B2 Cloud Storage
- Demo files stored on Backblaze B2 (S3-compatible storage)
- Cost: ~$2.50/month for 500GB storage
- See [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) for credentials

### Redis Queue System
- Queue jobs now use Redis instead of database
- Much faster for processing demos at scale
- Worker service automatically processes uploads

---

## What Files Were Modified During Setup

**Total files changed: 21**

### File Changes Breakdown:

1. **Dependency Lock Files (2 files)**:
   - `composer.lock` - PHP dependency versions locked after `composer install`
   - `package-lock.json` - Node.js dependency versions locked after `npm install`

2. **Filament Assets (19 files)**:
   - All files in `public/js/filament/` and `public/css/filament/`
   - These are **auto-generated** by the `php artisan filament:assets` command
   - Contains admin panel JavaScript and CSS components

### Why These Files Changed:
- **NOT manually edited** - all changes are from automated Laravel/Filament commands
- The Filament admin framework regenerates its public assets during setup
- Lock files update when dependencies are installed/updated

## Steps to Set Up Local Development

### Prerequisites
- Docker and Docker Compose installed and running
- WSL2 (if on Windows)

### Setup Commands Run:

1. **Initial Docker Setup**:
   ```bash
   ./local_devel/start_local_server.sh
   ```
   *(This script failed at MySQL wait step, so manual steps were needed)*

2. **Manual Laravel Setup**:
   ```bash
   # Database setup
   ./vendor/bin/sail artisan migrate

   # Frontend dependencies and build
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run build

   # Laravel asset and cache setup
   ./vendor/bin/sail artisan storage:link --force
   ./vendor/bin/sail artisan filament:assets
   ./vendor/bin/sail artisan config:cache
   ./vendor/bin/sail artisan route:cache
   ./vendor/bin/sail artisan view:cache
   ./vendor/bin/sail artisan icons:cache
   ./vendor/bin/sail artisan octane:reload
   ./vendor/bin/sail artisan queue:restart
   ```

3. **Load Test Data**:
   ```bash
   ./local_devel/load_dummy_data.sh
   ```

4. **Start Development Server** (CRITICAL):
   ```bash
   ./vendor/bin/sail npm run dev
   ```
   *(This runs Vite dev server - required for local development)*

### Final Result:
- **Website**: http://localhost
- **Admin Panel**: http://localhost/defraghq
- **Admin Credentials**: admin / password

### Key Issue Resolved:
The main problem was that Laravel in `local` mode expects a Vite dev server running on port 5173 to serve JavaScript/CSS assets. Without `npm run dev`, the page loads as blank white because JS assets can't load.

### Container Status:
```bash
./vendor/bin/sail ps
```
Should show:
- laravel.test (healthy)
- mysql (healthy)
- typesense (running)

Plus Vite dev server running in background.

## How to Restart After PC Reboot

After restarting your computer, follow these steps to get the site running again:

1. **Navigate to project directory**:
   ```bash
   cd /home/lukas/projects/defrag-racing-project
   ```

2. **Start Laravel Sail containers**:
   ```bash
   ./vendor/bin/sail up -d
   ```

3. **Start Vite dev server** (CRITICAL - without this you get blank page):
   ```bash
   ./vendor/bin/sail npm run dev
   ```

4. **Verify site is working**:
   - Main site: http://localhost
   - Admin panel: http://localhost/defraghq (admin/password)

### Quick One-Liner:
```bash
cd /home/lukas/projects/defrag-racing-project && ./vendor/bin/sail up -d && ./vendor/bin/sail npm run dev
```

### Why These Steps Are Needed:
- Docker containers stop when PC restarts
- Vite dev server doesn't auto-restart
- **The Vite dev server is essential** - Laravel in local mode expects it for serving JS/CSS assets
- Database data persists in Docker volumes (no data loss)