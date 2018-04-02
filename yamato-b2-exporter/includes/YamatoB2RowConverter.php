<?php
/**
 * Class YamatoB2RowConverter
 *
 * @package yb2
 */
class YamatoB2RowConverter {

	/**
	 * Header names
	 *
	 * @var array
	 */
	public $headers = [
		'お客様管理番号',
		'送り状種別',
		'クール区分',
		'伝票番号',
		'出荷予定日',
		'お届け予定（指定）日',
		'配達時間帯',
		'お届け先コード',
		'お届け先電話番号',
		'お届け先電話番号枝番',
		'お届け先郵便番号',
		'お届け先住所',
		'お届け先住所（アパートマンション名）',
		'お届け先会社・部門名１',
		'お届け先会社・部門名２',
		'お届け先名',
		'お届け先名略称カナ',
		'敬称',
		'ご依頼主コード',
		'ご依頼主電話番号',
		'ご依頼主電話番号枝番',
		'ご依頼主郵便番号',
		'ご依頼主住所１',
		'ご依頼主住所（アパートマンション名）',
		'ご依頼主名',
		'ご依頼主略称カナ',
		'品名コード１',
		'品名１',
		'品名コード２',
		'品名２',
		'荷扱い１',
		'荷扱い２',
		'記事',
		'コレクト代金引換額（税込）',
		'コレクト内消費税額等',
		'営業所止置き',
		'営業所コード',
		'発行枚数',
		'個数口表示フラグ',
		'請求先顧客コード',
		'請求先分類コード',
		'運賃管理番号',
		'クロネコwebコレクトデータ登録',
		'クロネコwebコレクト加盟店番号',
		'クロネコwebコレクト申込受付番号１',
		'クロネコwebコレクト申込受付番号２',
		'クロネコwebコレクト申込受付番号３',
		'お届け予定ｅメール利用区分',
		'お届け予定ｅメールe-mailアドレス',
		'入力機種',
		'お届け予定eメールメッセージ',
		'お届け完了ｅメール利用区分',
		'お届け完了ｅメールe-mailアドレス',
		'お届け完了eメールメッセージ',
		'クロネコ収納代行利用区分',
		'決済ＱＲコード印字フラグ',
		'収納代行請求金額(税込)',
		'収納代行内消費税額等',
		'収納代行請求先郵便番号',
		'収納代行請求先住所',
		'収納代行請求先住所（アパートマンション名）',
		'収納代行請求先会社・部門名１',
		'収納代行請求先会社・部門名２',
		'収納代行請求先名(漢字)',
		'収納代行請求先名(カナ)',
		'収納代行問合せ先名(漢字)',
		'収納代行問合せ先郵便番号',
		'収納代行問合せ先住所',
		'収納代行問合せ先住所（アパートマンション名）',
		'収納代行問合せ先電話番号',
		'収納代行管理番号',
		'収納代行品名',
		'収納代行備考',
		'複数口くくりキー',
		'検索キータイトル1',
		'検索キー1',
		'検索キータイトル2',
		'検索キー2',
		'検索キータイトル3',
		'検索キー3',
		'検索キータイトル4',
		'検索キー4',
		'検索キータイトル5',
		'検索キー5',
		'予備',
		'予備',
		'投函予定メール利用区分',
		'投函予定メールe-mailアドレス',
		'投函予定メールメッセージ',
		'投函完了メール (お届け先宛)利用区分',
		'投函完了メール(お届け先宛) e-mailアドレス',
		'投函完了メール(お届け先宛) メールメッセージ',
		'投函完了メール (ご依頼主宛)利用区分',
		'投函完了メール(ご依頼主宛) e-mailアドレス',
		'投函完了メール(ご依頼主宛) メールメッセージ',
		'連携管理番号',
		'通知メールアドレス',
	];
	
