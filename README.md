# Free Gift Coupons Bulk Coupon Generator

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/0d4ba2d43e6e4ee1b2171879388e8cbe)](https://app.codacy.com/gh/EngineScript/free-gift-coupons-bulk-coupons-generator/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg?logo=gnu)](https://www.gnu.org/licenses/gpl-3.0.html)
[![WordPress Compatible](https://img.shields.io/badge/WordPress-6.8%2B-blue.svg?logo=wordpress)](https://wordpress.org/)
[![PHP Compatible](https://img.shields.io/badge/PHP-8.2%2B-purple.svg?logo=php)](https://www.php.net/)

## Current Version

[![Version](https://img.shields.io/badge/Version-1.6.0-orange.svg?logo=github)](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/releases/latest/download/free-gift-bulk-coupon-generator-1.6.0.zip)

A WordPress plugin for generating bulk free gift coupons that work specifically with the **Free Gift Coupons for WooCommerce** plugin. Creates coupons with the proper data structure required for free gift functionality.

**Important**: This plugin requires the [Free Gift Coupons for WooCommerce](https://woocommerce.com/products/free-gift-coupons/) plugin to function properly. The Free Gift Coupons plugin can be purchased from the official WooCommerce marketplace.

## Features

- **Free Gift Compatibility**: Specifically designed to work with the Free Gift Coupons for WooCommerce plugin
- **Easy-to-use Admin Interface**: Generate free gift coupons through a user-friendly WordPress admin panel
- **AJAX Product Search**: WooCommerce Select2-powered product search - scales to any catalog size
- **AJAX Batch Generation**: Generates coupons in batches of 10 with a real-time progress bar to reduce timeout risk
- **Multi-Product Support**: Select single or multiple products as free gifts
- **Custom Prefixes**: Add custom prefixes to your coupon codes (e.g., GIFTABC123)
- **Generated Code Export**: Displays generated codes one per line and downloads them as a `.txt` file
- **Bulk Generation**: Generate up to 100 coupons per run with server-side enforcement
- **Proper Data Structure**: Creates `$gift_info` arrays with the correct product and variation ID mapping
- **Security First**: Follows WordPress and OWASP security best practices with comprehensive protection
- **Responsive Design**: Works perfectly on desktop and mobile devices
- **Internationalization Ready**: Prepared for translation into multiple languages
- **Developer Friendly**: Extensive hooks and filters for customization

## Requirements

- WordPress 6.8 or higher
- PHP 8.2 or higher
- WooCommerce 5.0 or higher
- **[Free Gift Coupons for WooCommerce](https://woocommerce.com/products/free-gift-coupons/)** plugin (required - available for purchase)
- Write access to the WordPress uploads directory

## Installation

1. Download the plugin files
2. Upload the `free-gift-bulk-coupon-generator` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Make sure you have the **Free Gift Coupons for WooCommerce** plugin installed and activated
5. Navigate to **WooCommerce > Free Gift Bulk Coupons** to start using the plugin

## Usage

1. Go to **WooCommerce > Free Gift Bulk Coupons** in your WordPress admin
2. Search and select one or more products you want to give as free gifts
3. Enter the number of coupons to generate (1-100)
4. Optionally add a custom prefix and random code length
5. Click "Generate Free Gift Coupons" and watch the progress bar
6. Copy the generated codes from the page or download them as a `.txt` file

## Generated Coupon Features

- **Unique Codes**: Each coupon gets a unique alphanumeric code
- **One-time Use**: Each coupon can only be used once
- **Individual Use**: Coupons cannot be combined with other coupons
- **Auto-expiration**: Coupons expire after 1 year
- **Detailed Descriptions**: Each coupon includes information about the associated product

## Security Features

- User capability verification (`manage_woocommerce` required)
- WordPress nonce verification for form submissions
- Input sanitization and validation
- SQL injection prevention
- XSS protection

## Developer Information

### File Structure

```text
free-gift-bulk-coupon-generator/
|-- free-gift-bulk-coupon-generator.php    # Plugin entry point
|-- includes/
|   |-- class-fgcbg-admin-assets.php        # Admin asset loading and script data
|   |-- class-fgcbg-admin-page.php          # Admin page rendering
|   |-- class-fgcbg-ajax-handler.php        # AJAX request handling
|   |-- class-fgcbg-coupon-generator.php    # Coupon generation logic
|   |-- class-fgcbg-dependencies.php        # Dependency checks
|   `-- class-fgcbg-plugin.php              # Main plugin orchestration
|-- assets/
|   |-- css/
|   |   `-- admin.css                       # Admin interface styles
|   `-- js/
|       `-- admin.js                        # Admin interface JavaScript
|-- languages/
|   `-- free-gift-bulk-coupon-generator.pot
`-- README.md
```

### Hooks and Filters

The plugin provides several hooks for developers:

#### Actions

- `fgcbg_before_coupon_generation` - Fired before coupon generation starts
- `fgcbg_after_coupon_generation` - Fired after coupon generation completes
- `fgcbg_coupon_generated` - Fired after each individual coupon is created

#### Filters

- `fgcbg_coupon_code_length` - Filter the random portion length of generated coupon codes (default: 8, bounds: 8-24)
- `fgcbg_coupon_expiry_days` - Filter the number of days until coupon expiry (default: 365)
- `fgcbg_max_coupons_per_batch` - Filter the maximum number of coupons per batch (default: 100)

### Code Example

```php
/**
 * Customize coupon expiry to 30 days.
 *
 * @param int $days Default expiry days.
 * @return int
 */
function my_custom_coupon_expiry( $days ) {
    return 30;
}
add_filter( 'fgcbg_coupon_expiry_days', 'my_custom_coupon_expiry' );

/**
 * Log when coupons are generated.
 *
 * @param array $product_ids Product IDs.
 * @param int   $count       Number generated.
 * @return void
 */
function my_log_coupon_generation( $product_ids, $count ) {
    wc_get_logger()->info( "Generated {$count} coupons.", array( 'source' => 'my-plugin' ) );
}
add_action( 'fgcbg_after_coupon_generation', 'my_log_coupon_generation', 10, 2 );
```

## Changelog

For a detailed list of changes, please see the [CHANGELOG.md](CHANGELOG.md) file.

## Support

For support, feature requests, or bug reports, please visit the [GitHub repository](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator).

---

**Note**: This plugin requires WooCommerce and Free Gift Coupons for WooCommerce to be installed and activated.
