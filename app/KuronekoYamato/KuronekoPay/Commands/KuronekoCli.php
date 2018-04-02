<?php

namespace KuronekoYamato\KuronekoPay\Commands;

use cli\Table;
use KuronekoYamato\KuronekoPay\API\CreditCard;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;

/**
 * Command utility for Kuroneko Web Collect
 *
 * @package KuronekoPay
 */
class KuronekoCli extends \WP_CLI_Command {

	const COMMAND_NAME = 'kuroneko';

	/**
	 * Get order information from Kuroneko API
	 *
	 * @param array $args
	 * @synopsis <order_id>
	 */
	public function transaction( $args ) {
		list( $order_id ) = $args;
		$api = CreditCard::get_instance();
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			\WP_CLI::error( sprintf( 'Order #%d not found.', $order_id ) );
		}
		$key = OrderHelper::get_instance()->order_key( $order );
		$response = $api->get_transaction_info( $key );
		if ( is_wp_error( $response ) ) {
			\WP_CLI::error( sprintf( '%s: %s', $response->get_error_code(), $response->get_error_message() ) );
		}
		\WP_CLI::line( sprintf( 'Transaction %d found for %s', (int) $response->resultCount, $key ) );
		foreach ( $response->resultData as $data ) {
			$table = new Table();
			$table->setHeaders( [ 'Name', 'Value' ] );
			foreach ( $data->children() as $child ) {
				/** @var \SimpleXMLElement $child */
				$table->addRow( [ $child->getName(), (string) $child ] );
			}
			$table->display();
		}
	}

	/**
	 * Change order status
	 *
	 * Cancel outdated orders.
	 *
	 * @synopsis <target>
	 * @param array $args Command arguments
	 */
	public function cancel( $args ) {
		list( $target ) = $args;
		switch ( $target ) {
			case 'outdated':
				$result = OrderHelper::get_instance()->scheduled_expiration();
				if ( is_wp_error( $result ) ) {
					foreach ( $result->get_error_messages() as $message ) {
						\WP_CLI::warning( $message );
					}
					\WP_CLI::error( 'Failed to update.' );
				} else {
					/* translators: %d: Order number */
					\WP_CLI::success( sprintf( '%s canceled.', sprintf( _n( '%d order', '%d orders', $result, 'kuroneko' ), $result ) ) );
				}
				break;
			default:
				\WP_CLI::error( sprintf( 'Target %s is not allowed.', $target ) );
				break;
		}
	}

}
