# GitHub Setup Guide

This guide helps you push TinySpaces to GitHub and set up the repository properly.

## Prerequisites

- [Git](https://git-scm.com/) installed locally
- GitHub account created
- Git configured with your credentials

## Step 1: Create GitHub Repository

1. Go to [GitHub.com](https://github.com/new)
2. Create a new repository named `tinyspace`
3. Choose **Public** for open-source visibility
4. **Do NOT initialize** with README (we have one)
5. Click "Create Repository"

## Step 2: Configure Git Locally

```bash
# Navigate to project directory
cd c:\laragon\www\tinyspace

# Initialize git (if not already done)
git init

# Configure your Git identity (one-time setup)
git config user.name "Your Name"
git config user.email "your.email@example.com"
```

## Step 3: Add GitHub Remote

Replace `yourusername` with your actual GitHub username:

```bash
# Add GitHub as remote origin
git remote add origin https://github.com/yourusername/tinyspace.git

# Verify remote was added
git remote -v
```

## Step 4: Check .gitignore

The `.gitignore` file is already created and excludes:

- `node_modules/` - npm dependencies
- `vendor/` - composer dependencies
- `tmp/` - Fat-Free template cache
- `storage/database.sqlite` - user data
- `public/uploads/spaces/` - uploaded files
- `.env` - environment variables
- And other build artifacts

**Never commit:**

- Database files (auto-creates on first run)
- Sensitive credentials or API keys
- Build artifacts or cache files
- IDE/editor configuration

## Step 5: Initial Commit

```bash
# Stage all files for commit
git add .

# Create initial commit
git commit -m "Initial commit: TinySpaces file hosting application

- User authentication with remember-me
- File upload/download with space organization
- File tagging and sharing system
- Admin dashboard for user management
- Dark mode support with Alpine.js
- Tailwind CSS responsive design
- SQLite database with auto-migration
- Fat-Free Framework backend"

# Verify commit was created
git log --oneline -n 3
```

## Step 6: Push to GitHub

```bash
# Push main branch to GitHub
git branch -M main
git push -u origin main

# Verify push was successful
git remote -v
```

## Step 7: Set GitHub Repository Settings

1. Go to your repository on GitHub: `github.com/yourusername/tinyspace`

2. **Settings → Description**
   - Description: "A lightweight, open-source file hosting and sharing platform"
   - Website: Leave empty or add your deployment URL

3. **Settings → Topics**
   - Add: `file-hosting`, `php`, `fat-free-framework`, `alpine-js`, `tailwind-css`, `open-source`

4. **Settings → Social Preview**
   - Leave default or upload custom image

## Step 8: Enable Features

1. **Settings → Features**
   - [x] Discussions (for community)
   - [x] Issues (for bug reports)
   - [x] Projects (for task tracking)
   - [x] Wiki (for documentation)

2. **Settings → Pages** (Optional - for GitHub Pages)
   - Not needed for backend application

## Step 9: Configure Branch Protection (Optional)

1. **Settings → Branches → Add Rule**
   - Branch name: `main`
   - Require pull request reviews
   - Require status checks to pass

## Step 10: Add Collaborators (Optional)

1. **Settings → Collaborators → Add people**
   - Invite team members with appropriate permissions

## Subsequent Commits

```bash
# Make changes to files
# Then commit:
git add .
git commit -m "Clear, descriptive commit message"
git push origin main

# For feature branches:
git checkout -b feature/your-feature-name
# Make changes
git add .
git commit -m "Add new feature"
git push origin feature/your-feature-name
# Create Pull Request on GitHub
```

## Commit Message Best Practices

Use clear, descriptive messages:

```
# Good commit messages
git commit -m "Add file tagging system for spaces"
git commit -m "Fix toast notification timing on profile update"
git commit -m "Update README with deployment guide"
git commit -m "Refactor UserController API methods"

# Avoid vague messages
git commit -m "fix"          # ❌ Too vague
git commit -m "Update"      # ❌ No context
git commit -m "stuff"       # ❌ Not descriptive
```

## Version Tagging

When releasing new versions:

```bash
# Create version tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"

# Push tags to GitHub
git push origin --tags

# View all tags
git tag -l
```

Update `package.json` version and `CHANGELOG.md` before tagging.

## GitHub README

Your repository will automatically display `README.md` on the main page. It includes:

- ✅ Project description and features
- ✅ Tech stack overview
- ✅ Installation instructions
- ✅ Database schema documentation
- ✅ Deployment guide
- ✅ API endpoints reference
- ✅ Contributing guidelines
- ✅ License information

## GitHub Workflows

Automated GitHub Actions are configured in `.github/workflows/build.yml`:

- ✅ Tests on push/pull request
- ✅ PHP syntax checking
- ✅ Directory structure verification
- ✅ CSS build verification
- ✅ HTMX detection (ensures none exist)

Workflows run automatically when you push code.

## Useful GitHub Features

### Issues

- Create issues for bugs: Use bug report template
- Feature requests: Use feature request template
- Community can contribute fixes via pull requests

### Discussions

- Get feedback on ideas
- Answer user questions
- Build community around the project

### GitHub Pages (Optional)

If you want a project website:

```bash
# Create docs folder
mkdir docs
echo "# TinySpaces Documentation" > docs/index.md

# Push to GitHub
git add docs/
git commit -m "Add documentation"
git push origin main

# Enable in Settings → Pages → Source: main/docs folder
```

## Sharing Your Repository

Once published, share these links:

```
GitHub Repository: https://github.com/yourusername/tinyspace
Clone Command: git clone https://github.com/yourusername/tinyspace.git
GitHub Pages: https://yourusername.github.io/tinyspace (if enabled)
```

## Common Commands Reference

```bash
# Check status
git status

# View commit history
git log --oneline -n 10

# Create and switch to branch
git checkout -b feature-name

# Switch to branch
git checkout main

# Merge branch
git merge feature-name

# Delete branch
git branch -d feature-name

# Undo last commit (if not pushed)
git reset --soft HEAD~1

# View differences
git diff

# Stash changes temporarily
git stash
git stash pop
```

## Troubleshooting

### "fatal: not a git repository"

```bash
cd c:\laragon\www\tinyspace
git init
```

### "Permission denied" (SSH issues)

```bash
# Use HTTPS instead of SSH
git remote remove origin
git remote add origin https://github.com/yourusername/tinyspace.git
```

### Changes not showing on GitHub

```bash
# Verify remote is correct
git remote -v

# Force push (use with caution)
git push -u origin main --force
```

### Accidental commit of sensitive files

1. Never push credentials in commits
2. Use `.env` files with `.gitignore`
3. If already committed, use `git filter-branch` or contact GitHub support

## Next Steps

1. ✅ Create GitHub repository
2. ✅ Configure git locally
3. ✅ Push initial commit
4. ✅ Configure repository settings
5. ✅ Add topics and description
6. ✅ Enable features (Issues, Discussions)
7. ✅ Share repository with community
8. ✅ Monitor issues and pull requests

## Additional Resources

- [GitHub Guides](https://guides.github.com/)
- [Git Documentation](https://git-scm.com/doc)
- [Fat-Free Framework Docs](https://fatfreeframework.com/)
- [Contributing Guidelines](/.github/CONTRIBUTING.md)

## Support

For questions about GitHub specifically, check:

- [GitHub Help](https://help.github.com/)
- [GitHub Docs](https://docs.github.com/)

For TinySpaces issues, create an issue in your repository!

---

**Ready to deploy?** Check [DEPLOYMENT.md](DEPLOYMENT.md) for production setup.
