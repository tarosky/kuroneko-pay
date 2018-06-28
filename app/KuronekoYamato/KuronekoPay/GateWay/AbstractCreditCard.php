<?php
namespace KuronekoYamato\KuronekoPay\GateWay;


use Hametuha\HametWoo\Utility\Compatibility;
use Hametuha\HametWoo\Utility\Tools;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Master\Brands;
use KuronekoYamato\KuronekoPay\Master\Endpoint;
use KuronekoYamato\KuronekoPay\Member;
use KuronekoYamato\KuronekoPay\Pattern\AbstractApi;
use KuronekoYamato\KuronekoPay\Utility\GatewayFieldGroup;

/**
 * Kuroneko Creditcard API
 *
 * @package Hametuha\WooCommerce\Service\Webpay
 * @property-read Member      $member
 * @property-read OrderHelper $helper
 * @property-read bool        $has_access_key
 * @property-read bool        $has_option_service
 * @property-read \KuronekoYamato\KuronekoPay\API\CreditCard $api
 * @property-read string      $card_key
 */
abstract class AbstractCreditCard extends \WC_Payment_Gateway {

	use GatewayFieldGroup, Tools {
		Tools::__get as traitGet;
	}

	public $id = 'kuroneko_cc';

	public $access_key = '';

	static private $did_footer = false;

	/**
	 * Get
	 *
	 * @return AbstractApi
	 */
	abstract protected function get_api();

	/**
	 * Set title and description
	 *
	 * @return void
	 */
	protected function set_default_values(){
		$this->_title = __( 'Credit card', 'woocommerce' );
		$this->_description = __( 'Pay with your credit card via Kuroneko Web Collect.', 'kuroneko' );
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Setup default values
		$this->has_fields = true;
		$this->set_default_values();
		// Form fields.
		$this->init_form_fields();
		// Load the setting.
		$this->init_settings();
		// Get setting values.
		$this->title = $this->get_option( 'title', $this->_title );
		$this->description = $this->get_option( 'description', $this->_description );
		$this->enabled = $this->get_option( 'enabled', 'no' );
		$this->sandbox = $this->get_option( 'sandbox', 'yes' );
		$this->hidden = $this->get_option( 'hidden', 'no' );
		$this->trader_code = $this->get_option( 'trader_code', '' );
		$this->access_key = $this->get_option( 'access_key', '' );
		// Set up supports.
		$this->supports = [
			'products',
			'default_credit_card_form',
			'refunds',
		];
		if ( $this->has_option_service ) {
			$this->supports = array_merge( $this->supports, [
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_payment_method_change',
			] );
		}
		// Add hooks for admin panel.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		// Initialize credentials.
		if ( 'yes' === $this->enabled ) {
			if ( $this->trader_code ) {
				// Add credit card hoook.
				add_filter( 'woocommerce_credit_card_form_fields', [ $this, 'credit_card_form_fields' ], 10, 2 );
				add_action( 'woocommerce_credit_card_form_start', [ $this, 'credit_card_form_start' ] );
				add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_payment_gateway' ] );
				add_action( 'woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, [
					$this,
					'updated_failed_order',
				] );
				// Add hook for Recurring payment.
				if ( $this->has_option_service ) {
					add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [
						$this,
						'process_recurring_payment',
					], 10, 2 );
				}
				// Load assets for checkout page.
				add_action( 'wp_enqueue_scripts', function () {
					if ( is_checkout() ) {
						wp_enqueue_style( 'kuroneko-checkout', kuroneko_asset( '/css/checkout.css' ), [], $this->kuroneko_version );
						wp_enqueue_script( 'kuroneko-checkout', kuroneko_asset( '/js/checkout.js' ), [ 'jquery', 'jssha-sha256' ], $this->kuroneko_version, true );
					}
				} );
				// Add token script container.
				add_action( 'wp_footer', [ $this, 'render_token_container' ] );
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
		$form_fields['access_key'] = [
			'title'       => __( 'Access Key', 'kuroneko' ),
			'type'        => 'text',
			'description' => __( '6-7 digits on contract sheet or last numerics of access URL. To use subscription, Access key is required.', 'kuroneko' ),
			'default'     => '',
			'desc_tip'    => true,
		];
		$form_fields['hide_option_service'] = [
			'title'       => __( 'Option Service', 'kuroneko' ),
			'label'       => __( 'Do not use option service.', 'kuroneko' ),
			'type'        => 'checkbox',
			'description' => __( 'If you don\'t want to use option service, check this on.', 'kuroneko' ),
			'default'     => 'no',
			'desc_tip'    => true,
		];
		$form_fields['auth_div'] = [
			'title'       => __( 'Auth Div', 'kuroneko' ),
			'type'        => 'select',
			'description' => __( '3D secure is under development.', 'kuroneko' ),
			'default'     => '2',
			'options'     => [
			//	'1' => __( '3D secure', 'kuroneko' ),
				'2' => __( 'Security Code', 'kuroneko' ), // TODO: Add 3D secure
				// translators: %1$s and %2$s are option 1 and 2 above.
			//	'3' => sprintf( __( 'Both %1$s and %2$s', 'kuroneko' ), __( '3D secure', 'kuroneko' ), __( 'Security Code', 'kuroneko' ) ),
			],
			'desc_tip'    => true,
		];
		$this->form_fields = $form_fields;
	}

