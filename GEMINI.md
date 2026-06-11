# Free Gift Coupons Bulk Coupon Generator - WordPress Plugin

## Project Overview

This is a WordPress plugin that generates bulk free gift coupon codes for WooCommerce stores. It integrates with the "Free Gift Coupons for WooCommerce" plugin to create coupons with the proper data structure for free gift functionality. Designed for store administrators who need to generate large quantities of gift coupons efficiently.

## Plugin Details

- **Name:** Free Gift Coupons Bulk Coupon Generator
- **Version:** 1.6.0
- **WordPress Compatibility:** 6.8+
- **PHP Compatibility:** 8.2+
- **WooCommerce Compatibility:** 5.0+
- **License:** GPL-3.0-or-later
- **Text Domain:** free-gift-bulk-coupon-generator
- **Dependencies:** WooCommerce, Free Gift Coupons for WooCommerce

## Architecture & Design Patterns

### Multi-Class Plugin Architecture

The plugin uses a singleton orchestrator that delegates to dedicated classes:

```php
FGCBG_Plugin           - Plugin orchestration and admin menu hooks
FGCBG_Dependencies     - WooCommerce and free gift coupon type checks
FGCBG_Admin_Assets     - Admin asset loading and script data
FGCBG_Ajax_Handler     - Coupon generation AJAX request handling
FGCBG_Coupon_Generator - Coupon creation, code generation, batch processing
FGCBG_Admin_Page       - Admin page rendering (form, sidebar, footer)
```

### File Structure

```text
free-gift-bulk-coupon-generator.php    - Plugin entry point, constants, require_once
includes/
    class-fgcbg-admin-assets.php       - Admin asset loading and script data
    class-fgcbg-admin-page.php         - Admin page rendering
    class-fgcbg-ajax-handler.php       - AJAX request handling
    class-fgcbg-coupon-generator.php   - Coupon generation logic
    class-fgcbg-dependencies.php       - Dependency checks
    class-fgcbg-plugin.php             - Main plugin orchestration
assets/
    css/admin.css                      - Admin interface styles (CSS custom properties, tab indentation)
    js/admin.js                        - Admin interface JavaScript (ESNext, AJAX batch generation)
languages/
    free-gift-bulk-coupon-generator.pot - Translation template
```

### Plugin Initialization

```php
function fgcbg_init() {
    FGCBG_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'fgcbg_init' );
```

## WordPress Coding Standards

### Naming Conventions

- **Classes:** `FGCBG_Plugin`, `FGCBG_Coupon_Generator`, `FGCBG_Admin_Page` (WP prefix style)
- **Methods:** `snake_case` (WordPress standard)
- **Variables:** `$snake_case`
- **Constants:** `FGCBG_UPPER_SNAKE_CASE`
- **Hooks/Filters:** `fgcbg_*` prefix
- **Text Domain:** Always use `'free-gift-bulk-coupon-generator'`

### Security Requirements

- Always use `esc_html()`, `esc_attr()`, `esc_url()` for output
- Sanitize input with `sanitize_text_field()`, `wp_unslash()`, `absint()` etc.
- Use `current_user_can( 'manage_woocommerce' )` for capability checks
- AJAX: `check_ajax_referer()` for nonce verification, `wp_send_json_error()`/`wp_send_json_success()` for responses
- Validate product IDs and coupon parameters
- Bound coupon generation counts to prevent resource abuse

### WordPress & WooCommerce Integration

- **Hooks:** Named methods via `add_action()` / `add_filter()` (no anonymous closures)
- **WooCommerce API:** `WC_Coupon` class for coupon creation, `wc_get_product()` for validation
- **Product Search:** WooCommerce Select2 AJAX search (`wc-product-search` class, `wc-enhanced-select` dependency)
- **Database:** WordPress/WooCommerce APIs only, no direct SQL
- **Internationalization:** All strings use `__()`, `esc_html__()`, `esc_html_e()`; JS strings via `wp_add_inline_script()` data
- **Admin Interface:** Integrated into WooCommerce admin menu

## Plugin-Specific Context

