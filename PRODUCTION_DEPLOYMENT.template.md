# Production Deployment Guide

## ⚠️ IMPORTANT: Credentials & Secrets

**NEVER commit these to GitHub:**
- `.env` file
- Backblaze B2 API keys
- Database passwords
- API tokens
- SSH keys

## Backblaze B2 Account Information

**Account Location:** your Backblaze account (Backblaze.com)

**Bucket Details:**
- Bucket Name: `defrag-demos`
- Region: `us-west-004`
- Access: Private

**Application Key (Created: 2025-10-02):**
- Key Name: `laravel-defrag`
- Key ID: `YOUR_B2_KEY_ID_HERE`
- Application Key: `YOUR_B2_SECRET_KEY_HERE`
- ⚠️ **SECURITY**: This key has Read/Write access to the bucket

**Access:**
- Login: Stored in password manager
- 2FA: Enabled (recommended)

---

## Production Server Setup

### 1. Clone Repository on Production VPS

```bash
# SSH into production VPS
ssh user@your-production-vps-ip

# Clone repository
cd /var/www
git clone git@github.com:neyoneit/defrag-racing-project.git
cd defrag-racing-project

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2. Create Production .env File

**IMPORTANT:** Never copy `.env` from development! Create new one:

```bash
# Copy example
cp .env.example .env

# Generate app key
php artisan key:generate
```

### 3. Configure Production .env

**Edit `.env` on production server:**

```bash
nano .env
```

**Required configuration:**

```env
# Application
APP_NAME="Defrag Racing"
APP_ENV=production
APP_KEY=base64:GENERATED_BY_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://defrag.racing

# Database (your production MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=defrag_production
DB_USERNAME=defrag_user
DB_PASSWORD=YOUR_SECURE_DB_PASSWORD

# Redis (for queues and cache)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_REDIS_PASSWORD
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis
QUEUE_DEMOS=demos

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Backblaze B2 Storage (COPY FROM THIS DOCUMENT)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=YOUR_B2_KEY_ID_HERE
AWS_SECRET_ACCESS_KEY=YOUR_B2_SECRET_KEY_HERE
AWS_DEFAULT_REGION=us-west-004
AWS_BUCKET=defrag-demos
AWS_ENDPOINT=https://s3.us-west-004.backblazeb2.com
AWS_URL=https://f004.backblazeb2.com/file/defrag-demos
AWS_USE_PATH_STYLE_ENDPOINT=false

# Demo Compression Format
DEMO_COMPRESSION_FORMAT=7z

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@defrag.racing
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@defrag.racing
MAIL_FROM_NAME="Defrag Racing"

# Other services (Typesense, etc.)
TYPESENSE_API_KEY=your-typesense-key
TYPESENSE_HOST=your-typesense-host
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
```

### 4. Set Proper File Permissions

```bash
# Set ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/defrag-racing-project
sudo chmod -R 755 /var/www/defrag-racing-project

# Storage and cache directories need write access
sudo chmod -R 775 /var/www/defrag-racing-project/storage
sudo chmod -R 775 /var/www/defrag-racing-project/bootstrap/cache
```

### 5. Run Production Setup Commands

```bash
# Database migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache

# Create storage symlink
php artisan storage:link

# Install p7zip for demo compression
sudo apt update
sudo apt install -y p7zip-full
```

### 6. Setup Queue Workers

**Create systemd service for queue worker:**

```bash
sudo nano /etc/systemd/system/defrag-queue.service
```

**Add this content:**

```ini
[Unit]
Description=Defrag Racing Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/defrag-racing-project
ExecStart=/usr/bin/php /var/www/defrag-racing-project/artisan queue:work redis --queue=demos --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Enable and start the service:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable defrag-queue
sudo systemctl start defrag-queue
sudo systemctl status defrag-queue
```

### 7. Setup Web Server (Nginx Example)

```bash
sudo nano /etc/nginx/sites-available/defrag-racing
```

**Nginx configuration:**

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name defrag.racing www.defrag.racing;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name defrag.racing www.defrag.racing;

    root /var/www/defrag-racing-project/public;
    index index.php;

    # SSL certificates (use Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/defrag.racing/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/defrag.racing/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Max upload size (for demos)
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Enable site:**

```bash
sudo ln -s /etc/nginx/sites-available/defrag-racing /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Security Checklist

