<?php
/**
 * Request value tests for the AJAX handler.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Tests for how the handler reads, bounds, and refuses request values.
 */
final class RequestInputTest extends FGCBG_Test_Case {

	/**
	 * Hooks these tests register.
	 *
	 * @var array<int, string>
	 */
	private const HOOKS = array(
		'fgcbg_max_coupons_per_batch',
		'fgcbg_coupon_code_length',
	);

	/**
	 * Reset request globals and WooCommerce test doubles.
	 */
	protected function setUp(): void {
		parent::setUp();

		$products = array( 123 => new FGCBG_Test_Product( 'Sample Mug' ) );
		for ( $product_id = 201; $product_id <= 221; $product_id++ ) {
			$products[ $product_id ] = new FGCBG_Test_Product( 'Gift ' . $product_id );
		}

		$this->remove_test_hooks( self::HOOKS );
		$this->reset_test_request();
		$this->set_test_products( $products );
		$this->reset_test_generation_state();
		$this->set_test_current_user_can( true );
		$this->set_test_current_user_capabilities( array() );
	}

	/**
	 * Clear request globals and stub state.
	 */
	protected function tearDown(): void {
		$this->remove_test_hooks( self::HOOKS );
		$this->reset_test_request();
		$this->reset_test_generation_state();

		parent::tearDown();
	}

	/**
	 * Send a request through the handler and return the JSON response it ends with.
	 *
	 * @param array<string, mixed> $post_data POST data. A valid nonce is added.
	 * @return array<string, mixed> Response recorded by the wp_send_json_*() stand-ins.
	 */
	private function send_request( array $post_data ): array {
		$this->clear_test_coupons();
		$this->set_test_post_data( $post_data + array( 'nonce' => 'nonce-fgcbg_ajax_nonce' ) );

		try {
			( new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() ) )->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			return $response->response;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * Record the first argument the code length filter receives.
	 *
	 * @return ArrayObject<int, mixed> Filled as requests are sent.
	 */
	private function record_requested_code_lengths(): ArrayObject {
		$seen = new ArrayObject();
		add_filter(
			'fgcbg_coupon_code_length',
			static function ( $requested_length ) use ( $seen ) {
				$seen->append( $requested_length );

				return $requested_length;
			}
		);

		return $seen;
	}

	/**
	 * The handler limits a request to 100 coupons even when the generator's own maximum is raised.
	 */
	public function test_handler_keeps_its_own_limit_when_the_maximum_filter_is_raised(): void {
		add_filter(
			'fgcbg_max_coupons_per_batch',
			static function () {
				return 1000;
			}
		);

		$response = $this->send_request(
			array(
				'product_ids' => array( '123' ),
				'batch_size'  => '500',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH, $response['data']['generated'] );
	}

	/**
	 * The handler bounds the requested code length before the length filter sees it.
	 */
	public function test_handler_bounds_the_code_length_before_the_filter(): void {
		$seen = $this->record_requested_code_lengths();

		foreach ( array( '3', '99', '16' ) as $length ) {
			$this->send_request(
				array(
					'product_ids'        => array( '123' ),
					'batch_size'         => '1',
					'coupon_code_length' => $length,
				)
			);
		}

		$this->assertSame( array( FGCBG_Coupon_Generator::MIN_CODE_LENGTH, FGCBG_Coupon_Generator::MAX_CODE_LENGTH, 16 ), $seen->getArrayCopy() );
	}

	/**
	 * A request without products is refused with the message that asks for one.
	 */
	public function test_request_without_products_asks_for_a_product(): void {
		foreach ( array( array(), array( 'product_ids' => array() ), array( 'product_ids' => array( '0', 'abc' ) ) ) as $post_data ) {
			$response = $this->send_request( $post_data + array( 'batch_size' => '1' ) );

			$this->assertFalse( $response['success'] );
			$this->assertSame( 400, $response['status'] );
			$this->assertStringContainsString( 'select at least one product', $response['data']['message'] );
			$this->assertCount( 0, $this->get_test_coupons() );
		}
	}

	/**
	 * Entries that are not positive numbers, and repeated IDs, do not count toward the limit of 20 products.
	 */
	public function test_unusable_and_repeated_ids_do_not_count_toward_the_product_limit(): void {
		$twenty = array_map( 'strval', range( 201, 220 ) );

		$accepted = $this->send_request(
			array(
				'product_ids' => array_merge( $twenty, array( '0', 'abc', '', '201', '220' ) ),
				'batch_size'  => '1',
			)
		);
		$this->assertTrue( $accepted['success'], 'Twenty products with noise are accepted.' );
		$this->assertSame( range( 201, 220 ), $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );

		$refused = $this->send_request(
			array(
				'product_ids' => array_merge( $twenty, array( '221' ) ),
				'batch_size'  => '1',
			)
		);
		$this->assertFalse( $refused['success'] );
		$this->assertSame( 400, $refused['status'] );
		$this->assertStringContainsString( (string) FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS, $refused['data']['message'] );
		$this->assertCount( 0, $this->get_test_coupons() );
	}

	/**
	 * A value that ends in a line break is read as the value before it.
	 */
	public function test_values_that_end_in_a_line_break(): void {
		$seen     = $this->record_requested_code_lengths();
		$response = $this->send_request(
			array(
				'product_ids'        => array( "123\n" ),
				'batch_size'         => "3\n",
				'coupon_prefix'      => "gift\n",
				'coupon_code_length' => "16\r\n",
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 3, $response['data']['generated'] );
		$this->assertSame( array( 16 ), $seen->getArrayCopy(), 'The length is settled once per request.' );
		foreach ( $response['data']['codes'] as $code ) {
			$this->assertMatchesRegularExpression( '/\Agift[a-z0-9]{16}\z/', $code );
		}
	}
}
