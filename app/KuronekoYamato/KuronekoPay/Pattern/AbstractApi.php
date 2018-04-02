<?php

namespace KuronekoYamato\KuronekoPay\Pattern;

use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Master\ErrorMaster;

/**
 * API controller.
 *
 * @package KuronekoPay
 */
class AbstractApi extends Singleton {

	public $id = '';

	/**
	 * Detect if connection is established.
	 *
	 * @return string|\WP_Error If success, returns IP address.
	 */
	public function check_connection() {
		$response = $this->make_request( Endpoint::H01, [], true );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return (string) $response->ipAddress;
	}

	/**
	 * Make request
	 *
	 * @param string    $endpoint
	 * @param array     $data
	 * @param null|bool $sandbox If not set, option value is used.
	 * @return \WP_Error|\SimpleXMLElement
	 */
	public function make_request( $endpoint, array $data = [], $sandbox = null ) {
		$function_div = Endpoint::get_function_div( $endpoint );
		$option = get_option( "woocommerce_{$this->id}_settings", [] );
		if ( is_null( $sandbox ) ) {
			$sandbox = ! ( $option['sandbox'] && ( 'no' == $option['sandbox'] ) );
		}
		$data         = wp_parse_args( $data, [
			'function_div' => $function_div,
			'trader_code'  => $option['trader_code'],
		] );
		$endpoint     = Endpoint::make_url( $endpoint, $sandbox );
		$response = wp_remote_post( $endpoint, [
			'timeout' => 30,
			'body' => $data,
			'sslverify' => false,
			'sslcertificates' => '',
		] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response_obj = simplexml_load_string( $response['body'] );
		if ( ! $response_obj ) {
			return new \WP_Error( 500, __( 'Failed to parse response. Please try again later.', 'kuroneko' ), [
				'response' => $response,
			] );
		}
		if ( 0 !== (int) $response_obj->returnCode ) {
			// Error returned.
			$err_code = (string) $response_obj->errorCode;
			return new \WP_Error( 500, ErrorMaster::convert( $err_code ) );
		}
		return $response_obj;
	}

	/**
	 * Get order no
	 *
	 * @param string $order_no
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function get_transaction_info( $order_no ) {
		return $this->make_request( Endpoint::E04, [
			'order_no' => $order_no,
		] );
	}

}
