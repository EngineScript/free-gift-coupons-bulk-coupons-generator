<?php
/**
 * Render the generator screen and its script settings for the admin script tests.
 *
 * Prints one JSON object: `html` is the markup FGCBG_Admin_Page::render()
 * produces, and `config` is the `fgcbgAdminConfig` object as the browser
 * receives it from wp_localize_script(), which turns every value into a
 * string. WordPress is not loaded; tests/bootstrap.php stands in for it, as it
 * does for the unit suite.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

require dirname( __DIR__ ) . '/bootstrap.php';

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
