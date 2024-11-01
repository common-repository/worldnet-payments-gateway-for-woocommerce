<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_WorldnetPayments_Payment_Tokens extends WorldnetPayments_Gateway {
	private static $_this;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
        parent::__construct();
		self::$_this = $this;

        add_action( 'wp', array( $this, 'delete_payment_method_action' ), 19);

        add_action( 'rest_api_init', function () {
            register_rest_route( 'worldnetpayments-gateway-for-woocommerce/v1', 'secure-token-registration-response', array(
                'methods' => 'GET',
                'callback' => array( $this, 'secure_token_registration_response' ),
                'permission_callback' => '__return_true',
                'login_user_id' => get_current_user_id()
            ) );
        } );
    }

    public static function get_instance() {
        return self::$_this;
    }

    /**
     * Process the delete payment method form.
     */
    public function delete_payment_method_action() {
        global $wp;

        if ( isset( $wp->query_vars['delete-payment-method'] ) ) {
            wc_nocache_headers();

            $token_id = absint( $wp->query_vars['delete-payment-method'] );
            $token    = WC_Payment_Tokens::get( $token_id );

            $terminalId = $token->get_meta("terminal_id");

            if($terminalId === $this->publishable_key && $this->secret_key) {
                $terminalId = $this->publishable_key;
                $secret = $this->secret_key;
            } else if($terminalId === $this->publishable_key2 && $this->secret_key2) {
                $terminalId = $this->publishable_key2;
                $secret = $this->secret_key2;
            } else if($terminalId === $this->publishable_key3 && $this->secret_key3) {
                $terminalId = $this->publishable_key3;
                $secret = $this->secret_key3;
            }

            $merchantRef = $token->get_meta("merchant_ref");
            $secureCardCardRef = $token->get_token();

            $XmlSecureCardDelRequest = new WorldnetPaymentsXmlSecureCardDelRequest($merchantRef, $terminalId, $secureCardCardRef);

            $serverUrl = $this->testmode == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';
            $response = $XmlSecureCardDelRequest->ProcessRequestToGateway($secret, $serverUrl);

            if ($response->IsError()) {
                wc_add_notice( __( $response->ErrorString(), 'woocommerce' ), 'error' );
            } else {
                if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() || ! isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'delete-payment-method-' . $token_id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    wc_add_notice( __( 'Invalid payment method.', 'woocommerce' ), 'error' );
                } else {
                    WC_Payment_Tokens::delete( $token_id );
                    wc_add_notice( __( 'Payment method deleted.', 'woocommerce' ) );
                }
            }

            wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
            exit();
        }

    }

    function secure_token_registration_response( WP_REST_Request $request ) {
        if(isset($_GET['CARDREFERENCE'])) {
            @session_start();

            if(isset($_GET['RESPONSECODE']) && sanitize_text_field($_GET['RESPONSECODE']) !== 'A') {

                wc_add_notice( sanitize_text_field($_GET['RESPONSETEXT']), 'error' );

            } else if(isset($_GET['RESPONSECODE']) && sanitize_text_field($_GET['RESPONSECODE']) === 'A') {
                $options = get_option("woocommerce_worldnetpayments_settings");

                $merchantRef = wc_clean(wp_unslash($_GET['MERCHANTREF']));
                $orderDetails =  $_SESSION['wc_gateway_worldnetpayments_secure_token_reg_request'];
                $order_id = $orderDetails['orderId'];
                $terminalId = $orderDetails['terminalId'];

                $env = "live_";
                if($options['testmode'] == "yes")
                    $env = "test_";

                if($terminalId == $options[$env.'publishable_key'] && $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                    $terminalId = $options[$env.'publishable_key'];
                    $secret = $options[$env.'secret_key'];
                    $multicurrency = $options[$env.'multicurrency'];
                } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                    $terminalId = $options[$env.'publishable_key2'];
                    $secret = $options[$env.'secret_key2'];
                    $multicurrency = $options[$env.'multicurrency2'];
                } else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                    $terminalId = $options[$env.'publishable_key3'];
                    $secret = $options[$env.'secret_key3'];
                    $multicurrency = $options[$env.'multicurrency3'];
                }

                $expectedHash = md5 ($terminalId . $_GET['RESPONSECODE'] . $_GET['RESPONSETEXT'] . $_GET['MERCHANTREF'] . $_GET['CARDREFERENCE'] . $_GET['DATETIME'] . $secret);
                if(!hash_equals($expectedHash, $_GET['HASH'])) {
                    wc_add_notice( __('There was an issue with the server response.'), 'error' );
                    return;
                }

                $cardNumber = wc_clean(wp_unslash($_GET['MASKEDCARDNUMBER']));
                $cardExpiry = wc_clean(wp_unslash($_GET['CARDEXPIRY']));
                $last4 = substr($cardNumber, -4);
                $month = substr($cardExpiry, 0, 2);
                $year = '20' . substr($cardExpiry, -2);

                $attrs =  $request->get_attributes();
                $user_id        = intval($attrs['login_user_id']);

                $token = wc_clean(wp_unslash($_GET['CARDREFERENCE']));
                $wc_token = new WC_WorldnetPayments_Payment_Token_CC();
                $wc_token->set_token( $token );
                $wc_token->set_gateway_id( 'worldnetpayments' );
                $wc_token->set_card_type( strtolower( wc_clean(wp_unslash($_GET['CARDTYPE'])) ) );
                $wc_token->set_last4( $last4 );
                $wc_token->set_expiry_month( $month );
                $wc_token->set_expiry_year( $year );
                $wc_token->set_merchant_ref( $merchantRef );
                $wc_token->set_terminal_id( $terminalId );

                $wc_token->set_user_id( $user_id );
                $wc_token->save();


                $order = wc_get_order($order_id);
                $currency = $order->get_currency();
                $orderTotal = $order->get_total();

                foreach ( WC()->cart->get_cart() as $item ) {
                    if ( class_exists( 'WC_Subscriptions_Product' ) &&  WC_Subscriptions_Product::is_subscription( $item['product_id'] )) {
                        $product = wc_get_product( $item['product_id'] );

                        if(!$this->process_subscription($order, $product, $merchantRef, $terminalId, $secret)) {
                            wp_redirect(wc_get_checkout_url());
                            exit();
                        } else {
                            $orderTotal -= $product->get_meta('_subscription_sign_up_fee');
                            $orderTotal -= $product->get_meta('_subscription_price');
                        }
                    }
                }

                # Set up the authorisation object
                $amount = number_format(wc_clean(wp_unslash($orderTotal)), 2, '.', '');
                $cardNumber = $token;
                $cardType = 'SECURECARD';

                $receiptPageUrl = $order->get_checkout_order_received_url();

                if($amount <= 0) {
                    $order->update_status( apply_filters( 'woocommerce_gateway_worldnetpayments_process_payment_order_status', 'processing', $order ), __( 'Order successfully processed.', 'woocommerce' ) );

                    $order->save();

                    WC()->mailer()->customer_invoice( $order );

                    wc_reduce_stock_levels( $order_id );

                    WC()->cart->empty_cart();
                    $_SESSION['wc_gateway_worldnetpayments_secure_token_reg_request'] = '';
                    unset($_SESSION['wc_gateway_worldnetpayments_secure_token_reg_request']);

                    wp_redirect($receiptPageUrl);
                    exit();
                }

                $XmlAuthRequest = new WorldnetPaymentsGatewayXmlAuthRequest($terminalId, $order_id, $currency, $amount, $cardNumber, $cardType);

                if ($multicurrency=='yes') $XmlAuthRequest->SetMultiCur();


                if(true || $options['avs']) { //always send AVS data on xml requests and let gateway decide if will use it or not
                    $address1 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1();
                    $address2 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2();
                    $postcode = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode();
                    $city = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_city : $order->get_billing_city();
                    $region = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_state : $order->get_billing_state();
                    $country = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country();
                    $email = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();
                    $phone = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_phone : $order->get_billing_phone();


                    $XmlAuthRequest->SetAvs($address1, $address2, $postcode);
                    $XmlAuthRequest->SetCity($city);
                    if($region != "") $XmlAuthRequest->SetRegion($region);
                    $XmlAuthRequest->SetCountry($country);
                    $XmlAuthRequest->SetEmail($email);
                    $XmlAuthRequest->SetPhone($phone);

                    $ipAddress = false;
                    if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ipAddress = wc_clean(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
                    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                        if(is_array($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                            for($i = 0; $i < count($_SERVER['HTTP_X_FORWARDED_FOR']); $i++) {
                                if(filter_var($_SERVER['HTTP_X_FORWARDED_FOR'][$i], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                                    $ipAddress = wc_clean(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'][$i]));
                                    break;
                                }
                            }
                        } elseif(filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            $ipAddress = wc_clean(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
                        }
                    }
                    if(!$ipAddress && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ipAddress = wc_clean(wp_unslash($_SERVER['REMOTE_ADDR']));
                    } elseif(filter_var($options['default_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ipAddress = $options['default_ip'];
                    }
                    if($ipAddress) $XmlAuthRequest->SetIPAddress($ipAddress);
                }

                # Perform the online authorisation and read in the result
                $serverUrl = $options['testmode'] == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';
                $response = $XmlAuthRequest->ProcessRequestToGateway($secret, $serverUrl);

                $expectedResponseHash = md5($terminalId . $response->UniqueRef() . ($multicurrency=='yes' ? $currency : '')  . $amount . $response->DateTime() . $response->ResponseCode() . $response->ResponseText() . $secret);
                $isHashCorrect = ($expectedResponseHash == $response->Hash());

                $worldnetpaymentsResponse = '';

                if ($response->IsError()) $worldnetpaymentsResponse .= 'AN ERROR OCCURED! Your transaction was not processed. Error details: ' . $response->ErrorString();
                elseif ($isHashCorrect) {
                    switch ($response->ResponseCode()) {
                        case "A" :    # -- If using local database, update order as Authorised.
                            $worldnetpaymentsResponse .= 'Payment Processed successfully. Thanks you for your order.';
                            $uniqueRef = $response->UniqueRef();
                            $responseText = $response->ResponseText();
                            $approvalCode = $response->ApprovalCode();
                            $avsResponse = $response->AvsResponse();
                            $cvvResponse = $response->CvvResponse();
                            break;
                        case "R" :
                        case "D" :
                        case "C" :
                        case "S" :
                        default  :    # -- If using local database, update order as declined/failed --
                            $worldnetpaymentsResponse .= 'PAYMENT DECLINED! Please try again with another card. Bank response: ' . $response->ResponseText();
                    }
                } else {
                    $worldnetpaymentsResponse .= 'PAYMENT FAILED: INVALID RESPONSE HASH. Please contact ' . $adminEmail . ' or call ' . $adminPhone . ' to clarify if you will get charged for this order.';
                    if ($response->UniqueRef()) $worldnetpaymentsResponse .= 'Please quote Worldnet Payments Terminal ID: ' . $terminalId . ', and Unique Reference: ' . $response->UniqueRef() . ' when mailing or calling.';
                }

                if (!$response->IsError() && $isHashCorrect && $response->ResponseCode() == 'A') {

                    $order->update_status( apply_filters( 'woocommerce_gateway_worldnetpayments_process_payment_order_status', 'processing', $order ), __( 'Payment successfully processed #'. $response->UniqueRef(), 'woocommerce' ) );

                    $order->set_transaction_id( $response->UniqueRef() );

                    $order->save();

                    WC()->mailer()->customer_invoice( $order );

                    wc_reduce_stock_levels( $order_id );

                    WC()->cart->empty_cart();
                    $_SESSION['wc_gateway_worldnetpayments_secure_token_reg_request'] = '';
                    unset($_SESSION['wc_gateway_worldnetpayments_secure_token_reg_request']);

                    wp_redirect($receiptPageUrl);
                    exit();
                }
                else {
                    wc_add_notice( $worldnetpaymentsResponse, 'error' );
                }
            }
        }
    }
}

new WC_WorldnetPayments_Payment_Tokens();
