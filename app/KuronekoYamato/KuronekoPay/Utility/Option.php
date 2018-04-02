<?php

namespace KuronekoYamato\KuronekoPay\Utility;

use Hametuha\HametWoo\Pattern\Singleton;

/**
 * Option utility
 *
 * @package KuronekoPay
 * @since 1.0.0
 * @property-read bool    $enabled
 * @property-read bool    $sandbox
 * @property-read bool    $hidden
 * @property-read string  $trader_code
 * @property-read string  $access_key
 */
class Option extends Singleton {

	/**
	 * Get option
	 *
	 * @return object
	 */
	protected function get_options() {
		return (object) get_option( 'woocommerce_kuroneko_cc_settings', [
			'enabled'     => 'no',
			'sandbox'     => 'yes',
			'hidden'      => 'no',
			'trader_code' => '',
		    'access_key' => '',
		] );
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'enabled':
			case 'sandbox':
			case 'hidden':
				return 'yes' == $this->get_options()->{$name};
				break;
			case 'trader_code':
			case 'access_key':
				return $this->get_options()->{$name};
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}

}
