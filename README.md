# WP Atomic Design Framework

A modular WordPress plugin that provides small, focused tools aligned with Atomic Design workflows.

## Features
- Dev Status for Pages with predefined statuses (dropdown in editor)
- Admin list filter and bulk actions for Dev Status
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
  - `Enable Dev Tags`: enables Dev Status on `page` with a dropdown in the editor, a list filter, and bulk actions
  - `Hide Author Column`: removes the Author column from the Pages list

### Dev Status presets
Slugs remain stable (English), labels auto-switch by site locale (EN/FR):

- `approved` → EN: 🟩 Approved · FR: 🟩 Validé
- `pending-validation` → EN: 🟧 Pending validation · FR: 🟧 Pour validation
- `in-development` → EN: 🟦 In development · FR: 🟦 En cours de développement
- `empty` → EN: ⬜ Not started · FR: ⬜ Non commencé
- `blocked` → EN: 🟥 Blocked (missing content) · FR: 🟥 Bloqué (contenu manquant)

## Auto‑Update
This plugin checks a public JSON to determine if a newer version is available and offers a one‑click update.
- Update endpoint: `https://raw.githubusercontent.com/AtomicDesignBelgium/wp-atomic-design-framework/main/update.json`
- ZIP URL expected in JSON: `https://github.com/AtomicDesignBelgium/wp-atomic-design-framework/releases/latest/download/wp-atomic-design-framework.zip`

### Releasing a new version
1. Bump the version in the plugin header (`wp-atomic-design-framework.php`) and in `update.json`.
2. Update `update.json` changelog accordingly.
3. Create a Git tag `vX.Y.Z` on GitHub.
4. GitHub Actions (`.github/workflows/release.yml`) builds and attaches `wp-atomic-design-framework.zip` to the release.
5. WordPress will detect and offer the update.

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