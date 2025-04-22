### Changelog

## [Unreleased]

## [v1.0.2] - 2025-04-21

### Added
- New order status `"Awaiting payment on LeanX"` created on install and used during initial checkout
- `LeanXHelper::postJson()` method for generic POST API support (e.g. JWT decoding)
- Logs now written to `var/logs/` instead of root directory
- Timestamped logs for callback and API response tracing

### 🔁 Updated
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