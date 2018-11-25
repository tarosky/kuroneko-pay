<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * Error Master
 *
 * @package KuronekoPay
 */
class ErrorMaster {

	/**
	 * Check error code and return message.
	 *
	 * @param string $err_code
	 *
	 * @return bool|string
	 */
	protected static function get_error_codes( $err_code ) {
		$prefix = substr( $err_code, 0, 3 );
		$rest   = substr( $err_code, 3 );
		$genre  = substr( $rest, 0, 3 );
		$code   = substr( $rest, 3 );
		$rest   = (int) $rest;
		try {
			switch ( $prefix ) {
				case 'Z01':
					switch ( $rest ) {
						case 1:
						case 2:
						case 3:
						case 4:
						case 8:
						case 9:
						case 10:
						case 11:
							throw new \Exception( 'store_error' );
							break;
						case 6:
							return _x( 'Request time out.', 'error', 'kuroneko' );
							break;
						case 7:
							return _x( 'Too many transaction.', 'error', 'kuroneko' );
							break;
						default:
							throw new \Exception( 'external_error' );
							break;
					}
					break;
				case 'A01':
					switch ( $genre ) {
						case 101:
							switch ( $code ) {
								case '0502':
									return _x( 'Available amount exceeded.', 'error', 'kuroneko' );
									break;
								default:
									throw new \Exception( 'store_error' );
									break;
							}
							break;
						case 200:
						case 201:
						case 202:
							throw new \Exception( 'store_error' );
							break;
						case 204:
						case 205:
						case 206:
							return _x( 'This credit card is not available. Please try another card.', 'error', 'kuroneko' );
							break;
						case 207:
							return _x( 'Failed to save credit card.', 'error', 'kuroneko' );
							break;
						default:
							throw new \Exception( 'external_error' );
							break;
					}
					break;
				case 'A06':
					switch ( $genre ) {
						case 101:
							throw new \Exception( 'store_error' );
							break;
						default:
							return _x( 'Failed to cancel transaction. Order number doesn\'t exist or not cancelable.', 'error', 'kuroneko' );
							break;
					}
					break;
				case 'A07':
					switch ( $genre ) {
						case '000':
							throw new \Exception( 'external_error' );
							break;
						case '101':
							switch ( $code ) {
								case '0301':
									return _x( 'Transaction not found', 'error', 'kuroneko' );
									break;
								case '0402':
								case '0471':
								case '0472':
									break;
								default:
									throw new \Exception( 'store_error' );
									break;

							}
							break;
						default:
							return _x( 'Sorry, but this transaction cannot be canceled.', 'error', 'kuroneko' );
							break;
					}
					break;
				case 'A03':
					throw new \Exception( 'store_error' );
					break;
				case 'A04':
					switch ( $genre ) {
						case '101':
							throw new \Exception( 'setting_error' );
							break;
						default:
							throw new \Exception( 'store_error' );
							break;
					}
					break;
				case 'A05':
					throw new \Exception( 'setting_error' );
					break;
				case 'B01':
				case 'B02':
				case 'B03':
				case 'B04':
				case 'B05':
				case 'B06':
					switch ( $genre ) {
						case '101':
							throw new \Exception( 'setting_error' );
							break;
						default:
							throw new \Exception( 'store_error' );
							break;
					}
					break;
				case 'E01':
				case 'E02':
				case 'E03':
				case 'E04':
					switch ( $genre ) {
						case '101':
							throw new \Exception( 'setting_error' );
							break;
						case '300':
							return _x( 'System reject request because of duplicate process.', 'error', 'kuroneko' );
							break;
						default:
							return _x( 'Something is wrong. Please check order No, tracking No and so on.', 'error', 'kuroneko' );
							break;
					}
					break;
				default:
					// Undefined error, so do nothing.
					break;
			}
		} catch ( \Exception $e ) {
			switch ( $e->getMessage() ) {
				case 'setting_error':
					return _x( 'Specified data is wrong. Please check your name, tel and so on.', 'error', 'kuroneko' );
					break;
				case 'store_error':
					return _x( 'Store setting error. Please try again later or contact to store owner.', 'error', 'kuroneko' );
					break;
				case 'external_error':
					return _x( 'Something wrong with external gateway.', 'error', 'kuroneko' );
					break;
				default:
					return _x( 'Something is wrong. Please try again later.', 'error', 'kuroneko' );
					break;
			}
		}

		return false;
	}

	/**
	 * Get error message
	 *
	 * @param string $err_code
	 *
	 * @return string
	 */
	public static function convert( $err_code ) {
		$message = self::get_error_codes( $err_code );
		if ( ! $message ) {
			$message = _x( 'Undefined error occurs.', 'error', 'kuroneko' );
		}

		$message = sprintf( '[%s] %s', $err_code, $message );

		/**
		 * kuroneko_error_message
		 *
		 * @package KuronekoPay
		 * @since 1.0.0
		 * @param string $message Error message
		 * @param string $err_code
		 * @return string
		 */
		$message = apply_filters( 'kuroneko_error_message', $message, $err_code );

		return $message;
	}


}
