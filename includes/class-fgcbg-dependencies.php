<?php
/**
 * Dependency checks.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides dependency checks for WooCommerce and Free Gift Coupons support.
 *
 * @since 1.6.0
 */
final class FGCBG_Dependencies {

	/**
	 * Check whether WooCommerce is available.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check whether the Free Gift Coupons coupon type is registered.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public static function has_free_gift_coupon_type(): bool {
		return function_exists( 'wc_get_coupon_types' )
			&& array_key_exists( FGCBG_Coupon_Generator::DISCOUNT_TYPE, wc_get_coupon_types() );
	}
}
