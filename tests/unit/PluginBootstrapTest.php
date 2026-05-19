<?php
/**
 * Plugin bootstrap tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the main plugin entry point.
 */
final class PluginBootstrapTest extends TestCase {
	/**
	 * The main plugin file defines the expected constants and helper.
	 */
	public function test_main_plugin_defines_constants_and_helper(): void {
		$this->assertTrue( defined( 'FGCBG_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'FGCBG_PLUGIN_PATH' ) );
		$this->assertTrue( defined( 'FGCBG_PLUGIN_VERSION' ) );
		$this->assertSame( '1.6.0', FGCBG_PLUGIN_VERSION );
		$this->assertTrue( fgcbg_is_loaded() );
	}

	/**
	 * The plugin registers its bootstrap callback on plugins_loaded.
	 */
	public function test_plugins_loaded_hook_is_registered(): void {
		$this->assertSame( 10, has_action( 'plugins_loaded', 'fgcbg_init' ) );
	}

	/**
	 * Initializing with WooCommerce present registers the admin hooks.
	 */
	public function test_admin_hooks_are_registered_when_initialized(): void {
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();

		$this->assertSame( 10, has_action( 'admin_menu', array( $plugin, 'add_admin_menu' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $this->get_plugin_property( $plugin, 'admin_assets' ), 'enqueue' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_fgcbg_generate_batch', array( $this->get_plugin_property( $plugin, 'ajax_handler' ), 'generate_batch' ) ) );
	}

	/**
	 * Admin assets use standalone files with a minimal modern script dependency list.
	 */
	public function test_admin_assets_are_enqueued_for_generator_page(): void {
		fgcbg_init();

		$GLOBALS['fgcbg_test_enqueued']       = array();
		$GLOBALS['fgcbg_test_inline_scripts'] = array();

		$plugin = FGCBG_Plugin::get_instance();
		$assets = $this->get_plugin_property( $plugin, 'admin_assets' );

		$this->assertInstanceOf( FGCBG_Admin_Assets::class, $assets );

		$assets->enqueue( 'woocommerce_page_free-gift-bulk-coupon-generator' );

		$this->assertArrayHasKey( 'fgcbg-admin', $GLOBALS['fgcbg_test_enqueued']['scripts'] );
		$this->assertSame( array( 'wc-enhanced-select' ), $GLOBALS['fgcbg_test_enqueued']['scripts']['fgcbg-admin']['dependencies'] );
		$this->assertSame(
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			),
			$GLOBALS['fgcbg_test_enqueued']['scripts']['fgcbg-admin']['args']
		);
		$this->assertArrayHasKey( 'fgcbg-admin', $GLOBALS['fgcbg_test_enqueued']['styles'] );

		$this->assertSame( 'before', $GLOBALS['fgcbg_test_inline_scripts']['fgcbg-admin'][0]['position'] );
		$this->assertStringStartsWith( 'globalThis.fgcbgAdminConfig = Object.freeze(', $GLOBALS['fgcbg_test_inline_scripts']['fgcbg-admin'][0]['data'] );
		$this->assertStringNotContainsString( 'fgcbg_i18n', $GLOBALS['fgcbg_test_inline_scripts']['fgcbg-admin'][0]['data'] );
	}

	/**
	 * Read a private collaborator from the plugin singleton.
	 *
	 * @param FGCBG_Plugin $plugin   Plugin instance.
	 * @param string       $property Property name.
	 * @return object
	 */
	private function get_plugin_property( FGCBG_Plugin $plugin, string $property ): object {
		$reflection_property = new ReflectionProperty( $plugin, $property );
		$property_value      = $reflection_property->getValue( $plugin );

		$this->assertIsObject( $property_value );

		return $property_value;
	}
}
