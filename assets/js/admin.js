/**
 * Free Gift Coupons Bulk Generator - Admin JavaScript.
 *
 * Modern browser code for the WordPress 7.0+ admin. Request-specific data is
 * injected before this file as `fgcbgAdminConfig`. Every limit and every
 * message comes from that object; without it the script shows the notice the
 * page already contains and does nothing else.
 */

( () => {
	const config = Object.freeze( globalThis.fgcbgAdminConfig ?? {} );

	const selectors = Object.freeze( {
		codeLength: '#coupon_code_length',
		configError: '#fgcbg-config-error',
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

	/** Coupon totals above this ask for confirmation before generating. */
	const CONFIRM_ABOVE = 25;

	/** Coupon totals above this show a caution beside the field. */
	const CAUTION_ABOVE = 50;

	function message( key ) {
		return String( config[ key ] ?? '' );
	}

	function formatMessage( key, replacements ) {
		let template = message( key );

		for ( const [ placeholder, value ] of replacements ) {
			template = template.replace( placeholder, String( value ) );
		}

		return template;
	}

	function limit( key ) {
		return Number.parseInt( config[ key ], 10 );
	}

	const BATCH_SIZE = limit( 'batch_size' );
	const MAX_COUPON_COUNT = limit( 'max_coupon_count_value' );
	const MAX_PREFIX_LENGTH = limit( 'max_prefix_length' );
	const MIN_CODE_LENGTH = limit( 'min_code_length' );
	const MAX_CODE_LENGTH = limit( 'max_code_length' );

	/**
	 * Check that the server supplied everything the script needs.
	 *
	 * @returns {boolean} True when the configuration is usable.
	 */
	function isConfigured() {
		const limits = [ BATCH_SIZE, MAX_COUPON_COUNT, MAX_PREFIX_LENGTH, MIN_CODE_LENGTH, MAX_CODE_LENGTH ];

		return message( 'ajax_url' ) !== '' &&
			message( 'nonce' ) !== '' &&
			limits.every( ( value ) => Number.isInteger( value ) && value > 0 );
	}

	/**
	 * Announce a message to assistive technology when WordPress provides the helper.
	 *
	 * @param {string} text - Message text.
	 * @param {string} politeness - 'polite' or 'assertive'.
	 * @returns {void}
	 */
	function speak( text, politeness ) {
		globalThis.wp?.a11y?.speak?.( text, politeness );
	}

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

	/**
	 * Build a WordPress admin notice with safe text content.
	 *
	 * @param {string} className - Notice CSS classes.
	 * @param {string} text - Notice message text.
	 * @returns {HTMLElement} The notice element.
	 */
	function buildNotice( className, text ) {
		const notice = createElement( 'div', { class: className } );
		const paragraph = createElement( 'p' );

		paragraph.textContent = String( text ).slice( 0, 500 );
		notice.append( paragraph );

		return notice;
	}

	class AdminController {
		constructor() {
			const form = document.querySelector( selectors.form );

			this.elements = Object.freeze( {
				codeLength: document.querySelector( selectors.codeLength ),
				configError: document.querySelector( selectors.configError ),
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

			// Kept as a property so the same function can be added and removed.
			this.onBeforeUnload = ( event ) => this.warnBeforeUnload( event );
			this.submitButtonHadFocus = false;
		}

		/** Bootstrap all event bindings. */
		init() {
			if ( ! this.elements.form ) {
				return;
			}

			if ( ! isConfigured() ) {
				this.showConfigurationError();
				return;
			}

			this.bindEvents();
			this.initFormValidation();
		}

		/**
		 * Reveal the notice the page ships for missing settings and disable the form.
		 *
		 * @returns {void}
		 */
		showConfigurationError() {
			if ( this.elements.configError ) {
				this.elements.configError.hidden = false;
			}

			this.setSubmitButtonDisabled( true );
			this.elements.form.addEventListener( 'submit', ( event ) => event.preventDefault() );
		}

		/** Attach DOM event handlers. */
		bindEvents() {
			this.elements.form.addEventListener( 'submit', ( event ) => this.handleFormSubmission( event ) );
			this.elements.prefix?.addEventListener( 'input', () => this.formatPrefix() );
			this.elements.couponCount?.addEventListener( 'input', () => this.updateCountWarning() );
			this.elements.couponCount?.addEventListener( 'change', () => this.normalizeCouponCount() );
			this.elements.codeLength?.addEventListener( 'change', () => this.normalizeCodeLength() );
			this.elements.downloadButton?.addEventListener( 'click', () => this.downloadGeneratedCodes() );
		}

		/**
		 * Handle form submission: validate, confirm large batches, run AJAX generation.
		 *
		 * @param {SubmitEvent} event - Submit event.
		 * @returns {void}
		 */
		handleFormSubmission( event ) {
			event.preventDefault();
			this.clearNotices();

			if ( ! this.validateForm() ) {
				return;
			}

			const total = Number.parseInt( this.elements.couponCount.value, 10 );

			if ( total > CONFIRM_ABOVE ) {
				const confirmation = formatMessage( 'confirm_large_batch', [
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
			const state = {
				collectedCodes: [],
				generated: 0,
				remaining: total,
				total,
				warning: '',
			};

			this.prepareBatchGeneration();

			try {
				await this.generateCouponBatches( state );
			} catch {
				// The request may have reached the server, so coupons may exist that this page never saw.
				this.showErrorMessage( message( 'response_unreadable' ) );
			} finally {
				this.finishBatchGeneration( state );
			}
		}

		/** Prepare the form controls before batch generation begins. */
		prepareBatchGeneration() {
			this.elements.form.classList.add( 'loading' );
			globalThis.addEventListener( 'beforeunload', this.onBeforeUnload );
			// A disabled button loses keyboard focus; remember it so the run can give it back.
			this.submitButtonHadFocus = document.activeElement === this.elements.submitButton;
			this.setSubmitButtonDisabled( true );
			this.setProgressVisible( true );
			this.setResultsVisible( false );
			this.clearGeneratedCodes();
			this.updateProgress( 0 );
		}

		/**
		 * Generate all coupon batches.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @returns {Promise<void>}
		 */
		async generateCouponBatches( state ) {
			while ( state.remaining > 0 ) {
				if ( ! await this.generateCouponBatch( state ) ) {
					return;
				}
			}
		}

		/**
		 * Generate a single coupon batch and update progress state.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @returns {Promise<boolean>} True when generation can continue.
		 */
		async generateCouponBatch( state ) {
			const batchSize = Math.min( BATCH_SIZE, state.remaining );
			const response = await this.sendBatchRequest( batchSize );

			if ( response === 0 || response === -1 ) {
				this.showErrorMessage( message( 'session_expired' ) );
				return false;
			}

			if ( ! response?.success ) {
				this.showBatchFailure( response );
				return false;
			}

			const generatedInBatch = this.addBatchResult( state, response );

			if ( generatedInBatch < 1 ) {
				this.showBatchFailure( response );
				return false;
			}

			this.updateBatchProgress( state );

			return true;
		}

		/**
		 * Add a successful AJAX response to the current batch state.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @param {Object} response - AJAX response payload.
		 * @returns {number} Number of coupons generated in the batch.
		 */
		addBatchResult( state, response ) {
			const parsedGenerated = Number.parseInt( response.data?.generated ?? 0, 10 );
			const generatedInBatch = Number.isNaN( parsedGenerated ) ? 0 : parsedGenerated;

			state.generated += generatedInBatch;
			state.remaining = Math.max( 0, state.remaining - generatedInBatch );

			if ( Array.isArray( response.data?.codes ) ) {
				state.collectedCodes.push( ...response.data.codes.map( String ) );
			}

			// Every request reports the same products, so the first warning is enough.
			if ( state.warning === '' && typeof response.data?.warning === 'string' ) {
				state.warning = response.data.warning;
			}

			return generatedInBatch;
		}

		/**
		 * Refresh progress UI after a successful batch.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @returns {void}
		 */
		updateBatchProgress( state ) {
			this.updateProgress( Math.min( 100, Math.round( ( state.generated / state.total ) * 100 ) ) );

			if ( this.elements.progressText ) {
				this.elements.progressText.textContent = formatMessage( 'generating_progress', [
					[ '%1$d', state.generated ],
					[ '%2$d', state.total ],
				] );
			}
		}

		/**
		 * Display the AJAX failure response.
		 *
		 * @param {Object|null|undefined} response - AJAX response payload.
		 * @returns {void}
		 */
		showBatchFailure( response ) {
			this.showErrorMessage( response?.data?.message ?? message( 'generation_failed' ) );
		}

		/**
		 * Restore controls and reveal generated coupon results.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @returns {void}
		 */
		finishBatchGeneration( state ) {
			this.elements.form.classList.remove( 'loading' );
			globalThis.removeEventListener( 'beforeunload', this.onBeforeUnload );
			this.setSubmitButtonDisabled( false );
			this.restoreSubmitButtonFocus();

			if ( state.generated > 0 ) {
				this.showBatchResults( state );
			} else {
				this.setProgressVisible( false );
			}
		}

		/**
		 * Display completed batch generation results.
		 *
		 * @param {Object} state - Mutable batch state.
		 * @returns {void}
		 */
		showBatchResults( state ) {
			if ( state.remaining === 0 ) {
				this.updateProgress( 100 );
			}

			this.setGeneratedCodes( state.collectedCodes );
			this.setResultsVisible( true );

			if ( state.remaining === 0 ) {
				this.showSuccessMessage( formatMessage( 'generation_complete', [
					[ '%d', state.generated ],
				] ) );
			}

			if ( state.warning !== '' ) {
				this.showWarningMessage( state.warning );
			}
		}

		/**
		 * Return keyboard focus to the submit button after a run.
		 *
		 * Only when the button had focus as the run began and nothing else has
		 * taken focus since, so focus is never pulled away from where the user
		 * has moved it.
		 *
		 * @returns {void}
		 */
		restoreSubmitButtonFocus() {
			const { activeElement } = document;
			const focusWasLost = ! activeElement || activeElement === document.body || activeElement === this.elements.submitButton;

			if ( this.submitButtonHadFocus && focusWasLost ) {
				this.elements.submitButton?.focus();
			}

			this.submitButtonHadFocus = false;
		}

		/**
		 * Toggle the submit button disabled state when the button exists.
		 *
		 * @param {boolean} disabled - Whether the submit button is disabled.
		 * @returns {void}
		 */
		setSubmitButtonDisabled( disabled ) {
			if ( this.elements.submitButton ) {
				this.elements.submitButton.disabled = disabled;
			}
		}

		/**
		 * Toggle progress visibility when the progress element exists.
		 *
		 * @param {boolean} visible - Whether progress should be visible.
		 * @returns {void}
		 */
		setProgressVisible( visible ) {
			if ( this.elements.progress ) {
				this.elements.progress.hidden = ! visible;
			}
		}

		/**
		 * Toggle results visibility when the results element exists.
		 *
		 * @param {boolean} visible - Whether results should be visible.
		 * @returns {void}
		 */
		setResultsVisible( visible ) {
			if ( this.elements.results ) {
				this.elements.results.hidden = ! visible;
			}
		}

		/** Clear any previously generated coupon codes. */
		clearGeneratedCodes() {
			this.setGeneratedCodes( [] );
		}

		/**
		 * Replace the generated coupon code textarea contents.
		 *
		 * @param {string[]} codes - Coupon codes.
		 * @returns {void}
		 */
		setGeneratedCodes( codes ) {
			if ( this.elements.generatedCodes ) {
				this.elements.generatedCodes.value = codes.join( '\n' );
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

			const response = await fetch( message( 'ajax_url' ), {
				body,
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
				},
				method: 'POST',
			} );
			const payload = await response.json().catch( () => null );

			// WordPress answers 0 (logged out or unknown action) or -1 (nonce refused); nothing was created.
			if ( payload === 0 || payload === -1 ) {
				return payload;
			}

			if ( ! payload ) {
				throw new Error( `Unexpected AJAX response: ${ response.status }` );
			}

			return payload;
		}

		/** Reduce the coupon prefix to lower-case letters and digits as it is typed, as codes are stored. */
		formatPrefix() {
			const { prefix } = this.elements;

			if ( ! prefix ) {
				return;
			}

			prefix.value = String( prefix.value )
				.replace( /[^a-zA-Z0-9]/g, '' )
				.toLowerCase()
				.slice( 0, MAX_PREFIX_LENGTH );
		}

		/**
		 * Show or clear the coupon-count warning without changing what was typed.
		 *
		 * @returns {void}
		 */
		updateCountWarning() {
			const { couponCount } = this.elements;

			if ( ! couponCount ) {
				return;
			}

			document.querySelector( selectors.warning )?.remove();

			const count = Number.parseInt( couponCount.value, 10 );

			if ( Number.isNaN( count ) ) {
				return;
			}

			if ( count > MAX_COUPON_COUNT ) {
				couponCount.insertAdjacentElement(
					'afterend',
					buildWarningSpan(
						'error',
						formatMessage( 'max_coupons_warning', [
							[ '%d', MAX_COUPON_COUNT ],
						] )
					)
				);
				return;
			}

			if ( count > CAUTION_ABOVE ) {
				couponCount.insertAdjacentElement(
					'afterend',
					buildWarningSpan( 'caution', message( 'many_coupons_warning' ) )
				);
			}
		}

		/**
		 * Bring the coupon count into range once the user has finished editing it.
		 *
		 * @returns {void}
		 */
		normalizeCouponCount() {
			const { couponCount } = this.elements;

			if ( ! couponCount ) {
				return;
			}

			const count = Number.parseInt( String( couponCount.value ).replace( /\D/g, '' ), 10 );

			if ( Number.isNaN( count ) || count < 1 ) {
				couponCount.value = '1';
			} else {
				couponCount.value = String( Math.min( count, MAX_COUPON_COUNT ) );
			}

			this.updateCountWarning();
		}

		/**
		 * Bring the random code length into range once the user has finished editing it.
		 *
		 * @returns {void}
		 */
		normalizeCodeLength() {
			const { codeLength } = this.elements;

			if ( ! codeLength ) {
				return;
			}

			const length = Number.parseInt( String( codeLength.value ).replace( /\D/g, '' ), 10 );

			if ( Number.isNaN( length ) || length < MIN_CODE_LENGTH ) {
				codeLength.value = String( MIN_CODE_LENGTH );
				return;
			}

			codeLength.value = String( Math.min( length, MAX_CODE_LENGTH ) );
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
				[ () => this.validateCodeLength( errors ), this.elements.codeLength ],
			];

			for ( const [ validate, field ] of validations ) {
				this.clearInvalid( field );

				if ( ! validate() ) {
					this.markInvalid( field );
					firstInvalid ??= field;
				}
			}

			if ( errors.length > 0 ) {
				this.showErrorMessage( errors.join( '\n' ) );
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
				errors.push( message( 'select_product' ) );
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
				errors.push( message( 'invalid_coupon_count' ) );
				return false;
			}

			if ( count > MAX_COUPON_COUNT ) {
				errors.push( formatMessage( 'max_coupon_count', [
					[ '%d', MAX_COUPON_COUNT ],
				] ) );
				return false;
			}

			return true;
		}

		/**
		 * Validate random coupon-code length field.
		 *
		 * @param {string[]} errors - Collector array.
		 * @returns {boolean} True when valid.
		 */
		validateCodeLength( errors ) {
			const raw = this.elements.codeLength?.value.trim() ?? '';
			const length = Number.parseInt( raw, 10 );

			if ( ! raw || Number.isNaN( length ) || length < MIN_CODE_LENGTH || length > MAX_CODE_LENGTH ) {
				errors.push( formatMessage( 'code_length_invalid', [
					[ '%1$d', MIN_CODE_LENGTH ],
					[ '%2$d', MAX_CODE_LENGTH ],
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

			globalThis.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
		}

		/**
		 * Clear a field's invalid state when the user edits it.
		 *
		 * The state is deliberately not cleared on focus: validation moves focus
		 * to the first invalid field, and the mark has to survive that.
		 *
		 * @returns {void}
		 */
		initFormValidation() {
			const fields = [
				this.elements.couponCount,
				this.elements.codeLength,
			].filter( Boolean );

			for ( const field of fields ) {
				for ( const eventName of [ 'input', 'change' ] ) {
					field.addEventListener( eventName, () => this.clearInvalid( field ) );
				}
			}

			// The product field is a WooCommerce enhanced select, which reports changes through jQuery events only.
			if ( this.elements.products ) {
				globalThis.jQuery?.( this.elements.products ).on( 'change', () => this.clearInvalid( this.elements.products ) );
			}
		}

		/**
		 * The element that visibly represents a field.
		 *
		 * For the product field that is the select2 container WooCommerce inserts
		 * after the original, visually hidden select.
		 *
		 * @param {HTMLElement} field - Form field.
		 * @returns {Element|null} The visible container, or null when the field is shown itself.
		 */
		getEnhancedContainer( field ) {
			const next = field.nextElementSibling;

			return next?.classList.contains( 'select2-container' ) ? next : null;
		}

		/**
		 * Mark a field as invalid for sighted and assistive-technology users.
		 *
		 * @param {HTMLElement|null} field - Form field.
		 * @returns {void}
		 */
		markInvalid( field ) {
			if ( ! field ) {
				return;
			}

			field.classList.add( 'error' );
			field.setAttribute( 'aria-invalid', 'true' );
			this.getEnhancedContainer( field )?.classList.add( 'fgcbg-invalid' );
		}

		/**
		 * Remove a field's invalid state.
		 *
		 * @param {HTMLElement|null} field - Form field.
		 * @returns {void}
		 */
		clearInvalid( field ) {
			if ( ! field ) {
				return;
			}

			field.classList.remove( 'error' );
			field.removeAttribute( 'aria-invalid' );
			this.getEnhancedContainer( field )?.classList.remove( 'fgcbg-invalid' );
		}

		/** Remove every notice this script has shown. */
		clearNotices() {
			document.querySelectorAll( '.fgcbg-error-message, .fgcbg-success-message, .fgcbg-warning-message' ).forEach( ( notice ) => {
				notice.remove();
			} );
		}

		/**
		 * Display an error notice above the form. It stays until the next attempt.
		 *
		 * @param {string} text - Error text.
		 * @returns {void}
		 */
		showErrorMessage( text ) {
			this.clearNotices();
			this.insertNoticeBeforeForm( buildNotice( 'notice notice-error fgcbg-error-message', text ) );
			speak( text, 'assertive' );
		}

		/**
		 * Display a success notice above the form, replacing any earlier notice.
		 *
		 * @param {string} text - Success text.
		 * @returns {void}
		 */
		showSuccessMessage( text ) {
			this.clearNotices();
			this.insertNoticeBeforeForm( buildNotice( 'notice notice-success fgcbg-success-message', text ) );
			speak( text, 'polite' );
		}

		/**
		 * Display a warning notice below any other notice, without removing it.
		 *
		 * @param {string} text - Warning text.
		 * @returns {void}
		 */
		showWarningMessage( text ) {
			this.insertNoticeBeforeForm( buildNotice( 'notice notice-warning fgcbg-warning-message', text ) );
			speak( text, 'polite' );
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
		 * Bound only while a run is in progress.
		 *
		 * @param {BeforeUnloadEvent} event - Before unload event.
		 * @returns {string} Warning message for legacy browsers.
		 */
		warnBeforeUnload( event ) {
			const warning = message( 'generation_in_progress' );

			event.preventDefault();
			event.returnValue = warning;

			return warning;
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
