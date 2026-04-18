# Contributing to LibreMesh

## Versioning & Releases

LibreMesh uses semantic versioning with pre-release suffixes (e.g., `0.1.0-alpha.1`).

Releases are triggered by git tags matching the pattern `v*` (e.g., `v0.1.0-alpha.1`).

### Cutting a Release

1. **Update `VERSION` file** with the new version (e.g., `0.1.0-alpha.2`)
2. **Update `CHANGELOG.md`**:
   - Add new `[version]` entry at top
   - Move unreleased items to the new version section
   - Add date in `YYYY-MM-DD` format
3. **Commit changes**: `git add . && git commit -m "release: v0.1.0-alpha.2"`
4. **Create and push tag**: `git tag v0.1.0-alpha.2 && git push --tags`

The release workflow will:
- Build and push Docker images to GitHub Container Registry (GHCR)
- Create a GitHub Release with the CHANGELOG content

## Commit Convention

Commits must follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

Examples:
- `feat(api): add upload_chunk endpoint`
- `fix(cron): correct peer selection logic`
- `docs: update README with new instructions`

This is enforced by husky pre-commit hooks.

## Branch Strategy

- `main` branch: stable, always deployable
- Feature branches: `feat/*`, `fix/*`, `docs/*`
- All PRs must pass CI before merging to `main`

## CI Pipeline

GitHub Actions runs on every push and PR:
- PHP syntax check on all `.php` files
- Integration tests via Docker Compose

## Getting Help

Open an issue for bugs, feature requests, or questions.