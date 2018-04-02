<?php
defined( 'ABSPATH' ) or die();
/** @var WC_Order $order */

$url   = get_post_meta( $order->id, '_kuroneko_bank_html', true );
$limit = get_post_meta( $order->id, '_kuroneko_nb_expired_date', true );
?>

<?php if ( 'canceled' == $order->get_status() ) : ?>

	<p>
		<?php printf(
			__( 'Your payment is completed at %1$s via <strong>%2$s</strong>.', 'kuroneko' ),
			mysql2date( get_option( 'date_format' ), get_post_meta( $order->id, '_kuroneko_final_date', true ) ),
			\KuronekoYamato\KuronekoPay\Master\CvsInfo::label( $cvs )
		); ?>
		<strong></strong>
	</p>
<?php elseif ( 'on-hold' == $order->get_status() ) : ?>
	<h2>
		<?php _e( 'Payment Instruction', 'kuroneko' ) ?>
	</h2>
	<p>
		<?php echo wp_kses( sprintf(
			__( 'Please go to Rakuten Bank from button below and transfer order amount till <strong>%s</strong>.', 'kuroneko' ),
			mysql2date( get_option( 'date_format' ), $limit )
		), [ 'strong' => [] ] ) ?>
	</p>
	<?php echo $url ?>

<?php endif; ?>
