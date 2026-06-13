=== Free Gift Coupons Bulk Coupon Generator ===
Contributors: enginescript
Tags: woocommerce, coupons, bulk, free-gifts, gift-coupons
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 1.6.0
Requires PHP: 8.2
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Bulk-generate free gift coupons that work with the Free Gift Coupons for WooCommerce plugin and use the proper free gift data structure.

== Description ==

Free Gift Coupons Bulk Coupon Generator is a specialized WordPress plugin designed to work specifically with the **Free Gift Coupons for WooCommerce** plugin. It generates bulk free gift coupons with the correct data structure required by that plugin.

**IMPORTANT**: This plugin requires the Free Gift Coupons for WooCommerce plugin to function properly. The Free Gift Coupons plugin can be purchased at https://woocommerce.com/products/free-gift-coupons/

The plugin creates coupons with the proper `$gift_info` array structure required by the Free Gift Coupons plugin, ensuring compatibility where other bulk coupon generators fail.

Key features:
* **Free Gift Compatibility**: Specifically designed for the Free Gift Coupons for WooCommerce plugin
* **Bulk Generation**: Create up to 100 free gift coupons at once with AJAX batch processing
* **AJAX Product Search**: WooCommerce Select2-powered search - scales to any catalog size
* **Progress Bar**: Real-time progress feedback during generation to reduce timeout risk
* **Multi-Product Support**: Select multiple products as free gifts in a single coupon
* **Proper Data Structure**: Creates `$gift_info` arrays with correct product and variation ID mapping
* **Custom Prefixes**: Add alphanumeric prefixes to coupon codes for easy organization
* **Generated Code Export**: View generated codes one per line and download them as a `.txt` file
* **Security First**: CSRF protection, input sanitization, output escaping, and capability checks
* **User-Friendly Interface**: Clean, responsive admin interface with real-time validation
* **Performance Optimized**: Batch processing and server-friendly delays
* **Internationalization Ready**: Full i18n support with translation files
* **Clean Uninstall**: Leaves generated coupons intact so active promotions are not broken

Perfect for:
* Promotional campaigns with product-specific free gifts
* Bulk coupon creation for marketing events
* Educational platforms offering course-specific discounts
* E-commerce stores needing organized coupon management

This plugin is built with security as the top priority, implementing multiple layers of protection against common vulnerabilities while maintaining excellent performance and user experience.

== Installation ==

1. Purchase and install the Free Gift Coupons for WooCommerce plugin from https://woocommerce.com/products/free-gift-coupons/
2. Upload the plugin files to the `/wp-content/plugins/free-gift-bulk-coupon-generator` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Ensure WooCommerce is installed and activated.
5. Navigate to WooCommerce -> Free Gift Bulk Coupons in your WordPress admin.
6. Select products, set the number of coupons, optionally add a custom prefix, and click "Generate Free Gift Coupons".

== Frequently Asked Questions ==

= What are the system requirements? =

