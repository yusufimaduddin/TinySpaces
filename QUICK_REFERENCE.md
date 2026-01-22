# Quick Reference Guide

## Project Commands

### Development

```bash
# Install dependencies
npm install

# Start development server (watch CSS changes)
npm run dev

# Build for production (minified CSS)
npm run build
```

### Verification

```bash
# Linux/Mac
bash setup-verify.sh

# Windows PowerShell
.\setup-verify.ps1
```

## Default Credentials

```
Username: admin
Email: admin@tinyspace.com
Password: admin123
```

**⚠️ IMPORTANT**: Change the default admin password immediately in production!

## File Structure Quick Reference

```
tinyspace/
├── app/
│   ├── controllers/     → Handle HTTP requests
│   ├── models/          → Data models (User, Space, File, Tag)
│   ├── views/           → HTML templates
│   └── database.php     → Database setup & queries
├── public/
│   ├── css/             → Compiled Tailwind CSS
│   ├── js/              → Frontend JavaScript
│   └── uploads/         → User uploaded files
├── src/
│   └── input.css        → Tailwind CSS source
├── storage/
│   └── database.sqlite  → SQLite database (auto-created)
├── index.php            → Application entry point
├── package.json         → Node.js dependencies
└── tailwind.config.js   → Tailwind configuration
```

## Key Routes

### Public Routes

- `GET /` → Redirect to login
- `GET /login` → Login page
- `POST /login` → Process login

### User Routes (Authenticated)

- `GET /user/dashboard` → List user spaces
- `GET /user/space/:id` → View space details
- `GET /user/profile` → User profile settings
- `GET /logout` → Logout user

### API Routes (Authenticated)

- `POST /api/spaces` → Create space
- `GET /api/spaces` → List spaces
- `PUT /api/spaces/:id` → Update space
- `DELETE /api/spaces/:id` → Delete space
- `POST /api/spaces/:id/upload` → Upload file
- `DELETE /api/spaces/:id/files/:file_id` → Delete file
- `POST /api/spaces/:id/tags` → Add/remove tags
- `POST /api/profile/update` → Update profile
- `POST /api/password/update` → Change password
- `POST /api/account/delete` → Delete account

### Admin Routes (Authenticated + Admin)

- `GET /admin/dashboard` → Admin panel
- `POST /admin/users` → Create new user

## Database Tables

### Core Tables

- `users` → User accounts and credentials
- `spaces` → File collections
- `files` → Individual files
- `tags` → Tag definitions
- `space_tags` → Space-to-tag relationships
- `space_access` → Shared space access

### Important Indexes

- `users.username`, `users.email` → Fast user lookup
- `spaces.owner_id` → Fast space filtering
- `files.space_id` → Fast file listing
- `space_tags.tag_id` → Fast tag searching

## Common Tasks

### Change Admin Password

1. Login as admin
2. Go to `/user/profile`
3. Use "Change Password" form

### Create New Admin User

1. Login as admin
2. Go to `/admin/dashboard`
3. Fill in user form (role: admin)
4. Share new credentials securely

### Upload New Files

1. Go to `/user/dashboard`
2. Click on a space
3. Click "Upload Files"
4. Select files (max 100MB each)

### Share a Space

1. Open space from dashboard
2. Click "Share" button
3. Select users to share with

### Backup Database

```bash
cp storage/database.sqlite storage/backup-$(date +%Y%m%d-%H%M%S).sqlite
```

### Reset to Fresh State

```bash
# Delete database (auto-recreates on next load)
rm storage/database.sqlite

# Clear template cache
rm -rf tmp/*

# Reinstall dependencies
npm install && npm run build
```

## Troubleshooting Commands

### Check PHP Version

```bash
php -v
```

### Check Node.js

```bash
node -v
npm -v
```

### Test Database Connection

Access `/` and check if database auto-creates

### View PHP Errors

```bash
tail -f /var/log/php-errors.log
```

### Check Nginx Errors

```bash
tail -f /var/log/nginx/error.log
```

### Clear Browser Cache

- Chrome: Ctrl+Shift+Delete
- Firefox: Ctrl+Shift+Delete
- Safari: Cmd+Shift+Delete

## Environment Checklist

- [ ] PHP 7.4+ installed
- [ ] Node.js 14+ installed
- [ ] SQLite support enabled in PHP
- [ ] `storage/` folder exists and is writable
- [ ] `public/uploads/` folder exists and is writable
- [ ] `tmp/` folder exists and is writable
- [ ] npm dependencies installed (`npm install`)
- [ ] CSS compiled (`npm run build`)
- [ ] Web server running on correct port
- [ ] Domain/URL accessible

## Security Checklist

- [ ] Default admin password changed
- [ ] HTTPS/SSL enabled (production)
- [ ] DEBUG mode disabled (`DEBUG = 0`)
- [ ] Regular database backups scheduled
- [ ] File permissions set correctly (755 folders, 644 files)
- [ ] Storage folder access restricted
- [ ] Sensitive files excluded from git (.gitignore)

## Performance Tips

- Run `npm run build` (minified CSS)
- Enable gzip compression on web server
- Set up database backups
- Monitor disk space usage
- Clear old template cache periodically
- Use CDN for static assets (optional)
- Enable browser caching headers

## Documentation Files

- `README.md` → Main documentation
- `DEPLOYMENT.md` → Production deployment guide
- `CHANGELOG.md` → Version history and updates
- `.github/CONTRIBUTING.md` → Contribution guidelines
- `.github/CODE_OF_CONDUCT.md` → Community standards

## Getting Help

1. Check documentation files
2. Review error logs
3. Check browser console for JavaScript errors
4. Open issue on GitHub with:
   - Error message
   - Steps to reproduce
   - PHP/browser version
   - Screenshots if applicable

---

**Last Updated**: January 2024
**Current Version**: 1.0.0
