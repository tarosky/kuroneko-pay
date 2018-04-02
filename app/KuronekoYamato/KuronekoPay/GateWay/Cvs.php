<?php
namespace KuronekoYamato\KuronekoPay\GateWay;


use Hametuha\HametWoo\Utility\Compatibility;
use Hametuha\HametWoo\Utility\Tools;
use KuronekoYamato\KuronekoPay\API\CvsApi;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Master\CvsInfo;
use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Utility\GatewayFieldGroup;

/**
 * Pay.JP PAYMENT GATEWAY
 *
 * @package KuronekoPay
 * @property-read OrderHelper $helper
 * @property-read bool        $has_access_key
 * @property-read CvsApi $api
 */
class Cvs extends \WC_Payment_Gateway {

	use GatewayFieldGroup, Tools {
		Tools::__get as traitGet;
	}

	public $id = CvsInfo::ID;

	public $allowed_cvs = [];

	public $fee = CvsInfo::FEE;

	public $limit_days = 7;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->method_title = __( 'Convenience Store', 'kuroneko' );
		$this->method_description = __( 'Convenience Store\'s GateWay via Kuroneko Web Collect. You have to create account.', 'kuroneko' );
		$this->_title = __( 'Convenience Store', 'kuroneko' );
		$this->_description = __( 'Pay at Convenience Store.', 'kuroneko' );

		// Form fields.
		$this->init_form_fields();
		// Load the setting.
		$this->init_settings();
		// Get setting values.
		$this->title = $this->get_option( 'title', __( 'Convenience Store', 'kuroneko' ) );
		$this->description = $this->get_option( 'description', __( 'Pay at Convenience Store.', 'kuroneko' ) );
		$this->sandbox = $this->get_option( 'sandbox', 'yes' );
		$this->hidden = $this->get_option( 'hidden', 'no' );
		$this->trader_code = $this->get_option( 'trader_code', '' );
		$this->allowed_cvs = $this->get_option( 'allowed_cvs', [] );
		$this->fee = $this->get_option( 'fee', CvsInfo::FEE );
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
			if ( $this->trader_code && $this->allowed_cvs ) {
				// Filter Payment Gateway.
				add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_payment_gateway' ] );
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
		// Set CVS
		$cvs = [];
		foreach ( CvsInfo::get_all() as $code => $ids ) {
			$cvs[ $code ] = implode( ', ', array_map( function( $id ) {
				return CvsInfo::label( $id );
			}, $ids ) );
		}
		$form_fields['allowed_cvs'] = [
				'title' => __( 'Available Stores', 'kuroneko' ),
				'type'  => 'multiselect',
				'description' => __( 'Please choose available store brands. You can select multiple with Shift + Click.', 'kuroneko' ),
				'default' => [],
				'options' => $cvs,
		];
		$form_fields['fee'] = [
			'title' => __( 'Payment Fee Charge', 'kuroneko' ),
			'type'  => 'text',
			'description' => __( 'CVS payment will requires fee. If you want charge it to your customer, enter price and it will be appended to cart amount.', 'kuroneko' ),
			'default' => CvsInfo::FEE,
		];
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
		$cvs = [];
		foreach ( CvsInfo::get_all() as $code => $label ) {
			if ( false !== array_search( $code, $this->allowed_cvs ) ) {
				$cvs[$code] = array_map( function( $id ) {
					return CvsInfo::label( $id );
				}, $label );
			}
		}
		if ( 'yes' === $this->hidden ) {
			printf( '<p class="description">%s</p>', esc_html__( 'This payment method is only visible for site admins.', 'kuroneko' ) );
		}
		?>
		<p class="form-row form-row-wide form-row--kuroneko_cvs">
			<?php foreach ( $cvs as $code => $label ) : ?>
			<label class="kuroneko_cvs_label">
				<input type="radio" name="kuroneko_cvs" value="<?php echo esc_attr( $code ) ?>" />
				<?php echo esc_html( implode( ', ', $label ) ) ?>
			</label>
			<?php endforeach; ?>
		</p>
		<?php if ( $fee = CvsInfo::get_current_fee() ) : ?>
		<p class="kuroneko_description">
			<?php printf( __( 'You will be charged &yen;%s for payment fee.', 'kuroneko' ), number_format_i18n( $fee ) ) ?>
		</p>
		<?php endif; ?>
		<p class="kuroneko_description">
			<?php printf( __( 'You have to finish payment until %s.', 'kuroneko' ), mysql2date( get_option( 'date_format' ), $this->get_limit_date() ) ) ?>
		</p>

		<?php
	}