### Production Environment Variables

**These MUST be set correctly:**

- [ ] `APP_ENV=production` (not local!)
- [ ] `APP_DEBUG=false` (CRITICAL - never true in production!)
- [ ] `APP_KEY` generated uniquely for production
- [ ] Strong database password (not 'password'!)
- [ ] Redis password set
- [ ] Backblaze B2 keys from this document
- [ ] HTTPS enabled with valid SSL certificate

### File Permissions

- [ ] `.env` file is NOT readable by web server users: `chmod 600 .env`
- [ ] `.env` is in `.gitignore` (should already be)
- [ ] Storage directories writable by www-data
- [ ] No sensitive files in public directory

### Backblaze B2 Security

- [ ] Application key has minimum required permissions (Read/Write to specific bucket)
- [ ] Keys stored securely (not in git, not in screenshots, not in chat logs)
- [ ] 2FA enabled on Backblaze account
- [ ] Bucket is private (not public)
- [ ] Consider creating separate key for production vs development

---

## Updating Production

### Deploying Code Changes

```bash
# SSH to production
ssh user@production-vps

cd /var/www/defrag-racing-project

# Pull latest changes
git pull origin main

# Update dependencies (if composer.json changed)
composer install --no-dev --optimize-autoloader

# Update frontend (if package.json changed)
npm ci
npm run build

# Run migrations (if any)
php artisan migrate --force

# Clear and rebuild caches
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reload Octane (if using Octane)
php artisan octane:reload

# Restart queue workers
sudo systemctl restart defrag-queue
```

### Zero-Downtime Deployment (Advanced)

Consider using tools like:
- Laravel Envoyer (paid, official Laravel tool)
- Deployer (free, open source)
- GitHub Actions with SSH deployment

---

## Monitoring & Maintenance

### Check Queue Worker Status

```bash
sudo systemctl status defrag-queue
journalctl -u defrag-queue -f  # Watch logs in real-time
```

### Monitor Backblaze B2 Usage

- Login to Backblaze.com (your Backblaze account)
- Check storage usage and bandwidth
- Monitor costs (should be ~$2.50/month initially)

### Check Disk Space

```bash
df -h  # Check VPS disk space
```

### Laravel Logs

```bash
tail -f /var/www/defrag-racing-project/storage/logs/laravel.log
```

### Database Backups

**Setup automated backups:**

```bash
# Create backup script
sudo nano /usr/local/bin/backup-defrag-db.sh
```

**Script content:**

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/defrag-racing"
mkdir -p $BACKUP_DIR

# MySQL backup
mysqldump -u defrag_user -p'YOUR_DB_PASSWORD' defrag_production | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete

echo "Backup completed: db_$DATE.sql.gz"
```

**Make executable and add to cron:**

```bash
sudo chmod +x /usr/local/bin/backup-defrag-db.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
# Add line:
0 2 * * * /usr/local/bin/backup-defrag-db.sh
```

---

## Troubleshooting

### Queue Jobs Not Processing

```bash
# Check worker is running
sudo systemctl status defrag-queue

# Check Redis connection
redis-cli ping

# Check queue size
php artisan queue:monitor redis:demos
```

### Backblaze B2 Connection Issues

```bash
# Test B2 connection
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'test');
>>> Storage::disk('s3')->exists('test.txt');
```

### Demo Upload Fails

```bash
# Check p7zip is installed
which 7z

# Check storage permissions
ls -la storage/app/

# Check logs
tail -100 storage/logs/laravel.log | grep -i error
```

### Site is Slow

```bash
# Check Octane is running (if using Octane)
ps aux | grep octane

# Check database queries
# Enable query logging temporarily in .env
DB_LOG_QUERIES=true

