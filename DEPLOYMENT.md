# Deployment Guide

This guide covers deploying TinySpaces to production environments.

## Pre-Deployment Checklist

- [ ] Run `npm install && npm run build` to compile CSS
- [ ] Set `DEBUG` to 0 in `index.php` for production
- [ ] Review `app/database.php` for security settings
- [ ] Ensure `storage/` and `public/uploads/` exist and are writable
- [ ] Configure a strong admin password (not default admin123)
- [ ] Enable HTTPS/SSL on your server
- [ ] Set up regular database backups

## Security Configuration

### 1. Set Production Mode

Edit `index.php`:

```php
// Production mode - disable debug output
$app->set('DEBUG', 0);
```

### 2. Session Security

In `app/controllers/AuthController.php`, ensure secure session settings:

```php
session_set_cookie_params([
    'secure' => true,      // HTTPS only
    'httponly' => true,    // No JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);
```

### 3. Change Default Admin Credentials

After first login, immediately change the default admin password:

- Default: admin/admin123
- Access: /admin/dashboard
- Change password through profile settings

### 4. Database Backups

Backup `storage/database.sqlite` regularly:

```bash
# Daily backup script
cp storage/database.sqlite storage/backups/database.$(date +%Y%m%d).sqlite
```

## Server Configuration

### PHP Settings (php.ini)

```ini
; File Upload Limits
upload_max_filesize = 100M
post_max_size = 100M

; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php-errors.log

; Session Security
session.secure = On           # HTTPS only
session.httponly = On         # No JavaScript access
session.cookie_samesite = Strict
session.gc_maxlifetime = 2592000  # 30 days
```

### Nginx Configuration (Example)

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    root /var/www/tinyspace;
    index index.php;

    # Enable gzip compression
    gzip on;
    gzip_types text/plain text/css text/javascript application/json;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Block access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ storage/ {
        deny all;
    }

    location ~ tmp/ {
        deny all;
    }

    # PHP configuration
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Rewrite for Fat-Free Framework
    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php [QSA,L];
        }
    }
}
```

### Apache Configuration (Example)

Create `.htaccess` in root:

```apache
RewriteEngine On

# Prevent direct access to sensitive directories
RewriteRule ^(storage|tmp|vendor)(/|$) - [F,L]

# Fat-Free Framework routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Frame-Options "DENY"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Prevent directory listing
Options -Indexes

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## Deployment Platforms

### Heroku

1. Create `Procfile`:

```
web: heroku-php-nginx
```

2. Create `composer.json` (if needed)
3. Set environment variables:

```bash
heroku config:set DEBUG=0
```

4. Deploy:

```bash
git push heroku main
```

### DigitalOcean App Platform

1. Connect GitHub repository
2. Configure build command:

```
npm install && npm run build
```

3. Set environment variables:
   - `DEBUG`: 0
4. Deploy from GitHub

### Shared Hosting (cPanel)

1. Upload files via FTP to `public_html`
2. Create `storage/` and `tmp/` directories with 755 permissions
3. Create `public/uploads/` with 755 permissions
4. Ensure PHP 7.4+ is selected in cPanel
5. Create database backup via cPanel regularly

## Environment-Specific Configuration

Create `.env` file (if needed):

```env
DEBUG=0
DB_PATH=/path/to/storage/database.sqlite
SESSION_TIMEOUT=2592000
UPLOAD_MAX_SIZE=104857600
```

Load in `index.php`:

```php
// Load environment file
if (file_exists('.env')) {
    $lines = file('.env');
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
```

## Monitoring & Maintenance

### Log Files

Monitor these locations:

- PHP errors: `/var/log/php-errors.log`
- Web server errors: `/var/log/nginx/error.log` or `/var/log/apache2/error.log`
- Application logs: Created in storage directory

### Database Optimization

Regular maintenance:

```sql
-- SQLite optimization
VACUUM;
ANALYZE;
```

### Backup Strategy

1. **Automated Daily Backups**: Use cron job to backup database
2. **Version Control**: Push code to GitHub regularly
3. **Retention Policy**: Keep last 30 days of backups
4. **Test Restores**: Periodically test backup restoration

### Monitoring

Monitor:

- Disk space usage
- Database file size growth
- PHP error logs
- Server uptime
- SSL certificate expiration

## Troubleshooting

### Database Locked Error

```
sqlite3 database is locked
```

**Solution**: Ensure only one process accesses database at a time. Check for stuck processes.

### File Upload Fails

- Verify `public/uploads/` permissions (755)
- Check PHP `upload_max_filesize` setting
- Check available disk space

### CSS Not Loading

- Run `npm run build` again
- Check file permissions on `public/css/`
- Clear browser cache

### High Database Size

- Run `VACUUM;` in SQLite to compress
- Remove old backup entries
- Archive old files

## Performance Optimization

### Enable Caching

Add to `index.php`:

```php
$app->set('CACHE', 'folder=tmp/');
```

### Minify Assets

Already configured via Tailwind CLI with `--minify` flag.

### Enable Compression

Configured in web server (nginx/Apache examples above).

### Database Indexes

Already created in `database.php` for optimal query performance.

## Support

For deployment issues, open an issue on GitHub or consult the [Fat-Free Framework documentation](https://fatfreeframework.com/3.8/user-guide).
