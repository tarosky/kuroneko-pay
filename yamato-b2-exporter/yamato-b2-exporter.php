<?php
/**
Plugin Name: Yamato B2 Exporter for Woo
Plugin URI: https://gianism.info/ja/add-on/yamato-b2-exporter
Description: クロネコヤマトのB2をWooCommerceと連動させるプラグイン。
Author: Hametuha INC.
Version: 1.1.3
PHP Version: 5.4.0
Text Domain: yb2
Author URI: https://gianism.info/
*/

//-----HEADER_ENDS------//

defined( 'ABSPATH' ) || die();

// Do not load twice.
if ( defined( 'YB2_LOADED' ) ) {
	return;
} else {
	define( 'YB2_LOADED', true );
}

add_action( 'plugins_loaded', 'yb2_translation', 1 );

if ( version_compare( phpversion(), yb2_php_version(), '<' ) ) {
	add_action( 'admin_notices', 'yb2_notice' );
} else {
	add_action( 'admin_enqueue_scripts', 'yb2_enqueue_script' );
	require_once __DIR__ . '/includes/YamatoB2Exporter.php';
	require_once __DIR__ . '/includes/YamatoB2Importer.php';
	require_once __DIR__ . '/includes/YamatoB2RowConverter.php';
	require_once __DIR__ . '/includes/YamatoB2LineFormatter.php';
	require_once __DIR__ . '/includes/YamatoB2MailHandler.php';
	new YamatoB2Exporter();
	new YamatoB2Importer();
	new YamatoB2MailHandler();
}

/**
 * Register translation.
 */
function yb2_translation() {
	$language_dir = __DIR__ . '/languages';
	$base = ABSPATH . 'wp-content/plugins/';
	$language_dir = str_replace( $base, '', $language_dir );
	load_plugin_textdomain( 'yb2', false, $language_dir );
}

/**
 * Get version string
 *
 * @return string
 */
function yb2_php_version() {
	$data = get_file_data( __FILE__, array(
		'php_version' => 'PHP Version',
	) );
	return $data['php_version'];
}

/**
 * Enqueue assets for admin screen
 *
 * @internal
 * @param string $page Page path for admin.
 */
function yb2_enqueue_script( $page ) {
	// CSS.
	wp_enqueue_style( 'yb2-export', yb2_asset( 'css/yb2-admin.css' ), [], yb2_version() );
	// JS.
	wp_enqueue_script( 'yb2-export', yb2_asset( 'js/bulk-exporter.js' ), [ 'jquery' ], yb2_version(), true );
	/**
	 * yb2_default_shipping_date
	 *
	 * @package yb2
	 * @since 1.1.0
	 * @param string $date Date for shipping. Default today.
	 * @return string
	 */
	$today = apply_filters( 'yb2_default_shipping_date', date_i18n( 'Y-m-d' ) );
	wp_localize_script( 'yb2-export', 'YB2', [
		'import'  => yb2_endpoint( 'yb2-import' ),
		'export'  => yb2_endpoint( 'yb2-export' ),
		'nonce'   => wp_create_nonce( 'yb2' ),
		'glue'    => get_option( 'rewrite_rules' ) ? '?' : '&',
	    'today'   => $today,
		'prompt'  => __( 'Enter shipping date in YYYY-MM-DD format. If empty, nothing will be set.', 'yb2' ),
		'confirm' => __( 'Shipping date is empty. Are you sure to get CSV with empty shipping date column?', 'yb2' ),
	] );
}

/**
 * Get plugin version
 *
 * @return string
 */
function yb2_version() {
	static $data = array();
	if ( ! $data ) {
		$data = get_file_data( __FILE__, array(
			'version' => 'Version',
		) );
	}
	return $data['version'];
}

/**
 * Return file URL
 *
 * @param string $path
 *
 * @return string
 */
function yb2_asset( $path ) {
	$base = plugin_dir_url( __FILE__ ) . 'assets/';
	return $base . ltrim( $path, '/' );
}

/**
 * Show error message.
 *
 * @internal
 */
function yb2_notice(){
	// translators: %s is required PHP version.
	printf( '<div class="error"><p>%s</p></div>', sprintf( __( '<strong>Yamato B2 Exporter for Woo</strong> requires PHP %s and over.', 'yb2' ), yb2_php_version() ) );
}


/**
 * Get woocommerce api endpoint
 *
 * @param $endpoint
 *
 * @return string
 */
function yb2_endpoint( $endpoint ) {
	if ( get_option( 'rewrite_rules' ) ) {
		return home_url( "/wc-api/{$endpoint}/" );
	} else {
		return add_query_arg( [
			'wc-api' => $endpoint,
		], home_url( '/' ) );
	}
}
