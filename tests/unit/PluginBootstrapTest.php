<?php
/**
 * Plugin bootstrap tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use z4kn4fein\SemVer\SemverException;
use z4kn4fein\SemVer\Version;

/**
 * Tests for the main plugin entry point.
 *
 * This suite targets PHP 8.2+, matching the plugin minimum.
 */
final class PluginBootstrapTest extends TestCase {
	use FGCBG_Test_Stub_State;

	/**
	 * Hook suffix WordPress returns for the WooCommerce coupon generator submenu page.
	 */
	private const GENERATOR_PAGE_HOOK = 'woocommerce_page_free-gift-bulk-coupon-generator';

	/**
	 * Reset recorded asset state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_test_asset_state();
	}

	/**
	 * Reset recorded asset state after each test.
	 */
	protected function tearDown(): void {
		$this->reset_test_asset_state();

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

		$plugin         = FGCBG_Plugin::get_instance();
		$assets         = $this->get_object_property( $plugin, 'admin_assets', FGCBG_Admin_Assets::class );
		$ajax           = $this->get_object_property( $plugin, 'ajax_handler', FGCBG_Ajax_Handler::class );
		$generator      = $this->get_object_property( $plugin, 'generator', FGCBG_Coupon_Generator::class );
		$ajax_generator = $this->get_object_property( $ajax, 'generator', FGCBG_Coupon_Generator::class );

		$this->assertSame( $generator, $ajax_generator, 'Expected AJAX handler to use the plugin coupon generator instance.' );
		$this->assertSame( 10, has_action( 'admin_menu', array( $plugin, 'add_admin_menu' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_fgcbg_generate_batch', array( $ajax, 'generate_batch' ) ) );
	}

	/**
	 * The coupon generator submenu requires coupon publishing permission.
	 */
	public function test_admin_menu_requires_coupon_publish_capability(): void {
		fgcbg_test_define_woocommerce_marker();
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();
		$plugin->add_admin_menu();

		$submenu_page = $this->get_last_recorded_submenu_page();

		$this->assertSame( 'woocommerce', $submenu_page[0] );
		$this->assertSame( FGCBG_Ajax_Handler::GENERATE_COUPONS_CAPABILITY, $submenu_page[3] );
	}

	/**
	 * Admin assets use standalone files with a minimal modern script dependency list.
	 */
	public function test_admin_assets_are_enqueued_for_generator_page(): void {
		fgcbg_test_define_woocommerce_marker();
		fgcbg_init();

		$plugin = FGCBG_Plugin::get_instance();
		$assets = $this->get_object_property( $plugin, 'admin_assets', FGCBG_Admin_Assets::class );

		$this->assertInstanceOf( FGCBG_Admin_Assets::class, $assets );

		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$admin_script_path = FGCBG_PLUGIN_PATH . 'assets/js/admin.js';
		$admin_script_url  = FGCBG_PLUGIN_URL . 'assets/js/admin.js';
		$scripts           = $this->get_recorded_scripts();
		$styles            = $this->get_recorded_styles();

		$this->assertArrayHasKey( 'fgcbg-admin', $scripts );
		$this->assertSame( $admin_script_url, $scripts['fgcbg-admin']['src'] );
		$this->assertFileExists( $admin_script_path, sprintf( 'Expected enqueued admin script "%s" to exist.', $admin_script_path ) );
		$this->assertSame( array( 'wc-enhanced-select' ), $scripts['fgcbg-admin']['dependencies'] );
		$this->assertSame(
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			),
			$scripts['fgcbg-admin']['args']
		);
		$this->assertArrayHasKey( 'fgcbg-admin', $styles );

		$localized_script = $this->get_recorded_localized_script( 'fgcbg-admin' );
		$inline_scripts   = $this->get_recorded_inline_scripts();

		$this->assertArrayNotHasKey( 'fgcbg-admin', $inline_scripts );
		$this->assertSame( 'fgcbgAdminConfig', $localized_script['object_name'] );
		$this->assertArrayNotHasKey( 'ajax_url', $localized_script['data'] );
		$this->assertArrayNotHasKey( 'fgcbg_i18n', $localized_script['data'] );
	}

	/**
	 * Script data encoding failures are surfaced to admins.
	 */
	public function test_admin_assets_queue_notice_when_script_data_cannot_be_encoded(): void {
		$assets = new FGCBG_Admin_Assets();

		$this->set_test_json_encode_result( false );

		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$localized_script = $this->get_recorded_localized_script( 'fgcbg-admin' );

		$this->assertSame( 10, has_action( 'admin_notices', array( $assets, 'script_data_failure_notice' ) ) );
		$this->assertSame( 'fgcbgAdminConfig', $localized_script['object_name'] );
		$this->assertSame( array(), $localized_script['data'] );
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
	 * Read a private object property.
	 *
	 * The test fails through PHPUnit assertions when the property does not
	 * exist or when its value is not an instance of the expected class.
	 *
	 * @template TObject of object
	 * @param object                $object         Object instance.
	 * @param string                $property       Property name.
	 * @param class-string<TObject> $expected_class Expected object class.
	 * @return TObject
	 */
	private function get_object_property( object $object, string $property, string $expected_class ) {
		$reflection_class = new ReflectionClass( $object );
		$this->assertTrue(
			$reflection_class->hasProperty( $property ),
			sprintf( 'Expected object property "%s" to exist.', $property )
		);

		$reflection_property = $reflection_class->getProperty( $property );
		$property_value      = $reflection_property->getValue( $object );

		$this->assertInstanceOf(
			$expected_class,
			$property_value,
			sprintf( 'Expected object property "%s" to be an instance of %s.', $property, $expected_class )
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

		$match_count = preg_match( '/^\s*\*\s*Version:\s*([^\s]+)\s*$/m', $contents, $matches );

		$this->assertSame( 1, $match_count, sprintf( 'Expected plugin file "%s" to contain a Version header.', $plugin_file ) );
		$this->assertArrayHasKey( 1, $matches, sprintf( 'Expected Version header in "%s" to capture the version number.', $plugin_file ) );

		try {
			$parsed_version = Version::parse( $matches[1] );
			$this->assertSame(
				$matches[1],
				(string) $parsed_version,
				sprintf( 'Expected Version header in "%s" to contain a canonical SemVer 2.0.0 value.', $plugin_file )
			);
		} catch ( SemverException $exception ) {
			$this->fail(
				sprintf(
					'Expected Version header in "%s" to contain a valid SemVer 2.0.0 value: %s',
					$plugin_file,
					$exception->getMessage()
				)
			);
		}

		return $matches[1];
	}
}
