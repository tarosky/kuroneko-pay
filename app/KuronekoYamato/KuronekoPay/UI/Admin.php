<?php

namespace KuronekoYamato\KuronekoPay\UI;


use KuronekoYamato\KuronekoPay\API\CreditCard;
use KuronekoYamato\KuronekoPay\API\CreditCardIntl;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;

/**
 * Admin screen
 *
 * @package KuronekoYamato\KuronekoPay\UI
 */
class Admin extends Singleton {

	protected $notices = [];

	/**
	 * Admin constructor.
	 */
	protected function __construct() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices', [ $this, 'contract_notice' ] );
		add_action( 'woocommerce_settings_checkout', [ $this, 'do_section' ], 20 );
		add_action( 'admin_init', function(){
			foreach ( [
				'contract' => sprintf(
					__( 'To activate Kuroneko Web Collect, please register account <a href="%s" target="_blank">here</a>.', 'kuroneko' ),
					'https://www.yamatofinancial.jp/wc/'
				),
			] as $key => $notice ) {
				$this->notices[ $key ] = $notice;
			}
			add_action( 'wp_ajax_kuroneko_dismiss_notice', [ $this, 'dismiss_notice' ] );
		} );
		add_action( 'wp_ajax_kuroneko_ip_check', [ $this, 'api_check' ] );
	}

	/**
	 * Render section message.
	 */
	public function do_section(){
		$id = $this->input->get( 'section' );
		if ( false === array_search( $id, [ 'kuroneko_cc', 'kuroneko_cc_intl' ] ) ) {
			return;
		}
		$last_result = wp_parse_args( get_option( $id . '_connection_status', [] ), [
			'success'      => false,
			'message'      => __( 'Connection to Kuroneko Web Collect API is never checked.', 'kuroneko' ),
			'last_updated' => false,
		]);
		?>
		<div class="kuroneko-connect">
			<p class="<?php echo $last_result['success'] ? 'kuroneko-connect-success' : 'kuroneko-connect-failure' ?>">
				<?php echo wp_kses( $last_result['message'], [ 'code' => [] ] ) ?>
				<small>
					<?php printf(
						__( 'Last Checked: %s', 'kuroneko' ),
						$last_result['last_updated'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),  $last_result['last_updated'] ) : 'N/A'
					); ?>
				</small>
				<?php ?>

				<?php ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=kuroneko_ip_check&id=' . $id ) ) ?>">
					<?php esc_html_e( 'Check', 'kuroneko' ) ?>
				</a>
				<br />
				<?php _e( '<strong>NOTICE: </strong>Connection check works only on Sandbox mode.', 'kuroneko' ) ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Detect if notice is dismissed.
	 *
	 * @param string $key Notice name
	 *
	 * @return bool
	 */
	public function notice_dismissed( $key ) {
		$option = (array) get_option( 'kuroneko_notice', [] );
		return ( isset( $option[ $key ] ) && $option[ $key ] );
	}

	/**
	 * Ajax action
	 */
	public function dismiss_notice() {
		try {
			if ( ! $this->input->verify_nonce( 'kuroneko_dismiss_notice' ) ) {
				throw new \Exception( __( 'Permission Error', 'kuroneko' ), 401 );
			}
			$key = $this->input->get( 'key' );
			if ( !( isset( $this->notices[ $key ]  ) && ! $this->notice_dismissed( $key ) ) ) {
				throw new \Exception( __( 'No message exists.', 'kuroneko' ), 400 );
			}
			$option = (array) get_option( 'kuroneko_notice', [] );
			$option[ $key ] = 1;
			update_option( 'kuroneko_notice', $option );
			wp_send_json_success( 'OK' );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}
	}

	/**
	 * Render message.
	 */
	public function contract_notice() {
		foreach ( $this->notices as $key => $message ) {
			if ( $this->notice_dismissed( $key ) ) {
				// Already dismissed.
				continue;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				continue;
			}
			$endpoint = wp_nonce_url( add_query_arg( [
				'action' => 'kuroneko_dismiss_notice',
				'key'    => $key,
			], admin_url( 'admin-ajax.php' ) ), 'kuroneko_dismiss_notice' );
			?>
			<div class="updated kuroneko-message">
				<button data-endpoint="<?php echo esc_url( $endpoint ) ?>" class="kuroneko-message-dismiss notice-dismiss"></button>
				<p>
					<?php echo wp_kses( $message, [
						'strong' => [],
						'a' => [
							'href'   => true,
							'target' => true,
						],
					] ) ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check API
	 */
	public function api_check() {
		$id = $this->input->get( 'id' );
		switch ( $id ) {
			case 'kuroneko_cc':
				$api = CreditCard::get_instance();
				break;
			case 'kuroneko_cc_intl':
				$api = CreditCardIntl::get_instance();
				break;
			default:
				wp_die( __( 'Payment Gateway ID is not set.', 'kuroneko' ), get_status_header_desc( 400 ), [ 'status' => 400 ] );
				break;

		}
		$response = $api->check_connection();
		$option = [
			'success' => false,
			'message' => '',
			'last_updated' => current_time( 'mysql' ),
		];
		if ( is_wp_error( $response ) ) {
			$option['message'] = $response->get_error_message();
		} else {
			$option['success'] = true;
			$option['message'] = sprintf( __( 'Connection from <code>%s</code> is valid.', 'kuroneko' ), $response );
		}
		update_option( $id . '_connection_status', $option );
		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id ) );
		exit;
	}

}
