# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **AJAX Batch Coupon Generation**: Replaced synchronous form POST with AJAX-based batch processing. Coupons are generated in batches of 10 with a real-time progress bar, eliminating timeout risks for large batches.
- **WooCommerce Select2 Product Search**: Replaced static `<select multiple>` dropdown (limited to pre-loaded products) with WooCommerce's built-in AJAX product search (`wc-product-search`). Scales to unlimited catalog sizes with search-as-you-type.
- **Progress Bar UI**: Added visual progress bar with coupon count during AJAX batch generation.
- **CSS Custom Properties**: Defined all colors as `:root` CSS custom properties for consistency and maintainability.
- **Accessibility**: Added `aria-describedby` attributes to all form fields linking to their description text.
- **i18n-safe List Formatting**: Product name lists in coupon descriptions now use `wp_sprintf( '%l', ... )` for proper localized formatting (Oxford comma, translated conjunctions).
- **JavaScript Internationalization**: All user-facing JS strings are now sourced from `wp_add_inline_script()` data - no hardcoded English.
- **Named Constants**: Extracted magic numbers to class constants for batch size, generation delays, prefix length, code length, and default expiry.

### Changed

- **Plugin Rename**: Renamed plugin from "WC Free Gift Coupons Bulk Coupon Generator" to "Free Gift Coupons Bulk Coupon Generator" to remove WC prefix while maintaining clarity as a companion to the Free Gift Coupons for WooCommerce plugin.
  - Renamed main class from `WooCommerceFreeGiftBulkCoupons` to `FGCBG_Plugin`
  - Updated all plugin constants: `SCG_*` -> `FGCBG_*` (Free Gift Coupons Bulk Generator)
  - Updated all function names and hooks to use `fgcbg_` prefix instead of `scg_`
  - Updated all coupon metadata keys from `_scg_*` to `_fgcbg_*`
  - Updated helper functions and initialization callbacks
  - Updated PHPCS and composer configuration

  **Migration Notes**: External code depending on the old hooks, filters, constants, or function names will need updating. This is considered a major version change due to the breaking API changes.

- **File Architecture**: Split monolithic single-file plugin into modular architecture:
  - `includes/class-fgcbg-plugin.php` - Main plugin class (singleton, hooks, AJAX handler)
  - `includes/class-fgcbg-coupon-generator.php` - Coupon generation logic
  - `includes/class-fgcbg-admin-page.php` - Admin page rendering
  - `free-gift-bulk-coupon-generator.php` - Slim entry point (~60 lines)

- **ESNext JavaScript**: Modernized admin.js to use `const`/`let`, arrow functions, template literals, optional chaining (`?.`), and nullish coalescing (`??`). WordPress 6.8+ targets modern browsers.
- **Admin Notices**: Replaced anonymous `add_action('admin_notices', ...)` closures with a named notice-queue pattern (unhookable by other plugins). AJAX handler now returns JSON responses directly.
- **CSS Standards**: Rewrote `admin.css` with tab indentation, alphabetical property ordering, proper section comment headers, and `@package`/`@since` in file header per WordPress Coding Standards.
- **PHPDoc**: Added `@since` tags to all class properties and constants. Complete `@param`, `@return`, `@since` on all methods.

### Fixed

- **CSRF in `admin_init()`**: Restored nonce verification before processing form data to prevent CSRF. The v1.5.1 removal was incorrect - without it, `$_POST` is accessed before any security check. (Now replaced entirely by AJAX `check_ajax_referer()`.)
- **XSS in Success Notice**: Wrapped `sprintf()` output in `esc_html()` (using `__()` instead of `esc_html__()` for the format string) to properly escape the final rendered output.
- **Coupon Generation Parse Error**: Restored a missing `catch` block and success return path in `create_single_coupon()` to fix `Cannot use try without catch or finally`.
- **Error Logging**: `log_coupon_error()` now logs `$exception->getMessage()` instead of the useless `$exception->getCode()`.
- **Inline Styles**: Removed all inline `style` attributes from PHP and JavaScript; moved to dedicated CSS classes.
- **wordpress-stubs Version**: Updated `composer.json` from `^6.8` to `^6.9` to match "Tested up to" version.

### Removed

- **Static Product Dropdown Cache**: Removed `get_products_for_dropdown()`, `invalidate_product_cache()`, and product cache invalidation hooks. Replaced by WooCommerce AJAX search.
- **Synchronous Form POST**: Removed `admin_init()` and `handle_coupon_generation()` methods. All coupon generation is now via AJAX.
- **Product Cache Transient Cleanup**: Removed `fgcbg_products_dropdown` transient cleanup from `uninstall.php` (transient no longer exists).

