<?php
/**
 * Output escaping tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Tests that translated text reaches the page escaped.
 *
 * A translation file is input the plugin does not control. These tests stand
 * in for one that holds markup, and check that the plugin chose an escaping
 * function everywhere it prints translated text. They do not test the
 * escaping functions themselves, which are WordPress's.
 */
final class OutputEscapingTest extends FGCBG_Test_Case {

	/**
	 * Markup the hostile translation adds to every string. It breaks out of
	 * element content and out of a double-quoted attribute when printed raw.
	 *
	 * @var string
	 */
	private const MARKUP = '"><b data-test="hostile">';

	/**
	 * Install the hostile translation.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_test_asset_state();
		$this->set_test_current_user_can( true );
		$this->set_test_current_user_capabilities( array() );
		$this->set_test_translation(
			static function ( string $text ): string {
				return $text . self::MARKUP;
			}
		);
	}

	/**
	 * Remove the hostile translation.
	 */
	protected function tearDown(): void {
		$this->set_test_translation( null );
		$this->set_test_coupon_types( null );
		$this->reset_test_asset_state();

		parent::tearDown();
	}

	/**
	 * Assert that output used the translation and printed none of its markup raw.
	 *
	 * @param string $output Printed markup.
	 * @param string $where  What was printed.
	 */
	private function assert_translation_is_escaped( string $output, string $where ): void {
		$this->assertStringContainsString( 'hostile', $output, $where . ': the translation was not used, so the check proves nothing.' );
		$this->assertStringNotContainsString( '<b data-test', $output, $where . ': translated markup reached the page as an element.' );
		$this->assertStringNotContainsString( '"><b', $output, $where . ': translated text closed an attribute.' );
	}

	/**
	 * Capture what a callable prints.
	 *
	 * @param callable $callback Prints output.
	 */
	private function capture( callable $callback ): string {
		ob_start();
		$callback();

		return (string) ob_get_clean();
	}

	/**
	 * Every translated string on the generator page is escaped, in element content and in attributes.
	 */
	public function test_generator_page_escapes_translated_text(): void {
		$html = $this->capture( array( new FGCBG_Admin_Page(), 'render' ) );

		$this->assert_translation_is_escaped( $html, 'generator page' );
		$this->assertGreaterThanOrEqual( 20, substr_count( $html, 'hostile' ), 'The page prints many translated strings; all were checked.' );
	}

	/**
	 * Both dependency notices escape their translated text; the WooCommerce link stays a link.
	 */
	public function test_dependency_notices_escape_translated_text(): void {
		fgcbg_test_define_woocommerce_marker();
		$plugin = FGCBG_Plugin::get_instance();
		$this->set_test_coupon_types( array( 'percent' => 'Percentage discount' ) );

		$woocommerce = $this->capture( array( $plugin, 'woocommerce_missing_notice' ) );
		$free_gift   = $this->capture( array( $plugin, 'free_gift_coupons_missing_notice' ) );

		$this->assert_translation_is_escaped( $woocommerce, 'WooCommerce notice' );
		$this->assert_translation_is_escaped( $free_gift, 'Free Gift Coupons notice' );
		$this->assertStringContainsString( '<a href="https://woocommerce.com/"', $woocommerce, 'The link the plugin builds itself is markup.' );
	}

	/**
	 * The settings notice escapes its translated text.
	 */
	public function test_settings_notice_escapes_translated_text(): void {
		$assets = new FGCBG_Admin_Assets();
		$assets->set_page_hook( 'woocommerce_page_free-gift-bulk-coupon-generator' );
		$this->set_test_json_encode_result( false );
		$assets->enqueue( 'woocommerce_page_free-gift-bulk-coupon-generator' );
		remove_filter( 'admin_notices', array( $assets, 'script_data_failure_notice' ) );

		$this->assert_translation_is_escaped( $this->capture( array( $assets, 'script_data_failure_notice' ) ), 'settings notice' );
	}

	/**
	 * The menu title is escaped, because WordPress prints menu titles as markup.
	 */
	public function test_menu_title_escapes_translated_text(): void {
		fgcbg_test_define_woocommerce_marker();
		FGCBG_Plugin::get_instance()->add_admin_menu();

		$this->assert_translation_is_escaped( (string) $this->get_last_recorded_submenu_page()[2], 'menu title' );
	}
}
