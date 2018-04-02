<?php

namespace KuronekoYamato\KuronekoPay;

use Hametuha\HametWoo\Utility\Compatibility;
use KuronekoYamato\KuronekoPay\Master\Brands;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;

/**
 * Credit Card Manager
 *
 * @package kuroneko
 * @property-read array $option
 * @property-read Member $member
 */
class CardManager extends Singleton {

	/**
	 * Constructor
	 *
	 * @param array $setting Not used.
	 */
	protected function __construct( array $setting = [] ) {

		if ( ! $this->option->enabled ) {
			return;
		}
		// Register assets.
		add_action( 'wp_enqueue_scripts', function () {
			if ( is_account_page() ) {
				wp_enqueue_style( 'kuroneko-cc-helper', kuroneko_asset( '/css/my-account.css' ), [], $this->version );
				wp_enqueue_script( 'kuroneko-cc-helper', kuroneko_asset( '/js/card-manager.js' ), [
					'jquery-blockui',
					'wp-api',
				], $this->version, true );
				wp_localize_script( 'kuroneko-cc-helper', 'KuronekoCC', [
					'ajaxLoaderImage' => kuroneko_asset( '/img/ajax-loader@2x.gif' ),
				    'confirm'         => __( 'Are you sure to delete this card?', 'kuroneko' ),
				    'failureDelete'   => __( 'Failed to delete Credit card.', 'kuroneko' ),
				    'failureAdd'      => __( 'Failed to add Credit card.', 'kuroneko' ),
				] );
			}
		} );
		// Register API
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}


	/**
	 * Show credit card form
	 */
	public function credit_card_list() {
		$cards      = $this->member->get_cards( get_current_user_id() );
		$class_name = empty( $cards ) ? ' my_kuroneko_cc--empty' : '';
		?>
		<section class="my_kuroneko_cc <?php echo esc_attr( $class_name ); ?>">
			<table class="shop_table my_account_orders my_kuroneko_cc__table">
				<thead>
				<tr>
					<th class="number">
						#
					</th>
					<th class="subscription">
						<?php _e( 'Card Number', 'kuroneko' ) ?>
					</th>
					<th>
						<?php _e( 'Brand', 'kuroneko' ) ?>
					</th>
					<th class="">
						<?php _e( 'Expiry (MM/YY)', 'woocommerce' ) ?>
					</th>
					<th>
						<?php _e( 'Available Area', 'kuroneko' ) ?>
					</th>
					<th>
						<?php _e( 'Action' ) ?>
					</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $cards as $card ) : ?>
					<?php echo $this->render_card( $card ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="my_kuroneko_cc__alert error">
				<p><?php esc_html_e( 'No registered card. You can add 1 credit card for each area on check out page.', 'kuroneko' ) ?></p>
			</div>

			<div class="my_kuroneko_cc__warning">
				<p>
					<?php esc_html_e( 'You can save only 1 card for each area. To register new, please remove existing before.', 'kuroneko' ) ?>
					<?php if ( Compatibility::subscription_available() ) : ?>
						<?php esc_html_e( 'If your card is used for subscription, you can\'t delete it as long as you stop subscription.', 'kuroneko' ) ?>
					<?php endif; ?>
				</p>
			</div>
		</section>
		<?php
	}

	/**
	 * Card row
	 *
	 * @param \stdClass $card
	 *
	 * @return string
	 */
	protected function render_card( $card ) {
		ob_start();
		?>
		<tr>
			<td class="number">
				<?php echo $card->card_key ?>
			</td>
			<td>
				**** <?php echo esc_html( $card->last4 ) ?>
			</td>
			<td>
				<?php echo esc_html( $card->brand ) ?>
			</td>
			<td>
				<?php printf( '%02d / %d', $card->exp_month, $card->exp_year ) ?>
			</td>
			<td>
				<?php if ( $card->international ) : ?>
					<i class="fa fa-globe my_kuroneko_cc__globe"></i> <?php _e( 'International', 'kuroneko' ) ?>
				<?php else : ?>
					<span class="my_kuroneko_cc__japan">●</span> <?php _e( 'Japan', 'kuroneko' ) ?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $this->member->is_removable( get_current_user_id(), $card->card_key, $card->international ? 'kuroneko_cc_intl' : 'kuroneko_cc' ) ) : ?>
					<a class="button delete-cc" href="#"
					   data-card-id="<?php echo esc_attr( $card->card_key ) ?>"
					   data-card-method="<?php echo esc_attr( $card->method ) ?>"><?php _e( 'Remove' ) ?></a>
				<?php else : ?>
					<span style="color: grey">
						&times; <?php esc_html_e( 'Undeletable', 'kuroneko' ) ?>
						<small> (<?php esc_html_e( 'Using', 'kuroneko' ) ?>)</small>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		$row = ob_get_contents();
		ob_end_clean();
		return $row;
	}

	/**
	 * Register REST request
	 *
	 * @param \WP_REST_Server $server Server instance.
	 */
	public function rest_api_init( $server ) {
		register_rest_route( 'kuroneko-pay/v1', 'card/?', [
			[
				'methods' => 'DELETE',
				'callback' => [ $this, 'delete_card' ],
				'permission_callback' => function( \WP_REST_Request $request ) {
					return current_user_can( 'read' );
				},
				'args' => [
					'kuroneko-card-key' => [
						'required' => true,
					],
					'method' => [
						'required' => true,
						'validate_callback' => function( $var ) {
							return false !== array_search( $var, [ 'kuroneko_cc', 'kuroneko_cc_intl' ] );
						},
					],
				],
			],
		] );
	}

	/**
	 * Delete credit card via AJAX.
	 *
	 * @param \WP_REST_Request $params
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function delete_card( $params ) {
		try {
			// Check if user can remove this card.
			$card_id = $params['kuroneko-card-key'];
			if ( ! $card_id || ! $this->member->is_removable( get_current_user_id(), $card_id, $params['method'] ) ) {
				throw new \Exception( __( 'This card is not deletable.', 'kuroneko' ), 500 );
			}
			$result = $this->member->delete_card( get_current_user_id(), $card_id, $params['method'] );
			if ( ! is_wp_error( $result ) ) {
				$json = [
					'success' => true,
					'html' => '',
				];
				foreach ( $this->member->get_cards( get_current_user_id() ) as $card ) {
					$json['html'] .= $this->render_card( $card );
				}
				return new \WP_REST_Response( $json );
			} else {
				throw new \Exception( $result->get_error_message(), $result->get_error_code() );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage(), [
				'status' => $e->getCode(),
			] );
		}
	}

	/**
	 * Validate ajax request
	 *
	 * @throws \Exception Check nonce and verify credentials.
	 */
	protected function validate_ajax() {
		// Check nonce.
		if ( ! $this->input->verify_nonce( 'kuroneko_cc' ) ) {
			throw new \Exception( __( 'Invalid access.', 'kuroneko' ), 403 );
		}
	}


	/**
	 * Getter
	 *
	 * @param string $name Key name.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'member':
				return Member::get_instance();
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}

}
