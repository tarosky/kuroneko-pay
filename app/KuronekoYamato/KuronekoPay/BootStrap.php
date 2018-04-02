<?php
namespace KuronekoYamato\KuronekoPay;

use Hametuha\HametWoo;
use KuronekoYamato\KuronekoPay\Commands\KuronekoCli;
use KuronekoYamato\KuronekoPay\Helper\OrderHelper;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;
use KuronekoYamato\KuronekoPay\Rest\Payment;
use KuronekoYamato\KuronekoPay\UI\CardList;
use KuronekoYamato\KuronekoPay\UI\Admin;
use KuronekoYamato\KuronekoPay\UI\OrderReview;
use KuronekoYamato\KuronekoPay\Utility\Option;
use KuronekoYamato\KuronekoPay\Validator\Requirement;

/**
 * Plugin's bootStrap
 *
 * @package KuronekoPay
 */
class BootStrap extends Singleton {

	/**
	 * Constructor
	 *
	 * @param array $setting Settings array.
	 */
	protected function __construct( array $setting = [] ) {
		$errors = Requirement::validate();
		if ( is_wp_error( $errors ) ) {
			add_action( 'admin_notices', function () use ( $errors ) {
				printf( '<div class="error"><p>[Kuroneko Web Collect Error]%s</p></div>', implode( '<br />', array_map( function( $message ) {
					return wp_kses( $message, [
						'a' => [ 'href', 'target' ],
					    'strong' => [],
					] );
				}, $errors->get_error_messages() ) ) );
			} );
		} else {
			HametWoo::init();
			// If CLI, register commands.
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				\WP_CLI::add_command( KuronekoCli::COMMAND_NAME, 'KuronekoYamato\\KuronekoPay\\Commands\\KuronekoCli' );
			}
			// Add gate way.
			add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
				$methods[] = 'KuronekoYamato\\KuronekoPay\\GateWay\\CreditCard';
				$methods[] = 'KuronekoYamato\\KuronekoPay\\GateWay\\CreditCardIntl';
				$methods[] = 'KuronekoYamato\\KuronekoPay\\GateWay\\Cvs';
//				$methods[] = 'KuronekoYamato\\KuronekoPay\\GateWay\\NetBank';
				return $methods;
			} );
			// Shipping helper.
			OrderHelper::get_instance();
			// Add custom emails.
			HametWoo\Custom\Email::activate();
			// Payment fee helper.
			OrderReview::get_instance();
			// Add REST API for capturing.
			Payment::get_instance();
			// Scripts.
			add_action( 'admin_enqueue_scripts', function() {
				wp_enqueue_script( 'kuroneko-admin', kuroneko_asset( '/js/kuroneko-admin.js' ), [ 'jquery' ], $this->version );
				wp_localize_script( 'kuroneko-admin', 'KuronekoAdmin', [
					'rest_route' => rest_url( 'kuroneko-pay/v1' ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'loading'    => __( 'Requesting...', 'kuroneko' ),
					'successMsg' => __( 'Refresh this page to see latest information? Unsaved information will be lost.', 'kuroneko' ),
					'confirm'    => __( 'Are you sure to change tracking no? You might notify this change to customer.', 'kuroneko' ),
					'error'      => _x( 'Server doesn\'t respond. Please contact to server admin.', 'rest_request', 'kuroneko' ),
				] );
				wp_enqueue_style( 'wc-yamato-admin', kuroneko_asset( '/css/admin.css' ), [], $this->version );
			} );
			add_action( 'init', function () {
				// Register SHA 256.
				wp_register_script( 'jssha-sha256', kuroneko_asset( '/js/sha256.js' ), [], '2.3.1', true );
			} );
			// Add admin.
			Admin::get_instance();
			if ( Option::get_instance()->access_key ) {
				// Add Credit Card Manager.
				CardManager::get_instance();
				// Add Tab content.
				CardList::get_instance();
			}
		}
	}


}
