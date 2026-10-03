<?php
/**
 * Admin page tests.
 *
 * @package FreeGiftCouponsBulkGenerator
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the generator screen markup.
 */
final class AdminPageTest extends TestCase {

	/**
	 * Render the page and return its markup.
	 */
	private function render_page(): string {
		ob_start();
		( new FGCBG_Admin_Page() )->render();

		return (string) ob_get_clean();
	}

	/**
	 * Every element the admin script looks up by ID is present exactly once.
	 */
	public function test_page_contains_the_elements_the_script_uses(): void {
		$html = $this->render_page();

		foreach (
			array(
				'fgcbg_product_ids',
				'number_of_coupons',
				'coupon_prefix',
				'coupon_code_length',
				'fgcbg-config-error',
				'fgcbg-progress',
				'fgcbg-progress-bar',
				'fgcbg-progress-text',
				'fgcbg-results',
				'fgcbg-generated-codes',
				'fgcbg-download-codes',
			) as $element_id
		) {
			$this->assertSame( 1, substr_count( $html, 'id="' . $element_id . '"' ), sprintf( 'Expected exactly one element with ID "%s".', $element_id ) );
		}

		$this->assertStringContainsString( 'class="fgcbg-form"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
	}

	/**
	 * Field limits and defaults come from the generator constants.
	 */
	public function test_fields_use_the_generator_limits(): void {
		$html = $this->render_page();

		$this->assertStringContainsString( 'max="' . FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH . '"', $html );
		$this->assertStringContainsString( 'maxlength="' . FGCBG_Coupon_Generator::MAX_PREFIX_LENGTH . '"', $html );
		$this->assertStringContainsString( 'min="' . FGCBG_Coupon_Generator::MIN_CODE_LENGTH . '"', $html );
		$this->assertStringContainsString( 'max="' . FGCBG_Coupon_Generator::MAX_CODE_LENGTH . '"', $html );
		$this->assertStringContainsString( 'value="' . FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH . '"', $html );
	}

	/**
	 * The settings-error notice ships hidden; the script reveals it when needed.
	 */
	public function test_settings_error_notice_is_hidden_by_default(): void {
		$this->assertMatchesRegularExpression( '/<div id="fgcbg-config-error"[^>]*\shidden>/', $this->render_page() );
	}
}
