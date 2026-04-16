# Ship Command

Execute a release: analyze commits since last tag, bump version, update changelog, create tag and push.

## Usage

```
/ship
```

## Behavior

1. Check if on `main` branch and working tree is clean
2. Find last git tag: `git describe --tags --abbrev=0`
3. Get commits since last tag: `git log --oneline <last-tag>..HEAD`
4. Categorize commits: `feat:` → Added, `fix:` → Fixed, `docs:` → Changed, etc.
5. Determine version bump:
   - Any `BREAKING CHANGE:` → major
   - Any `feat:` → minor
   - Any `fix:` → patch
   - Alpha versions preserve suffix
6. Update `VERSION` file with new version
7. Update `CHANGELOG.md` - add new `[version] - YYYY-MM-DD` section at top
8. Stage and commit: `git add VERSION CHANGELOG.md && git commit -m "release: v{version}"`
9. Create tag: `git tag v{version}`
10. Push: `git push && git push --tags`
11. Report what was done and that GitHub Actions will handle the rest