	/**
	 * Show description for sandbox
	 *
	 * @param string $id This ID.
	 */
	public function credit_card_form_start( $id ) {
		if ( $id === $this->id ) {
			if ( 'yes' === $this->sandbox ) {
			    $message = sprintf( '<strong>%s</strong>', esc_html( __( 'This is sandbox environment.', 'kuroneko' ) ) );
				/**
				 * kuroneko_sandbox_message
                 *
                 * @package KuronekoPay
                 * @since 1.0.0
                 * @param string $message Message displayed on sandbox mode.
				 * @param string $id      Payment Gateway ID
                 * @return string
				 */
			    $message = apply_filters( 'kuroneko_sandbox_message', $message, $this->id );
			    printf( '<p class="description description--kuroneko">%s</p>', $message );
			}
			if ( 'yes' === $this->hidden && current_user_can( 'edit_others_posts' ) ) {
				printf( '<p class="description description--kuroneko">%s</p>', esc_html__( 'This payment method is only visible for site admins.', 'kuroneko' ) );
			}
		}
	}

	/**
	 * Add credit card fields
	 *
	 * @param array $args Fields's argument.
	 * @param string $id ID of payment gateway.
	 *
	 * @return array
	 */
	public function credit_card_form_fields( $args, $id ) {
		if ( $id !== $this->id ) {
			return $args;
		}
		// Add token script.
		ob_start();
		$check_sum = $this->get_checksum( get_current_user_id() );
		?>
		<div class="kuroneko-cc-info">
			<p class="form-row form-row-wide form-row--kuroneko">
				<label for="kuroneko_cc-card-brand">
					<?php esc_html_e( 'Card Brand', 'kuroneko' ) ?>
					<span class="required">*</span>
				</label>
				<select name="kuroneko_cc-card-brand" id="kuroneko_cc-card-brand">
					<option value="0"><?php esc_html_e( 'Select Card Brand', 'kuroneko' ) ?></option>
					<?php foreach ( Brands::get_brand( 'kuroneko_cc_intl' == $this->id ) as $key => $label ) : ?>
						<option value="<?php echo $key ?>"><?php echo esc_html( $label ) ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<div class="kuroneko-cc-token-wrapper">
				<button class="kuroneko-cc-token-trigger" type="button"
						data-trader-cd="<?php echo esc_attr( $this->get_option( 'trader_code' ) ) ?>"
						data-auth-div="<?php echo esc_attr( $this->get_option( 'auth_div', 2 ) ) ?>"
						data-member-id="<?php echo is_user_logged_in() ? esc_attr( $this->member->get_member_id( get_current_user_id() ) ) : '' ?>"
						data-auth-key="<?php echo is_user_logged_in() ? esc_attr( $this->member->auth_key( get_current_user_id() ) ) : '' ?>"
						data-check-sum="<?php echo esc_attr( $check_sum ) ?>"
				>
					<span><?php esc_html_e( 'Enter Card Number', 'kuroneko' ) ?></span>
					<strong><?php esc_html_e( 'Card O.K.', 'kuroneko' ) ?></strong>
				</button>
				<input type="hidden" name="kuroneko_cc-token" value=""/>
			</div>
		</div>
		<?php
		$field = ob_get_contents();
		ob_end_clean();
		$args = [
			'token' => $field,
		];

		// If user can register card, show checkbox.
		if ( is_user_logged_in() && $this->has_option_service ) {

			$cards = $this->member->get_cards( get_current_user_id(), $this->id );
			if ( ! $cards ) {
				// User has no card.
				ob_start();
				?>
				<div class="kuroneko-cc-info">
					<p class="form-row form-row-wide form-row--kuroneko">
						<label>
							<?php esc_html_e( 'Register Card', 'kuroneko' ) ?>
							<?php if ( Compatibility::subscription_available() ) : ?>
								<small class="required">
									<?php esc_html_e( '* for Subscription', 'kuroneko' ) ?>
								</small>
							<?php endif; ?>
						</label>
						<label>
							<input type="checkbox" class="kuroneko-cc-save-card" name="<?php echo esc_attr( $this->id ) ?>-card-save" id="<?php echo esc_attr( $this->id ) ?>-card-save" checked
								   value="yes"/>
							<?php esc_html_e( 'Save this card for next checkout.', 'kuroneko' ) ?>
							<small><?php esc_html_e( 'NOTICE: Card number will never be saved.', 'kuroneko' ) ?></small>
						</label>
					</p>
				</div>
				<?php
				$args['kuroneko-card'] = ob_get_contents();
				ob_end_clean();
			} else {
				// User has card.
				ob_start();
				?>
					<hr class="form-row__line" />
					<p class="form-row__title--kuroneko">
						<?php esc_html_e( 'Or Pay With Registered Card', 'kuroneko' ) ?>
						<?php if ( Compatibility::subscription_available() ) : ?>
							<small class="required">
								<?php esc_html_e( '* for Subscription', 'kuroneko' ) ?>
							</small>
						<?php endif; ?>
					</p>
					<?php foreach ( $cards as $card ) : ?>
					<p class="form-row form-row-wide">
							<label class="form-row__input--kuroneko">
								<input type="checkbox" name="kuroneko_cc-card-select" class="kuroneko_cc-card-select"
									   data-card-key="<?php echo esc_attr( $card->card_key ) ?>"
									   data-card-no="<?php echo esc_attr( $card->masked_no ) ?>"
									   data-card-owner="<?php echo esc_attr( $card->owner ) ?>"
									   data-card-exp="<?php printf( '%02d%02d', $card->exp_month, $card->exp_year ) ?>"
									   data-last-credit-date="<?php echo mysql2date( 'YmdHis', $card->last_credit_date ) ?>"
									   value="1">
								<?php echo esc_html( $card->brand ) ?>
								**** <?php echo esc_html( $card->last4 ) ?>
								( <?php printf( '%02d', $card->exp_month ) ?>
								/ <?php echo esc_html( $card->exp_year ) ?> )
							</label>
					</p>
					<?php endforeach; ?>
				<?php
				$args['kuroneko-select'] = ob_get_contents();
				ob_end_clean();
			} // End if().
		} // End if().
		return $args;
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
		try {
			if ( Compatibility::subscription_available() ) {
				$has_recurring = \WC_Subscriptions_Order::order_contains_subscription( $order );
			} else {
				$has_recurring = false;
			}
			$use_token = true;
			$token = $this->input->post( 'kuroneko_cc-token' );
			$cc_save = 'yes' === $this->input->post( 'kuroneko_cc-card-save' ) || $has_recurring;
			$cc_registered = trim( $this->input->post( 'kuroneko_cc-card-select' ) );
			$cc_brand = trim( $this->input->post( 'kuroneko_cc-card-brand' ) );
			// Build params for Kuroneko Web Collect.
			$charge = [
				'device_div' => 2,
				'order_no'   => $transaction_id,
				'settle_price' => (int) $order->get_total(),
				'pay_way'  => 1,
				'description' => $transaction_id,
			];
			// Set names.
			$charge['buyer_name_kanji'] = $order->get_formatted_billing_full_name();
			$charge['buyer_tel'] = $order->get_billing_phone();
			$charge['buyer_email'] = $order->get_billing_email();
			// If credit card is specified, check it's validity.
			if ( $cc_registered ) {
				$use_token = false;
				//
				// Credit card is specified thus repeater.
				//
				if ( ! $this->member->can_use( $cc_registered, get_current_user_id(), $this->id ) ) {
					throw new \Exception( __( 'Specified card is not available.', 'kuroneko' ) );
				}
				$card = $this->member->get_card( get_current_user_id(), $cc_registered, $this->id );
				if ( ! $card ) {
					throw new \Exception( __( 'Specified card not found.' ), 404 );
				}
				$charge = array_merge( $charge, [
					'option_service_div' => '01',
					'auth_div' => 0,
					'member_id' => $this->member->get_member_id( get_current_user_id() ),
					'authentication_key' => $this->member->auth_key( get_current_user_id() ),
					'card_key' => $card->card_key,
					'last_credit_date' => mysql2date( 'YmdHis', $card->last_credit_date ),
				] );
			} else {
				// Token is required.
				if ( ! $token ) {
					throw new \Exception( __( 'Card token is empty. Please enter by clicking button.', 'kuroneko' ), 404 );
				}
				// Check card brand.
				if ( ! Brands::brand_exists( $cc_brand ) ) {
					throw new \Exception( __( 'Card brand is not set.', 'kuroneko' ), 404 );
				}
				// This is credit card transaction.
				$charge = array_merge( $charge, [
					'token'         => $token,
					'card_code_api' => Brands::brand_code( $cc_brand ),
				] );
				if ( $cc_save ) {
					// Save credit card.
					if ( ! is_user_logged_in() ) {
						// translators: %s is login URL.
						throw new \Exception( sprintf( __( 'To save credit card, you have to <a href="%s">log in</a>.', 'kuroneko' ), wc_get_checkout_url() ) );
					}
					// Check if card exists.
					$cards = $this->member->get_cards( get_current_user_id(), $this->id );
					if ( $cards ) {
						// translators: %s is account URL.
						throw new \Exception( __( 'You have already 1 card and no more card can be saved. To add new card, please go to your <a href="%s">account page</a>.', 'kuroneko' ), wc_get_account_endpoint_url( 'kuroneko-cards' ) );
					}
					// O.K. card can be saved.
					$cc_registered = 1;
					$charge = array_merge( $charge, [
						'member_id' => $this->member->get_member_id( get_current_user_id() ),
						'authentication_key' => $this->member->auth_key( get_current_user_id() ),
					] );
				}
			} // End if().
			if ( $has_recurring && ! $cc_registered ) {
				throw new \Exception( __( 'You should register credit card for subscription.', 'kuroneko' ), 400 );
			}
			// Do transaction if total amount more than 0.
			if ( $charge['settle_price'] > 0 ) {
				$response = $this->api->authorize( $charge, $use_token );
				if ( is_wp_error( $response ) ) {
					throw new \Exception( $response->get_error_message() );
				}
				add_post_meta( $order_id, '_woocommerce_kuroneko_auth_code', (string) $response->crdCResCd );
			}

			// Save credit card number.
			if ( $cc_registered ) {
				add_post_meta( $order_id, $this->card_key, $cc_registered );
			}
			$order->add_order_note( __( 'Payment is authorized.', 'kuroneko' ) );

			if ( $this->helper->has_shipping( $order ) ) {
				$order->payment_complete( $transaction_id );
			} else {
				// If this is virtual order, automatically capture.
				$result = $this->helper->capture_order( $order, '0', false );
				if ( is_wp_error( $result ) ) {
					// Failed capture.
					$order->add_order_note( __( 'Failed to capture.', 'kuroneko' ) . ' : ' . $result->get_error_message() );
					$order->payment_complete( $transaction_id );
				} else {
					// Success.
					$order->update_status( 'completed' );
				}
			}
		} catch ( \Exception $e ) {
			wc_add_notice( 'Error: ' . $e->getMessage(), 'error' );
			return [];
		}
		WC()->cart->empty_cart();

		return [
			'result' => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Let's try recurring
	 *
	 * @param float          $amount_to_charge Amount to charge.
	 * @param \WC_Order|int $order Order object.
	 */
	public function process_recurring_payment( $amount_to_charge, $order ) {
		try {
			// Get user ID.
			$order = wc_get_order( $order );
			if ( ! ( $user_id = $order->get_user_id() ) ) {
				throw new \Exception( __( 'User of this subscription not found.', 'kuroneko' ) );
			}
			// Get saved card.
			$subscription_id = get_post_meta( $order->get_id(), '_subscription_renewal', true );
			$original_subscription = get_post( $subscription_id );
			if ( 'shop_subscription' != $original_subscription->post_type || ! $original_subscription->post_parent ) {
				throw new \Exception( __( 'Parent subscription not found.', 'kuroneko' ), 404 );
			}
			$card_seq = get_post_meta( $original_subscription->post_parent, $this->card_key, true );
			if ( ! $card_seq || ! ( $card = $this->member->get_card( $order->get_user_id(), $card_seq, $this->id ) ) ) {
				throw new \Exception( sprintf( __('Subscription #%d has no credit card information.', 'kuroneko' ), $order->get_id() ), 400 );
			}
			// Try Transaction.
			$transaction_id = 'kuroneko-' . $order->get_id() . '-' . time();
			// Do recursive payment.
			$charge = [
				'device_div' => 2,
				'order_no'   => $this->helper->order_key( $order ),
				'settle_price' => (int) $amount_to_charge,
				'auth_div' => 0,
				'pay_way'  => 1,
				'description'        => $transaction_id,
				'option_service_div' => '01',
				'buyer_name_kanji'   => $order->get_formatted_billing_full_name(),
				'buyer_tel'          => $order->get_billing_phone(),
				'buyer_email'        => $order->get_billing_email(),
				'member_id' => $this->member->get_member_id( $order->get_user_id() ),
				'authentication_key' => $this->member->auth_key( $order->get_user_id() ),
				'card_key' => $card_seq,
				'last_credit_date' => mysql2date( 'YmdHis', $card->last_credit_date ),
			];
			$response = $this->api->authorize( $charge );
			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message(), $response->get_error_code() );
			}
			\WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			if ( ! $this->helper->has_shipping( $order ) ) {
				// This is not a shipping order, capture immediately.
				$result = $this->helper->capture_order( $order, '0', false );
				if ( is_wp_error( $result ) ) {
					// Failed capture.
					$order->add_order_note( sprintf( '[%s] %s', $result->get_error_code(), $result->get_error_message() ) );
				} else {
					// Success.
					$order->update_status( 'completed' );
				}
			}
		} catch ( \Exception $e ) {
			$order->add_order_note( sprintf( '[%s] %s', $e->getCode(), $e->getMessage() ) );
			$order->update_status( 'failed' );
			\WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
		}
	}

