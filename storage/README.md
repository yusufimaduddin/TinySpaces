# Storage Directory

This directory contains persistent application data.

## Contents

- `database.sqlite` - SQLite database file (created automatically on first run)
- `database.sqlite-journal` - SQLite journal file (temporary, can be deleted)
- `backups/` - Database backup files (optional)

## Notes

- The database is **excluded from git** via `.gitignore`
- Each installation creates its own database
- First run automatically creates tables and default admin user
- This folder must be **writable** by the web server

## Permissions

Ensure this folder has write permissions:

```bash
# Linux/Mac
chmod 755 storage

# Or more permissive if needed
chmod 777 storage
```

## Backup Instructions

Create regular backups:

```bash
# Simple copy backup
cp storage/database.sqlite storage/backup-$(date +%Y%m%d-%H%M%S).sqlite

# Automated daily backup (add to crontab)
0 2 * * * cp /path/to/storage/database.sqlite /path/to/storage/backups/db-$(date +\%Y\%m\%d).sqlite
```

## Database Recovery

If database is corrupted:

1. Stop the application
2. Delete `storage/database.sqlite`
3. Restart application (auto-creates fresh database)
4. Re-import data from backup if needed

## Maintenance

Periodic maintenance tasks:

```sql
-- Run these commands periodically in SQLite
VACUUM;          -- Compress database
ANALYZE;         -- Update statistics
PRAGMA integrity_check; -- Check integrity
```

See [README.md](../README.md) for more information.
