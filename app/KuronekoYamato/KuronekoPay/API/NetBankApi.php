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
class NetBankApi extends AbstractApi {

	public $id = 'kuroneko_nb';

	/**
	 * Register payment
	 *
	 * @param string $order_no
	 * @param array  $data
	 *
	 * @return mixed
	 */
	public function register( $order_no, $data = [] ) {
		$data = array_merge( [
			'order_no'   => $order_no,
			'device_div' => 2,
		], $data );
		return $this->make_request( Endpoint::D01, $data );
	}

}
