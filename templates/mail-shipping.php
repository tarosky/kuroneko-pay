<?php
defined( 'ABSPATH' ) or die();
/** @var WC_Order $order */
/** @var string $url */
/** @var bool $plain_text */

$title = __( 'Tracking URL', 'kuroneko' );
$desc  = __( 'You can track your items shipping from this URL.', 'kuroneko' );
if ( $plain_text ) {
	// Plain text.
	echo <<<TXT


{$title}: {$url}
{$desc}

TXT;

} else {
	// HTML mail.
	?>
	<h2><?php echo esc_html( $title ) ?></h2>
	<ul>
		<li>
					<span class="text">
						<a href="<?php echo esc_url( $url ) ?>"><?php echo esc_html( $url ) ?></a>
					</span>
		</li>
	</ul>
	<p><?php echo esc_html( $desc ) ?></p>
	<?php
}
