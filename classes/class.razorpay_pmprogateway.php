<?php

//load classes init method
add_action('init', array('PMProGateway_Razorpay', 'init'));
add_filter('pmpro_is_ready', array( 'PMProGateway_Razorpay', 'pmpro_is_razorpay_ready' ), 999, 1 );

class PMProGateway_Razorpay extends PMProGateway {

	function __construct( $gateway = NULL ) {

		$this->gateway = $gateway;
		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	static function init() {
		//make sure Razorpay is a gateway option
		add_filter('pmpro_gateways', array('PMProGateway_Razorpay', 'pmpro_gateways')); //

        //add fields to payment settings
		add_filter('pmpro_payment_options', array('PMProGateway_Razorpay', 'pmpro_payment_options')); //
		add_filter('pmpro_payment_option_fields', array('PMProGateway_Razorpay', 'pmpro_payment_option_fields'), 10, 2); //
		add_action('wp_ajax_pmpro_razorpay_ipn', array('PMProGateway_Razorpay', 'pmpro_razorpay_ipn')); //
		add_action('wp_ajax_nopriv_pmpro_razorpay_ipn', array('PMProGateway_Razorpay', 'pmpro_razorpay_ipn')); //

        //code to add at checkout
		$gateway = pmpro_getGateway();
		if ($gateway == "razorpay") {
			// Add support for custom fields.
			add_action( 'pmpro_before_send_to_razorpay', 'pmpro_after_checkout_save_fields', 20, 2 ); ///

			//add_filter('pmpro_include_billing_address_fields', '__return_false');
			add_filter('pmpro_required_billing_fields', array('PMProGateway_Razorpay', 'pmpro_required_billing_fields'));
			add_filter('pmpro_include_payment_information_fields', '__return_false', 20 );
			add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_Razorpay', 'pmpro_checkout_before_change_membership_level'), 10, 2); //

			add_filter('pmpro_gateways_with_pending_status', array('PMProGateway_Razorpay', 'pmpro_gateways_with_pending_status'));

			add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_Razorpay', 'pmpro_checkout_default_submit_button')); //

            // custom confirmation page
			//add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_Razorpay', 'pmpro_pages_shortcode_confirmation'), 20, 1); //

            // Refund functionality.
			add_filter( 'pmpro_allowed_refunds_gateways', array( 'PMProGateway_Razorpay', 'pmpro_allowed_refunds_gateways' ) ); //
			add_filter( 'pmpro_process_refund_razorpay', array( 'PMProGateway_Razorpay', 'process_refund' ), 10, 2 );
			// Hook into checkout processing
			//add_action('pmpro_checkout_before_processing', array('PMProGateway_Razorpay', 'checkout_process'), 10, 2);

			//add_action('pmpro_checkout_after_billing_fields', array('PMProGateway_Razorpay', 'add_razorpay_hidden_field'));

		}
	}

	/**
     * Make sure Razorpay is in the gateways list
     */
	static function pmpro_gateways($gateways)
	{
		if (empty($gateways['razorpay'])) {
			$gateways = array_slice($gateways, 0, 1) + array("razorpay" => __('Razorpay', 'razorpay-gateway-paid-memberships-pro')) + array_slice($gateways, 1);
		}
		return $gateways;
	}

	/**
     * Get a list of payment options that the Razorpay gateway needs/supports.
     */
	static function getGatewayOptions()
	{
		$options = array (
			'razorpay_key',
			'razorpay_secret',
			'currency',
			'tax_state',
			'tax_rate'
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 */
	static function pmpro_payment_options($options)
	{
                    //get Razorpay options
		$razorpay_options = self::getGatewayOptions();

                    //merge with others.
		$options = array_merge($razorpay_options, $options);

		return $options;
	}
	/**
	 * Check if all fields are complete
	 */
	static function pmpro_is_razorpay_ready( $ready ){

		if ( get_option('razorpay_key') == "" ||
			get_option('razorpay_secret') == "")
		{
			$ready = false;
		}else{
			$ready = ture;
		}
	}
	static function pmpro_payment_option_fields($values, $gateway)
	{
		?>
		<tr class="pmpro_settings_divider gateway gateway_razorpay" <?php if ($gateway != "razorpay") { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<hr />
				<h2><?php _e('RazorPay Settings', 'pmpro-razorpay'); ?></h2>
			</td>
		</tr>
		<tr class="gateway gateway_razorpay" <?php if ($gateway != "razorpay") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="razorpay_key"><?php _e('RazorPay Key', 'pmpro-razorpay'); ?>:</label>
			</th>
			<td>
				<input type="text" id="razorpay_key" name="razorpay_key" size="60" value="<?php echo esc_attr($values['razorpay_key']); ?>" />
			</td>
		</tr>
		<tr class="gateway gateway_razorpay" <?php if ($gateway != "razorpay") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="razorpay_secret"><?php _e('RazorPay Secret', 'pmpro-razorpay'); ?>:</label>
			</th>
			<td>
				<input type="text" id="razorpay_secret" name="razorpay_secret" size="60" value="<?php echo esc_attr($values['razorpay_secret']); ?>" />
			</td>
		</tr>
		<?php
	}

	/**
     * Webhook handler for Razorpay.
     * @since 1.0 (Renamed in 1.7.1)
     */
	// static function pmpro_razorpay_ipn() {
	// 	global $wpdb;

    //                 // Let's make sure the request came from Razorpay by checking the secret key
	// 	if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) || ! array_key_exists( 'HTTP_X_PAYSTACK_SIGNATURE', $_SERVER ) ) {
	// 		pmpro_razorpay_webhook_log( 'Razorpay signature not found' );
	// 		pmpro_razorpay_webhook_exit();
	// 	}

    //                 // Log all the $_POST data to the IPN log.
	// 	pmpro_razorpay_webhook_log( print_r( $_POST, true ) );

    //                 // Get the relevant secret key based on gateway environment.
	// 	$mode = pmpro_getOption("gateway_environment");
	// 	if ($mode == 'sandbox') {
	// 		$secret_key = pmpro_getOption("razorpay_tsk");
	// 	} else {
	// 		$secret_key = pmpro_getOption("razorpay_lsk");
	// 	}


	// 	$input = @file_get_contents("php://input");

    //                 // The Razorpay signature doesn't match the secret key, let's bail.
	// 	if ( $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, $secret_key ) ) {
	// 		pmpro_razorpay_webhook_log( 'Razorpay signature does not match.' );
	// 		pmpro_razorpay_webhook_exit();
	// 	}

	// 	$event = json_decode( $input );
	// 	pmpro_razorpay_webhook_log( 'Event: ' . print_r( $event, true ) );

	// 	switch( $event->event ){
	// 		case 'subscription.create':

	// 		break;
	// 		case 'subscription.disable':
	// 		$amount = $event->data->subscription->amount/100;
	// 		$morder = new MemberOrder();
	// 		$subscription_code = $event->data->subscription_code;
	// 		$email = $event->data->customer->email;
	// 		$morder->Email = $email;
	// 		$users_row = $wpdb->get_row( "SELECT ID, display_name FROM $wpdb->users WHERE user_email = '" . esc_sql( $email ). "' LIMIT 1" );
	// 		if ( ! empty( $users_row )  ) {
	// 			$user_id = $users_row->ID;
	// 			$user = get_userdata($user_id);
	// 			$user->membership_level = pmpro_getMembershipLevelForUser($user_id);
	// 		}

	// 		if ( empty( $user ) ) {
	// 			pmpro_razorpay_webhook_log( 'Could not get user' );
	// 			pmpro_razorpay_webhook_exit();
	// 		}
	// 		self::cancelMembership($user);
	// 		break;
	// 		case 'charge.success':
	// 		$morder =  new MemberOrder($event->data->reference);
	// 		$morder->getMembershipLevel();
	// 		$morder->getUser();
	// 		$morder->Gateway->pmpro_pages_shortcode_confirmation('', $event->data->reference);
	// 		$mode = pmpro_getOption("gateway_environment");
	// 		if ($mode == 'sandbox') {
	// 			$pk = pmpro_getOption("razorpay_tpk");
	// 		} else {
	// 			$pk = pmpro_getOption("razorpay_lpk");
	// 		}
	// 		$pstk_logger = new pmpro_razorpay_plugin_tracker('pm-pro',$pk);
	// 		$pstk_logger->log_transaction_success($event->data->reference);
	// 		pmpro_razorpay_webhook_log( 'Charge success. Reference: ' . $event->data->reference );
	// 		break;
	// 		case 'invoice.create':
	// 		self::renewpayment($event);
	// 		break;
	// 		case 'invoice.update':
	// 		self::renewpayment($event);
	// 		break;
	// 	}
	// 	http_response_code(200);
	// 	pmpro_razorpay_webhook_exit();
	// }

	static function pmpro_required_billing_fields($fields)
	{
		// unset($fields['bfirstname']);
		// unset($fields['blastname']);
		// unset($fields['baddress1']);
		// unset($fields['bcity']);
		// unset($fields['bstate']);
		// unset($fields['bzipcode']);
		// unset($fields['bphone']);
		// unset($fields['bemail']);
		// unset($fields['bcountry']);
		unset($fields['CardType']);
		unset($fields['AccountNumber']);
		unset($fields['ExpirationMonth']);
		unset($fields['ExpirationYear']);
		unset($fields['CVV']);

		return $fields;
	}

	private static function create_razorpay_plan($level_id)
	{
        // Get the membership level data
		$level = pmpro_getLevel($level_id);

        // Get the currency from PMPro settings
		$currency = pmpro_getOption('currency');

        // Prepare the period and interval based on the membership level
        $period = strtolower($level->cycle_period); // monthly, yearly, etc.
        $interval = $level->cycle_number; // The number of periods (1, 3, 6, etc.)

        // RazorPay API credentials
        $razorpay_key = pmpro_getOption('razorpay_key');
        $razorpay_secret = pmpro_getOption('razorpay_secret');

        // Initialize Razorpay API
        $api = new Razorpay\Api\Api($razorpay_key, $razorpay_secret);

        // Prepare the plan data to be sent to RazorPay
        $plan_data = array(
        	'period' => $period,
        	'interval' => $interval,
        	'item' => array(
        		'name' => $level->name,
                'amount' => $level->billing_amount * 100, // Amount in the smallest currency unit
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
        		return $plan->id;
        	}
        } catch (Exception $e) {
        	wp_die('RazorPay Plan creation failed: ' . $e->getMessage());
        }

        return null;
    }

	/**
     * Instead of change membership levels, send users to Razorpay payment page.
     */
	static function pmpro_checkout_before_change_membership_level( $user_id, $morder ) {
		global $wpdb, $discount_code_id;

        //if no order, no need to pay
		if ( empty( $morder  )) {
			return;
		}

		if ( empty( $morder->code ) ) {
			$morder->code = $morder->getRandomCode();
		}

		$morder->payment_type = "razorpay";
		$morder->status = "pending";
		$morder->user_id = $user_id;
		$morder->saveOrder();

        // Try to get the discount_code from a query param.
		if ( empty( $discount_code_id ) ) {
            // PMPro 3.0+
			if ( isset( $_REQUEST['pmpro_discount_code'] ) ) {
				$discount_code = sanitize_text_field( $_REQUEST['pmpro_discount_code'] );
			}

            // PMPro < 3.0
			if ( isset( $_REQUEST['discount_code'] ) ) {
				$discount_code = sanitize_text_field( $_REQUEST['discount_code'] );
			}
		}
        // if global is empty but query is available. PMPro 3.0
		if ( empty( $discount_code_id ) && ! empty( $discount_code ) ) {
			$discount_code_id = $wpdb->get_var( "SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql( $discount_code ) . "'" );
		}

        // save discount code use
		if ( ! empty( $discount_code_id ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $wpdb->pmpro_discount_codes_uses 
					(code_id, user_id, order_id, timestamp) 
					VALUES( %d , %d, %d, %s )",
					$discount_code_id,
					$user_id,
					$morder->id,
					current_time( 'mysql' )
				)
			);
		}

		do_action("pmpro_before_send_to_razorpay", $user_id, $morder);

		$morder->Gateway->sendToRazorpay($morder);
	}


	private function sendToRazorpay(&$order){
		
		
		$razorpay_key = pmpro_getOption('razorpay_key');
		// Initialize Razorpay API
		$api = self::initialize_razorpay_api();
		
		// Retrieve or create the Razorpay subscription
		$razorpay_plan_id = self::get_or_create_razorpay_plan($order->membership_id, $api);
		$razorpay_customer_id = self::get_or_create_razorpay_customer($order->user_id, $order->Email, $order->FirstName." ".$order->LastName, $api);
		$razorpay_subscription_id = self::create_razorpay_subscription($razorpay_plan_id, $razorpay_customer_id, $api);
		$order_id = $order->code; ?>
		<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
		<script>
			var options = {
				"key": "<?php echo esc_js($razorpay_key); ?>",
	            "subscription_id": "<?php echo esc_js($razorpay_subscription_id); ?>", // Pass the order ID
	            "name": "Your Company Name",
	            "description": "Subscription Plan",
	            "image": "https://iconithemes.com/wp-content/uploads/2024/07/iconithemes-logo-2.png",
	            "callback_url": "<?php echo esc_url(pmpro_url("confirmation")."?pmpro_level=".$order->membership_id)."&pmpro_order=".$order_id; ?>", // URL to handle successful payment
	            "prefill": {
	            	"name": "<?php echo esc_js($order->FirstName." ".$order->LastName); ?>",
	            	"email": "<?php echo esc_js($order->Email); ?>",
	            },
	            "theme": {
	            	"color": "#F37254"
	            }
	        };
	        console.log(options);
	        var rzp1 = new Razorpay(options);
	        rzp1.open();
	    </script>
	    <?php
    	// Prevent PMPro from continuing to change membership level
	    exit;
	}





	public static function prepare_subscription($user_id, $level_id)
	{
		global $pmpro_level, $current_user;
    	// Initialize Razorpay API
		$api = self::initialize_razorpay_api();

    	// Retrieve or create the Razorpay subscription
		$razorpay_plan_id = self::get_or_create_razorpay_plan($level_id, $api);
		$razorpay_customer_id = self::get_or_create_razorpay_customer($user_id, $current_user->user_email, $current_user->display_name, $api);
		$razorpay_subscription_id = self::create_razorpay_subscription($razorpay_plan_id, $razorpay_customer_id, $api);

		return $razorpay_subscription_id;
	}

	public static function checkout_process($user_id, $morder)
	{
		global $pmpro_level, $current_user;

        // Check if RazorPay is the active gateway
		$active_gateway = pmpro_getOption('gateway');
		if ($active_gateway !== 'razorpay') {
            return; // Exit if RazorPay is not active
        }

        // Initialize Razorpay API
        $api = self::initialize_razorpay_api();

        // Step 1: Retrieve or Create Razorpay Plan
        $level_id = $pmpro_level->id;
        $razorpay_plan_id = self::get_or_create_razorpay_plan($level_id, $api);

        if (empty($razorpay_plan_id)) {
        	wp_die('Failed to retrieve or create Razorpay Plan.');
        }

        // Step 2: Retrieve or Create Razorpay Customer
        $user_email = $current_user->user_email;
        $user_name = $current_user->display_name;

        $razorpay_customer_id = self::get_or_create_razorpay_customer($user_id, $user_email, $user_name, $api);

        if (empty($razorpay_customer_id)) {
        	wp_die('Failed to retrieve or create Razorpay Customer.');
        }

        // Step 3: Create Razorpay Subscription
        $subscription_id = self::create_razorpay_subscription($razorpay_plan_id, $razorpay_customer_id, $api);

        if (empty($subscription_id)) {
        	wp_die('Failed to create Razorpay Subscription.');
        }

        // Step 4: Save Subscription Details in Order Meta
        update_post_meta($morder->id, 'razorpay_subscription_id', $subscription_id);
    }

    private static function initialize_razorpay_api()
    {
    	$razorpay_key = pmpro_getOption('razorpay_key');
    	$razorpay_secret = pmpro_getOption('razorpay_secret');

    	return new Razorpay\Api\Api($razorpay_key, $razorpay_secret);
    }

    private static function get_or_create_razorpay_plan($level_id, $api)
    {
        // Check if plan ID exists
    	$razorpay_plan_id = get_pmpro_membership_level_meta($level_id, 'razorpay_plan_id', true);

    	if (!empty($razorpay_plan_id)) {
    		return $razorpay_plan_id;
    	}

        // Plan doesn't exist, create a new one
    	$level = pmpro_getLevel($level_id);
    	$currency = pmpro_getOption('currency');

        // Prepare plan data
        $period = strtolower($level->cycle_period); // e.g., 'monthly', 'yearly'
        $interval = $level->cycle_number; // e.g., 1, 3

        // Map PMPro period to Razorpay period
        $valid_periods = array('daily', 'weekly', 'monthly', 'yearly');
        if (!in_array($period, $valid_periods)) {
        	wp_die('Invalid billing period for Razorpay. Supported periods are daily, weekly, monthly, yearly.');
        }

        $plan_data = array(
        	'period' => $period,
        	'interval' => $interval,
        	'item' => array(
        		'name' => $level->name,
                'amount' => $level->billing_amount * 100, // Convert to smallest currency unit
                'currency' => $currency,
                'description' => $level->description
            )
        );

        try {
        	$plan = $api->plan->create($plan_data);

        	if (!empty($plan->id)) {
                // Save plan ID for future use
        		update_pmpro_membership_level_meta($level_id, 'razorpay_plan_id', $plan->id);
        		return $plan->id;
        	}
        } catch (Exception $e) {
        	wp_die('Razorpay Plan creation failed: ' . $e->getMessage());
        }

        return null;
    }

    private static function get_or_create_razorpay_customer($user_id, $email, $name, $api)
    {
        // Check if customer ID exists
    	$razorpay_customer_id = get_user_meta($user_id, 'razorpay_customer_id', true);

    	if (!empty($razorpay_customer_id)) {
    		return $razorpay_customer_id;
    	}

        // Customer doesn't exist, create a new one
    	$customer_data = array(
    		'name' => $name,
    		'email' => $email,
            // Add more fields as necessary, e.g., contact, notes
    	);

    	try {
    		$customer = $api->customer->create($customer_data);

    		if (!empty($customer->id)) {
                // Save customer ID for future use
    			update_user_meta($user_id, 'razorpay_customer_id', $customer->id);
    			return $customer->id;
    		}
    	} catch (Exception $e) {
    		wp_die('Razorpay Customer creation failed: ' . $e->getMessage());
    	}

    	return null;
    }

    private static function create_razorpay_subscription($plan_id, $customer_id, $api)
    {
        // Prepare subscription data
    	$subscription_data = array(
    		'plan_id' => $plan_id,
    		'customer_notify' => 1,
            'total_count' => 999, // Set as per your requirement
            'customer_id' => $customer_id,
            // 'start_at' => future_timestamp, // Optional: Set if you want to start the subscription at a future date
            // 'addons' => array(), // Optional: Addons if any
            // 'notes' => array(), // Optional: Any notes
        );

    	try {
    		$subscription = $api->subscription->create($subscription_data);

    		if (!empty($subscription->id)) {
    			return $subscription->id;
    		}
    	} catch (Exception $e) {
    		wp_die('Razorpay Subscription creation failed: ' . $e->getMessage());
    	}

    	return null;
    }

    static function pmpro_checkout_default_submit_button($show)
    {
    	global $gateway, $pmpro_requirebilling, $current_user, $pmpro_level;

		//show our submit buttons
    	?>
    	<span id="pmpro_submit_span">
    		<input type="hidden" name="submit-checkout" value="1" />
    		<input type="submit" id="rzp-button1" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout' ) ); ?>" value="<?php if ( $pmpro_requirebilling ) { esc_html_e( 'Check Out with Razorpay', 'razorpay-gateway-paid-memberships-pro' ); } else { esc_html_e( 'Submit and Confirm', 'razorpay-gateway-paid-memberships-pro' ); }?>" />
    	</span>
    	<?php

        //don't show the default
    	return false;
    }

	/** 
     * Enable refund functionality for razorpay.
     * @since TBD.
     */
	static function pmpro_allowed_refunds_gateways( $gateways ) {
		$gateways[] = 'razorpay';
		return $gateways;
	}

	/**
     * Allow refunds from within Paid Memberships Pro and Razorpay.
     * @since TBD
     */
	public static function process_refund( $success, $order ) {
		global $current_user;

        //default to using the payment id from the order
		if ( !empty( $order->payment_transaction_id ) ) {
			$transaction_id = $order->payment_transaction_id;
		}

        //need a transaction id
		if ( empty( $transaction_id ) ) {
			return false;
		}

        // OKAY do the refund now.
        // Make the API call to PayStack to refund the order.
		$mode = pmpro_getOption("gateway_environment");
		if ( $mode == "sandbox" ) {
			$key = pmpro_getOption("razorpay_tsk");

		} else {
			$key = pmpro_getOption("razorpay_lsk");
		}

		$razorpay_url = 'https://api.razorpay.co/refund/';

		$headers = array(
			'Authorization' => 'Bearer ' . $key,
			'Cache-Control' => 'no-cache'
		);

      	// The transaction ID for the refund.
		$fields = array(
			'transaction' => $transaction_id
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
			'body' => $fields
		);


		$success = false;

        // Try to make the API call now.
		$request = wp_remote_post( $razorpay_url, $args );

		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

            // If not successful throw an error.
			if ( ! $response->status ) {
				$order->notes = trim( $order->notes.' '.sprintf( __('Admin: Order refund failed on %1$s for transaction ID %2$s by %3$s. Order may have already been refunded.', 'paid-memberships-pro' ), date_i18n('Y-m-d H:i:s'), $transaction_id, $current_user->display_name ) );
				$order->saveOrder();
			} else {
                // Set the order status to refunded and save it and return true
				$order->status = 'refunded';

				$success = true;

				$order->notes = trim( $order->notes.' '.sprintf( __('Admin: Order successfully refunded on %1$s for transaction ID %2$s by %3$s.', 'paid-memberships-pro' ), date_i18n('Y-m-d H:i:s'), $transaction_id, $current_user->display_name ) );	

				$user = get_user_by( 'id', $order->user_id );

                //send an email to the member
				$myemail = new PMProEmail();
				$myemail->sendRefundedEmail( $user, $order );

                //send an email to the admin
				$myemail = new PMProEmail();
				$myemail->sendRefundedAdminEmail( $user, $order );

				$order->saveOrder();
			}                            
		}
		return $success;

	}
	static function add_razorpay_hidden_field() {
		?>
		<input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" value="" />
		<?php
	}
}
