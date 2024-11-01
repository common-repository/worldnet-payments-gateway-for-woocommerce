<?php
/*
 * Plugin Name: Worldnet Payments Gateway for WooCommerce
 * Description: Take credit card payments on your store using Worldnet Payments.
 * Author: Worldnet Payments
 * Author URI: https://www.worldnetpayments.com/
 * Version: 2.7.9.2
 * Text Domain: worldnetpayments-gateway-for-woocommerce
 *
 * Tested up to: 6.6.1
 * WC tested up to: 9.1.4
 *
 * Copyright (c) 2020 Worldnet Payments
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WorldnetPayments_Gateway_for_WC_VERSION', '2.7.9.2' );
define( 'WorldnetPayments_Gateway_for_WC_MIN_PHP_VER', '7.0.0' );
define( 'WorldnetPayments_Gateway_for_WC_MIN_WC_VER', '2.5.0' );
define( 'WorldnetPayments_Gateway_for_WC_MAIN_FILE', __FILE__ );
define( 'WC_WorldnetPayments_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
global $terminal_id;

/* Pull in Composer's autoload file */
require("vendor/autoload.php");

class WorldnetPayments_Gateway_for_WC {

    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;

    /**
     * @var Reference to logging class.
     */
    private static $log;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    public function __clone() {}

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    public function __wakeup() {}

    /**
     * Flag to indicate whether or not we need to load code for / support subscriptions.
     *
     * @var bool
     */
    private $subscription_support_enabled = false;

    /**
     * Flag to indicate whether or not we need to load support for pre-orders.
     *
     * @since 3.0.3
     *
     * @var bool
     */
    private $pre_order_enabled = false;

    /**
     * Flag to indicate whether or not Sentry is initialized
     *
     * @var bool
     */

    private $sentry_initialized = false;

    /**
     * Flag to indicate whether or not Curl is loaded
     *
     * @var bool
     */
    private static $has_curl;

        /**
     * Flag to indicate whether or not Mbstring is loaded
     *
     * @var bool
     */
    private static $has_mbstring;

