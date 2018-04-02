<?php

/**
 * Class YamatoB2MailHandler
 *
 * @package yb2
 */
class YamatoB2MailHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', function() {
			if ( class_exists( 'WooCommerce' ) ) {
				// Show instruction on email.
				add_action( 'woocommerce_email_after_order_table', [ $this, 'email_detail' ], 20, 4 );
				// Show shipping detail at.
				add_action( 'woocommerce_order_details_after_order_table', [ $this, 'show_order_detail_tracking' ] );
			}
		} );
	}

	/**
	 * Get order detail instruction.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function get_order_args( $order ) {
		$order = wc_get_order( $order );
		if ( 'completed' !== $order->get_status() ) {
			return [];
		}
		$should_ship = false;
		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_product_id() );
			if ( ! $product->is_virtual() ) {
				$should_ship = true;
				break;
			}
		}
		// No shipment, do nothing.
		if ( ! $should_ship ) {
			return [];
		}
		$is_kuroneko     = (bool) get_post_meta( $order->get_id(), '_is_kuroneko', true );
		$shipping_at     = get_post_meta( $order->get_id(), '_kuroneko_shipping_at', true );
		$tracking_no     = get_post_meta( $order->get_id(), '_kuroneko_tracking_no', true );
		$tracking_url    = get_post_meta( $order->get_id(), '_tracking_url', true );
		if ( preg_match( '/^0+$/u', $tracking_no ) ) {
			$tracking_no = '';
		}
		if ( ! $shipping_at && ! $tracking_no && ! $is_kuroneko ) {
			return [];
		}
		$args = [
			__( 'Shipping Company', 'yb2' ) => $is_kuroneko ? __( 'Yamato Transport', 'yb2' ) : '---',
		];
		if ( $shipping_at ) {
			$args[ __( 'Shipping at', 'yb2' ) ] = mysql2date( get_option( 'date_format' ), $shipping_at );
		}
		if ( $tracking_no ) {
			$args[ __( 'Tracking No.', 'yb2' ) ] = $tracking_no ?: '---';
		}
		if ( $tracking_url ) {
			$args['tracking_url'] = $tracking_url;
		} elseif ( $is_kuroneko && $tracking_no ) {
			$args['tracking_url'] = esc_url( 'http://jizen.kuronekoyamato.co.jp/jizen/servlet/crjz.b.NQ0010?id=' . $tracking_no );
		}

		/**
		 * yb2_shipping_mail_args
		 *
		 * Filter to display on the completed email.
		 *
		 * @param array    $args
		 * @param WC_Order $order
		 */
		return apply_filters( 'yb2_shipping_mail_args', $args, $order );
	}

	/**
	 * Add email section
	 *
	 * @param WC_Order                          $order
	 * @param bool                              $sent_to_admin
	 * @param bool                              $plain_text
	 * @param WC_Email_Customer_Completed_Order $email
	 */
	public function email_detail( $order, $sent_to_admin, $plain_text, $email ) {
		$args = $this->get_order_args( $order );
		if ( ! $args ) {
			return;
		}
		if ( $plain_text ) {
			$this->email_plain( $order, $args );
		} else {
			echo '<div style="margin-bottom: 40px;">';
			$this->email_html( $order, $args );
			echo '</div>';
		}
	}

	/**
	 * Render shipping information
	 *
	 * @param WC_Order $order
	 * @param array    $args
	 */
	public function email_plain( $order, $args ) {
		?>

-----------------

<?php
		_e( 'Shipping Information', 'yb2' );

		echo "\n\n\n";

		foreach ( $args as $label => $value ) {
			switch ( $label ) {
				case 'tracking_url':
					echo $value . "\n";
					break;
				default:
					echo sprintf( '%s: %s', $label, $value ) . "\n";
					break;
			}
		}
?>


		<?php
	}

	/**
	 * Render shipping information
	 *
	 * @param WC_Order $order
	 * @param array    $args
	 * @param string   $style
	 */
	public function email_html( $order, $args, $style = 'mail' ) {
		switch ( $style ) {
			case 'mail':
				$atts = ' class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;" border="1"';
				break;
			default:
				$atts = '';
				break;
		}
		?>
		<h2><?php esc_html_e( 'Shipping Information', 'yb2' ) ?></h2>
		<table<?php echo $atts ?>>
			<tbody>
			<?php foreach ( $args as $label => $value ) : if ( 'tracking_url' === $label ) continue; ?>
			<tr>
				<th class="td"><?php echo esc_html( $label ) ?></th>
				<td class="td">
					<?php echo esc_html( $value ) ?>
					<?php if ( ( __( 'Tracking No.', 'yb2' ) === $label ) && $args['tracking_url'] ) : ?>
						（<a href="<?php echo esc_url( $args['tracking_url'] ) ?>"><?php esc_html_e( 'Confirm', 'yb2' ) ?></a>）
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render shipping information after order table.
	 *
	 * @param WC_Order $order
	 */
	public function show_order_detail_tracking( $order ) {
		$args = $this->get_order_args( $order );
		if ( $args ) {
			$this->email_html( $order, $args, 'html' );
		}
	}

}