### Core Functionality

#### AJAX Batch Coupon Generation

- **Product Selection:** WooCommerce Select2 AJAX search - scales to unlimited products
- **Batch Processing:** JS sends AJAX requests in batches of 10; progress bar updates between batches
- **AJAX Handler:** `wp_ajax_fgcbg_generate_batch` - validates input and delegates to `FGCBG_Coupon_Generator::generate_coupon_batch()`
- **Progress Feedback:** Real-time progress bar with coupon count to reduce timeout risk
- **Generated Code Output:** Shows generated coupon codes one per line and allows `.txt` download
- **Free Gift Integration:** Creates coupons compatible with Free Gift Coupons plugin

#### Coupon Security Features

- **Unique Code Generation:** WordPress alphanumeric password generation with WooCommerce duplicate checks
- **Input Validation:** Comprehensive sanitization of all coupon parameters
- **Capability Checks:** `manage_woocommerce` permission required for all operations
- **Nonce Verification:** AJAX nonce via `check_ajax_referer()`

#### Performance Optimizations

- **AJAX Batching:** Generates coupons in small batches (10 at a time) to avoid timeouts
- **Server-Relief Delays:** Micro-delays every 50 coupons to prevent resource exhaustion
- **WooCommerce AJAX Search:** No pre-loaded product lists - search-as-you-type via WooCommerce built-in
- **Database Efficiency:** WooCommerce API usage for all coupon creation

#### Admin Interface

- **WooCommerce Integration:** Submenu under WooCommerce admin menu
- **Select2 Product Search:** AJAX-powered, scalable product selection
- **Form Validation:** Client-side validation with i18n error messages
- **Progress Bar:** Real-time batch generation progress
- **Responsive Design:** Works on desktop and mobile

### WooCommerce Integration

- **WC_Coupon API:** Proper use of WooCommerce coupon creation methods
- **Product Validation:** Verification of product existence via `wc_get_product()`
- **Metadata Structure:** Correct `_wc_free_gift_coupon_data` format for Free Gift Coupons compatibility
- **Coupon Properties:** Expiration, usage limits, individual use, discount type settings

### Hooks and Filters

#### Actions

- `fgcbg_before_coupon_generation` - Fired before coupon generation starts
- `fgcbg_after_coupon_generation` - Fired after coupon generation completes
- `fgcbg_coupon_generated` - Fired after each individual coupon is created

#### Filters

- `fgcbg_coupon_code_length` - Filter the random portion length of generated coupon codes (default: 8, bounds: 8-24)
- `fgcbg_coupon_expiry_days` - Filter the number of days until coupon expiry (default: 365)
- `fgcbg_max_coupons_per_batch` - Filter the maximum number of coupons per batch (default: 100)

## Development Standards

### CSS Standards

- Tab indentation (WordPress standard)
- Alphabetical property ordering within each rule
- CSS custom properties on `:root` for all colors
- Proper section comment headers (`/** ... */`)
- `@package` and `@since` in file header

### JavaScript Standards

- ESNext (const/let, arrow functions, template literals, optional chaining, nullish coalescing)
- jQuery for DOM manipulation (WooCommerce admin dependency)
- All user-facing strings from WordPress script data - never hardcoded
- IIFE scope with `'use strict'`

### PHP Standards

- PHPDoc on all functions: `@param`, `@return`, `@since` tags
- `@since` tags on all class properties and constants
- `wp_sprintf( '%l', $array )` for i18n-safe list formatting
- `aria-describedby` on all form fields for accessibility
- WordPress APIs exclusively (no raw PHP for DB, HTTP, filesystem)

### Error Handling

- **AJAX:** `wp_send_json_error()` with translated messages
- **Coupon Creation:** Try/catch with `wc_get_logger()` for debug-mode logging
- **Graceful Degradation:** WooCommerce missing detection with admin notice

### Testing & Quality Assurance

- **PHPStan Level 6:** Static analysis with WooCommerce stubs
- **PHPCS WordPress Standards:** Full WordPress and WooCommerce coding standards
- **PHPMD:** Code quality and complexity management
