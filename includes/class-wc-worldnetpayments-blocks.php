<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WorldnetPayments Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_WorldnetPayments_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_WorldnetPayments
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'worldnetpayments';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_worldnetpayments_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = plugin_dir_path( __FILE__ ) . '/assets/js/frontend/blocks.asset.php';

		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        =  untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/..' . $script_path;

		wp_register_script(
			'wc-worldnetpayments-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-worldnetpayments-payments-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
        @session_start();
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            if (empty($_SESSION['CSRF-Token'])) {
                $_SESSION['CSRF-Token'] = bin2hex(random_bytes(32));
            }
        } elseif (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            if (empty($_SESSION['CSRF-Token'])) {
                if (function_exists('mcrypt_create_iv')) {
                    $_SESSION['CSRF-Token'] = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
                } else {
                    $_SESSION['CSRF-Token'] = bin2hex(openssl_random_pseudo_bytes(32));
                }
            }
        }

        $orderHasSubscription = false;

		if (!defined('WC_VERSION') || !isset(WC()->cart)) return;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( class_exists( 'WC_Subscriptions_Product' ) &&  WC_Subscriptions_Product::is_subscription( $item['product_id'] )) {
				$orderHasSubscription = true;
			}
		}
		
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'csrfToken' => $_SESSION['CSRF-Token'],
            'integrationType' => $this->get_setting( 'worldnetpayments_integration_type' ),
            'secureTokensEnabled' => 'yes' === $this->get_setting( 'securecard' ),
            'applepayData' => (object) [
                "enabled" => ($this->get_setting( 'applepay_active' ) === "yes" && !$orderHasSubscription),
                "controller" => get_site_url(null, '?rest_route=/worldnetpayments-gateway-for-woocommerce/v1/applepaysession', 'https'),
                "countryCode" => WC()->countries->get_base_country(),
                "currencyCode" => get_option('woocommerce_currency'),
                "total" => (object) [
                    "label" => 'with Worldnet Payments Payment Gateway',
                    "amount" =>  number_format((float) WC()->cart->total, 2, '.', '')
                ],
                //TODO: use terminal features values
                "merchantCapabilities" => ['supports3DS'],
                "supportedNetworks" => ["amex", "discover", "electron", "jcb", "maestro", "masterCard", "visa"],
            ]
		];
	}
}
