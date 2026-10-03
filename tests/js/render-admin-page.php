<?php
/**
 * Render the generator screen and its script settings for the admin script tests.
 *
 * Prints one JSON object: `html` is the markup FGCBG_Admin_Page::render()
 * produces, and `config` is the `fgcbgAdminConfig` object as the browser
 * receives it from wp_localize_script(), which turns every value into a
 * string. WordPress is not loaded; the few functions the two classes call are
 * defined below.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

/**
 * Escape text for HTML.
 *
 * @param mixed $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escape text for an attribute. Like WordPress, existing entities are kept.
 *
 * @param mixed $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}

/**
 * Return text unchanged; no translations are loaded.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Return escaped text.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html__( $text ) {
	return esc_html( $text );
}

/**
 * Print escaped text.
 *
 * @param string $text Text.
 * @return void
 */
function esc_html_e( $text ) {
	echo esc_html( $text );
}

/**
 * Print text escaped for an attribute.
 *
 * @param string $text Text.
 * @return void
 */
function esc_attr_e( $text ) {
	echo esc_attr( $text );
}

/**
 * Build an admin URL.
 *
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

/**
 * Return a fixed nonce.
 *
 * @param string $action Nonce action.
 * @return string
 */
function wp_create_nonce( $action ) {
	return 'nonce-' . $action;
}

require ABSPATH . 'includes/class-fgcbg-coupon-generator.php';
require ABSPATH . 'includes/class-fgcbg-ajax-handler.php';
require ABSPATH . 'includes/class-fgcbg-admin-assets.php';
require ABSPATH . 'includes/class-fgcbg-admin-page.php';

ob_start();
( new FGCBG_Admin_Page() )->render();
$fgcbg_html = (string) ob_get_clean();

$fgcbg_config = ( new ReflectionMethod( FGCBG_Admin_Assets::class, 'get_script_data' ) )->invoke( new FGCBG_Admin_Assets() );

// wp_localize_script() sends every scalar as a string with HTML entities decoded.
foreach ( $fgcbg_config as $fgcbg_key => $fgcbg_value ) {
	if ( is_scalar( $fgcbg_value ) ) {
		$fgcbg_config[ $fgcbg_key ] = html_entity_decode( (string) $fgcbg_value, ENT_QUOTES, 'UTF-8' );
	}
}

echo json_encode(
	array(
		'html'   => $fgcbg_html,
		'config' => $fgcbg_config,
	),
	JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