## [1.5.1] - 2026-02-23

### Fixed

- **Double-Escaping in Product Dropdown**: Removed premature `esc_html()` calls in `get_products_for_dropdown()` that caused double-escaping when rendered. Escaping now occurs only at render time in `render_product_selection_field()`.
- **Double-Escaping in Success Notice**: Removed redundant `esc_html()` wrapping an integer inside an already-escaped `esc_html__()` format string in the coupon generation success message.
- **Redundant Nonce Verification**: Removed duplicate nonce check in `admin_init()` - the nonce is properly verified once inside `handle_coupon_generation()`.
- **Rate Limiting Transient Leak**: All early-return validation paths in `handle_coupon_generation()` now clear the rate-limiting transient, preventing users from being locked out for 5 minutes after a validation error.
- **FAQ Inaccuracy**: Corrected readme.txt FAQ to reference `random_int()` instead of the incorrect `wp_generate_password()`.
- **Version Mismatch**: Synchronized version numbers across README.md, GEMINI.md, readme.txt, and plugin header.

### Improved

- **Naming Collision Protection**: Added `defined()` guards around `SCG_PLUGIN_URL`, `SCG_PLUGIN_PATH`, and `SCG_PLUGIN_VERSION` constants to prevent fatal errors. Renamed uninstall helper `scg_delete_transients_with_prefix()` to `wc_fgbcg_delete_transients_with_prefix()` for namespace safety.
- **Implemented `scg_coupon_code_length` Filter**: The documented filter now actually works, with sane bounds enforcement (8-32 characters).
- **Removed Dead Code**: Removed unused JavaScript AJAX data that had no corresponding AJAX handler.
- **Removed Misplaced Security Headers**: Removed HTTP header injection from `admin_enqueue_scripts` hook where headers are typically already sent. WordPress already provides these protections via its admin framework.
- **Cleaned Up Redundant Comments**: Simplified inline comment noise on sanitization lines.

## [1.5.0] - 2025-08-23

### Fixed

- **Plugin Initialization**: Fixed plugin load order by moving initialization to `plugins_loaded` hook instead of immediate global scope execution. This prevents potential conflicts with WooCommerce and ensures all dependencies are properly loaded before initialization.
- **PHPStan Compatibility**: Fixed PHPStan errors including type casting for `esc_html()` and added proper annotations for WooCommerce class types.
- **Code Style and Documentation**: Addressed multiple code style issues, including whitespace, alignment, and indentation, to improve readability and maintainability.
- **PHPDoc Blocks**: Added comprehensive PHPDoc blocks to all functions, ensuring all parameters and return values are clearly documented.
- **Trailing Whitespace**: Removed trailing whitespace from PHP files to comply with coding standards.
- **Code Quality Comments**: Added comprehensive comments addressing Codacy false positives specific to WordPress development environment.

### Improved

- Fixed WordPress coding standards violations in function formatting
- Fixed anonymous function spacing and indentation issues
- Added missing parameter documentation for validate_products function
- Added return type documentation for generate_coupons function
- Improved code alignment and formatting consistency
- Addressed Codacy static analysis issues with appropriate comments for WordPress-specific patterns

## [1.4.0] - 2025-01-15

### Breaking Changes

- **Text Domain Standardization**: Changed text domain from `free-gift-coupons-bulk-coupons-generator` to `free-gift-coupons-bulk-coupons-generator` to comply with WordPress standards that require only lowercase letters, numbers, and hyphens.

### Security

- **Nonce Verification Fix**: Added proper nonce verification in the `admin_init` method to prevent unauthorized form processing and address WordPress.Security.NonceVerification.Missing warning.

### Improvements

- **Deprecated Function Removal**: Removed the deprecated `load_plugin_textdomain()` call as WordPress automatically handles plugin translations for plugins hosted on WordPress.org since version 4.6.
- **Repository Links Update**: Updated all GitHub repository links to use lowercase format to match the renamed repository.
- **Testing Support**: Added `wc_free_gift_bulk_coupons_is_loaded()` helper function to improve compatibility with testing frameworks and replace the removed test function.

### Development

- **Package References**: Updated all `@package` references to use the new lowercase text domain format.

## 1.3.0 - August 2, 2025

### Security

