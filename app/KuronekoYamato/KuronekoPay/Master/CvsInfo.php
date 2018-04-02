<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * CSV list
 *
 * @package KuronekoPay
 */
class CvsInfo {

	const ID = 'kuroneko_cvs';

	const FEE = 250;

	/**
	 * CVS codes
	 *
	 * @return array
	 */
	protected static function codes() {
		static $codes = [];
		if ( ! $codes ) {
			$codes = [
				21 => [
					'seven-eleven',
					__( 'Seven Eleven', 'kuroneko' ),
				],
				22 => [
					'lawson',
					__( 'Lawson', 'kuroneko' ),
				],
				23 => [
					'family-mart',
					__( 'Family Mart', 'kuroneko' ),
				],
				24 => [
					'seiko',
					__( 'SeikoMart', 'kuroneko' ),
				],
				25 => [
					'mini-stop',
					__( 'Mini Stop', 'kuroneko' ),
				],
				26 => [
					'circle-k',
					__( 'Circle K Sunks', 'kuroneko' ),
				],
			];
		}

		return $codes;
	}

	protected static $group = [
		'B01' => [ 21 ],
		'B02' => [ 23 ],
		'B03' => [ 22, 24, 25, 26 ],
	];

	/**
	 * Get label from code
	 *
	 * @param int $code
	 *
	 * @return string
	 */
	public static function label( $code ) {
		$codes = self::codes();
		if ( ! isset( $codes[ $code ] ) ) {
			return '';
		}

		return $codes[ $code ][1];
	}

	/**
	 * Get slug from code
	 *
	 * @param int $code
	 *
	 * @return string
	 */
	public static function slug( $code ) {
		$codes = self::codes();
		if ( ! isset( $codes[ $code ] ) ) {
			return '';
		}

		return $codes[ $code ][0];
	}

	/**
	 * Get all CVS
	 *
	 * @return array
	 */
	public static function get_all() {
		return self::$group;
	}

	/**
	 * Get instruction fields
	 *
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public static function get_instruction_field( $order ) {
		$code = get_post_meta( $order->id, '_kuroneko_cvs_code', true );
		$order = wc_get_order( $order );
		$fields = [[
			'label'  => __( 'Available Store', 'kuroneko' ),
		    'output' => implode( ', ', array_map( function( $id ){
		    	return self::label( $id );
		    }, self::$group[ $code ] ) ),
		]];
		switch ( $code ) {
			case 'B01':
				$fields[] = [
					'label' => __( 'Payment No.', 'kuroneko' ) ,
					'output' => get_post_meta( $order->id, '_kuroneko_cvs_billing_no', true ),
				];
				$url = get_post_meta( $order->id, '_kuroneko_cvs_billing_url', true );
				$fields[] = [
					'label' => __( 'Payment Coupon URL', 'kuroneko' ) ,
					'output' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $url ) ),
				];
				break;
			case 'B02':
				$fields[] = [
					'label' => __( 'Company Code', 'kuroneko' ) ,
					'output' => get_post_meta( $order->id, '_kuroneko_cvs_billing_company', true ),
				];
				$fields[] = [
					'label' => __( 'Order No.', 'kuroneko' ) ,
					'output' => get_post_meta( $order->id, '_kuroneko_cvs_billing_nof', true ),
				];

				break;
			case 'B03':
				$fields[] = [
					'label' => __( 'Order No.', 'kuroneko' ) ,
					'output' => get_post_meta( $order->get_id(), '_kuroneko_cvs_billing_econ', true ),
				];
				$fields[] = [
					'label'  => __( 'Phone Number', 'kuroneko' ),
				    'output' => $order->get_billing_phone(),
				];
				break;
		}

		$fields[] = [
			'label'  => __( 'Payment Limit', 'kuroneko' ),
			'output' => mysql2date( get_option( 'date_format' ), get_post_meta( $order->id, '_kuroneko_cvs_expired_date', true ) )
		];
		return $fields;
	}

	/**
	 * Get payment instruction
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function get_description( $code ) {
		switch ( $code ) {
			case 'B01':
				return implode( "\n", [
					_x( '1. Print the payment coupon and pay with it at register.', 'Instruction', 'kuroneko' ),
					_x( '2. Or, remember your payment number(13 digits) and tell it to a shop assistant.', 'Instruction', 'kuroneko' ),
				]);
				break;
			case 'B02':
				return implode( "\n", [
					_x( '1. Remember company code and order NO on your payment coupon.', 'Instruction', 'kuroneko' ),
					_x( '2. At a shop, enter these 2 numbers to Famiport and you will get a ticket.', 'Instruction', 'kuroneko' ),
					_x( '3. Pay with the ticket.', 'Instruction', 'kuroneko' ),
				]);
				break;
			case 'B03':
				return implode( "\n", [
					_x( '1. Remember order No and your billing phone number.', 'Instruction', 'kuroneko' ),
					_x( '2. At a shop, enter these 2 number to Loppi terminal and you will get a ticket.', 'Instruction', 'kuroneko' ),
					_x( '3. Pay with the ticket.', 'Instruction', 'kuroneko' ),
					_x( '4. If you have trouble, please ask shop assistant about your online payment.', 'Instruction', 'kuroneko' ),
				]);
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get payment fee
	 *
	 * @return int
	 */
	public static function get_current_fee() {
		$option = get_option( 'woocommerce_' . self::ID . '_settings', [] );
		if ( isset( $option['fee'] ) && is_numeric( $option['fee'] ) ) {
			return (int) $option['fee'];
		} else {
			return self::FEE;
		}
	}
}
