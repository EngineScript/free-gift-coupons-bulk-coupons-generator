<?php
/**
 * Test helpers for WordPress and WooCommerce stub state.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Encapsulates test access to bootstrap stub globals.
 */
trait FGCBG_Test_Stub_State {

	/**
	 * Reset request globals used by AJAX tests.
	 *
	 * @return void
	 */
	private function reset_test_request(): void {
		$_POST = array();
	}

	/**
	 * Set request data used by AJAX tests.
	 *
	 * @param array<string, mixed> $post_data POST data.
	 * @return void
	 */
	private function set_test_post_data( array $post_data ): void {
		$_POST = $post_data;
	}

	/**
	 * Set products returned by the WooCommerce product stub.
	 *
	 * @param array<int, FGCBG_Test_Product> $products Product doubles keyed by ID.
	 * @return void
	 */
	private function set_test_products( array $products ): void {
		$GLOBALS['fgcbg_test_products'] = $products;
	}

	/**
	 * Clear coupons recorded by the WooCommerce coupon stub.
	 *
	 * @return void
	 */
	private function clear_test_coupons(): void {
		$GLOBALS['fgcbg_test_coupons'] = array();
	}

	/**
	 * Get coupons recorded by the WooCommerce coupon stub.
	 *
	 * @return array<int, WC_Coupon>
	 */
	private function get_test_coupons(): array {
		return $GLOBALS['fgcbg_test_coupons'] ?? array();
	}

	/**
	 * Get one recorded test coupon.
	 *
	 * @param int $index Coupon index.
	 * @return WC_Coupon
	 */
	private function get_test_coupon( int $index = 0 ): WC_Coupon {
		$coupons = $this->get_test_coupons();

		$this->assertArrayHasKey( $index, $coupons, sprintf( 'Expected test coupon at index %d.', $index ) );

		return $coupons[ $index ];
	}

	/**
	 * Set deterministic passwords returned by wp_generate_password().
	 *
	 * @param array<int, string> $passwords Password queue.
	 * @return void
	 */
	private function set_test_passwords( array $passwords ): void {
		$GLOBALS['fgcbg_test_passwords'] = $passwords;
	}

	/**
	 * Set the current time returned by the WordPress current_time() stub.
	 *
	 * @param string $current_time Current time in MySQL datetime format.
	 * @return void
	 */
	private function set_test_current_time( string $current_time ): void {
		$GLOBALS['fgcbg_test_current_time'] = $current_time;
	}

	/**
	 * Reset the WordPress current_time() stub to its default test clock.
	 *
	 * @return void
	 */
	private function reset_test_current_time(): void {
		unset( $GLOBALS['fgcbg_test_current_time'] );
	}

	/**
	 * Get the current time returned by the WordPress current_time() stub.
	 *
	 * @return string
	 */
	private function get_test_current_time(): string {
		return current_time( 'mysql' );
	}

	/**
	 * Set the fallback current_user_can() result.
	 *
	 * @param bool $can Whether the user has unspecified capabilities.
	 * @return void
	 */
	private function set_test_current_user_can( bool $can ): void {
		$GLOBALS['fgcbg_test_current_user_can'] = $can;
	}

	/**
	 * Set specific current_user_can() capability results.
	 *
	 * @param array<string, mixed> $capabilities Capability map.
	 * @return void
	 */
	private function set_test_current_user_capabilities( array $capabilities ): void {
		$GLOBALS['fgcbg_test_current_user_capabilities'] = $capabilities;
	}

	/**
	 * Reset WordPress asset/menu recording state.
	 *
	 * @return void
	 */
	private function reset_test_asset_state(): void {
		unset(
			$GLOBALS['fgcbg_test_enqueued'],
			$GLOBALS['fgcbg_test_inline_scripts'],
			$GLOBALS['fgcbg_test_localized_scripts'],
			$GLOBALS['fgcbg_test_submenu_pages'],
			$GLOBALS['fgcbg_test_wp_json_encode_result'],
			$GLOBALS['fgcbg_test_wp_localize_script_result']
		);

		$GLOBALS['fgcbg_test_enqueued'] = array(
			'scripts' => array(),
			'styles'  => array(),
		);

		$GLOBALS['fgcbg_test_inline_scripts']     = array();
		$GLOBALS['fgcbg_test_localized_scripts'] = array();
		$GLOBALS['fgcbg_test_submenu_pages']     = array();
	}

	/**
	 * Get scripts recorded by wp_enqueue_script().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_recorded_scripts(): array {
		return $GLOBALS['fgcbg_test_enqueued']['scripts'] ?? array();
	}

	/**
	 * Get styles recorded by wp_enqueue_style().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_recorded_styles(): array {
		return $GLOBALS['fgcbg_test_enqueued']['styles'] ?? array();
	}

	/**
	 * Get inline scripts recorded by wp_add_inline_script().
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function get_recorded_inline_scripts(): array {
		return $GLOBALS['fgcbg_test_inline_scripts'] ?? array();
	}

	/**
	 * Get localized script data recorded by wp_localize_script().
	 *
	 * @param string $handle Script handle.
	 * @param int    $index  Zero-based localization entry index.
	 * @return array{data: array<string,mixed>, object_name: string}
	 */
	private function get_recorded_localized_script( string $handle, int $index = 0 ): array {
		$localized_scripts = $GLOBALS['fgcbg_test_localized_scripts'] ?? array();

		$this->assertArrayHasKey(
			$handle,
			$localized_scripts,
			sprintf( 'Expected localized script data for handle "%s" to be recorded.', $handle )
		);
		$this->assertIsArray( $localized_scripts[ $handle ] );
		$this->assertArrayHasKey(
			$index,
			$localized_scripts[ $handle ],
			sprintf( 'Expected localized script data entry %d for handle "%s" to be recorded.', $index, $handle )
		);
		$this->assertIsArray( $localized_scripts[ $handle ][ $index ] );
		$this->assertArrayHasKey( 'data', $localized_scripts[ $handle ][ $index ] );
		$this->assertArrayHasKey( 'object_name', $localized_scripts[ $handle ][ $index ] );
		$this->assertIsArray( $localized_scripts[ $handle ][ $index ]['data'] );
		$this->assertIsString( $localized_scripts[ $handle ][ $index ]['object_name'] );

		return $localized_scripts[ $handle ][ $index ];
	}

	/**
	 * Get the most recently recorded submenu page arguments.
	 *
	 * @return array<int, mixed>
	 */
	private function get_last_recorded_submenu_page(): array {
		$submenu_pages = $GLOBALS['fgcbg_test_submenu_pages'] ?? array();

		$this->assertNotEmpty( $submenu_pages );

		$submenu_page = end( $submenu_pages );

		$this->assertIsArray( $submenu_page );

		return $submenu_page;
	}

	/**
	 * Force wp_json_encode() to return a specific test value.
	 *
	 * @param string|false $result Forced encoding result.
	 * @return void
	 */
	private function set_test_json_encode_result( string|false $result ): void {
		$GLOBALS['fgcbg_test_wp_json_encode_result'] = $result;
	}
}
