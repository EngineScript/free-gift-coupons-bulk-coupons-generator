<?php
/**
 * Coupon generator tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for WooCommerce coupon creation behavior.
 */
final class CouponGeneratorTest extends TestCase {
	use FGCBG_Test_Stub_State;

	/**
	 * Characters the generator may use for the random part of a code.
	 */
	private const CODE_CHARACTERS = 'abcdefghjkmnpqrstuvwxyz23456789';

	/**
	 * Hooks that tests in this class register callbacks on.
	 */
	private const TEST_HOOKS = array(
		'fgcbg_before_coupon_generation',
		'fgcbg_after_coupon_generation',
		'fgcbg_coupon_generated',
		'fgcbg_max_coupons_per_batch',
		'fgcbg_coupon_code_length',
	);

	/**
	 * Reset the WooCommerce test doubles.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->set_test_products(
			array(
				123 => new FGCBG_Test_Product( 'Sample Mug' ),
				321 => new FGCBG_Test_Product( '<strong>Gift</strong> <script>alert(1)</script>Card' ),
				456 => new FGCBG_Test_Product( 'Sticker Pack' ),
				500 => new FGCBG_Test_Product( 'Retired Mug', 0, 'trash' ),
				600 => new FGCBG_Test_Product( 'Unreleased Mug', 0, 'draft' ),
				789 => new FGCBG_Test_Product( 'Sample Hoodie - Blue', 456 ),
			)
		);
		$this->reset_test_generation_state();
		$this->reset_test_current_time();
	}

	/**
	 * Reset mutable test state.
	 */
	protected function tearDown(): void {
		$this->remove_test_hooks( self::TEST_HOOKS );
		$this->reset_test_generation_state();
		$this->reset_test_current_time();

		parent::tearDown();
	}

	/**
	 * Free gift coupons get the metadata required by Free Gift Coupons for WooCommerce.
	 */
	public function test_generates_free_gift_coupon_with_expected_metadata(): void {
		$generator = new FGCBG_Coupon_Generator();
		$this->set_test_current_time( gmdate( 'Y-m-d H:i:s', DAY_IN_SECONDS ) );
		$expected_generation_date = $this->get_test_current_time();

		$generated = $generator->generate_coupons( array( 123, 456 ), 1, 'GIFT' );

		$this->assertSame( 1, $generated );
		$this->assertCount( 1, $this->get_test_coupons() );

		$coupon = $this->get_test_coupon();

		$this->assertStringStartsWith( 'gift', $coupon->get_code() );
		$this->assertSame( 'free_gift', $coupon->get_discount_type() );
		$this->assertTrue( $coupon->get_prop( 'individual_use' ) );
		$this->assertSame( 1, $coupon->get_prop( 'usage_limit' ) );
		$this->assertSame( DAY_IN_SECONDS + ( 365 * DAY_IN_SECONDS ), $coupon->get_prop( 'date_expires' ) );
		$this->assertSame( array( 123, 456 ), $coupon->get_meta( '_fgcbg_product_ids' ) );
		$this->assertTrue( $coupon->get_meta( '_fgcbg_generated' ) );
		$this->assertSame( $expected_generation_date, $coupon->get_meta( '_fgcbg_generation_date' ) );
		$this->assertSame(
			array(
				123 => array(
					'product_id'   => 123,
					'variation_id' => 0,
					'quantity'     => 1,
				),
				456 => array(
					'product_id'   => 456,
					'variation_id' => 0,
					'quantity'     => 1,
				),
			),
			$coupon->get_meta( '_wc_free_gift_coupon_data' )
		);
	}

