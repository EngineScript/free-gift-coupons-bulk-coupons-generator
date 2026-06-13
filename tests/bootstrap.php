<?php
/**
 * PHPUnit bootstrap for local test runs.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once __DIR__ . '/TestStubState.php';

/**
 * Define a minimal WooCommerce presence marker for plugin initialization tests.
 *
 * @return void
 */
function fgcbg_test_define_woocommerce_marker() {
	if ( class_exists( 'WooCommerce', false ) ) {
		return;
	}

	/**
	 * Minimal WooCommerce presence marker.
	 */
	class WooCommerce {}
}

$GLOBALS['fgcbg_test_hooks']            = array();
$GLOBALS['fgcbg_test_products']         = array();
$GLOBALS['fgcbg_test_coupons']          = array();
$GLOBALS['fgcbg_test_enqueued']         = array();
$GLOBALS['fgcbg_test_inline_scripts']   = array();
$GLOBALS['fgcbg_test_localized_scripts'] = array();
$GLOBALS['fgcbg_test_passwords']        = array();
$GLOBALS['fgcbg_test_submenu_pages']    = array();
$GLOBALS['fgcbg_test_current_user_can'] = true;
$GLOBALS['fgcbg_test_is_admin']         = true;

/**
 * Compare two WordPress hook callbacks.
 *
 * @param callable|string $registered Registered callback.
 * @param callable|string $target     Target callback.
 * @return bool
 */
function fgcbg_test_callbacks_match( $registered, $target ) {
	if ( is_string( $registered ) || is_string( $target ) ) {
		return $registered === $target;
	}

	if ( is_array( $registered ) && is_array( $target ) ) {
		return $registered[0] === $target[0] && $registered[1] === $target[1];
	}

	return $registered === $target;
}

/**
 * Locate a registered hook callback.
 *
 * @param string          $hook     Hook name.
 * @param callable|string $callback Optional callback.
 * @return int|false
 */
