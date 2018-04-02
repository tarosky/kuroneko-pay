<?php

/**
 * Main routine
 *
 * @package yb2
 */
class YamatoB2Exporter {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}

	/**
	 * Init settings
	 */
	public function plugins_loaded() {
		// WooCommerce not found.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'register_bulk_actions' ], 200 );
		add_action( 'load-edit.php', [ $this, 'do_bulk_action' ] );
		add_action( 'woocommerce_api_yb2-export', [ $this, 'export_csv' ] );
		// Register section.
		add_action( 'woocommerce_shipping_settings', [ $this, 'register_setting' ] );
		// Register metabox.
		add_action( 'add_meta_boxes', function( $post_type ) {
			if ( 'shop_order' === $post_type ) {
				add_meta_box( 'yb2-info', __( 'Shipping Information', 'yb2' ), [ $this, 'do_meta_box' ], $post_type, 'normal', 'high' );
			}
		}, 200 );
		add_action( 'save_post', [ $this, 'save_post' ], 2, 2 );
	}

	/**
	 * Add CSV export action.
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['yb2'] = __( 'Export Yamato B2 CSV', 'yb2' );

		return $actions;
	}

	/**
	 * Export iframe
	 */
	public function do_bulk_action() {
		add_action( 'admin_notices', function () {
			$screen = get_current_screen();
			if ( 'edit-shop_order' == $screen->id ) {
				?>
				<iframe name="yb2-export" style="height: 0;"></iframe>
				<?php
			}
		} );
	}

	/**
	 * Register setting
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function register_setting( $settings ) {
		$settings[] = [
			'name' => 'Yamato Transport B2 Setting',
			'type' => 'title',
			'desc' => __( 'Please fill form below to use Yamato Transport B2.', 'yb2' ),
			'id'   => 'yb2',
		];
		// Add name.
		$settings[] = [
			'name'     => _x( 'Sender Name', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_name',
			'type'     => 'text',
			'required' => true,
			'desc_tip' => __( 'If empty, site name will be used.', 'yb2' ),
		];
		$settings[] = [
			'name'     => _x( 'Tel', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_phone',
			'type'     => 'text',
			'required' => true,
		];
		$settings[] = [
			'name'     => _x( 'Zip', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_postcode',
			'type'     => 'text',
			'required' => true,
		];
		$settings[] = [
			'name'     => _x( 'Address', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_address1',
			'type'     => 'text',
			'required' => true,
		];
		$settings[] = [
			'name'     => _x( 'Address2', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_address2',
			'type'     => 'text',
			'required' => false,
		];
		$settings[] = [
			'name'     => _x( 'Customer Code', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_phonecode',
			'type'     => 'text',
			'required' => false,
		];
		$settings[] = [
			'name'     => _x( 'Send email on shipping', 'yb2_sender', 'yb2' ),
			'id'       => 'yb2_send_email',
			'type'     => 'checkbox',
			'default'  => 'no',
			'required' => false,
		];
		$settings[] = [
			'type' => 'sectionend',
			'id'   => 'yb2',
		];
		return $settings;
	}

	/**
	 * Save metabox data
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}
		if ( ! isset( $_POST['_yb2nonce'] ) || ! wp_verify_nonce( $_POST['_yb2nonce'], 'yb2-save' ) ) {
			return;
		}
		// Save date if changed.
		foreach ( [ '_kuroneko_tracking_no', '_kuroneko_shipping_at' ] as $key ) {
			$former_value = get_post_meta( $post_id, $key, true );
			if ( $former_value != $_POST[ $key ] ) {
				update_post_meta( $post_id, $key, $_POST[ $key ] );
			}
		}
		if ( isset( $_POST['_is_kuroneko'] ) && $_POST['_is_kuroneko'] ) {
			update_post_meta( $post_id, '_is_kuroneko', true );
		} else {
			delete_post_meta( $post_id, '_is_kuroneko' );
		}
	}

	/**
	 * Render meta box
	 *
	 * @param WP_Post $post
	 */
	public function do_meta_box( $post ) {
		wp_nonce_field( 'yb2-save', '_yb2nonce', false );
		?>
		<div class="yb2-meta-row">
			<div class="yb2-meta-col">
				<label class="yb2-meta-label">
					<?php esc_html_e( 'Tracking No.', 'yb2' ) ?><br />
					<input type="text" name="_kuroneko_tracking_no" value="<?= esc_attr( get_post_meta( $post->ID, '_kuroneko_tracking_no', true ) ) ?>" />
				</label>
			</div>
			<div class="yb2-meta-col">
				<label class="yb2-meta-label">
					<?php esc_html_e( 'Shipping Date', 'yb2' ) ?><br />
					<input type="text" name="_kuroneko_shipping_at" value="<?= esc_attr( get_post_meta( $post->ID, '_kuroneko_shipping_at', true ) ) ?>"
					 placeholder="YYYY-MM-DD"/>
				</label>
			</div>
			<div class="yb2-meta-footer">
				<label>
					<input type="checkbox" name="_is_kuroneko" value="1" <?php checked( get_post_meta( $post->ID, '_is_kuroneko', true ) ) ?> />
					<?php esc_html_e( 'Ship with Yamato Transport', 'yb2' ) ?>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render CSV
	 */
	public function export_csv() {
		nocache_headers();
		// Check nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'yb2' ) ) {
			$message = esc_js( __( 'Wrong access.', 'yb2' ) );
			echo <<<HTML
<html>
<body>
<script>alert( '{$message}' );</script>
</body>
</html>
HTML;
			exit;
		}
		// Export.
		$name = untrailingslashit( preg_replace( '#https?://#', '', home_url() ) ) . '-' . date_i18n( 'YmdHis' ) . '.csv';
		header( 'Content-Type: text/csv; charset=Shift_JIS' );
		header( 'Content-Disposition: attachment; filename=' . $name );
		$stream    = fopen( 'php://output', 'w' );
		stream_filter_register('msLineEnding', 'YamatoB2LineFormatter' );
		stream_filter_append( $stream, 'msLineEnding' );
		$converter = new YamatoB2RowConverter();
		fputcsv( $stream, $converter->get_header() );
		foreach ( explode( ',', $_GET['post_ids'] ) as $post_id ) {
			$order = wc_get_order( $post_id );
			if ( ! $order ) {
				continue;
			}
			fputcsv( $stream, $converter->render( $order, $_GET['date'] ) );
		}
		exit;
	}
}
