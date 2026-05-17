<?php
/**
 * Coupon generation logic.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce coupon creation, code generation, and batch processing.
 *
 * @since 1.6.0
 */
final class FGCBG_Coupon_Generator {

	/**
	 * Coupon discount type registered by Free Gift Coupons for WooCommerce.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	public const DISCOUNT_TYPE = 'free_gift';

	/**
	 * Maximum coupons that can be generated in a single batch.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const MAX_COUPONS_PER_BATCH = 100;

	/**
	 * Maximum coupon prefix length.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const MAX_PREFIX_LENGTH = 8;

	/**
	 * Number of coupons between server-relief micro-delays.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	private const DELAY_INTERVAL = 50;

	/**
	 * Micro-delay duration in microseconds (0.1 s).
	 *
	 * @since 1.6.0
	 * @var int
	 */
	private const DELAY_MICROSECONDS = 100000;

	/**
	 * Minimum generated coupon code length.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const MIN_CODE_LENGTH = 8;

	/**
	 * Maximum generated coupon code length.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const MAX_CODE_LENGTH = 24;

	/**
	 * Default generated coupon code length.
	 *
	 * @since 1.6.0
	 * @var int
	 */
	public const DEFAULT_CODE_LENGTH = 8;

	/**
	 * Default coupon expiry in days (filterable via fgcbg_coupon_expiry_days).
	 *
	 * @since 1.6.0
	 * @var int
	 */
	private const DEFAULT_EXPIRY_DAYS = 365;

	/**
	 * Generate coupons.
	 *
	 * This trusted service method does not read request data or perform
	 * authorization. Controllers must verify nonces and capabilities before
	 * calling it.
	 *
	 * @since 1.0.0
	 * @param array<int>|int $product_ids       Product IDs to generate coupons for.
	 * @param int            $number_of_coupons Number of coupons to generate.
	 * @param string         $prefix            Coupon prefix.
	 * @param int|null       $code_length       Generated random code length, excluding the optional prefix.
	 * @return int Number of coupons generated.
	 */
	public function generate_coupons( array|int $product_ids, int $number_of_coupons, string $prefix = '', ?int $code_length = null ): int {
		$result = $this->generate_coupon_batch( $product_ids, $number_of_coupons, $prefix, $code_length );

		return $result['generated'];
	}

	/**
	 * Generate coupons and return the generated coupon codes.
	 *
	 * This trusted service method does not read request data or perform
	 * authorization. Controllers must verify nonces and capabilities before
	 * calling it.
	 *
	 * @since 1.6.0
	 * @param array<int>|int $product_ids       Product IDs to generate coupons for.
	 * @param int            $number_of_coupons Number of coupons to generate.
	 * @param string         $prefix            Coupon prefix.
	 * @param int|null       $code_length       Generated random code length, excluding the optional prefix.
	 * @return array{generated:int, codes:array<int, string>} Generated coupon summary.
	 */
	public function generate_coupon_batch( array|int $product_ids, int $number_of_coupons, string $prefix = '', ?int $code_length = null ): array {
		$valid_products = $this->validate_products( $product_ids );
		if ( empty( $valid_products ) ) {
			return array(
				'generated' => 0,
				'codes'     => array(),
			);
		}

		$generation_params = $this->prepare_generation_params( $number_of_coupons, $prefix, $code_length );
		$gift_info         = $this->prepare_gift_info( $valid_products );

		do_action( 'fgcbg_before_coupon_generation', $product_ids, $generation_params['count'] );

		$generated_count = $this->execute_coupon_generation( $valid_products, $gift_info, $generation_params );

		do_action( 'fgcbg_after_coupon_generation', $product_ids, $generated_count['generated'] );

		return $generated_count;
	}

	/**
	 * Validate products for coupon generation.
	 *
	 * @since 1.0.0
	 * @param array<int>|int $product_ids Product IDs to validate.
	 * @return array<int, WC_Product> Array of valid product objects keyed by ID.
	 */
	private function validate_products( array|int $product_ids ): array {
		if ( ! is_array( $product_ids ) ) {
			$product_ids = array( $product_ids );
		}

		$valid_products = array();
		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id < 1 || isset( $valid_products[ $product_id ] ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( $product ) {
				$valid_products[ $product_id ] = $product;
			}
		}

		return $valid_products;
	}

	/**
	 * Prepare generation parameters.
	 *
	 * @since 1.0.0
	 * @param int      $number_of_coupons Number of coupons to generate.
	 * @param string   $prefix            Coupon prefix.
	 * @param int|null $code_length     Generated random code length.
	 * @return array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} Generation parameters array.
	 */
	private function prepare_generation_params( int $number_of_coupons, string $prefix, ?int $code_length ): array {
		$requested_count = max( 0, $number_of_coupons );
		$max_count       = max( 1, (int) apply_filters( 'fgcbg_max_coupons_per_batch', self::MAX_COUPONS_PER_BATCH ) );
		$count           = min( $requested_count, $max_count );
		$expiry_days     = max( 1, (int) apply_filters( 'fgcbg_coupon_expiry_days', self::DEFAULT_EXPIRY_DAYS ) );
		$code_length     = $this->normalize_code_length( $code_length );

		return array(
			'count'        => $count,
			'prefix'       => $this->normalize_prefix( $prefix ),
			'code_length'  => $code_length,
			'expiry_days'  => $expiry_days,
			'max_attempts' => max( $count * 2, $count + 10 ),
		);
	}

