
var options = {
	"key": "<?php echo pmpro_getOption('razorpay_key_id'); ?>", // Your Razorpay API key
	"subscription_id": "<?php echo $subscription_id; ?>", // Subscription ID generated in PHP
	"name": "Your Company Name",
	"description": "Subscription Plan Description",
	"image": "/your_logo.jpg",
	"callback_url": "<?php echo site_url('/payment-success'); ?>", // URL to handle successful payment
	"prefill": {
		"name": "<?php echo esc_js($name); ?>",
		"email": "<?php echo esc_js($email); ?>",
		"contact": "<?php echo esc_js($contact); ?>"
	},
	"notes": {
		"note_key_1": "Additional Info",
		"note_key_2": "More Details"
	},
	"theme": {
		"color": "#F37254"
	}
};
var rzp1 = new Razorpay(options);
document.getElementById('rzp-button1').onclick = function(e) {
	rzp1.open();
	e.preventDefault();
}
