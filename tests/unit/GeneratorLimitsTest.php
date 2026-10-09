<?php
/**
 * Coupon generator limit and filter tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Tests for the limits the generator applies to its input and to filter results.
 */
final class GeneratorLimitsTest extends FGCBG_Test_Case {

	/**
	 * Hooks these tests register.
	 *
	 * @var array<int, string>
	 */
	private const HOOKS = array(
		'fgcbg_max_coupons_per_batch',
		'fgcbg_coupon_expiry_days',
		'fgcbg_coupon_code_length',
	);

	/**
	 * Prepare the WooCommerce stand-ins.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->remove_test_hooks( self::HOOKS );
		$this->set_test_products(
			array(
				123 => new FGCBG_Test_Product( 'Sample Mug' ),
				124 => new FGCBG_Test_Product( '<b></b>' ),
			)
		);
		$this->reset_test_generation_state();
		$this->set_test_current_time( gmdate( 'Y-m-d H:i:s', DAY_IN_SECONDS ) );
	}

	/**
	 * Return the stand-ins to their defaults.
	 */
	protected function tearDown(): void {
		$this->remove_test_hooks( self::HOOKS );
		$this->reset_test_generation_state();
		$this->reset_test_current_time();

		parent::tearDown();
	}

	/**
	 * Make a filter return one fixed value.
	 *
	 * @param string $hook  Filter name.
	 * @param mixed  $value Value to return.
	 */
	private function filter_returns( string $hook, mixed $value ): void {
		$this->remove_test_hooks( array( $hook ) );
		add_filter(
			$hook,
			static function () use ( $value ) {
				return $value;
			}
		);
	}

