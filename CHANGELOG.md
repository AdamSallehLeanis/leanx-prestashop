### Changelog

## [Unreleased]

## [v1.0.3] - 2025-04-22

### Added
- CRON timeout handler that auto-cancels unpaid orders after a configurable delay (default: 30 minutes)
- `LEANX_TIMEOUT_MINUTES` config field with validation support
- `Check Cron Status` button in module config UI with smart positioning and Bootstrap styling
- CRON log tail parser to detect recent executions via `leanx_timeout.log`
- Link to GitHub README in admin interface with detailed CRON setup instructions

### Updated
- Improved display style and alignment for config form utilizing Bootstrap

### Fixed
- Custom order state `"Awaiting payment on LeanX"` avoids duplication on module reset

## [v1.0.2] - 2025-04-21

### Added
- New order status `"Awaiting payment on LeanX"` created on install and used during initial checkout
- `LeanXHelper::postJson()` method for generic POST API support (e.g. JWT decoding)
- Logs now written to `var/logs/` instead of root directory
- Timestamped logs for callback and API response tracing

### Updated
- `success.php` now uses `LeanXHelper::callApi()` instead of raw cURL for manual invoice checking
- `callback.php` now uses `LeanXHelper::postJson()` for decoding signed payloads
- Improved logging clarity and structure in callback and success controllers

### Fixed
- Removed misleading status `On backorder (paid)` in favor of clean custom state

## [v1.0.1] - 2025-04-21
### Added
- Validation for LeanX API credentials on config save
- Helper class `LeanxHelper` for reusable cURL/validation logic

### Fixed
- Switch field now correctly preselects “Disabled” when saved

## [v1.0.0] - 2025-04-18
- Initial release of LeanX payment gateway for PrestaShop