	/**
	 * Variations are keyed by variation ID with parent product ID nested inside.
	 */
	public function test_variation_gift_coupon_metadata_uses_upstream_shape(): void {
		$generator = new FGCBG_Coupon_Generator();

		$generated = $generator->generate_coupons( array( 789 ), 1 );

		$this->assertSame( 1, $generated );

		$coupon = $this->get_test_coupon();

		$this->assertSame(
			array(
				789 => array(
					'product_id'   => 456,
					'variation_id' => 789,
					'quantity'     => 1,
				),
			),
			$coupon->get_meta( '_wc_free_gift_coupon_data' )
		);
		$this->assertSame( array( 789 ), $coupon->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * Invalid product IDs should not produce coupons.
	 */
	public function test_invalid_products_do_not_generate_coupons(): void {
		$generator = new FGCBG_Coupon_Generator();

		$generated = $generator->generate_coupons( array( 999 ), 3 );

		$this->assertSame( 0, $generated );
		$this->assertSame( array(), $this->get_test_coupons() );
	}

	/**
	 * A product in the trash cannot be given away; a draft still can.
	 */
	public function test_trashed_products_are_not_valid_gifts(): void {
		$generator = new FGCBG_Coupon_Generator();

		$this->assertSame( 0, $generator->generate_coupons( array( 500 ), 1 ) );
		$this->assertSame( array(), $this->get_test_coupons() );

		$this->assertSame( 1, $generator->generate_coupons( array( 123, 500, 600 ), 1 ) );
		$this->assertSame( array( 123, 600 ), $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * One coupon carries at most MAX_GIFT_PRODUCTS products.
	 */
	public function test_more_than_the_maximum_gift_products_generates_nothing(): void {
		$products = array();
		for ( $product_id = 1; $product_id <= FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS + 1; $product_id++ ) {
			$products[ $product_id ] = new FGCBG_Test_Product( 'Product ' . $product_id );
		}
		$this->set_test_products( $products );

		$generator = new FGCBG_Coupon_Generator();
		$all_ids   = array_keys( $products );

		$this->assertSame( 0, $generator->generate_coupons( $all_ids, 1 ) );
		$this->assertSame( array(), $this->get_test_coupons() );

		$allowed_ids = array_slice( $all_ids, 0, FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS );

		$this->assertSame( 1, $generator->generate_coupons( $allowed_ids, 1 ) );
		$this->assertSame( $allowed_ids, $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * The usability check is all or nothing.
	 */
	public function test_are_products_usable_requires_every_product_to_be_usable(): void {
		$generator = new FGCBG_Coupon_Generator();

		$this->assertTrue( $generator->are_products_usable( array( 123 ) ) );
		$this->assertTrue( $generator->are_products_usable( array( 123, 456, 123, 600, 789 ) ) );
		$this->assertFalse( $generator->are_products_usable( array() ) );
		$this->assertFalse( $generator->are_products_usable( array( 999 ) ) );
		$this->assertFalse( $generator->are_products_usable( array( 123, 999 ) ) );
		$this->assertFalse( $generator->are_products_usable( array( 123, 500 ) ) );
	}

	/**
	 * Coupon prefixes are normalized before code generation.
	 */
	public function test_coupon_prefix_is_normalized(): void {
		$generator = new FGCBG_Coupon_Generator();

		$result = $generator->generate_coupon_batch( array( 123 ), 1, 'summer gift! 2026', 8 );

		$this->assertSame( 1, $result['generated'] );
		$this->assertCount( 1, $result['codes'] );

		$coupon = $this->get_test_coupon();

		$this->assertStringStartsWith( 'summergi', $coupon->get_code() );
		$this->assertSame( $coupon->get_code(), $result['codes'][0] );
		$this->assertSame( 16, strlen( $coupon->get_code() ) );
		$this->assertSame( 'free_gift', $coupon->get_discount_type() );
		$this->assertNotNull( $coupon->get_meta( '_wc_free_gift_coupon_data' ) );
		$this->assertSame( array( 123 ), $coupon->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * Requested random code length is honored within configured bounds.
	 */
	public function test_coupon_code_length_is_user_configurable(): void {
		$generator = new FGCBG_Coupon_Generator();

		$result = $generator->generate_coupon_batch( array( 123 ), 1, '', 20 );

		$this->assertSame( 1, $result['generated'] );
		$this->assertCount( 1, $result['codes'] );
		$this->assertSame( 20, strlen( $result['codes'][0] ) );
	}

	/**
	 * Without a requested length, codes have 12 characters from the fixed alphabet.
	 */
	public function test_default_codes_use_twelve_characters_from_the_code_alphabet(): void {
		$generator = new FGCBG_Coupon_Generator();

		$result = $generator->generate_coupon_batch( array( 123 ), 25 );

		$this->assertSame( 12, FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH );
		$this->assertSame( 25, $result['generated'] );
		$this->assertCount( 25, array_unique( $result['codes'] ) );

		foreach ( $result['codes'] as $code ) {
			$this->assertMatchesRegularExpression( '/\A[' . self::CODE_CHARACTERS . ']{12}\z/', $code );
		}
	}

	/**
	 * Each character is one draw from the alphabet, in order.
	 */
	public function test_code_characters_come_from_wp_rand_positions(): void {
		$this->queue_test_coupon_codes( array( 'abcdefgh' ) );

		$generator = new FGCBG_Coupon_Generator();
		$result    = $generator->generate_coupon_batch( array( 123 ), 1, 'Gift', 8 );

		$this->assertSame( array( 'giftabcdefgh' ), $result['codes'] );
	}

	/**
	 * A filtered code length stays within the allowed range.
	 */
	public function test_filtered_code_length_is_kept_within_bounds(): void {
		$generator = new FGCBG_Coupon_Generator();

		add_filter(
			'fgcbg_coupon_code_length',
			static function () {
				return 100;
			}
		);
		$too_long = $generator->generate_coupon_batch( array( 123 ), 1 );

		$this->remove_test_hooks( array( 'fgcbg_coupon_code_length' ) );
		add_filter(
			'fgcbg_coupon_code_length',
			static function () {
				return 1;
			}
		);
		$too_short = $generator->generate_coupon_batch( array( 123 ), 1 );

		$this->assertSame( FGCBG_Coupon_Generator::MAX_CODE_LENGTH, strlen( $too_long['codes'][0] ) );
		$this->assertSame( FGCBG_Coupon_Generator::MIN_CODE_LENGTH, strlen( $too_short['codes'][0] ) );
	}

	/**
	 * Product names are normalized before being stored in coupon descriptions.
	 */
	public function test_coupon_description_strips_product_name_markup(): void {
		$generator = new FGCBG_Coupon_Generator();

		$generated = $generator->generate_coupons( array( 321 ), 1 );

		$this->assertSame( 1, $generated );

		$coupon = $this->get_test_coupon();

		$this->assertStringNotContainsString( '<script>', $coupon->get_prop( 'description' ) );
		$this->assertStringNotContainsString( '<strong>', $coupon->get_prop( 'description' ) );
	}

	/**
	 * The description names the gift products and carries no batch numbering.
	 */
	public function test_coupon_description_names_the_gift_products(): void {
		$generator = new FGCBG_Coupon_Generator();

		$generator->generate_coupons( array( 123, 456 ), 2 );

		$this->assertSame( 'Auto-generated coupon for Sample Mug, Sticker Pack', $this->get_test_coupon( 0 )->get_prop( 'description' ) );
		$this->assertSame( 'Auto-generated coupon for Sample Mug, Sticker Pack', $this->get_test_coupon( 1 )->get_prop( 'description' ) );
	}

	/**
	 * The batch-size filter defines the maximum allowed count, not the requested count.
	 */
	public function test_coupon_generation_clamps_to_filtered_batch_maximum(): void {
		add_filter(
			'fgcbg_max_coupons_per_batch',
			static function () {
				return 2;
			}
		);

		$generator = new FGCBG_Coupon_Generator();
		$generated = $generator->generate_coupons( array( 123 ), 5 );

		$this->assertSame( 2, $generated );
		$this->assertCount( 2, $this->get_test_coupons() );
	}

	/**
	 * Duplicate generated codes are skipped and retried.
	 */
	public function test_duplicate_coupon_codes_are_retried(): void {
		$this->queue_test_coupon_codes(
			array(
				'dupe222222',
				'dupe222222',
				'fresh33333',
			)
		);

		$generator = new FGCBG_Coupon_Generator();

		$result = $generator->generate_coupon_batch( array( 123 ), 2, '', 10 );

		$this->assertSame( 2, $result['generated'] );
		$this->assertSame( array( 'dupe222222', 'fresh33333' ), $result['codes'] );
		$this->assertCount( 2, $this->get_test_coupons() );
		$this->assertSame( array(), $this->get_test_log_messages(), 'A duplicate code is retried without being logged.' );
	}

	/**
	 * A save that returns no coupon ID is a failure, logged once per attempt.
	 */
	public function test_coupon_saved_without_an_id_is_not_counted(): void {
		$this->set_test_coupon_save_mode( 'no_id' );

		$generator = new FGCBG_Coupon_Generator();
		$result    = $generator->generate_coupon_batch( array( 123 ), 5 );

		$this->assertSame(
			array(
				'generated' => 0,
				'codes'     => array(),
			),
			$result
		);
		$this->assertSame( array(), $this->get_test_coupons() );
		// Attempts are bounded at max( 2 * count, count + 10 ).
		$this->assertCount( 15, $this->get_test_log_messages() );
	}

	/**
	 * A save that throws is logged with the exception class and message.
	 */
	public function test_failed_save_is_logged(): void {
		$this->set_test_coupon_save_mode( 'throw' );

		$generator = new FGCBG_Coupon_Generator();

		$this->assertSame( 0, $generator->generate_coupons( array( 123 ), 1 ) );

		$messages = $this->get_test_log_messages();

		$this->assertCount( 11, $messages );
		$this->assertStringContainsString( '[RuntimeException]: Coupon could not be saved.', $messages[0] );
	}

	/**
	 * A callback that throws after the save must not hide the saved coupon.
	 */
	public function test_throwing_coupon_generated_callback_keeps_the_saved_coupon(): void {
		add_action(
			'fgcbg_coupon_generated',
			static function (): void {
				throw new LogicException( 'callback failed' );
			}
		);

		$generator = new FGCBG_Coupon_Generator();
		$result    = $generator->generate_coupon_batch( array( 123 ), 3 );

		$this->assertSame( 3, $result['generated'] );
		$this->assertCount( 3, $result['codes'] );
		$this->assertCount( 3, $this->get_test_coupons(), 'No replacement coupon may be created.' );

		$messages = $this->get_test_log_messages();

		$this->assertCount( 3, $messages );
		$this->assertStringContainsString( '[LogicException]: callback failed', $messages[0] );
	}

	/**
	 * All three actions receive the validated product IDs.
	 */
	public function test_generation_actions_receive_validated_product_ids(): void {
		$calls = array();

		foreach ( array( 'fgcbg_before_coupon_generation', 'fgcbg_after_coupon_generation', 'fgcbg_coupon_generated' ) as $hook ) {
			add_action(
				$hook,
				static function ( $first, $second ) use ( &$calls, $hook ): void {
					$calls[] = array( $hook, $first, $second );
				},
				10,
				2
			);
		}

		$generator = new FGCBG_Coupon_Generator();
		$generator->generate_coupon_batch( array( '123', 999, 123 ), 2 );

		$this->assertSame(
			array(
				array( 'fgcbg_before_coupon_generation', array( 123 ), 2 ),
				array( 'fgcbg_coupon_generated', 1, array( 123 ) ),
				array( 'fgcbg_coupon_generated', 2, array( 123 ) ),
				array( 'fgcbg_after_coupon_generation', array( 123 ), 2 ),
			),
			$calls
		);
	}
}
