# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial project setup and structure

### Changed

### Deprecated

### Removed

### Fixed

### Security

## [1.0.0] - 2024-01-01

### Added

- User authentication system with remember-me functionality
- User profile management (update profile, change password, delete account)
- Admin dashboard for user management
- Space creation and management
- File upload/download functionality with size tracking
- File tagging system with search capabilities
- Space sharing with other users
- README.md auto-generation and editing for spaces
- Dark mode support
- Toast notifications for user feedback
- Responsive design for mobile and desktop
- SQLite database with auto-migration
- Fat-Free Framework integration
- Alpine.js reactive components
- Tailwind CSS styling
- Boxicons integration
- Day.js relative timestamp formatting

### Changed

- Initial release

## How to Update This File

When making changes, add a new section following this format:

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added

- New feature 1
- New feature 2

### Changed

- Modified behavior 1
- Modified behavior 2

### Deprecated

- Soon-to-be removed feature 1

### Removed

- Removed feature 1

### Fixed

- Bug fix 1
- Bug fix 2

### Security

- Security fix 1
```

**Categories**:

- **Added**: New features
- **Changed**: Changes in existing functionality
- **Deprecated**: Soon-to-be removed features
- **Removed**: Now removed features
- **Fixed**: Bug fixes
- **Security**: Security vulnerability fixes

Always update the version number in:

1. `package.json`
2. This `CHANGELOG.md`
3. Git tags (for releases)

Example:

```bash
git tag -a v1.0.1 -m "Version 1.0.1 - bug fixes"
git push origin v1.0.1
```
