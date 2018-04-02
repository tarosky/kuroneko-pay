<?php

namespace KuronekoYamato\KuronekoPay\API;


use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Pattern\AbstractApi;
use KuronekoYamato\KuronekoPay\Utility\Option;

/**
 * Credit card API
 *
 * @package KuronekoPay
 * @since 1.0.0
 */
class CreditCard extends AbstractApi {

	public $id = 'kuroneko_cc';

	/**
	 * Authorize transaction
	 *
	 * @param array $data
	 * @param bool  $use_token
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function authorize( $data, $use_token = false ) {
		if ( $use_token ) {
			$endpoint = Endpoint::A08;
		} else {
			$endpoint = Endpoint::A01;
			$data = array_merge( [
				'option_service_div' => '00',
			], $data );
			if ( isset( $data['member_id'], $data['authentication_key'] ) ) {
				$data['check_sum'] = $this->make_checksum( $data['member_id'], $data['authentication_key'] );
			}
		}
		return $this->make_request( $endpoint, $data );
	}

	/**
	 * Register shipping information
	 *
	 * @param string $order_key
	 * @param string $no
	 * @param array  $additional_data
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function capture( $order_key, $no, $additional_data = [] ) {
		$data = array_merge( [
			'order_no' => $order_key,
			'slip_no'  => $no,
		], $additional_data );
		return $this->make_request( Endpoint::E01, $data );
	}

	/**
	 * Cancel shipment.
	 *
	 * @param string $order_key
	 * @param string $tracking_no
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function cacnel_shipment( $order_key, $tracking_no ) {
		return $this->make_request(  Endpoint::E02, [
			'order_no' => $order_key,
			'ship_no'  => $tracking_no,
		] );
	}

	/**
	 * Do transaction
	 *
	 * @param array $data
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function option_transaction( $data ) {
		$data = array_merge( $data, [
			'option_service_div' => '01',
		] );
		return $this->make_request( Endpoint::A01, $data );
	}

	/**
	 * Get new price
	 *
	 * @param string $order_no
	 * @param int    $new_price
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function change_price( $order_no, $new_price ) {
		$data = [
			'order_no'  => $order_no,
		    'new_price' => (int) $new_price,
		];
		return $this->make_request( Endpoint::A07, $data );
	}

	/**
	 * Get registered cards
	 *
	 * @param string $member_id
	 * @param string $auth_key
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function get_cards( $member_id, $auth_key ) {
		$data = [
			'member_id' => $member_id,
			'authentication_key' => $auth_key,
		    'check_sum' => $this->make_checksum( $member_id, $auth_key ),
		];
		return $this->make_request( Endpoint::A03, $data );
	}

	/**
	 * Delete card
	 *
	 * @param array $card Array consists of 'member_id', 'authentication_key', 'card_key' and 'last_credit_date'.
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function delete_card( $card = [] ) {
		$data = array_merge( [
			'member_id' => '',
			'authentication_key' => '',
		    'last_credit_date' => '',
		    'card_key' => 0,
		], $card );
		if ( ! isset( $data['check_sum'] ) ) {
			$data['check_sum'] = $this->make_checksum( $data['member_id'], $data['authentication_key'] );
		}
		return $this->make_request( Endpoint::A05, $data );
	}

	/**
	 * Get hash key
	 *
	 * @param string $member_id
	 * @param string $auth_key
	 *
	 * @return string
	 */
	public function make_checksum( $member_id, $auth_key ) {
		$hash = "{$member_id}{$auth_key}{$this->option->access_key}";
		return hash( 'sha256', $hash );
	}

	/**
	 * Cancel price
	 *
	 * @param string $order_no
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function cancel( $order_no ) {
		return $this->make_request( Endpoint::A06, [
			'order_no' => $order_no,
		] );
	}

	/**
	 * Remove shipment information.
	 *
	 * @param string $order_no
	 * @param string $ship_no
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function remove_shipment( $order_no, $ship_no ) {
		return $this->make_request( Endpoint::E02, [
			'order_no' => $order_no,
		    'slip_no'  => $ship_no,
		] );
	}
}
