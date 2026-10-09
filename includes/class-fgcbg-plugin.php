<?php
/**
 * Main plugin orchestration class.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton that wires hooks and delegates runtime responsibilities to
 * dedicated classes.
 *
 * @since 1.0.0
 */
final class FGCBG_Plugin {

	/**
	 * Capability WooCommerce requires for its own admin menu.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private const WOOCOMMERCE_MENU_CAPABILITY = 'edit_others_shop_orders';

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var FGCBG_Plugin|null
	 */
	private static ?self $instance = null;

	/**
	 * Coupon generator instance.
	 *
	 * @since 1.6.0
	 * @var FGCBG_Coupon_Generator
	 */
	private FGCBG_Coupon_Generator $generator;

	/**
	 * Admin page renderer instance.
	 *
	 * @since 1.6.0
	 * @var FGCBG_Admin_Page
	 */
	private FGCBG_Admin_Page $admin_page;

	/**
	 * AJAX handler instance.
	 *
	 * @since 1.6.0
	 * @var FGCBG_Ajax_Handler
	 */
	private FGCBG_Ajax_Handler $ajax_handler;

	/**
	 * Admin assets instance.
	 *
	 * @since 1.6.0
	 * @var FGCBG_Admin_Assets
	 */
	private FGCBG_Admin_Assets $admin_assets;

	/**
	 * Hook suffix of the generator screen, or an empty string when the screen
	 * is not registered for the current user.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Get plugin instance.
	 *
	 * @since 1.0.0
	 * @return FGCBG_Plugin
	 */
	public static function get_instance(): self {
		self::$instance ??= new self();

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin.
	 *
	 * The Free Gift Coupons notice is always registered in the admin and decides
	 * for itself whether to print. The coupon-type lookup must not run here:
	 * this method runs on `plugins_loaded`, before translations may be loaded
	 * and before other plugins have registered their coupon types.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Private; the dependency lookup moved into the notice callback.
	 * @return void
	 */
	private function init(): void {
		if ( ! FGCBG_Dependencies::has_woocommerce() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->generator    = new FGCBG_Coupon_Generator();
		$this->admin_page   = new FGCBG_Admin_Page();
		$this->ajax_handler = new FGCBG_Ajax_Handler( $this->generator );
		$this->admin_assets = new FGCBG_Admin_Assets();

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			$this->admin_assets->register_hooks();
			$this->ajax_handler->register_hooks();
			add_action( 'admin_notices', array( $this, 'free_gift_coupons_missing_notice' ) );
		}
	}

	/**
	 * Decide whether the current user should see a dependency notice.
	 *
	 * Users who can activate plugins see it on every admin screen. Anyone else
	 * sees it only on the generator screen, where it explains why coupon
	 * generation is unavailable.
	 *
	 * @since 1.7.0
	 * @return bool True when a dependency notice may be shown.
	 */
	private function should_show_dependency_notice(): bool {
		if ( current_user_can( 'activate_plugins' ) ) {
			return true;
		}

		if ( '' === $this->page_hook ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen instanceof WP_Screen && $screen->id === $this->page_hook;
	}

	/**
	 * WooCommerce missing notice.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 Shown only to users who can act on it.
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		if ( ! $this->should_show_dependency_notice() ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: WooCommerce download link */
			esc_html__( 'Free Gift Coupons Bulk Coupon Generator requires WooCommerce to be installed and active. You can download %s here.', 'free-gift-bulk-coupon-generator' ),
			'<a href="' . esc_url( 'https://woocommerce.com/' ) . '" target="_blank" rel="noopener noreferrer">WooCommerce</a>'
		);

		wp_admin_notice( $message, array( 'type' => 'error' ) );
	}

	/**
	 * Free Gift Coupons missing notice.
	 *
	 * Runs on `admin_notices`, after `init`, so the coupon-type lookup sees
	 * every registered type and may load translations.
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Checks the dependency itself and is shown only to users who can act on it.
	 * @return void
	 */
	public function free_gift_coupons_missing_notice(): void {
		if ( FGCBG_Dependencies::has_free_gift_coupon_type() || ! $this->should_show_dependency_notice() ) {
			return;
		}

		wp_admin_notice(
			esc_html__( 'Free Gift Coupons Bulk Coupon Generator requires Free Gift Coupons for WooCommerce to be active so the free_gift coupon type is available.', 'free-gift-bulk-coupon-generator' ),
			array( 'type' => 'error' )
		);
	}

	/**
	 * Add admin menu.
	 *
	 * The screen sits under the WooCommerce menu. A user who may not see
	 * that menu gets it under Coupons instead, the top-level menu WooCommerce
	 * gives such a user for the coupon list. WordPress refuses a screen whose
	 * parent menu the user may not open, so without this the capability to
	 * publish coupons would not be enough to reach the screen.
	 *
	 * @since 1.0.0
	 * @since 1.8.0 Registered under Coupons for users without the WooCommerce menu.
	 * @return void
	 */
	public function add_admin_menu(): void {
		$page_hook = add_submenu_page(
			current_user_can( self::WOOCOMMERCE_MENU_CAPABILITY ) ? 'woocommerce' : 'edit.php?post_type=shop_coupon',
			__( 'Free Gift Bulk Coupons', 'free-gift-bulk-coupon-generator' ),
			esc_html__( 'Coupon Generator', 'free-gift-bulk-coupon-generator' ),
			FGCBG_Ajax_Handler::GENERATE_COUPONS_CAPABILITY,
			'free-gift-bulk-coupon-generator',
			array( $this->admin_page, 'render' )
		);

		$this->page_hook = is_string( $page_hook ) ? $page_hook : '';
		$this->admin_assets->set_page_hook( $this->page_hook );
	}
}
