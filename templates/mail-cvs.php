<?php
defined( 'ABSPATH' ) or die();
/** @var WC_Order $order */
/** @var string $url */
/** @var bool $plain_text */
$code = get_post_meta( $order->id, '_kuroneko_cvs_code', true );
$title = __( 'Payment Instruction', 'kuroneko' );
$desc  = \KuronekoYamato\KuronekoPay\Master\CvsInfo::get_description( $code );
$fields = \KuronekoYamato\KuronekoPay\Master\CvsInfo::get_instruction_field( $order );

if ( $plain_text ) {

	echo <<<TXT

{$title}:

{$desc}

=========================

TXT;
	foreach ( $fields as $field ) {
		printf( "%s: %s\n", $field['label'], strip_tags( $field['output'] ) );
	}
	echo "\n\n=========================\n\n\n";


} else {
	?>
	<h2><?php echo esc_html( $title ) ?></h2>

	<?php echo wpautop( wp_kses( $desc, [ 'a' => [ 'href' => true, 'target' => true ], 'strong' => [], ] ) ) ?>

	<table>
		<tbody>
		<?php foreach ( $fields as $field ) : ?>
			<tr>
				<th><?php echo esc_html( $field['label'] ) ?></th>
				<td><?php echo wp_kses( $field['output'], [ 'a' => [ 'href' => true ] ] ) ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