function fgcbg_test_has_hook( $hook, $callback = false ) {
	if ( empty( $GLOBALS['fgcbg_test_hooks'][ $hook ] ) ) {
		return false;
	}

	foreach ( $GLOBALS['fgcbg_test_hooks'][ $hook ] as $priority => $callbacks ) {
		if ( false === $callback ) {
			return (int) $priority;
		}

		foreach ( $callbacks as $registered ) {
			if ( fgcbg_test_callbacks_match( $registered['callback'], $callback ) ) {
				return (int) $priority;
			}
		}
	}

	return false;
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Get the URL for a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string URL.
	 */
	function plugin_dir_url( $file ) {
		unset( $file );
		return 'https://example.org/wp-content/plugins/free-gift-bulk-coupon-generator/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Get the filesystem path for a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string Directory path.
	 */
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Get a plugin basename for tests.
	 *
	 * @param string $file Plugin file.
	 * @return string Basename.
	 */
	function plugin_basename( $file ) {
		return basename( $file );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback.
	 *
	 * @param string          $hook          Hook name.
	 * @param callable|string $callback      Callback.
	 * @param int             $priority      Hook priority.
	 * @param int             $accepted_args Accepted args.
	 * @return bool True.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['fgcbg_test_hooks'][ $hook ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register an action callback.
	 *
	 * @param string          $hook          Hook name.
	 * @param callable|string $callback      Callback.
	 * @param int             $priority      Hook priority.
	 * @param int             $accepted_args Accepted args.
	 * @return bool True.
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Remove a filter callback.
	 *
	 * @param string          $hook     Hook name.
	 * @param callable|string $callback Callback.
	 * @param int             $priority Hook priority.
	 * @return bool True when removed.
	 */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['fgcbg_test_hooks'][ $hook ][ $priority ] ) ) {
			return false;
		}

		foreach ( $GLOBALS['fgcbg_test_hooks'][ $hook ][ $priority ] as $index => $registered ) {
			if ( fgcbg_test_callbacks_match( $registered['callback'], $callback ) ) {
				unset( $GLOBALS['fgcbg_test_hooks'][ $hook ][ $priority ][ $index ] );
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Check whether a filter callback is registered.
	 *
	 * @param string          $hook     Hook name.
	 * @param callable|string $callback Optional callback.
	 * @return int|false
	 */
	function has_filter( $hook, $callback = false ) {
		return fgcbg_test_has_hook( $hook, $callback );
	}
}

if ( ! function_exists( 'has_action' ) ) {
	/**
	 * Check whether an action callback is registered.
	 *
	 * @param string          $hook     Hook name.
	 * @param callable|string $callback Optional callback.
	 * @return int|false
	 */
	function has_action( $hook, $callback = false ) {
		return fgcbg_test_has_hook( $hook, $callback );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply registered filters.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  $value Value to filter.
	 * @return mixed Filtered value.
	 */
	function apply_filters( $hook, $value ) {
		$args = array_slice( func_get_args(), 2 );

		if ( empty( $GLOBALS['fgcbg_test_hooks'][ $hook ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['fgcbg_test_hooks'][ $hook ] );
		foreach ( $GLOBALS['fgcbg_test_hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$callback_args = array_merge( array( $value ), $args );
				$value         = call_user_func_array(
					$registered['callback'],
					array_slice( $callback_args, 0, (int) $registered['accepted_args'] )
				);
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Run registered action callbacks.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function do_action( $hook ) {
		$args = array_slice( func_get_args(), 1 );

		if ( empty( $GLOBALS['fgcbg_test_hooks'][ $hook ] ) ) {
			return;
		}

		ksort( $GLOBALS['fgcbg_test_hooks'][ $hook ] );
		foreach ( $GLOBALS['fgcbg_test_hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				call_user_func_array(
					$registered['callback'],
					array_slice( $args, 0, (int) $registered['accepted_args'] )
				);
			}
		}
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Check whether current request is admin.
	 *
	 * @return bool
	 */
	function is_admin() {
		return (bool) $GLOBALS['fgcbg_test_is_admin'];
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Check whether current request is AJAX.
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Build an admin URL.
	 *
	 * @param string $path Admin path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Create a test nonce.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce( $action ) {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * Generate a deterministic alphanumeric password for tests.
	 *
	 * @param int  $length              Password length.
	 * @param bool $special_chars       Whether to include special characters.
	 * @param bool $extra_special_chars Whether to include extra special characters.
	 * @return string
	 */
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $special_chars, $extra_special_chars );

		if ( ! empty( $GLOBALS['fgcbg_test_passwords'] ) ) {
			return substr( (string) array_shift( $GLOBALS['fgcbg_test_passwords'] ), 0, (int) $length );
		}

		static $counter = 0;
		++$counter;

		$seed = 'a' . base_convert( (string) $counter, 10, 36 ) . 'b2c3d4e5f6g7h8';

		return substr( str_repeat( $seed, 3 ), 0, (int) $length );
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * Validate an AJAX nonce.
	 *
	 * @param int|string $action    Nonce action.
	 * @param string|bool $query_arg Request field that contains the nonce.
	 * @param bool       $stop      Whether to stop on failure.
	 * @return bool
	 */
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
		$nonce_key = false === $query_arg ? '_ajax_nonce' : (string) $query_arg;
		$nonce     = isset( $_POST[ $nonce_key ] ) ? (string) wp_unslash( $_POST[ $nonce_key ] ) : '';
		$valid     = 'nonce-' . $action === $nonce;

		if ( ! $valid && $stop ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		return $valid;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Check test user capabilities.
	 *
	 * @param string $capability Capability name.
	 * @param mixed  ...$args    Optional capability arguments.
	 * @return bool
	 */
	function current_user_can( $capability = '', ...$args ) {
		$capabilities = $GLOBALS['fgcbg_test_current_user_capabilities'] ?? array();

		if ( is_array( $capabilities ) && array_key_exists( $capability, $capabilities ) ) {
			$grant = $capabilities[ $capability ];

			if ( is_callable( $grant ) ) {
				return (bool) $grant( $capability, ...$args );
			}

			if ( is_array( $grant ) ) {
				$object_id = isset( $args[0] ) ? (int) $args[0] : 0;

				return in_array( $object_id, array_map( 'intval', $grant ), true );
			}

			return (bool) $grant;
		}

		return (bool) $GLOBALS['fgcbg_test_current_user_can'];
	}
}

if ( ! class_exists( 'FGCBG_Test_Json_Response' ) ) {
	/**
	 * Exception used to stop wp_send_json_* calls during tests.
	 */
	class FGCBG_Test_Json_Response extends RuntimeException {
		/**
		 * Response payload.
		 *
		 * @var array<string, mixed>
		 */
		public $response;

		/**
		 * Constructor.
		 *
		 * @param array<string, mixed> $response Response payload.
		 */
		public function __construct( $response ) {
			parent::__construct( 'JSON response sent' );
			$this->response = $response;
		}
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * Send a JSON success response.
	 *
	 * @param mixed $data Response data.
	 * @return never
	 */
	function wp_send_json_success( $data = null ) {
		throw new FGCBG_Test_Json_Response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * Send a JSON error response.
	 *
	 * @param mixed $data        Response data.
	 * @param int   $status_code HTTP status code.
	 * @return never
	 */
	function wp_send_json_error( $data = null, $status_code = null ) {
		throw new FGCBG_Test_Json_Response(
			array(
				'success' => false,
				'data'    => $data,
				'status'  => $status_code,
			)
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Record an enqueued script.
	 *
	 * @param string           $handle    Script handle.
	 * @param string           $src       Script source URL.
	 * @param array<int,string> $deps      Script dependencies.
	 * @param string|bool|null $ver       Script version.
	 * @param bool|array       $args      Footer flag or loading args.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
		$GLOBALS['fgcbg_test_enqueued']['scripts'][ $handle ] = array(
			'args'         => $args,
			'dependencies' => $deps,
			'src'          => $src,
			'version'      => $ver,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Record an enqueued style.
	 *
	 * @param string           $handle Style handle.
	 * @param string           $src    Style source URL.
	 * @param array<int,string> $deps   Style dependencies.
	 * @param string|bool|null $ver    Style version.
	 * @param string           $media  Style media.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['fgcbg_test_enqueued']['styles'][ $handle ] = array(
			'dependencies' => $deps,
			'media'        => $media,
			'src'          => $src,
			'version'      => $ver,
		);
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	/**
	 * Record inline script data.
	 *
	 * @param string $handle   Script handle.
	 * @param string $data     Inline script data.
	 * @param string $position Inline script position.
	 * @return void
	 */
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['fgcbg_test_inline_scripts'][ $handle ][] = array(
			'data'     => $data,
			'position' => $position,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Record localized script data.
	 *
	 * @param string               $handle      Script handle.
	 * @param string               $object_name JavaScript object name.
	 * @param array<string,mixed>  $l10n        Localization data.
	 * @return bool
	 */
	function wp_localize_script( $handle, $object_name, $l10n ) {
		if ( isset( $GLOBALS['fgcbg_test_wp_localize_script_result'] ) ) {
			return (bool) $GLOBALS['fgcbg_test_wp_localize_script_result'];
		}

		$GLOBALS['fgcbg_test_localized_scripts'][ $handle ][] = array(
			'data'        => $l10n,
			'object_name' => $object_name,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encode data as JSON.
	 *
	 * @param mixed $data  Data to encode.
	 * @param int   $flags JSON encoding flags.
	 * @return string|false
	 */
	function wp_json_encode( $data, $flags = 0 ) {
		if ( array_key_exists( 'fgcbg_test_wp_json_encode_result', $GLOBALS ) ) {
			return $GLOBALS['fgcbg_test_wp_json_encode_result'];
		}

		return json_encode( $data, $flags );
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Record submenu pages.
	 *
	 * @return string
	 */
	function add_submenu_page() {
		$GLOBALS['fgcbg_test_submenu_pages'][] = func_get_args();
		return 'woocommerce_page_free-gift-bulk-coupon-generator';
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return translated text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Echo translated text.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	function _e( $text ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape HTML text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape an HTML attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return esc_attr( $url );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Return escaped translated text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html__( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Echo escaped translated text.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	function esc_html_e( $text ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Echo escaped translated attribute text.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	function esc_attr_e( $text ) {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Allow post-safe HTML in tests.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function wp_kses_post( $html ) {
		return $html;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Remove slashes from a value.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip all HTML tags.
	 *
	 * @param string $value Value to strip.
	 * @return string
	 */
	function wp_strip_all_tags( $value ) {
		return strip_tags( $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitize text for tests.
	 *
	 * @param string $value Value to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Convert a value to a positive integer.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_sprintf' ) ) {
	/**
	 * Minimal wp_sprintf support for product list formatting.
	 *
	 * @param string $pattern Pattern.
	 * @param array  $items   List items.
	 * @return string
	 */
	function wp_sprintf( $pattern, $items ) {
		if ( '%l' === $pattern && is_array( $items ) ) {
			return implode( ', ', $items );
		}

		return sprintf( $pattern, $items );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Return the default test clock value.
	 *
	 * @return string
	 */
	function fgcbg_test_default_current_time() {
		return gmdate( 'Y-m-d H:i:s', 0 );
	}

	/**
	 * Return a stable current time for tests.
	 *
	 * @param string $type Time format requested.
	 * @param int    $gmt  Whether to use GMT.
	 * @return int|string
	 */
	function current_time( $type = 'mysql', $gmt = 0 ) {
		unset( $gmt );

		$current_time = isset( $GLOBALS['fgcbg_test_current_time'] )
			? (string) $GLOBALS['fgcbg_test_current_time']
			: fgcbg_test_default_current_time();

		if ( 'timestamp' === $type ) {
			return (int) strtotime( $current_time );
		}

		return $current_time;
	}
}

if ( ! function_exists( 'current_datetime' ) ) {
	/**
	 * Return a stable current datetime for tests.
	 *
	 * @return DateTimeImmutable
	 */
	function current_datetime() {
		$current_time = isset( $GLOBALS['fgcbg_test_current_time'] )
			? (string) $GLOBALS['fgcbg_test_current_time']
			: (string) fgcbg_test_default_current_time();

		return new DateTimeImmutable( '@' . (string) strtotime( $current_time ) );
	}
}

if ( ! class_exists( 'FGCBG_Test_Product' ) ) {
	/**
	 * Minimal WooCommerce product double.
	 */
	class FGCBG_Test_Product {
		/**
		 * Product name.
		 *
		 * @var string
		 */
		private $name;

		/**
		 * Parent product ID.
		 *
		 * @var int
		 */
		private $parent_id;

		/**
		 * Constructor.
		 *
		 * @param string $name      Product name.
		 * @param int    $parent_id Parent product ID.
		 */
		public function __construct( $name, $parent_id = 0 ) {
			$this->name      = $name;
			$this->parent_id = $parent_id;
		}

		/**
		 * Get product name.
		 *
		 * @return string
		 */
		public function get_name() {
			return $this->name;
		}

		/**
		 * Get parent product ID.
		 *
		 * @return int
		 */
		public function get_parent_id() {
			return $this->parent_id;
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Get a product from the test product store.
	 *
	 * @param int $product_id Product ID.
	 * @return FGCBG_Test_Product|null
	 */
	function wc_get_product( $product_id ) {
		return $GLOBALS['fgcbg_test_products'][ $product_id ] ?? null;
	}
}

if ( ! function_exists( 'wc_get_coupon_types' ) ) {
	/**
	 * Return registered coupon types.
	 *
	 * @return array<string, string>
	 */
	function wc_get_coupon_types() {
		return array(
			'fixed_cart'    => 'Fixed cart discount',
			'fixed_product' => 'Fixed product discount',
			'percent'       => 'Percentage discount',
			'free_gift'     => 'Free Gift',
		);
	}
}

if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
	/**
	 * Check whether a test coupon code exists.
	 *
	 * @param string $code Coupon code.
	 * @return int Coupon ID or zero.
	 */
	function wc_get_coupon_id_by_code( $code ) {
		foreach ( $GLOBALS['fgcbg_test_coupons'] as $coupon ) {
			if ( method_exists( $coupon, 'get_code' ) && $coupon->get_code() === $code ) {
				return $coupon->get_id();
			}
		}

		return 0;
	}
}

if ( ! class_exists( 'WC_Coupon' ) ) {
	/**
	 * Minimal WooCommerce coupon double.
	 */
	class WC_Coupon {
		/**
		 * Coupon ID.
		 *
		 * @var int
		 */
		private $id = 0;

		/**
		 * Coupon properties.
		 *
		 * @var array<string, mixed>
		 */
		private $props = array();

		/**
		 * Coupon metadata.
		 *
		 * @var array<string, mixed>
		 */
		private $meta = array();

		/**
		 * Set coupon code.
		 *
		 * @param string $code Coupon code.
		 * @return void
		 */
		public function set_code( $code ) {
			$this->props['code'] = $code;
		}

		/**
		 * Set coupon description.
		 *
		 * @param string $description Description.
		 * @return void
		 */
		public function set_description( $description ) {
			$this->props['description'] = $description;
		}

		/**
		 * Set discount type.
		 *
		 * @param string $type Discount type.
		 * @return void
		 */
		public function set_discount_type( $type ) {
			$this->props['discount_type'] = $type;
		}

		/**
		 * Set individual use.
		 *
		 * @param bool $individual_use Individual use flag.
		 * @return void
		 */
		public function set_individual_use( $individual_use ) {
			$this->props['individual_use'] = $individual_use;
		}

		/**
		 * Set usage limit.
		 *
		 * @param int $usage_limit Usage limit.
		 * @return void
		 */
		public function set_usage_limit( $usage_limit ) {
			$this->props['usage_limit'] = $usage_limit;
		}

		/**
		 * Set expiry timestamp.
		 *
		 * @param int $date_expires Expiry timestamp.
		 * @return void
		 */
		public function set_date_expires( $date_expires ) {
			$this->props['date_expires'] = $date_expires;
		}

		/**
		 * Update coupon metadata.
		 *
		 * @param string $key   Meta key.
		 * @param mixed  $value Meta value.
		 * @return void
		 */
		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}

		/**
		 * Save the coupon to the test store.
		 *
		 * @return void
		 */
		public function save() {
			$this->id = count( $GLOBALS['fgcbg_test_coupons'] ) + 1;
			$GLOBALS['fgcbg_test_coupons'][] = $this;
		}

		/**
		 * Get coupon ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return $this->id;
		}

		/**
		 * Get coupon code.
		 *
		 * @return string
		 */
		public function get_code() {
			return $this->props['code'] ?? '';
		}

		/**
		 * Get discount type.
		 *
		 * @return string
		 */
		public function get_discount_type() {
			return $this->props['discount_type'] ?? '';
		}

		/**
		 * Get a coupon property.
		 *
		 * @param string $key Property key.
		 * @return mixed
		 */
		public function get_prop( $key ) {
			return $this->props[ $key ] ?? null;
		}

		/**
		 * Get coupon metadata.
		 *
		 * @param string $key Meta key.
		 * @return mixed
		 */
		public function get_meta( $key ) {
			return $this->meta[ $key ] ?? null;
		}
	}
}

if ( ! function_exists( 'wc_get_logger' ) ) {
	/**
	 * Get a minimal WooCommerce logger.
	 *
	 * @return object
	 */
	function wc_get_logger() {
		return new class() {
			/**
			 * Record an error.
			 *
			 * @return void
			 */
			public function error() {
			}
		};
	}
}

require dirname( __DIR__ ) . '/free-gift-bulk-coupon-generator.php';
