<?php
namespace Bayarcash\WooCommerce;

class BayarcashCheckoutFee {
	private array $payment_methods;
	private const DISABLE_GATEWAY_ID = 'duitnowshopee-wc';
	private const DISABLE_MESSAGE = '<div class="woocommerce-error">SPayLater can only be used for orders up to RM 1000.</div>';
	private const CHECKOUT_ERROR_MESSAGE = 'There was an error processing your order using SPayLater. The order total exceeds the RM 1000 limit for this payment method. Please choose a different payment method or reduce your order total.';

	public function __construct() {
		$this->payment_methods = [
			'bayarcash-wc',
			'duitnow-wc',
			'linecredit-wc',
			'directdebit-wc',
			'duitnowqr-wc',
			'duitnowshopee-wc',
			'duitnowboost-wc',
			'duitnowqris-wc',
			'duitnowqriswallet-wc'
		];
		$this->init();
	}

	public function init(): void {
		add_action('woocommerce_cart_calculate_fees', [$this, 'add_checkout_fee']);
		add_filter('woocommerce_available_payment_gateways', [$this, 'disable_gateway_by_country']);
		add_filter('woocommerce_available_payment_gateways', [$this, 'disable_duitnowshopee_over_limit']);
		add_action('wp_footer', [$this, 'disable_checkout_button_for_payment_method']);
		add_action('woocommerce_before_checkout_process', [$this, 'check_payment_method_before_processing']);
		add_filter('woocommerce_checkout_error_message', [$this, 'custom_checkout_error_message'], 10, 2);
	}

	public function add_checkout_fee($cart): void {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}
		$chosen_payment_method = WC()->session->get('chosen_payment_method');
		if (!$this->is_bayarcash_payment_method($chosen_payment_method)) {
			return;
		}
		$settings = get_option('woocommerce_' . $chosen_payment_method . '_settings', []);
		// Extract fee-related settings
		$fee_settings = [
			'enable_additional_charges' => $settings['enable_additional_charges'] ?? 'no',
			'additional_charge_type' => $settings['additional_charge_type'] ?? '',
			'additional_charge_amount' => $settings['additional_charge_amount'] ?? '',
			'additional_charge_percentage' => $settings['additional_charge_percentage'] ?? '',
		];
		if ($fee_settings['enable_additional_charges'] !== 'yes') {
			return;
		}
		if (empty($fee_settings['additional_charge_type']) || empty($fee_settings['additional_charge_amount'])) {
			return;
		}
		$charge_type = $fee_settings['additional_charge_type'];
		$charge_amount = floatval($fee_settings['additional_charge_amount']);
		$charge_percentage = floatval($fee_settings['additional_charge_percentage']);

		if ($charge_amount <= 0 && $charge_percentage <= 0) {
			return;
		}

		$cart_total = $cart->get_subtotal() + $cart->get_shipping_total();
		$fee = 0;
		$fee_label = '';

		switch ($charge_type) {
			case 'fixed':
				$fee = $charge_amount;
				$fee_label = sprintf(__('Bayarcash Processing Fee (RM %s)', 'bayarcash-wc'), number_format($charge_amount, 2));
				break;
			case 'percentage':
				$fee = ($cart_total * $charge_amount) / 100;
				$fee_label = sprintf(__('Bayarcash Processing Fee (%s%%)', 'bayarcash-wc'), $charge_amount);
				break;
			case 'both':
				$fixed_fee = $charge_amount;
				$percentage_fee = ($cart_total * $charge_percentage) / 100;
				$fee = $fixed_fee + $percentage_fee;
				$fee_label = sprintf(__('Bayarcash Processing Fee (RM %s + %s%%)', 'bayarcash-wc'), number_format($charge_amount, 2), $charge_percentage);
				break;
		}

		if ($fee > 0) {
			$cart->add_fee($fee_label, $fee);
		}
	}

	public function disable_gateway_by_country($available_gateways) {
		if (is_admin()) {
			return $available_gateways;
		}
		if (!WC()->customer) {
			return $available_gateways;
		}
		$customer_country = WC()->customer->get_billing_country();
		foreach ($this->payment_methods as $method) {
			if (isset($available_gateways[$method])) {
				$settings = get_option('woocommerce_' . $method . '_settings', []);
				$enabled_country = $settings['enabled_country'] ?? '';
				// Show for all countries if 'ALL' is selected or if enabled_country is empty
				if ($enabled_country === 'ALL' || empty($enabled_country)) {
					continue;
				}
				if ($customer_country !== $enabled_country) {
					unset($available_gateways[$method]);
				}
			}
		}
		return $available_gateways;
	}

	public function disable_duitnowshopee_over_limit($available_gateways) {
		if (is_admin() || !is_checkout()) {
			return $available_gateways;
		}

		$cart_total = WC()->cart->get_total('edit');

		if ($cart_total > 1000 && isset($available_gateways[self::DISABLE_GATEWAY_ID])) {
			// Disable the payment method but still display it
			$available_gateways[self::DISABLE_GATEWAY_ID]->enabled = false;
			// Add custom message to the description
			$available_gateways[self::DISABLE_GATEWAY_ID]->description = self::DISABLE_MESSAGE . $available_gateways[self::DISABLE_GATEWAY_ID]->description;
		}

		return $available_gateways;
	}

	public function disable_checkout_button_for_payment_method() {
		if (is_checkout()) {
			?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Continuously check for changes in payment method selection
                    $('form.checkout').on('change', 'input[name="payment_method"]', function() {
                        // Check if the disabled payment method is selected
                        if ($('input[name="payment_method"]:checked').val() === '<?php echo self::DISABLE_GATEWAY_ID; ?>') {
                            // Disable the place order button
                            $('#place_order').prop('disabled', true).css('opacity', '0.5');
                        } else {
                            // Enable the place order button if other methods are selected
                            $('#place_order').prop('disabled', false).css('opacity', '1');
                        }
                    });
                    // Trigger the change event on page load in case the method is already selected
                    $('input[name="payment_method"]:checked').trigger('change');
                });
            </script>
			<?php
		}
	}

	public function check_payment_method_before_processing() {
		$chosen_payment_method = WC()->session->get('chosen_payment_method');
		$cart_total = WC()->cart->get_total('edit');

		if ($chosen_payment_method === self::DISABLE_GATEWAY_ID && $cart_total > 1000) {
			wc_add_notice(self::CHECKOUT_ERROR_MESSAGE, 'error');
		}
	}

	public function custom_checkout_error_message($error_message, $error_type) {
		if ($error_type === 'checkout') {
			$chosen_payment_method = WC()->session->get('chosen_payment_method');
			$cart_total = WC()->cart->get_total('edit');

			if ($chosen_payment_method === self::DISABLE_GATEWAY_ID && $cart_total > 1000) {
				return self::CHECKOUT_ERROR_MESSAGE;
			}
		}
		return $error_message;
	}

	private function is_bayarcash_payment_method($payment_method): bool {
		return in_array($payment_method, $this->payment_methods);
	}
}