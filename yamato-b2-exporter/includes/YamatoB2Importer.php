<?php

/**
 * Import Yamato B2 import
 *
 * @package yb2
 */
class YamatoB2Importer {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}

	/**
	 * Execute if WooCommerce exists.
	 */
	public function plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'woocommerce_api_yb2-import', [ $this, 'import_csv' ] );
		add_action( 'admin_menu', function () {
			add_submenu_page( 'woocommerce', __( 'Yamato Transport B2 CSV Import', 'yb2' ), __( 'B2 Import', 'yb2' ), 'edit_posts', 'yb2-import', [
				$this,
				'render_screen',
			] );
		}, 1000 );
	}

	/**
	 * Render screen
	 */
	public function render_screen() {
		if ( get_option( 'rewrite_rules' ) ) {
			$endpoint = home_url( '/wc-api/yb2-import/' );
		} else {
			$endpoint = add_query_arg( [
				'wc-api' => 'yb2-import',
			], home_url( '/' ) );
		}
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Yamato Transport B2 CSV Import', 'yb2' ) ?></h2>
			<p>
				<?php esc_html_e( 'Please upload Yamto B2 format CSV. Orders with ID in "Customer code" column will be "completed".', 'yb2' ) ?>
			</p>
			<form method="post" action="<?= esc_url( $endpoint ) ?>" enctype="multipart/form-data"
				  target="yb2-import-frame"
				  id="yb2-file-uploader">
				<?php wp_nonce_field( 'yb2_import' ) ?>
				<table class="form-table">
					<tr>
						<th><label for="b2-csv"><?php esc_html_e( 'CSV File', 'yb2' ) ?></label></th>
						<td><input type="file" id="b2-csv" name="b2-csv" value="<?php esc_attr_e( 'Choose file', 'yb2' ) ?>"></td>
					</tr>
					<tr>
						<th><label for="b2-dry-run"><?php esc_html_e( 'Dry run', 'yb2' ) ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="b2-dry-run" id="b2-dry-run" value="1" checked/>
								<?php esc_html_e( 'If checked, records will not actually imported.', 'yb2' ) ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Upload', 'yb2' ) ) ?>
			</form>
		</div>
		<div class="yb2-result-window">
			<h2><?php esc_html_e( 'Import result', 'yb2' ) ?></h2>
			<div class="yb2-success toggle">
				<p class="yb2-message"></p>
				<ol class="yb2-messages">

				</ol>
			</div>
			<div class="yb2-error toggle">
				<p></p>
			</div>
		</div>
		<iframe height="2000" width="100%" name="yb2-import-frame"></iframe>
		<?php
	}

	/**
	 * Parse CSV and import them.
	 */
	public function import_csv() {
		$json = [
			'error'   => false,
			'data'    => [],
			'message' => '',
		];
		try {
			set_time_limit( 0 );
			ignore_user_abort( true );
			// Check nonce.
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'yb2_import' ) ) {
				throw new Exception( __( 'Wrong access. Reload this page and try again later.', 'yb2' ), 401 );
			}
			// Check CSV exists.
			if ( ! isset( $_FILES['b2-csv'] ) || UPLOAD_ERR_OK !== $_FILES['b2-csv']['error'] ) {
				throw new Exception( __( 'Failed to catch CSV. Please choose CSV file.', 'yb2' ), 400 );
			}
			$file = $_FILES['b2-csv'];
			if ( ! preg_match( '#\.csv$#u', $file['name'] ) ) {
				throw new Exception( __( 'This file doesn\'t seem to be CSV format.', 'yb2' ), 400 );
			}
			// Create csv pointer.
			$data        = [];
			$file_object = new SplFileObject( $file['tmp_name'] );
			$file_object->setFlags( SplFileObject::READ_CSV );
			// Dry run flag.
			$dry_run = isset( $_POST['b2-dry-run'] ) && $_POST['b2-dry-run'];
			// Start import.
			$importer = new YamatoB2RowConverter();
			foreach ( $file_object as $line ) {
				$result = $importer->import_line( $line, get_current_user_id(), $dry_run );
				if ( is_wp_error( $result ) ) {
					$data[] = sprintf( '<li class="yb2-item-error">%s</li>', implode( '<br />', $result->get_error_messages() ) );
				} elseif ( $result ) {
					if ( $dry_run ) {
						// translators: %1$d is order ID, %2$s is order detail URL.
						$message = __( 'Order # %1$d will be completed(<a href="%2$s">Check</a>)', 'yb2' );
					} else {
						// translators: %1$d is order ID, %2$s is order detail URL.
						$message = __( 'Order # %1$d has been completed(<a href="%2$s">Check</a>)', 'yb2' );
					}
					$data[] = sprintf(
						'<li class="yb2-item-success">' . $message . '</li>',
						$result->get_id(),
						get_edit_post_link( $result->get_id() )
					);
				} else {
					// This is header line. do nothing.
				}
			}
			if ( ! $data ) {
				throw new Exception( __( 'No data processed. File may be bad format.', 'yb2' ), 400 );
			}
			$json['data'] = $data;
			if ( $dry_run ) {
				// translators: %d is record count.
				$json['message'] = sprintf( __( 'Testing uploaded CSV, %d records will be processed with it.', 'yb2' ), count( $data ) );
			} else {
				// translators: %d is record count.
				$json['message'] = sprintf( __( '%d records are processed.', 'yb2' ), count( $data ) );
			}
		} catch ( \Exception $e ) {
			$json['error']   = true;
			$json['message'] = $e->getMessage();
		}
		?>
		<html>
		<body>
		<script>
          window.parent.YB2.callback( <?= json_encode( $json ) ?> );
		</script>
		</body>
		</html>
		<?php
		exit;
	}

}
