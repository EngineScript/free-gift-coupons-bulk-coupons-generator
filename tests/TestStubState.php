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
	 * Queue the random parts of the next coupon codes.
	 *
	 * The generator draws one wp_rand() value per character, so each code is
	 * queued as the positions of its characters in the generator's alphabet.
	 * An empty list returns the wp_rand() stand-in to real random values.
	 *
	 * @param array<int, string> $codes Random parts, in the order they will be generated.
	 * @return void
	 */
	private function queue_test_coupon_codes( array $codes ): void {
		$alphabet = (string) ( new ReflectionClassConstant( FGCBG_Coupon_Generator::class, 'CODE_ALPHABET' ) )->getValue();
		$values   = array();

		foreach ( $codes as $code ) {
			foreach ( str_split( $code ) as $character ) {
				$position = strpos( $alphabet, $character );

				$this->assertNotFalse( $position, sprintf( 'Character "%s" is not in the coupon code alphabet.', $character ) );

				$values[] = (int) $position;
			}
		}

		$GLOBALS['fgcbg_test_rand_values'] = $values;
	}

	/**
	 * Choose how the WooCommerce coupon stub saves: 'ok', 'no_id', or 'throw'.
	 *
	 * @param string $mode Save mode.
	 * @return void
	 */
	private function set_test_coupon_save_mode( string $mode ): void {
		$GLOBALS['fgcbg_test_coupon_save_mode'] = $mode;
	}

	/**
	 * Get the messages the plugin wrote to the WooCommerce logger stub.
	 *
	 * @return array<int, string>
	 */
	private function get_test_log_messages(): array {
		return array_column( $GLOBALS['fgcbg_test_log'] ?? array(), 'message' );
	}

	/**
	 * Set the coupon types WooCommerce reports, or null for the default list.
	 *
	 * @param array<string, string>|null $types Coupon types keyed by slug.
	 * @return void
	 */
	private function set_test_coupon_types( ?array $types ): void {
		if ( null === $types ) {
			unset( $GLOBALS['fgcbg_test_coupon_types'] );
			return;
		}

		$GLOBALS['fgcbg_test_coupon_types'] = $types;
	}

	/**
	 * Set the admin screen returned by get_current_screen(), or null for none.
	 *
	 * @param string|null $screen_id Screen ID.
	 * @return void
	 */
	private function set_test_current_screen( ?string $screen_id ): void {
		if ( null === $screen_id ) {
			unset( $GLOBALS['fgcbg_test_current_screen'] );
			return;
		}

		$screen     = new WP_Screen();
		$screen->id = $screen_id;

		$GLOBALS['fgcbg_test_current_screen'] = $screen;
	}

	/**
	 * Remove every callback a test registered on the given hooks.
	 *
	 * @param array<int, string> $hooks Hook names.
	 * @return void
	 */
	private function remove_test_hooks( array $hooks ): void {
		foreach ( $hooks as $hook ) {
			unset( $GLOBALS['fgcbg_test_hooks'][ $hook ] );
		}
	}

	/**
	 * Return coupon generation stubs to their defaults.
	 *
	 * @return void
	 */
	private function reset_test_generation_state(): void {
		$this->clear_test_coupons();
		$this->set_test_coupon_save_mode( 'ok' );
		$this->set_test_coupon_types( null );

		$GLOBALS['fgcbg_test_rand_values'] = array();
		$GLOBALS['fgcbg_test_log']         = array();
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
