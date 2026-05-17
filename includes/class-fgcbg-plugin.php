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
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var FGCBG_Plugin|null
	 */
	private static $instance = null;

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
	 * Get plugin instance.
	 *
	 * @since 1.0.0
	 * @return FGCBG_Plugin
	 * @psalm-suppress PossiblyUnusedReturnValue Public singleton accessor for integrations and tests.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
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
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
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

			if ( ! FGCBG_Dependencies::has_free_gift_coupon_type() ) {
				add_action( 'admin_notices', array( $this, 'free_gift_coupons_missing_notice' ) );
			}
		}
	}

	/**
	 * WooCommerce missing notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		$message = sprintf(
			/* translators: %s: WooCommerce download link */
			esc_html__( 'Free Gift Coupons Bulk Coupon Generator requires WooCommerce to be installed and active. You can download %s here.', 'free-gift-bulk-coupon-generator' ),
			'<a href="' . esc_url( 'https://woocommerce.com/' ) . '" target="_blank" rel="noopener noreferrer">WooCommerce</a>'
		);

		echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Free Gift Coupons missing notice.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function free_gift_coupons_missing_notice(): void {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Free Gift Coupons Bulk Coupon Generator requires Free Gift Coupons for WooCommerce to be active so the free_gift coupon type is available.', 'free-gift-bulk-coupon-generator' );
		echo '</p></div>';
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Free Gift Bulk Coupons', 'free-gift-bulk-coupon-generator' ),
			__( 'Coupon Generator', 'free-gift-bulk-coupon-generator' ),
			'manage_woocommerce',
			'free-gift-bulk-coupon-generator',
			array( $this->admin_page, 'render' )
		);
	}
}
