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
	 * Maximum coupons that one request can create.
	 *
	 * The admin screen also uses this as the maximum for one run, which it
	 * sends as several smaller requests.
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
	 * @since 1.7.0 Raised from 8 to 12.
	 * @var int
	 */
	public const DEFAULT_CODE_LENGTH = 12;

	/**
	 * Maximum gift products that one coupon can carry.
	 *
	 * @since 1.7.0
	 * @var int
	 */
	public const MAX_GIFT_PRODUCTS = 20;

	/**
	 * Characters used for the random part of a coupon code.
	 *
	 * Lower-case letters and digits without the look-alike characters
	 * i, l, o, 0, and 1, because shoppers type these codes.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	private const CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

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
	 * @psalm-api
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
	 * @since 1.7.0 The before and after actions receive the validated product IDs.
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
		$valid_product_ids = array_keys( $valid_products );

		/**
		 * Fires before the coupons of one request are generated.
		 *
		 * The admin screen sends one run as several requests, so this fires once
		 * per request, not once per run.
		 *
		 * @since 1.7.0 The first argument is the validated list of product IDs.
		 *
		 * @param array<int> $valid_product_ids Validated gift product IDs.
		 * @param int        $count             Number of coupons this request will try to create.
		 */
		do_action( 'fgcbg_before_coupon_generation', $valid_product_ids, $generation_params['count'] );

		$result = $this->execute_coupon_generation( $valid_products, $gift_info, $generation_params );

		/**
		 * Fires after the coupons of one request have been generated.
		 *
		 * @since 1.7.0 The first argument is the validated list of product IDs.
		 *
		 * @param array<int> $valid_product_ids Validated gift product IDs.
		 * @param int        $generated         Number of coupons this request created.
		 */
		do_action( 'fgcbg_after_coupon_generation', $valid_product_ids, $result['generated'] );

		return $result;
	}

	/**
	 * Check whether every given ID is a product that can be given away.
	 *
	 * False when an ID is not a product, when a product is in the trash, or
	 * when there are more products than one coupon can carry.
	 *
	 * @since 1.7.0
	 * @param array<int> $product_ids Product IDs to check.
	 * @return bool True when all IDs can be used.
	 */
	public function are_products_usable( array $product_ids ): bool {
		$unique_ids = array_unique( array_filter( array_map( 'absint', $product_ids ) ) );

		return count( $unique_ids ) > 0 && count( $this->validate_products( $unique_ids ) ) === count( $unique_ids );
	}

	/**
	 * Validate products for coupon generation.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Products in the trash are not valid, and nothing is valid when
	 *              more than MAX_GIFT_PRODUCTS products are given.
	 * @param array<int>|int $product_ids Product IDs to validate.
	 * @return array<int, WC_Product> Array of valid product objects keyed by ID.
	 */
	private function validate_products( array|int $product_ids ): array {
		if ( ! is_array( $product_ids ) ) {
			$product_ids = array( $product_ids );
		}

		$product_ids = array_unique( array_filter( array_map( 'absint', $product_ids ) ) );
		if ( count( $product_ids ) > self::MAX_GIFT_PRODUCTS ) {
			return array();
		}

		$valid_products = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && 'trash' !== $product->get_status() ) {
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

		/**
		 * Filters the maximum number of coupons that one request can create.
		 *
		 * Values below 1 are raised to 1. The AJAX handler separately limits a
		 * request to MAX_COUPONS_PER_BATCH, so a higher value only affects code
		 * that calls this class directly.
		 *
		 * @param int $max_count Maximum coupons per request. Default 100.
		 */
		$max_count = max( 1, (int) apply_filters( 'fgcbg_max_coupons_per_batch', self::MAX_COUPONS_PER_BATCH ) );
		$count     = min( $requested_count, $max_count );

		/**
		 * Filters the number of days until a generated coupon expires.
		 *
		 * Values below 1 are raised to 1.
		 *
		 * @param int $expiry_days Days until expiry. Default 365.
		 */
		$expiry_days = max( 1, (int) apply_filters( 'fgcbg_coupon_expiry_days', self::DEFAULT_EXPIRY_DAYS ) );
		$code_length = $this->normalize_code_length( $code_length );

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

		while ( $generated_count < $params['count'] && $attempt_count < $params['max_attempts'] ) {
			++$attempt_count;

			$generated_code = $this->create_single_coupon( $valid_products, $gift_info, $params );

			if ( null !== $generated_code ) {
				++$generated_count;
				$generated_codes[] = $generated_code;
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
	 * A code that already exists is not an error: the caller simply tries
	 * again. Every other failure is logged.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 A save without a coupon ID is a failure, and an exception from
	 *              a `fgcbg_coupon_generated` callback no longer discards the saved coupon.
	 * @param array<int, WC_Product>                                                              $valid_products Array of valid product objects.
	 * @param array<int, array{product_id:int, variation_id:int, quantity:int}>                   $gift_info      Gift information array.
	 * @param array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} $params         Generation parameters.
	 * @return string|null Generated coupon code, or null when creation failed.
	 */
	private function create_single_coupon( array $valid_products, array $gift_info, array $params ): ?string {
		try {
			$coupon      = new WC_Coupon();
			$random_code = $this->generate_coupon_code( $params['prefix'], $params['code_length'] );
			if ( $this->coupon_code_exists( $random_code ) ) {
				return null;
			}

			$this->set_coupon_properties( $coupon, $random_code, $valid_products, $params );
			$this->set_coupon_metadata( $coupon, $gift_info );

			$coupon->save();

			// WooCommerce can return from save() without creating the coupon and without throwing.
			$coupon_id = $coupon->get_id();
			if ( $coupon_id < 1 ) {
				$this->log_coupon_error( new \RuntimeException( 'WooCommerce saved the coupon without returning an ID.' ) );
				return null;
			}
		} catch ( \Throwable $e ) {
			$this->log_coupon_error( $e );
			return null;
		}

		// The coupon exists from here on. A failing callback must not hide it from the caller.
		try {
			/**
			 * Fires after one coupon has been saved.
			 *
			 * An exception thrown by a callback is logged and does not undo the coupon.
			 *
			 * @param int        $coupon_id   ID of the new coupon.
			 * @param array<int> $product_ids Validated gift product IDs.
			 */
			do_action( 'fgcbg_coupon_generated', $coupon_id, array_keys( $valid_products ) );
		} catch ( \Throwable $e ) {
			$this->log_coupon_error( $e );
		}

		return $random_code;
	}

	/**
	 * Set coupon properties.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 The description no longer carries a per-request sequence number.
	 * @param WC_Coupon                                                                           $coupon         The coupon object.
	 * @param string                                                                              $code           The coupon code.
	 * @param array<int, WC_Product>                                                              $valid_products Array of valid product objects.
	 * @param array{count:int, prefix:string, code_length:int, expiry_days:int, max_attempts:int} $params         Generation parameters.
	 * @return void
	 */
	private function set_coupon_properties( WC_Coupon $coupon, string $code, array $valid_products, array $params ): void {
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
				/* translators: %s: List of gift product names. */
				__( 'Auto-generated coupon for %s', 'free-gift-bulk-coupon-generator' ),
				$products_text
			)
		);
		$coupon->set_discount_type( self::DISCOUNT_TYPE );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_date_expires( current_datetime()->getTimestamp() + ( $params['expiry_days'] * DAY_IN_SECONDS ) );
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
	 * Log coupon generation errors.
	 *
	 * Errors are written to the WooCommerce log (source
	 * `free-gift-bulk-coupon-generator`) on every site, not only in debug mode.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 No longer limited to WP_DEBUG.
	 * @param \Throwable $exception The exception that occurred.
	 * @return void
	 */
	private function log_coupon_error( \Throwable $exception ): void {
		wc_get_logger()->error(
			sprintf(
				/* translators: 1: Exception class name, 2: Error message. */
				__( 'FGCBG Error generating coupon [%1$s]: %2$s', 'free-gift-bulk-coupon-generator' ),
				$exception::class,
				$exception->getMessage()
			),
			array( 'source' => 'free-gift-bulk-coupon-generator' )
		);
	}

	/**
	 * Generate a candidate coupon code.
	 *
	 * Each character is drawn uniformly from CODE_ALPHABET with wp_rand(),
	 * which uses the PHP cryptographic random source. The caller checks
	 * whether the code is already in use.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 No longer uses wp_generate_password(), so `random_password`
	 *              filters cannot change coupon codes.
	 * @param string $prefix      Normalized prefix for the coupon code, or an empty string.
	 * @param int    $code_length Generated random code length, excluding the optional prefix.
	 * @return string Generated coupon code.
	 */
	private function generate_coupon_code( string $prefix, int $code_length ): string {
		$last_index  = strlen( self::CODE_ALPHABET ) - 1;
		$random_part = '';

		for ( $position = 0; $position < $code_length; $position++ ) {
			$random_part .= self::CODE_ALPHABET[ wp_rand( 0, $last_index ) ];
		}

		return $prefix . $random_part;
	}

	/**
	 * Check whether a coupon code already exists.
	 *
	 * @since 1.6.0
	 * @param string $code Coupon code.
	 * @return bool True when WooCommerce already has a coupon with this code.
	 */
	private function coupon_code_exists( string $code ): bool {
		return wc_get_coupon_id_by_code( $code ) > 0;
	}

	/**
	 * Normalize an optional coupon prefix.
	 *
	 * WooCommerce stores coupon codes in lower case, so the prefix is too.
	 *
	 * @since 1.6.0
	 * @param string $prefix Raw coupon prefix.
	 * @return string Lowercase alphanumeric prefix limited to eight characters.
	 */
	private function normalize_prefix( string $prefix ): string {
		$prefix = (string) preg_replace( '/[^A-Za-z0-9]/', '', $prefix );

		return strtolower( substr( $prefix, 0, self::MAX_PREFIX_LENGTH ) );
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

		/**
		 * Filters the length of the random part of a coupon code.
		 *
		 * The result is kept between MIN_CODE_LENGTH and MAX_CODE_LENGTH.
		 *
		 * @param int      $requested_length Requested length, or the default of 12 when none was given.
		 * @param int|null $code_length      Length as passed by the caller; null when none was given.
		 */
		$filtered_length = (int) apply_filters( 'fgcbg_coupon_code_length', $requested_length, $code_length );

		return max( self::MIN_CODE_LENGTH, min( self::MAX_CODE_LENGTH, $filtered_length ) );
	}
}
