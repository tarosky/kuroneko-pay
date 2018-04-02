<?php
namespace KuronekoYamato\KuronekoPay\Validator;


use Hametuha\HametWoo\Pattern\Validator;
use Hametuha\HametWoo\Utility\Compatibility;

/**
 * Validate requirements
 *
 * @package woopyajp
 */
class Requirement extends Validator {

	/**
	 * Check WooCommerce version
	 *
	 * @return bool|\WP_Error
	 */
	public function test_woo_version() {
		if ( Compatibility::satisfies( '3.0' ) ) {
			return true;
		} else {
			return new \WP_Error( 'too_low_version',
				// translators: %s is WooCommerce version.
				sprintf( __( 'Kuroneko Web Collect requires WooCommerce Version %s and over!', 'kuroneko' ), '3.0' )
			);
		}
	}

	/**
	 * Check currency
	 *
	 * @return bool|\WP_Error
	 */
	public function test_currency() {
		if ( Compatibility::check_currency( 'JPY' ) ) {
			return true;
		} else {
			return new \WP_Error( 'invalid_currency', __( 'Kuroneko Web Collect supports only JPY.', 'kuroneko' ) );
		}
	}
}
