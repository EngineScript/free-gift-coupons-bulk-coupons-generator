# Free Gift Coupons Bulk Coupon Generator

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/0d4ba2d43e6e4ee1b2171879388e8cbe)](https://app.codacy.com/gh/EngineScript/free-gift-coupons-bulk-coupons-generator/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg?logo=gnu)](https://www.gnu.org/licenses/gpl-3.0.html)
[![WordPress Compatible](https://img.shields.io/badge/WordPress-7.0%2B-blue.svg?logo=wordpress)](https://wordpress.org/)
[![PHP Compatible](https://img.shields.io/badge/PHP-8.2%2B-purple.svg?logo=php)](https://www.php.net/)

## Current Version

[![Version](https://img.shields.io/badge/Version-1.6.0-orange.svg?logo=github)](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/releases/latest/download/free-gift-bulk-coupon-generator-1.6.0.zip)

A WordPress plugin for generating bulk free gift coupons that work specifically with the **Free Gift Coupons for WooCommerce** plugin. Creates coupons with the proper data structure required for free gift functionality.

**Important**: This plugin requires the [Free Gift Coupons for WooCommerce](https://woocommerce.com/products/free-gift-coupons/) plugin to function properly. The Free Gift Coupons plugin can be purchased from the official WooCommerce marketplace.

## Features

- **Free Gift Compatibility**: Specifically designed to work with the Free Gift Coupons for WooCommerce plugin
- **Easy-to-use Admin Interface**: Generate free gift coupons through a user-friendly WordPress admin panel
- **AJAX Product Search**: WooCommerce Select2-powered product search - scales to any catalog size
- **AJAX Batch Generation**: Generates coupons in requests of 10 with a real-time progress bar to reduce timeout risk
- **Multi-Product Support**: Select one product or up to 20 products as free gifts
- **Custom Prefixes**: Add a prefix of up to 8 letters and digits to your coupon codes (for example, `gift` gives codes such as `giftk7m2p9x4wq3n`)
- **Generated Code Export**: Displays generated codes one per line and downloads them as a `.txt` file
- **Bulk Generation**: Generate up to 100 coupons per run. The server limits each request to 100 coupons; it does not limit how many runs a user starts
- **Proper Data Structure**: Creates `$gift_info` arrays with the correct product and variation ID mapping
- **Security**: Nonce and capability checks on every request, sanitized input, and escaped output
- **Responsive Design**: Works on desktop and mobile devices
- **Internationalization Ready**: All interface text is translatable, and a translation template is included
- **Developer Friendly**: Three actions and three filters for customization

## Requirements

- WordPress 7.0 or higher
- PHP 8.2 or higher
- WooCommerce 10.8 or higher
- **[Free Gift Coupons for WooCommerce](https://woocommerce.com/products/free-gift-coupons/)** plugin (required - available for purchase)

## Installation

1. Download the plugin zip file from the [latest release](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/releases/latest)
2. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin** and upload the zip file, or extract it into your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Make sure you have the **Free Gift Coupons for WooCommerce** plugin installed and activated
5. Navigate to **WooCommerce > Coupon Generator** to start using the plugin

Install from the release zip file, not from a copy of the repository. The repository also holds tests, analyzer stubs, and development tooling that do not belong on a live site.

## Usage

1. Go to **WooCommerce > Coupon Generator** in your WordPress admin
2. Search and select one or more products (up to 20) you want to give as free gifts
3. Enter the number of coupons to generate (1-100)
4. Optionally add a custom prefix and change the random code length (8-24 characters, 12 by default)
5. Click "Generate Free Gift Coupons" and watch the progress bar
6. Copy the generated codes from the page or download them as a `.txt` file

## Generated Coupon Features

- **Unique Codes**: Each coupon gets a random code of lower-case letters and digits, checked against existing coupons before it is saved. Look-alike characters (`i`, `l`, `o`, `0`, `1`) are left out. WooCommerce accepts coupon codes in any letter case
- **One-time Use**: Each coupon can only be used once
- **Individual Use**: Coupons cannot be combined with other coupons
- **Auto-expiration**: Coupons expire after 365 days by default
- **Descriptions**: Each coupon's description names its gift products

## Security Features

- Capability checks: `publish_shop_coupons` to generate coupons, and `edit_product` for every selected product
- WordPress nonce verification on every generation request
- Input sanitization and validation
- No direct database queries: coupons are created through the WooCommerce API
- Escaped output
- Selected products that are in the trash, or are not products, are refused

The plugin does not limit how many runs an authorized user starts. A user who may publish coupons can already create them without limit in WooCommerce.

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

The admin screen sends one run as several requests of 10 coupons, so the first two actions fire once per request, not once per run.

- `fgcbg_before_coupon_generation` - Fired before the coupons of one request are generated. Arguments: the validated product IDs (`int[]`) and the number of coupons the request will try to create (`int`)
- `fgcbg_after_coupon_generation` - Fired after the coupons of one request have been generated. Arguments: the validated product IDs (`int[]`) and the number of coupons created (`int`)
- `fgcbg_coupon_generated` - Fired after each individual coupon is saved. Arguments: the coupon ID (`int`) and the validated product IDs (`int[]`)

#### Filters

- `fgcbg_coupon_code_length` - Filter the random portion length of generated coupon codes (default: 12, bounds: 8-24). Arguments: the requested length (`int`) and the length as passed by the caller (`int|null`)
- `fgcbg_coupon_expiry_days` - Filter the number of days until coupon expiry (default: 365, minimum: 1)
- `fgcbg_max_coupons_per_batch` - Filter the maximum number of coupons one request can create (default: 100). Requests from the admin screen are also limited to 100 by the request handler, so a higher value only affects code that calls the generator class directly

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
 * Log when a request has generated coupons.
 *
 * @param int[] $product_ids Validated product IDs.
 * @param int   $count       Number generated by this request.
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
