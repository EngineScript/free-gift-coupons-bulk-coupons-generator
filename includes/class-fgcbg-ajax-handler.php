<?php
/**
 * AJAX request handling.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin AJAX requests for coupon generation.
 *
 * @since 1.6.0
 */
final class FGCBG_Ajax_Handler {

	/**
	 * Default number of coupons generated per AJAX request.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const DEFAULT_BATCH_SIZE = 10;

	/**
	 * Coupon generator.
	 *
	 * @since 1.6.0
	 * @var FGCBG_Coupon_Generator
	 */
	private FGCBG_Coupon_Generator $generator;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 * @param FGCBG_Coupon_Generator $generator Coupon generator.
	 */
	public function __construct( FGCBG_Coupon_Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_fgcbg_generate_batch', array( $this, 'generate_batch' ) );
	}

	/**
	 * Handle AJAX batch coupon generation.
	 *
	 * @since 1.6.0
	 * @return never
	 */
	public function generate_batch(): never {
		check_ajax_referer( 'fgcbg_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to generate coupons.', 'free-gift-bulk-coupon-generator' ) ), 403 );
		}

		if ( ! FGCBG_Dependencies::has_free_gift_coupon_type() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Free Gift Coupons for WooCommerce must be active before generating free gift coupons.', 'free-gift-bulk-coupon-generator' ),
				),
				400
			);
		}

		$product_ids   = $this->get_post_absint_list( 'product_ids' );
		$batch_size    = $this->get_batch_size();
		$coupon_prefix = $this->get_post_text_value( 'coupon_prefix' );
		$code_length   = $this->get_code_length();

		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select at least one product.', 'free-gift-bulk-coupon-generator' ) ), 400 );
		}

		if ( ! $this->current_user_can_edit_products( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to generate coupons for one or more selected products.', 'free-gift-bulk-coupon-generator' ) ), 403 );
		}

		$result = $this->generator->generate_coupon_batch( $product_ids, $batch_size, $coupon_prefix, $code_length );

		wp_send_json_success( $result );
	}

	/**
	 * Check whether the current user can edit every selected product.
	 *
	 * @since 1.6.0
	 * @param array<int> $product_ids Product IDs selected for free gift coupon generation.
	 * @return bool True when the current user can edit all selected products.
	 */
	private function current_user_can_edit_products( array $product_ids ): bool {
		foreach ( $product_ids as $product_id ) {
			if ( ! current_user_can( 'edit_product', $product_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get a scalar text value from the current POST request.
	 *
	 * @since 1.6.0
	 * @param string $key      POST field key.
	 * @param string $fallback Default value.
	 * @return string Sanitized text value.
	 */
	private function get_post_text_value( string $key, string $fallback = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified before this helper is called.
			return $fallback;
		}

		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- AJAX nonce is verified before this helper is called; sanitized after scalar validation.
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Get a positive integer list from the current POST request.
	 *
	 * @since 1.6.0
	 * @param string $key POST field key.
	 * @return array<int>
	 */
	private function get_post_absint_list( string $key ): array {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified before this helper is called.
			return array();
		}

		$values = (array) wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- AJAX nonce is verified before this helper is called; sanitized below.
		$ids    = array();

		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$id = absint( $value );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Get the requested batch size from the current POST request.
	 *
	 * @since 1.6.0
	 * @return int
	 */
	private function get_batch_size(): int {
		$batch_size = isset( $_POST['batch_size'] ) ? absint( wp_unslash( $_POST['batch_size'] ) ) : self::DEFAULT_BATCH_SIZE; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified before this helper is called.

		if ( $batch_size < 1 ) {
			return self::DEFAULT_BATCH_SIZE;
		}

		return min( $batch_size, FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH );
	}

	/**
	 * Get the requested random coupon code length from the current POST request.
	 *
	 * @since 1.6.0
	 * @return int
	 */
	private function get_code_length(): int {
		$code_length = isset( $_POST['coupon_code_length'] ) ? absint( wp_unslash( $_POST['coupon_code_length'] ) ) : FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified before this helper is called.

		return max(
			FGCBG_Coupon_Generator::MIN_CODE_LENGTH,
			min( FGCBG_Coupon_Generator::MAX_CODE_LENGTH, $code_length )
		);
	}
}
