<?php

namespace KuronekoYamato\KuronekoPay\UI;


use KuronekoYamato\KuronekoPay\Master\CvsInfo;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;

/**
 * OrderReview customizer
 *
 * @package KuronekoPay
 */
class OrderReview extends Singleton {

	/**
	 * Constructor
	 */
	protected function __construct() {
		// Add payment fee
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'calculate_fee' ] );
	}

	/**
	 * Add payment fee
	 *
	 * @param $cart
	 */
	public function calculate_fee( $cart ) {
		$current_gateway    = WC()->session->chosen_payment_method;
		if ( ! $current_gateway ) {
			// Get default.
			if ( $available_gateways = WC()->payment_gateways->get_available_payment_gateways() ) {
				$current_gateway = current( $available_gateways );
			}
		}
		switch ( $current_gateway ) {
			case CvsInfo::ID:
				// Add CVS fee
				$fee = CvsInfo::get_current_fee();
				if ( $fee ) {
					WC()->cart->add_fee( __( 'Payment Fee', 'kuroneko' ), $fee, true );
				}
				break;
			default:
				// Do nothing.
				break;
		}
	}
}
