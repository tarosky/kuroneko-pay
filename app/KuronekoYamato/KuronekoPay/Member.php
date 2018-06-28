<?php
namespace KuronekoYamato\KuronekoPay;

use Hametuha\HametWoo\Utility\Compatibility;
use KuronekoYamato\KuronekoPay\API\CreditCard;
use KuronekoYamato\KuronekoPay\API\CreditCardIntl;
use KuronekoYamato\KuronekoPay\Master\Brands;
use KuronekoYamato\KuronekoPay\Pattern\Singleton;

/**
 * Member function
 *
 * @package Hametuha\WooCommerce\Service\Webpay
 * @property CreditCard $api
 */
class Member extends Singleton {

	/**
	 * Get registered cards
	 *
	 * @param int    $user_id WordPress' user id.
	 * @param string $method kuroneko_cc or kuroneko_cc_intl.
	 *
	 * @return array
	 */
	public function get_cards( $user_id, $method = '' ) {
		$results = [];
		switch ( $method ) {
			case 'kuroneko_cc':
			case 'kuroneko_cc_intl':
				$results[ $method ] = $this->get_api( $method )->get_cards( $this->get_member_id( $user_id ), $this->auth_key( $user_id ) );
				break;
			default:
				foreach ( [ 'kuroneko_cc', 'kuroneko_cc_intl' ] as $index => $method_id ) {
					$results[ $method_id ] = $this->get_api( $method_id )->get_cards( $this->get_member_id( $user_id ), $this->auth_key( $user_id ) );
				}
				break;
		}
		$registered = [];
		foreach ( $results as $method_id => $cards ) {
			if ( is_wp_error( $cards ) ) {
				if ( WP_DEBUG ) {
					/* @var \WP_Error $cards */
					error_log( $cards->get_error_message() );
				}
				continue;
			} elseif ( 0 === (int) $cards->cardUnit ) {
				continue;
			} else {
				foreach ( $cards->cardData as $card ) {
					preg_match( '#(\d{2})(\d{2})#', (string) $card->cardExp, $matches );
					list( $whole, $month, $year ) = $matches;
					$code                            = (int) $card->cardCodeApi;
					$registered[] = (object) [
						'card_key'         => (string) $card->cardKey,
						'last4'            => str_replace( '*', '', (string) $card->maskingCardNo ),
						'masked_no'        => (string) $card->maskingCardNo,
						'exp_month'        => $month,
						'exp_year'         => $year,
						'code'             => $code,
						'brand'            => Brands::get_brand_label( $code ),
						'last_credit_date' => preg_replace( '#(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})#', '$1-$2-$3 $4:$5:$6', (string) $card->lastCreditDate ),
					    'international'    => 'kuroneko_cc_intl' == $method_id,
					    'method'           => $method_id,
						'owner'            => (string) $card->cardOwner,
					];
				}
			}
		}
		return $registered;
	}

	/**
	 * Get specific card.
	 *
	 * @param int    $user_id WordPress' user ID.
	 * @param string $card_id Card id.
	 *
	 * @return \stdClass
	 */
	public function get_card( $user_id, $card_id, $method = '' ) {
		foreach ( $this->get_cards( $user_id, $method ) as $card ) {
			if ( $card->card_key === $card_id ) {
				return $card;
			}
		}
		return null;
	}

	/**
	 * Get member ID from user ID.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string
	 */
	public function get_member_id( $user_id ) {
		/**
		 * kuroneko_member_id
		 *
		 * @param string $member_id
		 * @param int    $user_id
		 * @package KuronekoPay
		 * @since 1.0.0
		 */
		return apply_filters( 'kuroneko_member_id', sprintf( 'wc-%d', $user_id ), $user_id );
	}

	/**
	 * Get user's auth key
	 *
	 * @param int $user_id
	 *
	 * @return mixed|string
	 */
	public function auth_key( $user_id ) {
		$auth_key = get_user_meta( $user_id, 'kuroneko_auth_key', true );
		if ( ! $auth_key ) {
			$auth_key = substr( uniqid(), 0, 8 );
			update_user_meta( $user_id, 'kuroneko_auth_key', $auth_key );
		}
		return $auth_key;
	}