	/**
	 * Validate CVS
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$code = isset( $_POST['kuroneko_cvs'] ) ? $_POST['kuroneko_cvs'] : null;
		if ( ! $code ) {
			wc_add_notice( __( 'CVS is not selected.', 'kuroneko' ), 'error' );
			return false;
		}
		if ( false === array_search( $code, $this->allowed_cvs ) ) {
			wc_add_notice( __( 'Sorry, but specified CVS is not available.', 'kuroneko' ), 'error' );
			return false;
		}
		if ( 'B02' == $code ) {
			// Check if yomigana exists.
			foreach ( [
				'billing_yomigana_first_name',
				'billing_yomigana_last_name'
			] as $key ) {
				$yomi = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';
				if ( empty( $yomi ) || ! preg_match( '/^[ァ-ヶー]+$/u', $yomi ) ) {
					wc_add_notice( __( 'Family Mart requires yomigana of your name. They should be katakana.', 'kuroneko' ), 'error' );
					return false;
				}
			}
		}

		return true;
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
		$code = $post_data['kuroneko_cvs'];
		try {
			// Build params for Kuroneko Web Collect.
			$charge = [
				'order_no'   => $transaction_id,
				'goods_name' => '',
				'settle_price' => (int) $order->get_total(),
				'buyer_name_kanji' => $order->get_formatted_billing_full_name(),
				'buyer_tel' => $order->get_billing_phone(),
				'buyer_email' => $order->get_billing_email(),
			];
			// Add product name.
			$all_products = $this->get_all_products_in( $order );
			$name = '';
			foreach ( $all_products as $product ) {
				/* @var \WC_Product $product */
				$name = $product->get_formatted_name();
				break;
			}
			if ( 1 < count( $all_products ) ) {
				$name = sprintf( __( '%s and etc', 'kuroneko' ), $name );
			}
			// customize request
			switch ( $code ) {
				case 'B02':
					$charge['goods_name'] = $name;
					$charge['buyer_name_kana'] = mb_substr( "{$post_data['billing_yomigana_last_name']}{$post_data['billing_yomigana_first_name']}", 0, 15, 'utf-8');
					break;
				case 'B03':
					$charge['goods_name'] = $name;
					break;
				case 'B01':
				default:
					// Do nothing
					break;
			}

			// Do transaction if total amount more than 0.
			$response = $this->api->register( $code, $charge );
			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message(), $response->get_error_code() );
			}
			// Save cvs code.
			add_post_meta( $order_id, '_kuroneko_cvs_code', $code );
			// Save limitation.
			add_post_meta( $order_id, '_kuroneko_cvs_expired_date', preg_replace( '#(\d{4})(\d{2})(\d{2})#u', '$1-$2-$3 23:59:59', (string) $response->expiredDate ) );
			switch ( $code ) {
				case 'B01':
					add_post_meta( $order_id, '_kuroneko_cvs_billing_no', (string) $response->billingNo );
					add_post_meta( $order_id, '_kuroneko_cvs_billing_url', (string) $response->billingUrl );
					break;
				case 'B02':
					add_post_meta( $order_id, '_kuroneko_cvs_billing_company', (string) $response->companyCode );
					add_post_meta( $order_id, '_kuroneko_cvs_billing_nof', (string) $response->orderNoF );
					break;
				case 'B03':
					add_post_meta( $order_id, '_kuroneko_cvs_billing_econ', (string) $response->econNo );
					break;
				default:
					// Do nothing.
					break;
			}
			$order->add_order_note( __( 'CVS Payment is registered.', 'kuroneko' ) );
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
	 * Filter payment gateway with billing country
	 *
	 * @param array $gateways
	 *
	 * @return mixed
	 */
	public function filter_payment_gateway( $gateways ) {
		if ( 'yes' === $this->hidden && ! current_user_can( 'edit_others_posts' ) ) {
			unset( $gateways[ $this->id ] );
		} elseif ( is_checkout() && WC()->customer && $this->helper->is_international( WC()->customer ) ) {
			unset( $gateways[ $this->id ] );
		}
		return $gateways;
	}

	/**
	 * Show URL
	 */
	public function show_admin_url( ) {
		if ( CvsInfo::ID == $this->input->get( 'section' ) ) {
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
				return CvsApi::get_instance();
				break;
			default:
				return $this->traitGet( $name );
				break;
		}
	}
}
