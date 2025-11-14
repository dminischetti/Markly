# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - Unreleased
### Added
- `htdocs/version.json` served alongside a console log hook so deployments surface build numbers in-browser.
- README “Quick Facts” panel and hard-linked live demo preview for recruiter drive-bys.

### Changed
- Bumped Markly runtime constants and service worker cache to `v1.2.0`, keeping docs and offline copy in sync.
- Updated SPA boot flow to fetch build metadata for visibility without impacting offline caching.

## [1.1.0] - 2024-10-21
### Added
- Centralised `Markly\Constants` with app metadata, service worker cache version, and namespaced storage keys.
- Architecture overview diagram (`docs/architecture.md`) plus recruiter-focused README sections (Live Demo, Security, Built With, Quick View).
- Animated SVG walkthrough (`htdocs/assets/screenshots/demo.svg`) showcasing offline sync without introducing binary assets.
- Versioned service worker logging, footer attribution, and fallback notices when SW registration is blocked.

### Changed
- Hardened authentication by rotating CSRF tokens on login/logout and exposing demo-friendly session regen controls.
- Prefixed IndexedDB/LocalStorage keys with `mdpro_`, refreshed OKLCH palettes, and introduced pending toasts for long-running saves.
- Updated README with security practises, built-with table, and recruiter quick view; added CHANGELOG for ongoing visibility.

### Fixed
- Prevented stale CSRF reuse by clearing token pools on auth transitions.
- Improved offline queue deduplication feedback with loading indicators and namespaced storage to avoid collisions across subdomains.
