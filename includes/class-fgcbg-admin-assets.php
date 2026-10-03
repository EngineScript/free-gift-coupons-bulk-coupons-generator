<?php
/**
 * Admin asset loading.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin assets and exposes server-side configuration to JavaScript.
 *
 * @since 1.6.0
 */
final class FGCBG_Admin_Assets {

	/**
	 * Admin script handle.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'fgcbg-admin';

	/**
	 * Admin style handle.
	 *
	 * @since 1.6.0
	 * @var string
	 */
	private const STYLE_HANDLE = 'fgcbg-admin';

	/**
	 * Whether admin script data failed to load.
	 *
	 * @since 1.6.0
	 * @var bool
	 */
	private bool $script_data_failed = false;

	/**
	 * Hook suffix of the generator screen, as returned by add_submenu_page().
	 *
	 * Empty until the screen is registered, and when the current user may not
	 * open it.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Set the hook suffix of the generator screen.
	 *
	 * @since 1.7.0
	 * @param string $page_hook Hook suffix returned by add_submenu_page(), or an empty string.
	 * @return void
	 */
	public function set_page_hook( string $page_hook ): void {
		$this->page_hook = $page_hook;
	}

	/**
	 * Register asset hooks.
	 *
	 * Priority 20 runs after WooCommerce registers its admin styles (priority
	 * 10), so the plugin stylesheet can be printed after them.
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Priority 20.
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.6.0
	 * @since 1.7.0 Recognizes the screen by its registered hook suffix and loads
	 *              the WooCommerce admin styles that the product search needs.
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( '' === $this->page_hook || $this->page_hook !== $hook ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			FGCBG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wc-enhanced-select', 'wp-a11y' ),
			FGCBG_PLUGIN_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		/*
		 * The product search is a WooCommerce enhanced select. Its styles ship in
		 * the WooCommerce admin stylesheet, which WooCommerce enqueues only on its
		 * own screens. Queue it here and make the plugin stylesheet depend on it,
		 * so it prints first. The dependency is declared only when the handle is
		 * registered, because an unregistered dependency would keep the plugin
		 * stylesheet from printing at all.
		 */
		wp_enqueue_style( 'woocommerce_admin_styles' );
		$style_dependencies = wp_style_is( 'woocommerce_admin_styles', 'registered' ) ? array( 'woocommerce_admin_styles' ) : array();

		wp_enqueue_style(
			self::STYLE_HANDLE,
			FGCBG_PLUGIN_URL . 'assets/css/admin.css',
			$style_dependencies,
			FGCBG_PLUGIN_VERSION
		);

		$script_data = $this->get_script_data();
		if ( false === wp_json_encode( $script_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ) {
			$this->queue_script_data_failure_notice();
			$script_data = array();
		}

		if ( ! wp_localize_script( self::SCRIPT_HANDLE, 'fgcbgAdminConfig', $script_data ) ) {
			$this->queue_script_data_failure_notice();
		}
	}

	/**
	 * Queue an admin notice when script data cannot be prepared.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	private function queue_script_data_failure_notice(): void {
		if ( $this->script_data_failed ) {
			return;
		}

		$this->script_data_failed = true;
		add_action( 'admin_notices', array( $this, 'script_data_failure_notice' ) );
	}

	/**
	 * Display the script data failure notice.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function script_data_failure_notice(): void {
		if ( ! $this->script_data_failed ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'Free Gift Coupons Bulk Coupon Generator could not load its admin script settings. Coupon generation controls may not work until this is resolved.', 'free-gift-bulk-coupon-generator' ) . '</p></div>';
	}

	/**
	 * Get JavaScript configuration and translated strings.
	 *
	 * @since 1.6.0
	 * @return array<string, int|string>
	 */
	private function get_script_data(): array {
		return array(
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'batch_size'             => FGCBG_Ajax_Handler::DEFAULT_BATCH_SIZE,
			'max_coupon_count_value' => FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH,
			'max_prefix_length'      => FGCBG_Coupon_Generator::MAX_PREFIX_LENGTH,
			'min_code_length'        => FGCBG_Coupon_Generator::MIN_CODE_LENGTH,
			'max_code_length'        => FGCBG_Coupon_Generator::MAX_CODE_LENGTH,
			'nonce'                  => wp_create_nonce( 'fgcbg_ajax_nonce' ),
			/* translators: %d: Number of coupons to generate. */
			'confirm_large_batch'    => __( 'You are about to generate %d coupons. Do you want to continue?', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d: Maximum number of coupons per run. */
			'max_coupons_warning'    => __( 'Maximum %d coupons allowed', 'free-gift-bulk-coupon-generator' ),
			'many_coupons_warning'   => __( 'Generating many coupons may take some time.', 'free-gift-bulk-coupon-generator' ),
			'select_product'         => __( 'Please select at least one product.', 'free-gift-bulk-coupon-generator' ),
			'invalid_coupon_count'   => __( 'Please enter a valid number of coupons (minimum 1).', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d: Maximum number of coupons per run. */
			'max_coupon_count'       => __( 'Maximum number of coupons is %d.', 'free-gift-bulk-coupon-generator' ),
			/* translators: 1: Minimum random code length, 2: Maximum random code length. */
			'code_length_invalid'    => __( 'Please enter a random code length between %1$d and %2$d characters.', 'free-gift-bulk-coupon-generator' ),
			'generation_in_progress' => __( 'Coupon generation is in progress. Are you sure you want to leave this page?', 'free-gift-bulk-coupon-generator' ),
			/* translators: 1: Number of coupons generated so far, 2: Total number of coupons to generate. */
			'generating_progress'    => __( 'Generating coupons: %1$d of %2$d', 'free-gift-bulk-coupon-generator' ),
			/* translators: %d: Number of coupons generated. */
			'generation_complete'    => __( 'Successfully generated %d coupons.', 'free-gift-bulk-coupon-generator' ),
			'generation_failed'      => __( 'Failed to generate coupons. Please try again.', 'free-gift-bulk-coupon-generator' ),
			'session_expired'        => __( 'Your session has expired or this page is out of date. Reload the page, then try again.', 'free-gift-bulk-coupon-generator' ),
			'response_unreadable'    => __( 'The server response could not be read, so some coupons may have been created without being listed here. Check WooCommerce > Coupons before generating again.', 'free-gift-bulk-coupon-generator' ),
		);
	}
}