	/**
	 * Detect if card is removable.
	 *
	 * @param int    $user_id WordPress's user_id.
	 * @param string $card_id Card ID.
	 * @param string $method  Payment method.
	 * @return bool
	 */
	public function is_removable( $user_id, $card_id, $method ) {
		static $orders = null;
		// No subscription exists, so delete this.
		if ( ! Compatibility::subscription_available() ) {
			return true;
		}
		if ( is_null( $orders ) ) {
			$orders = wcs_get_users_subscriptions( $user_id );
		}
		foreach ( $orders as $subscription ) {
			/** @var \WC_Order $order */
			$order    = $subscription->get_parent();
			if ( 'active' !== $subscription->get_status() || $order->get_payment_method() != $method ) {
				// This is not valid subscription.
				continue;
			}
			if ( (string) get_post_meta( $order->get_id(), "_{$method}_card_key", true ) === (string) $card_id ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Save card
	 *
	 * @param int    $user_id WordPress' user id.
	 * @param array  $card Card data array.
	 * @param string $method
	 *
	 * @throws \Exception Failed to save card data.
	 * @return bool
	 */
	public function save_card( $user_id, $card, $method ) {
		if ( ! ( $user = get_userdata( $user_id ) ) ) {
			throw new \Exception( __( 'User doesn\'t exist.', 'kuroneko' ) );
		}
		// Check if card slot empty
		$cards = $this->get_cards( $user->ID, $method );
		if ( 1 <= count( $cards ) ) {
			throw new \Exception( __( 'You have already 1 card saved.', 'kuroneko' ), 400 );
		}
		// Save card information.
		$transaction_id = strtoupper( uniqid( 'wc-cc-' ) );
		$response = $this->get_api( $method )->authorize( array_merge( [
			'device_div' => 2,
			'order_no'   => $transaction_id,
			'settle_price' => 1,
			'auth_div' => 2,
			'pay_way'  => 1,
			'option_service_div' => '01',
			'description' => $transaction_id,
		    'buyer_name_kanji' => sprintf(
		    	'%s%s',
			    get_user_meta( $user->ID, 'billing_last_name', true ),
			    get_user_meta( $user->ID, 'billing_first_name', true )
		    ),
		    'buyer_tel'   => get_user_meta( $user->ID, 'billing_phone', true ),
		    'buyer_email' => $user->user_email,
		    'member_id'   => $this->get_member_id( $user_id ),
		    'authentication_key'    => $this->auth_key( $user_id ),
		], $card ) );
		// If failed, throw error
		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message(), $response->get_error_code() );
		}
		// Cancel transaction.
		$cancel = $this->get_api( $method )->cancel( $transaction_id );
		if ( is_wp_error( $cancel ) ) {
			// Card register is success, but transaction failed.
			error_log( '[Kuroneko]' . sprintf( '%s: Failed to cancel pseudo transaction %s.', 'kuroneko' ), $cancel->get_error_code(), $transaction_id );
		}
		// Return card information.
		return true;
	}

	/**
	 * Delete registered cards
	 *
	 * @param int    $user_id WordPress' user id.
	 * @param string $card_id Sequence number.
	 * @param string $method  kuroneko_cc or kuroneko_cc_intl
	 *
	 * @return bool|\WP_Error
	 */
	public function delete_card( $user_id, $card_id, $method ) {
		foreach ( $this->get_cards( $user_id, $method ) as $card ) {
			if ( $card->card_key === $card_id ){
				// Delete card
				$response = $this->get_api( $method )->delete_card( [
					'member_id' => $this->get_member_id( $user_id ),
				    'authentication_key' => $this->auth_key( $user_id ),
				    'last_credit_date' => mysql2date( 'YmdHis', $card->last_credit_date ),
				    'card_key' => $card_id,
				] );
				if ( is_wp_error( $response ) ) {
					return $response;
				} else {
					return true;
				}
			}
		}
		return new \WP_Error( 404, __( 'No card found.', 'kuroneko' ) );
	}

	/**
	 * Detect if user has access to card
	 *
	 * @param string $card_id Card ID.
	 * @param int    $user_id WordPress' user id.
	 * @param string $method_id
	 *
	 * @return bool
	 */
	public function can_use( $card_id, $user_id, $method_id ) {
		foreach ( $this->get_cards( $user_id, $method_id ) as $card ) {
			if ( $card_id == $card->card_key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get API
	 *
	 * @param string $id
	 *
	 * @return CreditCard|CreditCardIntl
	 */
	public function get_api( $id ) {
		switch ( $id ) {
			case 'kuroneko_cc_intl':
				return CreditCardIntl::get_instance();
				break;
			case 'kuroneko_cc':
			default:
				return $this->api;
				break;
		}
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'api':
				return CreditCard::get_instance();
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}

}