* WordPress 6.8 or higher
* WooCommerce plugin installed and activated
* Free Gift Coupons for WooCommerce plugin (required - purchase at https://woocommerce.com/products/free-gift-coupons/)
* PHP 8.2 or higher
* Administrator or Shop Manager capabilities

= How many coupons can I generate at once? =

The plugin allows up to 100 coupons per batch to prevent server timeouts and maintain performance. This limit is enforced both in the UI and server-side validation.

= Can I customize the coupon codes? =

Yes! You can add a custom prefix to all generated coupon codes. Prefixes are alphanumeric, limited to 8 characters, and are added directly before the random code without a separator.

= Are the generated coupons secure? =

Yes. Coupon codes are generated through WordPress's password-generation utility with alphanumeric output, and each generated code is checked for uniqueness before it is saved.

= Which products are included as free gifts? =

You can select one or more products or variations when generating coupons. Those selected items are stored in the free gift metadata used by the Free Gift Coupons for WooCommerce plugin.

= What discount type is used for generated coupons? =

The plugin generates free gift coupons that work with the Free Gift Coupons for WooCommerce plugin. These coupons provide free products rather than percentage or fixed amount discounts.

= Is the plugin translation-ready? =

Yes, the plugin includes a .pot file and is fully prepared for translation into any language.

= How do I uninstall the plugin? =

Deactivate and delete the plugin through the WordPress admin. Generated coupons are intentionally left in place because they may still be active on the site.

= Can developers extend the plugin? =

Yes! The plugin includes numerous hooks and filters for developers to customize coupon generation, validation, and the admin interface.

== License ==

This plugin is licensed under the GPL v3 or later.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

== Changelog ==

= Unreleased =

**Security:**

* Coupon-generation requests now use the WordPress-provided admin AJAX URL from `admin_url()` instead of a hardcoded relative endpoint.
* Changed coupon generator access checks from the broad WooCommerce management capability to WooCommerce's coupon publishing capability before allowing free-gift coupon creation.

**Changed:**

* Refactored the admin script to use native DOM APIs, `fetch()`, `URLSearchParams`, async batch generation, and class-based controller organization for a modern WordPress 6.8+ browser baseline.
* Removed the plugin's direct jQuery dependency, kept only WooCommerce's enhanced select dependency, and deferred the standalone admin script.
* Hardened runtime JavaScript configuration with WordPress-managed script data.
* Simplified client-side validation so the first invalid field is tracked through a single validation list.
* Prefer WordPress plugin path and datetime APIs for plugin includes and generated coupon expiry calculations.
* Added VIPWPCS to Composer development dependencies for local standards testing.

**Fixed:**

* Removed a useless conditional in admin form validation reported by static analysis.

= 1.6.0 =

**Added:**

* AJAX batch coupon generation with real-time progress bar - eliminates timeout risks.
* WooCommerce Select2 AJAX product search - scales to unlimited catalog sizes.
* CSS custom properties for all colors.
* `aria-describedby` attributes on all form fields for accessibility.
* i18n-safe list formatting with `wp_sprintf()`.
* All JavaScript strings provided via WordPress script configuration data.
* Named class constants for all important generation limits.

**Changed:**

* Renamed plugin from "WC Free Gift Coupons Bulk Coupon Generator" to "Free Gift Coupons Bulk Coupon Generator". Updated all prefixes from `scg_`/`SCG_` to `fgcbg_`/`FGCBG_`. **Breaking change** for external code using old hooks/filters/constants.
* Split single-file architecture into dedicated classes under `includes/`.
* Modernized JavaScript to ESNext (`const`/`let`, arrow functions, template literals, optional chaining, nullish coalescing).
* Replaced anonymous hook closures with named methods (unhookable by other plugins).
* Rewrote CSS with tab indentation, alphabetical property ordering per WordPress Coding Standards.
* Complete PHPDoc with `@since`, `@param`, `@return` on all classes, properties, constants, and methods.

**Fixed:**

* CSRF vulnerability in `admin_init()` - now replaced by AJAX `check_ajax_referer()`.
* XSS in success notice - properly escaped `sprintf()` output.
* Parse error from missing `catch` block in `create_single_coupon()`.
* Error logging now uses `$exception->getMessage()` instead of useless `$exception->getCode()`.
* Removed all inline styles from PHP and JavaScript.
* Updated `composer.json` wordpress-stubs from `^6.8` to `^6.9`.

**Removed:**

* Static product dropdown cache (`get_products_for_dropdown()`, `invalidate_product_cache()`). Replaced by WooCommerce AJAX search.
* Synchronous form POST (`admin_init()`, `handle_coupon_generation()`). All generation via AJAX.
* Product cache transient cleanup from `uninstall.php`.

= 1.5.1 =
* **Double-Escaping Fix**: Fixed product names being double-escaped in the dropdown and success notices.
* **Rate Limiting Fix**: Validation errors no longer lock users out for 5 minutes.
* **Naming Collision Protection**: Added constant definition guards and renamed uninstall helper function.
* **Implemented Filter**: The `fgcbg_coupon_code_length` filter now works as documented.
* **Code Cleanup**: Removed dead AJAX localization code, misplaced security headers, and redundant nonce check.
* **Version Sync**: Synchronized version numbers across all project files.
* **FAQ Fix**: Corrected inaccurate reference to `wp_generate_password()` in FAQ.

= 1.5.0 =
* **Plugin Initialization**: Fixed plugin load order by moving initialization to `plugins_loaded` hook instead of immediate global scope execution. This prevents potential conflicts with WooCommerce and ensures all dependencies are properly loaded before initialization.
* **PHPStan Compatibility**: Fixed PHPStan errors including type casting for `esc_html()` and added proper annotations for WooCommerce class types.
* **Code Style and Documentation**: Addressed multiple code style issues, including whitespace, alignment, and indentation, to improve readability and maintainability.
* **PHPDoc Blocks**: Added comprehensive PHPDoc blocks to all functions, ensuring all parameters and return values are clearly documented.
* **Trailing Whitespace**: Removed trailing whitespace from PHP files to comply with coding standards.
* **Code Quality Comments**: Added comprehensive comments addressing Codacy false positives specific to WordPress development environment.

= 1.4.0 =
* **BREAKING**: Fixed text domain to use only lowercase letters and hyphens as required by WordPress standards.
* **Security Fix**: Added proper nonce verification in admin_init to prevent unauthorized form processing.
* **Improvement**: Removed deprecated load_plugin_textdomain() call as WordPress automatically handles translations for plugins hosted on WordPress.org.
* **Improvement**: Updated all repository links to use lowercase format.
* **Testing**: Added helper function for plugin load testing to improve compatibility with testing frameworks.
* **Code Quality Comments**: Added comprehensive comments addressing Codacy false positives specific to WordPress development environment.

= 1.3.0 =
* **Security Enhancement**: Replaced `wp_rand()` with `random_int()` for cryptographically secure coupon code generation.
* **Security Enhancement**: Removed redundant nonce verification in `admin_init` to streamline security checks.
* **Bug Fix**: Corrected the transient key in `uninstall.php` to ensure proper cleanup of cached data upon plugin removal.
* **Code Refinement**: Removed unused `fgbcg_admin_menu()` function to improve code maintainability.

= 1.2.0 =
* **Security Enhancement**: Implemented comprehensive security audit and hardening.
* **Security Enhancement**: Added `X-Content-Type-Options: nosniff` and `X-Frame-Options: SAMEORIGIN` headers.
* **Security Enhancement**: Enhanced input sanitization and validation to prevent XSS and other injection attacks.
* **Security Enhancement**: Implemented rate limiting to prevent abuse of the coupon generation feature.
* **Bug Fix**: Resolved issue where coupon prefix was not properly sanitized.
* **Bug Fix**: Fixed potential for invalid product IDs to be processed.
* **Improvement**: Added caching for product dropdown to improve performance.
* **Improvement**: Added more detailed error messages for failed coupon generation.

= 1.1.0 =
* **Text Domain Standardization**: Fixed WordPress textdomain to match plugin slug for proper internationalization
* **Internationalization Compliance**: Updated all translation functions and POT file to use consistent textdomain
* **Code Quality**: Enhanced code documentation and inline comments for better maintainability
* **WordPress Standards**: Improved compliance with WordPress coding standards and best practices
* **Coupon Generation**: Restored full character set for coupon codes (all lowercase letters and digits)
* **File Structure**: Renamed POT file to match WordPress naming conventions
* **Security Enhancement**: Additional input validation and sanitization improvements
* **Documentation**: Added comprehensive changelog.txt file for WordPress.org compatibility
* **Technical Improvements**: Updated load_plugin_textdomain(), standardized all translation function calls
* **Developer Experience**: Enhanced PHPDoc comments and improved error logging

= 1.0.0 =
* **Initial Release**: Complete WordPress plugin based on original WooCommerce coupon snippet
* **Security Features**: CSRF protection, input sanitization, output escaping, rate limiting
* **Bulk Generation**: Create up to 100 coupons at once with server-friendly processing
* **Product Selection**: Multi-select dropdown for product-specific coupon restrictions
* **Custom Prefixes**: Add custom prefixes to organize coupon codes
* **Admin Interface**: Clean, responsive UI with real-time validation and feedback
* **Performance**: Batch processing and optimized database queries
* **Internationalization**: Full i18n support with .pot translation file
* **Developer Friendly**: Extensive hooks and filters for customization
* **Clean Uninstall**: Complete data removal when plugin is deleted
* **Path Validation**: Improved security by validating logical path structure before filesystem operations
* **Attack Surface Reduction**: Minimized potential attack vectors by pre-validating user input before realpath() calls
* **Security Logging**: Enhanced security event logging for better monitoring of potential attacks


== Upgrade Notice ==

= 1.4.0 =
IMPORTANT: This version includes breaking changes to the text domain and fixes critical security issues. Please update immediately. The text domain has been changed to comply with WordPress standards which may affect custom translations.

= 1.3.0 =
This version includes important security enhancements and bug fixes. It is highly recommended to update for improved security and stability.

= 1.2.0 =
This version includes a comprehensive security audit and hardening. It is highly recommended to update for improved security.

= 1.1.0 =
Important update: Fixed WordPress textdomain for proper internationalization. Enhanced code quality and WordPress standards compliance.

= 1.0.0 =
Initial release - Secure bulk free gift coupon generator for WooCommerce with proper Free Gift Coupons plugin compatibility.
