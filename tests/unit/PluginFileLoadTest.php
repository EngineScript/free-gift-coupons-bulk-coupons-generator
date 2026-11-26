<?php
/**
 * Test for loading the main plugin file: its constants, class files, and hooks.
 *
 * @package Free_Gift_Bulk_Coupon_Generator
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * The bootstrap loads the plugin file before coverage starts. This test loads it in a separate
 * process instead, where the bootstrap skips it (see FGCBG_TEST_DEFER_PLUGIN_LOAD), so coverage
 * records the file's top-level code.
 */
final class PluginFileLoadTest extends FGCBG_Test_Case {

	/**
	 * Tell the bootstrap of the separate process not to load the plugin file.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		putenv( 'FGCBG_TEST_DEFER_PLUGIN_LOAD=1' );
	}

	/**
	 * Remove the variable, so later processes load the plugin file as usual.
	 *
	 * @return void
	 */
	public static function tearDownAfterClass(): void {
		putenv( 'FGCBG_TEST_DEFER_PLUGIN_LOAD' );
		parent::tearDownAfterClass();
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_loading_the_plugin_file_defines_its_constants_and_registers_its_hooks(): void {
		$this->assertFalse( defined( 'FGCBG_PLUGIN_FILE' ), 'The bootstrap loaded the plugin file in this process.' );

		$file = dirname( __DIR__, 2 ) . '/free-gift-bulk-coupon-generator.php';
		require $file;

		$this->assertSame( realpath( $file ), realpath( FGCBG_PLUGIN_FILE ) );
		$this->assertSame( plugin_dir_url( FGCBG_PLUGIN_FILE ), FGCBG_PLUGIN_URL );
		$this->assertSame( plugin_dir_path( FGCBG_PLUGIN_FILE ), FGCBG_PLUGIN_PATH );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', FGCBG_PLUGIN_VERSION );
		$this->assertTrue( fgcbg_is_loaded() );
		$this->assertSame( 10, has_action( 'plugins_loaded', 'fgcbg_init' ) );
		$this->assertSame( 10, has_action( 'before_woocommerce_init', 'fgcbg_declare_woocommerce_compatibility' ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_file_runs_without_error_when_wordpress_uninstalls_the_plugin(): void {
		define( 'WP_UNINSTALL_PLUGIN', 'free-gift-coupons-bulk-coupons-generator/free-gift-bulk-coupon-generator.php' );

		$this->assertSame( 1, require dirname( __DIR__, 2 ) . '/uninstall.php' );
	}
}
