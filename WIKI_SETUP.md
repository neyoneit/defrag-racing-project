# MediaWiki Setup Guide

This document explains how to set up and use the MediaWiki integration for the Defrag Racing project.

## Overview

MediaWiki is integrated into this project via **reverse proxy**. Users access it at `yourdomain.com/wiki`, and Laravel proxies requests to the MediaWiki Docker container.

**Architecture:**
- Users visit: `localhost/wiki` (or `defrag.racing/wiki` in production)
- Laravel receives the request and proxies it to the MediaWiki container
- MediaWiki container runs internally on the Docker network
- Same MySQL server, separate `wiki` database

## Initial Setup

### 1. Start the Containers

Start all Docker containers:

```bash
./vendor/bin/sail up -d
```

This will start:
- Laravel application (port 80)
- MediaWiki (internal, exposed temporarily on port 8080 for setup)
- MySQL database
- Redis, Typesense, etc.

### 2. Install MediaWiki

**IMPORTANT:** For initial setup, use port 8080 directly. After setup, you'll access via `/wiki`.

1. Open your browser and navigate to: **http://localhost:8080**

2. Click "**set up the wiki**" to start the installation wizard

3. Follow the installation steps:

   **Database Configuration:**
   - Database type: `MySQL`
   - Database host: `mysql` (not localhost!)
   - Database name: `wiki`
   - Database username: `sail`
   - Database password: `password`

   **Wiki Configuration:**
   - Wiki name: `Defrag Racing Wiki` (or your preference)
   - **IMPORTANT - Server URL:** When asked, use `http://localhost` (NOT http://localhost:8080)
   - Administrator username: Choose your admin username
   - Administrator password: Choose a strong password
   - Email address: Your email

4. Continue through the wizard with default settings

5. At the end, download the `LocalSettings.php` file

6. **IMPORTANT:** Edit the downloaded `LocalSettings.php` and ensure these settings are correct:
   ```php
   ## The protocol and server name to use in fully-qualified URLs
   $wgServer = "http://localhost";  // or https://defrag.racing for production

   ## The URL path to the wiki
   $wgScriptPath = "/wiki";
   ```

7. Replace the placeholder file:
   ```bash
   # The downloaded file is usually in ~/Downloads/LocalSettings.php
   mv ~/Downloads/LocalSettings.php /home/lukas/projects/defrag-racing-project/wiki/LocalSettings.php
   ```

8. Restart the MediaWiki container:
   ```bash
   ./vendor/bin/sail restart mediawiki
   ```

9. **Access your wiki at: http://localhost/wiki** (integrated into your main site!)

## Configuration

### Environment Variables

You can customize these in your `.env` file:

```env
WIKI_PORT=8080                          # Port for accessing wiki
WIKI_DB_NAME=wiki                       # Database name for MediaWiki
WIKI_SITE_NAME="Defrag Racing Wiki"     # Name of your wiki
WIKI_SITE_SERVER=http://localhost:8080  # Base URL for wiki
```

### Customizing LocalSettings.php

After installation, you can edit `wiki/LocalSettings.php` to customize:

- **Logo:** Add your custom logo
- **Skin:** Change the appearance
- **Extensions:** Enable/disable features
- **Permissions:** Control who can edit

Example customizations to add to LocalSettings.php:

```php
# Disable anonymous editing (require login to edit)
$wgGroupPermissions['*']['edit'] = false;

# Allow file uploads
$wgEnableUploads = true;

# Custom logo (place your logo in public/images/)
$wgLogo = "/path/to/your/logo.png";

# Enable visual editor
wfLoadExtension( 'VisualEditor' );
```

## Accessing the Wiki

- **Development:** http://localhost/wiki
- **Production:** https://defrag.racing/wiki (update $wgServer in LocalSettings.php)

The wiki is automatically integrated via Laravel's reverse proxy. No additional web server configuration needed!

## Popular Extensions for Game Wikis

Consider installing these MediaWiki extensions (similar to what OSRS uses):

1. **VisualEditor** - WYSIWYG editing (already included in modern MediaWiki)
2. **Cargo** - Store and query structured data
3. **ParserFunctions** - Advanced template logic
4. **TemplateData** - Document templates
5. **Scribunto** - Lua scripting for advanced templates

To install extensions:
1. Download extension to `wiki/extensions/ExtensionName/`
2. Add `wfLoadExtension( 'ExtensionName' );` to LocalSettings.php
3. Restart container: `./vendor/bin/sail restart mediawiki`

## Content Migration

To migrate content from q3df.org wiki:

1. You can manually copy content or use MediaWiki's import tools
2. Wiki markup is generally compatible, but you may need to adjust formatting
3. Create templates for common elements (maps, players, techniques)

## User Authentication

Currently, MediaWiki has its own separate user system from your Laravel app. To integrate them:

1. Install **PluggableAuth** extension + an auth provider
2. Or use **SimpleSAMLphp** for SSO
3. Or keep them separate for now (users create separate wiki accounts)

## Backup

The wiki data is stored in:
- **Database:** MySQL `wiki` database (included in your regular DB backups)
- **Images:** Docker volume `sail-mediawiki-images`

To backup images:
```bash
docker run --rm -v defrag-racing-project_sail-mediawiki-images:/source -v $(pwd)/backups:/backup alpine tar czf /backup/wiki-images-$(date +%Y%m%d).tar.gz -C /source .
```

## Troubleshooting

### Can't connect to database
- Ensure database host is `mysql` (not `localhost`)
- Check credentials match your `.env` file

### Wiki shows blank page
- Check LocalSettings.php is properly configured
- View logs: `./vendor/bin/sail logs mediawiki`

### Need to reset wiki
```bash
# Drop and recreate database
./vendor/bin/sail mysql -e "DROP DATABASE IF EXISTS wiki; CREATE DATABASE wiki;"
# Delete LocalSettings.php and run installation again
rm wiki/LocalSettings.php
```

## Resources

- [MediaWiki Documentation](https://www.mediawiki.org/wiki/Documentation)
- [OSRS Wiki](https://oldschool.runescape.wiki/) - Great example of a game wiki
- [Silksong Wiki](https://hollowknight.wiki.gg/wiki/Hollow_Knight_Wiki) - Another good reference
- [q3df.org Wiki](https://q3df.org/wiki) - Your source material

## Next Steps

1. Set up basic page structure (Main Page, categories, templates)
2. Create templates for common elements (maps, players, runs)
3. Import content from q3df.org wiki
4. Customize theme/logo to match your Laravel site
5. Consider SSO integration for unified login
