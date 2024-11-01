jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle WorldnetPayments admin functions.
	 */
	var worldnetpayments_gateway_admin = {

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_worldnetpayments_testmode', function() {
				var test_secret_key = $( '#woocommerce_worldnetpayments_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key = $( '#woocommerce_worldnetpayments_test_publishable_key' ).parents( 'tr' ).eq( 0 ),
					test_currency = $( '#woocommerce_worldnetpayments_test_currency' ).parents( 'tr' ).eq( 0 ),
					test_multicurrency = $( '#woocommerce_worldnetpayments_test_multicurrency' ).parents( 'tr' ).eq( 0 ),
					test_secret_key2 = $( '#woocommerce_worldnetpayments_test_secret_key2' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key2 = $( '#woocommerce_worldnetpayments_test_publishable_key2' ).parents( 'tr' ).eq( 0 ),
					test_currency2 = $( '#woocommerce_worldnetpayments_test_currency2' ).parents( 'tr' ).eq( 0 ),
					test_multicurrency2 = $( '#woocommerce_worldnetpayments_test_multicurrency2' ).parents( 'tr' ).eq( 0 ),
					test_secret_key3 = $( '#woocommerce_worldnetpayments_test_secret_key3' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key3 = $( '#woocommerce_worldnetpayments_test_publishable_key3' ).parents( 'tr' ).eq( 0 ),
					test_currency3 = $( '#woocommerce_worldnetpayments_test_currency3' ).parents( 'tr' ).eq( 0 ),
					test_multicurrency3 = $( '#woocommerce_worldnetpayments_test_multicurrency3' ).parents( 'tr' ).eq( 0 ),

                    test_applepay_merchant_identifier = $( '#woocommerce_worldnetpayments_test_applepay_merchant_identifier' ).parents( 'tr' ).eq( 0 ),
                    test_applepay_display_name = $( '#woocommerce_worldnetpayments_test_applepay_display_name' ).parents( 'tr' ).eq( 0 ),
                    test_applepay_initiative_context = $( '#woocommerce_worldnetpayments_test_applepay_initiative_context' ).parents( 'tr' ).eq( 0 ),

					live_secret_key = $( '#woocommerce_worldnetpayments_live_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key = $( '#woocommerce_worldnetpayments_live_publishable_key' ).parents( 'tr' ).eq( 0 ),
					live_currency = $( '#woocommerce_worldnetpayments_live_currency' ).parents( 'tr' ).eq( 0 ),
					live_multicurrency = $( '#woocommerce_worldnetpayments_live_multicurrency' ).parents( 'tr' ).eq( 0 ),
					live_secret_key2 = $( '#woocommerce_worldnetpayments_live_secret_key2' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key2 = $( '#woocommerce_worldnetpayments_live_publishable_key2' ).parents( 'tr' ).eq( 0 ),
					live_currency2 = $( '#woocommerce_worldnetpayments_live_currency2' ).parents( 'tr' ).eq( 0 ),
					live_multicurrency2 = $( '#woocommerce_worldnetpayments_live_multicurrency2' ).parents( 'tr' ).eq( 0 ),
					live_secret_key3 = $( '#woocommerce_worldnetpayments_live_secret_key3' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key3 = $( '#woocommerce_worldnetpayments_live_publishable_key3' ).parents( 'tr' ).eq( 0 ),
					live_currency3 = $( '#woocommerce_worldnetpayments_live_currency3' ).parents( 'tr' ).eq( 0 ),
					live_multicurrency3 = $( '#woocommerce_worldnetpayments_live_multicurrency3' ).parents( 'tr' ).eq( 0 ),

                	live_applepay_merchant_identifier = $( '#woocommerce_worldnetpayments_live_applepay_merchant_identifier' ).parents( 'tr' ).eq( 0 ),
                    live_applepay_display_name = $( '#woocommerce_worldnetpayments_live_applepay_display_name' ).parents( 'tr' ).eq( 0 ),
                    live_applepay_initiative_context = $( '#woocommerce_worldnetpayments_live_applepay_initiative_context' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_publishable_key.show();
					test_currency.show();
					test_multicurrency.show();
					test_secret_key2.show();
					test_publishable_key2.show();
					test_currency2.show();
					test_multicurrency2.show();
					test_secret_key3.show();
					test_publishable_key3.show();
					test_currency3.show();
					test_multicurrency3.show();

                    if($('#woocommerce_worldnetpayments_applepay_active').is( ':checked' )) {
						test_applepay_merchant_identifier.show();
						test_applepay_display_name.show();
						test_applepay_initiative_context.show();
					}

					live_secret_key.hide();
					live_publishable_key.hide();
					live_currency.hide();
					live_multicurrency.hide();
					live_secret_key2.hide();
					live_publishable_key2.hide();
					live_currency2.hide();
					live_multicurrency2.hide();
					live_secret_key3.hide();
					live_publishable_key3.hide();
					live_currency3.hide();
					live_multicurrency3.hide();

                    live_applepay_merchant_identifier.hide();
                    live_applepay_display_name.hide();
                    live_applepay_initiative_context.hide();
				} else {
					test_secret_key.hide();
					test_publishable_key.hide();
					test_currency.hide();
					test_multicurrency.hide();
					test_secret_key2.hide();
					test_publishable_key2.hide();
					test_currency2.hide();
					test_multicurrency2.hide();
					test_secret_key3.hide();
					test_publishable_key3.hide();
					test_currency3.hide();
					test_multicurrency3.hide();

                    test_applepay_merchant_identifier.hide();
                    test_applepay_display_name.hide();
                    test_applepay_initiative_context.hide();

					live_secret_key.show();
					live_publishable_key.show();
					live_currency.show();
					live_multicurrency.show();
					live_secret_key2.show();
					live_publishable_key2.show();
					live_currency2.show();
					live_multicurrency2.show();
					live_secret_key3.show();
					live_publishable_key3.show();
					live_currency3.show();
					live_multicurrency3.show();

                    if($('#woocommerce_worldnetpayments_applepay_active').is( ':checked' )) {
						live_applepay_merchant_identifier.show();
						live_applepay_display_name.show();
						live_applepay_initiative_context.show();
					}
				}
			} );


            $( document.body ).on( 'change', '#woocommerce_worldnetpayments_unbranded', function() {
            	var title = $( '#woocommerce_worldnetpayments_title' ),
                    description = $( '#woocommerce_worldnetpayments_description' );

                if ( $( this ).is( ':checked' ) ) {
                    title.val('Debit or Credit Card');
                    description.val('Pay with your debit or credit card.');
                } else {
                    title.val('Debit or Credit Card (Worldnet Payments)');
                    description.val('Pay with your debit or credit card via Worldnet Payments.');
                }
            } );


            $( document.body ).on( 'change', '#woocommerce_worldnetpayments_3ds', function() {
                var mpi_receipt_url = $( '#woocommerce_worldnetpayments_mpi_receipt_url' ).parents( 'tr' ).eq( 0 )

                if ( $( this ).is( ':checked' ) ) {
                    mpi_receipt_url.show();
                } else {
                    mpi_receipt_url.hide();
                }
            } );


            $( document.body ).on( 'change', '#woocommerce_worldnetpayments_securecard', function() {
                var securecard_url = $( '#woocommerce_worldnetpayments_securecard_url' ).parents( 'tr' ).eq( 0 )

                if ( $( this ).is( ':checked' ) ) {
                    securecard_url.show();
                } else {
                    securecard_url.hide();
                }
            } );


            $( document.body ).on( 'change', '#woocommerce_worldnetpayments_background_validation', function() {
                var background_validation_url = $( '#woocommerce_worldnetpayments_background_validation_url' ).parents( 'tr' ).eq( 0 )

                if ( $( this ).is( ':checked' ) ) {
                    background_validation_url.show();
                } else {
                    background_validation_url.hide();
                }
            } );


            $( document.body ).on( 'change', '#woocommerce_worldnetpayments_applepay_active', function() {
                var test_applepay_merchant_identifier = $( '#woocommerce_worldnetpayments_test_applepay_merchant_identifier' ).parents( 'tr' ).eq( 0 ),
                    test_applepay_display_name = $( '#woocommerce_worldnetpayments_test_applepay_display_name' ).parents( 'tr' ).eq( 0 ),
                    test_applepay_initiative_context = $( '#woocommerce_worldnetpayments_test_applepay_initiative_context' ).parents( 'tr' ).eq( 0 ),

                    live_applepay_merchant_identifier = $( '#woocommerce_worldnetpayments_live_applepay_merchant_identifier' ).parents( 'tr' ).eq( 0 ),
                    live_applepay_display_name = $( '#woocommerce_worldnetpayments_live_applepay_display_name' ).parents( 'tr' ).eq( 0 ),
                    live_applepay_initiative_context = $( '#woocommerce_worldnetpayments_live_applepay_initiative_context' ).parents( 'tr' ).eq( 0 );

                if ( $( this ).is( ':checked' ) ) {
                    if($('#woocommerce_worldnetpayments_testmode').is( ':checked' )) {
						test_applepay_merchant_identifier.show();
						test_applepay_display_name.show();
						test_applepay_initiative_context.show();

                        live_applepay_merchant_identifier.hide();
                        live_applepay_display_name.hide();
                        live_applepay_initiative_context.hide();
					} else {
                        test_applepay_merchant_identifier.hide();
                        test_applepay_display_name.hide();
                        test_applepay_initiative_context.hide();

						live_applepay_merchant_identifier.show();
						live_applepay_display_name.show();
						live_applepay_initiative_context.show();
					}
                } else {
					test_applepay_merchant_identifier.hide();
					test_applepay_display_name.hide();
					test_applepay_initiative_context.hide();
					live_applepay_merchant_identifier.hide();
					live_applepay_display_name.hide();
					live_applepay_initiative_context.hide();
                }
			});

            $( '#woocommerce_worldnetpayments_testmode' ).change();
            $( '#woocommerce_worldnetpayments_3ds' ).change();
            $( '#woocommerce_worldnetpayments_securecard' ).change();
            $( '#woocommerce_worldnetpayments_background_validation' ).change();
            $( '#woocommerce_worldnetpayments_applepay_active' ).change();


		}
	};

	worldnetpayments_gateway_admin.init();
});
