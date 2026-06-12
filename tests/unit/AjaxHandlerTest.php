<?php
/**
 * AJAX handler tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for admin AJAX coupon generation.
 */
final class AjaxHandlerTest extends TestCase {
	/**
	 * Reset request globals and WooCommerce test doubles.
	 */
	protected function setUp(): void {
		parent::setUp();

		$_POST                                  = array();
		$GLOBALS['fgcbg_test_products']         = array(
			123 => new FGCBG_Test_Product( 'Sample Mug' ),
		);
		$GLOBALS['fgcbg_test_coupons']          = array();
		$GLOBALS['fgcbg_test_current_user_can'] = true;
		$GLOBALS['fgcbg_test_current_user_capabilities'] = array();
	}

	/**
	 * Clear request globals.
	 */
	protected function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * AJAX generation accepts scalar product IDs and creates coupons.
	 */
	public function test_generate_batch_creates_coupon_from_posted_product_ids(): void {
		$_POST = array(
			'product_ids'    => array( '123' ),
			'batch_size'     => '1',
			'coupon_prefix'  => 'GIFT',
			'nonce'          => 'nonce-fgcbg_ajax_nonce',
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertTrue( $response->response['success'] );
			$this->assertSame( 1, $response->response['data']['generated'] );
			$this->assertCount( 1, $response->response['data']['codes'] );
			$this->assertSame( array( 123 ), $GLOBALS['fgcbg_test_coupons'][0]->get_meta( '_fgcbg_product_ids' ) );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects requests with an invalid nonce.
	 */
	public function test_generate_batch_rejects_invalid_nonce(): void {
		$_POST = array(
			'product_ids'    => array( '123' ),
			'batch_size'     => '1',
			'coupon_prefix'  => 'GIFT',
			'nonce'          => 'invalid',
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $GLOBALS['fgcbg_test_coupons'] );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects users without the coupon publishing capability.
	 */
	public function test_generate_batch_rejects_user_without_coupon_publish_capability(): void {
		$GLOBALS['fgcbg_test_current_user_capabilities'] = array(
			'manage_woocommerce'   => true,
			'edit_product'         => array( 123 ),
			'edit_shop_coupons'    => true,
			'publish_shop_coupons' => false,
		);

		$_POST = array(
			'product_ids'    => array( '123' ),
			'batch_size'     => '1',
			'coupon_prefix'  => 'GIFT',
			'nonce'          => 'nonce-fgcbg_ajax_nonce',
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $GLOBALS['fgcbg_test_coupons'] );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * AJAX generation rejects selected products the current user cannot edit.
	 */
	public function test_generate_batch_rejects_uneditable_product_ids(): void {
		$GLOBALS['fgcbg_test_current_user_capabilities'] = array(
			'edit_product'         => array(),
			'publish_shop_coupons' => true,
		);

		$_POST = array(
			'product_ids'    => array( '123' ),
			'batch_size'     => '1',
			'coupon_prefix'  => 'GIFT',
			'nonce'          => 'nonce-fgcbg_ajax_nonce',
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 403, $response->response['status'] );
			$this->assertSame( array(), $GLOBALS['fgcbg_test_coupons'] );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}

	/**
	 * Nested posted product values are ignored instead of being coerced to ID 1.
	 */
	public function test_generate_batch_ignores_nested_product_values(): void {
		$_POST = array(
			'product_ids'   => array( array( 'nested' => '123' ) ),
			'batch_size'    => '1',
			'coupon_prefix' => 'GIFT',
			'nonce'         => 'nonce-fgcbg_ajax_nonce',
		);

		$handler = new FGCBG_Ajax_Handler( new FGCBG_Coupon_Generator() );

		try {
			$handler->generate_batch();
		} catch ( FGCBG_Test_Json_Response $response ) {
			$this->assertFalse( $response->response['success'] );
			$this->assertSame( 400, $response->response['status'] );
			$this->assertSame( array(), $GLOBALS['fgcbg_test_coupons'] );
			return;
		}

		$this->fail( 'Expected JSON response exception.' );
	}
}
