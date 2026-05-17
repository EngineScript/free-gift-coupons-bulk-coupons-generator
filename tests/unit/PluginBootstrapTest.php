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
		$this->assertSame( '1.5.1', FGCBG_PLUGIN_VERSION );
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
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts' ) );
		$this->assertSame( 10, has_action( 'wp_ajax_fgcbg_generate_batch' ) );
	}
}
