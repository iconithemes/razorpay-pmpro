<?php
/*
Plugin Name: RazorPay Gateway - Paid Memberships Pro
Plugin URI: https://iconithemes.com/
Description: PMPro Gateway integration for RazorPay
Version: 1.0
Author: MV
Author URI: https://iconithemes.com/
Text Domain: razorpay-pmpro
Domain Path: /languages
*/

define( "RAZORPAY_PMPRO_DIR", dirname( __FILE__ ) );

/**
 * Loads rest of RazorPay gateway if PMPro is active.
 */
function razorpay_pmpro_load_gateway() {

	if ( class_exists( 'PMProGateway' ) ) {
		require_once( RAZORPAY_PMPRO_DIR . '/classes/class.razorpay_pmprogateway.php' );
		add_action( 'wp_ajax_nopriv_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );
		add_action( 'wp_ajax_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );
	}

}
add_action( 'plugins_loaded', 'razorpay_pmpro_load_gateway' );

/**
 * Callback for RazorPay Webhook
 */
function razorpay_pmpro_wp_ajax_webhook() {

	require_once( dirname(__FILE__) . "/webhook.php" );
	exit;
}
add_action( 'wp_ajax_nopriv_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );
add_action( 'wp_ajax_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );

/**
 * Runs only when the plugin is activated.
 *
 * @since 0.1.0
 */
function razorpay_pmpro_admin_notice_activation_hook() {
	// Create transient data.
	set_transient( 'razorpay-pmpro-admin-notice', true, 5 );
}
register_activation_hook( __FILE__, 'razorpay_pmpro_admin_notice_activation_hook' );

/**
 * Admin Notice on Activation.
 *
 * @since 0.1.0
 */
function razorpay_pmpro_admin_notice() {
	// Check transient, if available display notice.
	if ( get_transient( 'razorpay-pmpro-admin-notice' ) ) { 
	?>
		<div class="updated notice is-dismissible">
			<p><?php printf( __( 'Thank you for activating the Paid Memberships Pro: RazorPay Add On. <a href="%s">Visit the payment settings page</a> to configure the RazorPay Payment Gateway.', 'razorpay-pmpro' ), esc_url( get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) ) ); ?></p>
		</div>
		<?php
		// Delete transient, only display this notice once.
		delete_transient( 'razorpay-pmpro-admin-notice' );
	}
}
add_action( 'admin_notices', 'razorpay_pmpro_admin_notice' );

/**
 * Function to add links to the plugin action links
 *
 * @param array $links Array of links to be shown in plugin action links.
 */
function razorpay_pmpro_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$new_links = array(
			'<a href="' . get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) . '">' . __( 'Configure RazorPay', 'razorpay-pmpro' ) . '</a>',
		);
		$links  = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'razorpay_pmpro_plugin_action_links' );

/**
 * Function to add links to the plugin row meta
 *
 * @param array  $links Array of links to be shown in plugin meta.
 * @param string $file Filename of the plugin meta is being shown for.
 */
function razorpay_pmpro_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'razorpay-pmpro.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/razorpay/' ) . '" title="' . esc_attr( __( 'View Documentation', 'razorpay-pmpro' ) ) . '">' . __( 'Docs', 'razorpay-pmpro' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'razorpay-pmpro' ) ) . '">' . __( 'Support', 'razorpay-pmpro' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'razorpay_pmpro_plugin_row_meta', 10, 2 );

/**
 * Load the languages folder for translations.
 */
function razorpay_pmpro_load_textdomain(){
	load_plugin_textdomain( 'razorpay-pmpro' );
}
add_action( 'plugins_loaded', 'razorpay_pmpro_load_textdomain' );
?>
