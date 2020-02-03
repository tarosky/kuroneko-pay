<?php
/**
 * Plugin Name: Kuroneko Web Collect
 * Plugin URI: https://wordpress.org/kuroneko-pay/
 * Description: Add Kuroneko Web Collect's Payment Gateway to WooCommerce.
 * Version: 1.2.10
 * Author: YAMATO FINANCIAL Co.,Ltd.
 * Author URI: https://www.yamatofinancial.jp
 * PHP Version: 5.4.0
 * License: GPL3 or later
 * Text Domain: kuroneko
 *
 * @package KuronekoPay
 */

defined( 'ABSPATH' ) || die( 'Do not load directly.' );


// Load file data.
$kuroneko_pay_info = get_file_data( __FILE__, array(
	'name'        => 'Plugin Name',
	'version'     => 'Version',
	'php_version' => 'PHP Version',
	'domain'      => 'Text Domain',
) );


// Error message for PHP.
// translators: %1$s is plugin name, %2$s is required PHP version, %3$s is current PHP version.
$kuroneko_pay_info['error'] = sprintf( __( 'Plugin %1$s requires PHP %2$s and over, but your PHP is %3$s.' ), $kuroneko_pay_info['name'], $kuroneko_pay_info['php_version'], phpversion() );

// Defaint version.
define( 'KURONEKO_PAY_VERSION', $kuroneko_pay_info['version'] );


// Load b2 exporter if exists.
$b2_path = __DIR__ . '/yamato-b2-exporter/yamato-b2-exporter.php';
if ( file_exists( $b2_path ) ) {
	require_once $b2_path;
}

/**
 * Init plugin
 *
 * @internal
 */
function kuroneko_init() {
	global $kuroneko_pay_info;
	// Add i18n.
	load_plugin_textdomain( 'kuroneko', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	// Check PHP Version.
	if ( version_compare( phpversion(), $kuroneko_pay_info['php_version'], '<' ) ) {
		add_action( 'admin_notices', '_kuroneko_error' );

		return;
	}
	// Load bootstrap.
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require __DIR__ . '/vendor/autoload.php';
		call_user_func( [ 'KuronekoYamato\\KuronekoPay\\BootStrap', 'get_instance' ] );
	} else {
		$kuroneko_pay_info['error'] = __( 'Composer auto loader missing. Please rung "composer install".', $kuroneko_pay_info['domain'] );
		add_action( 'admin_notices', '_kuroneko_error' );
	}
	// OFF SSL verification.
	add_filter( 'https_ssl_verify', '__return_false' );
}

/**
 * Show error messages
 */
function _kuroneko_error() {
	global $kuroneko_pay_info;
	?>
	<div class="error">
		<p>
			<strong>[Error]</strong>
			<?php echo esc_html( $kuroneko_pay_info['error'] ) ?>
		</p>
	</div>
	<?php
}

/**
 * Flush rewrite rules.
 *
 * @ignore
 */
function kuroneko_flush() {
	flush_rewrite_rules();
}

/**
 * Get asset URL
 *
 * @param  string $path Relative path from assets folder.
 *
 * @return string
 */
function kuroneko_asset( $path ) {
	return plugin_dir_url( __FILE__ ) . 'assets/' . ltrim( $path, '/' );
}

/**
 * Get kuroneko template
 *
 * @param string $name
 * @param string $slug
 * @param array $args
 */
function kuroneko_template( $name, $args = [] ) {
	$base = __DIR__ . '/templates/' . $name . '.php';
	if ( file_exists( $base ) ) {
		extract( $args );
		include $base;
	}
}

// Initialize plugin.
add_action( 'plugins_loaded', 'kuroneko_init' );

// Flush rewrite rules on activation.
register_activation_hook( __FILE__, 'kuroneko_flush' );
