<?php
defined( 'ABSPATH' ) or die();
/** @var WC_Order $order */

$code        = get_post_meta( $order->id, '_kuroneko_cvs_code', true );
$description = \KuronekoYamato\KuronekoPay\Master\CvsInfo::get_description( $code );
$fields      = \KuronekoYamato\KuronekoPay\Master\CvsInfo::get_instruction_field( $order );
?>

<?php if ( $cvs = get_post_meta( $order->id, '_kuroneko_final_cvs', true ) ) : ?>

	<p>
		<?php printf(
			__( 'Your payment is completed at %1$s via <strong>%2$s</strong>.', 'kuroneko' ),
			mysql2date( get_option( 'date_format' ), get_post_meta( $order->id, '_kuroneko_final_date', true ) ),
			\KuronekoYamato\KuronekoPay\Master\CvsInfo::label( $cvs )
		); ?>
		<strong></strong>
	</p>
<?php elseif ( $failed = get_post_meta( $order->id, '_kuroneko_final_failed', true ) ) : ?>
	<p>
		<?php printf(
			__( 'Your order is expired at %s.', 'kuroneko' ),
			mysql2date( get_option( 'date_format' ), $failed )
		); ?>
	</p>
<?php else : ?>
	<h2>
		<?php _e( 'Payment Instruction', 'kuroneko' ) ?>
		<?php if ( 'completed' == $order->get_status() ) : ?>
			<small>- <?php _e( 'Completed', 'kuroneko' ) ?></small>
		<?php endif; ?>
	</h2>

	<?php echo wpautop( wp_kses( $description, [ 'a' => [ 'href' => true, 'target' => true ], 'strong' => [], ] ) ) ?>

	<table class="kuroneko-order-instruction order_details">

		<tbody>

		<?php foreach ( $fields as $field ) : ?>
			<tr>
				<th><?php echo esc_html( $field['label'] ) ?></th>
				<td><?php echo wp_kses( $field['output'], [ 'a' => [ 'href' => true ] ] ) ?></td>
			</tr>
		<?php endforeach; ?>

		</tbody>

	</table>
<?php endif; ?>
