# TinySpaces
![TinySpaces Capture](https://i.ibb.co.com/HL87JLwk/tinyspace.jpg)
[![License: ISC](https://img.shields.io/badge/License-ISC-yellow.svg)](https://opensource.org/licenses/ISC)

A lightweight, open-source file hosting and sharing platform built with PHP Fat-Free Framework, Alpine.js, and Tailwind CSS.

---

**📢 Important Notice**

If anyone has already built a project like this with newer, faster, and more efficient technology that can be cross-platform (built with PHP, JS, etc.), please feel free to share. I'm very much looking forward to that development.

---

**📅 Latest Update (12 November 2026)**

**File List & Download**
- Tidied the space file list: groups and files are sorted alphabetically (numeric-aware), grouped files are indented, and file-type icons are shown.
- `README.md` is no longer duplicated in the regular file list or file count (it keeps its own preview section).
- Clicking a file name now opens it when possible: text/code files open in the editor, images/PDF/audio/video are previewed inline, and unsupported types are downloaded automatically using the original filename.

**Editor**
- The editor now supports text and code files (HTML, CSS, JS, PHP, JSON, YAML, XML, SQL, scripts, extensionless files like `Dockerfile`, etc.), not just Markdown.
- The Markdown preview is only shown for `.md` files; code files use the full editor width.
- Added Tab indentation, `Ctrl+S` to save, and an unsaved-changes warning.

**Server & Downloads**
- Fixed `Content-Disposition` headers so downloads and inline previews work correctly and preserve the original filename (including Unicode characters and spaces).
- Raw text files are served as `text/plain` so editing HTML/XML is not mistaken for a session-expired HTML response.
- Only previewable MIME types/extensions are sent inline; everything else is sent as an attachment.
- Added `Content-Length` from the actual file, `X-Content-Type-Options: nosniff`, and blocked saving non-text files as text.

**Development**
- Added `router.php` so the app can run locally with the PHP built-in server while still serving CSS/JS assets.

**Security**
- Uploaded files are now stored with a neutral `.dat` extension and are served only through the app's authenticated endpoints; direct access to `public/uploads` is denied.
- Root `.htaccess` and `router.php` now block `storage/`, `tmp/`, `.git/`, `composer.json`, and other sensitive paths.
- Debug output is off by default; enable it locally with `APP_DEBUG=1`.
- Rendered Markdown is sanitized with DOMPurify to prevent stored XSS through README content.
- File deletion now verifies that the file belongs to the space in the request.
- Added `scripts/migrate-uploads.php` to move legacy uploads to `.dat`.
- Documented PHP2Shell (CVE-2026-109618 / CVE-2026-109619) remediation and the `session.upload_progress.enabled=0` mitigation.

---

**📅 Previous Update (26 March 2026)**

**Bug Fixes:**
- Fixed upload and delete file issues for restricted users in spaces with limited access (role/permission-based restrictions)
- Resolved permission validation where users with restricted status were incorrectly able to upload or delete files
- Enhanced access control checks to properly enforce space restrictions

**Update Details:**
- Improved authorization logic for file operations in shared spaces
- Fixed edge cases where users with revoked access could still modify files
- Added additional validation layers for file upload and delete endpoints

---

**TinySpaces** continues to be maintained with these improvements. For any questions or contributions, please open an issue on GitHub.

---

## 🎯 Features

### User Management
- ✅ User authentication with email and password
- ✅ User registration and login
- ✅ Remember me functionality (30 days)
- ✅ Profile management (update username, change password)
- ✅ Account deletion with password confirmation
- ✅ Role-based access control (Admin/User)

### File Management
- ✅ Create and manage spaces (file collections)
- ✅ Upload files to spaces (max 100MB per file)
- ✅ Open or download files: text/code files open in the editor, previewable media opens inline, and other types download with the original filename
- ✅ Smart file list: grouped files, alphabetical sorting, file-type icons, and README kept separate
- ✅ Assign a group name to each selected file before uploading
- ✅ Delete files with owner verification
- ✅ **Text & Code Editor**: Edit Markdown, plain text, and source-code files with a comfortable full-width editor **(new)**
- ✅ **Markdown Preview**: Real-time preview and synchronized scrolling for Markdown files only **(new)**
- ✅ Auto-generated README.md per space
- ✅ File metadata tracking (size, type, upload date)

### Space Management
- ✅ Create spaces with custom icons
- ✅ Set space status (published/private/archive)
- ✅ Add tags to spaces for organization
- ✅ Share spaces with other users
- ✅ Filter and search spaces
- ✅ Edit space details and README content
- ✅ **Review Mode**: Read-only public link access toggle **(new)**
- ✅ **Recently Modified**: Quick access to most recently updated spaces **(new)**
- ✅ **Auto Tag Cleanup**: Automatic removal of unused tags **(new)**

### UI/UX
- ✅ Dark mode support
- ✅ Responsive design (mobile-first)
- ✅ Toast notifications for user feedback
- ✅ Smooth transitions and animations
- ✅ Real-time relative timestamps (e.g., "5 minutes ago")

### Admin Features
- ✅ Admin dashboard
- ✅ User management (create new users)
- ✅ View all registered accounts

## 🛠️ Tech Stack

### Backend
- **Framework**: [Fat-Free Framework](https://fatfreeframework.com/) 3.8+
- **Database**: SQLite 3 (file-based, no setup needed)
- **Language**: PHP 8.2+ (use a PHP2Shell-patched release)
- **Session Management**: Native PHP sessions with remember tokens

### Frontend
- **JS Framework**: [Alpine.js](https://alpinejs.dev/) 3.15+
- **CSS Framework**: [Tailwind CSS](https://tailwindcss.com/) 4.1+
- **Icons**: [Boxicons](https://boxicons.com/) 2.1+
- **Date/Time**: [Day.js](https://day.js.org/) 1.11+ (relative time formatting)
- **Markdown**: [Marked.js](https://marked.js.org/) (loaded via CDN)

### Development
- **Build Tool**: Tailwind CSS CLI
- **Package Manager**: npm
- **Template Engine**: Fat-Free Framework native template

## 📋 Prerequisites

- PHP 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+ with `pdo_sqlite`/`sqlite3`, `mbstring`, and `fileinfo` extensions (patched against PHP2Shell)
- Node.js 14+ (for Tailwind CSS build)
- npm or yarn
- SQLite support (usually built into PHP)
- Composer (optional, for dependency management)

## 🚀 Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/yusufimaduddin/TinySpaces
cd TinySpaces
```

### 2. Install Dependencies
```bash
# Install frontend dependencies
npm install

# Composer is optional (already included via autoload)
composer install
```

### 3. Configure Environment
```bash
# No .env needed - SQLite database auto-creates in storage/
# Database path: storage/database.sqlite
```

### 4. Build CSS
```bash
# Development (watch mode)
npm run dev

# Production (minified)
npm run build
```

### 5. Run the Application

**Using PHP Built-in Server (recommended for local development):**
```bash
php -S localhost:8000 router.php
```

Then visit: `http://localhost:8000`

> `router.php` serves static assets (CSS, JS, images, `node_modules`) directly and forwards application routes to `index.php`. Without it, pages load but assets return 404.

> Debug is off by default. For local development, enable it before starting the server:
> ```powershell
> $env:APP_DEBUG=1
> php -S localhost:8000 router.php
> ```

**Using Laragon/XAMPP:**
- Point the document root / VirtualHost to the project folder, for example `D:\Web Projects\space\TinySpaces`
- Enable `mod_rewrite` and `AllowOverride All`
- Access via `http://localhost` or a VirtualHost such as `http://tinyspaces.test`

> The app uses absolute asset paths (`/public/...` and `/node_modules/...`), so it must be served from the domain root — not from a subfolder like `http://localhost/tinyspace`.

### 6. Login with Demo Account
```
Username: admin
Password: admin123
Email: admin@tinyspace.com
```

## 📁 Project Structure

```
tinyspace/
├── app/
│   ├── controllers/          # Request handlers
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── SpaceController.php
│   │   └── UserController.php
│   ├── models/               # Data models
│   │   ├── User.php
│   │   ├── Space.php
│   │   ├── File.php
│   │   └── Tag.php
│   ├── views/                # HTML templates
│   │   ├── auth/login.html
│   │   ├── user/
│   │   │   ├── dashboard.html
│   │   │   ├── profile.html
│   │   │   ├── space.html
│   │   │   ├── editor.html
│   │   │   └── review.html
│   │   ├── admin/dashboard.html
│   │   └── error.html
│   └── database.php          # Database initialization & schema
├── public/
│   ├── css/
│   │   ├── style.css         # Compiled Tailwind CSS
│   │   └── prose.css         # Markdown styling
│   ├── js/
│   │   ├── app.js            # Global Alpine store setup
│   │   └── space.js          # Space page logic
│   ├── images/
│   └── uploads/
│       ├── .htaccess         # Denies direct access to uploads
│       └── spaces/           # User uploaded files
├── src/
│   └── input.css             # Tailwind input file
├── storage/
│   └── database.sqlite       # SQLite database (auto-created)
├── tmp/                       # Fat-Free cache files
├── vendor/                    # Composer dependencies
├── index.php                  # Application entry point
├── router.php                 # Dev router for the PHP built-in server
├── scripts/
│   └── migrate-uploads.php    # One-time migration for legacy uploads
├── package.json              # Node.js dependencies
├── tailwind.config.js        # Tailwind configuration
└── README.md                 # This file
```

## 🗄️ Database Schema

### Users Table
```sql
- id (VARCHAR 12, PK)
- username (VARCHAR 50, UNIQUE)
- email (VARCHAR 100, UNIQUE)
- password_hash (VARCHAR 255)
- role (VARCHAR 20: 'admin'|'user')
- remember_token (VARCHAR 255, legacy/deprecated)
- token_expires_at (TIMESTAMP, legacy/deprecated)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### Remember Tokens Table
One row per remembered device. Only a SHA-256 hash of the cookie token is stored.
```sql
- id (VARCHAR 12, PK)
- user_id (FK -> users.id)
- token_hash (VARCHAR 64, UNIQUE)
- expires_at (TIMESTAMP)
- created_at (TIMESTAMP)
- last_used_at (TIMESTAMP, nullable)
- user_agent (TEXT, nullable)
```

### Spaces Table
```sql
- id (VARCHAR 12, PK)
- name (VARCHAR 100)
- description (TEXT)
- status (VARCHAR: 'published'|'private'|'archive')
- class_icon (VARCHAR for Boxicons class)
- review_mode (INTEGER 0|1)
- owner_id (FK -> users.id)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### Files Table
```sql
- id (VARCHAR 12, PK)
- space_id (FK -> spaces.id)
- original_name (VARCHAR 255)
- file_path (TEXT)
- mime_type (VARCHAR 100)
- file_size (INTEGER bytes)
- uploaded_by (FK -> users.id)
- uploaded_at (TIMESTAMP)
- updated_at (TIMESTAMP)
- group_name (VARCHAR 100, nullable)
```

### Tags & Space Tags
```sql
tags:
- id (VARCHAR 12, PK)
- name (VARCHAR 50, UNIQUE)
- created_at (TIMESTAMP)

space_tags:
- space_id (FK)
- tag_id (FK)
- PRIMARY KEY (space_id, tag_id)
```

### Space Access (Sharing)
```sql
- id (VARCHAR 12, PK)
- space_id (FK -> spaces.id)
- user_id (FK -> users.id)
- granted_at (TIMESTAMP)
- UNIQUE (space_id, user_id)
```

## 🔐 Authentication

### Login Flow
1. User enters username/email and password
2. System validates credentials using bcrypt hashing
3. Session created with user data (id, username, email, role)
4. Optional: remember-me cookie set for 30 days (a hashed token is stored in `remember_tokens`)
5. User redirected based on role (admin/user dashboard)

### Password Security
- Bcrypt hashing with PASSWORD_DEFAULT algorithm
- Minimum 8 characters required
- Passwords never stored in plain text
- Password change requires current password verification

### Authorization
- Role-based middleware checks (admin/user)
- Space access control (owner or shared with user)
- Owner verification on file/space operations

## 🛡️ Security

### PHP2Shell (CVE-2026-109618 / CVE-2026-109619)

PHP2Shell is a family of critical memory-safety vulnerabilities in **core PHP** (CVSS 9.2, unauthenticated RCE). It affects every web-serving PHP runtime — PHP-FPM, Apache `mod_php`, `php-cgi`, FrankenPHP, LiteSpeed — even when the application code itself is secure. Updating this application does **not** update the PHP runtime underneath it.

Use a patched PHP release and restart the web server / PHP workers afterwards:

| PHP branch | Minimum safe version |
|---|---|
| 8.5 | 8.5.12+ |
| 8.4 | 8.4.27+ |
| 8.3 | 8.3.36+ |
| 8.2 | 8.2.35+ |

PHP also recommends keeping `session.upload_progress.enabled=0` if you do not need upload progress; it blocks the related session upload-progress vector described in **CVE-2026-109619** (`GHSA-6wmf-4rv5-p3rp`). If you cannot upgrade immediately, the advisory also suggests `fatal_error_backtraces=0` (PHP 8.5) and an administratively enforced `memory_limit=-1`; these are partial mitigations only, not a replacement for the patched runtime.

```ini
session.upload_progress.enabled = 0
```

### Hardening Checklist

- [ ] Run a PHP2Shell-patched PHP release (see table above).
- [ ] Set `session.upload_progress.enabled=0`.
- [ ] Set `display_errors=0`, `display_startup_errors=0`, `expose_php=0`, and `fatal_error_backtraces=0` in production.
- [ ] Leave `APP_DEBUG` unset in production; enable it only for local development.
- [ ] Keep `storage/`, `tmp/`, `.git/`, and other sensitive paths blocked by the web server (the bundled `.htaccess` and `router.php` do this).
- [ ] Serve uploaded files only through the authenticated view/download endpoints. Uploads are stored with a neutral `.dat` extension, and `public/uploads/.htaccess` denies direct access.
- [ ] Use HTTPS and secure session cookies in production.

> The bundled `.htaccess` rules require Apache `AllowOverride All` (or equivalent). If you use Nginx, replicate the same blocks in the server configuration: deny `/storage`, `/tmp`, `/.git`, and `/public/uploads`, and serve only `/public` and `/node_modules` statically.

## 🔌 API Endpoints

### Authentication
- `GET /login` - Login page
- `POST /login` - Process login
- `GET /logout` - Logout user

### User Profile
- `GET /user/profile` - Profile settings page
- `POST /api/profile/update` - Update username
- `POST /api/password/update` - Change password
- `POST /api/account/delete` - Delete account

### Spaces
- `GET /user/dashboard` - List user spaces
- `GET /user/space/:id` - View space details
- `POST /api/spaces` - Create new space
- `GET /api/spaces` - List spaces (with filters)
- `GET /api/spaces/:id` - Get space details
- `PUT /api/spaces/:id` - Update space
- `DELETE /api/spaces/:id` - Delete space
- `POST /api/spaces/:id/share` - Share space
- `POST /api/spaces/:id/review-mode` - Toggle review mode
- `PUT /api/spaces/:id/readme` - Update README
- `GET /api/spaces/recent` - Get 3 most recently updated spaces
- `GET /user/review/:id` - Read-only space view

### Files
- `POST /api/spaces/:id/upload` - Upload file
- `GET /api/spaces/:space_id/files/:file_id/download` - Download file (always as attachment, original filename preserved)
- `GET /api/spaces/:space_id/files/:file_id/view` - View file (text/code redirects to the editor; media previews inline; other types download)
- `GET /api/spaces/:space_id/files/:file_id/view?raw=1` - Get raw text content as `text/plain`
- `PUT /api/spaces/:space_id/files/:file_id` - Save new content for a text/code file
- `DELETE /api/spaces/:space_id/files/:file_id` - Delete file

### File Groups
- `POST /api/spaces/:id/files/merge` - Add files to a group (or remove them from a group with `group_name: null`)
- `POST /api/spaces/:id/groups/release` - Remove all files from a group without deleting them
- `DELETE /api/spaces/:id/groups` - Delete all files in a group
- `GET /api/spaces/:id/groups/download?name=...` - Download a group as a ZIP file

### Tags
- `POST /api/spaces/:id/tags` - Add/remove tags

### Admin
- `GET /admin/dashboard` - Admin panel
- `POST /admin/users` - Create new user

## 🎨 Customization

### Change Tailwind Theme
Edit `tailwind.config.js`:
```javascript
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: '#your-color',
      }
    }
  }
}
```

### Add Custom Icons
1. Choose from [Boxicons](https://boxicons.com/)
2. Use in space creation form
3. Available classes: `bx-folder`, `bx-file`, `bx-image`, etc.

### Modify Database Schema
Edit `app/database.php` in `createTables()` method and run again.

## 📝 Development Workflow

### CSS Development
```bash
npm run dev
# Watches for changes in src/input.css and app/views
```

### Production Build
```bash
npm run build
# Minifies CSS for production
```

### File Upload Limits
- App limit: 100MB per file
- Effective limit: the smallest of the app limit, PHP `upload_max_filesize`, and `post_max_size`
- Upload directory: `public/uploads/spaces/{space_id}/`
- MIME type detection: automatic
- Physical files use a neutral `.dat` extension; the original name and MIME type are stored in the database

> The UI shows the effective limit. Many PHP installations default to `upload_max_filesize=2M` and `post_max_size=8M`; raise both in `php.ini` if you need larger uploads.

## 🐛 Troubleshooting

### Database Not Creating
- Ensure `storage/` folder is writable: `chmod 755 storage/`
- Check PHP SQLite extension is enabled

### Files Not Uploading
- Verify `public/uploads/` folder exists and is writable
- Check server max upload size in `php.ini`
- Ensure disk space is available

### CSS Not Loading
- Run `npm install` and `npm run build`
- Check `public/css/style.css` exists
- Clear browser cache
- With the PHP built-in server, make sure you run `php -S localhost:8000 router.php` so assets are served correctly

### Routes or Assets Return 404
- Start the PHP built-in server with the router: `php -S localhost:8000 router.php`
- Run the command from the project root folder
- For Apache/Laragon/XAMPP, set the document root to the project folder and enable `mod_rewrite`

### File Opens in the Wrong Way
- Text and code files open in the editor
- Images, PDF, audio, and video preview inline
- All other file types are downloaded automatically with the original filename
- If a text file downloads instead of opening, verify its extension or MIME type is detected as text

### Database, .git, or Uploads Accessible via URL
- Enable Apache `AllowOverride All` so the bundled `.htaccess` rules apply
- With the PHP built-in server, always run `php -S localhost:8000 router.php`
- On Nginx, add equivalent deny rules for `/storage`, `/tmp`, `/.git`, and `/public/uploads`
- Never serve the project root with a generic static file server

### Uploaded PHP File Executes
- New uploads are stored with a neutral `.dat` extension and cannot be executed
- Migrate legacy uploads with `php scripts/migrate-uploads.php --dry-run`, then run without `--dry-run`
- Ensure `public/uploads/.htaccess` exists and `AllowOverride All` is enabled
- Disable PHP execution in the uploads directory at the web-server level

### Remember Token Not Working
- Check cookies are enabled in browser
- Verify `php.ini` session settings
- Check the `remember_tokens.expires_at` and `remember_tokens.token_hash` fields
- Expired tokens are removed automatically when a new token is created

## 📦 Dependencies

### Production
- `alpinejs@3.15.4` - Lightweight reactive UI
- `boxicons@2.1.4` - Icon library
- `@tailwindcss/cli@4.1.18` - CSS framework
- `marked` (CDN) - Markdown rendering
- `dompurify@3.2.6` (CDN) - Sanitizes rendered Markdown

### Development
- `tailwindcss@4.1.18` - CSS framework
- `autoprefixer@10.4.23` - CSS vendor prefixes
- `postcss@8.5.6` - CSS processor

## 🚀 Deployment

### Deployment Checklist
- [ ] Upgrade PHP to a PHP2Shell-patched release (8.5.12+ / 8.4.27+ / 8.3.36+ / 8.2.35+)
- [ ] Set `session.upload_progress.enabled=0` in `php.ini`
- [ ] Set `display_errors=0`, `display_startup_errors=0`, `expose_php=0`, `fatal_error_backtraces=0`
- [ ] Run `php scripts/migrate-uploads.php` once to move legacy uploads to `.dat`
- [ ] Leave `APP_DEBUG` unset (debug is off by default)
- [ ] Run `npm install && npm run build`
- [ ] Set correct file permissions (644 files, 755 folders)
- [ ] Ensure `storage/` and `public/uploads/` are writable
- [ ] Verify `/storage`, `/.git`, and `/public/uploads` are not directly accessible
- [ ] Configure proper session security in `php.ini`
- [ ] Use HTTPS in production
- [ ] Set secure cookie flags for remember tokens

### Hosting Requirements
- PHP 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+ with SQLite support (patched against PHP2Shell)
- Write permissions on storage/ and public/uploads/
- Optional: Node.js for CSS builds

## 🌟 Latest Updates

### November 2026
- **Group on Upload**: Each file in the upload selection list now has an optional group-name field, so grouped files can be uploaded directly (existing group names are suggested).
- **Login Without "Remember Me"**: Login now works even when the checkbox is unchecked. A browser-session token is issued, and PHP sessions fall back to `storage/sessions` when the host's session directory is unusable.
- **File List Cleanup**: Groups and files are sorted alphabetically (numeric-aware), grouped files are indented, file-type icons are shown, and `README.md` is excluded from the regular list/count.
- **Smart Open/Download**: Clicking a filename opens text/code files in the editor, previews images/PDF/audio/video inline, and downloads other types with the original filename.
- **Text & Code Editor**: The editor now handles source-code and plain-text files, not just Markdown.
- **Markdown-Only Preview**: The preview pane is hidden for non-Markdown files, giving code files the full editor width.
- **Editor Comfort**: Added Tab indentation, `Ctrl+S` save, and an unsaved-changes warning.
- **Download Header Fix**: Corrected `Content-Disposition` generation so downloads and inline previews work and preserve original filenames (including Unicode/spaces).
- **Raw Text Serving**: Raw text is returned as `text/plain`, preventing HTML/XML edits from being mistaken for session-expired responses.
- **Safer File Responses**: Added accurate `Content-Length`, `X-Content-Type-Options: nosniff`, and blocked saving binary files as text.
- **Local Development**: Added `router.php` for running the app with the PHP built-in server while serving static assets.

### Jan 2026
- **Markdown Editor**: Implemented a built-in Markdown editor with real-time preview.
- **Editor Synchronization**: Added real-time synchronized scrolling (50% editor = 50% preview) with a switchable toggle.
- **Enhanced Review Mode**: Added strict access control and a toggleable public link.
- **Dashboard Refresh**: Added "Recently Modified" section and improved sorting (oldest created first).
- **Date Formatting**: Unified date display to `DD MMM YYYY` format.
- **Tag Management**: Integrated automatic cleanup for orphaned tags.
- **Auth Fix**: Resolved database initialization issues for custom usernames.
- **UI Polishing**: Improved modal transitions and button styles.

## 📄 License

This project is licensed under the ISC License - see [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📧 Contact

For questions and support, please open an issue on GitHub.

## 🙏 Acknowledgments

- [Fat-Free Framework](https://fatfreeframework.com/) - Lightweight PHP framework
- [Alpine.js](https://alpinejs.dev/) - Lightweight JavaScript framework
- [Tailwind CSS](https://tailwindcss.com/) - Utility-first CSS framework
- [Boxicons](https://boxicons.com/) - High-quality SVG icons

---

Made with ❤️ for the web community
