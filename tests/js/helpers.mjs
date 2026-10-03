/**
 * Helpers for the admin script tests.
 *
 * The page markup and the script settings come from the real PHP classes
 * (render-admin-page.php), and the real assets/js/admin.js runs inside jsdom.
 * Network requests, confirm(), and wp.a11y.speak() are replaced by recorders.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const renderScript = fileURLToPath( new URL( './render-admin-page.php', import.meta.url ) );

/** Markup and settings rendered by PHP. */
export const page = JSON.parse( execFileSync( 'php', [ renderScript ], { encoding: 'utf8' } ) );

/** The script under test. */
export const source = readFileSync( new URL( '../../assets/js/admin.js', import.meta.url ), 'utf8' );

/**
 * A successful batch response.
 *
 * @param {number} count - Coupons in the batch.
 * @param {number} first - Number of the first code.
 * @returns {Object} Response payload.
 */
export function okResponse( count, first = 0 ) {
	return {
		success: true,
		data: {
			generated: count,
			codes: Array.from( { length: count }, ( _, index ) => `code${ first + index }` ),
		},
	};
}

/**
 * Replace the placeholders of a configured message the way the script does.
 *
 * @param {string} key - Configuration key.
 * @param {Array<Array>} replacements - Placeholder and value pairs.
 * @returns {string} Formatted message.
 */
export function formatted( key, replacements ) {
	let text = page.config[ key ];

	for ( const [ placeholder, value ] of replacements ) {
		text = text.replace( placeholder, String( value ) );
	}

	return text;
}

/**
 * Load the generator screen and run the admin script in it.
 *
 * @param {Object} options - Options.
 * @param {Object|null} options.config - Script settings; null leaves them out.
 * @param {Array} options.responses - Queued responses: a payload object, a string body, an Error to throw, or a function returning a promise.
 * @param {boolean} options.confirmResult - What confirm() answers.
 * @returns {Promise<Object>} The window, its document, and the recorders.
 */
export async function loadPage( { config = page.config, responses = [], confirmResult = true } = {} ) {
	const dom = new JSDOM( `<!DOCTYPE html><html lang="en"><head><title>Generator</title></head><body>${ page.html }</body></html>`, {
		runScripts: 'outside-only',
		url: 'https://example.test/wp-admin/admin.php?page=free-gift-bulk-coupon-generator',
	} );
	const { window } = dom;
	const requests = [];
	const spoken = [];
	const confirmations = [];
	const downloads = [];

	window.Element.prototype.scrollIntoView = () => {};
	window.HTMLAnchorElement.prototype.click = function () {
		downloads.push( { download: this.getAttribute( 'download' ), href: this.getAttribute( 'href' ) } );
	};
	window.URL.createObjectURL = ( blob ) => {
		downloads.push( { blobType: blob.type, blobSize: blob.size } );
		return 'blob:https://example.test/codes';
	};
	window.URL.revokeObjectURL = () => {};
	window.confirm = ( text ) => {
		confirmations.push( text );
		return confirmResult;
	};
	window.wp = { a11y: { speak: ( text, politeness ) => spoken.push( [ politeness, text ] ) } };
	window.fetch = async ( url, init ) => {
		requests.push( {
			url,
			method: init.method,
			body: Object.fromEntries( init.body.entries() ),
			products: init.body.getAll( 'product_ids[]' ),
		} );

		let next = responses.shift();

		if ( typeof next === 'function' ) {
			next = await next();
		}

		if ( next instanceof Error ) {
			throw next;
		}

		return {
			status: 200,
			json: async () => ( typeof next === 'string' ? JSON.parse( next ) : next ),
		};
	};

	if ( config !== null ) {
		window.fgcbgAdminConfig = config;
	}

	await new Promise( ( resolve ) => {
		if ( window.document.readyState === 'complete' ) {
			resolve();
		} else {
			window.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	window.eval( source );

	const document = window.document;

	return {
		window,
		document,
		requests,
		spoken,
		confirmations,
		downloads,
		form: document.querySelector( '.fgcbg-form' ),
		submit: document.querySelector( '.fgcbg-form .button-primary' ),
		products: document.querySelector( '#fgcbg_product_ids' ),
		count: document.querySelector( '#number_of_coupons' ),
		prefix: document.querySelector( '#coupon_prefix' ),
		codeLength: document.querySelector( '#coupon_code_length' ),
		close: () => window.close(),
	};
}

/**
 * Select products in the product field.
 *
 * @param {Object} view - Value returned by loadPage().
 * @param {Array<string>} ids - Product IDs.
 * @returns {void}
 */
export function selectProducts( view, ids ) {
	for ( const id of ids ) {
		view.products.append( new view.window.Option( `Product ${ id }`, id, true, true ) );
	}
}

/**
 * Set a field's value and fire the given events, as typing or leaving the field would.
 *
 * @param {Object} view - Value returned by loadPage().
 * @param {HTMLElement} field - Field.
 * @param {string} value - New value.
 * @param {Array<string>} events - Event names.
 * @returns {void}
 */
export function setField( view, field, value, events = [ 'input' ] ) {
	field.value = value;

	for ( const name of events ) {
		field.dispatchEvent( new view.window.Event( name, { bubbles: true } ) );
	}
}

/**
 * Submit the form the way a click on the submit button would.
 *
 * @param {Object} view - Value returned by loadPage().
 * @returns {Event} The submit event.
 */
export function submitForm( view ) {
	const event = new view.window.Event( 'submit', { bubbles: true, cancelable: true } );

	view.form.dispatchEvent( event );

	return event;
}

/**
 * Wait until a condition holds, or fail after a time limit.
 *
 * @param {Function} condition - Returns true when done.
 * @param {number} timeout - Milliseconds.
 * @returns {Promise<void>}
 */
export async function waitFor( condition, timeout = 2000 ) {
	const started = Date.now();

	while ( ! condition() ) {
		if ( Date.now() - started > timeout ) {
			throw new Error( 'Timed out waiting for the admin script.' );
		}

		await new Promise( ( resolve ) => setTimeout( resolve, 5 ) );
	}
}

/**
 * Wait until a generation run has finished.
 *
 * @param {Object} view - Value returned by loadPage().
 * @returns {Promise<void>}
 */
export function waitForRun( view ) {
	return waitFor( () => ! view.form.classList.contains( 'loading' ) && ! view.submit.disabled );
}

/**
 * Texts of the notices the script has shown.
 *
 * @param {Object} view - Value returned by loadPage().
 * @param {string} type - 'error' or 'success'.
 * @returns {Array<string>} Notice texts.
 */
export function notices( view, type ) {
	return Array.from( view.document.querySelectorAll( `.fgcbg-${ type }-message` ), ( notice ) => notice.textContent );
}
