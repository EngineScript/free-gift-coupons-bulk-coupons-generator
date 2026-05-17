/* eslint-disable jsdoc/check-tag-names */
/**
 * Free Gift Coupons Bulk Generator - Admin JavaScript.
 *
 * Modern ESNext code. WordPress 6.8+ targets browsers with full ES6+ support.
 * All user-facing strings are sourced from `fgcbg_i18n`.
 */
/* eslint-enable jsdoc/check-tag-names */

( ( $ ) => {
	/** Runtime configuration injected with wp_add_inline_script(). */
	const i18n = window.fgcbg_i18n ?? {};
	const configuredBatchSize = parseInt( i18n.batch_size ?? 10, 10 );
	const BATCH_SIZE = Number.isNaN( configuredBatchSize ) || configuredBatchSize < 1 ? 10 : configuredBatchSize;
	const configuredMaxCoupons = parseInt( i18n.max_coupon_count_value ?? 100, 10 );
	const MAX_COUPON_COUNT = Number.isNaN( configuredMaxCoupons ) || configuredMaxCoupons < 1 ? 100 : configuredMaxCoupons;
	const configuredMaxPrefix = parseInt( i18n.max_prefix_length ?? 8, 10 );
	const MAX_PREFIX_LENGTH = Number.isNaN( configuredMaxPrefix ) || configuredMaxPrefix < 1 ? 8 : configuredMaxPrefix;

	/**
	 * Create a jQuery element safely from a static HTML tag string.
	 *
	 * @param {string} tag - Tag name (e.g. 'div', 'span').
	 * @param {Object} attrs - Attribute key/value pairs.
	 * @returns {jQuery} The new element.
	 */
	function createElement( tag, attrs ) {
		// Create element using document.createElement (safe, no string parsing)
		const domElement = document.createElement( tag );
		const el = jQuery( domElement );
		if ( attrs ) {
			el.attr( attrs );
		}
		return el;
	}

	/**
	 * Build a coupon-count warning span using safe DOM construction.
	 *
	 * @param {string} modifier - CSS BEM modifier ('error' or 'caution').
	 * @param {string} text - Warning message text (escaped via .text()).
	 * @returns {jQuery} The warning span element.
	 */
	function buildWarningSpan( modifier, text ) {
		return createElement( 'span', {
			id: 'coupon-count-warning',
			class: `fgcbg-coupon-count-warning is-${ modifier }`,
		} ).text( text );
	}

	/**
	 * Insert a notice element before the form and scroll it into view.
	 *
	 * @param {jQuery} $el - The notice element to insert.
	 */
	function insertNoticeBeforeForm( $el ) {
		$el.insertBefore( $( '.fgcbg-form' ) );

		const offset = $el.offset();
		if ( offset?.top ) {
			$( 'html, body' ).animate( { scrollTop: Math.max( 0, offset.top - 50 ) }, 300 );
		}
	}

	/**
	 * Send a single batch AJAX request and return the jQuery promise.
	 *
	 * @param {number} batchSize - Number of coupons for this batch.
	 * @returns {Promise} jQuery AJAX promise.
	 */
	function sendBatchRequest( batchSize ) {
		return $.ajax( {
			url: i18n.ajax_url,
			type: 'POST',
			data: {
				action: 'fgcbg_generate_batch',
				nonce: i18n.nonce,
				product_ids: $( '#fgcbg_product_ids' ).val(),
				batch_size: batchSize,
				coupon_prefix: $( '#coupon_prefix' ).val(),
				coupon_code_length: $( '#coupon_code_length' ).val(),
			},
		} );
	}

	/**
	 * Main admin controller for the coupon generator form.
	 */
	const FGCBG_Admin = {

		/**
		 * Clean a string to uppercase alphanumeric only.
		 *
		 * @param {string} value - Raw input value.
		 * @param {number} maxLength - Maximum output length.
		 * @returns {string} Cleaned value.
		 */
		cleanAlphanumeric( value, maxLength = MAX_PREFIX_LENGTH ) {
			return String( value )
				.replace( /[^a-zA-Z0-9]/g, '' )
				.toUpperCase()
				.slice( 0, maxLength );
		},

		/** Bootstrap all event bindings. */
		init() {
			this.bindEvents();
			this.initFormValidation();
		},

		/** Attach DOM event handlers. */
		bindEvents() {
			$( '.fgcbg-form' ).on( 'submit', this.handleFormSubmission );
			$( '#coupon_prefix' ).on( 'input', this.formatPrefix );
			$( '#number_of_coupons' ).on( 'input', this.validateNumberInput );
			$( '#coupon_code_length' ).on( 'input', this.validateCodeLengthInput );
			$( '#fgcbg-download-codes' ).on( 'click', this.downloadGeneratedCodes );
		},

		/**
		 * Handle form submission - validate, confirm large batches, run AJAX generation.
		 *
		 * @param {Event} e - Submit event.
		 */
		handleFormSubmission( e ) {
			e.preventDefault();

			if ( ! FGCBG_Admin.validateForm() ) {
				return;
			}

			const total = parseInt( $( '#number_of_coupons' ).val(), 10 );

			if ( total > 25 ) {
				const message = String( i18n.confirm_large_batch ?? '' ).replace( '%d', String( total ) );

				if ( ! confirm( message ) ) { // eslint-disable-line no-alert
					return;
				}
			}

			FGCBG_Admin.runBatchGeneration( total );
		},

		/**
		 * Run AJAX batch coupon generation with progress feedback.
		 * Uses recursive callbacks instead of await-in-loop.
		 *
		 * @param {number} total - Total coupons to generate.
		 */
		runBatchGeneration( total ) {
			const $form = $( '.fgcbg-form' );
			const $submitBtn = $form.find( '.button-primary' );
			const $progress = $( '#fgcbg-progress' );
			const $bar = $( '#fgcbg-progress-bar' );
			const $text = $( '#fgcbg-progress-text' );
			const $results = $( '#fgcbg-results' );
			const $codes = $( '#fgcbg-generated-codes' );

			$form.addClass( 'loading' );
			$submitBtn.prop( 'disabled', true );
			$progress.prop( 'hidden', false );
			$results.prop( 'hidden', true );
			$codes.val( '' );
			$bar.css( 'width', '0%' ).attr( 'aria-valuenow', '0' );
			$text.text( '' );

			let generated = 0;
			let remaining = total;
			const generatedCodes = [];

			/**
			 * Process the next batch recursively.
			 */
			function processNextBatch() {
				if ( remaining <= 0 ) {
					onComplete();
					return;
				}

				const batchSize = Math.min( BATCH_SIZE, remaining );

				sendBatchRequest( batchSize )
					.done( ( response ) => {
						if ( response?.success ) {
							generated += response.data?.generated ?? 0;
							if ( Array.isArray( response.data?.codes ) ) {
								generatedCodes.push( ...response.data.codes );
							}
						} else {
							const msg = response?.data?.message ?? ( i18n.generation_failed ?? '' );
							FGCBG_Admin.showErrorMessage( msg );
							onComplete();
							return;
						}

						remaining -= batchSize;

						const pct = Math.round( ( generated / total ) * 100 );
						$bar.css( 'width', `${ pct }%` ).attr( 'aria-valuenow', pct );
						$text.text(
							String( i18n.generating_progress ?? '' )
								.replace( '%1$d', String( generated ) )
								.replace( '%2$d', String( total ) )
						);

						processNextBatch();
					} )
					.fail( () => {
						FGCBG_Admin.showErrorMessage( i18n.generation_failed ?? '' );
						onComplete();
					} );
			}

			/**
			 * Finalize the generation run - restore UI state and show result.
			 */
			function onComplete() {
				$form.removeClass( 'loading' );
				$submitBtn.prop( 'disabled', false );

				if ( generated > 0 ) {
					$bar.css( 'width', '100%' ).attr( 'aria-valuenow', '100' );
					$codes.val( generatedCodes.join( '\n' ) );
					$results.prop( 'hidden', false );
					const msg = String( i18n.generation_complete ?? '' ).replace( '%d', String( generated ) );
					FGCBG_Admin.showSuccessMessage( msg );
				}

				if ( generated === 0 ) {
					$progress.prop( 'hidden', true );
				}
			}

			processNextBatch();
		},

		/** Sanitize the coupon prefix input on keystroke. */
		formatPrefix() {
			const $input = $( this );
			$input.val( FGCBG_Admin.cleanAlphanumeric( $input.val(), MAX_PREFIX_LENGTH ) );
		},

		/** Validate and clamp the coupon-count input field. */
		validateNumberInput() {
			const $input = $( this );
			const raw = String( $input.val() ).replace( /\D/g, '' );
			let num = parseInt( raw, 10 );

			$( '#coupon-count-warning' ).remove();

			if ( Number.isNaN( num ) || num < 1 ) {
				$input.val( '1' );
				return;
			}

			if ( num > MAX_COUPON_COUNT ) {
				num = MAX_COUPON_COUNT;
				$input.val( num );
				buildWarningSpan(
					'error',
					String( i18n.max_coupons_warning ?? '' ).replace( '%d', String( MAX_COUPON_COUNT ) )
				).insertAfter( $input );
			} else if ( num > 50 ) {
				$input.val( num );
				buildWarningSpan( 'caution', i18n.many_coupons_warning ?? '' ).insertAfter( $input );
			} else {
				$input.val( num );
			}
		},

		/**
		 * Run all field validations.
		 *
		 * @returns {boolean} True when valid.
		 */
		validateForm() {
			const errors = [];
			let firstInvalid = null;

			if ( ! this.validateProductSelection( errors ) && ! firstInvalid ) {
				firstInvalid = $( '#fgcbg_product_ids' );
			}
			if ( ! this.validateCouponCount( errors ) && ! firstInvalid ) {
				firstInvalid = $( '#number_of_coupons' );
			}
			if ( ! this.validateCouponPrefix( errors ) && ! firstInvalid ) {
				firstInvalid = $( '#coupon_prefix' );
			}
			if ( ! this.validateCodeLength( errors ) && ! firstInvalid ) {
				firstInvalid = $( '#coupon_code_length' );
			}

			if ( errors.length > 0 ) {
				this.showErrorMessage( errors.join( '\n' ) );
				firstInvalid?.addClass( 'error' ).trigger( 'focus' );
			}

			return errors.length === 0;
		},

		/**
		 * Validate product selection.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateProductSelection( errors ) {
			const ids = $( '#fgcbg_product_ids' ).val();
			if ( ! ids || ( Array.isArray( ids ) && ids.length === 0 ) ) {
				errors.push( i18n.select_product ?? 'Please select at least one product.' );
				return false;
			}
			return true;
		},

		/**
		 * Validate coupon count field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCouponCount( errors ) {
			const raw = $( '#number_of_coupons' ).val();
			const count = parseInt( raw, 10 );

			if ( ! raw || Number.isNaN( count ) || count < 1 ) {
				errors.push( i18n.invalid_coupon_count ?? 'Please enter a valid number of coupons (minimum 1).' );
				return false;
			}
			if ( count > MAX_COUPON_COUNT ) {
				errors.push(
					String( i18n.max_coupon_count ?? 'Maximum number of coupons is %d.' )
						.replace( '%d', String( MAX_COUPON_COUNT ) )
				);
				return false;
			}
			return true;
		},

		/**
		 * Validate coupon prefix field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCouponPrefix( errors ) {
			const prefix = $( '#coupon_prefix' ).val();
			if ( prefix && prefix.length > MAX_PREFIX_LENGTH ) {
				errors.push(
					String( i18n.prefix_too_long ?? 'Coupon prefix must be %d characters or less.' )
						.replace( '%d', String( MAX_PREFIX_LENGTH ) )
				);
				return false;
			}
			return true;
		},

		/** Validate and clamp the random coupon-code length field. */
		validateCodeLengthInput() {
			const $input = $( this );
			const min = parseInt( i18n.min_code_length ?? 8, 10 );
			const max = parseInt( i18n.max_code_length ?? 32, 10 );
			const raw = String( $input.val() ).replace( /\D/g, '' );
			let num = parseInt( raw, 10 );

			if ( Number.isNaN( num ) || num < min ) {
				$input.val( String( min ) );
				return;
			}

			if ( num > max ) {
				num = max;
			}

			$input.val( String( num ) );
		},

		/**
		 * Validate random coupon-code length field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCodeLength( errors ) {
			const min = parseInt( i18n.min_code_length ?? 8, 10 );
			const max = parseInt( i18n.max_code_length ?? 32, 10 );
			const raw = $( '#coupon_code_length' ).val();
			const count = parseInt( raw, 10 );

			if ( ! raw || Number.isNaN( count ) || count < min || count > max ) {
				errors.push(
					String( i18n.code_length_invalid ?? 'Please enter a random code length between %1$d and %2$d characters.' )
						.replace( '%1$d', String( min ) )
						.replace( '%2$d', String( max ) )
				);
				return false;
			}
			return true;
		},

		/** Download the generated coupon code list as a plain text file. */
		downloadGeneratedCodes() {
			const codes = String( $( '#fgcbg-generated-codes' ).val() ?? '' ).trim();
			if ( '' === codes ) {
				return;
			}

			const blob = new Blob( [ `${ codes }\n` ], { type: 'text/plain;charset=utf-8' } );
			const url = window.URL.createObjectURL( blob );
			const link = document.createElement( 'a' );

			link.href = url;
			link.download = 'free-gift-coupon-codes.txt';
			document.body.appendChild( link );
			link.click();
			link.remove();

			window.URL.revokeObjectURL( url );
		},

		/** Wire up real-time error-class removal on focus/input. */
		initFormValidation() {
			$( '#fgcbg_product_ids, #number_of_coupons, #coupon_prefix, #coupon_code_length' ).on(
				'focus input change',
				function () {
					$( this ).removeClass( 'error' );
				}
			);
		},

		/**
		 * Display an error notice above the form.
		 *
		 * @param {string} message - Error text.
		 */
		showErrorMessage( message ) {
			$( '.fgcbg-error-message' ).remove();

			message = String( message ).slice( 0, 500 );

			const $el = createElement( 'div', { class: 'notice notice-error fgcbg-error-message' } );
			$el.append( createElement( 'p' ).text( message ) );
			insertNoticeBeforeForm( $el );

			setTimeout( () => $el.fadeOut( 400, () => $el.remove() ), 5000 );
		},

		/**
		 * Display a success notice above the form.
		 *
		 * @param {string} message - Success text.
		 */
		showSuccessMessage( message ) {
			message = String( message ).slice( 0, 500 );

			const $el = createElement( 'div', { class: 'notice notice-success is-dismissible' } );
			$el.append( createElement( 'p' ).text( message ) );
			insertNoticeBeforeForm( $el );
		},
	};

	// Boot.
	FGCBG_Admin.init();

	// Warn on navigating away during generation.
	$( window ).on( 'beforeunload', () => {
		if ( $( '.fgcbg-form' ).hasClass( 'loading' ) ) {
			return i18n.generation_in_progress ?? '';
		}
	} );

	// Reset loading state on fresh page load (back-button / refresh edge case).
	$( '.fgcbg-form' ).removeClass( 'loading' );
	$( '.button-primary' ).prop( 'disabled', false );
} )( jQuery );
