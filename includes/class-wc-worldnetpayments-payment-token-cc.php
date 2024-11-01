<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_WorldnetPayments_Payment_Token_CC extends WC_Payment_Token_CC {

	protected $extra_data = array(
		'merchant_ref'        => '',
		'terminal_id'        => '',
		'last4'        => '',
		'expiry_year'  => '',
		'expiry_month' => '',
		'card_type'    => '',
	);


    public function get_merchant_ref( $context = 'view' ) {
        return $this->get_prop( 'merchant_ref', $context );
    }
    public function set_merchant_ref( $merchant_ref ) {
        $this->set_prop( 'merchant_ref', $merchant_ref );
    }


    public function get_terminal_id( $context = 'view' ) {
        return $this->get_prop( 'terminal_id', $context );
    }
    public function set_terminal_id( $terminal_id ) {
        $this->set_prop( 'terminal_id', $terminal_id );
    }
}
