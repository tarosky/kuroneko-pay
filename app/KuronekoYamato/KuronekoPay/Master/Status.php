<?php

namespace KuronekoYamato\KuronekoPay\Master;

/**
 * Status Master
 *
 * @since 1.0.0
 * @package KuronekoPay
 */
class Status {
	/**
	 * 決済依頼済み
	 */
	const REQUESTING = 0;

	/**
	 * 決済申込完了
	 */
	const REQUESTED = 1;

	/**
	 * 入金完了(速報)
	 */
	const MAYBE_PAID = 2;

	/**
	 * 入金完了(確報)
	 */
	const PAID = 3;

	/**
	 * 与信完了
	 */
	const AUTHORIZED = 4;

	/**
	 * 予約受付完了
	 */
	const RESERVED = 5;

	/**
	 * 継続課金申込完了
	 */
	const SUBSCRIBED = 6;

	/**
	 * 購入者都合エラー
	 */
	const USER_ERROR = 11;

	/**
	 * 加盟店都合エラー
	 */
	const SHOP_ERROR = 12;

	/**
	 * 決済機関都合エラー
	 */
	const BANK_ERROR = 13;

	/**
	 * その他システムエラー
	 */
	const MISC_ERROR = 14;

	/**
	 * 予約販売与信エラー
	 */
	const RESERVE_FAILED = 15;

	/**
	 * 決済依頼取消エラー
	 */
	const REFUND_FAILED = 16;

	/**
	 * 金額変更NG
	 */
	const CHANGE_FAILED = 17;

	/**
	 * 継続課金与信エラー
	 */
	const SUBSCRIPTION_FAILED = 18;

	/**
	 * 決済中断
	 */
	const TRANSACTION_CANCELED = 20;

	/**
	 * 決済手続き中
	 */
	const TRANSACTION_PROCESSING = 21;

	/**
	 * 継続課金停止中
	 */
	const SUBSCRIPTION_STOPPED = 22;

	/**
	 * 精算確定待ち
	 */
	const WAITING_CHARGE = 30;

	/**
	 * 精算確定
	 */
	const CHARGE_DONE = 31;

	/**
	 * 取消
	 */
	const CANCELED = 40;
}