	/**
	 * Generate coupons with every PHP warning, notice, and deprecation turned into a failure.
	 *
	 * @param int $count Coupons to ask for.
	 * @return array{generated:int, codes:array<int, string>}
	 */
	private function generate_strictly( int $count ): array {
		set_error_handler(
			static function ( int $severity, string $text ): bool {
				throw new ErrorException( $text, 0, $severity );
			}
		);

		try {
			return ( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array( 123 ), $count );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * A product ID that is repeated counts once, also toward the limit of 20 gift products.
	 */
	public function test_repeated_product_ids_count_once(): void {
		$result = ( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array_fill( 0, FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS + 1, 123 ), 1 );

		$this->assertSame( 1, $result['generated'] );
		$this->assertSame( array( 123 ), $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * One product ID that is not in a list is accepted.
	 */
	public function test_single_product_id_is_accepted(): void {
		$generator = new FGCBG_Coupon_Generator();

		$this->assertSame( 1, $generator->generate_coupon_batch( 123, 1 )['generated'] );
		$this->assertSame( 2, $generator->generate_coupons( 123, 2 ) );
	}

	/**
	 * A product whose name is empty once its markup is removed is left out of the description.
	 */
	public function test_product_without_a_name_is_left_out_of_the_description(): void {
		( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array( 123, 124 ), 1 );

		$this->assertSame( 'Auto-generated coupon for Sample Mug', $this->get_test_coupon()->get_prop( 'description' ) );
	}

	/**
	 * The per-request maximum cannot be filtered below one coupon.
	 */
	public function test_maximum_per_request_is_at_least_one(): void {
		foreach ( array( 0, -5, false ) as $position => $value ) {
			$this->clear_test_coupons();
			$this->filter_returns( 'fgcbg_max_coupons_per_batch', $value );

			$this->assertSame( 1, $this->generate_strictly( 5 )['generated'], sprintf( 'Case %d.', $position ) );
		}
	}

	/**
	 * An expiry filtered to zero or less becomes one day.
	 */
	public function test_expiry_is_at_least_one_day(): void {
		foreach ( array( 0, -30 ) as $days ) {
			$this->clear_test_coupons();
			$this->filter_returns( 'fgcbg_coupon_expiry_days', $days );
			$this->generate_strictly( 1 );

			$this->assertSame( 2 * DAY_IN_SECONDS, $this->get_test_coupon()->get_prop( 'date_expires' ), (string) $days );
		}
	}

	/**
	 * Filter results that are not integers are read without a PHP warning.
	 *
	 * Each value is given to the per-request maximum while two coupons are
	 * asked for, so the number created shows what the value was read as.
	 */
	public function test_filter_results_that_are_not_integers(): void {
		$cases = array(
			array( 'not a number is 0, raised to 1', NAN, 1 ),
			array( 'null is 0, raised to 1', null, 1 ),
			array( 'a list is 0, raised to 1', array( 50 ), 1 ),
			array( 'an object is 0, raised to 1', new stdClass(), 1 ),
			array( 'text is 0, raised to 1', 'many', 1 ),
			array( 'below the integer range is the lowest integer', -1e30, 1 ),
			array( 'negative infinity is the lowest integer', -INF, 1 ),
			array( 'above the integer range is the highest integer', 1e30, 2 ),
			array( 'infinity is the highest integer', INF, 2 ),
			array( 'a numeric string is its number', '50', 2 ),
			array( 'a numeric string of infinity is the highest integer', '1e1000', 2 ),
			array( 'a fraction loses its fraction', 1.9, 1 ),
		);

		foreach ( $cases as $case ) {
			$this->clear_test_coupons();
			$this->filter_returns( 'fgcbg_max_coupons_per_batch', $case[1] );

			$this->assertSame( $case[2], $this->generate_strictly( 2 )['generated'], $case[0] );
		}
	}

	/**
	 * The code length filter receives the length in use and the length the caller passed.
	 */
	public function test_code_length_filter_receives_both_lengths(): void {
		$received = array();
		add_filter(
			'fgcbg_coupon_code_length',
			static function ( $requested_length, $code_length ) use ( &$received ) {
				$received[] = array( $requested_length, $code_length );

				return $requested_length;
			},
			10,
			2
		);
		$generator = new FGCBG_Coupon_Generator();

		$generator->generate_coupon_batch( array( 123 ), 1 );
		$generator->generate_coupon_batch( array( 123 ), 1, '', 16 );

		$this->assertSame(
			array(
				array( FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH, null ),
				array( 16, 16 ),
			),
			$received
		);
	}

	/**
	 * Every character of the alphabet can be drawn, and none outside it.
	 */
	public function test_random_draws_cover_the_whole_alphabet(): void {
		$alphabet = (string) ( new ReflectionClassConstant( FGCBG_Coupon_Generator::class, 'CODE_ALPHABET' ) )->getValue();

		( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array( 123 ), 1, '', 8 );

		$calls = $this->get_test_rand_calls();
		$this->assertCount( 8, $calls, 'One draw per character.' );
		foreach ( $calls as $call ) {
			$this->assertSame( array( 0, strlen( $alphabet ) - 1 ), $call );
		}
	}

	/**
	 * A failed save is written to the WooCommerce log under the plugin's own source.
	 */
	public function test_failed_save_is_logged_under_the_plugin_source(): void {
		$this->set_test_coupon_save_mode( 'throw' );

		$result = ( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array( 123 ), 1 );

		$this->assertSame( 0, $result['generated'] );
		$this->assertNotEmpty( $this->get_test_log_contexts() );
		foreach ( $this->get_test_log_contexts() as $context ) {
			$this->assertSame( array( 'source' => 'free-gift-bulk-coupon-generator' ), $context );
		}
	}

	/**
	 * A prefix that ends in a line break loses it, like every other character that is not a letter or digit.
	 */
	public function test_prefix_with_a_trailing_line_break(): void {
		foreach ( array( "gift\n", "gift\r\n", "gi\nft", "\ngift" ) as $prefix ) {
			$code = ( new FGCBG_Coupon_Generator() )->generate_coupon_batch( array( 123 ), 1, $prefix, 8 )['codes'][0];

			$this->assertMatchesRegularExpression( '/\Agift[a-z0-9]{8}\z/', $code, addcslashes( $prefix, "\r\n" ) );
		}
	}
}
