<?php

namespace KuronekoYamato\KuronekoPay\Helper;


use Hametuha\HametWoo\Utility\Input;
use Hametuha\HametWoo\Utility\OrderHandler;
use KuronekoYamato\KuronekoPay\API\CreditCard;
use KuronekoYamato\KuronekoPay\API\CreditCardIntl;
use KuronekoYamato\KuronekoPay\API\CvsApi;
use KuronekoYamato\KuronekoPay\API\NetBankApi;
use KuronekoYamato\KuronekoPay\Master\CvsInfo;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;


/**
 * Shipping Helper
 *
 * @since 1.0.0
 * @package KuronekoPay
 */
class OrderHelper extends Singleton {

	/**
	 * Cron event key
	 *
	 * @var string
	 */
	protected $cron_event = 'kuroneko_outdated_orders';

	/**
	 * Constructor
	 */
	protected function __construct() {
		// Automatic disable expired orders.
		add_action( 'init', function() {
			if ( ! wp_next_scheduled( $this->cron_event ) ) {
				wp_schedule_event( current_time( 'timestamp', true ), 'daily', $this->cron_event );
			}
			add_action( $this->cron_event, [ $this, 'scheduled_expiration' ] );
		} );
		// Capture order with shipping.
		add_action( 'woocommerce_order_status_processing_to_completed', [ $this, 'complete_order' ], 10, 2 );
		// Show field on posts custom column.
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'posts_columns' ], 11, 2 );
		// Add metabox for payment instructoin
		add_action( 'add_meta_boxes', function( $post_type, $post ) {
			if ( 'shop_order' !== $post_type ) {
				return;
			}
			$order = wc_get_order( $post->ID );
			switch ( $order->get_payment_method() ) {
				case 'kuroneko_cc':
				case 'kuroneko_cc_intl':
					add_meta_box( 'kuroneko-failed-order', __( 'Kuroneko Web Collect', 'yb2' ), [ $this, 'do_meta_box' ], $post_type, 'normal', 'high' );
					break;
				default:
					// Do nothing.
					break;
			}
		}, 31, 2 );
		// Show shipping detail at.
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'show_order_detail_instruction' ] );
		// Show shipment URL for email.
		add_action( 'woocommerce_email_customer_details', [ $this, 'display_payment_instruction_process' ], 10, 4 );
		// Show payment instruction for net bank.
		add_action( 'woocommerce_thankyou_kuroneko_nb', [ $this, 'show_order_bank_instruction' ] );
		// Register CVS callback.
		add_action( 'woocommerce_api_kuroneko-cvs', [ $this, 'post_back_handler' ] );

	}

	/**
	 * Get order key
	 *
	 * @param int|\WC_Order|false $order_id
	 *
	 * @return mixed
	 */
	public function order_key( $order_id ) {
		$order = wc_get_order( $order_id );

		return strtoupper( str_replace( 'wc_order_', 'wc-', $order->get_order_key() ) );
	}

	/**
	 * Get tracking no
	 *
	 * @param int|false|\WC_Order $order_id
	 *
	 * @return string
	 */
	public function get_tracking_no( $order_id ) {
		$order = wc_get_order( $order_id );

		return (string) get_post_meta( $order->get_id(), '_kuroneko_tracking_no', true );
	}

	/**
	 * Get API
	 *
	 * @param int|\WC_Order $order
	 *
	 * @return CreditCard|CreditCardIntl
	 */
	public function get_api( $order ) {
		$order = wc_get_order( $order );
		switch ( $order->get_payment_method() ) {
			case 'kuroneko_cc':
				return CreditCard::get_instance();
				break;
			case 'kuroneko_cc_intl':
				return CreditCardIntl::get_instance();
				break;
			default:
				return null;
				break;
		}
	}

	/**
	 * Detect if order has shipping item.
	 *
	 * @param int|false|\WC_Order $order_id
	 *
	 * @return bool
	 */
	public function has_shipping( $order_id ) {
		$order   = wc_get_order( $order_id );
		$items   = $order->get_items();
		$virtual = true;
		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_product_id() );
			// For deleted products
			if ( empty( $product ) ) {
				$virtual = true;
				break;
			}
			if ( ! $product->is_virtual() ) {
				$virtual = false;
				break;
			}
		}

		return ! $virtual;
	}

	/**
	 * Save order meta
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object.
	 */
	public function complete_order( $order_id, $order ) {
		// Check order payment method is CC.
		if ( ! in_array( $order->get_payment_method(), [ 'kuroneko_cc', 'kuroneko_cc_intl' ] ) ) {
			return;
		}
		// Already captured so do nothing.
		if ( $this->is_captured( $order ) ) {
			return;
		}
		// Capture.
		$is_yamato = (bool) get_post_meta( $order->get_id(), '_is_kuroneko', true );
		$response = $this->capture_order( $order, $this->get_tracking_no( $order ), $is_yamato );
		if ( is_wp_error( $response ) ) {
			// translators: %s is error message.
			$order->add_order_note( sprintf( __( 'Failed to capture transaction: %s', 'kuroneko' ), $response->get_error_message() ) );
		}
	}

	/**
	 * Detect if this order is already captured.
	 *
	 * @param int|false|\WC_Order $order_id
	 *
	 * @return bool|string
	 */
	public function is_captured( $order_id ) {
		$order = wc_get_order( $order_id );
		clean_post_cache( $order->get_id() );
		$captured = get_post_meta( $order->get_id(), '_kuroneko_captured', true );

		return preg_match( '#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $captured ) ? $captured : false;
	}

	/**
	 * Get status label
	 *
	 * @param int|false|\WC_Order $order_id
	 *
	 * @return string
	 */
	public function get_status_label( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->has_shipping( $order ) ) {
			return '';
		}
		$captured    = $this->is_captured( $order );
		$tracking_no = $this->get_tracking_no( $order );
		$color       = 'green';
		switch ( $order->get_status() ) {
			case 'completed':
				if ( $captured ) {
					$color     = 'green';
					$dashicons = 'yes';
					$label     = __( 'Complete', 'kuroneko' );
				} else {
					$color     = 'red';
					$dashicons = 'no';
					$label     = __( 'Failed Payment', 'kuroneko' );
				}
				break;
			case 'processing':
				if ( $tracking_no ) {
					$color     = 'green';
					$dashicons = 'yes';
					$label     = __( 'Tracking Ready', 'kuroneko' );
				} else {
					$color     = 'orange';
					$dashicons = 'no';
					$label     = __( 'Need Tracking No.', 'kuroneko' );
				}
				break;
			default:
				$color     = 'gray';
				$dashicons = 'minus';
				$label     = __( 'No Payment', 'kuroneko' );
				break;
		}
		ob_start();
		?>
		<span style="color: <?php echo esc_attr( $color ) ?>;">
			<span class="dashicons dashicons-<?php echo esc_attr( $dashicons ) ?>"></span>
			<?php echo esc_html( $label ) ?>
		</span>
		<?php
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Remove shipment no
	 *
	 * @param int|\WC_Order $order
	 *
	 * @return bool|\WP_Error
	 */
	public function change_shipment( $order ) {
		$order = wc_get_order( $order );
		if ( ! in_array( $order->payment_method, [ 'kuroneko_cc', 'kuroneko_cc_intl' ] ) ) {
			return false;
		}
		if ( ! $this->is_captured( $order ) ) {
			return false;
		}
		$no       = $this->get_tracking_no( $order );
		$response = CreditCard::get_instance()->remove_shipment( $this->order_key( $order ), $no );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		delete_post_meta( $order->get_id(), '_kuroneko_captured' );
		delete_post_meta( $order->get_id(), '_tracking_url' );

		return true;
	}

	/**
	 * Capture order
	 *
	 * @param $order
	 * @param bool $tracking_no
	 * @param bool $is_kuroneko
	 *
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public function capture_order( $order, $tracking_no = false, $is_kuroneko = true ) {
		$order = wc_get_order( $order );
		// If tracking no is not set, retrieve from post meta.
		if ( false === $tracking_no ) {
			$tracking_no = get_post_meta( $order->get_id(), '_kuroneko_tracking_no', true );
			if ( ! $tracking_no ) {
				return new \WP_Error( 'not_found', __( 'Tracking No is not specified.', 'kuroneko' ), [
					'status' => 404,
				] );
			}
		}
		// If kuroneko, check number format.
		if ( ! $this->is_tracking_no_valid( $tracking_no, $is_kuroneko ) ) {
			return new \WP_Error( 'invalid', __( 'Tracking number is mal-formatted.', 'kuroneko' ), [
				'status' => 500,
			] );
		}
		// Capture.
		$response = $this->get_api( $order )->capture( $this->order_key( $order ), $tracking_no, [
			'delivery_service_code' => $is_kuroneko ? '00' : '99',
		] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		// Save tracking url.
		if ( $is_kuroneko ) {
			update_post_meta( $order->get_id(), '_tracking_url', (string) $response->slipUrlPc );
		}
		// Save captured date.
		$now = current_time( 'mysql' );
		update_post_meta( $order->get_id(), '_kuroneko_captured', $now );
		update_post_meta( $order->get_id(), '_kuroneko_last_shipped_no', $tracking_no );
		$order->add_order_note( sprintf(
			__( 'Transaction %1$s successfully captured at %2$s.', 'kuroneko' ),
			$this->order_key( $order ),
			mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $now )
		) );

		return $response;
	}

	/**
	 * Check validator
	 *
	 * @param string $value
	 * @param bool $is_kuroneko
	 *
	 * @return bool
	 */
	public function is_tracking_no_valid( $value, $is_kuroneko = true ) {
		list( $from, $to ) = $is_kuroneko ? [ 11, 12 ] : [ 1, 23 ];
		$regexp = '/[\-A-Z0-9#\\*\/<>:@]{' . $from . ',' . $to . '}/u';
		return (bool) preg_match( $regexp, $value );
	}

	/**
	 * Render column field
	 *
	 * @param string $column
	 * @param int $order_id
	 */
	public function posts_columns( $column, $order_id ) {
		if ( false === array_search( $column, [ 'shipping_address', 'order_title' ] ) ) {
			// Do nothing if this is not shipping field.
			return;
		}
		$order       = wc_get_order( $order_id );
		$tracking_no = $this->get_tracking_no( $order );
		switch ( $column ) {
			case 'order_title':
				?>
				<p class="wc-yamato-payment-status">
					<?php echo $this->get_status_label( $order ) ?>
				</p>
				<?php
				break;
			case 'shipping_address':
				$is_kuroneko = (bool) get_post_meta( $order_id, '_is_kuroneko', true );
				// Get track icon
				if ( ! $this->has_shipping( $order ) ) {
					$icon = sprintf(
						'<span class="dashicons dashicons-download" title="%1$s"></span> %1$s',
						__( 'No shipping', 'kuroneko' )
					);
				} elseif ( $is_kuroneko ) {
					$icon = sprintf(
						'<img class="kuroneko" src="%1$s" alt="%2$s" width="20" height="20" /> %2$s',
						kuroneko_asset( '/img/kuroneko.svg' ),
						__( 'Shipped via Yamato', 'kuroneko' )
					);
				} else {
					$icon = sprintf(
						'<span class="dashicons dashicons-cart" title="%1$s"></span> %1$s',
						__( 'Has shipping', 'kuroneko' )
					);
				}
				?>
				<p class="wc-yamato-shipping-status">
					<?php
					echo wp_kses( $icon, [
						'img'  => [
							'src'    => true,
							'alt'    => true,
							'width'  => true,
							'height' => true,
							'class'  => true,
						],
						'span' => [
							'class' => true,
							'title' => true,
						],
					] ); ?>
					:
					<?php
					if ( $tracking_no ) {
						printf( '<input type="text" readonly value="%s" />', esc_attr( $tracking_no ) );
					} else {
						echo '<span style="color: lightgrey;">---</span>';
					}
					?>
				</p>
				<?php
				break;
		}
	}

	/**
	 * Display order instruction on thank you page.
	 *
	 * @param int $order_id
	 */
	public function show_order_bank_instruction( $order_id ) {
		kuroneko_template( 'order-bank', [
			'order' => wc_get_order( $order_id ),
		] );
	}

	/**
	 * Show instruction on order detail page.
	 *
	 * @param \WC_Order $order
	 */
	public function show_order_detail_instruction( $order ) {
		$order = wc_get_order( $order );
		$template = false;
		switch ( $order->payment_method ) {
			case CvsInfo::ID:
				$template = 'order-cvs';
				break;
			case 'kuroneko_nb':
				if ( is_view_order_page() ) {
					$template = 'order-bank';
				}
				break;
			default:
				// Do nothing.
				break;
		}
		if ( $template ) {
			kuroneko_template( $template, [ 'order' => $order ] );
		}
	}

	/**
	 * Show payment instruction
	 *
	 * @param mixed $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 * @param \WC_Email $email
	 *
	 * @return void
	 */
	public function display_payment_instruction_process( $order, $sent_to_admin, $plain_text, $email ) {
		$order = wc_get_order( $order );
		if ( ( 'on-hold' == $order->get_status() ) && ( CvsInfo::ID == $order->payment_method ) ) {
			kuroneko_template( 'mail-cvs', [
				'order'      => $order,
				'plain_text' => $plain_text
			] );
		}
	}

	/**
	 * Handle callback
	 */
	public function post_back_handler() {
		try {
			// Get posted values.
			$input = Input::get_instance();
			$trader_code = $input->post( 'trader_code' );
			$order_no    = $input->post( 'order_no' );
			$settle_date = $input->post( 'settle_date' );
			$result      = (int) $input->post( 'settle_result' );
			$result_no   = (int) $input->post( 'settle_detail' );
			$method      = (int) $input->post( 'settle_method' );
			if ( CvsInfo::slug( $method ) ) {
				// This is CVS.
				$api    = CvsApi::get_instance();
			} elseif ( 41 == $method ) {
				// This is Bank
				$api = NetBankApi::get_instance();
			} else {
				throw new \Exception( __( 'Invalid Payment Method.', 'kuroneko' ), 400 );
			}
			$payment_method = $api->id;
			$option      = get_option( 'woocommerce_' . $api->id . '_settings', [] );
			if ( ! $trader_code || ( $option['trader_code'] != $trader_code ) ) {
				throw new \Exception( __( 'Invalid trader code.', 'kuroneko' ), 400 );
			}
			$order_key = strtolower( str_replace( 'WC-', 'wc_order_', $order_no ) );
			$order_id = wc_get_order_id_by_order_key( $order_key );
			if ( ! $order_id ) {
				throw new \Exception( __( 'Order not found.', 'kuroneko' ), 404 );
			}
			// Order exists. Check complete information.
			$order = wc_get_order( $order_id );
			if ( $payment_method != $order->get_payment_method() ) {
				// This is not CVS.
				throw new \Exception( __( 'Order is not paid with specified method.', 'kuroneko' ), 400 );
			}
			$transaction_info = $api->get_transaction_info( $order_no );
			if ( is_wp_error( $transaction_info ) ) {
				$message = sprintf( __( 'Error on %1$s transaction: %2$s', 'kuroneko' ), $payment_method, $transaction_info->get_error_message() );
				$order->add_order_note( $message );
				throw new \Exception( $message, 400 );
			}
			switch ( $result ) {
				case 1:
					// Success.
					if ( 'on-hold' == $order->get_status() ) {
						if ( $this->has_shipping( $order ) ) {
							$order->payment_complete();
						} else {
							$order->update_status( 'completed' );
						}
						update_post_meta( $order->get_id(), '_kuroneko_final_date', current_time( 'mysql' ) );
						switch ( $payment_method ) {
							case 'kuroneko_cvs':
								update_post_meta( $order->get_id(), '_kuroneko_final_cvs', $method );
								break;
							case 'kuroneko_nb':
								// Do nothing
								break;
							default:
								// Do nothing.
								break;
						}
					}
					break;
				case 2:
					// Failed.
					if ( 'cancelled' != $order->get_status() ) {
						update_post_meta( $order->get_id(), '_kuroneko_final_failed', current_time( 'mysql' ) );
						$reason = __( 'Payment limit is outdated.', 'kuroneko' );
						update_post_meta( $order->get_id(), '_hametwoo_cancel_reason', $reason );
						$order->update_status( 'cancelled', sprintf( _x( 'Failed to pay: %s', 'Error', 'kuroneko' ), $result_no ) );
						// Restore stock.
						OrderHandler::restore_stock( $order );
					}
					break;
				default:
					throw new \Exception( __( 'Failed to update order.', 'kuroneko' ), 400 );
					break;
			}
			wp_send_json_success( __( 'Order updated', 'kuroneko' ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}
	}

	/**
	 * Detect if this order is international.
	 *
	 * @param \WC_Customer $customer
	 *
	 * @return bool
	 */
	public function is_international( $customer ) {
		return 'JP' != $customer->get_billing_country();
	}

	/**
	 * Show meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function do_meta_box( $post ) {
		$order = wc_get_order( $post->ID );
		printf( '<p class="description">%s</p><hr />', esc_html__( 'You can check order payment status at Store admin of Kuroneko Web Collect.', 'kuroneko' ) );
		switch ( $order->get_status() ) {
			case 'completed':
				if ( $this->is_captured( $order->get_id() ) ) {
					echo wp_kses_post( sprintf( '<p>%s</p>', sprintf( __( 'Payment is captured at <code>%s</code>. If you want to change tracking no, please click button below after saving new one.', 'kuroneko' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_post_meta( $order->get_id(), '_kuroneko_captured', true ) ) ) ) );
					printf(
						'<p><button type="button" class="button-primary" id="kuroneko-tracking-change" data-post-id="%d">%s</button></p>',
						$post->ID,
						__( 'Change Tracking No.', 'kuroneko' )
					);
				} else {
					echo wp_kses_post( sprintf( '<p>%s</p>', sprintf( __( 'Payment is <strong style="color: red;">NOT captured</strong>. To confirm payment, Please click button below after saving %s.', 'kuroneko' ),  __( 'Tracking No.', 'yb2' ) ) ) );
					printf(
						'<p><button type="button" class="button-primary" id="kuroneko-capture-order" data-post-id="%d">%s</button></p>',
						$post->ID,
						__( 'Capture Payment', 'kuroneko' )
					);
				}
				break;
			case 'processing':
				if ( $this->get_tracking_no( $order->get_id() ) ) {
					printf( '<p>%s</p>', wp_kses_post( sprintf( __( '<strong style="color: darkgreen">%s is set</strong>. Payment will be captured when order is "completed".', 'kuroneko' ), __( 'Tracking No.', 'yb2' ) ) ) );
				} else {
					printf(
						'<p>%s %s</p>',
						wp_kses_post( sprintf( __( '<strong style="color: red">%1$s is required</strong>. Enter %1$s in %2$s.', 'kuroneko' ), __( 'Tracking No.', 'yb2' ), __( 'Shipping Information', 'yb2' ) ) ),
						wp_kses_post( sprintf( __( 'In case of downloadable products or other shipping method as Yamato Transport, enter <code>000000000000</code> as dummy %s.', 'kuroneko' ), __( 'Tracking No.', 'yb2' ) ) )
					);
				}
				break;
			default:
				printf( '<p>%s</p>', esc_html__( 'No additional information for this order.', 'kuroneko' ) );
				break;
		}
	}

	/**
	 * Expires all outdated orders
	 *
	 * @return int|\WP_Error
	 */
	public function scheduled_expiration() {
		$error = new \WP_Error();
		$canceled = 0;
		$now = new \DateTime( date_i18n( \DateTime::ISO8601 )  );
		$interval = new \DateInterval( 'PT12H' );
		$now->sub( $interval );

		$limit = $now->format( 'Y-m-d H:i:s' );
		foreach ( [
			CvsInfo::ID   => '_kuroneko_cvs_expired_date',
			'kuroneko_nb' => '_kuroneko_nb_expired_date',
		] as $method => $meta_key) {
			$orders = get_posts( [
				'post_type'   => 'shop_order',
				'post_status' => [ 'wc-on-hold', 'wc-pending' ],
				'posts_per_page' => -1,
				'meta_query'  => [
					// This is specified method
					[
						'key'   => '_payment_method',
						'value' => $method
					],
					// This has payment limit.
					[
						'key'     => $meta_key,
						'value'   => $limit,
						'compare' => '<',
						'type'    => 'DATETIME'
					],
				],
			] );
			foreach ( $orders as $order ) {
				$order = wc_get_order( $order );
				update_post_meta( $order->get_id(), '_kuroneko_final_failed', current_time( 'mysql' ) );
				$reason = __( 'Payment limit is outdated.', 'kuroneko' );
				update_post_meta( $order->get_id(), '_hametwoo_cancel_reason', $reason );
				$order->update_status( 'cancelled', sprintf( _x( 'Failed to pay: %s', 'Error', 'kuroneko' ), $this->order_key( $order ) ) );
				$canceled++;
			}
		}
		if ( $error->get_error_messages() ) {
			return $error;
		} else {
			return $canceled;
		}
	}
}
