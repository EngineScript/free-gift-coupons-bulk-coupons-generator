<?php
/**
 * Admin page rendering.
 *
 * @package FreeGiftCouponsBulkGenerator
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin admin page.
 *
 * @since 1.6.0
 */
final class FGCBG_Admin_Page {

	/**
	 * Render the admin page.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Free Gift Coupons Bulk Coupon Generator', 'free-gift-coupons-bulk-coupons-generator' ); ?></h1>
			<p><?php esc_html_e( 'Generate bulk free gift coupons that work with the Free Gift Coupons for WooCommerce plugin. These coupons are created with the proper data structure required for free gift functionality.', 'free-gift-coupons-bulk-coupons-generator' ); ?></p>

			<div class="fgcbg-admin-container">
				<div class="fgcbg-main-content">
					<?php $this->render_admin_form(); ?>
				</div>

				<div class="fgcbg-sidebar">
					<?php $this->render_admin_sidebar(); ?>
				</div>
			</div>

			<?php $this->render_admin_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Render admin form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_admin_form(): void {
		?>
		<form class="fgcbg-form">

			<table class="form-table">
				<?php $this->render_product_selection_field(); ?>
				<?php $this->render_coupon_count_field(); ?>
				<?php $this->render_coupon_prefix_field(); ?>
				<?php $this->render_coupon_code_length_field(); ?>
			</table>

			<p class="submit">
				<button type="submit" class="button-primary">
					<?php esc_html_e( 'Generate Free Gift Coupons', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</button>
			</p>

			<div id="fgcbg-progress" class="fgcbg-progress" hidden>
				<div class="fgcbg-progress-track">
					<div id="fgcbg-progress-bar" class="fgcbg-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
				</div>
				<p id="fgcbg-progress-text" class="fgcbg-progress-text"></p>
			</div>

			<div id="fgcbg-results" class="fgcbg-results" hidden>
				<h2><?php esc_html_e( 'Generated Coupon Codes', 'free-gift-coupons-bulk-coupons-generator' ); ?></h2>
				<textarea id="fgcbg-generated-codes" class="large-text code" rows="10" readonly aria-describedby="fgcbg-generated-codes-description"></textarea>
				<p id="fgcbg-generated-codes-description" class="description">
					<?php esc_html_e( 'One coupon code per line.', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</p>
				<p>
					<button type="button" id="fgcbg-download-codes" class="button">
						<?php esc_html_e( 'Download .txt', 'free-gift-coupons-bulk-coupons-generator' ); ?>
					</button>
				</p>
			</div>
		</form>
		<?php
	}

	/**
	 * Render product selection field.
	 *
	 * Uses WooCommerce's built-in AJAX product search (Select2) for scalability.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_product_selection_field(): void {
		?>
		<tr>
			<th scope="row">
				<label for="fgcbg_product_ids"><?php esc_html_e( 'Select Products', 'free-gift-coupons-bulk-coupons-generator' ); ?></label>
			</th>
			<td>
				<select class="wc-product-search fgcbg-product-search" multiple="multiple" id="fgcbg_product_ids" name="product_ids[]"
						data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'free-gift-coupons-bulk-coupons-generator' ); ?>"
						data-action="woocommerce_json_search_products_and_variations"
						data-allow_clear="true"
						aria-describedby="product-id-description">
				</select>
				<p class="description" id="product-id-description">
					<?php esc_html_e( 'Search and select one or more products that will be given as free gifts with the coupon.', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render coupon count field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_coupon_count_field(): void {
		?>
		<tr>
			<th scope="row">
				<label for="number_of_coupons"><?php esc_html_e( 'Number of Coupons', 'free-gift-coupons-bulk-coupons-generator' ); ?></label>
			</th>
			<td>
				<input type="number" name="number_of_coupons" id="number_of_coupons"
						class="regular-text" min="1" max="<?php echo esc_attr( (string) FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH ); ?>" value="10" required aria-describedby="coupon-count-description">
				<p class="description" id="coupon-count-description">
					<?php
					printf(
						/* translators: %d is the maximum number of coupons that can be generated in one run. */
						esc_html__( 'Enter the number of coupons to generate (maximum %d).', 'free-gift-coupons-bulk-coupons-generator' ),
						esc_html( (string) FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH )
					);
					?>
				</p>
				<div class="fgcbg-warning-box">
					<p class="fgcbg-warning-text">
						<span class="dashicons dashicons-warning fgcbg-warning-icon"></span>
						<?php esc_html_e( 'Note: Coupon generation can be time-consuming. Generating large numbers of coupons may cause the page to timeout based on your server\'s PHP timeout settings. If you need to generate many coupons, consider doing it in smaller batches.', 'free-gift-coupons-bulk-coupons-generator' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render coupon prefix field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_coupon_prefix_field(): void {
		?>
		<tr>
			<th scope="row">
				<label for="coupon_prefix"><?php esc_html_e( 'Coupon Prefix', 'free-gift-coupons-bulk-coupons-generator' ); ?></label>
			</th>
			<td>
				<input type="text" name="coupon_prefix" id="coupon_prefix"
						class="regular-text" maxlength="<?php echo esc_attr( (string) FGCBG_Coupon_Generator::MAX_PREFIX_LENGTH ); ?>" placeholder="e.g. GIFT" aria-describedby="coupon-prefix-description">
				<p class="description" id="coupon-prefix-description">
					<?php esc_html_e( 'Optional prefix for coupon codes (e.g. GIFTABC123DEF456).', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render generated coupon code length field.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	private function render_coupon_code_length_field(): void {
		?>
		<tr>
			<th scope="row">
				<label for="coupon_code_length"><?php esc_html_e( 'Random Code Length', 'free-gift-coupons-bulk-coupons-generator' ); ?></label>
			</th>
			<td>
				<input type="number" name="coupon_code_length" id="coupon_code_length"
						class="regular-text" min="<?php echo esc_attr( (string) FGCBG_Coupon_Generator::MIN_CODE_LENGTH ); ?>"
						max="<?php echo esc_attr( (string) FGCBG_Coupon_Generator::MAX_CODE_LENGTH ); ?>"
						value="<?php echo esc_attr( (string) FGCBG_Coupon_Generator::DEFAULT_CODE_LENGTH ); ?>"
						required aria-describedby="coupon-code-length-description">
				<p class="description" id="coupon-code-length-description">
					<?php esc_html_e( 'Number of random characters after the optional prefix. With an 8-character prefix, the total coupon code is at most 32 characters.', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render admin sidebar.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_admin_sidebar(): void {
		?>
		<div class="fgcbg-info-box">
			<h3><?php esc_html_e( 'Information', 'free-gift-coupons-bulk-coupons-generator' ); ?></h3>
			<ul>
				<li>
					<?php
					printf(
						/* translators: %d is the maximum number of coupons that can be generated in one run. */
						esc_html__( 'Maximum %d coupons can be generated at once', 'free-gift-coupons-bulk-coupons-generator' ),
						esc_html( (string) FGCBG_Coupon_Generator::MAX_COUPONS_PER_BATCH )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Coupons are set to expire after 1 year', 'free-gift-coupons-bulk-coupons-generator' ); ?></li>
				<li><?php esc_html_e( 'Each coupon can only be used once', 'free-gift-coupons-bulk-coupons-generator' ); ?></li>
				<li><?php esc_html_e( 'Coupons are set for individual use only', 'free-gift-coupons-bulk-coupons-generator' ); ?></li>
				<li><?php esc_html_e( 'Generated coupons appear in WooCommerce > Coupons', 'free-gift-coupons-bulk-coupons-generator' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render admin footer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_admin_footer(): void {
		?>
		<div class="fgcbg-footer">
			<p class="fgcbg-repo-link">
				<a href="https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View on GitHub', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</a>
				|
				<a href="https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/issues" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Report Issues', 'free-gift-coupons-bulk-coupons-generator' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
