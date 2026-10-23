/*
 * Copy button of SampleBackendService's notice.
 *
 * The service declares this script for admin screens, deferred, so the notice
 * has been parsed by the time it runs. It copies the text the button carries
 * and reports the result in the status next to it. The messages come from the
 * notice's markup, so this file holds no text of its own.
 */
( function () {
	'use strict';

	var buttons = document.querySelectorAll( '[data-mwpd-basic-scaffold-copy]' );

	Array.prototype.forEach.call( buttons, function ( button ) {
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
	} );
}() );
