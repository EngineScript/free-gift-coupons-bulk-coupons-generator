<?php
/**
 * WooCommerce symbols used by static analysis.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

if ( ! defined( 'FGCBG_PLUGIN_URL' ) ) {
	define( 'FGCBG_PLUGIN_URL', '' );
}

if ( ! defined( 'FGCBG_PLUGIN_PATH' ) ) {
	define( 'FGCBG_PLUGIN_PATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'FGCBG_PLUGIN_VERSION' ) ) {
	define( 'FGCBG_PLUGIN_VERSION', '1.5.1' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

/**
 * Minimal WooCommerce product contract used by this plugin.
 */
class WC_Product {
	/**
	 * Get the product name.
	 *
	 * @return string
	 */
	public function get_name() {
	}

	/**
	 * Get the parent product ID for a variation.
	 *
	 * @return int
	 */
	public function get_parent_id() {
	}
}

/**
 * Minimal WooCommerce coupon contract used by this plugin.
 */
class WC_Coupon {
	/**
	 * Set the coupon code.
	 *
	 * @param string $code Coupon code.
	 * @return void
	 */
	public function set_code( $code ) {
	}

	/**
	 * Set the coupon description.
	 *
	 * @param string $description Description.
	 * @return void
	 */
	public function set_description( $description ) {
	}

	/**
	 * Set the discount type.
	 *
	 * @param string $discount_type Discount type.
	 * @return void
	 */
	public function set_discount_type( $discount_type ) {
	}

	/**
	 * Set individual-use behavior.
	 *
	 * @param bool $individual_use Individual-use flag.
	 * @return void
	 */
	public function set_individual_use( $individual_use ) {
	}

	/**
	 * Set coupon usage limit.
	 *
	 * @param int $usage_limit Usage limit.
	 * @return void
	 */
	public function set_usage_limit( $usage_limit ) {
	}

	/**
	 * Set coupon expiry timestamp.
	 *
	 * @param int $date_expires Expiry timestamp.
	 * @return void
	 */
	public function set_date_expires( $date_expires ) {
	}

	/**
	 * Update coupon metadata.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
	}

	/**
	 * Persist the coupon.
	 *
	 * @return void
	 */
	public function save() {
	}

	/**
	 * Get coupon ID.
	 *
	 * @return int
	 */
	public function get_id() {
	}
}

/**
 * Minimal WooCommerce logger contract.
 */
class WC_Logger {
	/**
	 * Log an error.
	 *
	 * @param string               $message Error message.
	 * @param array<string, mixed> $context Error context.
	 * @return void
	 */
	public function error( $message, $context = array() ) {
	}
}

/**
 * Get a WooCommerce product.
 *
 * @param int $product_id Product ID.
 * @return WC_Product|false
 */
function wc_get_product( $product_id ) {
}

/**
 * Get registered WooCommerce coupon types.
 *
 * @return array<string, string>
 */
function wc_get_coupon_types() {
}

/**
 * Get a coupon ID by coupon code.
 *
 * @param string $code Coupon code.
 * @return int
 */
function wc_get_coupon_id_by_code( $code ) {
}

/**
 * Get the WooCommerce logger.
 *
 * @return WC_Logger
 */
function wc_get_logger() {
}
