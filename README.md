# WP Atomic Design Framework

A modular WordPress plugin that provides small, focused tools aligned with Atomic Design workflows.

## Features
- Dev status taxonomy (hidden) to tag Pages for internal tracking
- Option to hide the Author column from the Pages list
- Lightweight GitHub-driven auto-update via `update.json`

## Requirements
- WordPress `>= 5.8`
- PHP `>= 7.4`

## Installation
- Download the latest ZIP: `https://github.com/AtomicDesignBelgium/wp-atomic-design-framework/releases/latest/download/wp-atomic-design-framework.zip`
- In WordPress admin: `Plugins` → `Add New` → `Upload Plugin` → select the ZIP → `Install` → `Activate`

## Settings
- Navigate to `Settings` → `WP Atomic Design`
- Options:
  - `Enable Dev Tags`: registers a hidden taxonomy `dev_status` for `page`
  - `Hide Author Column`: removes the Author column from the Pages list

## Auto‑Update
This plugin checks a public JSON to determine if a newer version is available and offers a one‑click update.
- Update endpoint: `https://raw.githubusercontent.com/AtomicDesignBelgium/wp-atomic-design-framework/main/update.json`
- ZIP URL expected in JSON: `https://github.com/AtomicDesignBelgium/wp-atomic-design-framework/releases/latest/download/wp-atomic-design-framework.zip`

### Releasing a new version
1. Bump the version in the plugin header (`wp-atomic-design-framework.php`) and in `update.json`.
2. Create a Git tag `vX.Y.Z` on GitHub.
3. GitHub Actions (`.github/workflows/release.yml`) builds and attaches `wp-atomic-design-framework.zip` to the release.
4. WordPress will detect and offer the update.

## Code Map
- Bootstrap and constants: `wp-atomic-design-framework.php`
- Settings page: `core/class-settings.php`
- Dev status taxonomy: `modules/dev-status/class-devstatus.php`
- Hide Author column: `modules/author-column/class-author.php`
- Updater hooks: `core/class-updater.php`

## Notes
- The plugin guards WordPress function calls to avoid IDE/static analysis warnings when loaded outside WP.
- `Update URI` is set in the plugin header for clarity and matches the updater endpoint.

## License
- MIT or similar — update as appropriate for your project.