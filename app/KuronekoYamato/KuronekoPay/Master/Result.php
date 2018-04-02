<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * Result Master
 *
 * @since 1.0.0
 * @package KuronekoPay
 */
class Result {

	const SUCCESS = '1';

	const ERROR   = '2';

	/**
	 * Is status error?
	 *
	 * @param string $status Status code
	 *
	 * @return bool
	 */
	public static function is_error( $status ) {
		return self::SUCCESS != $status;
	}
}
