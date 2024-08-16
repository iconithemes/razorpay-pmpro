<?php
/*
Plugin Name: Razorpay Gateway - Paid Memberships Pro
Plugin URI: https://iconithemes.com/
Description: PMPro Gateway integration for Razorpay
Version: 1.0
Author: MV
Author URI: https://iconithemes.com/
Text Domain: razorpay-pmpro
Domain Path: /languages
*/

define( "RAZORPAY_PMPRO_DIR", dirname( __FILE__ ) );

/**
 * Loads rest of Razorpay gateway if PMPro is active.
 */
function razorpay_pmpro_load_gateway() {
	// Include the Razorpay SDK
	require_once __DIR__ . '/helper/razorpay-php/Razorpay.php';

	if ( class_exists( 'PMProGateway' ) ) {
		require_once( RAZORPAY_PMPRO_DIR . '/classes/class.razorpay_pmprogateway.php' );
		add_action( 'wp_ajax_nopriv_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );
		add_action( 'wp_ajax_razorpay-webhook', 'razorpay_pmpro_wp_ajax_webhook' );
	}

}
add_action( 'plugins_loaded', 'razorpay_pmpro_load_gateway' );

/**
 * Callback for Razorpay Webhook
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
			<p><?php printf( __( 'Thank you for activating the Paid Memberships Pro: Razorpay Add On. <a href="%s">Visit the payment settings page</a> to configure the Razorpay Payment Gateway.', 'razorpay-pmpro' ), esc_url( get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) ) ); ?></p>
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
			'<a href="' . get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) . '">' . __( 'Configure Razorpay', 'razorpay-pmpro' ) . '</a>',
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

function pmpro_razorpay_enqueue_admin_css() {
    // Register the CSS file for the admin area
	wp_enqueue_style('pmpro_razorpay_admin_css', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', false, '1.0.0');
}
add_action('admin_enqueue_scripts', 'pmpro_razorpay_enqueue_admin_css');


// Hook into the membership level save action
add_action('pmpro_save_membership_level', 'pmpro_razorpay_create_plan', 10, 1);

function pmpro_razorpay_create_plan($level_id) {
    // Check if RazorPay is the active gateway
	$active_gateway = pmpro_getOption('gateway');

	if ($active_gateway !== 'razorpay') {
        return; // Exit if RazorPay is not active
    }
    // Get the membership level data
    $level = pmpro_getLevel($level_id);

    // Get the currency from PMPro settings
    $currency = pmpro_getOption('currency');
    if($currency != "INR"){
    	return; // Exit if RazorPay is not active
    }
    $razorpay_period = [
    	'day' => 'daily',
    	'week' => 'weekly',
    	'month' => 'monthly',
    	'year' => 'yearly',
    ];
    // Prepare the period and interval based on the membership level
    $period = strtolower($level->cycle_period); // monthly, yearly, etc.
    $interval = $level->cycle_number; // The number of periods (1, 3, 6, etc.)

    // RazorPay API credentials
    $razorpay_key = pmpro_getOption('razorpay_key_id');
    $razorpay_secret = pmpro_getOption('razorpay_secret_key');

    // Initialize Razorpay API
    $api = new Razorpay\Api\Api($razorpay_key, $razorpay_secret);


    // Prepare the plan data to be sent to RazorPay
    $plan_data = array(
        'period' => $razorpay_period[$period], // You can change this as per the membership level settings
        'interval' => $interval,       // You can change this as per the membership level settings
        'item' => array(
        	'name' => $level->name,
            'amount' => $level->billing_amount * 100, // Amount in paise (RazorPay requires amounts in the smallest currency unit)
            'currency' => $currency,
            'description' => $level->description
        )
    );
    try {
        // Create the plan using the RazorPay SDK
    	$plan = $api->plan->create($plan_data);
        // Check if the API request was successful
    	if (!empty($plan->id)) {
            // Save the RazorPay plan ID in the membership level meta
    		update_pmpro_membership_level_meta($level_id, 'razorpay_plan_id', $plan->id);
    	}
    } catch (Exception $e) {

    	error_log('RazorPay Plan creation failed: ' . $e->getMessage());
    }
}

add_action('pmpro_confirmation_message', 'handle_razorpay_confirmation', 999, 2);

function handle_razorpay_confirmation($pmpro_level, $morder) {
	$razorpay_secret = pmpro_getOption('razorpay_secret');
	
    // Check if Razorpay is the payment gateway
	if ($morder->gateway !== 'razorpay') {
		return;
	}
    // Get the Razorpay Payment ID and Subscription ID from the URL or session (depending on how you implement it)
	$razorpay_payment_id = isset($_REQUEST['razorpay_payment_id']) ? sanitize_text_field($_REQUEST['razorpay_payment_id']) : '';
	$razorpay_subscription_id = isset($_REQUEST['razorpay_subscription_id']) ? sanitize_text_field($_REQUEST['razorpay_subscription_id']) : '';
	$generated_signature = hash_hmac('sha256',$razorpay_payment_id ."|".$razorpay_subscription_id, $razorpay_secret);
	if ($generated_signature == $_REQUEST['razorpay_signature']) {

        // Update the order with the Razorpay Payment ID
		$morder->payment_transaction_id = $razorpay_payment_id;
		
        // Update the order with the Razorpay Subscription ID
		$morder->subscription_transaction_id = $razorpay_subscription_id;
		$morder->status = 'success';
	}

    // Save the updated order
	$morder->saveOrder();
}
