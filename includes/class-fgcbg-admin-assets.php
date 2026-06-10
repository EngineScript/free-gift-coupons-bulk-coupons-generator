<?php
/**
 * Admin asset loading.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin assets and exposes server-side configuration to JavaScript.
 *
 * @since 1.6.0
 */
final class FGCBG_Admin_Assets {

	/**
	 * Admin script handle.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'fgcbg-admin';

	/**
	 * Admin style handle.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const STYLE_HANDLE = 'fgcbg-admin';

	/**
	 * Register asset hooks.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.6.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_free-gift-bulk-coupon-generator' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			FGCBG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wc-enhanced-select' ),
			FGCBG_PLUGIN_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_style(
			self::STYLE_HANDLE,
			FGCBG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FGCBG_PLUGIN_VERSION
		);

		$script_data = wp_json_encode(
			$this->get_script_data(),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( false === $script_data ) {
			$script_data = '{}';
		}

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'globalThis.fgcbgAdminConfig = Object.freeze(' . $script_data . ');',
			'before'
		);
	}

	/**
	 * Get JavaScript configuration and translated strings.
	 *
	 * @since 1.6.0
	 * @return array<string, int|string>
	 */
	private function get_script_data(): array {
		return array(
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'batch_size'             => FGCBG_Ajax_Handler::DEFAULT_BATCH_SIZE,
			'max_coupon_count_value' => FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH,
			'max_prefix_length'      => FGCBG_Coupon_Generator::MAX_PREFIX_LENGTH,
			'min_code_length'        => FGCBG_Coupon_Generator::MIN_CODE_LENGTH,
			'max_code_length'        => FGCBG_Coupon_Generator::MAX_CODE_LENGTH,
			'nonce'                  => wp_create_nonce( 'fgcbg_ajax_nonce' ),
			/* translators: %d is the number of coupons to be generated. */
			'confirm_large_batch'    => __( 'You are about to generate %d coupons. This may take a while and could potentially timeout depending on your server settings. Do you want to continue?', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d is the maximum number of coupons that can be generated in one run. */
			'max_coupons_warning'    => __( 'Maximum %d coupons allowed', 'free-gift-bulk-coupon-generator' ),
			'many_coupons_warning'   => __( 'Generating many coupons may take some time and could timeout', 'free-gift-bulk-coupon-generator' ),
			'select_product'         => __( 'Please select at least one product.', 'free-gift-bulk-coupon-generator' ),
			'invalid_coupon_count'   => __( 'Please enter a valid number of coupons (minimum 1).', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d is the maximum number of coupons that can be generated in one run. */
			'max_coupon_count'       => __( 'Maximum number of coupons is %d.', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d is the maximum coupon prefix length. */
			'prefix_too_long'        => __( 'Coupon prefix must be %d characters or less.', 'free-gift-bulk-coupon-generator' ),
			/* translators: 1: minimum random code length, 2: maximum random code length. */
			'code_length_invalid'    => __( 'Please enter a random code length between %1$d and %2$d characters.', 'free-gift-bulk-coupon-generator' ),
			'generation_in_progress' => __( 'Coupon generation is in progress. Are you sure you want to leave this page?', 'free-gift-bulk-coupon-generator' ),
			/* translators: %1$d is the current coupon count, %2$d is the total number of coupons to generate. */
			'generating_progress'    => __( 'Generating coupons: %1$d of %2$d', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d is the number of successfully generated coupons. */
			'generation_complete'    => __( 'Successfully generated %d coupons.', 'free-gift-bulk-coupon-generator' ),
			'generation_failed'      => __( 'Failed to generate coupons. Please try again.', 'free-gift-bulk-coupon-generator' ),
		);
	}
}
