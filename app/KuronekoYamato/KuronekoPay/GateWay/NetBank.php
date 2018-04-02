<?php
namespace KuronekoYamato\KuronekoPay\GateWay;


use Hametuha\HametWoo\Utility\Compatibility;
use Hametuha\HametWoo\Utility\Tools;
use KuronekoYamato\KuronekoPay\API\NetBankApi;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Utility\GatewayFieldGroup;

/**
 * Pay.JP PAYMENT GATEWAY
 *
 * @package KuronekoPay
 * @property-read OrderHelper $helper
 * @property-read NetBankApi  $api
 */
class NetBank extends \WC_Payment_Gateway {

	use GatewayFieldGroup, Tools {
		Tools::__get as traitGet;
	}

	public $id = 'kuroneko_nb';

	public $trader_code = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->method_title = 'Rakuten Bank';
		$this->method_description = __( 'Rakuten Bank GateWay. Kuroneko Web Payment account is required.', 'kuroneko' );
		$this->_title = __( 'Rakuten Bank', 'kuroneko' );
		$this->_description = __( 'Pay at Rakuten Bank', 'kuroneko' );

		// Form fields.
		$this->init_form_fields();
		// Load the setting.
		$this->init_settings();
		// Get setting values.
		$this->title = $this->get_option( 'title', $this->_title );
		$this->description = $this->get_option( 'description', $this->_description );
		$this->sandbox = $this->get_option( 'sandbox', 'yes' );
		$this->hidden = $this->get_option( 'hidden', 'no' );
		$this->trader_code = $this->get_option( 'trader_code', '' );
		$this->limit_days = max( 1, (int) $this->get_option( 'limit', 7 ) );
		// Set up supports.
		$this->supports = [
			'products',
		];
		// Add hooks for admin panel.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		// Add URL indicator
		add_action( 'woocommerce_settings_checkout', [ $this, 'show_admin_url' ], 20 );
		// Initialize credentials.
		if ( 'yes' === $this->enabled ) {
			if ( $this->trader_code ) {
				// Load assets for checkout page.
				add_action( 'wp_enqueue_scripts', function () {
					if ( is_checkout() ) {
						wp_enqueue_style( 'kuroneko-checkout', kuroneko_asset( '/css/checkout.css' ), [], $this->kuroneko_version );
						wp_enqueue_script( 'kuroneko-checkout', kuroneko_asset( '/js/checkout.js' ), [ 'jquery' ], $this->kuroneko_version, true );
					}
				} );
			} else {
				add_action( 'admin_notices', function () {
					printf( '<div class="error"><p><strong>Kuroneko Web Collect: </strong>%s</p></div>', esc_html__( 'Trader code is required.', 'kuroneko' ) );
				} );
			}
		}
	}

	/**
	 * Initialize form fields
	 */
	public function init_form_fields() {
		$form_fields = $this->common_fields();
		$form_fields['limit'] = [
			'title' => __( 'Limit Days', 'kuroneko' ),
			'type'  => 'text',
			'description' => __( 'Transaction will be canceled after the specified days. Must be more than 0.', 'kuroneko' ),
			'default' => 7,
		];
		// Set value
		$this->form_fields = $form_fields;
	}

	/**
	 * Show CVS fields
	 */
	public function payment_fields(){
		parent::payment_fields();
		?>
		<p class="kuroneko_description">
			<?php esc_html_e( 'After check out, you will find a link to Rankuten Bank on order detail page. Please transfer order amount there.', 'kuroneko' ); ?>
			<?php printf( __( 'You have to finish payment until %s.', 'kuroneko' ), mysql2date( get_option( 'date_format' ), $this->get_limit_date() ) ) ?>
		</p>
		<?php
	}

	/**
	 * Show process order
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$transaction_id = $this->helper->order_key( $order );
		$post_data = $this->get_post_data();
		try {
			// Build params for Kuroneko Web Collect.
			$charge = [
				'goods_name'   => '',
				'settle_price' => (int) $order->get_total(),
				'buyer_name_kanji' => $order->get_formatted_billing_full_name(),
				'buyer_email' => $order->billing_email,
				'return_url'  => $this->get_return_url( $order ),
			];
			// Do transaction if total amount more than 0.
			$response = $this->api->register( $transaction_id, $charge );
			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message(), $response->get_error_code() );
			}
			// Save link.
			$html = (string) $response->requestHtml;
			add_post_meta( $order_id, '_kuroneko_bank_html', $html );
			// Save limitation.
			add_post_meta( $order_id, '_kuroneko_nb_expired_date', $this->get_limit_date() );
			// Update status and redirect to rakuten.
            $order->set_transaction_id( $transaction_id );
			$order->update_status( 'on-hold', __( 'Awaiting CVS payment complete.', 'kuroneko' ) );
            wc_reduce_stock_levels( $order->get_id() );
			WC()->cart->empty_cart();
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		} catch ( \Exception $e ) {
			wc_add_notice( 'Error: ' . $e->getMessage(), 'error' );
			return [];
		}
	}

	/**
	 * Show URL
	 */
	public function show_admin_url( ) {
		if ( $this->id == $this->input->get( 'section' ) ) {
			$url = get_option( 'rewrite_rules' ) ? home_url( '/wc-api/kuroneko-cvs/' ) : add_query_arg( [
				'wc-api' => 'kuroneko-cvs',
			], home_url() );
			printf( __( '<p>Payment Notification URL: <code>%s</code></p>', 'kuroneko' ), esc_html( $url ) );
		}
	}

	/**
	 * Getter.
	 *
	 * @param string $name Key.
	 *
	 * @return mixed.
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'helper':
				return OrderHelper::get_instance();
				break;
			case 'api':
				return NetBankApi::get_instance();
				break;
			default:
				return $this->traitGet( $name );
				break;
		}
	}
}
