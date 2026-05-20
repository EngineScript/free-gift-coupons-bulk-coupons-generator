<?php
/**
 * Plugin bootstrap tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the main plugin entry point.
 *
 * This suite targets PHP 8.2+, matching the plugin minimum.
 */
final class PluginBootstrapTest extends TestCase {
	/**
	 * Hook suffix WordPress returns for the WooCommerce coupon generator submenu page.
	 */
	private const GENERATOR_PAGE_HOOK = 'woocommerce_page_free-gift-bulk-coupon-generator';

	/**
	 * Strict semantic version value expected in the plugin header.
	 */
	private const SEMANTIC_VERSION_PATTERN = '[0-9]+\.[0-9]+\.[0-9]+';

	/**
	 * Reset recorded asset state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_asset_globals();
	}

	/**
	 * Reset recorded asset state after each test.
	 */
	protected function tearDown(): void {
		$this->reset_asset_globals();

		parent::tearDown();
	}

	/**
	 * The main plugin file defines the expected constants and helper.
	 */
	public function test_main_plugin_defines_constants_and_helper(): void {
		$this->assertTrue( defined( 'FGCBG_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'FGCBG_PLUGIN_PATH' ) );
		$this->assertTrue( defined( 'FGCBG_PLUGIN_VERSION' ) );
		$this->assertTrue( fgcbg_is_loaded() );
	}

	/**
	 * The runtime version constant stays aligned with the plugin header.
	 */
	public function test_plugin_version_constant_matches_header(): void {
		$this->assertSame(
			$this->get_plugin_header_version(),
			FGCBG_PLUGIN_VERSION,
			'Expected FGCBG_PLUGIN_VERSION to match the plugin header Version value.'
		);
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
		fgcbg_test_define_woocommerce_marker();
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();
		$assets = $this->get_plugin_property( $plugin, 'admin_assets', FGCBG_Admin_Assets::class );
		$ajax   = $this->get_plugin_property( $plugin, 'ajax_handler', FGCBG_Ajax_Handler::class );

		$this->assertSame( 10, has_action( 'admin_menu', array( $plugin, 'add_admin_menu' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_fgcbg_generate_batch', array( $ajax, 'generate_batch' ) ) );
	}

	/**
	 * Admin assets use standalone files with a minimal modern script dependency list.
	 */
	public function test_admin_assets_are_enqueued_for_generator_page(): void {
		fgcbg_test_define_woocommerce_marker();
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();
		$assets = $this->get_plugin_property( $plugin, 'admin_assets', FGCBG_Admin_Assets::class );

		$this->assertInstanceOf( FGCBG_Admin_Assets::class, $assets );

		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$admin_script_path = FGCBG_PLUGIN_PATH . 'assets/js/admin.js';
		$admin_script_url  = FGCBG_PLUGIN_URL . 'assets/js/admin.js';

		$this->assertArrayHasKey( 'fgcbg-admin', $GLOBALS['fgcbg_test_enqueued']['scripts'] );
		$this->assertSame( $admin_script_url, $GLOBALS['fgcbg_test_enqueued']['scripts']['fgcbg-admin']['src'] );
		$this->assertFileExists( $admin_script_path, sprintf( 'Expected enqueued admin script "%s" to exist.', $admin_script_path ) );
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
	 * Initializing without WooCommerce registers only the dependency notice.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_woocommerce_registers_notice_without_admin_hooks(): void {
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();

		$this->assertFalse( class_exists( 'WooCommerce' ) );
		$this->assertSame( 10, has_action( 'plugins_loaded', 'fgcbg_init' ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( $plugin, 'woocommerce_missing_notice' ) ) );
		$this->assertFalse( has_action( 'admin_menu', array( $plugin, 'add_admin_menu' ) ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts' ) );
		$this->assertFalse( has_action( 'wp_ajax_fgcbg_generate_batch' ) );
	}

	/**
	 * Read a private object property from the plugin singleton.
	 *
	 * @template TObject of object
	 * @param FGCBG_Plugin          $plugin         Plugin instance.
	 * @param string                $property       Property name.
	 * @param class-string<TObject> $expected_class Expected object class.
	 * @return TObject
	 */
	private function get_plugin_property( FGCBG_Plugin $plugin, string $property, string $expected_class ) {
		$reflection_class = new ReflectionClass( $plugin );
		$this->assertTrue(
			$reflection_class->hasProperty( $property ),
			sprintf( 'Expected plugin property "%s" to exist.', $property )
		);

		$reflection_property = $reflection_class->getProperty( $property );
		$property_value      = $reflection_property->getValue( $plugin );

		$this->assertInstanceOf(
			$expected_class,
			$property_value,
			sprintf( 'Expected plugin property "%s" to be an instance of %s.', $property, $expected_class )
		);

		return $property_value;
	}

	/**
	 * Read the plugin version from the WordPress plugin header.
	 */
	private function get_plugin_header_version(): string {
		$plugin_file = FGCBG_PLUGIN_PATH . 'free-gift-bulk-coupon-generator.php';

		$this->assertFileExists( $plugin_file, sprintf( 'Expected plugin file "%s" to exist.', $plugin_file ) );
		$this->assertTrue( is_readable( $plugin_file ), sprintf( 'Expected plugin file "%s" to be readable.', $plugin_file ) );

		$contents = file_get_contents( $plugin_file );

		$this->assertNotFalse( $contents, sprintf( 'Failed to read plugin file "%s".', $plugin_file ) );
		$this->assertIsString( $contents );

		$match_count = preg_match( sprintf( '/^\s*\*\s*Version:\s*(%s)\s*$/m', self::SEMANTIC_VERSION_PATTERN ), $contents, $matches );

		$this->assertSame( 1, $match_count, sprintf( 'Expected plugin file "%s" to contain a semantic Version header like 1.2.3.', $plugin_file ) );
		$this->assertArrayHasKey( 1, $matches, sprintf( 'Expected Version header in "%s" to capture the version number.', $plugin_file ) );

		return $matches[1];
	}

	/**
	 * Clear WordPress asset globals used by the bootstrap stubs.
	 */
	private function reset_asset_globals(): void {
		unset( $GLOBALS['fgcbg_test_enqueued'], $GLOBALS['fgcbg_test_inline_scripts'] );

		$GLOBALS['fgcbg_test_enqueued'] = array(
			'scripts' => array(),
			'styles'  => array(),
		);

		$GLOBALS['fgcbg_test_inline_scripts'] = array();
	}
}
