<?php
/**
 * Stand-in for WooCommerce's FeaturesUtil, loaded only by the test that needs it.
 *
 * It records what the plugin declares. It is kept out of tests/bootstrap.php
 * so that the suite can also call the plugin while the class does not exist.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

namespace Automattic\WooCommerce\Utilities;

/**
 * Records compatibility declarations.
 */
final class FeaturesUtil {

	/**
	 * Record one declaration.
	 *
	 * @param string $feature_id         Feature name.
	 * @param string $plugin_file        Plugin entry file.
	 * @param bool   $positive_compatibility Whether the plugin is compatible.
	 * @return bool
	 */
	public static function declare_compatibility( string $feature_id, string $plugin_file, bool $positive_compatibility = true ): bool {
		$GLOBALS['fgcbg_test_declared_compatibility'][] = array( $feature_id, $plugin_file, $positive_compatibility );

		return true;
	}
}
