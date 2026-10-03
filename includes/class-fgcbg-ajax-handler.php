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
	 * Capability required to mint generated coupons.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	public const GENERATE_COUPONS_CAPABILITY = 'publish_shop_coupons';

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

		if ( ! current_user_can( self::GENERATE_COUPONS_CAPABILITY ) ) {
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

		// The nonce is verified above. Request values are read here and nowhere else.
		$product_ids   = $this->to_absint_list( isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each scalar value is passed through absint() in to_absint_list().
		$batch_size    = $this->to_batch_size( isset( $_POST['batch_size'] ) ? absint( wp_unslash( $_POST['batch_size'] ) ) : self::DEFAULT_BATCH_SIZE );
		$coupon_prefix = $this->to_text( isset( $_POST['coupon_prefix'] ) ? wp_unslash( $_POST['coupon_prefix'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed through sanitize_text_field() in to_text() after a string check.
		$code_length   = $this->to_code_length( isset( $_POST['coupon_code_length'] ) ? absint( wp_unslash( $_POST['coupon_code_length'] ) ) : FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH );

		$this->require_usable_products( $product_ids );

		$result = $this->generator->generate_coupon_batch( $product_ids, $batch_size, $coupon_prefix, $code_length );

		if ( $result['generated'] < 1 ) {
			wp_send_json_error(
				array(
					'message' => __( 'No coupons could be created. If the selected products are valid, check WooCommerce > Status > Logs for details.', 'free-gift-bulk-coupon-generator' ),
				),
				500
			);
		}

		wp_send_json_success( $this->add_purchasability_warning( $result, $product_ids ) );
	}

	/**
	 * Add a warning to the response when a gift product is not purchasable.
	 *
	 * The coupons are still created, as Free Gift Coupons for WooCommerce
	 * saves such a gift with a warning of its own.
	 *
	 * @since 1.7.0
	 * @param array{generated:int, codes:array<int, string>} $result      Generation result.
	 * @param array<int>                                     $product_ids Selected product IDs.
	 * @return array{generated:int, codes:array<int, string>, warning?:string} Result, with `warning` when needed.
	 */
	private function add_purchasability_warning( array $result, array $product_ids ): array {
		$product_names = $this->generator->get_unpurchasable_product_names( $product_ids );

		if ( ! empty( $product_names ) ) {
			$result['warning'] = sprintf(
				/* translators: %s: List of gift product names. */
				__( 'These gift products are not purchasable and cannot be gifted until they are published, in stock, and have a price: %s.', 'free-gift-bulk-coupon-generator' ),
				wp_sprintf( '%l', $product_names )
			);
		}

		return $result;
	}

	/**
	 * End the request with an error unless the selected products can be used.
	 *
	 * The whole request is refused when any one product cannot be used, so a
	 * coupon is never created for fewer gifts than were selected.
	 *
	 * @since 1.7.0
	 * @param array<int> $product_ids Product IDs selected for free gift coupon generation.
	 * @return void
	 */
	private function require_usable_products( array $product_ids ): void {
		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select at least one product.', 'free-gift-bulk-coupon-generator' ) ), 400 );
		}

		if ( count( $product_ids ) > FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: Maximum number of gift products per coupon. */
						__( 'Please select no more than %d products.', 'free-gift-bulk-coupon-generator' ),
						FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS
					),
				),
				400
			);
		}

		if ( ! $this->current_user_can_edit_products( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to generate coupons for one or more selected products.', 'free-gift-bulk-coupon-generator' ) ), 403 );
		}

		if ( ! $this->generator->are_products_usable( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'One or more selected products are not available. Remove any product that is in the trash or has been deleted, then try again.', 'free-gift-bulk-coupon-generator' ) ), 400 );
		}
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
	 * Convert an unslashed request value to sanitized text.
	 *
	 * Request values are strings or arrays. Anything that is not a string
	 * yields an empty string.
	 *
	 * @since 1.7.0
	 * @param mixed $value Unslashed request value.
	 * @return string Sanitized text value.
	 */
	private function to_text( mixed $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Convert an unslashed request value to a list of unique positive integers.
	 *
	 * Nested arrays and other non-scalar entries are dropped.
	 *
	 * @since 1.7.0
	 * @param mixed $value Unslashed request value.
	 * @return array<int>
	 */
	private function to_absint_list( mixed $value ): array {
		$ids = array();

		foreach ( (array) $value as $item ) {
			if ( is_scalar( $item ) ) {
				$id = absint( $item );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Bound the requested batch size.
	 *
	 * @since 1.7.0
	 * @param int $batch_size Requested number of coupons for this request.
	 * @return int
	 */
	private function to_batch_size( int $batch_size ): int {
		if ( $batch_size < 1 ) {
			return self::DEFAULT_BATCH_SIZE;
		}

		return min( $batch_size, FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH );
	}

	/**
	 * Bound the requested random coupon code length.
	 *
	 * @since 1.7.0
	 * @param int $code_length Requested random code length.
	 * @return int
	 */
	private function to_code_length( int $code_length ): int {
		return max(
			FGCBG_Coupon_Generator::MIN_CODE_LENGTH,
			min( FGCBG_Coupon_Generator::MAX_CODE_LENGTH, $code_length )
		);
	}
}
