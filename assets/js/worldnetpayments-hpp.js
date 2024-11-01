jQuery( function( $ ) {
	'use strict';
	
	/**
	 * Object to handle WorldnetPayments payment forms.
	 */
	var wc_worldnetpayments_form = {

		/**
		 * Initialize e handlers and UI state.
		 */
		init: function( form ) {
			this.form          = form;
			this.worldnetpayments_submit = false;

			$( this.form )
				// We need to bind directly to the click (and not checkout_place_order_worldnetpayments) to avoid popup blockers
				// especially on mobile devices (like on Chrome for iOS) from blocking WorldnetPaymentsCheckout.open from opening a tab
				.on( 'click', '#place_order', this.onSubmit )

				// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
				.on( 'submit checkout_place_order_worldnetpayments' );

			$( document.body ).on( 'checkout_error', this.resetModal );
		},

		isWorldnetPaymentsChosen: function() {
			return $( '#payment_method_worldnetpayments' ).is( ':checked' ) && ( ! $( 'input[name="wc-worldnetpayments-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-worldnetpayments-payment-token"]:checked' ).val() );
		},

		isWorldnetPaymentsModalNeeded: function( e ) {
			var token = wc_worldnetpayments_form.form.find( 'input.worldnetpayments_token' ),
				$required_inputs;

			// If this is a worldnetpayments submission (after modal) and token exists, allow submit.
			if ( wc_worldnetpayments_form.worldnetpayments_submit && token ) {
				return false;
			}

			// Don't affect submission if modal is not needed.
			if ( ! wc_worldnetpayments_form.isWorldnetPaymentsChosen() ) {
				return false;
			}

			// Don't open modal if required fields are not complete
			if ( $( 'input#terms' ).length === 1 && $( 'input#terms:checked' ).length === 0 ) {
				return false;
			}

			if ( $( '#createaccount' ).is( ':checked' ) && $( '#account_password' ).length && $( '#account_password' ).val() === '' ) {
				return false;
			}

			// check to see if we need to validate shipping address
			if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
				$required_inputs = $( '.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required' );
			} else {
				$required_inputs = $( '.woocommerce-billing-fields .validate-required' );
			}

			if ( $required_inputs.length ) {
				var required_error = false;

				$required_inputs.each( function() {
					if ( $( this ).find( 'input.input-text, select' ).not( $( '#account_password, #account_username' ) ).val() === '' ) {
						required_error = true;
					}

					var emailField = $( this ).find( '#billing_email' );

					if ( emailField.length ) {
						var re = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

						if ( ! re.test( emailField.val() ) ) {
							required_error = true;
						}
					}
				});

				if ( required_error ) {
					return false;
				}
			}

			return true;
		},

		block: function() {
			wc_worldnetpayments_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_worldnetpayments_form.form.unblock();
		},

		onClose: function() {
			wc_worldnetpayments_form.unblock();
		},

		onSubmit: function( e ) {
			var $form = wc_worldnetpayments_form.form;

			$form.submit();

			return false;
		},

		resetModal: function() {
			wc_worldnetpayments_form.form.find( 'input.worldnetpayments_token' ).remove();
			wc_worldnetpayments_form.worldnetpayments_submit = false;
		}
	};

	wc_worldnetpayments_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
