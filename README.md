# TinySpaces
![TinySpaces Capture](https://i.ibb.co.com/HL87JLwk/tinyspace.jpg)
[![License: ISC](https://img.shields.io/badge/License-ISC-yellow.svg)](https://opensource.org/licenses/ISC)

A lightweight, open-source file hosting and sharing platform built with PHP Fat-Free Framework, Alpine.js, and Tailwind CSS.

---

**📢 Important Notice**

If anyone has already built a project like this with newer, faster, and more efficient technology that can be cross-platform (built with PHP, JS, etc.), please feel free to share. I'm very much looking forward to that development.

---

**📅 Latest Update (26 March 2026)**

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
- ✅ Download and view files
- ✅ Delete files with owner verification
- ✅ **Markdown Editor**: Full-featured editor with real-time preview **(new)**
- ✅ **Synchronized Scrolling**: Real-time sync between editor and preview **(new)**
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
- **Language**: PHP 7.4+
- **Session Management**: Native PHP sessions with remember tokens

### Frontend
- **JS Framework**: [Alpine.js](https://alpinejs.dev/) 3.15+
- **CSS Framework**: [Tailwind CSS](https://tailwindcss.com/) 4.1+
- **Icons**: [Boxicons](https://boxicons.com/) 2.1+
- **Date/Time**: [Day.js](https://day.js.org/) 1.11+ (relative time formatting)
- **Markdown**: [Marked.js](https://marked.js.org/)

### Development
- **Build Tool**: Tailwind CSS CLI
- **Package Manager**: npm
- **Template Engine**: Fat-Free Framework native template

## 📋 Prerequisites

- PHP 7.4 or higher
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

**Using Laragon/XAMPP:**
- Place folder in `htdocs/` or `www/`
- Access via `http://localhost/tinyspace`

**Using PHP Built-in Server:**
```bash
php -S localhost:8000 -t .
```

Then visit: `http://localhost:8000`

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
│   │   │   └── space.html
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
│   └── uploads/spaces/       # User uploaded files
├── src/
│   └── input.css             # Tailwind input file
├── storage/
│   └── database.sqlite       # SQLite database (auto-created)
├── tmp/                       # Fat-Free cache files
├── vendor/                    # Composer dependencies
├── index.php                  # Application entry point
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
- remember_token (VARCHAR 255)
- token_expires_at (TIMESTAMP)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
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
4. Optional: Remember token cookie set for 30 days
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
- `GET /api/spaces/:space_id/files/:file_id/download` - Download file
- `GET /api/spaces/:space_id/files/:file_id/view` - View file
- `DELETE /api/spaces/:space_id/files/:file_id` - Delete file

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
- Max file size: 100MB per file
- Upload directory: `public/uploads/spaces/{space_id}/`
- MIME type detection: automatic

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

### Remember Token Not Working
- Check cookies are enabled in browser
- Verify `php.ini` session settings
- Check database token_expires_at field

## 📦 Dependencies

### Production
- `alpinejs@3.15.4` - Lightweight reactive UI
- `boxicons@2.1.4` - Icon library
- `@tailwindcss/cli@4.1.18` - CSS framework

### Development
- `tailwindcss@4.1.18` - CSS framework
- `autoprefixer@10.4.23` - CSS vendor prefixes
- `postcss@8.5.6` - CSS processor

## 🚀 Deployment

### Deployment Checklist
- [ ] Run `npm install && npm run build`
- [ ] Set correct file permissions (644 files, 755 folders)
- [ ] Ensure `storage/` and `public/uploads/` are writable
- [ ] Set `DEBUG` level to 0 in `index.php` (production)
- [ ] Configure proper session security in `php.ini`
- [ ] Use HTTPS in production
- [ ] Set secure cookie flags for remember tokens

### Hosting Requirements
- PHP 7.4+ with SQLite support
- Write permissions on storage/ and public/uploads/
- Optional: Node.js for CSS builds

## 🌟 Latest Updates

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
