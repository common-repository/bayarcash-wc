<?php
namespace Bayarcash\WooCommerce;

use AllowDynamicProperties;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use WC_Order;
use WC_Order_Refund;
use WC_Subscriptions_Manager;
use Webimpian\BayarcashSdk\Bayarcash;
use Webimpian\BayarcashSdk\Exceptions\ValidationException;

class DirectDebitGateway extends Gateway
{
	public function __construct()
	{
		parent::__construct('directdebit');
		$this->supports = ['products', 'subscriptions', 'subscription_cancellation', 'gateway_scheduled_payments'];
		$this->init_form_fields();
		$this->init_settings();
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Recurring Direct Debit',
			'method_title' => 'Bayarcash Direct Debit'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Recurring payment via online banking Maybank2u, CIMB Clicks, Bank Islam GO and more banks from Malaysia.',
            'method_description' => 'Allow customers to pay with Direct Debit.',
        ];
    }

	public function is_available(): bool
	{
		$is_available = parent::is_available();

		if ($is_available && !$this->cart_contains_subscription()) {
			$is_available = false;
		}

		return $is_available;
	}

	protected function cart_contains_subscription(): bool
	{
		if (!function_exists('WC') || !$this->has_subscriptions()) {
			return false;
		}

		$cart = WC()->cart;

		if (!$cart || $cart->is_empty()) {
			return false;
		}

		foreach ($cart->get_cart() as $cart_item) {
			$product = $cart_item['data'];
			if ($this->is_subscription_product($product)) {
				return true;
			}
		}

		return false;
	}
	protected function initialize_bayarcash_sdk($settings): void {
		if (!isset($this->bayarcashSdk)) {
			$bearer_token = $settings['bearer_token'] ?? '';
			$this->bayarcashSdk = new Bayarcash($bearer_token);

			if (isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes') {
				$this->bayarcashSdk->useSandbox();
			}
		}
	}

	protected function set_icon(): void {
		$settings = get_option('woocommerce_directdebit-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'directdebit-all.png';
		} else {
			$icon_filename = 'direct-debit.png';
		}

		$icon_url = $this->url . 'includes/admin/img/directdebit/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}

	protected function register_hooks(): void {
		parent::register_hooks();
		add_action('woocommerce_subscription_cancelled_' . $this->id, [$this, 'cancel_subscriptions_for_order']);
		add_action('woocommerce_before_order_notes', [$this, 'add_identification_fields']);
		add_action('woocommerce_subscription_status_cancelled', [$this, 'cancel_subscription'], 10, 1);
		add_action('admin_notices', [$this, 'bayarcash_admin_notices']);

	}

	public function add_identification_fields($checkout): void {
		if (!$this->has_subscriptions()) {
			return;
		}

		// Check if FunnelKit or FunnelKit Pro is active
		if (class_exists('WFFN_Core') || class_exists('WFFN_Pro_Core')) {
			return; // Exit if either FunnelKit or FunnelKit Pro is active
		}

		include_once BAYARCASH_WC['PATH'].'/includes/admin/checkout-fields.php';
	}

	public function process_payment($order_id): array {
		$order = wc_get_order($order_id);

		$order_no = $order->get_id();
		$errors = array();

		$payment_data = $this->get_payment_settings($this->id);
		$this->log('Direct Debit');
		$settings = $payment_data['settings'];

		$portal_key = $settings['portal_key'] ?? '';
		$bearer_token = $settings['bearer_token'] ?? '';

		if (empty($bearer_token)) {
			$errors[] = esc_html__('Personal Access Token (PAT) is empty', 'bayarcash-wc');
		}

		if (empty($portal_key)) {
			$errors[] = esc_html__('Portal Key is empty', 'bayarcash-wc');
		}

		$identification_type = null;
		$identification_id = null;
		$item_subscription = 0;

		if ($this->has_subscriptions()) {
			$item_not_subscription = 0;
			foreach ($order->get_items() as $item_id => $item) {
				if ($this->is_subscription_product($item->get_product())) {
					++$item_subscription;
				} else {
					++$item_not_subscription;
				}
			}

			if ($item_subscription > 0 && $item_not_subscription > 0) {
				$errors[] = esc_html__("Subscription can't checkout with non subscription items", 'bayarcash-wc');
			} elseif ($item_subscription > 1) {
				$errors[] = esc_html__("Subscription can't checkout with more than one subscriptions", 'bayarcash-wc');
			}

			if ($item_subscription) {
				if (empty($_POST['bayarcash_identification_type'])) {
					$errors[] = esc_html__('Please select identification type.', 'bayarcash-wc');
				} else {
					$identification_type = sanitize_text_field($_POST['bayarcash_identification_type']);
				}

				if (empty($_POST['bayarcash_identification_id'])) {
					$errors[] = esc_html__('Please enter identification number.', 'bayarcash-wc');
				} else {
					$identification_id = sanitize_text_field($_POST['bayarcash_identification_id']);
				}
			}
		}

		if (!empty($errors)) {
			$error_message = implode(' ', $errors);
			$this->log('Error in settings: ' . $error_message);

			wc_add_notice($error_message, 'error');

			return array(
				'result'   => 'failure',
				'messages' => $errors,
			);
		}

		$token_value = $order_no.','.$identification_type.','.$identification_id.','.$item_subscription;

		return array(
			'result'   => 'success',
			'redirect' => get_site_url().'/?wc-api=bayarcash_payment&bc-woo-return='.bayarcash_return_token_set($token_value, 'bayarcash_payment', 'process_payment'),
		);
	}

	public function process_bayarcash(): void {
		$this->log('Starting Direct Debit Bayarcash process');

		try {
			$this->validate_request();
			$order = $this->get_and_validate_order();
			$settings = $this->get_payment_settings($this->id);

			// Initialize the Bayarcash SDK
			$this->initialize_bayarcash_sdk($settings);

			$args = $this->prepare_enrollment_args($order, $settings);

			$this->log_enrollment_args($args);

			$response = $this->create_fpx_direct_debit_enrollment($args, $settings);

			$this->log('Redirecting to Direct Debit Enrollment URL: ' . $response->url);
			wp_redirect($response->url);
			exit;
		} catch (ValidationException $e) {
			$error_message = $e->getMessage();
			$this->log('Validation Exception: ' . $error_message);

			// Extract detailed error messages
			$errors = $e->errors;
			if (is_array($errors) && !empty($errors)) {
				$detailed_error = implode('. ', $errors);
			} else {
				$detailed_error = $error_message;
			}

			$this->log('Detailed Validation Errors: ' . $detailed_error);

			// Display user-friendly message
			wc_add_notice(__('We encountered an issue while processing your Direct Debit enrollment: ', 'bayarcash-wc') . $detailed_error, 'error');

			wp_redirect(wc_get_checkout_url());
			exit;
		} catch (Exception $e) {
			$this->log('General Exception: ' . $e->getMessage());
			wc_add_notice(__('An unexpected error occurred. Please try again or contact support.', 'bayarcash-wc'), 'error');
			wp_redirect(wc_get_checkout_url());
			exit;
		}
	}

	/**
	 * @throws Exception
	 */
	private function validate_request(): void
	{
		if (empty($_GET['bc-woo-return'])) {
			throw new Exception('Invalid request: Missing bc-woo-return parameter');
		}

		$data_key = sanitize_text_field($_GET['bc-woo-return']);
		$data_dec = bayarcash_return_token_get($data_key, 'bayarcash_payment', 'process_payment');

		if (!$data_dec) {
			throw new Exception('Invalid token key: ' . $data_key);
		}

		$this->data_dec = $data_dec;
	}

	/**
	 * @return bool|WC_Order|WC_Order_Refund
	 * @throws Exception
	 */
	private function get_and_validate_order()
	{
		$data_dec_arr = explode(',', $this->data_dec->data);
		$order = wc_get_order($data_dec_arr[0]);

		if (!$order || 'completed' === $order->get_status()) {
			throw new Exception('Invalid Order Number or Order already completed');
		}

		return $order;
	}

	private function prepare_enrollment_args(\WC_Abstract_Order $order, array $settings): array
	{
		$order_no = $order->get_id();
		$portal_key = $settings['settings']['portal_key'] ?? '';
		$api_secret_key = $settings['settings']['api_secret_key'] ?? '';
		$subscription_period = $this->get_subscription_period($order);

		$order_data = $order->get_data();
		$billing = $order_data['billing'];
		$buyer_name = $this->sanitize_buyer_name($billing['first_name'] . ' ' . $billing['last_name']);
		$buyer_email = $order->get_billing_email();
		$buyer_phone = $this->format_phone_number($order->get_billing_phone());
		$return_url = get_site_url() . '/?wc-api=bayarcash_callback';

		$data_dec_arr = explode(',', $this->data_dec->data);

		$args = [
			"portal_key" => $portal_key,
			"order_number" => $order_no,
			"amount" => number_format($order->get_total(), 2, '.', ''),
			"payer_name" => $buyer_name,
			"payer_email" => $buyer_email,
			"payer_telephone_number" => !empty($buyer_phone) ? $buyer_phone : '0123654789',
			"payer_id_type" => $data_dec_arr[1],
			"payer_id" => $data_dec_arr[2],
			"frequency_mode" => !empty($subscription_period) && 'week' === $subscription_period ? 'WK' : 'MT',
			"application_reason" => 'Enrollment of ' . $order_no,
			"metadata" => json_encode(["order_id" => $order_no]),
			"return_url" => $return_url . '&bc-woo-return=' . bayarcash_return_token_set($order_no, $order_no, 'directdebit'),
			"success_url" => $return_url . '&bc-woo-success=' . bayarcash_return_token_set($order_no, $order_no, 'directdebit'),
			"failed_url" => $return_url . '&bc-woo-failed=' . bayarcash_return_token_set($order_no, 'bc-woo-failed', 'directdebit'),
		];

		$this->initialize_bayarcash_sdk($settings);

		$args['checksum'] = $this->bayarcashSdk->createFpxDIrectDebitEnrolmentChecksumValue($api_secret_key, $args);

		return $args;
	}

	private function get_subscription_period(\WC_Abstract_Order $order): string
	{
		if (!$this->has_subscriptions()) {
			return '';
		}

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product && $this->is_subscription_product($product)) {
				// Use the fully qualified class name
				return \WC_Subscriptions_Product::get_period($product);
			}
		}

		return '';
	}

	private function format_phone_number(string $phone): ?string
	{
		$phone = preg_replace('/^\+?6?0?|\D/', '', $phone);
		if (preg_match('/^1\d{8,12}$/', $phone)) {
			return '0' . $phone;
		}
		return null;
	}

	private function sanitize_buyer_name($name): string {
		$name = preg_replace('/[^a-zA-Z0-9\s-]/', '', $name);
		return trim($name) ?: '';
	}

	private function log_enrollment_args(array $args): void
	{
		$log_args = $args;
		$log_args['checksum'] = substr($log_args['checksum'], 0, 8) . '********';
		$this->log('Direct Debit Enrollment Args: ' . print_r($log_args, true));
	}

	/**
	 * @throws Exception
	 */
	private function create_fpx_direct_debit_enrollment(array $args, array $settings): object
	{
		$bearer_token = $settings['settings']['bearer_token'] ?? '';
		$this->log('Bearer Token: ' . (empty($bearer_token) ? 'Not set' : 'Set'));
		$this->bayarcashSdk = new Bayarcash($bearer_token);

		if (isset($settings['settings']['sandbox_mode']) && $settings['settings']['sandbox_mode'] === 'yes') {
			$this->bayarcashSdk->useSandbox();
			$this->log('Sandbox mode enabled');
		}

		$response = $this->bayarcashSdk->createFpxDirectDebitEnrollment($args);

		if (empty($response->url)) {
			throw new Exception('Direct Debit Enrollment Failed: Empty URL returned');
		}

		return $response;
	}

	public function process_callback(): void {
		//$this->log('Starting Direct Debit callback processing');

		$json_data = file_get_contents('php://input');
		$data = json_decode($json_data, true);

		//$this->log('Decoded JSON data: ' . print_r($data, true));

		if (is_array($data)) {
			$_POST = array_merge($_POST, $data);
		}

		$response_data = $_POST;

		if (isset($response_data['record_type'])) {
			$this->log('Processing record type: ' . $response_data['record_type']);
			// $this->log('Decoded JSON data: ' . print_r($response_data, true));
		}

		// Handle bank_approval record type
		if (isset($response_data['record_type']) && $response_data['record_type'] === 'bank_approval') {
			$this->handle_bank_approval($response_data);
			return;
		}

		// Handle transaction record type (recurring subscription)
		if (isset($response_data['record_type']) && $response_data['record_type'] === 'transaction') {
			$this->handle_return_callback($response_data);
			return;
		}

		$order = null;
		if (isset($_GET['bc-woo-success']) || isset($_GET['bc-woo-failed']) || isset($_GET['bc-woo-return'])) {
			$order_id = $this->get_order_id_from_callback();
			if ($order_id) {
				$order = wc_get_order($order_id);
			}
		}

		if (!$order) {
			$this->log('Invalid order in Direct Debit callback');
			wp_send_json_error('Invalid order', 403);
			return;
		}

		if (isset($_GET['bc-woo-success'])) {
			$this->handle_success_callback($order);
		} elseif (isset($_GET['bc-woo-failed'])) {
			$this->handle_failed_callback($order);
		}

		$this->log('Finished Direct Debit callback processing');
	}

	private function get_order_id_from_callback()
	{
		if (isset($_GET['bc-woo-success'])) {
			$data_key = sanitize_text_field($_GET['bc-woo-success']);
			$order_no = isset($_POST['order_number']) ? sanitize_text_field($_POST['order_number']) : null;
			$token_data = bayarcash_return_token_get($data_key, $order_no, 'directdebit');
			return $token_data ? $token_data->data : null;
		} elseif (isset($_GET['bc-woo-failed'])) {
			$data_key = sanitize_text_field($_GET['bc-woo-failed']);
			$token_data = bayarcash_return_token_get($data_key, 'bc-woo-failed', 'directdebit');
			return $token_data ? $token_data->data : null;
		} elseif (isset($_GET['bc-woo-return'])) {
			return isset($_POST['order_no']) ? sanitize_text_field($_POST['order_no']) : null;
		}
		return null;
	}

	/**
	 * @throws Exception
	 */
	private function handle_bank_approval($data): void {
		$this->log('Processing bank approval callback');

		if (!isset($data['order_number']) || !isset($data['approval_status'])) {
			$this->log('Invalid bank approval data');
			wp_send_json_error('Invalid bank approval data', 400);
			return;
		}

		$order_number = $data['order_number'];
		$this->log('Order No.: ' . $order_number);

		$order = wc_get_order($order_number);
		if (!$order) {
			$this->log('Invalid order number in bank approval: ' . $order_number);
			wp_send_json_error('Invalid order number', 404);
			return;
		}

		$approval_status = intval($data['approval_status']);
		$status_text = $this->get_approval_status_text($approval_status);
		$this->log('Approval Status: ' . $status_text . ' (' . $approval_status . ')');

		$note = sprintf(
			'Bank approval received. Status: %s. Mandate ID: %s, Reference: %s, Date: %s, Bank Code: %s',
			$status_text,
			$data['mandate_id'] ?? 'N/A',
			$data['mandate_reference_number'] ?? 'N/A',
			$data['approval_date'] ?? 'N/A',
			$data['payer_bank_code'] ?? 'N/A'
		);

		$order->add_order_note($note);
		$this->log('Added order note: ' . $note);

		// Handle subscription cancellation
		if (isset($data['application_type']) && $data['application_type'] === '03') {
			$this->log('Handling subscription cancellation approval');
			$this->handle_subscription_cancellation_approval($order, $data);
		} else {
			if ($approval_status === 5) { // Approved
				$this->log('Order approved, processing subscription activation');
				$this->process_subscription_activation($order, $data);
			} elseif ($approval_status === 6) { // Rejected
				$this->log('Order rejected, updating status to failed');
				$order->update_status('failed', 'Bank approval rejected.');
				$this->cancel_related_subscriptions($order);
			}
		}

		// Store information for future reference
		$order->update_meta_data('bayarcash_mandate_id', $data['mandate_id']);
		$order->update_meta_data('bayarcash_mandate_reference', $data['mandate_reference_number']);
		$order->update_meta_data('bayarcash_bank_code', $data['payer_bank_code']);
		$order->save();
		$this->log('Updated order meta data');

		$this->log('Bank approval processed successfully for Order No.: ' . $order_number);
	}

	private function process_subscription_activation($order, $data): void {
		$subscriptions = wcs_get_subscriptions_for_order($order);

		foreach ($subscriptions as $subscription) {
			try {
				// Ensure the subscription can be activated
				if (!apply_filters('woocommerce_can_subscription_be_updated_to', true, $subscription, 'active')) {
					throw new Exception('Subscription cannot be activated due to status restrictions.');
				}

				// Update subscription status
				$subscription->update_status('active', 'Subscription activated after bank approval.');

				// Set payment method
				$subscription->set_payment_method($this);

				// Set next payment date
				$next_payment = $subscription->calculate_date('next_payment');
				if ($next_payment) {
					$subscription->update_dates(array('next_payment' => $next_payment));
					$this->log('Updated next payment date for subscription: ' . $next_payment);
				}

				$subscription->save();

				// Trigger the activation action
				do_action('woocommerce_subscription_status_active', $subscription);

				$this->log('Subscription ' . $subscription->get_id() . ' activated successfully.');
			} catch (Exception $e) {
				$this->log('Error activating subscription ' . $subscription->get_id() . ': ' . $e->getMessage());
				$subscription->add_order_note('Failed to activate: ' . $e->getMessage());
			}
		}

		// Complete the parent order
		$order->update_status('completed', 'Order completed and subscription(s) activated after bank approval.');
		$order->payment_complete($data['mandate_reference_number']);

		// Trigger the subscriptions created action
		do_action('subscriptions_created_for_order', $order);
	}

	private function cancel_related_subscriptions($order): void {
		$subscriptions = wcs_get_subscriptions_for_order($order);

		foreach ($subscriptions as $subscription) {
			if ($subscription->get_status() !== 'cancelled') {
				$subscription->update_status('cancelled', 'Cancelled due to bank approval rejection.');
				do_action('woocommerce_subscription_status_cancelled', $subscription);
				$this->log('Subscription ' . $subscription->get_id() . ' cancelled due to bank approval rejection.');
			}
		}
	}

	private function get_approval_status_text($status): string {
		$statuses = [
			0 => 'New',
			1 => 'Waiting Approval',
			2 => 'Verification Failed',
			3 => 'Active',
			4 => 'Terminated',
			5 => 'Approved',
			6 => 'Rejected',
			7 => 'Cancelled',
			8 => 'Error',
		];

		return $statuses[$status] ?? 'Unknown';
	}

	private function handle_success_callback($order): void {
		$this->log('Processing Direct Debit success callback');

		$response_data = $_POST;

		if (empty($_POST['transaction_id']) || empty($_POST['order_number']) || empty($_POST['checksum'])) {
			wp_die('Invalid request', 'Error', ['response' => 403]);
		}

		$payment_method = $order->get_payment_method();
		$payment_data = $this->get_payment_settings($payment_method);
		$settings = $payment_data['settings'];

		$data_key = sanitize_text_field($_GET['bc-woo-success']);
		$order_no = sanitize_text_field($_POST['order_number']);

		if (!bayarcash_return_token_get($data_key, $order_no, 'directdebit')) {
			wp_die('Invalid token key: '.$data_key, 'Error', ['response' => 403]);
		}

		if ('completed' == $order->get_status()) {
			$this->log('Payment #'.$order_no.' is already completed. Redirecting to order received page.');
			exit($this->redirect($order->get_checkout_order_received_url()));
		}

		$this->initialize_bayarcash_sdk($settings);

		if (!$this->verify_transaction_callback($response_data, $settings, $order)) {
			wp_die('Data verification failed', 'Error', ['response' => 403]);
		}

		if ('3' === $_POST['status']) {
			$order->add_order_note('Bank verification successful. RM 1.00 deducted from customer Bank account.');

			$this->log('Order No. : '.$order_no);
			$this->log('Bank verification status: Success');
			$this->log('Response data: ' . print_r($response_data, true));

			// Update only the parent order status
			$order->update_status('on-hold', 'Direct Debit enrollment successful, awaiting first payment.');

			// If this is a subscription, add a note to the subscription
			$subscriptions = wcs_get_subscriptions_for_order($order);
			foreach ($subscriptions as $subscription) {
				$subscription->add_order_note('Parent order ' . $order->get_order_number() . ' has been put on-hold after successful Direct Debit enrollment.');
			}

			$receipt_url = $order->get_checkout_order_received_url().'&bc-woo-initial='.$order_no;
			exit($this->redirect($receipt_url));
		}
	}

	private function handle_failed_callback($order): void {
		$this->log('Processing Direct Debit failed callback');

		$data_key = sanitize_text_field($_GET['bc-woo-failed']);

		if (!($data_dec = bayarcash_return_token_get($data_key, 'bc-woo-failed', 'directdebit'))) {
			wp_die('Invalid token key: '.$data_key, 'Error', ['response' => 403]);
		}

		$order_no = $data_dec->data;

		if ('completed' == $order->get_status()) {
			$this->log('Payment #'.$order_no.' is already completed. Redirecting to order received page.');
			exit($this->redirect($order->get_checkout_order_received_url()));
		}

		$order->update_status('on-hold', 'Direct Debit enrollment successful, awaiting first payment.');
		$order->add_order_note('Bank verification failed');

		$receipt_url = $order->get_checkout_order_received_url();

		exit($this->redirect($receipt_url));
	}

	private function handle_return_callback($data): void {
		$this->log('Processing Direct Debit return callback');
		//$this->log('Callback Return data: ' . print_r($data, true));

		if (!isset($data['record_type'])) {
			$this->log('Invalid request: missing record_type');
			wp_send_json_error('Invalid request: missing record_type', 400);
			return;
		}

		$this->log('Processing record_type: ' . $data['record_type']);

		if ($data['record_type'] === 'transaction') {
			$this->handle_transaction_callback($data);
		} else {
			$this->log('Unsupported record_type: ' . $data['record_type']);
			wp_send_json_error('Unsupported record_type: ' . $data['record_type'], 400);
		}
	}

	private function handle_transaction_callback($data): void {
		$this->log('Processing transaction callback');
		//$this->log('Transaction data: ' . print_r($data, true));

		$required_fields = ['mandate_reference_number', 'status', 'amount', 'transaction_id', 'datetime', 'cycle'];
		foreach ($required_fields as $field) {
			if (!isset($data[$field])) {
				$this->log("Invalid transaction data: missing {$field}");
				wp_send_json_error("Invalid transaction data: missing {$field}", 400);
				return;
			}
		}

		$parent_order_id = $data['mandate_reference_number'];
		$this->log('Parent order ID: ' . $parent_order_id);

		$parent_order = wc_get_order($parent_order_id);
		if (!$parent_order) {
			$this->log('Invalid parent order number: ' . $parent_order_id);
			wp_send_json_error('Invalid parent order number', 404);
			return;
		}

		$subscriptions = wcs_get_subscriptions_for_order($parent_order, array('order_type' => 'parent'));
		if (empty($subscriptions)) {
			$this->log('No subscriptions found for parent order: ' . $parent_order_id);
			wp_send_json_error('No subscriptions found', 404);
			return;
		}

		foreach ($subscriptions as $subscription) {
			$this->log('Processing subscription: ' . $subscription->get_id());

			if ($subscription->get_status() !== 'active' && $subscription->get_status() !== 'pending') {
				$this->log('Subscription is not active or pending. Current status: ' . $subscription->get_status());
				continue;
			}

			try {
				if ($data['cycle'] == '1') {
					$this->process_first_payment($subscription, $parent_order, $data);
				} else {
					$renewal_order = $this->get_or_create_renewal_order($subscription, $data);
					$this->log('Using renewal order: ' . $renewal_order->get_id());

					if ($data['amount'] != $subscription->get_total()) {
						$this->log('Amount mismatch. Expected: ' . $subscription->get_total() . ', Received: ' . $data['amount']);
						// Decide how to handle this situation (e.g., partial payment, overpayment)
					}

					if ($data['status'] == '3') { // Successful transaction
						$this->log('Processing successful renewal');
						$this->process_successful_renewal($subscription, $renewal_order, $data);
					} else {
						$this->log('Processing failed renewal');
						$this->process_failed_renewal($subscription, $renewal_order, $data);
					}
				}
			} catch (Exception $e) {
				$this->log('Error processing transaction for subscription ' . $subscription->get_id() . ': ' . $e->getMessage());
				continue;
			}
		}

		$this->log('Transaction processing completed');
	}

	private function add_transaction_note($order, $data, $is_first_payment = false): void {
		$payment_status = ($data['status'] == '3') ? 'successful' : 'failed';
		$note_title = $is_first_payment ? "First payment" : "Renewal payment";

		$note = "{$note_title} {$payment_status}:\n"
		        . "- Date/Time: " . $data['datetime'] . "\n"
		        . "- Amount: " . wc_price($data['amount']) . "\n"
		        . "- Transaction ID: " . $data['transaction_id'] . "\n"
		        . "- Mandate ID: " . $data['mandate_id'] . "\n"
		        . "- Mandate Reference: " . $data['mandate_reference_number'] . "\n"
		        . "- Batch Number: " . $data['batch_number'] . "\n"
		        . "- Reference Number: " . $data['reference_number'] . "\n"
		        . "- Cycle: " . $data['cycle'] . "\n"
		        . "- Status: " . $data['status_description'];

		$order->add_order_note($note);
		//$this->log('Added note to order ' . $order->get_id() . ': ' . $note);
	}

	private function process_first_payment($subscription, $parent_order, $data): void {
		$this->log('Processing first payment for subscription: ' . $subscription->get_id());

		// Set parent order details
		$parent_order->set_payment_method($this);
		$parent_order->set_transaction_id($data['transaction_id']);
		$parent_order->set_date_paid(wc_string_to_datetime($data['datetime']));

		$this->add_transaction_note($parent_order, $data, true);

		if ($data['status'] == '3') { // Successful transaction
			// Complete the parent order and mark as 'completed'
			$parent_order->payment_complete();
			$parent_order->update_status('completed');
			$parent_order->save();
			$this->log('Parent order payment completed, marked as completed, and saved. Order ID: ' . $parent_order->get_id());

			// Activate the subscription
			$subscription->update_status('active');
			$subscription->save();

			WC_Subscriptions_Manager::process_subscription_payments_on_order($parent_order);

			$this->log('Successful first payment processed for subscription: ' . $subscription->get_id());
		} else {
			// Mark the parent order as failed
			$parent_order->update_status('failed');
			$parent_order->save();
			$this->log('Parent order marked as failed. Order ID: ' . $parent_order->get_id());

			// Update subscription
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($parent_order);

			$this->log('Failed first payment processed for subscription: ' . $subscription->get_id());
		}
	}

	/**
	 * @throws \WC_Data_Exception
	 */
	private function get_or_create_renewal_order($subscription, $data)
	{
		$parent_order_id = $subscription->get_parent_id();
		$this->log("Searching for renewal order. Subscription ID: {$subscription->get_id()}, Parent Order ID: {$parent_order_id}");

		$existing_orders = wc_get_orders(array(
			'type' => 'shop_order',
			'status' => array('pending'),
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_subscription_renewal',
					'value' => $parent_order_id,
					'compare' => '=',
				),
				array(
					'key' => '_subscription_renewal_subscription_id',
					'value' => $subscription->get_id(),
					'compare' => '=',
				),
			),
		));

		if (!empty($existing_orders)) {
			$renewal_order = reset($existing_orders);
			$this->log('Using existing renewal order: ' . $renewal_order->get_id());

			// Check if the order total matches the expected amount
			if (abs($renewal_order->get_total() - $data['amount']) > 0.01) {
				$this->log("Updating existing order total from {$renewal_order->get_total()} to {$data['amount']}");
				$renewal_order->set_total($data['amount']);
				$renewal_order->save();
			}
		} else {
			$renewal_order = wcs_create_renewal_order($subscription);
			$this->log('Created new renewal order: ' . $renewal_order->get_id());

			// Set the order total
			$renewal_order->set_total($data['amount']);
			$renewal_order->save();
		}

		return $renewal_order;
	}

	private function process_successful_renewal($subscription, $renewal_order, $data): void {
		$this->log('Processing successful renewal for subscription: ' . $subscription->get_id());

		// Set renewal order details
		$renewal_order->set_payment_method($this);
		$renewal_order->set_transaction_id($data['transaction_id']);
		$renewal_order->set_date_paid(wc_string_to_datetime($data['datetime']));

		$this->add_transaction_note($renewal_order, $data);

		// Complete the renewal order and mark as 'completed'
		$renewal_order->payment_complete();
		$renewal_order->update_status('completed');
		$renewal_order->save();
		$this->log('Renewal order payment completed, marked as completed, and saved. Order ID: ' . $renewal_order->get_id());

		// Use WC_Subscriptions_Manager to process the payment
		WC_Subscriptions_Manager::process_subscription_payments_on_order($renewal_order);

		$this->log('Successful renewal processed for subscription: ' . $subscription->get_id());
	}

	private function process_failed_renewal($subscription, $renewal_order, $data): void {
		$this->log('Processing failed renewal for subscription: ' . $subscription->get_id());

		// Set renewal order details
		$renewal_order->set_payment_method($this);
		$renewal_order->set_transaction_id($data['transaction_id']);

		$this->add_transaction_note($renewal_order, $data);

		// Use WC_Subscriptions_Manager to process the failed payment
		WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($renewal_order);

		$this->log('Failed renewal processed for subscription: ' . $subscription->get_id());
	}

	public function cancel_subscriptions_for_order($order): void {
		\WC_Subscriptions_Manager::cancel_subscriptions_for_order($order);
	}

	private function has_subscriptions(): bool {
		return class_exists('WC_Subscriptions', false) && class_exists('WC_Subscriptions_Order', false);
	}

	private function is_subscription_product($product): bool {
		return class_exists('WC_Subscriptions_Product', false) &&
		       method_exists('WC_Subscriptions_Product', 'is_subscription') &&
		       \WC_Subscriptions_Product::is_subscription($product);
	}

	private function redirect($redirect)
	{
		if (!headers_sent()) {
			wp_redirect($redirect);
			exit;
		}

		$html = "<script>window.location.replace('".$redirect."');</script>";
		$html .= '<noscript><meta http-equiv="refresh" content="1; url='.$redirect.'">Redirecting..</noscript>';

		echo wp_kses(
			$html,
			[
				'script'   => [],
				'noscript' => [],
				'meta'     => [
					'http-equiv' => [],
					'content'    => [],
				],
			]
		);
		exit;
	}

	public function cancel_subscription($subscription): void {
		$this->log('Starting subscription cancellation process for subscription ID: ' . $subscription->get_id());

		try {
			$settings = $this->get_payment_settings($this->id);
			$bearer_token = $settings['settings']['bearer_token'] ?? '';

			$this->bayarcashSdk = new Bayarcash($bearer_token);

			if (isset($settings['settings']['sandbox_mode']) && $settings['settings']['sandbox_mode'] === 'yes') {
				$this->bayarcashSdk->useSandbox();
				$this->log('Sandbox mode enabled for cancellation');
			}

			// Get the parent order
			$parent_order_id = $subscription->get_parent_id();
			$parent_order = wc_get_order($parent_order_id);

			if (!$parent_order) {
				throw new Exception( 'Parent order not found for subscription ID: ' . $subscription->get_id());
			}

			// Get the mandate ID from the parent order's metadata
			$mandate_id = $parent_order->get_meta('bayarcash_mandate_id');

			if (empty($mandate_id)) {
				throw new Exception( 'Mandate ID not found in parent order for subscription ID: ' . $subscription->get_id());
			}

			$this->log('Retrieved Mandate ID from parent order: ' . $mandate_id);

			$response = $this->bayarcashSdk->createFpxDirectDebitTermination($mandate_id, ['application_reason' => 'Subscription Cancellation']);

			if (empty($response->url)) {
				throw new Exception('Cancellation Failed: Empty URL returned');
			}

			$this->log('Cancellation request successful. Redirect URL: ' . $response->url);

			// Store cancellation details
			$cancellation_data = [
				'order_number' => $parent_order_id,
				'payer_id_type' => $parent_order->get_meta('bayarcash_payer_id_type'),
				'payer_id' => $parent_order->get_meta('bayarcash_payer_id'),
				'payer_name' => $parent_order->get_billing_first_name() . ' ' . $parent_order->get_billing_last_name(),
				'payer_email' => $parent_order->get_billing_email(),
				'payer_telephone_number' => $parent_order->get_billing_phone(),
				'amount' => $subscription->get_total(),
				'application_type' => 'TERMINATION',
			];

			// Store cancellation data in subscription meta
			$subscription->update_meta_data('bayarcash_cancellation_data', $cancellation_data);
			$subscription->save();

			// Determine if the current user is an admin
			$is_admin = current_user_can('manage_options');

			// Check if we're on the subscriptions or view-subscription page
			$current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
			$is_subscriptions_page = ( str_contains( $current_url, '/my-account/subscriptions' ) );
			$is_view_subscription_page = ( str_contains( $current_url, '/my-account/view-subscription' ) );

			// Treat admin as normal user if on subscriptions or view-subscription page
			$treat_as_user = !$is_admin || $is_subscriptions_page || $is_view_subscription_page;

			// Handle AJAX requests or treat as AJAX for specific pages
			if (wp_doing_ajax() || $treat_as_user) {
				wp_send_json_success(['redirect_url' => $response->url]);
			}
			// Handle non-AJAX requests for admin
			else {
				// For admin users, set a transient to show an admin notice
				set_transient('bayarcash_admin_notice', 'Cancellation request sent successfully.', 45);
				wp_safe_redirect(wp_get_referer() ?: admin_url());
				exit;
			}

		} catch ( Exception $e) {
			$this->log('Cancellation Error: ' . $e->getMessage());

			$error_message = 'Unable to process cancellation: ' . $e->getMessage();

			if (wp_doing_ajax() || $treat_as_user) {
				wp_send_json_error(['message' => $error_message]);
			} else {
				// For admin users, set a transient to show an admin notice
				set_transient('bayarcash_admin_notice', $error_message, 45);
				wp_safe_redirect(wp_get_referer() ?: admin_url());
				exit;
			}
		}
	}

	public function bayarcash_admin_notices(): void {
		$notice = get_transient('bayarcash_admin_notice');
		if ($notice) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($notice) . '</p></div>';
			delete_transient('bayarcash_admin_notice');
		}
	}
	/**
	 * @throws Exception
	 */
	private function handle_subscription_cancellation_approval($order, $data): void {
		$this->log('Handling subscription cancellation approval for Order No.: ' . $order->get_id());

		// Find the related subscription
		$subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'any'));

		if (empty($subscriptions)) {
			$this->log('No subscriptions found for Order No.: ' . $order->get_id());
			return;
		}

		foreach ($subscriptions as $subscription) {
			$this->log('Processing cancellation for Subscription ID: ' . $subscription->get_id());

			// Check if the subscription is already cancelled
			if ($subscription->get_status() === 'cancelled') {
				$this->log('Subscription ' . $subscription->get_id() . ' is already cancelled.');
				continue;
			}

			// Prepare the cancellation note
			$note = sprintf(
				'Subscription cancelled. Bank approval received. Mandate ID: %s, Reference: %s, Date: %s',
				$data['mandate_id'] ?? 'N/A',
				$data['mandate_reference_number'] ?? 'N/A',
				$data['approval_date'] ?? 'N/A'
			);

			// Add the note to the subscription
			$subscription->add_order_note($note);

			// Update subscription status
			$subscription->update_status('cancelled', 'Subscription cancelled due to bank approval of cancellation request.');

			// Remove scheduled actions for this subscription
			WC_Subscriptions_Manager::remove_all_subscription_scheduled_actions($subscription->get_id());

			// Trigger the cancellation action
			do_action('woocommerce_subscription_status_cancelled', $subscription);

			// Force save the subscription to ensure notes are persisted
			$subscription->save();

			$this->log('Subscription ' . $subscription->get_id() . ' cancelled successfully.');
		}

		// Update the parent order status if necessary
		if ($order->get_status() !== 'cancelled') {
			$order->update_status('cancelled', 'Order cancelled due to subscription cancellation approval.');
			$this->log('Parent order ' . $order->get_id() . ' status updated to cancelled.');
		}

		$this->log('Subscription cancellation approval process completed for Order No.: ' . $order->get_id());
	}

}