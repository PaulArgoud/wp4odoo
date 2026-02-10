/**
 * Lead form AJAX submission handler.
 *
 * @package WP4Odoo
 * @since   1.0.0
 */
( function () {
	'use strict';

	document.querySelectorAll( '.wp4odoo-lead-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var feedback = form.querySelector( '.wp4odoo-lead-feedback' );
			var button   = form.querySelector( '.wp4odoo-lead-submit' );
			var original = button.textContent;

			feedback.textContent = '';
			feedback.className   = 'wp4odoo-lead-feedback';
			button.disabled      = true;
			button.textContent   = wp4odooLead.i18n.sending;

			var data = new FormData( form );
			data.append( 'action', 'wp4odoo_submit_lead' );
			data.append( '_ajax_nonce', wp4odooLead.nonce );
			data.append( 'source', form.dataset.source || '' );

			fetch( wp4odooLead.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( json.success ) {
						feedback.textContent = json.data.message || wp4odooLead.i18n.success;
						feedback.classList.add( 'success' );
						form.reset();
					} else {
						feedback.textContent = json.data.message || wp4odooLead.i18n.error;
						feedback.classList.add( 'error' );
					}
				} )
				.catch( function () {
					feedback.textContent = wp4odooLead.i18n.error;
					feedback.classList.add( 'error' );
				} )
				.finally( function () {
					button.disabled    = false;
					button.textContent = original;
				} );
		} );
	} );
} )();
