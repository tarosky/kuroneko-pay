<?php

namespace KuronekoYamato\KuronekoPay\Rest;

use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;

/**
 * Payment API
 *
 * @package KuronekoYamato
 * @property OrderHelper $helper
 */
class Payment extends Singleton {

	/**
	 * Payment constructor.
	 */
	protected function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}

	/**
	 * Fix order
	 */
	public function register_rest() {
		$args = [
			'order_id' => [
				'required' => true,
				'validate_callback' => [ $this, 'is_order_valid' ],
				'description' => __( 'Order ID.', 'kuroneko' ),
			],
		];
		register_rest_route( 'kuroneko-pay/v1', 'payment/(?P<order_id>\d+)', [
			[
				'methods' => 'POST',
				'args' => $args,
				'callback' => [ $this, 'handle_post_request' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods' => 'PUT',
				'args' => $args,
				'callback' => [ $this, 'handle_put_request' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );
	}

	/**
	 * Get permission callback.
	 *
	 * @param \WP_REST_Request $request Request to param.
	 * @return \WP_Error|bool
	 */
	public function permission_callback( $request ) {
		$order_id = $request->get_param( 'order_id' );
		return current_user_can( 'edit_shop_order', $order_id ) ?: new \WP_Error( 'access_forbidden', __( 'You have no permission to manage order.', 'kuroneko' ), [
			'status' => 403,
		] );
	}

	/**
	 * Validate order id
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return \WP_Error|bool
	 */
	public function is_order_valid( $order_id ) {
		if ( ! is_numeric( $order_id ) ) {
			return new \WP_Error( 'bad_parameter', __( 'Order ID should be numeric.', 'kuroneko' ), [
				'status' => 400,
			] );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'bad_parameter', __( 'Order doens\'n exist.', 'kuroneko' ), [
				'status' => 404,
			] );
		}
		return true;
	}

	/**
	 * Handle POST request.
	 *
	 * This callback will capture payment.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_post_request( $request ) {
		$order       = wc_get_order( $request->get_param( 'order_id' ) );
		if ( $this->helper->is_captured( $order ) ) {
			return new \WP_Error( 'already_captured', __( 'This order is already captured.', 'kuroneko' ), [
				'status' => 401,
			] );
		}
		$is_kuroneko = (bool) get_post_meta( $order->get_id(), '_is_kuroneko', true );
		$result = $this->helper->capture_order( $order->get_id(), false, $is_kuroneko );
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			return new \WP_REST_Response( [
				'success' => true,
				'message' => __( 'Order is successfully captured.', 'kuroneko' ),
			] );
		}
	}

	/**
	 * Handle PUT request.
	 *
	 * This callback will change shipping records.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_put_request( $request ) {
		$order       = wc_get_order( $request->get_param( 'order_id' ) );
		$former  = get_post_meta( $order->get_id(), '_kuroneko_last_shipped_no', true );
		$current = get_post_meta( $order->get_id(), '_kuroneko_tracking_no', true );
		$is_kuroneko = (bool) get_post_meta( $order->get_id(), '_is_kuroneko', true );
		if ( ! $this->helper->is_captured( $order ) ) {
			return new \WP_Error( 'not_captured', __( 'This order is not captured yet.', 'kuroneko' ), [
				'status' => 400,
			] );
		}
		if ( $former === $current ) {
			return new \WP_Error( 'nothing_changed', __( 'Tracking no seemed to be same.', 'kuroneko' ), [
				'status' => 400,
			] );
		}
		// Delete former.
		$api = $this->helper->get_api( $order );
		$key = $this->helper->order_key( $order );
		$response = $api->remove_shipment( $key, $former );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		// Recreate shipping.
		$response = $api->capture( $key, $current, [
			'delivery_service_code' => $is_kuroneko ? '00' : '99',
		] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		update_post_meta( $order->get_id(), '_kuroneko_last_shipped_no', $current );
		// Here comes success.
		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Shipping information is changed.', 'kuroneko' ),
		] );
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'helper':
				return OrderHelper::get_instance();
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}
}
