<?php
/**
 * Plugin Name:       Savage Exports
 * Description:       Exports Data to CSV file.
 * Version:           1.0.0
 * Requires at least: 5.9.3
 * Requires PHP:      7.4
 * Author:            rtCamp
 * Author URI:        https://github.com/rtCamp/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       savage-exports
 * Domain Path:       /languages
 *
 * @package Savage-Exports
 */

use Savage_Exports\Includes;

defined( 'ABSPATH' ) || exit;

// Import necessary files.
require_once plugin_dir_path( __FILE__ ) . 'asw_vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once 'inc/helpers/custom-functions.php';
require_once 'inc/helpers/autoloader.php';

// Constants.
const SAVAGE_EXPORTS_VERSION              = '1.0.0'; // Current plugin version.
const SAVAGE_S3_DOWNLOAD_LINK_EXPIRY_TIME = '60';    // Expiry time for download link (In Minutes).

define( 'SAVAGE_EXPORTS_PATH', plugin_dir_path( __FILE__ ) );         // Plugin directory path.
define( 'SAVAGE_EXPORTS_URL', plugin_dir_url( __FILE__ ) );           // Plugin URI path.
define( 'SAVAGE_EXPORTS_BUCKET', get_option( 'aws_bucket_name' ) );       // S3 bucket name.
define( 'SAVAGE_EXPORTS_AWS_REGION', get_option( 'aws_bucket_region' ) ); // AWS Region.

// Test to see if WooCommerce is active (including network activated).
$woocommerce_plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (
	! in_array( $woocommerce_plugin_path, wp_get_active_and_valid_plugins(), true ) &&
	! (
		function_exists( 'wp_get_active_network_plugins' ) &&
		in_array( $woocommerce_plugin_path, wp_get_active_network_plugins(), true )
	)
) {

	// Log Warning on admin panel.
	new Includes\Log_Warning(
		esc_html__( '"Savage Exports" requires "Woocommerce" installed and activated', 'savage-exports' )
	);

	// Deactivate plugin.
	deactivate_plugins( plugin_basename( __FILE__ ) );
}

// Load plugin after woocommerce is loaded.
add_action( 'woocommerce_loaded', 'savage_exports_plugin_loader' );

// Create required categories.
add_action( 'init', 'maybe_create_taxonomies' );

/**
 * Begin the execution of plugin.
 *
 * @since 1.0.0
 */
function savage_exports_plugin_loader(): void {

	if (
		empty( get_option( 'aws_access_key' ) ) ||
		empty( get_option( 'aws_private_key' ) ) ||
		empty( 'aws_bucket_name' ) ||
		empty( 'aws_bucket_region' )
	) {
		new Includes\Log_Warning(
			esc_html__( 'AWS credentials are not set properly.', 'savage-exports' ),
			esc_html__( 'Please set them in', 'savage-exports' ),
			esc_url( admin_url( 'admin.php?page=savage_options_page' ) ),
			esc_html__( 'Savage Export\'s Settings', 'savage-exports' ),
		);
	}

	// Initialize main plugin class.
	new Includes\Savage_Exports();

}