    /**
     * Notices (array)
     * @var array
     */
    public $notices = array();

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct() {
        self::$has_curl = extension_loaded("curl");
        self::$has_mbstring = extension_loaded("mbstring");

        $this->terminal_settings = [];

        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        } );
        add_action( 'admin_init', array( $this, 'check_environment' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        add_action( 'plugins_loaded', array( $this, 'init' ), 0 );

        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'woocommerce_gateway_worldnetpayments_woocommerce_block_support' ) );
    }

    /**
     * Registers WooCommerce Blocks integration.
     *
     */
    public static function woocommerce_gateway_worldnetpayments_woocommerce_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Gateway_WorldnetPayments_Blocks_Support() );
                }
            );
        }
    }

    private function init_sentry() {
        if (!$this->sentry_initialized && self::$has_curl && self::$has_mbstring) {
            \Sentry\init([
                "dsn" => "https://8f90ff20254c49637082b49a43225ef3@o4505201678483456.ingest.us.sentry.io/4507531573854208",
                "traces_sample_rate" => 1.0,
                "profiles_sample_rate" => 1.0,
                'attach_stacktrace' => true,
                'before_send' => function (\Sentry\Event $event) {
                    $exceptions = $event->getExceptions();
                    $isPluginError = false;
            
                    if ($exceptions) {
                        foreach ($exceptions as $exception) {
                            $stacktrace = $exception->getStacktrace();
            
                            if ($stacktrace) {
                                $frames = $stacktrace->getFrames();
            
                                foreach ($frames as $frame) {
                                    if (is_object($frame) && get_class($frame) === 'Sentry\Frame') {
                                        // Use reflection to access Sentry 'Frame' private property
                                        $reflection = new ReflectionClass($frame);
                                        $property = $reflection->getProperty('absoluteFilePath');
                                        $property->setAccessible(true);
                                        $file = $property->getValue($frame);
                                    }
            
                                    // Check if the filename contains the merchant's plugin name
                                    if (strpos($file, "worldnetpayments-gateway-for-woocommerce") !== false) {
                                        $isPluginError = true;
                                        // Break out of the two foreach loops
                                        break 2;
                                    }
                                }
                            }
                        }
                    }

                    global $terminal_id;

                    $event->setTags([
                        'source' => 'backend',
                        'domain' => get_site_url(),
                        'plugin_version' => WorldnetPayments_Gateway_for_WC_VERSION,
                        'woocommerce_version' => WC_VERSION,
                        'wordpress_version' => get_bloginfo('version'),
                        'terminal_id' => $terminal_id,
                    ]);
            
                    // Return the event if it is a plugin error, otherwise return null
                    return $isPluginError ? $event : null;
                },
            ]);

            $this->sentry_initialized = true;
        }
    }

    /**
     * Init the plugin after plugins_loaded so environment variables are set.
     */
    public function init() {
        // Don't hook anything else in the plugin if we're in an incompatible environment
        if ( self::get_environment_warning() ) {
            return;
        }

        // Init the gateway itself
        $this->init_gateways();

        // Get terminal id based on used currency
        $this->get_terminal_id();

        // Init Sentry
        $this->init_sentry();

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
        add_action( 'wp_ajax_worldnetpayments_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );

        add_action( 'woocommerce_thankyou', array( $this, 'checkResponse'), 10, 1 );

        add_action('woocommerce_update_product', array( $this, 'woocommerce_worldnetpayments_stored_subscription_management'), 10, 2);



        add_filter( 'before_delete_post', array( $this, 'woocommerce_worldnetpayments_stored_subscription_delete'), 10, 2 );
        if(isset($_GET['action']) && $_GET['action'] === "duplicate_product") {
            add_filter( 'added_post_meta', array( $this, 'woocommerce_worldnetpayments_stored_subscription_duplicate'), 10, 4 );
        }
        if((isset($_GET['post_type']) && $_GET['post_type'] == "shop_subscription" && isset($_GET['action'])) ||
            (isset($_GET['change_subscription_to']) && $_GET['change_subscription_to'] == "cancelled"))
        {
            add_filter( 'save_post', array( $this, 'woocommerce_worldnetpayments_subscription_update'), 10, 4 );
        }

        // Add subscription pricing fields on edit product page
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woocommerce_worldnetpayments_product_options_general_product_data' ), 9, 0 );


		add_action( 'save_post',  array( $this, 'woocommerce_worldnetpayments_save_subscription_meta' ), 10 );

		add_action( 'rest_api_init', function () {
            add_action('http_api_curl', array( $this, 'custom_http_api_curl'), 10, 3);
            register_rest_route( 'worldnetpayments-gateway-for-woocommerce/v1', 'applepaysession', array(
                'methods' => 'GET',
                'callback' => array( $this, 'applepaysession_controller' ),
                'permission_callback' => '__return_true',
            ) );
        } );

		add_action( 'rest_api_init', function () {
            register_rest_route( 'worldnetpayments-gateway-for-woocommerce/v1', 'background-validation', array(
                'methods' => 'POST',
                'callback' => array( $this, 'background_validation' ),
                'permission_callback' => '__return_true',
            ) );
        } );
    }

    public function get_terminal_id() {
        $options = get_option("woocommerce_worldnetpayments_settings");

        $this->testmode                = 'yes' === $options['testmode'];
        $this->secret_key              = $this->testmode ? $options['test_secret_key'] : $options['live_secret_key'];
        $this->publishable_key         = $this->testmode ? $options['test_publishable_key'] : $options['live_publishable_key'];
        $this->currency         = $this->testmode ? $options['test_currency'] : $options['live_currency'];
        $this->secret_key2              = $this->testmode ? $options['test_secret_key2'] : $options['live_secret_key2'];
        $this->publishable_key2         = $this->testmode ? $options['test_publishable_key2'] : $options['live_publishable_key2'];
        $this->currency2         = $this->testmode ? $options['test_currency2'] : $options['live_currency2'];
        $this->secret_key3              = $this->testmode ? $options['test_secret_key3'] : $options['live_secret_key3'];
        $this->publishable_key3         = $this->testmode ? $options['test_publishable_key3'] : $options['live_publishable_key3'];
        $this->currency3         = $this->testmode ? $options['test_currency3'] : $options['live_currency3'];

        $currency = get_woocommerce_currency();
        global $terminal_id; 

        if($this->currency == $currency && $this->publishable_key && $this->secret_key) {
            $terminal_id = $this->publishable_key;
        } else if($this->currency2 == $currency && $this->publishable_key2 && $this->secret_key2) {
            $terminal_id = $this->publishable_key2;
        } else if($this->currency3 == $currency && $this->publishable_key3 && $this->secret_key3) {
            $terminal_id = $this->publishable_key3;
        }
    }

    function custom_http_api_curl($handle, $parsed_args = null, $url = ""){
        if ( $url === "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession" ) {
            curl_setopt($handle, CURLOPT_SSLCERTTYPE, "PEM");
            curl_setopt($handle, CURLOPT_SSLCERT, __DIR__ . "/certificates/applemid.pem");
            curl_setopt($handle, CURLOPT_SSLKEY, __DIR__ . "/certificates/applemid.key");
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($handle, CURLOPT_TIMEOUT, 15);
        }
    }

    function applepaysession_controller( WP_REST_Request $request ) {
        $options = get_option("woocommerce_worldnetpayments_settings");
        $env = "live_";
        if($options['testmode'] == "yes")
            $env = "test_";

        $validationURL = "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession";

        $data_string = json_encode([
            "merchantIdentifier" => $options[$env.'applepay_merchant_identifier'],
            "displayName"=> $options[$env.'applepay_display_name'],
            "initiative"=> "web",
            "initiativeContext"=> $options[$env.'applepay_initiative_context']
        ]);

        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Content-Length'] = strlen($data_string);

        $args = array(
            'body' => $data_string,
            'timeout' => '61',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array()
        );
        $result = wp_remote_post( $validationURL, $args );
        $result = json_decode($result['body']);

        return $result;
    }

    public function woocommerce_worldnetpayments_product_options_general_product_data() {
        if ( !class_exists( 'WC_Subscriptions_Product' ) || !class_exists( 'WC_Subscriptions_Admin' ) ) return;

        global $post;

        $chosen_price        = get_post_meta( $post->ID, '_subscription_price', true );
        $chosen_interval     = get_post_meta( $post->ID, '_subscription_period_interval', true );
        $period_count     = intval(get_post_meta( $post->ID, '_subscription_period_count', true ));
        $on_update     = str_replace(' ', '', get_post_meta( $post->ID, '_subscription_on_update', true ));
        $on_delete     = str_replace(' ', '', get_post_meta( $post->ID, '_subscription_on_delete', true ));

        $chosen_trial_length = WC_Subscriptions_Product::get_trial_length( $post->ID );
        $chosen_trial_period = WC_Subscriptions_Product::get_trial_period( $post->ID );

        $price_tooltip = __( 'Choose the subscription price, billing interval and period.', 'woocommerce-subscriptions' );
        // translators: placeholder is trial period validation message if passed an invalid value (e.g. "Trial period can not exceed 4 weeks")
        $trial_tooltip = sprintf( _x( 'An optional period of time to wait before charging the first recurring payment. Any sign up fee will still be charged at the outset of the subscription. %s', 'Trial period field tooltip on Edit Product administration screen', 'woocommerce-subscriptions' ), WC_Subscriptions_Admin::get_trial_period_validation_message() );

        // Set month as the default billing period
        $defaultPeriod = false;
        if ( ! $chosen_period = get_post_meta( $post->ID, '_subscription_period', true ) ) {
            $chosen_period = 'month';
            $defaultPeriod = true;
        }

        echo '<div class="options_group subscription_pricing show_if_subscription hidden">';

        $subscription_period_interval_strings = ["1" => "every"];
        $subscription_period_strings = [
                "weekly" => "week",
                "fortnightly" => "fortnight",
                "monthly" => "month",
                "quarterly" => "quarter",
                "yearly" => "year",
        ];


        // Subscription Price, Interval and Period
        ?><p class="form-field _subscription_price_fields _subscription_price_field">
        <label for="_subscription_price"><?php printf( esc_html__( 'Recurring Price (%s)', 'woocommerce-subscriptions' ), esc_html( get_woocommerce_currency_symbol() ) ); ?></label>
        <span class="wrap">
				<input type="text" id="_subscription_price" name="_subscription_price" class="wc_input_price wc_input_subscription_price" placeholder="<?php echo esc_attr_x( 'e.g. 5.90', 'example price', 'woocommerce-subscriptions' ); ?>" step="any" min="0" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
				<label for="_subscription_period_interval" class="wcs_hidden_label"><?php esc_html_e( 'Subscription interval', 'woocommerce-subscriptions' ); ?></label>
				<select id="_subscription_period_interval" name="_subscription_period_interval" class="wc_input_subscription_period_interval">
				<?php foreach ( $subscription_period_interval_strings as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $chosen_interval, true ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
				</select>
				<label for="_subscription_period" class="wcs_hidden_label"><?php esc_html_e( 'Subscription period', 'woocommerce-subscriptions' ); ?></label>
				<select id="_subscription_period" name="_subscription_period" class="wc_input_subscription_period last" <?php if(!$defaultPeriod) echo 'disabled="disabled"';?> >
				<?php foreach ( $subscription_period_strings as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $chosen_period, true ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
				</select>
			</span>
        <?php echo wcs_help_tip( $price_tooltip ); ?>
        </p>

        <p class="form-field">
            <label for="_subscription_period_count">Period Count</label>
            <select id="_subscription_period_count" name="_subscription_period_count" class="select short">
                <option value="0">0 (unlimited)</option>
                <?php for($i = 1; $i <= 48; $i++) { ?>
                <option value="<?php echo esc_attr($i);?>" <?php echo ($period_count === $i)?'selected="selected"':''; ?>><?php echo esc_attr($i); ?></option>
                <?php } ?>
            </select>
        </p>

        <?php

        // Subscription Length
       /* woocommerce_wp_select( array(
                'id'          => '_subscription_length',
                'class'       => 'wc_input_subscription_length select short',
                'label'       => __( 'Period Count', 'woocommerce-subscriptions' ),
                'options'     => $wcs_get_subscription_ranges,
                'value' => '0',
                'desc_tip'    => true,
                'description' => __( 'Automatically expire the subscription after this length of time.', 'woocommerce-subscriptions' ),
            )
        );*/

        // Sign-up Fee
        woocommerce_wp_text_input( array(
            'id'          => '_subscription_sign_up_fee',
            // Keep wc_input_subscription_intial_price for backward compatibility.
            'class'       => 'wc_input_subscription_intial_price wc_input_subscription_initial_price wc_input_price  short',
            // translators: %s is a currency symbol / code
            'label'       => sprintf( __( 'Setup Price (%s)', 'woocommerce-subscriptions' ), get_woocommerce_currency_symbol() ),
            'placeholder' => _x( 'e.g. 9.90', 'example price', 'woocommerce-subscriptions' ),
            'description' => __( 'Optionally include an amount to be charged at the outset of the subscription. The sign-up fee will be charged immediately, even if the product has a free trial or the payment dates are synced.', 'woocommerce-subscriptions' ),
            'desc_tip'    => true,
            'type'        => 'text',
            'data_type'   => 'price',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
            ),
        ) );

        ?>


        <p class="form-field">
            <label for="_subscription_on_update">On Update</label>
            <select id="_subscription_on_update" name="_subscription_on_update" class="select short">
                <option value="CONTINUE" <?php echo ($on_update === 'CONTINUE')?'selected="selected"':''; ?>>Continue Subscriptions</option>
                <option value="UPDATE" <?php echo ($on_update === 'UPDATE')?'selected="selected"':''; ?>>Update Subscriptions</option>
            </select>
        </p>


        <p class="form-field">
            <label for="_subscription_on_delete">On Delete</label>
            <select id="_subscription_on_delete" name="_subscription_on_delete" class="select short">
                <option value="CONTINUE" <?php echo ($on_delete === 'CONTINUE')?'selected="selected"':''; ?>>Continue Subscriptions</option>
                <option value="CANCEL" <?php echo ($on_delete === 'CANCEL')?'selected="selected"':''; ?>>Finish Subscriptions</option>
            </select>
        </p>

        <p class="form-field _subscription_trial_length_field hidden">
            <label for="_subscription_trial_length"><?php esc_html_e( 'Free trial', 'woocommerce-subscriptions' ); ?></label>
            <span class="wrap">
                    <input type="text" id="_subscription_trial_length" name="_subscription_trial_length" class="wc_input_subscription_trial_length" value="0" />
                    <label for="_subscription_trial_period" class="wcs_hidden_label"><?php esc_html_e( 'Subscription Trial Period', 'woocommerce-subscriptions' ); ?></label>
                    <select id="_subscription_trial_period" name="_subscription_trial_period" class="wc_input_subscription_trial_period last" >
                        <?php foreach ( wcs_get_available_time_periods() as $value => $label ) { ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $chosen_trial_period, true ) ?>><?php echo esc_html( $label ); ?></option>
                        <?php } ?>
                    </select>
                </span>
            <?php echo wcs_help_tip( $trial_tooltip ); ?>
        </p>
        <?php


        do_action( 'woocommerce_subscriptions_product_options_pricing' );

        wp_nonce_field( 'wcs_subscription_meta', '_wcsnonce' );

        echo '</div>';
        echo '<div class="show_if_subscription clear"></div>';

        remove_action( 'woocommerce_product_options_general_product_data', array('WC_Subscriptions_Admin', 'subscription_pricing_fields') );
    }

	/**
     * Save meta data for simple subscription product type when the "Edit Product" form is submitted.
     *
     * @param array Array of Product types & their labels, excluding the Subscription product type.
     * @return array Array of Product types & their labels, including the Subscription product type.
     * @since 1.0
     */
	public static function woocommerce_worldnetpayments_save_subscription_meta( $post_id ) {

        if ( empty( $_POST['_wcsnonce'] ) || ! wp_verify_nonce( $_POST['_wcsnonce'], 'wcs_subscription_meta' ) || !isset($_POST['product-type']) || $_POST['product-type'] !== 'subscription' || !class_exists( 'WC_Subscriptions_Admin' ) ) {
            return;
        }

        $subscription_price = isset( $_REQUEST['_subscription_price'] ) ? wc_format_decimal( wc_clean(wp_unslash($_REQUEST['_subscription_price'])) ) : '';
        $sale_price         = wc_format_decimal( wc_clean(wp_unslash($_REQUEST['_sale_price'])) );

        update_post_meta( $post_id, '_subscription_price', $subscription_price );

        // Set sale details - these are ignored by WC core for the subscription product type
        update_post_meta( $post_id, '_regular_price', $subscription_price );
        update_post_meta( $post_id, '_sale_price', $sale_price );

        $site_offset = get_option( 'gmt_offset' ) * 3600;

        // Save the timestamps in UTC time, the way WC does it.
        $date_from = ( ! empty( $_POST['_sale_price_dates_from'] ) ) ? wcs_date_to_time( $_POST['_sale_price_dates_from'] ) - $site_offset : '';
        $date_to   = ( ! empty( $_POST['_sale_price_dates_to'] ) ) ? wcs_date_to_time( $_POST['_sale_price_dates_to'] ) - $site_offset : '';

        $now = gmdate( 'U' );

        if ( ! empty( $date_to ) && empty( $date_from ) ) {
            $date_from = $now;
        }

        update_post_meta( $post_id, '_sale_price_dates_from', $date_from );
        update_post_meta( $post_id, '_sale_price_dates_to', $date_to );

        // Update price if on sale
        if ( '' !== $sale_price && ( ( empty( $date_to ) && empty( $date_from ) ) || ( $date_from < $now && ( empty( $date_to ) || $date_to > $now ) ) ) ) {
            $price = $sale_price;
        } else {
            $price = $subscription_price;
        }

        update_post_meta( $post_id, '_price', stripslashes( $price ) );

        // Make sure trial period is within allowable range
        $subscription_ranges = wcs_get_subscription_ranges();

        $max_trial_length = count( $subscription_ranges[ $_POST['_subscription_trial_period'] ] ) - 1;

        $_POST['_subscription_trial_length'] = absint( $_POST['_subscription_trial_length'] );

        if ( wc_clean(wp_unslash($_POST['_subscription_trial_length'])) > $max_trial_length ) {
            $_POST['_subscription_trial_length'] = $max_trial_length;
        }

        update_post_meta( $post_id, '_subscription_trial_length', wc_clean(wp_unslash($_POST['_subscription_trial_length'])) );

        update_post_meta( $post_id, '_subscription_length', wc_clean(wp_unslash($_POST['_subscription_period_count'])));
        update_post_meta( $post_id, '_subscription_period_count', wc_clean(wp_unslash($_POST['_subscription_period_count'])) );
        update_post_meta( $post_id, '_subscription_on_update', wc_clean(wp_unslash($_POST['_subscription_on_update'])) );
        update_post_meta( $post_id, '_subscription_on_delete', wc_clean(wp_unslash($_POST['_subscription_on_delete'])) );


        $_REQUEST['_subscription_sign_up_fee']       = wc_format_decimal( wc_clean(wp_unslash($_REQUEST['_subscription_sign_up_fee'])) );
        $_REQUEST['_subscription_one_time_shipping'] = isset( $_REQUEST['_subscription_one_time_shipping'] ) ? 'yes' : 'no';

        $subscription_fields = array(
            '_subscription_sign_up_fee',
            '_subscription_period',
            '_subscription_period_interval',
            '_subscription_length',
            '_subscription_trial_period',
            '_subscription_limit',
            '_subscription_one_time_shipping',
        );

        foreach ( $subscription_fields as $field_name ) {
            if ( isset( $_REQUEST[ $field_name ] ) ) {
                update_post_meta( $post_id, $field_name, wc_clean(wp_unslash( $_REQUEST[ $field_name ] )) );
            }
        }

        remove_action( 'save_post', array('WC_Subscriptions_Admin', 'save_subscription_meta') );
    }

    function woocommerce_worldnetpayments_stored_subscription_delete( $postid, $post ) {
        $post_meta = get_post_meta( $post->ID );

        $options = get_option("woocommerce_worldnetpayments_settings");

        $env = "live_";
        if($options['testmode'] == "yes")
            $env = "test_";

        $serverUrl = $options['testmode'] == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';

        if(isset($post->post_type) && $post->post_type == 'shop_subscription') { //customer subscription
            $orderPostId = $post->post_parent;

            $merchantRef = array_pop(get_post_meta( $orderPostId, 'subscriptionMerchantRef'));
            $terminalId = array_pop(get_post_meta( $orderPostId, 'terminalId'));

            if(!$merchantRef || !$terminalId) return;

            if($terminalId == $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $secret = $options[$env.'secret_key'];
            } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $secret = $options[$env.'secret_key2'];
            }  else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $secret = $options[$env.'secret_key3'];
            }


            $XmlSubscriptionDelRequest = new WorldnetPaymentsXmlSubscriptionDelRequest($merchantRef, $terminalId);
            $response = $XmlSubscriptionDelRequest->ProcessRequestToGateway($secret, $serverUrl);
        } else {
            if(!isset($post_meta['_subscription_price'])) return;

            $merchantRef = array_pop(get_post_meta( $post->ID, 'merchantRef'));
            $terminalId = array_pop(get_post_meta( $post->ID, 'terminalId'));

            if($terminalId == $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $secret = $options[$env.'secret_key'];
            } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $secret = $options[$env.'secret_key2'];
            }  else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $secret = $options[$env.'secret_key3'];
            }


            $XmlStoredSubscriptionDelRequest = new WorldnetPaymentsXmlStoredSubscriptionDelRequest($merchantRef, $terminalId);
            $response = $XmlStoredSubscriptionDelRequest->ProcessRequestToGateway($secret, $serverUrl);
        }

        if (isset($response) && $response->IsError()) {
            wp_die($response->ErrorString());
        } else {
            return;
        }
    }

    function woocommerce_worldnetpayments_stored_subscription_duplicate( $meta_id, $post_id, $meta_key, $meta_value ) {
	    if($meta_key === "merchantRef") {
            update_post_meta( $post_id, 'merchantRef', null );
        }
	    return null;
    }

    function woocommerce_worldnetpayments_subscription_update( $post_id, $post ) {
	    if(isset($_GET['action']) && $_GET['action'] != 'cancelled' && isset($_GET['change_subscription_to']) && $_GET['change_subscription_to'] != "cancelled") return;

        $options = get_option("woocommerce_worldnetpayments_settings");

        $env = "live_";
        if($options['testmode'] == "yes")
            $env = "test_";

        $serverUrl = $options['testmode'] == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';

        if(isset($post->post_type) && $post->post_type == 'shop_subscription') {
            $orderPostId = $post->post_parent;

            $merchantRefs = get_post_meta( $orderPostId, 'subscriptionMerchantRef');
            $merchantRef = array_pop($merchantRefs);
            $terminalIds = get_post_meta( $orderPostId, 'terminalId');
            $terminalId = array_pop($terminalIds);

            if($terminalId == $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $secret = $options[$env.'secret_key'];
            } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $secret = $options[$env.'secret_key2'];
            }  else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $secret = $options[$env.'secret_key3'];
            }

            if((isset($_GET['action']) && $_GET['action'] == 'cancelled') || (isset($_GET['change_subscription_to']) && $_GET['change_subscription_to'] == "cancelled")) {
                $XmlSubscriptionCancelRequest = new WorldnetPaymentsXmlSubscriptionCancelRequest($merchantRef, $terminalId);
                $response = $XmlSubscriptionCancelRequest->ProcessRequestToGateway($secret, $serverUrl);
            }

            if ($response->IsError()) {
                wp_die($response->ErrorString());
            } else {
                return;
            }
        }
    }

    private function floatvalue($val){
        $val = str_replace(",",".",$val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        return floatval($val);
    }

    public function woocommerce_worldnetpayments_stored_subscription_management( $product_id, $product ) {
        if(!isset($_POST['product-type']) || wc_clean(wp_unslash($_POST['product-type'])) !== 'subscription') return;

        $merchantRef = $product->get_meta("merchantRef");

        $options = get_option("woocommerce_worldnetpayments_settings");
        $currency = get_option('woocommerce_currency');

        $env = "live_";
        if($options['testmode'] == "yes")
            $env = "test_";

        $serverUrl = $options['testmode'] == 'yes' ?'https://testpayments.worldnettps.com/merchant/xmlpayment':'https://payments.worldnettps.com/merchant/xmlpayment';

        $name = wc_clean(wp_unslash($_POST['post_title']));
        $description = wc_clean(wp_unslash($_POST['content']));
        $periodType = isset($_POST['_subscription_period']) ? wc_clean(wp_unslash(strtoupper($_POST['_subscription_period']))) : '';
        $length = wc_clean(wp_unslash($_POST['_subscription_period_count']));
        $recurringAmount = number_format(wc_clean(wp_unslash($this->floatvalue($_POST['_subscription_price']))), 2, '.', '');
        $initialAmount = number_format(wc_clean(wp_unslash($this->floatvalue($_POST['_subscription_sign_up_fee']))), 2, '.', '');
        $type = "AUTOMATIC";
        $onUpdate = wc_clean(wp_unslash($_POST['_subscription_on_update']));
        $onDelete = wc_clean(wp_unslash($_POST['_subscription_on_delete']));

        if($description === '') $description = 'none';

        if($merchantRef) {

            $terminalId = $product->get_meta("terminalId");

            if($terminalId == $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $secret = $options[$env.'secret_key'];
            } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $secret = $options[$env.'secret_key2'];
            }  else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $secret = $options[$env.'secret_key3'];
            }

            $XmlStoredSubscriptionUpdRequest = new WorldnetPaymentsXmlStoredSubscriptionUpdRequest($merchantRef,
                    $terminalId,
                    $name,
                    $description,
                    $length,
                    $currency,
                    $recurringAmount,
                    $initialAmount,
                    $type,
                    $onUpdate,
                    $onDelete);

            $response = $XmlStoredSubscriptionUpdRequest->ProcessRequestToGateway($secret, $serverUrl);

        } else {
            if($periodType == "") $periodType = strtoupper($product->get_meta("_subscription_period"));

            if($currency == $options[$env.'currency'] && $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $terminalId = $options[$env.'publishable_key'];
                $secret = $options[$env.'secret_key'];
            } else if($currency == $options[$env.'currency2'] && $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $terminalId = $options[$env.'publishable_key2'];
                $secret = $options[$env.'secret_key2'];
            }  else if($currency == $options[$env.'currency3'] && $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $terminalId = $options[$env.'publishable_key3'];
                $secret = $options[$env.'secret_key3'];
            } else if ($options[$env.'multicurrency'] == 'yes') {
                $terminalId = $options[$env.'publishable_key'];
                $secret = $options[$env.'secret_key'];
            } else if ($options[$env.'multicurrency2'] == 'yes') {
                $terminalId = $options[$env.'publishable_key2'];
                $secret = $options[$env.'secret_key2'];
            } else if ($options[$env.'multicurrency3'] == 'yes') {
                $terminalId = $options[$env.'publishable_key3'];
                $secret = $options[$env.'secret_key3'];
            }

            $merchantRef = 'MREF_wc-' . md5($terminalId . $secret . $name . $description . date('U'));

            $XmlStoredSubscriptionRegRequest = new WorldnetPaymentsXmlStoredSubscriptionRegRequest($merchantRef,
                    $terminalId,
                    $name,
                    $description,
                    $periodType,
                    $length,
                    $currency,
                    $recurringAmount,
                    $initialAmount,
                    $type,
                    $onUpdate,
                    $onDelete);

            $response = $XmlStoredSubscriptionRegRequest->ProcessRequestToGateway($secret, $serverUrl);
        }

        if ($response->IsError()) {
            $worldnetpaymentsResponse = 'AN ERROR OCCURED! Error details: ' . $response->ErrorString();

            wp_die($worldnetpaymentsResponse);
        } else {
            $product->update_meta_data( 'merchantRef', $merchantRef);
            $product->update_meta_data( 'terminalId', $terminalId);

            remove_action('woocommerce_update_product', array( $this, 'woocommerce_worldnetpayments_stored_subscription_management'), 10, 2);
            $product->save();
            add_action('woocommerce_update_product', array( $this, 'woocommerce_worldnetpayments_stored_subscription_management'), 10, 2);
        }
    }

    public function checkResponse() {
        if (isset($_GET['ORDERID']) && wc_clean(wp_unslash($_GET['ORDERID'])) && isset($_GET['HASH']) && wc_clean(wp_unslash($_GET['HASH']))) {
            $options = get_option("woocommerce_worldnetpayments_settings");

            $env = "live_";
            if($options['testmode'] == "yes")
                $env = "test_";

            $terminalId = wc_clean(wp_unslash($_GET['TERMINALID']));
            if($terminalId == $options[$env.'publishable_key'] && $options[$env.'publishable_key'] && $options[$env.'secret_key']) {
                $terminalId = $options[$env.'publishable_key'];
                $secret = $options[$env.'secret_key'];
                $currency = $options[$env.'currency'];
                $multicurrency = $options[$env.'multicurrency'];
            } else if($terminalId == $options[$env.'publishable_key2'] && $options[$env.'publishable_key2'] && $options[$env.'secret_key2']) {
                $terminalId = $options[$env.'publishable_key2'];
                $secret = $options[$env.'secret_key2'];
                $currency = $options[$env.'currency2'];
                $multicurrency = $options[$env.'multicurrency2'];
            } else if($terminalId == $options[$env.'publishable_key3'] && $options[$env.'publishable_key3'] && $options[$env.'secret_key3']) {
                $terminalId = $options[$env.'publishable_key3'];
                $secret = $options[$env.'secret_key3'];
                $currency = $options[$env.'currency3'];
                $multicurrency = $options[$env.'multicurrency3'];
            }

            $expectedHash = md5($terminalId . $_GET['ORDERID'] . ($multicurrency=='yes' ? $currency : '') . $_GET['AMOUNT'] . $_GET['DATETIME'] . $_GET['RESPONSECODE'] . $_GET['RESPONSETEXT'] . $secret );

            $securecardStored = false;
            if (isset($_GET['SECURECARDMERCHANTREF']) && wc_clean(wp_unslash($_GET['SECURECARDMERCHANTREF']))
                && isset($_GET['ISSTORED']) && wc_clean(wp_unslash($_GET['ISSTORED'])) == true
                && isset($_GET['CARDTYPE']) && wc_clean(wp_unslash($_GET['CARDTYPE']))
                && isset($_GET['CARDNUMBER']) && wc_clean(wp_unslash($_GET['CARDNUMBER']))
                && isset($_GET['CARDREFERENCE']) && wc_clean(wp_unslash($_GET['CARDREFERENCE']))
                && isset($_GET['CARDEXPIRY']) && wc_clean(wp_unslash($_GET['CARDEXPIRY']))
                && isset($_GET['MERCHANTREF']) && wc_clean(wp_unslash($_GET['MERCHANTREF']))
                && wc_clean(wp_unslash($_GET['SECURECARDMERCHANTREF'])) == wc_clean(wp_unslash($_GET['MERCHANTREF']))
            ) {
                $securecardStored = true;
                $expectedHash = md5($terminalId . $_GET['ORDERID'] . ($multicurrency=='yes' ? $currency : '') . $_GET['AMOUNT'] . $_GET['DATETIME'] . $_GET['RESPONSECODE'] . $_GET['RESPONSETEXT'] . $secret
                                    . $_GET['MERCHANTREF'] . $_GET['CARDREFERENCE'] . $_GET['CARDTYPE'] . $_GET['CARDNUMBER'] . $_GET['CARDEXPIRY'] );
            }

            if (sanitize_text_field($_GET['RESPONSECODE']) == 'A' && hash_equals($expectedHash, $_GET['HASH'])) {
                $order_id = wc_get_order_id_by_order_key(urldecode($_GET['key']));
                $order = wc_get_order($order_id);
                $orderStatus = $order->get_status();
                $orderStatus = 'wc-' === substr( $orderStatus, 0, 3 ) ? substr( $orderStatus, 3 ) : $orderStatus;

                if($orderStatus == 'pending' || $orderStatus == 'failed') {
                    if ($securecardStored) {
                        $cardNumber = wc_clean(wp_unslash($_GET['CARDNUMBER']));
                        $cardExpiry = wc_clean(wp_unslash($_GET['CARDEXPIRY']));
                        $last4 = substr($cardNumber, -4);
                        $month = substr($cardExpiry, 0, 2);
                        $year = '20' . substr($cardExpiry, -2);

                        $wc_token = new WC_WorldnetPayments_Payment_Token_CC();
                        $wc_token->set_token( wc_clean(wp_unslash($_GET['CARDREFERENCE'])) );
                        $wc_token->set_gateway_id( 'worldnetpayments' );
                        $wc_token->set_card_type( strtolower( wc_clean(wp_unslash($_GET['CARDTYPE'])) ) );
                        $wc_token->set_last4( $last4 );
                        $wc_token->set_expiry_month( $month );
                        $wc_token->set_expiry_year( $year );
                        $wc_token->set_merchant_ref( wc_clean(wp_unslash($_GET['SECURECARDMERCHANTREF'])) );
                        $wc_token->set_terminal_id( wc_clean(wp_unslash($_GET['TERMINALID'])) );

                        $wc_token->set_user_id( get_current_user_id() );
                        $wc_token->save();
                    }

                    $order->update_status(apply_filters('woocommerce_order_status_pending_to_processing_notification', 'processing', $order), __('Payment processed correctly #' . sanitize_text_field($_GET['UNIQUEREF']), 'woocommerce'));

                    $order->set_transaction_id( sanitize_text_field($_GET['UNIQUEREF']) );

                    $order->save();

                    WC()->mailer()->customer_invoice($order);

                    wc_reduce_stock_levels( $order_id );

                    // Remove cart
                    WC()->cart->empty_cart();

                    // Redirect merchant to receipt page
                    $receiptPageUrl = $order->get_checkout_order_received_url();
                    wp_redirect($receiptPageUrl);
                }
            } else {
                $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

                $order_id = wc_get_order_id_by_order_key(urldecode($_GET['key']));
                $order = wc_get_order($order_id);
                $orderStatus = $order->get_status();
                $orderStatus = 'wc-' === substr( $orderStatus, 0, 3 ) ? substr( $orderStatus, 3 ) : $orderStatus;


                if($orderStatus == 'pending') {
                    $order->update_status(apply_filters('woocommerce_gateway_worldnetpayments_process_payment_order_status', 'failed', $order), __('Payment failed #' . sanitize_text_field($_GET['UNIQUEREF']), 'woocommerce'));

                    // Remove cart
                    WC()->cart->empty_cart();

                    wp_redirect($currentUrl);
                }
            }
        }
    }

    public function background_validation()
    {
        $options = get_option("woocommerce_worldnetpayments_settings");
        $background_validation = isset($options['background_validation'])?$options['background_validation']:false;

        if(isset($_POST['UNIQUEREF'])) {
            if ( $background_validation == "yes" ) {
                $terminalId = wc_clean(wp_unslash($_POST['TERMINALID']));
                $order_id = wc_clean(wp_unslash($_POST['ORDERID']));
                $amount = wc_clean(wp_unslash($_POST['AMOUNT']));
                $dateTime = wc_clean(wp_unslash($_POST['DATETIME']));
                $responseCode = wc_clean(wp_unslash($_POST['RESPONSECODE']));
                $responseText = wc_clean(wp_unslash($_POST['RESPONSETEXT']));
                $hash = wc_clean(wp_unslash($_POST['HASH']));

                $env = "live_";
                if($options['testmode'] == "yes")
                    $env = "test_";

                if($terminalId == $options[$env.'publishable_key2']) {
                    $secret = $options[$env.'secret_key2'];
                    $currency = $options[$env.'currency2'];
                    $multicurrency = $options[$env.'multicurrency2'];
                } else if($terminalId == $options[$env.'publishable_key3']) {
                    $secret = $options[$env.'secret_key3'];
                    $currency = $options[$env.'currency3'];
                    $multicurrency = $options[$env.'multicurrency3'];
                } else {
                    $secret = $options[$env.'secret_key'];
                    $currency = $options[$env.'currency'];
                    $multicurrency = $options[$env.'multicurrency'];
                }

                $expectedHash = md5($terminalId . $order_id . ($multicurrency=='yes' ? $currency : '') . $amount . $dateTime . $responseCode . $responseText . $secret);

                if($expectedHash == $hash) {
                    $uniqueref = wc_clean(wp_unslash($_POST['UNIQUEREF']));
                    $order_id = wc_clean(wp_unslash($_POST['ORDERID']));
                    $responseCode = wc_clean(wp_unslash($_POST['RESPONSECODE']));

                    $order = wc_get_order($order_id);
                    $orderStatus = $order->get_status();
                    $orderStatus = 'wc-' === substr($orderStatus, 0, 3) ? substr($orderStatus, 3) : $orderStatus;

                    if ($responseCode == 'A') {
                        if ($orderStatus == 'pending' || $orderStatus == 'failed') {
                            $order->update_status(apply_filters('woocommerce_gateway_worldnetpayments_process_payment_order_status', 'processing', $order), __('Payment processed correctly and Background Validation completed #' . $uniqueref, 'woocommerce'));

                            $order->set_transaction_id($uniqueref);

                            $order->save();
                        } else {
                            $order->add_order_note(__('Background Validation completed #' . $uniqueref, 'woocommerce'));
                        }
                    } else {
                        if ($orderStatus == 'pending') {
                            $order->update_status(apply_filters('woocommerce_gateway_worldnetpayments_process_payment_order_status', 'failed', $order), __('Payment failed #' . $uniqueref, 'woocommerce'));
                        } else {
                            $order->add_order_note(__('Background Validation completed #' . $uniqueref, 'woocommerce'));
                        }
                    }

                    echo 'OK';
                    exit;
                } else {
                    echo 'Incorrect HASH';
                    exit;
                }
            } else {
                echo 'Background Validation not enabled in Worldnet Payments plugin';
                exit;
            }
        }
    }

    public function send_new_order_email( $order_id ) {
        $emails = WC()->mailer()->get_emails();
        if ( ! empty( $emails ) && ! empty( $order_id ) ) {
            $emails['WC_Email_New_Order']->trigger( $order_id );
            $emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
        }
    }

    public function send_failed_order_email( $order_id ) {
        $emails = WC()->mailer()->get_emails();
        if ( ! empty( $emails ) && ! empty( $order_id ) ) {
            $emails['WC_Email_Failed_Order']->trigger( $order_id );
        }
    }



    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication)
     */
    public function add_admin_notice( $slug, $class, $message ) {
        $this->notices[ $slug ] = array(
            'class'   => $class,
            'message' => $message,
        );
    }

    /**
     * The backup sanity check, in case the plugin is activated in a weird way,
     * or the environment changes after activation. Also handles upgrade routines.
     */
    public function check_environment() {
        if ( ! defined( 'IFRAME_REQUEST' ) && ( WorldnetPayments_Gateway_for_WC_VERSION !== get_option( 'wc_worldnetpayments_version' ) ) ) {
            $this->install();

            do_action( 'woocommerce_worldnetpayments_updated' );
        }

        $environment_warning = self::get_environment_warning();

        if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            $this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
        }
    }

    /**
     * Updates the plugin version in db
     *
     * @since 3.1.0
     * @version 3.1.0
     * @return bool
     */
    private static function _update_plugin_version() {
        delete_option( 'wc_worldnetpayments_version' );
        update_option( 'wc_worldnetpayments_version', WorldnetPayments_Gateway_for_WC_VERSION );

        return true;
    }

    /**
     * Dismiss the Google Payment Request API Feature notice.
     *
     * @since 3.1.0
     * @version 3.1.0
     */
    public function dismiss_request_api_notice() {
        update_option( 'wc_worldnetpayments_show_request_api_notice', 'no' );
    }

    /**
     * Handles upgrade routines.
     *
     * @since 3.1.0
     * @version 3.1.0
     */
    public function install() {
        if ( ! defined( 'WorldnetPayments_Gateway_for_WC_INSTALLING' ) ) {
            define( 'WorldnetPayments_Gateway_for_WC_INSTALLING', true );
        }

        $this->_update_plugin_version();
    }

    /**
     * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
     * found or false if the environment has no problems.
     */
    static function get_environment_warning() {
        if ( version_compare( phpversion(), WorldnetPayments_Gateway_for_WC_MIN_PHP_VER, '<' ) ) {
            $message = __( 'WooCommerce WorldnetPayments - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-worldnetpayments' );

            return sprintf( $message, WorldnetPayments_Gateway_for_WC_MIN_PHP_VER, phpversion() );
        }

        if ( ! defined( 'WC_VERSION' ) ) {
            return __( 'WooCommerce WorldnetPayments requires WooCommerce to be activated to work.', 'woocommerce-gateway-worldnetpayments' );
        }

        if ( version_compare( WC_VERSION, WorldnetPayments_Gateway_for_WC_MIN_WC_VER, '<' ) ) {
            $message = __( 'WooCommerce WorldnetPayments - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-worldnetpayments' );

            return sprintf( $message, WorldnetPayments_Gateway_for_WC_MIN_WC_VER, WC_VERSION );
        }

        if ( ! function_exists( 'curl_init' ) ) {
            return __( 'WooCommerce WorldnetPayments - cURL is not installed.', 'woocommerce-gateway-worldnetpayments' );
        }

        if ( ! self::$has_curl || ! self::$has_mbstring) {
            $required_extension_message = "Please install the following server extensions to help us monitor plugin errors: ";
            
            if ( !self::$has_curl && !self::$has_mbstring ) {
                $required_extension_message = $required_extension_message . "Curl, Mbstring";
            }
        
            if ( !self::$has_mbstring && self::$has_curl ) {
                $required_extension_message = $required_extension_message . "Mbstring";
            }

            if ( !self::$has_curl && self::$has_mbstring ) {
                $required_extension_message = $required_extension_message . "Curl";
            }

            if ( strlen(trim($required_extension_message)) > 0 ) {
                return __( $required_extension_message, 'woocommerce-gateway-worldnetpayments' );
            }
        }

        return false;
    }

    /**
     * Adds plugin action links
     *
     * @since 1.0.0
     */
    public function plugin_action_links( $links ) {
        $setting_link = $this->get_setting_link();

        $plugin_links = array(
            '<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-worldnetpayments' ) . '</a>',
            '<a href="mailto:support@worldnettps.com">' . __( 'Support', 'woocommerce-gateway-worldnetpayments' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    /**
     * Get setting link.
     *
     * @since 1.0.0
     *
     * @return string Setting link
     */
    public function get_setting_link() {
        $use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

        $section_slug = $use_id_as_section ? 'worldnetpayments' : strtolower( 'WorldnetPayments_Gateway' );

        return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
    }

    /**
     * Display any notices we've collected thus far (e.g. for connection, disconnection)
     */
    public function admin_notices() {
        $show_request_api_notice = get_option( 'wc_worldnetpayments_show_request_api_notice' );

        if ( empty( $show_request_api_notice ) ) {
            // @TODO remove this notice in the future.
            ?>

            <div class="notice notice-warning wc-worldnetpayments-request-api-notice is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'Worldnet Payments requires Terminal Settings changes', 'woocommerce-gateway-worldnetpayments' ); ?></strong>
                </p>
                <p>
                    <?php echo sprintf( __( 'Please update the following in use fields in the Gateway Selfcare > Settings > Terminal form: MPI Receipt URL, Secure Token URL and Validation URL. The new values can be found on the <a href="%1$s">plugin settings</a> form.', 'woocommerce-gateway-worldnetpayments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=worldnetpayments' )); ?>
                </p>
            </div>
            <script type="application/javascript">
                jQuery( '.wc-worldnetpayments-request-api-notice' ).on( 'click', '.notice-dismiss', function() {
                    var data = {
                        action: 'worldnetpayments_dismiss_request_api_notice'
                    };

                    jQuery.post( '<?php echo esc_url_raw(admin_url( 'admin-ajax.php' )); ?>', data );
                });
            </script>

            <?php
        }

        foreach ( (array) $this->notices as $notice_key => $notice ) {
            echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
            echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
            echo '</p></div>';
        }
    }

    /**
     * Initialize the gateway. Called very early - in the context of the plugins_loaded action
     *
     * @since 1.0.0
     */
    public function init_gateways() {
        if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
            $this->subscription_support_enabled = true;
        }

        if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
            $this->pre_order_enabled = true;
        }

        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
            include_once( dirname( __FILE__ ) . '/includes/class-wc-worldnetpayments-blocks.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-worldnetpayments-gateway.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-wc-worldnetpayments-payment-token-cc.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-wc-worldnetpayments-payment-tokens.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-wc-worldnetpayments-payment-3ds.php' );
        }

        load_plugin_textdomain( 'woocommerce-gateway-worldnetpayments', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    }

    /**
     * Add the gateways to WooCommerce
     *
     * @since 1.0.0
     */
    public function add_gateways( $methods ) {
        $methods[] = 'WorldnetPayments_Gateway';

        return $methods;
    }

    /**
     * List of currencies supported by WorldnetPayments that has no decimals.
     *
     * @return array $currencies
     */
    public static function no_decimal_currencies() {
        return array(
            'bif', // Burundian Franc
            'djf', // Djiboutian Franc
            'jpy', // Japanese Yen
            'krw', // South Korean Won
            'pyg', // Paraguayan Guaraní
            'vnd', // Vietnamese Đồng
            'xaf', // Central African Cfa Franc
            'xpf', // Cfp Franc
            'clp', // Chilean Peso
            'gnf', // Guinean Franc
            'kmf', // Comorian Franc
            'mga', // Malagasy Ariary
            'rwf', // Rwandan Franc
            'vuv', // Vanuatu Vatu
            'xof', // West African Cfa Franc
        );
    }

    /**
     * WorldnetPayments uses smallest denomination in currencies such as cents.
     * We need to format the returned currency from WorldnetPayments into human readable form.
     *
     * @param object $balance_transaction
     * @param string $type Type of number to format
     */
    public static function format_number( $balance_transaction, $type = 'fee' ) {
        if ( ! is_object( $balance_transaction ) ) {
            return;
        }

        if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
            if ( 'fee' === $type ) {
                return $balance_transaction->fee;
            }

            return $balance_transaction->net;
        }

        if ( 'fee' === $type ) {
            return number_format( $balance_transaction->fee / 100, 2, '.', '' );
        }

        return number_format( $balance_transaction->net / 100, 2, '.', '' );
    }

    public static function log( $message ) {
        if ( empty( self::$log ) ) {
            self::$log = new WC_Logger();
        }

        self::$log->add( 'woocommerce-gateway-worldnetpayments', $message );
    }
}

