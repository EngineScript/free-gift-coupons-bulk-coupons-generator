/**
 * Tests for assets/js/admin.js.
 *
 * Run with `npm test`. See helpers.mjs for how the page is built.
 */
import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import {
	formatted,
	loadPage,
	notices,
	okResponse,
	page,
	selectProducts,
	setField,
	source,
	submitForm,
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
