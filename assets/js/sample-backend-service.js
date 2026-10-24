/*
 * Copy button of SampleBackendService's notice.
 *
 * The service declares this script for admin screens, deferred, so it
 * usually runs once the notice has been parsed. WordPress before 6.3 loads it
 * the classic way, in the head and before the notice, so it waits for the
 * document whenever that is still loading. It copies the text the button
 * carries and reports the result in the status next to it. The messages come
 * from the notice's markup, so this file holds no text of its own.
 */
( function () {
	'use strict';

	function setUp( button ) {
		var status = button.parentNode.querySelector( '.mwpd-basic-scaffold-copy-status' );

		function report( message ) {
			if ( status ) {
				status.textContent = message;
			}
		}

		// The Clipboard API needs a secure context, so the button stays
		// hidden where it could not work.
		if ( ! navigator.clipboard ) {
			return;
		}

		button.hidden = false;

		button.addEventListener( 'click', function () {
			navigator.clipboard.writeText( button.dataset.mwpdBasicScaffoldCopy ).then(
				function () {
					report( button.dataset.mwpdBasicScaffoldCopied );
				},
				function () {
					report( button.dataset.mwpdBasicScaffoldFailed );
				}
			);
		} );
	}

	function setUpAll() {
		var buttons = document.querySelectorAll( '[data-mwpd-basic-scaffold-copy]' );

		Array.prototype.forEach.call( buttons, setUp );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', setUpAll );
	} else {
		setUpAll();
	}
}() );