	/**
	 * Process a refund if supported
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Amount.
	 * @param  string $reason Reason.
	 *
	 * @return  boolean|\WP_Error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order = wc_get_order( $order_id );
			$tran_id = $this->helper->order_key( $order );
			if ( ! $tran_id ) {
				throw new \Exception( __( 'This transaction has no card information.', 'kuroneko' ) );
			}
			$current_price = $order->get_total() - $order->get_total_refunded();
			if ( $current_price <= 0 ) {
				// This is total payment.
				$result = $this->api->cancel( $tran_id );
			} else {
				// Partial payment.
				$result = $this->api->change_price( $tran_id, $current_price );
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				return true;
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}
	
	/**
	 * Render script container.
	 */
	public function render_token_container() {
		if ( ! is_checkout() ) {
			return;
		}
		if ( self::$did_footer ) {
			return;
		}
		// Check if already rendered.
		self::$did_footer = true;
		$src = ( 'yes' === $this->sandbox ) ? 'https://ptwebcollect.jp/test_gateway/token/js/tokenlib.js' : 'https://api.kuronekoyamato.co.jp/api/token/js/tokenlib.js';
		?>
		<div style="display:none;">
			<button type="button" id="create-token-launch">Button</button>
			<script type="text/javascript"
					class="webcollect-token-api" src="<?php echo esc_attr( $src ) ?>"
					data-trader-cd=""
					data-auth-div=""
					data-member-id=""
					data-auth-key=""
					data-check-sum="aaaaaaaaaa"
					data-opt-serv-div="00"
					data-callback="KuronekoCallback"></script>
		</div>
		<?php
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
		} elseif ( WC()->customer && ! $this->is_available_for( WC()->customer ) ) {
			unset( $gateways[ $this->id ] );
		}
		return $gateways;
	}

	/**
	 * Get checksum
	 *
	 * @param int $user_id
	 *
	 * @return false|string
	 */
	public function get_checksum( $user_id = 0 ) {
		if ( $user_id ) {
			$member_id = Member::get_instance()->get_member_id( $user_id );
			$key       = Member::get_instance()->auth_key( $user_id );
		} else {
			$member_id = '';
			$key = '';
		}
		$string = [
			$member_id,
			$key,
			$this->get_option( 'access_key' ),
			$this->get_option( 'auth_div', 2 ),
		];
		$string = implode( '', $string );
		return hash( 'sha256', $string );
	}

	/**
	 * Detect if this order is available.
	 *
	 * @param \WC_Customer $customer Current customer.
	 *
	 * @return bool
	 */
	abstract protected function is_available_for( $customer );


	/**
	 * Getter.
	 *
	 * @param string $name Key.
	 *
	 * @return mixed.
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'member':
				return Member::get_instance();
				break;
			case 'helper':
				return OrderHelper::get_instance();
				break;
			case 'api':
				return $this->get_api();
				break;
			case 'has_access_key':
				return '' !== $this->access_key;
				break;
			case 'has_option_service':
				return 'yes' !== $this->get_option( 'hide_option_service', 'no' );
				break;
			case 'card_key':
				return '_' . $this->id . '_card_key';
				break;
			default:
				return $this->traitGet( $name );
				break;
		}
	}
}
