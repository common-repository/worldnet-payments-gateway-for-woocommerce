<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_WorldnetPayments_Payment_3DS extends WorldnetPayments_Gateway {
	private static $_this;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0l
	 * @version 4.0.0
	 */
	public function __construct() {
        parent::__construct();
		self::$_this = $this;

        add_action( 'rest_api_init', function () {
            register_rest_route( 'worldnetpayments-gateway-for-woocommerce/v1', '3ds-request', array(
                'methods' => 'GET',
                'callback' => array( $this, 'WorldnetPayments_3ds_request' ),
                'permission_callback' => '__return_true',
            ) );
        } );

        add_action( 'rest_api_init', function () {
            register_rest_route( 'worldnetpayments-gateway-for-woocommerce/v1', '3ds-response', array(
                'methods' => 'GET',
                'callback' => array( $this, 'WorldnetPayments_3ds_response' ),
                'permission_callback' => '__return_true',
            ) );
        } );
    }

    public static function get_instance() {
        return self::$_this;
    }

    function WorldnetPayments_3ds_request( WP_REST_Request $request ) {
        @session_start();

        if(isset($_SESSION['request3ds'])) {
            $request3ds = $_SESSION['request3ds'];

            $checkSum = md5($request3ds['TERMINALID'] . ':' . $request3ds['CARDHOLDERNAME'] . ':' .
                $request3ds['CARDNUMBER'] . ':' . $request3ds['CARDEXPIRY'] . ':' . $request3ds['CARDTYPE'] . ':' .
                $request3ds['AMOUNT'] . ':' . $request3ds['CURRENCY'] . ':' . $request3ds['ORDERID'] . ':' .
                $request3ds['CVV'] . ':' . $request3ds['DATETIME'] . ':' . $request3ds['HASH'] . ':' . $request3ds['redirectUrl'] );

            if(hash_equals($checkSum, $request3ds['checkSum'])) {
                header('Content-Type: text/html');
                echo '
                <!doctype html>
                <html>
                <head>
                <meta charset="utf-8">
                </head>
                
                <body>
                    <script type="text/javascript">
                        var form = document.createElement("form");
                        form.style.display = "none";
                        
                        var TERMINALID = document.createElement("input"); 
                        TERMINALID.value="'. $request3ds['TERMINALID'] .'";
                        TERMINALID.name="TERMINALID";
                        form.appendChild(TERMINALID);  
                        
                        var CARDHOLDERNAME = document.createElement("input"); 
                        CARDHOLDERNAME.value="'. $request3ds['CARDHOLDERNAME'] .'";
                        CARDHOLDERNAME.name="CARDHOLDERNAME";
                        form.appendChild(CARDHOLDERNAME);  
                        
                        var CARDNUMBER = document.createElement("input"); 
                        CARDNUMBER.value="'. $request3ds['CARDNUMBER'] .'";
                        CARDNUMBER.name="CARDNUMBER";
                        form.appendChild(CARDNUMBER);  
                        
                        var CARDEXPIRY = document.createElement("input"); 
                        CARDEXPIRY.value="'. $request3ds['CARDEXPIRY'] .'";
                        CARDEXPIRY.name="CARDEXPIRY";
                        form.appendChild(CARDEXPIRY);  
                        
                        var CARDTYPE = document.createElement("input"); 
                        CARDTYPE.value="'. $request3ds['CARDTYPE'] .'";
                        CARDTYPE.name="CARDTYPE";
                        form.appendChild(CARDTYPE);  
                        
                        var AMOUNT = document.createElement("input"); 
                        AMOUNT.value="'. $request3ds['AMOUNT'] .'";
                        AMOUNT.name="AMOUNT";
                        form.appendChild(AMOUNT);  
                        
                        var CURRENCY = document.createElement("input"); 
                        CURRENCY.value="'. $request3ds['CURRENCY'] .'";
                        CURRENCY.name="CURRENCY";
                        form.appendChild(CURRENCY);  
                        
                        var ORDERID = document.createElement("input"); 
                        ORDERID.value="'. $request3ds['ORDERID'] .'";
                        ORDERID.name="ORDERID";
                        form.appendChild(ORDERID);  
                        
                        var CVV = document.createElement("input"); 
                        CVV.value="'. $request3ds['CVV'] .'";
                        CVV.name="CVV";
                        form.appendChild(CVV);  
                        
                        var DATETIME = document.createElement("input"); 
                        DATETIME.value="'. $request3ds['DATETIME'] .'";
                        DATETIME.name="DATETIME";
                        form.appendChild(DATETIME);
    
                        var EMAIL = document.createElement("input"); 
                        EMAIL.value="'. $request3ds['EMAIL'] .'";
                        EMAIL.name="EMAIL";
                        form.appendChild(EMAIL);  
                        
                        var HASH = document.createElement("input"); 
                        HASH.value="'. $request3ds['HASH'] .'";
                        HASH.name="HASH";
                        form.appendChild(HASH);  
                        
                        var APP_VERSION = document.createElement("input"); 
                        APP_VERSION.value="7.10.0.0";
                        APP_VERSION.name="APP_VERSION";
                        form.appendChild(APP_VERSION);  
                                        
                        form.method = "POST";
                        form.action = "'. $request3ds['redirectUrl'] .'";  
                    
                        document.body.appendChild(form);
                    
                        form.submit();
                      </script>
                </body>
                </html>';

            }

            $_SESSION['request3ds'] = '';
            unset($_SESSION['request3ds']);
        }
    }

    function WorldnetPayments_3ds_response( WP_REST_Request $request ) {
        if(isset($_GET['ORDERID'])) {
            @session_start();
            $order_id = absint($_GET['ORDERID']);

            $cardDetails = $_SESSION['wc_gateway_worldnetpayments_'.$order_id];

            $checkSum = md5($cardDetails['testmode'] . ':' . $cardDetails['avs'] . ':'
                . $cardDetails['multicurrency'] . ':' . $cardDetails['terminalId'] . ':' . $cardDetails['secret']
                . ':' . $cardDetails['currency'] . ':' . $cardDetails['amount'] . ':'  . $cardDetails['cardNumber']
                . ':' . $cardDetails['cardHolder'] . ':' . $cardDetails['cardExpiry'] . ':' . $cardDetails['cardType']
                . ':' . $cardDetails['cvv'] . ':' . $cardDetails['orderHasSubscription'] );

            $expectedHash = md5 ($_GET['RESULT'] . (isset($_GET['MPIREF'])?$_GET['MPIREF']:$_GET['TERMINALID']) . $_GET['ORDERID'] . $_GET['DATETIME'] . $cardDetails['secret']);

            if(!hash_equals($checkSum, $cardDetails['checkSum']) || !hash_equals($expectedHash, $_GET['HASH'])) {
                wc_add_notice( __('There\'s an issue with your cart session. Please try again.'), 'error' );
                return;
            }

            $order = wc_get_order($order_id);

            if(isset($_GET['RESULT']) && sanitize_text_field($_GET['RESULT']) == 'D') {
                $orderStatus = $order->get_status();
                $orderStatus = 'wc-' === substr( $orderStatus, 0, 3 ) ? substr( $orderStatus, 3 ) : $orderStatus;

                $receiptPageUrl = $order->get_checkout_order_received_url();

                if($orderStatus != 'failed') {
                    $order->update_status(apply_filters('woocommerce_gateway_worldnetpayments_process_payment_order_status', 'failed', $order), __('3DS failed'. (isset($_GET['STATUS'])?'; STATUS='.sanitize_text_field($_GET['STATUS']):'') . (isset($_GET['ECI'])?'; ECI='.sanitize_text_field($_GET['ECI']):''), 'woocommerce'));

                    // Remove cart
                    WC()->cart->empty_cart();
                    $_SESSION['wc_gateway_worldnetpayments_'.$order_id] = '';
                    unset($_SESSION['wc_gateway_worldnetpayments_'.$order_id]);
                }

                wp_redirect($receiptPageUrl);
                exit();
            } else if(isset($_GET['RESULT']) && (sanitize_text_field($_GET['RESULT']) == 'A' || (sanitize_text_field($_GET['RESULT']) == 'E' && isset($_GET['ERRORCODE']) && sanitize_text_field($_GET['ERRORCODE']) == '3'))) {
                $testmode = $cardDetails['testmode'];
                $avs = $cardDetails['avs'];
                $multicurrency = $cardDetails['multicurrency'];
                $terminalId = $cardDetails['terminalId'];
                $secret = $cardDetails['secret'];
                $currency = $cardDetails['currency'];
                $amount = $cardDetails['amount'];
                $cardNumber = $cardDetails['cardNumber'];
                $cardHolder = $cardDetails['cardHolder'];
                $cardExpiry = $cardDetails['cardExpiry'];
                $cardType = $cardDetails['cardType'];
                $cvv = $cardDetails['cvv'];

                if($cardDetails['orderHasSubscription']) {
                    $last4 = substr($cardNumber, -4);
                    $month = substr($cardExpiry, 0, 2);
                    $year = '20' . substr($cardExpiry, -2);

                    $address1 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1();
                    $postcode = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode();
                    $email = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();
                    $phone = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_phone : $order->get_billing_phone();

                    $regRequest = $this->add_payment_method_reg_request($terminalId, $secret, $cardHolder, $cardNumber, $cardExpiry, $cardType, $cvv, $last4, $month, $year, $address1, $postcode, $email, $phone);
                    $cardNumber = $regRequest['cardReference'];
                    $merchantReference = $regRequest['merchantReference'];

                    $cardType = 'SECURECARD';

                    if(isset($regRequest['error'])) {
                        wc_add_notice( $regRequest['error_msg'], 'error' );

                        wp_redirect(wc_get_checkout_url());
                        exit();
                    }

                    if($cardNumber === false) return;

                    //add subscriptions
                    foreach ( WC()->cart->get_cart() as $item ) {
                        if ( class_exists( 'WC_Subscriptions_Product' ) &&  WC_Subscriptions_Product::is_subscription( $item['product_id'] )) {
                            $product = wc_get_product( $item['product_id'] );

                            if(!$this->process_subscription($order, $product, $merchantReference, $terminalId, $secret)) {
                                wp_redirect(wc_get_checkout_url());
                                exit();
                            } else {
                                $amount -= $product->get_meta('_subscription_sign_up_fee');
                                $amount -= $product->get_meta('_subscription_price');
                            }
                        }
                    }
                }

                $receiptPageUrl = $order->get_checkout_order_received_url();

                if($amount <= 0 && $cardDetails['orderHasSubscription']) {
                    $order->update_status( apply_filters( 'woocommerce_gateway_worldnetpayments_process_payment_order_status', 'processing', $order ), __( 'Order successfully processed.', 'woocommerce' ) );

                    $order->save();

                    WC()->mailer()->customer_invoice( $order );

                    wc_reduce_stock_levels( $order_id );

                    WC()->cart->empty_cart();

                    wp_redirect($receiptPageUrl);
                    exit();
                }

                $XmlAuthRequest = new WorldnetPaymentsGatewayXmlAuthRequest($terminalId, $order_id, $currency, $amount, $cardNumber, $cardType);

                $XmlAuthRequest->SetTransactionType("5");

                if ($cardType != "SECURECARD") $XmlAuthRequest->SetNonSecureCardCardInfo($cardExpiry, $cardHolder);
                if ($cvv != "") $XmlAuthRequest->SetCvv($cvv);
                if ($multicurrency=='yes') $XmlAuthRequest->SetMultiCur();
                if (isset($_GET['MPIREF'])) $XmlAuthRequest->SetMpiRef(wc_clean(wp_unslash($_GET['MPIREF'])));
                if (isset($_GET['XID'])) $XmlAuthRequest->SetXid(wc_clean(wp_unslash($_GET['XID'])));
                if (isset($_GET['CAVV'])) $XmlAuthRequest->SetCavv(wc_clean(wp_unslash($_GET['CAVV'])));


                if(true || $avs) { //always send AVS data on xml requests and let gateway decide if will use it or not
                    $options = get_option("woocommerce_worldnetpayments_settings");

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
                $serverUrl = $testmode == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';
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
                    $_SESSION['wc_gateway_worldnetpayments_'.$order_id] = '';
                    unset($_SESSION['wc_gateway_worldnetpayments_'.$order_id]);

                    wp_redirect($receiptPageUrl);
                    exit();
                }
                else {
                    $order->update_status(apply_filters('woocommerce_gateway_worldnetpayments_process_payment_order_status', 'failed', $order), __('Payment failed: '.$worldnetpaymentsResponse, 'woocommerce'));

                    // Remove cart
                    WC()->cart->empty_cart();
                    $_SESSION['wc_gateway_worldnetpayments_'.$order_id] = '';
                    unset($_SESSION['wc_gateway_worldnetpayments_'.$order_id]);

                    wp_redirect($receiptPageUrl);
                    exit();
                }
            }
        }
    }
}

new WC_WorldnetPayments_Payment_3DS();
