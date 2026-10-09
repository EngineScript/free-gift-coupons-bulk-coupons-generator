<?php
/**
 * AJAX handler tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Tests for admin AJAX coupon generation.
 */
final class AjaxHandlerTest extends FGCBG_Test_Case {

	/**
	 * Reset request globals and WooCommerce test doubles.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_test_request();
		$this->set_test_products(
			array(
				123 => new FGCBG_Test_Product( 'Sample Mug' ),
				500 => new FGCBG_Test_Product( 'Retired Mug', 0, 'trash' ),
				600 => new FGCBG_Test_Product( 'Unreleased Mug', 0, 'draft' ),
			)
		);
		$this->reset_test_generation_state();
		$this->set_test_current_user_can( true );
		$this->set_test_current_user_capabilities( array() );
	}

	/**
	 * Clear request globals and stub state.
	 */
	protected function tearDown(): void {
		$this->reset_test_request();
		$this->reset_test_generation_state();
		$this->set_test_current_user_can( true );
		$this->set_test_current_user_capabilities( array() );

		parent::tearDown();
	}

	/**
	 * Send a request through the handler and return the JSON response it ends with.
	 *
	 * @param array<string, mixed> $post_data POST data. A valid nonce is added unless one is given.
	 * @return array<string, mixed> Response recorded by the wp_send_json_*() stand-ins.
	 */
	private function send_request( array $post_data ): array {
		$this->set_test_post_data( $post_data + array( 'nonce' => 'nonce-fgcbg_ajax_nonce' ) );

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			return $response->response;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation accepts scalar product IDs and creates coupons.
	 */
	public function test_generate_batch_creates_coupon_from_posted_product_ids(): void {
		$this->set_test_post_data(
			array(
				'product_ids'   => array( '123' ),
				'batch_size'    => '1',
				'coupon_prefix' => 'GIFT',
				'nonce'         => 'nonce-fgcbg_ajax_nonce',
			)
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertTrue( $response->response['success'] );
			$this->assertSame( 1, $response->response['data']['generated'] );
			$this->assertCount( 1, $response->response['data']['codes'] );
			$this->assertSame( array( 123 ), $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );
			$this->assertArrayNotHasKey( 'warning', $response->response['data'], 'No warning for purchasable products.' );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects requests with an invalid nonce.
	 */
	public function test_generate_batch_rejects_invalid_nonce(): void {
		$this->set_test_post_data(
			array(
				'product_ids'   => array( '123' ),
				'batch_size'    => '1',
				'coupon_prefix' => 'GIFT',
				'nonce'         => 'invalid',
			)
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $this->get_test_coupons() );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects users without the coupon publishing capability.
	 */
	public function test_generate_batch_rejects_user_without_coupon_publish_capability(): void {
		$this->set_test_current_user_capabilities(
			array(
				'manage_woocommerce'   => true,
				'edit_product'         => array( 123 ),
				'edit_shop_coupons'    => true,
				'publish_shop_coupons' => false,
			)
		);

		$this->set_test_post_data(
			array(
				'product_ids'   => array( '123' ),
				'batch_size'    => '1',
				'coupon_prefix' => 'GIFT',
				'nonce'         => 'nonce-fgcbg_ajax_nonce',
			)
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $this->get_test_coupons() );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects selected products the current user cannot edit.
	 */
	public function test_generate_batch_rejects_uneditable_product_ids(): void {
		$this->set_test_current_user_capabilities(
			array(
				'edit_product'         => array(),
				'publish_shop_coupons' => true,
			)
		);

		$this->set_test_post_data(
			array(
				'product_ids'   => array( '123' ),
				'batch_size'    => '1',
				'coupon_prefix' => 'GIFT',
				'nonce'         => 'nonce-fgcbg_ajax_nonce',
			)
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $this->get_test_coupons() );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * Nested posted product values are ignored instead of being coerced to ID 1.
	 */
	public function test_generate_batch_ignores_nested_product_values(): void {
		$this->set_test_post_data(
			array(
				'product_ids'   => array( array( 'nested' => '123' ) ),
				'batch_size'    => '1',
				'coupon_prefix' => 'GIFT',
				'nonce'         => 'nonce-fgcbg_ajax_nonce',
			)
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 400, $response->response['status'] );
			$this->assertSame( array(), $this->get_test_coupons() );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * One uneditable product refuses the whole request, even next to an editable one.
	 */
	public function test_generate_batch_refuses_mixed_editable_and_uneditable_products(): void {
		$this->set_test_products(
			array(
				123 => new FGCBG_Test_Product( 'Sample Mug' ),
				456 => new FGCBG_Test_Product( 'Sticker Pack' ),
			)
		);
		$this->set_test_current_user_capabilities(
			array(
				'edit_product'         => array( 123 ),
				'publish_shop_coupons' => true,
			)
		);

		$response = $this->send_request( array( 'product_ids' => array( '123', '456' ) ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 403, $response['status'] );
		$this->assertSame( array(), $this->get_test_coupons() );
	}

	/**
	 * A request is refused while the free gift coupon type is not registered.
	 */
	public function test_generate_batch_requires_the_free_gift_coupon_type(): void {
		$this->set_test_coupon_types( array( 'percent' => 'Percentage discount' ) );

		$response = $this->send_request( array( 'product_ids' => array( '123' ) ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
		$this->assertSame( array(), $this->get_test_coupons() );
	}

	/**
	 * A product in the trash refuses the whole request.
	 */
	public function test_generate_batch_refuses_trashed_products(): void {
		foreach ( array( array( '500' ), array( '123', '500' ) ) as $product_ids ) {
			$response = $this->send_request( array( 'product_ids' => $product_ids ) );

			$this->assertFalse( $response['success'] );
			$this->assertSame( 400, $response['status'] );
			$this->assertStringContainsString( 'not available', $response['data']['message'] );
			$this->assertSame( array(), $this->get_test_coupons() );
		}
	}

	/**
	 * An ID that is not a product refuses the request instead of being skipped.
	 */
	public function test_generate_batch_refuses_ids_that_are_not_products(): void {
		$response = $this->send_request( array( 'product_ids' => array( '123', '999' ) ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
		$this->assertSame( array(), $this->get_test_coupons() );
	}

	/**
	 * More products than one coupon can carry are refused.
	 */
	public function test_generate_batch_refuses_more_than_the_maximum_gift_products(): void {
		$products = array();
		for ( $product_id = 1; $product_id <= FGCBG_Coupon_Generator::MAX_GIFT_PRODUCTS + 1; $product_id++ ) {
			$products[ $product_id ] = new FGCBG_Test_Product( 'Product ' . $product_id );
		}
		$this->set_test_products( $products );

		$response = $this->send_request( array( 'product_ids' => array_map( 'strval', array_keys( $products ) ) ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
		$this->assertSame( 'Please select no more than 20 products.', $response['data']['message'] );
		$this->assertSame( array(), $this->get_test_coupons() );
	}

	/**
	 * A gift that cannot be bought is accepted with a warning, as Free Gift Coupons does.
	 */
	public function test_generate_batch_warns_about_unpurchasable_products(): void {
		$response = $this->send_request(
			array(
				'product_ids' => array( '123', '600' ),
				'batch_size'  => '2',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['generated'] );
		$this->assertStringContainsString( 'not purchasable', $response['data']['warning'] );
		$this->assertStringContainsString( 'Unreleased Mug', $response['data']['warning'] );
		$this->assertStringNotContainsString( 'Sample Mug', $response['data']['warning'] );
		$this->assertSame( array( 123, 600 ), $this->get_test_coupon()->get_meta( '_fgcbg_product_ids' ) );
	}

	/**
	 * A request that creates nothing ends in an error, not in an empty success.
	 */
	public function test_generate_batch_reports_an_error_when_nothing_was_created(): void {
		$this->set_test_coupon_save_mode( 'no_id' );

		$response = $this->send_request(
			array(
				'product_ids' => array( '123' ),
				'batch_size'  => '1',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 500, $response['status'] );
		$this->assertStringContainsString( 'No coupons could be created', $response['data']['message'] );
	}

	/**
	 * The batch size defaults to 10 and never exceeds the per-request maximum.
	 */
	public function test_generate_batch_bounds_the_batch_size(): void {
		$expected = array(
			'absent' => 10,
			'0'      => 10,
			'3'      => 3,
			'500'    => FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH,
		);

		foreach ( $expected as $batch_size => $generated ) {
			$this->clear_test_coupons();

			$post_data = array( 'product_ids' => array( '123' ) );
			if ( 'absent' !== $batch_size ) {
				$post_data['batch_size'] = (string) $batch_size;
			}

			$response = $this->send_request( $post_data );

			$this->assertTrue( $response['success'] );
			$this->assertSame( $generated, $response['data']['generated'], sprintf( 'Batch size "%s".', $batch_size ) );
			$this->assertCount( $generated, $this->get_test_coupons() );
		}
	}

	/**
	 * The random code length defaults to 12 and stays within the allowed range.
	 */
	public function test_generate_batch_bounds_the_code_length(): void {
		$expected = array(
			'absent' => FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH,
			'3'      => FGCBG_Coupon_Generator::MIN_CODE_LENGTH,
			'16'     => 16,
			'99'     => FGCBG_Coupon_Generator::MAX_CODE_LENGTH,
		);

		foreach ( $expected as $code_length => $length ) {
			$post_data = array(
				'product_ids' => array( '123' ),
				'batch_size'  => '1',
			);
			if ( 'absent' !== $code_length ) {
				$post_data['coupon_code_length'] = (string) $code_length;
			}

			$response = $this->send_request( $post_data );

			$this->assertTrue( $response['success'] );
			$this->assertSame( $length, strlen( $response['data']['codes'][0] ), sprintf( 'Code length "%s".', $code_length ) );
		}
	}

	/**
	 * A batch size or code length sent as a list counts as absent, so the defaults apply.
	 */
	public function test_generate_batch_ignores_numbers_sent_as_lists(): void {
		$list_size = $this->send_request(
			array(
				'product_ids' => array( '123' ),
				'batch_size'  => array( '5' ),
			)
		);
		$this->assertTrue( $list_size['success'] );
		$this->assertSame( FGCBG_Ajax_Handler::DEFAULT_BATCH_SIZE, $list_size['data']['generated'] );

		$list_length = $this->send_request(
			array(
				'product_ids'        => array( '123' ),
				'batch_size'         => '1',
				'coupon_code_length' => array( '16' ),
			)
		);
		$this->assertTrue( $list_length['success'] );
		$this->assertSame( FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH, strlen( $list_length['data']['codes'][0] ) );
	}

	/**
	 * The lowest integer, whose absolute value is a float, is bounded like any other large value.
	 */
	public function test_generate_batch_bounds_the_lowest_integer(): void {
		$lowest = (string) PHP_INT_MIN;

		$size = $this->send_request(
			array(
				'product_ids' => array( '123' ),
				'batch_size'  => $lowest,
			)
		);
		$this->assertTrue( $size['success'] );
		$this->assertSame( FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH, $size['data']['generated'] );

		$length = $this->send_request(
			array(
				'product_ids'        => array( '123' ),
				'batch_size'         => '1',
				'coupon_code_length' => $lowest,
			)
		);
		$this->assertTrue( $length['success'] );
		$this->assertSame( FGCBG_Coupon_Generator::MAX_CODE_LENGTH, strlen( $length['data']['codes'][0] ) );
	}

	/**
	 * A prefix that is not a string is ignored; a string prefix is reduced to letters and digits.
	 */
	public function test_generate_batch_sanitizes_the_prefix(): void {
		$base = array(
			'product_ids'        => array( '123' ),
			'batch_size'         => '1',
			'coupon_code_length' => '8',
		);

		$array_prefix = $this->send_request( $base + array( 'coupon_prefix' => array( 'GIFT' ) ) );
		$this->assertTrue( $array_prefix['success'] );
		$this->assertSame( 8, strlen( $array_prefix['data']['codes'][0] ) );

		$markup_prefix = $this->send_request( $base + array( 'coupon_prefix' => '<b>Gi-ft!</b>' ) );
		$this->assertTrue( $markup_prefix['success'] );
		$this->assertStringStartsWith( 'gift', $markup_prefix['data']['codes'][0] );
		$this->assertSame( 12, strlen( $markup_prefix['data']['codes'][0] ) );
	}
}
