<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * Endpoint master
 *
 * @since 1.0.0
 * @package KuronekoPay
 */
class Endpoint {

	const URL = 'https://api.kuronekoyamato.co.jp/api/';

	const SANDBOX = 'https://ptwebcollect.jp/test_gateway/';

	/**
	 * クレジット決済登録通常用
	 */
	const A01 = 'credit';

	/**
	 * クレジット決済登録3Dセキュア結果用
	 */
	const A02 = 'credit3D';

	/**
	 * クレジットカードのお預かり情報照会
	 */
	const A03 = 'creditInfoGet';

	/**
	 * クレジットカードのお預かり情報変更
	 */
	const A04 = 'creditInfoUpdate';

	/**
	 * クレジットカードのお預かり情報削除
	 */
	const A05 = 'creditInfoDelete';

	/**
	 * クレジット決済取消
	 */
	const A06 = 'creditCancel';

	/**
	 * クレジット金額変更
	 */
	const A07 = 'creditChangePrice';

	/**
	 * クレジットトークン決済
	 */
	const A08 = 'creditToken';

	/**
	 * コンビニ(オンライン払い)セブン-イレブ ン
	 */
	const B01 = 'cvs1';

	/**
	 * コンビニ(オンライン払い)ファミリーマー ト
	 */
	const B02 = 'cvs2';

	/**
	 * コンビニ(オンライン払い)ローソン等 (※1)
	 */
	const B03 = 'cvs3';

	/**
	 * 電子マネー決済登録(楽天 Edy(Cyber))
	 */
	const C01 = 'e_money1';

	/**
	 * 電子マネー決済登録(楽天 Edy(Mobile))
	 */
	const C02 = 'e_money2';

	/**
	 * 電子マネー決済登録(Suica(インターネットサービス))
	 */
	const C03 = 'e_money3';

	/**
	 * 電子マネー決済登録(Suica(Mobile))
	 */
	const C04 = 'e_money4';

	/**
	 * 電子マネー決済登録(WAON(PC))
	 */
	const C05 = 'e_money5';

	/**
	 * 電子マネー決済登録(WAON(MB))
	 */
	const C06 = 'e_money6';

	/**
	 * ネットバンク決済登録(楽天銀行)
	 */
	const D01 = 'bank1';

	/**
	 * 出荷情報登録
	 */
	const E01 = 'shipmentEntry';

	/**
	 * 出荷情報取消
	 */
	const E02 = 'shipmentCancel';

	/**
	 * 出荷予定日変更
	 */
	const E03 = 'changeDate';

	/**
	 * 取引情報照会
	 */
	const E04 = 'tradeInfo';

	/**
	 * 継続課金登録1
	 */
	const G01 = 'regular';

	/**
	 * 継続課金登録2
	 */
	const G02 = 'regular3D ';

	/**
	 * 継続課金照会
	 */
	const G03 = 'regularInfo';

	/**
	 * 継続課金更新
	 */
	const G04 = 'regularUpdate';

	/**
	 * 継続課金削除
	 */
	const G05 = 'regularDelete';

	/**
	 * IP アドレス照会
	 */
	const H01 = 'traderIp';

	/**
	 * Get request URL for action
	 *
	 * @param string $action Action name
	 * @param boolean $is_sandbox If this is sandbox.
	 *
	 * @return string
	 */
	public static function make_url( $action, $is_sandbox ) {
		$url = $is_sandbox ? self::SANDBOX . $action . '.api'
			: self::URL . $action;

		return $url;
	}

	/**
	 * Get function div from endpoint
	 *
	 * @param string $endpoint Action code name.
	 * @return string
	 */
	public static function get_function_div( $endpoint ) {
		$reflection = new \ReflectionClass( get_called_class() );
		foreach ( $reflection->getConstants() as $key => $constant ) {
			if ( $endpoint == $constant ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Get endpoint
	 *
	 * @param string $div
	 *
	 * @return string|false
	 */
	public static function get_endpoint_from_function_div( $div ) {
		$refl = new \ReflectionClass( get_called_class() );
		foreach ( $refl->getConstants() as $key => $value ) {
			if ( $key == $div ) {
				return $value;
			}
		}
		return '';
	}


}
