<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * Card brands
 *
 * @package KuronekoPay
 */
class Brands {

	/**
	 * All card brands
	 *
	 * @return array
	 */
	public static function get_brand( $is_international = false ) {
		$cards =  [
//			'uc' => _x( 'UC', 'brand', 'kuroneko' ),
			'diners' => _x( 'Diners', 'brand', 'kuroneko' ),
			'jcb' => _x( 'JCB', 'brand', 'kuroneko' ),
//			'dc' => _x( 'DC', 'brand', 'kuroneko' ),
//			'mitsui' => _x( 'Mitsui Sumitomo Credit', 'brand', 'kuroneko' ),
//			'ufj' => _x( 'UFJ', 'brand', 'kuroneko' ),
//			'saison' => _x( 'Credit Saison', 'brand', 'kuroneko' ),
//			'nicos' => _x( 'NICOS', 'brand', 'kuroneko' ),
			'visa' => _x( 'VISA', 'brand', 'kuroneko' ),
			'master' => _x( 'MASTER', 'brand', 'kuroneko' ),
//			'aeon' => _x( 'AEON CREDIT', 'brand', 'kuroneko' ),
			'amex' => _x( 'Amex', 'brand', 'kuroneko' ),
//			'top' => _x( 'TOP& card', 'brand', 'kuroneko' ),
		];
		if ( $is_international ) {
			return [
				'visa' => $cards['visa'],
			    'master' => $cards['master'],
			];
		} else {
			return $cards;
		}
	}

	/**
	 * Detect if brand exists.
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public static function brand_exists( $code ) {
		$brands = self::get_brand();
		return isset( $brands[ $code ] );
	}

	/**
	 * Get brand code
	 *
	 * @param string $brand
	 *
	 * @return int
	 */
	public static function brand_code( $brand ) {
		$i = 1;
		foreach ( self::get_brand() as $code => $label ) {
			if ( $brand == $code ) {
				return $i;
			}
			$i++;
		}
		return 0;
	}

	/**
	 * Get brand label
	 *
	 * @param int $code
	 *
	 * @return string
	 */
	public static function get_brand_label( $code ) {
		$brands = self::get_brand();
		$i = 0;
		foreach ( $brands as $key => $label ) {
			if ( $code == $i ) {
				return $label;
			}
			$i++;
		}
		return __( 'Unknown Brand', 'kuroneko' );
	}

}
