<?php

namespace KuronekoYamato\KuronekoPay\Utility;

/**
 * Class GatewayFieldGroup
 *
 * @package kuronekopay
 * @property string $limit_days
 */
trait GatewayFieldGroup {

	protected $kuroneko_version = KURONEKO_PAY_VERSION;

	public $sandbox = 'yes';

	public $hidden = 'no';

	public $trader_code = '';

	protected $_title = '';

	protected $_description = '';

	/**
	 * Return common fields.
	 *
	 * @return array
	 */
	protected function common_fields() {
		return [
			'enabled' => [
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				// translators: %s is gateway name.
				'label'       => sprintf( __( 'Enable "%s".', 'kuroneko' ), $this->_title ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable credit card payment gateway.', 'kuroneko' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'title' => [
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default' => $this->_title,
				'desc_tip' => true,
			],
			'description' => [
				'title' => __( 'Description', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
				'default' => $this->_description,
				'desc_tip' => true,
			],
			'sandbox' => [
				'title' => __( 'Sandbox', 'woocommerce' ),
				'label' => __( 'Enable Sandbox Mode', 'kuroneko' ),
				'type' => 'checkbox',
				'description' => __( 'On sandbox mode, real payments will not be taken. Enable this on test environment.', 'kuroneko' ),
				'default' => 'yes',
			],
			'hidden' => [
				'title' => __( 'Visibility', 'kuroneko' ),
				'label' => __( 'Hidden on checkout form', 'kuroneko' ),
				'type' => 'checkbox',
				'description' => __( 'If checked, your customer cannot choose this payment method. Useful on changing gateway on production environment.', 'kuroneko' ),
				'default' => 'no',
			],
			'trader_code' => [
				'title' => __( 'Trader Code', 'kuroneko' ),
				'type' => 'text',
				'description' => __( '9 digits on contract sheet with WEB Collect.', 'kuroneko' ),
				'default' => '',
				'desc_tip' => true,
			],
		];
	}

	/**
	 * Get limit time from current time.
	 *
	 * @param string $time
	 *
	 * @return string
	 */
	protected function get_limit_date( $time = '' ){
		if ( ! $time ) {
			$time = current_time( 'mysql' );
		}
		$date = new \DateTime( $time );
		$date->add( new \DateInterval('P' . $this->limit_days . 'D') );
		return $date->format( 'Y-m-d 23:59:59' );
	}

}