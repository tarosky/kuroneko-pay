<?php
namespace KuronekoYamato\KuronekoPay\GateWay;


use Hametuha\HametWoo\Utility\Compatibility;
use Hametuha\HametWoo\Utility\Tools;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Master\Brands;
use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Member;
use KuronekoYamato\KuronekoPay\Utility\GatewayFieldGroup;

/**
 * Pay.JP PAYMENT GATEWAY
 *
 * @package Hametuha\WooCommerce\Service\Webpay
 * @property-read Member $member
 * @property-read OrderHelper $helper
 * @property-read bool        $has_access_key
 * @property-read \KuronekoYamato\KuronekoPay\API\CreditCard $api
 */
class CreditCard extends AbstractCreditCard {

	public $id = 'kuroneko_cc';

	/**
	 * Getter for credit card api
	 *
	 * @return \KuronekoYamato\KuronekoPay\API\CreditCard
	 */
	protected function get_api() {
		return \KuronekoYamato\KuronekoPay\API\CreditCard::get_instance();
	}

	/**
	 * Get default value
	 */
	protected function set_default_values() {
		parent::set_default_values();
		$this->method_title = __( 'Credit Card (Domestic)', 'kuroneko' );
		$this->method_description = __( 'Credit card gateway of Kuroneko Web Collect. Only available for billing to Japan.', 'kuroneko' );
	}

	/**
	 * If international, O.K.
	 *
	 * @param \WC_Customer $customer
	 *
	 * @return bool
	 */
	protected function is_available_for( $customer ) {
		return ! $this->helper->is_international( $customer );
	}
}
