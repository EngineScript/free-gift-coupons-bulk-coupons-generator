<?php
/**
 * Plugin Name: Free Gift Coupons Bulk Coupon Generator
 * Plugin URI: https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator
 * Description: Generate bulk free gift coupon codes that work with the Free Gift Coupons for WooCommerce plugin. Creates coupons with the proper data structure for free gift functionality.
 * Version: 1.7.0
 * Author: EngineScript
 * Requires at least: 7.0
 * Tested up to: 7.1
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 10.8
 * WC tested up to: 11.1
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: free-gift-bulk-coupon-generator
 * Domain Path: /languages
 *
 * @package FreeGiftCouponsBulkGenerator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants. The checks avoid a redefinition warning when a constant already exists.
if ( ! defined( 'FGCBG_PLUGIN_URL' ) ) {
	define( 'FGCBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'FGCBG_PLUGIN_PATH' ) ) {
	define( 'FGCBG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FGCBG_PLUGIN_VERSION' ) ) {
	define( 'FGCBG_PLUGIN_VERSION', '1.7.0' );
}

// Load class files.
require_once __DIR__ . '/includes/class-fgcbg-dependencies.php';
require_once __DIR__ . '/includes/class-fgcbg-coupon-generator.php';
require_once __DIR__ . '/includes/class-fgcbg-ajax-handler.php';
require_once __DIR__ . '/includes/class-fgcbg-admin-assets.php';
require_once __DIR__ . '/includes/class-fgcbg-admin-page.php';
require_once __DIR__ . '/includes/class-fgcbg-plugin.php';

/**
 * Helper function to check if plugin is loaded.
 *
 * @since 1.0.0
 * @return bool
 */
function fgcbg_is_loaded(): bool {
	return class_exists( 'FGCBG_Plugin' );
}

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function fgcbg_init(): void {
	FGCBG_Plugin::get_instance();
}

add_action( 'plugins_loaded', 'fgcbg_init' );

/**
 * Declare compatibility with WooCommerce features.
 *
 * The plugin creates coupons through the WooCommerce coupon API and never
 * reads or writes orders, so it works with High-Performance Order Storage.
 * WooCommerce treats a plugin that sets `WC tested up to` without this
 * declaration as incompatible.
 *
 * @since 1.7.0
 * @return void
 */
function fgcbg_declare_woocommerce_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

add_action( 'before_woocommerce_init', 'fgcbg_declare_woocommerce_compatibility' );
