<?php
/**
 * Plugin Name: Free Gift Coupons Bulk Coupon Generator
 * Plugin URI: https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator
 * Description: Generate bulk free gift coupon codes that work with the Free Gift Coupons for WooCommerce plugin. Creates coupons with the proper data structure for free gift functionality.
 * Version: 1.6.0
 * Author: EngineScript
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 8.2
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

// Define plugin constants with existence checks to prevent fatal errors on reactivation.
if ( ! defined( 'FGCBG_PLUGIN_URL' ) ) {
	define( 'FGCBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'FGCBG_PLUGIN_PATH' ) ) {
	define( 'FGCBG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FGCBG_PLUGIN_VERSION' ) ) {
	define( 'FGCBG_PLUGIN_VERSION', '1.6.0' );
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
function fgcbg_is_loaded() {
	return class_exists( 'FGCBG_Plugin' );
}

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function fgcbg_init() {
	FGCBG_Plugin::get_instance();
}

add_action( 'plugins_loaded', 'fgcbg_init' );