- **Secure Coupon Generation**: Replaced `wp_rand()` with the more secure `random_int()` for generating coupon codes, ensuring cryptographic-level randomness and reducing the risk of predictable codes.
- **Streamlined Nonce Verification**: Removed a redundant nonce check in `admin_init` to simplify the security workflow, as the primary verification is already handled in `handle_coupon_generation`.

### Bug Fixes

- **Corrected Transient Removal**: Fixed a bug in `uninstall.php` where an incorrect transient key was used, preventing cached product data from being properly deleted upon plugin uninstallation.

### Refactoring

- **Code Cleanup**: Removed the unused `fgbcg_admin_menu()` function to improve code clarity and maintainability.

## 1.2.0 - July 15, 2025

### Security

- **Comprehensive Security Audit**: Conducted a full security review and implemented hardening measures across the plugin to protect against common vulnerabilities.
- **HTTP Security Headers**: Added `X-Content-Type-Options: nosniff` and `X-Frame-Options: SAMEORIGIN` headers to enhance protection against content sniffing and clickjacking attacks.
- **Enhanced Input Sanitization**: Improved sanitization and validation of all user inputs to provide stronger defense against XSS and other injection-based threats.
- **Rate Limiting**: Implemented a rate-limiting mechanism to prevent brute-force attacks and abuse of the coupon generation feature.

### Bug Fixes

- **Prefix Sanitization**: Resolved an issue where the coupon prefix was not being properly sanitized, closing a potential security gap.
- **Invalid Product ID Handling**: Fixed a bug that could allow invalid product IDs to be processed, improving data integrity.

### Improvements

- **Performance Boost**: Added transient caching for the product dropdown, significantly improving performance on sites with many products.
- **Error Messaging**: Implemented more descriptive error messages for failed coupon generation, making it easier to diagnose issues.

## 1.1.0 - June 25, 2025

### Improvements & Fixes

- **Text Domain Standardization**: Fixed WordPress textdomain to match plugin slug `free-gift-coupons-bulk-coupons-generator`
- **Internationalization Compliance**: Updated all translation functions and POT file to use consistent textdomain
- **Code Quality**: Enhanced code documentation and inline comments for better maintainability
- **WordPress Standards**: Improved compliance with WordPress coding standards and best practices
- **Coupon Generation**: Restored full character set for coupon codes (all lowercase letters and digits)
- **File Structure**: Renamed POT file to match WordPress naming conventions
- **Security Enhancement**: Additional input validation and sanitization improvements
- **Documentation**: Added comprehensive changelog.txt file for WordPress.org compatibility

### Technical Changes

- Updated load_plugin_textdomain() to use correct textdomain
- Standardized all `__()`, `esc_html__()`, `esc_attr_e()` function calls
- Enhanced PHPDoc comments and function documentation
- Improved error logging and debug information
- Added comprehensive version tracking across all project files

## 1.0.0 - June 17, 2025

### Initial Release

- **Core Features**: WordPress plugin for generating bulk free gift coupons that work with Free Gift Coupons for WooCommerce
- **Admin Interface**: User-friendly admin panel in WooCommerce menu
- **Multi-Product Support**: Select single or multiple products for free gift coupon generation
- **Custom Prefixes**: Add custom prefixes to coupon codes (e.g., GIFTABC123)
- **Bulk Generation**: Generate up to 100 coupons at once with timeout protection
- **Free Gift Compatibility**: Creates coupons with proper gift_info data structure for Free Gift Coupons for WooCommerce plugin
- **Security First**: Enterprise-grade security with nonces, capability checks, input sanitization
- **Rate Limiting**: Prevent concurrent generation requests
- **Performance Optimized**: Caching, optimized queries, memory-efficient processing
- **Internationalization**: Ready for translation into multiple languages
- **WordPress Standards**: Follows all WordPress coding standards and best practices
- **OWASP Compliant**: Meets OWASP security guidelines for web applications
- **Error Handling**: Graceful error handling with user-friendly messages
- **Responsive Design**: Works perfectly on desktop and mobile devices

### Security Features

- **XSS Prevention**: All outputs properly escaped using WordPress functions
- **CSRF Protection**: WordPress nonce verification for all form submissions
- **Input Validation**: Comprehensive sanitization and validation of all inputs
- **SQL Injection Protection**: Uses WordPress/WooCommerce APIs exclusively
- **Rate Limiting**: User-based request limiting with transients
- **HTTP Security Headers**: X-Content-Type-Options, X-Frame-Options
- **File System Protection**: .htaccess protection for sensitive files