	/**
	 * Prepare gift information for coupons.
	 *
	 * IMPORTANT: Free Gift Coupons for WooCommerce expects this exact metadata
	 * shape in the `_wc_free_gift_coupon_data` coupon meta key. The outer array
	 * key is the selected gift ID. For simple products, that key and the nested
	 * `product_id` are the same and `variation_id` is 0:
	 *
	 * 123 => array(
	 *     'product_id'   => 123,
	 *     'variation_id' => 0,
	 *     'quantity'     => 1,
	 * )
	 *
	 * For variations, the outer key is the variation ID, `product_id` is the
	 * parent product ID, and `variation_id` is the selected variation ID. Do not
	 * rename `$gift_info`, `_wc_free_gift_coupon_data`, or the nested keys unless
	 * the upstream free gift coupon plugin changes its storage contract.
	 *
	 * @since 1.0.0
	 * @param array<int, WC_Product> $valid_products Array of valid product objects keyed by selected gift ID.
	 * @return array<int, array{product_id:int, variation_id:int, quantity:int}> Gift information array.
	 */
	private function prepare_gift_info( array $valid_products ): array {
		$gift_info = array();
		foreach ( $valid_products as $gift_id => $product ) {
			$parent_id             = absint( $product->get_parent_id() );
			$gift_info[ $gift_id ] = array(
				'product_id'   => $parent_id > 0 ? $parent_id : $gift_id,
				'variation_id' => $parent_id > 0 ? $gift_id : 0,
				'quantity'     => 1,
			);
		}
		return $gift_info;
	}

	/**
	 * Execute the coupon generation process.
	 *
	 * @since 1.0.0
	 * @param array<int, WC_Product>                                                              $valid_products Array of valid product objects.
	 * @param array<int, array{product_id:int, variation_id:int, quantity:int}>                   $gift_info      Gift information array.
	 * @param array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} $params         Generation parameters.
	 * @return array{generated:int, codes:array<int, string>} Generated coupon summary.
	 */
	private function execute_coupon_generation( array $valid_products, array $gift_info, array $params ): array {
		$generated_count = 0;
		$attempt_count   = 0;
		$generated_codes = array();

		for ( $i = 1; $i <= $params['count']; $i++ ) {
			if ( $attempt_count >= $params['max_attempts'] ) {
				break;
			}
			++$attempt_count;

			$generated_code = $this->create_single_coupon( $valid_products, $gift_info, $params, $i );

			if ( null !== $generated_code ) {
				++$generated_count;
				$generated_codes[] = $generated_code;
				$this->handle_generation_delay( $i );
			} else {
				--$i;
			}
		}

		return array(
			'generated' => $generated_count,
			'codes'     => $generated_codes,
		);
	}

	/**
	 * Create a single coupon.
	 *
	 * @since 1.0.0
	 * @param array<int, WC_Product>                                                              $valid_products Array of valid product objects.
	 * @param array<int, array{product_id:int, variation_id:int, quantity:int}>                   $gift_info      Gift information array.
	 * @param array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} $params         Generation parameters.
	 * @param int                                                                                 $current_number Current coupon number in batch.
	 * @return string|null Generated coupon code, or null when creation failed.
	 */
	private function create_single_coupon( array $valid_products, array $gift_info, array $params, int $current_number ): ?string {
		try {
			$coupon      = new WC_Coupon();
			$random_code = $this->generate_coupon_code( $params['prefix'], $params['code_length'] );
			if ( $this->coupon_code_exists( $random_code ) ) {
				return null;
			}

			$this->set_coupon_properties( $coupon, $random_code, $valid_products, $params, $current_number );
			$this->set_coupon_metadata( $coupon, $gift_info );

			$coupon->save();

			do_action( 'fgcbg_coupon_generated', $coupon->get_id(), array_keys( $valid_products ) );

			return $random_code;
		} catch ( \Throwable $e ) {
			$this->log_coupon_error( $e );
			return null;
		}
	}

