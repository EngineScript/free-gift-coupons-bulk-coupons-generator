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
	 * Reset the WooCommerce test doubles.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->set_test_products(
			array(
				123 => new FGCBG_Test_Product( 'Sample Mug' ),
				321 => new FGCBG_Test_Product( '<strong>Gift</strong> <script>alert(1)</script>Card' ),
				456 => new FGCBG_Test_Product( 'Sticker Pack' ),
				789 => new FGCBG_Test_Product( 'Sample Hoodie - Blue', 456 ),
			)
		);
		$this->clear_test_coupons();
		$this->set_test_passwords( array() );
		$this->reset_test_current_time();
	}

	/**
	 * Reset mutable test clock state.
	 */
	protected function tearDown(): void {
		$this->reset_test_current_time();

		parent::tearDown();
	}

	/**
	 * Free gift coupons get the metadata required by Free Gift Coupons for WooCommerce.
	 */
	public function test_generates_free_gift_coupon_with_expected_metadata(): void {
		$generator                = new FGCBG_Coupon_Generator();
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
	 * The batch-size filter defines the maximum allowed count, not the requested count.
	 */
	public function test_coupon_generation_clamps_to_filtered_batch_maximum(): void {
		$filter = static function () {
			return 2;
		};

		add_filter( 'fgcbg_max_coupons_per_batch', $filter );

		$generator = new FGCBG_Coupon_Generator();
		$generated = $generator->generate_coupons( array( 123 ), 5 );

		remove_filter( 'fgcbg_max_coupons_per_batch', $filter );

		$this->assertSame( 2, $generated );
		$this->assertCount( 2, $this->get_test_coupons() );
	}

	/**
	 * Duplicate generated codes are skipped and retried.
	 */
	public function test_duplicate_coupon_codes_are_retried(): void {
		$this->set_test_passwords(
			array(
				'duplicate1',
				'duplicate1',
				'unique2222',
			)
		);

		$generator = new FGCBG_Coupon_Generator();

		$result = $generator->generate_coupon_batch( array( 123 ), 2, '', 10 );

		$this->assertSame( 2, $result['generated'] );
		$this->assertSame( array( 'duplicate1', 'unique2222' ), $result['codes'] );
		$this->assertCount( 2, $this->get_test_coupons() );
	}
}
