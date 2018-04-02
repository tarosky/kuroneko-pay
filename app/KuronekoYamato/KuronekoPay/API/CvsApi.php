<?php

namespace KuronekoYamato\KuronekoPay\API;


use KuronekoYamato\KuronekoPay\Master\CvsInfo;
use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Pattern\AbstractApi;

/**
 * CVS API Class
 *
 * @package KuronekoYamato\KuronekoPay\API
 */
class CvsApi extends AbstractApi {

	public $id = CvsInfo::ID;

	/**
	 * Register payment
	 *
	 * @param string $code
	 * @param array $data
	 *
	 * @return mixed
	 */
	public function register( $code, $data = [] ) {
		$data = array_merge( [
			'device_div' => 2,
		], $data );
		return $this->make_request( Endpoint::get_endpoint_from_function_div( $code ), $data );
	}

}