	/**
	 * Set coupon properties.
	 *
	 * @since 1.0.0
	 * @param WC_Coupon                                                                           $coupon         The coupon object.
	 * @param string                                                                              $code           The coupon code.
	 * @param array<int, WC_Product>                                                              $valid_products Array of valid product objects.
	 * @param array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} $params         Generation parameters.
	 * @param int                                                                                 $current_number Current coupon number in batch.
	 * @return void
	 */
	private function set_coupon_properties( WC_Coupon $coupon, string $code, array $valid_products, array $params, int $current_number ): void {
		$product_names = array();
		foreach ( $valid_products as $product ) {
			$product_name = sanitize_text_field( wp_strip_all_tags( $product->get_name() ) );
			if ( '' !== $product_name ) {
				$product_names[] = $product_name;
			}
		}
		$products_text = wp_sprintf( '%l', $product_names );

		$coupon->set_code( $code );
		$coupon->set_description(
			sprintf(
				/* translators: 1: Product names, 2: Current batch number, 3: Total number of coupons */
				__( 'Auto-generated coupon for %1$s (Batch %2$d/%3$d)', 'free-gift-coupons-bulk-coupons-generator' ),
				$products_text,
				$current_number,
				$params['count']
			)
		);
		$coupon->set_discount_type( self::DISCOUNT_TYPE );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_date_expires( time() + ( $params['expiry_days'] * DAY_IN_SECONDS ) );
	}

	/**
	 * Set coupon metadata.
	 *
	 * The `$gift_info` argument is required for generated coupons to work with
	 * Free Gift Coupons for WooCommerce. It must be saved verbatim to
	 * `_wc_free_gift_coupon_data`; the plugin reads that meta key to know which
	 * products or variations to add as gifts.
	 *
	 * @since 1.0.0
	 * @param WC_Coupon                                                         $coupon    The coupon object.
	 * @param array<int, array{product_id:int, variation_id:int, quantity:int}> $gift_info Gift information array.
	 * @return void
	 */
	private function set_coupon_metadata( WC_Coupon $coupon, array $gift_info ): void {
		$coupon->update_meta_data( '_wc_free_gift_coupon_data', $gift_info );
		$coupon->update_meta_data( '_fgcbg_generated', true );
		$coupon->update_meta_data( '_fgcbg_product_ids', array_keys( $gift_info ) );
		$coupon->update_meta_data( '_fgcbg_generation_date', current_time( 'mysql' ) );
	}

	/**
	 * Handle generation delay for performance.
	 *
	 * @since 1.0.0
	 * @param int $current_number Current coupon number in the batch.
	 * @return void
	 */
	private function handle_generation_delay( int $current_number ): void {
		if ( 0 === $current_number % self::DELAY_INTERVAL ) {
			usleep( self::DELAY_MICROSECONDS );
		}
	}

	/**
	 * Log coupon generation errors.
	 *
	 * @since 1.0.0
	 * @param \Throwable $exception The exception that occurred.
	 * @return void
	 */
	private function log_coupon_error( \Throwable $exception ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			wc_get_logger()->error(
				sprintf(
					/* translators: 1: Exception class name, 2: Error message */
					__( 'FGCBG Error generating coupon [%1$s]: %2$s', 'free-gift-coupons-bulk-coupons-generator' ),
					get_class( $exception ),
					$exception->getMessage()
				),
				array( 'source' => 'free-gift-coupons-bulk-coupons-generator' )
			);
		}
	}

	/**
	 * Generate unique coupon code.
	 *
	 * @since 1.0.0
	 * @param string $prefix      Optional prefix for the coupon code.
	 * @param int    $code_length Generated random code length, excluding the optional prefix.
	 * @return string Generated coupon code.
	 */
	private function generate_coupon_code( string $prefix, int $code_length ): string {
		$random_string = strtolower( wp_generate_password( $code_length, false, false ) );

		if ( '' !== $prefix ) {
			return strtolower( $prefix ) . $random_string;
		}

		return $random_string;
	}

	/**
	 * Check whether a coupon code already exists.
	 *
	 * @since 1.6.0
	 * @param string $code Coupon code.
	 * @return bool True when WooCommerce already has a coupon with this code.
	 */
	private function coupon_code_exists( string $code ): bool {
		return function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) > 0;
	}

	/**
	 * Normalize an optional coupon prefix.
	 *
	 * @since 1.6.0
	 * @param string $prefix Raw coupon prefix.
	 * @return string Uppercase alphanumeric prefix limited to eight characters.
	 */
	private function normalize_prefix( string $prefix ): string {
		$prefix = (string) preg_replace( '/[^A-Za-z0-9]/', '', $prefix );

		return strtoupper( substr( $prefix, 0, self::MAX_PREFIX_LENGTH ) );
	}

	/**
	 * Normalize generated coupon code length.
	 *
	 * @since 1.6.0
	 * @param int|null $code_length Requested generated code length.
	 * @return int Bounded generated code length.
	 */
	private function normalize_code_length( ?int $code_length ): int {
		$requested_length = $code_length ?? self::DEFAULT_CODE_LENGTH;
		$filtered_length  = (int) apply_filters( 'fgcbg_coupon_code_length', $requested_length, $code_length );

		return max( self::MIN_CODE_LENGTH, min( self::MAX_CODE_LENGTH, $filtered_length ) );
	}
}
