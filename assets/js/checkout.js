/**
 * Starfiniti Abandoned Cart: checkout listener.
 *
 * Sends the address typed into the checkout to the capture endpoint and shows
 * the reminder notice or the consent checkbox under the e-mail field.
 * Uses polling instead of a MutationObserver so it never fights with plugins
 * that rewrite the DOM (translation plugins, block checkout re-renders).
 */
( function () {
	'use strict';

	var config = window.sfacCheckout;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	var lastSent = '';
	var consentGiven = false;
	var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

	function findInput() {
		var selectors = config.selectors || [];

		for ( var i = 0; i < selectors.length; i++ ) {
			var input = document.querySelector( selectors[ i ] );

			if ( input ) {
				return input;
			}
		}

		return null;
	}

	function send( email, useBeacon ) {
		email = ( email || '' ).trim();

		if ( ! pattern.test( email ) ) {
			return;
		}

		if ( 'checkbox' === config.consent && ! consentGiven ) {
			return;
		}

		if ( email === lastSent && ! useBeacon ) {
			return;
		}

		lastSent = email;

		var body = JSON.stringify( {
			email: email,
			language: config.language || '',
			consent: consentGiven,
		} );

		try {
			if ( useBeacon && navigator.sendBeacon ) {
				navigator.sendBeacon( config.endpoint, new Blob( [ body ], { type: 'application/json' } ) );
				return;
			}

			window.fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: body,
			} ).catch( function () {} );
		} catch ( error ) {
			// Capture must never disturb the checkout.
		}
	}

	function isEmailField( target ) {
		if ( ! target || 'INPUT' !== target.tagName ) {
			return false;
		}

		if ( 'email' === target.type ) {
			return true;
		}

		var selectors = config.selectors || [];

		for ( var i = 0; i < selectors.length; i++ ) {
			if ( target.matches && target.matches( selectors[ i ] ) ) {
				return true;
			}
		}

		return false;
	}

	document.addEventListener( 'change', function ( event ) {
		if ( isEmailField( event.target ) ) {
			send( event.target.value );
		}
	}, true );

	document.addEventListener( 'focusout', function ( event ) {
		if ( isEmailField( event.target ) ) {
			send( event.target.value );
		}
	}, true );

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' !== document.visibilityState ) {
			return;
		}

		var input = findInput();

		if ( input && input.value.trim() !== lastSent ) {
			send( input.value, true );
		}
	} );

	function wrapperOf( element ) {
		return element.closest( '.wc-block-components-text-input, .form-row, .nv-field' ) || element;
	}

	function placeNotice() {
		if ( document.querySelector( '.sfac-notice' ) ) {
			return;
		}

		var anchor = config.anchor ? document.querySelector( config.anchor ) : findInput();

		if ( ! anchor ) {
			return;
		}

		var wrapper = config.anchor ? anchor : wrapperOf( anchor );
		var box;

		if ( 'checkbox' === config.consent && config.checkbox ) {
			box = document.createElement( 'label' );
			box.className = 'sfac-notice sfac-consent';
			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.addEventListener( 'change', function () {
				consentGiven = checkbox.checked;

				var input = findInput();

				if ( consentGiven && input ) {
					lastSent = '';
					send( input.value );
				}
			} );
			box.appendChild( checkbox );
			box.appendChild( document.createTextNode( ' ' + config.checkbox ) );
		} else if ( config.notice ) {
			box = document.createElement( 'p' );
			box.className = 'sfac-notice';
			box.textContent = config.notice;
		} else {
			return;
		}

		box.style.margin = '6px 0 0';
		box.style.fontSize = '13px';
		box.style.lineHeight = '1.4';
		box.style.opacity = '0.8';
		wrapper.insertAdjacentElement( 'afterend', box );
	}

	placeNotice();
	window.setInterval( placeNotice, 1500 );
} )();