# Check Redis
redis-cli info stats
```

---

## Credentials Reference (Keep Secure!)

**Backblaze B2 (your Backblaze account):**
- Account: https://www.backblaze.com
- Bucket: `defrag-demos`
- Region: `us-west-004`
- Key ID: `YOUR_B2_KEY_ID_HERE`
- Secret: `YOUR_B2_SECRET_KEY_HERE`

**IMPORTANT:**
- Store this document in a secure location (password manager, encrypted notes)
- Do NOT commit this file to GitHub if it contains real credentials
- For GitHub version, replace secrets with placeholders

**Production VPS Access:**
- Store SSH keys securely
- Use SSH key authentication (not passwords)
- Enable UFW firewall
- Keep server updated: `sudo apt update && sudo apt upgrade`

---

## Cost Monitoring

**Monthly costs to track:**

| Service | Expected Cost | What to Monitor |
|---------|---------------|-----------------|
| Backblaze B2 | $2-3/month | Storage usage, downloads |
| VPS (Processing) | $12-24/month | CPU/RAM usage |
| Domain | $12/year | Renewal date |
| SSL Certificate | Free (Let's Encrypt) | Auto-renewal |

**Total estimated: $15-30/month**

---

## Support & Resources

- **Backblaze B2 Docs**: https://www.backblaze.com/docs/cloud-storage
- **Laravel Deployment**: https://laravel.com/docs/deployment
- **Queue Workers**: https://laravel.com/docs/queues
- **Octane**: https://laravel.com/docs/octane

---

## Quick Reference Commands

```bash
# Pull latest code and deploy
git pull && composer install --no-dev && npm run build && php artisan migrate --force && php artisan config:cache && php artisan octane:reload

# Restart services
sudo systemctl restart defrag-queue nginx php8.3-fpm

# Check logs
tail -f storage/logs/laravel.log
journalctl -u defrag-queue -f

# Test B2 upload
php artisan tinker --execute="Storage::disk('s3')->put('test.txt', 'test');"

# Monitor queue
php artisan queue:monitor redis:demos --max=100
```

---

## Models System Deployment

### One-Time Setup (Required for Q3 Models Feature)

The models system requires base Quake 3 game files (pak0.pk3 and pak2.pk3) to be installed on production.

**See detailed guide in:** `MODELS_DEPLOYMENT.md`

#### Quick Setup Steps:

1. **Upload pak files to production:**
   ```bash
   # From your local machine
   scp pak0.pk3 pak2.pk3 user@production-vps:/var/www/defrag-racing-project/production/deploy/storage/app/
   ```

2. **Extract pak files on production:**
   ```bash
   # SSH to production
   ssh user@production-vps
   cd /var/www/defrag-racing-project/production/current

   # Extract base Q3 files to deploy/baseq3/ (persistent across deployments)
   php artisan setup:base-q3
   ```

3. **Verify installation:**
   ```bash
   ls /var/www/defrag-racing-project/production/deploy/baseq3/models/players/
   # Should show 31 base model directories
   ```

#### Directory Structure:

```
/var/www/defrag-racing-project/production/
├── deploy/
│   ├── storage/              # Shared storage (models uploads)
│   │   └── app/
│   │       ├── pak0.pk3     # Base Q3 game data (upload once)
│   │       ├── pak2.pk3     # Team Arena content (upload once)
│   │       └── models/      # User-uploaded model PK3 files
│   ├── baseq3/              # Extracted base Q3 files (run setup:base-q3 once)
│   │   ├── models/players/  # 31 base player models (MD3 + textures)
│   │   ├── sound/           # All base sounds
│   │   ├── textures/        # All base textures
│   │   └── scripts/         # Shader definitions
│   └── .env                 # Production config
├── releases/
│   └── defrag-racing-project-1/
│       └── public/
│           └── baseq3 -> ../../deploy/baseq3  # Symlinked by deploy.py
└── current -> releases/defrag-racing-project-1
```

#### How It Works:

- **deploy.py** automatically creates symlink: `public/baseq3 -> deploy/baseq3`
- Base Q3 files persist across deployments (only extracted once)
- User-uploaded models go to `storage/app/models/` (also persistent)
- 3D viewer uses base files as fallback for skin-only uploads

#### Troubleshooting:

**Models not loading in 3D viewer:**
```bash
# Check baseq3 symlink exists
ls -la /var/www/defrag-racing-project/production/current/public/baseq3
# Should show: baseq3 -> ../../deploy/baseq3

# Check base models are extracted
ls /var/www/defrag-racing-project/production/deploy/baseq3/models/players/
# Should show 31 directories
```

**Re-extract pak files if needed:**
```bash
cd /var/www/defrag-racing-project/production/current
php artisan setup:base-q3
```

**Permissions issues:**
```bash
sudo chown -R www-data:www-data /var/www/defrag-racing-project/production/deploy/baseq3
sudo chmod -R 755 /var/www/defrag-racing-project/production/deploy/baseq3
```
