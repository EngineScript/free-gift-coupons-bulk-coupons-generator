/**
 * Free Gift Coupons Bulk Generator - Admin JavaScript.
 *
 * Modern browser code for the WordPress 6.8+ admin. Request-specific data is
 * injected before this file as `fgcbgAdminConfig`.
 */

( () => {
	const config = Object.freeze( globalThis.fgcbgAdminConfig ?? {} );

	const selectors = Object.freeze( {
		codeLength: '#coupon_code_length',
		couponCount: '#number_of_coupons',
		downloadButton: '#fgcbg-download-codes',
		form: '.fgcbg-form',
		generatedCodes: '#fgcbg-generated-codes',
		prefix: '#coupon_prefix',
		productIds: '#fgcbg_product_ids',
		progress: '#fgcbg-progress',
		progressBar: '#fgcbg-progress-bar',
		progressText: '#fgcbg-progress-text',
		results: '#fgcbg-results',
		submitButton: '.button-primary',
		warning: '#coupon-count-warning',
	} );

	function toPositiveInteger( value, fallback ) {
		const parsed = Number.parseInt( value ?? fallback, 10 );

		return Number.isNaN( parsed ) || parsed < 1 ? fallback : parsed;
	}

	function message( key, fallback = '' ) {
		return String( config[ key ] ?? fallback );
	}

	function formatMessage( key, fallback, replacements ) {
		let template = message( key, fallback );

		for ( const [ placeholder, value ] of replacements ) {
			template = template.replace( placeholder, String( value ) );
		}

		return template;
	}

	const BATCH_SIZE = toPositiveInteger( config.batch_size, 10 );
	const MAX_COUPON_COUNT = toPositiveInteger( config.max_coupon_count_value, 100 );
	const MAX_PREFIX_LENGTH = toPositiveInteger( config.max_prefix_length, 8 );

	/**
	 * Create an element safely from a static tag name.
	 *
	 * @param {string} tag - Tag name.
	 * @param {Object} attrs - Attribute key/value pairs.
	 * @returns {HTMLElement} The new element.
	 */
	function createElement( tag, attrs = {} ) {
		const element = document.createElement( tag );

		for ( const [ key, value ] of Object.entries( attrs ) ) {
			element.setAttribute( key, value );
		}

		return element;
	}

	/**
	 * Build a coupon-count warning span using safe DOM construction.
	 *
	 * @param {string} modifier - CSS BEM modifier.
	 * @param {string} text - Warning message text.
	 * @returns {HTMLElement} The warning span element.
	 */
	function buildWarningSpan( modifier, text ) {
		const warning = createElement( 'span', {
			class: `fgcbg-coupon-count-warning is-${ modifier }`,
			id: 'coupon-count-warning',
		} );

		warning.textContent = text;

		return warning;
	}

	class AdminController {
		constructor() {
			const form = document.querySelector( selectors.form );

			this.elements = Object.freeze( {
				codeLength: document.querySelector( selectors.codeLength ),
				couponCount: document.querySelector( selectors.couponCount ),
				downloadButton: document.querySelector( selectors.downloadButton ),
				form,
				generatedCodes: document.querySelector( selectors.generatedCodes ),
				prefix: document.querySelector( selectors.prefix ),
				products: document.querySelector( selectors.productIds ),
				progress: document.querySelector( selectors.progress ),
				progressBar: document.querySelector( selectors.progressBar ),
				progressText: document.querySelector( selectors.progressText ),
				results: document.querySelector( selectors.results ),
				submitButton: form?.querySelector( selectors.submitButton ) ?? null,
			} );
		}

		/** Bootstrap all event bindings. */
		init() {
			if ( ! this.elements.form ) {
				return;
			}

			this.bindEvents();
			this.initFormValidation();
			this.resetLoadingState();
		}

		/** Attach DOM event handlers. */
		bindEvents() {
			this.elements.form.addEventListener( 'submit', ( event ) => this.handleFormSubmission( event ) );
			this.elements.prefix?.addEventListener( 'input', () => this.formatPrefix() );
			this.elements.couponCount?.addEventListener( 'input', () => this.validateNumberInput() );
			this.elements.codeLength?.addEventListener( 'input', () => this.validateCodeLengthInput() );
			this.elements.downloadButton?.addEventListener( 'click', () => this.downloadGeneratedCodes() );
			globalThis.addEventListener( 'beforeunload', ( event ) => this.warnBeforeUnload( event ) );
		}

		/**
		 * Handle form submission: validate, confirm large batches, run AJAX generation.
		 *
		 * @param {SubmitEvent} event - Submit event.
		 * @returns {void}
		 */
		handleFormSubmission( event ) {
			event.preventDefault();

			if ( ! this.validateForm() ) {
				return;
			}

			const total = Number.parseInt( this.elements.couponCount.value, 10 );

			if ( total > 25 ) {
				const confirmation = formatMessage( 'confirm_large_batch', '', [
					[ '%d', total ],
				] );

				// eslint-disable-next-line no-alert
				if ( ! globalThis.confirm( confirmation ) ) {
					return;
				}
			}

			void this.runBatchGeneration( total );
		}

		/**
		 * Run AJAX batch coupon generation with progress feedback.
		 *
		 * @param {number} total - Total coupons to generate.
		 * @returns {Promise<void>}
		 */
		async runBatchGeneration( total ) {
			const { form, generatedCodes, progress, progressText, results, submitButton } = this.elements;
			const collectedCodes = [];
			let generated = 0;
			let remaining = total;

			form.classList.add( 'loading' );
			if ( submitButton ) {
				submitButton.disabled = true;
			}
			if ( progress ) {
				progress.hidden = false;
			}
			if ( results ) {
				results.hidden = true;
			}
			if ( generatedCodes ) {
				generatedCodes.value = '';
			}
			this.updateProgress( 0 );

			try {
				while ( remaining > 0 ) {
					const batchSize = Math.min( BATCH_SIZE, remaining );
					const response = await this.sendBatchRequest( batchSize );

					if ( ! response?.success ) {
						this.showErrorMessage( response?.data?.message ?? message( 'generation_failed', 'Failed to generate coupons. Please try again.' ) );
						return;
					}

					const generatedInBatch = Number.parseInt( response.data?.generated ?? 0, 10 );
					generated += Number.isNaN( generatedInBatch ) ? 0 : generatedInBatch;

					if ( Array.isArray( response.data?.codes ) ) {
						collectedCodes.push( ...response.data.codes.map( String ) );
					}

					remaining -= batchSize;
					this.updateProgress( Math.min( 100, Math.round( ( generated / total ) * 100 ) ) );

					if ( progressText ) {
						progressText.textContent = formatMessage( 'generating_progress', '', [
							[ '%1$d', generated ],
							[ '%2$d', total ],
						] );
					}
				}
			} catch {
				this.showErrorMessage( message( 'generation_failed', 'Failed to generate coupons. Please try again.' ) );
			} finally {
				form.classList.remove( 'loading' );
				if ( submitButton ) {
					submitButton.disabled = false;
				}

				if ( generated > 0 ) {
					this.updateProgress( 100 );
					if ( generatedCodes ) {
						generatedCodes.value = collectedCodes.join( '\n' );
					}
					if ( results ) {
						results.hidden = false;
					}
					this.showSuccessMessage( formatMessage( 'generation_complete', '', [
						[ '%d', generated ],
					] ) );
				}

				if ( generated === 0 && progress ) {
					progress.hidden = true;
				}
			}
		}

		/**
		 * Send a single batch AJAX request.
		 *
		 * @param {number} batchSize - Number of coupons for this batch.
		 * @returns {Promise<Object>} Parsed JSON response.
		 */
		async sendBatchRequest( batchSize ) {
			const body = new URLSearchParams( {
				action: 'fgcbg_generate_batch',
				batch_size: String( batchSize ),
				coupon_code_length: this.elements.codeLength?.value ?? '',
				coupon_prefix: this.elements.prefix?.value ?? '',
				nonce: message( 'nonce' ),
			} );

			for ( const productId of this.getSelectedProductIds() ) {
				body.append( 'product_ids[]', productId );
			}

			const response = await fetch( 'admin-ajax.php', {
				body,
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
				},
				method: 'POST',
			} );
			const payload = await response.json().catch( () => null );

			if ( ! payload ) {
				throw new Error( `Unexpected AJAX response: ${ response.status }` );
			}

			return payload;
		}

		/** Sanitize the coupon prefix input on keystroke. */
		formatPrefix() {
			const { prefix } = this.elements;

			if ( ! prefix ) {
				return;
			}

			prefix.value = String( prefix.value )
				.replace( /[^a-zA-Z0-9]/g, '' )
				.toUpperCase()
				.slice( 0, MAX_PREFIX_LENGTH );
		}

		/** Validate and clamp the coupon-count input field. */
		validateNumberInput() {
			const { couponCount } = this.elements;

			if ( ! couponCount ) {
				return;
			}

			const raw = String( couponCount.value ).replace( /\D/g, '' );
			let num = Number.parseInt( raw, 10 );

			document.querySelector( selectors.warning )?.remove();

			if ( Number.isNaN( num ) || num < 1 ) {
				couponCount.value = '1';
				return;
			}

			if ( num > MAX_COUPON_COUNT ) {
				num = MAX_COUPON_COUNT;
				couponCount.value = String( num );
				couponCount.insertAdjacentElement(
					'afterend',
					buildWarningSpan(
						'error',
						formatMessage( 'max_coupons_warning', '', [
							[ '%d', MAX_COUPON_COUNT ],
						] )
					)
				);
				return;
			}

			couponCount.value = String( num );

			if ( num > 50 ) {
				couponCount.insertAdjacentElement(
					'afterend',
					buildWarningSpan( 'caution', message( 'many_coupons_warning' ) )
				);
			}
		}

		/**
		 * Run all field validations.
		 *
		 * @returns {boolean} True when valid.
		 */
		validateForm() {
			const errors = [];
			let firstInvalid = null;
			const validations = [
				[ () => this.validateProductSelection( errors ), this.elements.products ],
				[ () => this.validateCouponCount( errors ), this.elements.couponCount ],
				[ () => this.validateCouponPrefix( errors ), this.elements.prefix ],
				[ () => this.validateCodeLength( errors ), this.elements.codeLength ],
			];

			for ( const [ validate, field ] of validations ) {
				if ( ! validate() && firstInvalid === null ) {
					firstInvalid = field;
				}
			}

			if ( errors.length > 0 ) {
				this.showErrorMessage( errors.join( '\n' ) );
				firstInvalid?.classList.add( 'error' );
				firstInvalid?.focus();
			}

			return errors.length === 0;
		}

		/**
		 * Validate product selection.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateProductSelection( errors ) {
			if ( this.getSelectedProductIds().length === 0 ) {
				errors.push( message( 'select_product', 'Please select at least one product.' ) );
				return false;
			}

			return true;
		}

		/**
		 * Validate coupon count field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCouponCount( errors ) {
			const raw = this.elements.couponCount?.value.trim() ?? '';
			const count = Number.parseInt( raw, 10 );

			if ( ! raw || Number.isNaN( count ) || count < 1 ) {
				errors.push( message( 'invalid_coupon_count', 'Please enter a valid number of coupons (minimum 1).' ) );
				return false;
			}

			if ( count > MAX_COUPON_COUNT ) {
				errors.push( formatMessage( 'max_coupon_count', 'Maximum number of coupons is %d.', [
					[ '%d', MAX_COUPON_COUNT ],
				] ) );
				return false;
			}

			return true;
		}

		/**
		 * Validate coupon prefix field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCouponPrefix( errors ) {
			const prefix = this.elements.prefix?.value ?? '';

			if ( prefix.length > MAX_PREFIX_LENGTH ) {
				errors.push( formatMessage( 'prefix_too_long', 'Coupon prefix must be %d characters or less.', [
					[ '%d', MAX_PREFIX_LENGTH ],
				] ) );
				return false;
			}

			return true;
		}

		/** Validate and clamp the random coupon-code length field. */
		validateCodeLengthInput() {
			const { codeLength } = this.elements;

			if ( ! codeLength ) {
				return;
			}

			const min = toPositiveInteger( config.min_code_length, 8 );
			const max = toPositiveInteger( config.max_code_length, 32 );
			const raw = String( codeLength.value ).replace( /\D/g, '' );
			let num = Number.parseInt( raw, 10 );

			if ( Number.isNaN( num ) || num < min ) {
				codeLength.value = String( min );
				return;
			}

			if ( num > max ) {
				num = max;
			}

			codeLength.value = String( num );
		}

		/**
		 * Validate random coupon-code length field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCodeLength( errors ) {
			const min = toPositiveInteger( config.min_code_length, 8 );
			const max = toPositiveInteger( config.max_code_length, 32 );
			const raw = this.elements.codeLength?.value.trim() ?? '';
			const count = Number.parseInt( raw, 10 );

			if ( ! raw || Number.isNaN( count ) || count < min || count > max ) {
				errors.push( formatMessage( 'code_length_invalid', 'Please enter a random code length between %1$d and %2$d characters.', [
					[ '%1$d', min ],
					[ '%2$d', max ],
				] ) );
				return false;
			}

			return true;
		}

		/** Download the generated coupon code list as a plain text file. */
		downloadGeneratedCodes() {
			const codes = this.elements.generatedCodes?.value.trim() ?? '';

			if ( codes === '' ) {
				return;
			}

			const blob = new Blob( [ `${ codes }\n` ], { type: 'text/plain;charset=utf-8' } );
			const url = URL.createObjectURL( blob );
			const link = createElement( 'a', {
				download: 'free-gift-coupon-codes.txt',
				href: url,
			} );

			document.body.append( link );
			link.click();
			link.remove();

			URL.revokeObjectURL( url );
		}

		/** Wire up real-time error-class removal on focus/input. */
		initFormValidation() {
			const fields = [
				this.elements.products,
				this.elements.couponCount,
				this.elements.prefix,
				this.elements.codeLength,
			].filter( Boolean );

			for ( const field of fields ) {
				for ( const eventName of [ 'focus', 'input', 'change' ] ) {
					field.addEventListener( eventName, () => field.classList.remove( 'error' ) );
				}
			}
		}

		/**
		 * Display an error notice above the form.
		 *
		 * @param {string} text - Error text.
		 * @returns {void}
		 */
		showErrorMessage( text ) {
			document.querySelectorAll( '.fgcbg-error-message' ).forEach( ( notice ) => {
				notice.remove();
			} );

			const notice = createElement( 'div', { class: 'notice notice-error fgcbg-error-message' } );
			const paragraph = createElement( 'p' );
			paragraph.textContent = String( text ).slice( 0, 500 );
			notice.append( paragraph );

			this.insertNoticeBeforeForm( notice );

			globalThis.setTimeout( () => {
				notice.classList.add( 'is-dismissing' );
				notice.addEventListener( 'transitionend', () => notice.remove(), { once: true } );
				globalThis.setTimeout( () => notice.remove(), 500 );
			}, 5000 );
		}

		/**
		 * Display a success notice above the form.
		 *
		 * @param {string} text - Success text.
		 * @returns {void}
		 */
		showSuccessMessage( text ) {
			const notice = createElement( 'div', { class: 'notice notice-success is-dismissible fgcbg-success-message' } );
			const paragraph = createElement( 'p' );
			paragraph.textContent = String( text ).slice( 0, 500 );
			notice.append( paragraph );

			this.insertNoticeBeforeForm( notice );
		}

		/**
		 * Insert a notice element before the form and scroll it into view.
		 *
		 * @param {HTMLElement} notice - The notice element to insert.
		 * @returns {void}
		 */
		insertNoticeBeforeForm( notice ) {
			this.elements.form.before( notice );

			const prefersReducedMotion = globalThis.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches ?? false;
			notice.scrollIntoView( {
				behavior: prefersReducedMotion ? 'auto' : 'smooth',
				block: 'start',
			} );
		}

		/**
		 * Update the visual progress bar.
		 *
		 * @param {number} percent - Progress percentage.
		 * @returns {void}
		 */
		updateProgress( percent ) {
			if ( ! this.elements.progressBar ) {
				return;
			}

			this.elements.progressBar.style.width = `${ percent }%`;
			this.elements.progressBar.setAttribute( 'aria-valuenow', String( percent ) );
		}

		/**
		 * Get selected product IDs from the WooCommerce enhanced select.
		 *
		 * @returns {string[]} Selected product IDs.
		 */
		getSelectedProductIds() {
			return Array.from(
				this.elements.products?.selectedOptions ?? [],
				( option ) => option.value
			).filter( Boolean );
		}

		/**
		 * Warn before navigating away during generation.
		 *
		 * @param {BeforeUnloadEvent} event - Before unload event.
		 * @returns {string|undefined} Warning message for legacy browsers.
		 */
		warnBeforeUnload( event ) {
			if ( ! this.elements.form.classList.contains( 'loading' ) ) {
				return undefined;
			}

			const warning = message( 'generation_in_progress' );
			event.preventDefault();
			event.returnValue = warning;

			return warning;
		}

		/** Reset loading state on fresh page load (back-button / refresh edge case). */
		resetLoadingState() {
			this.elements.form.classList.remove( 'loading' );
			if ( this.elements.submitButton ) {
				this.elements.submitButton.disabled = false;
			}
		}
	}

	function boot() {
		new AdminController().init();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	} else {
		boot();
	}
} )();