	/**
	 * Return row
	 *
	 * @param WC_Order $order
	 * @param string   $date
	 * @return array
	 */
	public function render( $order, $date = '' ) {
		// Get states.
		$states = WC()->countries->get_states( $order->get_shipping_country() );
		$address1 = $states[ $order->get_shipping_state() ] . $order->get_shipping_city() . $order->get_shipping_address_1();
		$address2 = $order->get_shipping_address_2();
		$company  = $order->get_shipping_company();
		// Create product name.
		$categories = _x( 'Product', 'yb2_csv_column', 'yb2' );
		$terms = [];
		foreach ( $order->get_items() as $id => $item ) {
			$product_id = $item['product_id'];
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$terms[ $term->parent ] = $term->name;
				}
			}
		}
		if ( $terms ) {
			ksort( $terms );
			$categories = current( $terms );
			if ( 1 < count( $terms ) ) {
				$categories .= 'ほか';
			}
		}
		/**
		 * yb2_product_name
		 *
		 * Product name set to order sheet
		 *
		 * @param string $item_name
		 * @param WC_Order
		 * @return string
		 */
		$categories = apply_filters( 'yb2_product_name', $categories, $order );
		// Set delivery time
		$plan_to_deliver = (string) get_post_meta( $order->get_id(), '_kuroneko_shipping_at', true );
		if ( $plan_to_deliver ) {
			$plan_to_deliver = str_replace( '-', '/', $plan_to_deliver );
		} elseif( preg_match( '#[0-9]{4}-[0-9]{2}-[0-9]{2}#u', $date ) ) {
			$plan_to_deliver = str_replace( '-', '/', $date );
		} else {
			$plan_to_deliver = '';
		}
		// Get order date if it's possible
		$delivered_at = (string) get_post_meta( $order->get_id(), 'wc4jp-delivery-date', true );
		$now = date_i18n( 'Y-m-d' );
		if ( $delivered_at && $now < $delivered_at ) {
			$delivered_at = str_replace( '-', '/', $delivered_at );
		} else {
			$delivered_at = '';
		}
		// Get time
		$deliver_time = (string) get_post_meta( $order->get_id(), 'wc4jp-delivery-time-zone', true );
		if ( $deliver_time ) {
			$deliver_time = preg_replace( '#(\\d{2}):\\d{2}-(\\d{2}):\\d{2}#u', '$1$2', $deliver_time );
		} else {
			$deliver_time = '';
		}
		// Get tracking no
		$tracking_no = (string) get_post_meta( $order->get_id(), '_kuroneko_tracking_no', true );
		/**
		 * yb2_name_suffix
		 *
		 * Filter suffix for name. Default '様'.
		 *
		 * @param string   $suffix
		 * @param WC_Order $order
		 */
		$suffix = apply_filters( 'yb2_name_suffix', '様', $order );
		// Send email
		$send_email = ( 'yes' == get_option( 'yb2_send_email', 'yes' ) ) ? '1' : '0';
		$row = [
			$order->get_id(), //  1. お客様管理番号
			'0', // 2. 送り状種別
			'0', // 3. クール区分
			$tracking_no, // 4. 伝票番号
			$plan_to_deliver, // 5. 出荷予定日
			$delivered_at, // 6. お届け予定（指定）日
			$deliver_time, // 7.配達時間帯
			'', // 8.お届け先コード
			$order->get_billing_phone(), // 9.お届け先電話番号
			'', // 10.お届け先電話番号枝番
			$order->get_shipping_postcode(), // 11.お届け先郵便番号
			$address1, // 12.お届け先住所
			$address2, // 13.お届け先住所（アパートマンション名）
			$company, // 14.お届け先会社・部門名１
			'', // 15.お届け先会社・部門名２
			$order->get_formatted_shipping_full_name(), // 16.お届け先名
			'', // 17.お届け先名略称カナ
			$suffix, // 18.敬称
			'', // 19.ご依頼主コード
			get_option( 'yb2_phone', '' ), // 20.ご依頼主電話番号
			'', // 21.ご依頼主電話番号枝番
			get_option( 'yb2_postcode', '' ), // 22.ご依頼主郵便番号
			get_option( 'yb2_address1', '' ), // 23.ご依頼主住所１
			get_option( 'yb2_address2', '' ), // 24.ご依頼主住所（アパートマンション名）
			get_option( 'yb2_name', '' ), // 25.ご依頼主名
			'', // 26.ご依頼主略称カナ
			'', // 27.品名コード１
			$categories, // 28.品名１
			'', // 29.品名コード２
			'', // 30.品名２
			'', // 31.荷扱い１
			'', // 32.荷扱い２
			'', // 33.記事
			'', // 34.コレクト代金引換額（税込）
			'', // 35.コレクト内消費税額等
			'0', // 36.営業所止置き
			'', // 37.営業所コード
			'1', // 38.発行枚数
			'1', // 39.個数口表示フラグ
			get_option( 'yb2_phonecode', '' ), // 40.請求先顧客コード
			'', // 41.請求先分類コード
			'', // 42.運賃管理番号
			'0', // 43.クロネコwebコレクトデータ登録
			'', // 44.クロネコwebコレクト加盟店番号
			'', // 45.クロネコwebコレクト申込受付番号１
			'', // 46.クロネコwebコレクト申込受付番号２
			'', // 47.クロネコwebコレクト申込受付番号３
			$send_email, // 48.お届け予定ｅメール利用区分
			$order->get_billing_email(), // 49.お届け予定ｅメールe-mailアドレス
			'1', // 50.入力機種
			'', // 51.お届け予定eメールメッセージ
			$send_email, // 52.お届け完了ｅメール利用区分
			$order->get_billing_email(), // 53.お届け完了ｅメールe-mailアドレス
			'', // 54.お届け完了eメールメッセージ
			'0', // 55.クロネコ収納代行利用区分
			'', // 56. 予備
			'', // 57.収納代行請求金額(税込)
			'', // 58.収納代行内消費税額等
			'', // 59.収納代行請求先郵便番号
			'', // 60.収納代行請求先住所
			'', // 61.収納代行請求先住所（アパートマンション名）
			'', // 62.収納代行請求先会社・部門名１
			'', // 63.収納代行請求先会社・部門名２
			'', // 64.収納代行請求先名(漢字)
			'', // 65.収納代行請求先名(カナ)
			'', // 66.収納代行問合せ先名(漢字)
			'', // 67.収納代行問合せ先郵便番号
			'', // 68.収納代行問合せ先住所
			'', // 69.収納代行問合せ先住所（アパートマンション名）
			'', // 70.収納代行問合せ先電話番号
			'', // 71.収納代行管理番号
			'', // 72.収納代行品名
			'', // 73.収納代行備考
			'', // 74.複数口くくりキー
			'', // 75.検索キータイトル1
			'', // 76.検索キー1
			'', // 77.検索キータイトル2
			'', // 78.検索キー2
			'', // 79.検索キータイトル3
			'', // 80.検索キー3
			'', // 81.検索キータイトル4
			'', // 82.検索キー4
			'', // 83.検索キータイトル5
			'', // 84.検索キー5
			'', // 85.予備
			'', // 86.予備
			'0', // 87.投函予定メール利用区分
			'', // 88.投函予定メールe-mailアドレス
			'', // 89.投函予定メールメッセージ
			'', // 90.投函完了メール (お届け先宛)利用区分
			'', // 91.投函完了メール(お届け先宛) e-mailアドレス
			'', // 92.投函完了メール(お届け先宛) メールメッセージ
			'', // 93.投函完了メール (ご依頼主宛)利用区分
			'', // 94.投函完了メール(ご依頼主宛) e-mailアドレス
			'', // 95.投函完了メール(ご依頼主宛) メールメッセージ
			'', // 96.連携管理番号
			'', // 97.通知メールアドレス
		];
		/**
		 * yb2_each_row
		 *
		 * Filter for each CSV row
		 *
		 * @since 1.1.1
		 * @param array    $row
		 * @param WC_Order $order
		 */
		$row = apply_filters( 'yb2_each_row', $row, $order );
		return array_map( [ $this, 'encode' ], $row );
	}

	/**
	 * Import each line
	 *
	 * @param array $line
	 * @param int   $user_id
	 * @param bool  $dry_run If true, actual import doesn't occur.
	 * @return WP_Error|WC_Order|false
	 */
	public function import_line( $line, $user_id, $dry_run = false ) {
		$error = new WP_Error();
		if ( count( $line ) < 10 ) {
			return false;
		}
		if ( count( $line ) > 100 ) {
			// Too long.
			// translators: %d is cell count.
			$error->add( 400, sprintf( __( 'Cells count %d, and may be mal-formatted.', 'yb2' ), count( $line ) ) );
			return $error;
		}
		list( $order_id, $letter_type, $is_cool, $tracking_no, $shipping_at ) = array_map( [ $this, 'decode' ], $line );
		if ( ! is_numeric( $order_id ) ) {
			// Skip header.
			return false;
		}
		if ( ! ( $order = wc_get_order( $order_id ) ) ) {
			// translators: %d is order ID.
			$error->add( 404, sprintf( __( 'Specified order # %d not found.', 'yb2' ), $order_id ) );
			return $error;
		}
		/**
		 * yb2_update_order_capability
		 *
		 * @param bool     $capable
		 * @param WC_Order $order
		 * @return bool
		 */
		$caps = apply_filters( 'yb2_update_order_capability', user_can( $user_id,'edit_others_posts' ), $order );
		if ( ! $caps ) {
			// translators: %d is order ID.
			$error->add( 403, sprintf( 'You have no permission to edit order # %d.', $order_id ) );
			return $error;
		}
		// Update meta.
		if ( ! $dry_run ) {
			// Shipping date.
			if ( preg_match( '#(\\d{4})/(\\d{1,2})/(\\d{1,2})#u', $shipping_at, $match ) ) {
				update_post_meta( $order_id, '_kuroneko_shipping_at', sprintf( '%04d-%02d-%02d', $match[1], $match[2], $match[3] ) );
			} else {
				delete_post_meta( $order_id, '_kuroneko_shipping_at' );
			}
			// tracking no.
			if ( $tracking_no ) {
				update_post_meta( $order_id, '_kuroneko_tracking_no', $tracking_no );
				update_post_meta( $order_id, '_is_kuroneko', true );
			} else {
				delete_post_meta( $order_id, '_kuroneko_tracking_no' );
				delete_post_meta( $order_id, '_is_kuroneko' );
			}
		}

		// Change status.
		$do_complete = false;
		$url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		switch ( $order->get_status() ) {
			case 'processing':
				// O.K.
				$do_complete = true;
				break;
			case 'completed':
				// translators: %1$s is order URL, %2$d is order ID.
				$error->add( 400 , sprintf( __( '<a href="%1$s">Order # %2$d</a> is already completed.', 'yb2' ), $url, $order_id ) );
				break;
			case 'pending':
			case 'on-hold':
				// translators: %1$s is order URL, %2$d is order ID.
				$error->add( 400, sprintf( __( '<a href="%1$s">Order # %2$d</a> is waiting payment.', 'yb2' ), $url, $order_id ) );
				break;
			default:
				// translators: %1$d is order URl, %2$s is order status.
				$error->add( 400, sprintf(
					__( '<a href="%1$s">Order # %2$d</a> has status "%s". Bulk action requires status "Processing".', 'yb2' ),
					$url,
					$order_id,
					wc_get_order_status_name( $order->get_status() )
				) );
				break;
		}
		if ( $do_complete && ! $dry_run ) {
			// Update order
			$order->update_status( 'completed' );
		}
		return $error->get_error_messages() ? $error : $order;
	}

	/**
	 * Get CSV headers
	 *
	 * @return array
	 */
	public function get_header() {
		return array_map( [ $this, 'encode' ], $this->headers );
	}

	/**
	 * Convert UTF-8 to excel csv.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	protected function encode( $string ) {
		return mb_convert_encoding( $string, 'sjis-win', 'utf-8' );
	}

	/**
	 * Convert CSV to utf-8
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	protected function decode( $string ) {
		return mb_convert_encoding( $string, 'utf-8', 'sjis-win' );
	}

}
