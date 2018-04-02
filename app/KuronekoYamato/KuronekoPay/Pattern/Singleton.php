<?php

namespace KuronekoYamato\KuronekoPay\Pattern;

use KuronekoYamato\KuronekoPay\Utility\Option;


/**
 * Singleton pattern
 *
 * @package KuronekoPay
 * @property-read Option $option
 */
abstract class Singleton extends \Hametuha\HametWoo\Pattern\Singleton {

	/**
	 * @var string Kuroneko Web Collect version
	 */
	protected $version = KURONEKO_PAY_VERSION;

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'option':
				return Option::get_instance();
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}
}
