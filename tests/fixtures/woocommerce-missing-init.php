<?php
/**
 * Isolated bootstrap fixture for testing plugin initialization without WooCommerce.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );

$GLOBALS['fgcbg_fixture_hooks'] = array();

/**
 * Compare two hook callbacks.
 *
 * @param callable|string $registered Registered callback.
 * @param callable|string $target     Target callback.
 * @return bool
 */
function fgcbg_fixture_callbacks_match( $registered, $target ) {
	if ( is_string( $registered ) || is_string( $target ) ) {
		return $registered === $target;
	}

	if ( is_array( $registered ) && is_array( $target ) ) {
		return $registered[0] === $target[0] && $registered[1] === $target[1];
	}

	return $registered === $target;
}

/**
 * Register an action callback.
 *
 * @param string          $hook          Hook name.
 * @param callable|string $callback      Callback.
 * @param int             $priority      Hook priority.
 * @param int             $accepted_args Accepted args.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['fgcbg_fixture_hooks'][ $hook ][ $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * Check whether an action callback is registered.
 *
 * @param string          $hook     Hook name.
 * @param callable|string $callback Optional callback.
 * @return int|false
 */
function has_action( $hook, $callback = false ) {
	if ( empty( $GLOBALS['fgcbg_fixture_hooks'][ $hook ] ) ) {
		return false;
	}

	foreach ( $GLOBALS['fgcbg_fixture_hooks'][ $hook ] as $priority => $callbacks ) {
		if ( false === $callback ) {
			return (int) $priority;
		}

		foreach ( $callbacks as $registered ) {
			if ( fgcbg_fixture_callbacks_match( $registered['callback'], $callback ) ) {
				return (int) $priority;
			}
		}
	}

	return false;
}

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

/**
 * Get the filesystem path for a plugin file.
 *
 * @param string $file Plugin file.
 * @return string Directory path.
 */
function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
}

require dirname( __DIR__, 2 ) . '/free-gift-bulk-coupon-generator.php';

fgcbg_init();

$plugin = FGCBG_Plugin::get_instance();

echo json_encode(
	array(
		'woocommerce_loaded'      => class_exists( 'WooCommerce' ),
		'plugins_loaded_priority' => has_action( 'plugins_loaded', 'fgcbg_init' ),
		'missing_notice_priority' => has_action( 'admin_notices', array( $plugin, 'woocommerce_missing_notice' ) ),
		'admin_menu_priority'     => has_action( 'admin_menu', array( $plugin, 'add_admin_menu' ) ),
		'admin_enqueue_priority'  => has_action( 'admin_enqueue_scripts' ),
		'ajax_priority'           => has_action( 'wp_ajax_fgcbg_generate_batch' ),
	),
	JSON_THROW_ON_ERROR
);