function extend_wcs_get_subscription_period_strings( $translated_periods ) {

    $translated_periods['weekly'] = sprintf( _nx( 'week',  '%s weekly',  1, 'Subscription billing period.', 'woocommerce-subscriptions' ), 1 );
    $translated_periods['fortnightly'] = sprintf( _nx( 'fortnight',  '%s fortnightly',  1, 'Subscription billing period.', 'woocommerce-subscriptions' ), 1 );
    $translated_periods['monthly'] = sprintf( _nx( 'month',  '%s monthly',  1, 'Subscription billing period.', 'woocommerce-subscriptions' ), 1 );
    $translated_periods['quarterly'] = sprintf( _nx( 'quarter',  '%s quarterly',  1, 'Subscription billing period.', 'woocommerce-subscriptions' ), 1 );
    $translated_periods['yearly'] = sprintf( _nx( 'year',  '%s yearly',  1, 'Subscription billing period.', 'woocommerce-subscriptions' ), 1 );

    return $translated_periods;
}
add_filter( 'woocommerce_subscription_periods', 'extend_wcs_get_subscription_period_strings' );

function extend_wcs_get_subscription_ranges( $subscription_ranges ) {

    $subscription_ranges['weekly'] = _x( '1 week', 'Subscription lengths. e.g. "For 1 week..."', 'woocommerce-subscriptions' );
    $subscription_ranges['fortnightly'] = _x( '1 fortnight', 'Subscription lengths. e.g. "For 1 fortnight..."', 'woocommerce-subscriptions' );
    $subscription_ranges['monthly'] = _x( '1 month', 'Subscription lengths. e.g. "For 1 month..."', 'woocommerce-subscriptions' );
    $subscription_ranges['quarterly'] = _x( '1 quarter', 'Subscription lengths. e.g. "For 1 quarter..."', 'woocommerce-subscriptions' );
    $subscription_ranges['yearly'] = _x( '1 year', 'Subscription lengths. e.g. "For 1 year..."', 'woocommerce-subscriptions' );

    return $subscription_ranges;
}
add_filter( 'woocommerce_subscription_lengths', 'extend_wcs_get_subscription_ranges' );

$GLOBALS['wc_worldnetpayments'] = WorldnetPayments_Gateway_for_WC::get_instance();
