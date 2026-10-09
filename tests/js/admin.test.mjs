/**
 * Tests for assets/js/admin.js.
 *
 * Run with `npm test`. See helpers.mjs for how the page is built.
 */
import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import {
	enhanceProducts,
	formatted,
	loadPage,
	notices,
	okResponse,
	page,
	selectProducts,
	setField,
	source,
	submitForm,
	triggerJQuery,
	waitFor,
	waitForRun,
} from './helpers.mjs';

const { config } = page;
const MAX_COUNT = Number( config.max_coupon_count_value );
const MIN_LENGTH = Number( config.min_code_length );
const MAX_LENGTH = Number( config.max_code_length );

let view = null;

afterEach( () => {
	view?.close();
	view = null;
} );

test( 'every setting the script reads is provided by PHP, and every provided setting is read', () => {
	const read = new Set(
		Array.from( source.matchAll( /\b(?:message|formatMessage|limit)\(\s*'([a-z_]+)'/g ), ( match ) => match[ 1 ] )
	);

	assert.ok( read.size > 10, 'The pattern found the settings the script reads.' );
	assert.deepEqual( [ ...read ].filter( ( key ) => ! ( key in config ) ), [], 'Read but not provided.' );
	assert.deepEqual( Object.keys( config ).filter( ( key ) => ! read.has( key ) ), [], 'Provided but not read.' );
} );

test( 'every selector the script uses exists in the rendered page', async () => {
	view = await loadPage();
	const block = source.match( /const selectors = Object\.freeze\( \{([\s\S]*?)\} \);/ )[ 1 ];
	const selectors = Array.from( block.matchAll( /\x27([.#][^\x27]+)\x27/g ), ( match ) => match[ 1 ] );

	assert.ok( selectors.length >= 13 );

	for ( const selector of selectors ) {
		if ( selector === '#coupon-count-warning' ) {
			continue; // Created by the script.
		}

		assert.equal( view.document.querySelectorAll( selector ).length, 1, selector );
	}
} );

test( 'with settings, the form is usable and the settings notice stays hidden', async () => {
	view = await loadPage();

	assert.equal( view.document.querySelector( '#fgcbg-config-error' ).hidden, true );
	assert.equal( view.submit.disabled, false );
	assert.equal( view.codeLength.value, '12' );
} );

test( 'without settings, the page shows its notice and disables the form', async () => {
	view = await loadPage( { config: null } );

	assert.equal( view.document.querySelector( '#fgcbg-config-error' ).hidden, false );
	assert.equal( view.submit.disabled, true );

	selectProducts( view, [ '123' ] );
	const event = submitForm( view );

	assert.equal( event.defaultPrevented, true, 'The form must not submit natively.' );
	assert.equal( view.requests.length, 0 );
} );

test( 'an empty nonce counts as missing settings', async () => {
	view = await loadPage( { config: { ...config, nonce: '' } } );

	assert.equal( view.document.querySelector( '#fgcbg-config-error' ).hidden, false );
	assert.equal( view.submit.disabled, true );
} );

test( 'typing a two-digit code length is not rewritten while typing', async () => {
	view = await loadPage();

	for ( const wanted of [ '12', '9', '16', '20' ] ) {
		view.codeLength.value = '';

		for ( const character of wanted ) {
			setField( view, view.codeLength, view.codeLength.value + character );
			assert.equal( view.codeLength.value, wanted.slice( 0, view.codeLength.value.length ) );
		}

		setField( view, view.codeLength, view.codeLength.value, [ 'change' ] );
		assert.equal( view.codeLength.value, wanted );
	}
} );

test( 'leaving the code length field brings it into range', async () => {
	view = await loadPage();

	for ( const [ typed, expected ] of [ [ '3', MIN_LENGTH ], [ '99', MAX_LENGTH ], [ '', MIN_LENGTH ] ] ) {
		setField( view, view.codeLength, typed, [ 'change' ] );
		assert.equal( view.codeLength.value, String( expected ), `typed "${ typed }"` );
	}
} );

test( 'the coupon count shows warnings without changing the value, and is clamped when left', async () => {
	view = await loadPage();
	function warning() {
		return view.document.querySelector( '#coupon-count-warning' );
	}

	setField( view, view.count, '' );
	assert.equal( view.count.value, '' );
	assert.equal( warning(), null );

	setField( view, view.count, '75' );
	assert.equal( view.count.value, '75' );
	assert.equal( warning().textContent, config.many_coupons_warning );
	assert.ok( warning().classList.contains( 'is-caution' ) );

	setField( view, view.count, String( MAX_COUNT + 400 ) );
	assert.equal( view.count.value, String( MAX_COUNT + 400 ) );
	assert.equal( warning().textContent, formatted( 'max_coupons_warning', [ [ '%d', MAX_COUNT ] ] ) );
	assert.ok( warning().classList.contains( 'is-error' ) );
	assert.equal( view.document.querySelectorAll( '#coupon-count-warning' ).length, 1 );

	setField( view, view.count, view.count.value, [ 'change' ] );
	assert.equal( view.count.value, String( MAX_COUNT ) );
} );

test( 'the prefix is reduced to letters and digits as it is typed', async () => {
	view = await loadPage();

	setField( view, view.prefix, 'Gi-FT 2026!' );

	assert.equal( view.prefix.value, 'gift2026' );
} );

test( 'a missing product is reported, marked, focused, announced, and kept on screen', async () => {
	view = await loadPage();

	submitForm( view );

	assert.deepEqual( notices( view, 'error' ), [ config.select_product ] );
	assert.equal( view.products.getAttribute( 'aria-invalid' ), 'true' );
	assert.equal( view.document.activeElement, view.products );
	assert.deepEqual( view.spoken, [ [ 'assertive', config.select_product ] ] );
	assert.equal( view.requests.length, 0 );

	await new Promise( ( resolve ) => setTimeout( resolve, 50 ) );
	assert.deepEqual( notices( view, 'error' ), [ config.select_product ], 'Error notices are not removed on a timer.' );
} );

test( 'a second attempt replaces the first error and moves the mark', async () => {
	view = await loadPage();

	submitForm( view );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '0' );
	submitForm( view );

	assert.deepEqual( notices( view, 'error' ), [ config.invalid_coupon_count ] );
	assert.equal( view.products.hasAttribute( 'aria-invalid' ), false );
	assert.equal( view.count.getAttribute( 'aria-invalid' ), 'true' );

	setField( view, view.count, '5' );
	assert.equal( view.count.hasAttribute( 'aria-invalid' ), false, 'Editing the field clears its mark.' );
} );

test( 'a run of 25 is sent as requests of 10, 10, and 5 and reports success once', async () => {
	let releaseFirst;
	function firstResponse() {
		return new Promise( ( resolve ) => {
			releaseFirst = () => resolve( okResponse( 10, 0 ) );
		} );
	}

	view = await loadPage( { responses: [ firstResponse, okResponse( 10, 10 ), okResponse( 5, 20 ) ] } );
	selectProducts( view, [ '123', '456' ] );
	setField( view, view.count, '25' );
	setField( view, view.prefix, 'gift' );
	submitForm( view );

	// While the first request is pending, leaving the page asks for confirmation.
	await waitFor( () => typeof releaseFirst === 'function' );
	const during = new view.window.Event( 'beforeunload', { cancelable: true } );
	view.window.dispatchEvent( during );
	assert.equal( during.defaultPrevented, true );
	assert.equal( view.submit.disabled, true );

	releaseFirst();
	await waitForRun( view );

	assert.deepEqual( view.confirmations, [], 'No confirmation at 25 or fewer.' );
	assert.deepEqual( view.requests.map( ( request ) => request.body.batch_size ), [ '10', '10', '5' ] );

	const [ first ] = view.requests;
	assert.equal( first.url, config.ajax_url );
	assert.equal( first.method, 'POST' );
	assert.equal( first.body.action, 'fgcbg_generate_batch' );
	assert.equal( first.body.nonce, config.nonce );
	assert.equal( first.body.coupon_prefix, 'gift' );
	assert.equal( first.body.coupon_code_length, '12' );
	assert.deepEqual( first.products, [ '123', '456' ] );

	assert.equal( view.document.querySelector( '#fgcbg-generated-codes' ).value.split( '\n' ).length, 25 );
	assert.equal( view.document.querySelector( '#fgcbg-results' ).hidden, false );
	assert.equal( view.document.querySelector( '#fgcbg-progress-bar' ).getAttribute( 'aria-valuenow' ), '100' );
	assert.equal( view.document.querySelector( '#fgcbg-progress-text' ).textContent, formatted( 'generating_progress', [ [ '%1$d', 25 ], [ '%2$d', 25 ] ] ) );

	const success = formatted( 'generation_complete', [ [ '%d', 25 ] ] );
	assert.deepEqual( notices( view, 'success' ), [ success ] );
	assert.deepEqual( view.spoken.at( -1 ), [ 'polite', success ] );

	const after = new view.window.Event( 'beforeunload', { cancelable: true } );
	view.window.dispatchEvent( after );
	assert.equal( after.defaultPrevented, false, 'The leave warning is removed after the run.' );
} );

test( 'a warning from the server is shown once, after the success notice, and cleared by the next attempt', async () => {
	const warning = 'These gift products are not purchasable and cannot be gifted until they are published, in stock, and have a price: Unreleased Mug.';
	function withWarning( count, first ) {
		const response = okResponse( count, first );
		response.data.warning = warning;
		return response;
	}

	view = await loadPage( { responses: [ withWarning( 10, 0 ), withWarning( 5, 10 ) ] } );
	selectProducts( view, [ '123', '600' ] );
	setField( view, view.count, '15' );
	submitForm( view );
	await waitForRun( view );

	const success = formatted( 'generation_complete', [ [ '%d', 15 ] ] );
	const shown = Array.from( view.document.querySelectorAll( '.fgcbg-success-message, .fgcbg-warning-message' ), ( notice ) => notice.textContent );

	assert.deepEqual( shown, [ success, warning ] );
	assert.ok( view.document.querySelector( '.fgcbg-warning-message' ).classList.contains( 'notice-warning' ) );
	assert.deepEqual( view.spoken.slice( -2 ), [ [ 'polite', success ], [ 'polite', warning ] ] );

	setField( view, view.count, '0' );
	submitForm( view );
	assert.deepEqual( notices( view, 'warning' ), [] );
} );

test( 'keyboard focus returns to the submit button after a run, unless the user moved it', async () => {
	let release;
	function pending() {
		return new Promise( ( resolve ) => {
			release = () => resolve( okResponse( 1, 0 ) );
		} );
	}

	// Focus starts on the button. A browser drops focus from a button that becomes
	// disabled; jsdom does not, so the test moves focus to the body itself.
	view = await loadPage( { responses: [ pending ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	view.submit.focus();
	submitForm( view );
	await waitFor( () => typeof release === 'function' );
	view.prefix.focus();
	view.prefix.blur();
	assert.equal( view.document.activeElement, view.document.body );
	release();
	await waitForRun( view );
	assert.equal( view.document.activeElement, view.submit );

	// The user moved to another field during the run: focus stays there.
	release = undefined;
	view = await loadPage( { responses: [ pending ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	view.submit.focus();
	submitForm( view );
	await waitFor( () => typeof release === 'function' );
	view.prefix.focus();
	release();
	await waitForRun( view );
	assert.equal( view.document.activeElement, view.prefix );

	// The run began from a field, not from the button: focus is not moved.
	release = undefined;
	view = await loadPage( { responses: [ pending ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	view.count.focus();
	submitForm( view );
	await waitFor( () => typeof release === 'function' );
	release();
	await waitForRun( view );
	assert.equal( view.document.activeElement, view.count );
} );

test( 'more than 25 coupons asks first, and nothing is sent when declined', async () => {
	view = await loadPage( { confirmResult: false } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '30' );
	submitForm( view );

	assert.deepEqual( view.confirmations, [ formatted( 'confirm_large_batch', [ [ '%d', 30 ] ] ) ] );
	assert.equal( view.requests.length, 0 );
	assert.equal( view.submit.disabled, false );
} );

test( 'a server message is shown as text, never as markup', async () => {
	const markup = '<img src=x onerror="window.injected = true">';

	view = await loadPage( { responses: [ { success: false, data: { message: markup } } ] } );
	selectProducts( view, [ '123' ] );
	submitForm( view );
	await waitForRun( view );

	assert.deepEqual( notices( view, 'error' ), [ markup ] );
	assert.equal( view.document.querySelector( '.fgcbg-error-message img' ), null );
	assert.equal( view.window.injected, undefined );
	assert.equal( view.document.querySelector( '#fgcbg-progress' ).hidden, true );
} );

test( 'a refused nonce (-1) or an ended session (0) asks for a reload', async () => {
	for ( const body of [ '-1', '0' ] ) {
		view?.close();
		view = await loadPage( { responses: [ body, okResponse( 10 ) ] } );
		selectProducts( view, [ '123' ] );
		submitForm( view );
		await waitForRun( view );

		assert.deepEqual( notices( view, 'error' ), [ config.session_expired ], `body ${ body }` );
		assert.equal( view.requests.length, 1, 'No further request is sent.' );
	}
} );

test( 'an unreadable response says coupons may exist, keeps earlier codes, and stops', async () => {
	view = await loadPage( { responses: [ okResponse( 10 ), '<br />Notice: something{"success":true}' ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '20' );
	submitForm( view );
	await waitForRun( view );

	assert.equal( view.requests.length, 2 );
	assert.deepEqual( notices( view, 'error' ), [ config.response_unreadable ] );
	assert.deepEqual( notices( view, 'success' ), [] );
	assert.equal( view.document.querySelector( '#fgcbg-generated-codes' ).value.split( '\n' ).length, 10 );
	assert.equal( view.document.querySelector( '#fgcbg-results' ).hidden, false );
} );

test( 'a network failure gets the same cautious message', async () => {
	view = await loadPage( { responses: [ new Error( 'Failed to fetch' ) ] } );
	selectProducts( view, [ '123' ] );
	submitForm( view );
	await waitForRun( view );

	assert.deepEqual( notices( view, 'error' ), [ config.response_unreadable ] );
	assert.equal( view.submit.disabled, false );
} );

test( 'a success response that reports no coupons ends the run as a failure', async () => {
	view = await loadPage( { responses: [ okResponse( 0 ), okResponse( 10 ) ] } );
	selectProducts( view, [ '123' ] );
	submitForm( view );
	await waitForRun( view );

	assert.equal( view.requests.length, 1 );
	assert.deepEqual( notices( view, 'error' ), [ config.generation_failed ] );
} );

test( 'the generated codes can be downloaded as a text file', async () => {
	view = await loadPage( { responses: [ okResponse( 2 ) ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '2' );
	submitForm( view );
	await waitForRun( view );

	view.document.querySelector( '#fgcbg-download-codes' ).click();

	assert.deepEqual( view.downloads, [
		{ blobType: 'text/plain;charset=utf-8', blobSize: 'code0\ncode1\n'.length },
		{ download: 'free-gift-coupon-codes.txt', href: 'blob:https://example.test/codes' },
	] );
} );

// ---------------------------------------------------------------- what PHP prints for the script and for the browser

test( 'the page ships hidden states, roles, and control types as the script expects them', async () => {
	view = await loadPage();
	const { document } = view;
	const bar = document.querySelector( '#fgcbg-progress-bar' );

	assert.equal( document.querySelector( '#fgcbg-progress' ).hidden, true, 'Progress is hidden before a run.' );
	assert.equal( document.querySelector( '#fgcbg-results' ).hidden, true, 'Results are hidden before a run.' );
	assert.equal( document.querySelector( '#fgcbg-config-error' ).getAttribute( 'role' ), 'alert' );
	assert.equal( bar.getAttribute( 'role' ), 'progressbar' );
	assert.equal( bar.getAttribute( 'aria-valuemin' ), '0' );
	assert.equal( bar.getAttribute( 'aria-valuemax' ), '100' );
	assert.notEqual( bar.getAttribute( 'aria-label' ) ?? '', '' );
	assert.equal( document.querySelector( '#fgcbg-generated-codes' ).readOnly, true );
	assert.equal( document.querySelector( '#fgcbg-download-codes' ).type, 'button', 'A click on it must not submit the form.' );
	assert.equal( view.submit.type, 'submit' );
	assert.equal( document.querySelector( '.fgcbg-warning-icon' ).getAttribute( 'aria-hidden' ), 'true' );
} );

test( 'every field has a label, and every label and description reference resolves', async () => {
	view = await loadPage();
	const { document } = view;
	const fields = Array.from( document.querySelectorAll( '.wrap select, .wrap input, .wrap textarea' ) );

	assert.equal( fields.length, 5 );

	for ( const field of fields ) {
		const labelled = document.querySelectorAll( `label[for="${ field.id }"]` ).length === 1 || field.hasAttribute( 'aria-labelledby' );

		assert.equal( labelled, true, `#${ field.id } has no label.` );
	}

	for ( const label of document.querySelectorAll( '.wrap label[for]' ) ) {
		assert.ok( fields.includes( document.getElementById( label.htmlFor ) ), `label for "${ label.htmlFor }"` );
	}

	const references = Array.from( document.querySelectorAll( '.wrap [aria-describedby], .wrap [aria-labelledby]' ) )
		.flatMap( ( element ) => `${ element.getAttribute( 'aria-describedby' ) ?? '' } ${ element.getAttribute( 'aria-labelledby' ) ?? '' }`.split( /\s+/ ) )
		.filter( Boolean );

	assert.ok( references.length >= 5 );

	for ( const id of references ) {
		assert.notEqual( document.getElementById( id ), null, `Referenced element "${ id }" is missing.` );
	}
} );

test( 'the product field is a WooCommerce product search for several products and their variations', async () => {
	view = await loadPage();

	assert.ok( view.products.classList.contains( 'wc-product-search' ), 'WooCommerce enhances fields with this class.' );
	assert.equal( view.products.multiple, true );
	assert.equal( view.products.name, 'product_ids[]' );
	assert.equal( view.products.dataset.action, 'woocommerce_json_search_products_and_variations' );
} );

test( 'the fields carry the limits the script was given', async () => {
	view = await loadPage();

	assert.equal( view.count.min, '1' );
	assert.equal( view.count.max, config.max_coupon_count_value );
	assert.equal( view.count.required, true );
	assert.equal( String( view.prefix.maxLength ), config.max_prefix_length );
	assert.equal( view.codeLength.min, config.min_code_length );
	assert.equal( view.codeLength.max, config.max_code_length );
	assert.equal( view.codeLength.required, true );
} );

// ---------------------------------------------------------------- settings

test( 'settings without a request URL or with a limit that is not a positive whole number count as missing', async () => {
	const broken = [
		{ ajax_url: '' },
		{ batch_size: '0' },
		{ max_coupon_count_value: '' },
		{ max_prefix_length: 'eight' },
		{ min_code_length: '-8' },
		{ max_code_length: undefined },
	];

	for ( const change of broken ) {
		view?.close();
		view = await loadPage( { config: { ...config, ...change } } );

		assert.equal( view.document.querySelector( '#fgcbg-config-error' ).hidden, false, JSON.stringify( change ) );
		assert.equal( view.submit.disabled, true, JSON.stringify( change ) );
	}
} );

// ---------------------------------------------------------------- fields

test( 'the prefix field holds no more characters than the server allows', async () => {
	view = await loadPage();

	setField( view, view.prefix, 'abcdefghijklmnop' );

	assert.equal( view.prefix.value, 'abcdefghijklmnop'.slice( 0, Number( config.max_prefix_length ) ) );
} );

test( 'the caution starts above 50 coupons, and the error above the maximum', async () => {
	view = await loadPage();
	function warningClass() {
		return view.document.querySelector( '#coupon-count-warning' )?.className ?? 'none';
	}

	setField( view, view.count, '50' );
	assert.equal( warningClass(), 'none' );

	setField( view, view.count, '51' );
	assert.match( warningClass(), /is-caution/ );

	setField( view, view.count, String( MAX_COUNT ) );
	assert.match( warningClass(), /is-caution/, 'The maximum itself is allowed.' );

	setField( view, view.count, String( MAX_COUNT + 1 ) );
	assert.match( warningClass(), /is-error/ );
	assert.doesNotMatch( warningClass(), /is-caution/ );
} );

test( 'a count that is empty, zero, or not a number becomes 1 when the field is left', async () => {
	view = await loadPage();

	for ( const typed of [ '', '0', 'abc' ] ) {
		setField( view, view.count, typed, [ 'change' ] );
		assert.equal( view.count.value, '1', `typed "${ typed }"` );
	}
} );

test( 'the count warning follows the count once the field has been corrected', async () => {
	view = await loadPage();

	setField( view, view.count, String( MAX_COUNT + 400 ) );
	assert.match( view.document.querySelector( '#coupon-count-warning' ).className, /is-error/ );

	setField( view, view.count, view.count.value, [ 'change' ] );
	assert.equal( view.count.value, String( MAX_COUNT ) );
	assert.match( view.document.querySelector( '#coupon-count-warning' ).className, /is-caution/, 'The corrected count is allowed.' );
	assert.equal( view.document.querySelectorAll( '#coupon-count-warning' ).length, 1 );
} );

test( 'the count warning shows its text as text, never as markup', async () => {
	const markup = '<img src=x onerror="window.injected = true">';

	view = await loadPage( { config: { ...config, many_coupons_warning: markup, max_coupons_warning: `${ markup } %d` } } );

	for ( const typed of [ '75', String( MAX_COUNT + 1 ) ] ) {
		setField( view, view.count, typed );

		const warning = view.document.querySelector( '#coupon-count-warning' );
		assert.ok( warning.textContent.startsWith( markup ), `typed "${ typed }"` );
		assert.equal( warning.children.length, 0 );
	}

	assert.equal( view.window.injected, undefined );
} );

// ---------------------------------------------------------------- validation on submit

test( 'a count or a code length outside its range is refused on submit', async () => {
	const lengthError = formatted( 'code_length_invalid', [ [ '%1$d', MIN_LENGTH ], [ '%2$d', MAX_LENGTH ] ] );
	const cases = [
		[ String( MAX_COUNT + 1 ), '12', formatted( 'max_coupon_count', [ [ '%d', MAX_COUNT ] ] ), 'count' ],
		[ '5', String( MAX_LENGTH + 1 ), lengthError, 'codeLength' ],
		[ '5', String( MIN_LENGTH - 1 ), lengthError, 'codeLength' ],
		[ '5', '', lengthError, 'codeLength' ],
	];

	for ( const [ count, length, message, invalid ] of cases ) {
		view?.close();
		view = await loadPage();
		selectProducts( view, [ '123' ] );
		// Typed without leaving the field, so nothing has corrected the value yet.
		setField( view, view.count, count );
		setField( view, view.codeLength, length );
		submitForm( view );

		assert.deepEqual( notices( view, 'error' ), [ message ], `count "${ count }", length "${ length }"` );
		assert.equal( view[ invalid ].getAttribute( 'aria-invalid' ), 'true' );
		assert.equal( view.requests.length, 0 );
	}
} );

test( 'several invalid fields are reported together, all are marked, and the first gets focus', async () => {
	view = await loadPage();
	setField( view, view.count, '0' );
	setField( view, view.codeLength, '3' );
	submitForm( view );

	assert.deepEqual( notices( view, 'error' ), [ [
		config.select_product,
		config.invalid_coupon_count,
		formatted( 'code_length_invalid', [ [ '%1$d', MIN_LENGTH ], [ '%2$d', MAX_LENGTH ] ] ),
	].join( '\n' ) ] );

	for ( const field of [ view.products, view.count, view.codeLength ] ) {
		assert.equal( field.getAttribute( 'aria-invalid' ), 'true', field.id );
		assert.ok( field.classList.contains( 'error' ), field.id );
	}

	assert.equal( view.document.activeElement, view.products, 'Focus goes to the first invalid field, not the last.' );
} );

test( 'the visible product control is marked, and choosing a product clears the mark', async () => {
	view = await loadPage();
	const container = enhanceProducts( view );

	submitForm( view );
	assert.ok( container.classList.contains( 'fgcbg-invalid' ) );

	selectProducts( view, [ '123' ] );
	assert.equal( triggerJQuery( view, view.products, 'change' ), 1, 'The script listens for the jQuery change event.' );
	assert.equal( container.classList.contains( 'fgcbg-invalid' ), false );
	assert.equal( view.products.hasAttribute( 'aria-invalid' ), false );
	assert.equal( view.products.classList.contains( 'error' ), false );
} );

test( 'an option without a value is not a product', async () => {
	view = await loadPage();
	view.products.append( new view.window.Option( 'Choose a product', '', true, true ) );
	submitForm( view );

	assert.deepEqual( notices( view, 'error' ), [ config.select_product ] );
	assert.equal( view.requests.length, 0 );
} );

// ---------------------------------------------------------------- a run

test( 'a valid form is submitted by the script, with the session and a JSON answer asked for', async () => {
	view = await loadPage( { responses: [ okResponse( 1 ) ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	const event = submitForm( view );
	await waitForRun( view );

	assert.equal( event.defaultPrevented, true, 'The browser must not submit the form itself.' );
	assert.equal( view.requests.length, 1 );
	assert.equal( view.requests[ 0 ].url, config.ajax_url );
	assert.equal( view.requests[ 0 ].credentials, 'same-origin' );
	assert.equal( view.requests[ 0 ].accept, 'application/json' );
} );

test( 'while a run is under way the form is busy, progress shows, and the earlier result is put away', async () => {
	let release;
	function pending() {
		return new Promise( ( resolve ) => {
			release = () => resolve( okResponse( 1, 5 ) );
		} );
	}

	view = await loadPage( { responses: [ okResponse( 2 ), pending ] } );
	const { document } = view;
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '2' );
	submitForm( view );
	await waitForRun( view );
	assert.equal( document.querySelector( '#fgcbg-generated-codes' ).value, 'code0\ncode1' );

	setField( view, view.count, '1' );
	submitForm( view );
	await waitFor( () => typeof release === 'function' );

	assert.ok( view.form.classList.contains( 'loading' ), 'The form is marked busy.' );
	assert.equal( view.submit.disabled, true );
	assert.equal( document.querySelector( '#fgcbg-progress' ).hidden, false );
	assert.equal( document.querySelector( '#fgcbg-progress-bar' ).getAttribute( 'aria-valuenow' ), '0' );
	assert.equal( document.querySelector( '#fgcbg-results' ).hidden, true, 'The earlier result is hidden.' );
	assert.equal( document.querySelector( '#fgcbg-generated-codes' ).value, '', 'The earlier codes are cleared.' );
	assert.deepEqual( notices( view, 'success' ), [], 'The earlier success notice is gone.' );

	release();
	await waitForRun( view );

	assert.equal( view.form.classList.contains( 'loading' ), false );
	assert.equal( document.querySelector( '#fgcbg-results' ).hidden, false );
	assert.equal( document.querySelector( '#fgcbg-generated-codes' ).value, 'code5' );
} );

test( 'progress follows the share of coupons created, in the bar and in words', async () => {
	let release;
	function pending() {
		return new Promise( ( resolve ) => {
			release = () => resolve( okResponse( 10, 10 ) );
		} );
	}

	view = await loadPage( { responses: [ okResponse( 10, 0 ), pending, okResponse( 5, 20 ) ] } );
	const bar = view.document.querySelector( '#fgcbg-progress-bar' );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '25' );
	submitForm( view );
	await waitFor( () => typeof release === 'function' );

	assert.equal( bar.getAttribute( 'aria-valuenow' ), '40' );
	assert.equal( bar.style.width, '40%' );
	assert.equal(
		view.document.querySelector( '#fgcbg-progress-text' ).textContent,
		formatted( 'generating_progress', [ [ '%1$d', 10 ], [ '%2$d', 25 ] ] )
	);

	release();
	await waitForRun( view );

	assert.equal( bar.getAttribute( 'aria-valuenow' ), '100' );
	assert.equal( bar.style.width, '100%' );
} );

test( 'a failure response is a failure even when it carries coupons', async () => {
	view = await loadPage( { responses: [ { success: false, data: { generated: 5, codes: [ 'a', 'b', 'c', 'd', 'e' ], message: 'Refused.' } }, okResponse( 5 ) ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '10' );
	submitForm( view );
	await waitForRun( view );

	assert.deepEqual( notices( view, 'error' ), [ 'Refused.' ] );
	assert.equal( view.requests.length, 1 );
	assert.equal( view.document.querySelector( '#fgcbg-results' ).hidden, true );
	assert.equal( view.document.querySelector( '#fgcbg-generated-codes' ).value, '' );
} );

test( 'a count that is not a number ends the run as a failure', async () => {
	view = await loadPage( { responses: [ { success: true, data: { generated: 'several', codes: [ 'a' ] } }, okResponse( 5 ) ] } );
	selectProducts( view, [ '123' ] );
	submitForm( view );
	await waitForRun( view );

	assert.deepEqual( notices( view, 'error' ), [ config.generation_failed ] );
	assert.equal( view.requests.length, 1 );
	assert.equal( view.document.querySelector( '#fgcbg-results' ).hidden, true );
} );

test( 'a long server message is cut to 500 characters', async () => {
	view = await loadPage( { responses: [ { success: false, data: { message: 'x'.repeat( 620 ) } } ] } );
	selectProducts( view, [ '123' ] );
	submitForm( view );
	await waitForRun( view );

	assert.equal( notices( view, 'error' )[ 0 ].length, 500 );
} );

test( 'a warning that is not text is ignored', async () => {
	const response = okResponse( 1 );
	response.data.warning = { text: 'Not a string.' };

	view = await loadPage( { responses: [ response ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	submitForm( view );
	await waitForRun( view );

	assert.deepEqual( notices( view, 'success' ), [ formatted( 'generation_complete', [ [ '%d', 1 ] ] ) ] );
	assert.deepEqual( notices( view, 'warning' ), [] );
} );

test( 'a run that began in a field does not move focus to the button afterwards', async () => {
	let release;
	function pending() {
		return new Promise( ( resolve ) => {
			release = () => resolve( okResponse( 1 ) );
		} );
	}

	view = await loadPage( { responses: [ pending ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	view.count.focus();
	submitForm( view );
	await waitFor( () => typeof release === 'function' );
	// Focus is lost for a reason of the user's own, for example a click on the page background.
	view.count.blur();
	assert.equal( view.document.activeElement, view.document.body );
	release();
	await waitForRun( view );

	assert.equal( view.document.activeElement, view.document.body );
} );

test( 'nothing is downloaded while there are no codes', async () => {
	view = await loadPage();

	view.document.querySelector( '#fgcbg-download-codes' ).click();

	assert.deepEqual( view.downloads, [] );
} );

// ---------------------------------------------------------------- notices

test( 'each attempt starts without the notices of the one before', async () => {
	const withWarning = okResponse( 1 );
	withWarning.data.warning = 'One gift cannot be bought.';

	view = await loadPage( { responses: [ withWarning, okResponse( 1, 5 ) ] } );
	selectProducts( view, [ '123' ] );
	setField( view, view.count, '1' );
	submitForm( view );
	await waitForRun( view );
	assert.equal( notices( view, 'success' ).length, 1 );
	assert.equal( notices( view, 'warning' ).length, 1 );

	// A failed attempt replaces the success notice and the warning.
	setField( view, view.count, '0' );
	submitForm( view );
	assert.deepEqual( notices( view, 'success' ), [] );
	assert.deepEqual( notices( view, 'warning' ), [] );
	assert.deepEqual( notices( view, 'error' ), [ config.invalid_coupon_count ] );

	// A successful attempt replaces the error notice.
	setField( view, view.count, '1' );
	submitForm( view );
	await waitForRun( view );
	assert.deepEqual( notices( view, 'error' ), [] );
	assert.equal( notices( view, 'success' ).length, 1 );
	assert.equal( view.document.querySelectorAll( '.notice.fgcbg-success-message, .notice.fgcbg-error-message, .notice.fgcbg-warning-message' ).length, 1 );
} );

test( 'a notice is put above the form and scrolled into view, smoothly unless reduced motion is asked for', async () => {
	for ( const [ reducedMotion, behavior ] of [ [ false, 'smooth' ], [ true, 'auto' ] ] ) {
		view?.close();
		view = await loadPage( { reducedMotion } );
		submitForm( view );

		const notice = view.document.querySelector( '.fgcbg-error-message' );
		assert.equal( notice.nextElementSibling, view.form, 'The notice sits directly above the form.' );
		assert.equal( view.scrolls.length, 1 );
		assert.equal( view.scrolls[ 0 ].behavior, behavior, `reduced motion: ${ reducedMotion }` );
		assert.equal( view.scrolls[ 0 ].block, 'start' );
	}
} );
