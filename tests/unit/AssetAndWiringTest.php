<?php
/**
 * Asset loading and plugin wiring tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

/**
 * Tests for the rules that decide what the plugin registers and loads.
 */
final class AssetAndWiringTest extends FGCBG_Test_Case {

	/**
	 * Hook suffix WordPress returns for the generator screen under the WooCommerce menu.
	 *
	 * @var string
	 */
	private const GENERATOR_PAGE_HOOK = 'woocommerce_page_free-gift-bulk-coupon-generator';

	/**
	 * Hooks the plugin registers when it starts.
	 *
	 * @var array<int, string>
	 */
	private const PLUGIN_HOOKS = array(
		'admin_menu',
		'admin_enqueue_scripts',
		'admin_notices',
		'wp_ajax_fgcbg_generate_batch',
	);

	/**
	 * Reset recorded state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_test_asset_state();
		$this->set_test_is_admin( true );
	}

	/**
	 * Reset recorded state after each test.
	 */
	protected function tearDown(): void {
		$this->reset_test_asset_state();
		$this->set_test_is_admin( true );
		$this->set_test_current_user_can( true );
		$this->set_test_current_user_capabilities( array() );
		$this->forget_plugin_instance();

		parent::tearDown();
	}

	/**
	 * Make the next FGCBG_Plugin::get_instance() call build a new object.
	 */
	private function forget_plugin_instance(): void {
		( new ReflectionProperty( FGCBG_Plugin::class, 'instance' ) )->setValue( null, null );
	}

	/**
	 * Start the plugin afresh and return the new instance.
	 */
	private function start_plugin(): FGCBG_Plugin {
		fgcbg_test_define_woocommerce_marker();
		$this->remove_test_hooks( self::PLUGIN_HOOKS );
		$this->forget_plugin_instance();

		return FGCBG_Plugin::get_instance();
	}

	/**
	 * Outside the admin area the plugin registers no menu, assets, action, or notice.
	 */
	public function test_nothing_is_registered_outside_the_admin_area(): void {
		$this->set_test_is_admin( false );
		$this->start_plugin();

		foreach ( self::PLUGIN_HOOKS as $hook ) {
			$this->assertSame( 0, $this->count_test_hook_callbacks( $hook ), $hook );
		}

		$this->set_test_is_admin( true );
		$this->start_plugin();

		foreach ( self::PLUGIN_HOOKS as $hook ) {
			$this->assertSame( 1, $this->count_test_hook_callbacks( $hook ), $hook );
		}
	}

	/**
	 * The menu item's callback prints the generator page.
	 */
	public function test_menu_item_renders_the_generator_page(): void {
		$this->start_plugin()->add_admin_menu();
		$callback = $this->get_last_recorded_submenu_page()[5];

		$this->assertIsCallable( $callback );

		ob_start();
		$callback();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="fgcbg-form"', $html );
	}

	/**
	 * While the screen is not registered, no hook suffix loads the assets, not even an empty one.
	 */
	public function test_assets_do_not_load_while_the_screen_is_not_registered(): void {
		$assets = new FGCBG_Admin_Assets();

		foreach ( array( '', self::GENERATOR_PAGE_HOOK ) as $hook ) {
			$assets->enqueue( $hook );

			$this->assertSame( array(), $this->get_recorded_scripts() );
			$this->assertSame( array(), $this->get_recorded_styles() );
		}
	}

	/**
	 * Script and stylesheet point at the shipped files and carry the plugin version.
	 */
	public function test_assets_point_at_the_shipped_files_with_the_plugin_version(): void {
		$assets = new FGCBG_Admin_Assets();
		$assets->set_page_hook( self::GENERATOR_PAGE_HOOK );
		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$script = $this->get_recorded_scripts()['fgcbg-admin'];
		$style  = $this->get_recorded_styles()['fgcbg-admin'];
		$base   = plugin_dir_url( FGCBG_PLUGIN_FILE );

		$this->assertSame( $base . 'assets/js/admin.js', $script['src'] );
		$this->assertSame( $base . 'assets/css/admin.css', $style['src'] );
		$this->assertSame( FGCBG_PLUGIN_VERSION, $script['version'] );
		$this->assertSame( FGCBG_PLUGIN_VERSION, $style['version'] );

		foreach ( array( 'assets/js/admin.js', 'assets/css/admin.css' ) as $file ) {
			$this->assertFileExists( dirname( __DIR__, 2 ) . '/' . $file );
		}
	}

	/**
	 * Settings that WordPress cannot attach to the script raise one notice, which shows only then.
	 */
	public function test_settings_that_cannot_be_attached_raise_one_notice(): void {
		$this->remove_test_hooks( array( 'admin_notices' ) );
		$assets = new FGCBG_Admin_Assets();
		$assets->set_page_hook( self::GENERATOR_PAGE_HOOK );

		ob_start();
		$assets->script_data_failure_notice();
		$this->assertSame( '', (string) ob_get_clean(), 'No notice before a failure.' );

		// Both ways of failing at once still register the notice once.
		$this->set_test_json_encode_result( false );
		$this->set_test_localize_script_result( false );
		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$this->assertSame( 1, $this->count_test_hook_callbacks( 'admin_notices' ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( $assets, 'script_data_failure_notice' ) ) );

		ob_start();
		$assets->script_data_failure_notice();
		$notice = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $notice );
		$this->assertStringContainsString( 'could not load its admin script settings', $notice );
		$this->remove_test_hooks( array( 'admin_notices' ) );
	}

	/**
	 * A failure to attach the settings alone, with encodable settings, raises the notice too.
	 */
	public function test_attach_failure_alone_raises_the_notice(): void {
		$this->remove_test_hooks( array( 'admin_notices' ) );
		$assets = new FGCBG_Admin_Assets();
		$assets->set_page_hook( self::GENERATOR_PAGE_HOOK );
		$this->set_test_localize_script_result( false );

		$assets->enqueue( self::GENERATOR_PAGE_HOOK );

		$this->assertSame( 1, $this->count_test_hook_callbacks( 'admin_notices' ) );
		$this->remove_test_hooks( array( 'admin_notices' ) );
	}

	/**
	 * The plugin tells WooCommerce that it works with its order storage, when WooCommerce can be told.
	 */
	public function test_order_storage_compatibility_is_declared(): void {
		$class = 'Automattic\WooCommerce\Utilities\FeaturesUtil';

		$this->assertSame( 10, has_action( 'before_woocommerce_init', 'fgcbg_declare_woocommerce_compatibility' ) );

		// A WooCommerce without the class: the callback must do nothing rather than fail.
		$this->assertFalse( class_exists( $class, false ), 'No other test may define the WooCommerce class.' );
		fgcbg_declare_woocommerce_compatibility();

		require_once __DIR__ . '/fixtures/class-features-util.php';
		fgcbg_declare_woocommerce_compatibility();

		$this->assertSame(
			array( array( 'custom_order_tables', FGCBG_PLUGIN_FILE, true ) ),
			$GLOBALS['fgcbg_test_declared_compatibility'] ?? array()
		);
	}
}
