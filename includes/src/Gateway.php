<?php
/**
 * Bayarcash WooCommerce.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

namespace Bayarcash\WooCommerce;

use JetBrains\PhpStorm\NoReturn;
use WC_Data_Exception;
use Webimpian\BayarcashSdk\Bayarcash;

abstract class Gateway extends \WC_Payment_Gateway
{
	protected $bayarcashSdk;
	protected $payment_method;
	protected $slug;
	protected $url;

	protected array $payment_methods_config = [
		'bayarcash-wc' => ['log_title' => 'bayarcash_fpx', 'gateway_number' => 1],
		'duitnow-wc' => ['log_title' => 'bayarcash_duitnow', 'gateway_number' => 5],
		'linecredit-wc' => ['log_title' => 'bayarcash_linecredit', 'gateway_number' => 4],
		'directdebit-wc' => ['log_title' => 'bayarcash_directdebit', 'gateway_number' => 3],
		'duitnowqr-wc' => ['log_title' => 'bayarcash_duitnowqr', 'gateway_number' => 6],
		'duitnowshopee-wc' => ['log_title' => 'bayarcash_duitnowshopee', 'gateway_number' => 7],
		'duitnowboost-wc' => ['log_title' => 'bayarcash_duitnowboost', 'gateway_number' => 8],
		'duitnowqris-wc' => ['log_title' => 'bayarcash_duitnowqris', 'gateway_number' => 9],
		'duitnowqriswallet-wc' => ['log_title' => 'bayarcash_duitnowqriswallet', 'gateway_number' => 10],
	];

	public function __construct($payment_method)
	{
		$this->payment_method = $payment_method;
		$this->id = $payment_method . '-wc';
		$this->supports = ['products'];
		$this->register_setting();
		$this->register_hooks();
	}

	protected function register_setting(): void {
		$this->slug = BAYARCASH_WC['SLUG'];
		$this->url = BAYARCASH_WC['URL'];
		$this->has_fields = false;

		$titles = $this->get_payment_titles();
		$descriptions = $this->get_payment_descriptions();

		$this->title = $this->get_option('title', $titles['title']);
		$this->description = $this->get_option('description', $descriptions['description']);
		$this->method_title = apply_filters('woocommerce_' . $this->id . '_method_title', $titles['method_title']);
		$this->method_description = apply_filters('woocommerce_' . $this->id . '_method_description', $descriptions['method_description']);

		$this->set_icon();

		if (is_admin()) {
			$this->has_fields = true;
			$this->init_form_fields();
		}
	}

	protected function register_hooks(): void {
		add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
		add_action('woocommerce_api_bayarcash_payment', [$this, 'process_bayarcash']);
		add_action('woocommerce_api_bayarcash_callback', [$this, 'process_callback']);
		add_filter('woocommerce_order_button_text', [$this, 'custom_order_button_text'], 10, 1);
	}

	abstract protected function get_payment_titles();
	abstract protected function get_payment_descriptions();
	abstract protected function set_icon();

	public function init_form_fields(): void {
		$titles = $this->get_payment_titles();
		$this->form_fields = AdminFormFields::get_form_fields($this->payment_method, $titles);
	}

	public function custom_order_button_text($order_button_text)
	{
		$custom_text = AdminFormFields::custom_order_button_text($this->id, $this->get_option('place_order_text'));
		return $custom_text !== null ? $custom_text : $order_button_text;
	}

	public function payment_scripts(): void {
		if (\function_exists('is_checkout') && !is_checkout()) {
			return;
		}

		$version = bayarcash_version().'h'.date('Ymdh');
		wp_enqueue_script('checkout-'.$this->slug, $this->url.'includes/admin/bayarcash-wc-checkout.js', ['jquery'], $version, true);
	}

	public function process_admin_options(): void {
		parent::process_admin_options();

		$portal_key = bayarcash_strip_whitespace($this->get_option('portal_key'));
		$bearer_token = bayarcash_strip_whitespace($this->get_option('bearer_token'));
		$api_secret_key = bayarcash_strip_whitespace($this->get_option('api_secret_key'));

		$this->update_option('portal_key', $portal_key);
		$this->update_option('bearer_token', $bearer_token);
		$this->update_option('api_secret_key', $api_secret_key);

		if (empty($portal_key) || empty($bearer_token) || empty($api_secret_key)) {
			$this->update_option('enabled', 'no');

			if (class_exists('WC_Admin_Settings', false)) {
				if (empty($portal_key)) {
					\WC_Admin_Settings::add_error(esc_html__('Please enter portal key', 'bayarcash-wc'));
				}
				if (empty($bearer_token)) {
					\WC_Admin_Settings::add_error(esc_html__('Please enter Personal Access Token (PAT)', 'bayarcash-wc'));
				}
				if (empty($api_secret_key)) {
					\WC_Admin_Settings::add_error(esc_html__('Please enter API secret key', 'bayarcash-wc'));
				}
			}
		}
	}

	public function process_payment($order_id): array {
		$order = wc_get_order($order_id);

		if ($this->order_contains_subscription($order)) {
			$direct_debit_gateway = new DirectDebitGateway();
			return $direct_debit_gateway->process_payment($order_id);
		}

		$order_no = $order->get_id();
		$errors = '';

		$payment_method = $order->get_payment_method();
		$payment_data = $this->get_payment_settings($payment_method);
		$settings = $payment_data['settings'];

		$portal_key = $settings['portal_key'] ?? '';
		$bearer_token = $settings['bearer_token'] ?? '';

		if (empty($bearer_token)) {
			$errors .= '<li>'.esc_html__('Personal Access Token (PAT) is empty', 'bayarcash-wc').'</li>';
		}

		if (empty($portal_key)) {
			$errors .= '<li>'.esc_html__('Portal key is empty', 'bayarcash-wc').'</li>';
		}

		if (!empty($errors)) {
			$error_log = str_replace('</li>', '', $errors);
			$error_log = str_replace('<li>', "\n", $error_log);
			$this->log('Error in settings:'.$error_log);

			return [
				'result' => 'failure',
				'messages' => $errors,
			];
		}

		return [
			'result' => 'success',
			'redirect' => get_site_url().'/?wc-api=bayarcash_payment&bc-woo-return='.bayarcash_return_token_set($order_no, 'bayarcash_payment', 'process_payment'),
		];
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function process_bayarcash()
	{
		if (empty($_GET['bc-woo-return'])) {
			wp_die('Invalid request', 'Error', ['response' => 403]);
		}

		$data_key = sanitize_text_field($_GET['bc-woo-return']);
		$data_dec = bayarcash_return_token_get($data_key, 'bayarcash_payment', 'process_payment');

		if (!$data_dec) {
			wp_die("Invalid token key: $data_key", 'Error', ['response' => 403]);
		}

		$data_parts = explode(',', $data_dec->data);
		$order_id = $data_parts[0];

		$order = wc_get_order($order_id);

		if (!$order) {
			$this->log("Order not found for ID: {$order_id}");
			wp_die("Invalid Order Number", 'Error', ['response' => 403]);
		}

		if ('completed' === $order->get_status()) {
			wp_die("No further action needed as payment #{$order->get_id()} is already completed", 'Error', ['response' => 403]);
		}

		$payment_method = $order->get_payment_method();
		$this->log("Starting Bayarcash process for order {$order->get_id()} using {$payment_method}", $order);

		if ($payment_method === 'directdebit-wc') {
			$this->log("Redirecting to DirectDebitGateway for processing", $order);
			$directDebitGateway = new DirectDebitGateway();
			$directDebitGateway->process_bayarcash();
			return; // Exit the current method after processing
		}

		$payment_data   = $this->get_payment_settings($payment_method);
		$settings       = $payment_data['settings'];
		$order_no       = $order->get_id();

		$portal_key     = $settings['portal_key'] ?? '';
		$apiSecretKey   = $settings['api_secret_key'] ?? '';
		$payment_gateway = $payment_data['gateway_number'];
		$bearer_token   = $settings['bearer_token'] ?? '';

		$this->log('Bearer Token: ' . (empty($bearer_token) ? 'Not set' : 'Set'), $order);
		$this->bayarcashSdk = new Bayarcash($bearer_token);

		if (isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes') {
			$this->bayarcashSdk->useSandbox();
			$this->log('Sandbox mode enabled', $order);
		}

		$order_data  = $order->get_data();
		$billing = $order_data['billing'];
		$buyer_name = $billing['first_name'] . ' ' . $billing['last_name'];
		$buyer_email = $this->get_buyer_email_with_fallback($order, $settings);
		$buyer_phone = $this->sanitize_phone_number($billing['phone']);
		$return_url  = get_site_url() . '/?wc-api=bayarcash_callback';

		$data = [
			'portal_key'             => $portal_key,
			'payment_channel'        => $payment_gateway,
			'order_number'           => $order_no,
			'amount'                 => $order->get_total(),
			'payer_name'             => $this->sanitize_buyer_name($buyer_name),
			'payer_email'            => $buyer_email,
			'payer_telephone_number' => $buyer_phone,
			'description'            => "Payment for Order $order_no",
			'return_url'             => $return_url,
		];

		$this->log('Payment Gateway: ' . $payment_gateway, $order);
		$this->log('Data: ' . print_r($data, true), $order);

		if (empty($apiSecretKey)) {
			$this->log('API Secret Key is missing', $order);
			wp_die('Configuration error: API Secret Key is missing', 'Error', ['response' => 500]);
		}

		$data['checksum'] = $this->bayarcashSdk->createPaymentIntenChecksumValue($apiSecretKey, $data);
		$this->log('Checksum created : ' . $data['checksum'] , $order);

		try {
			$response = $this->bayarcashSdk->createPaymentIntent($data);

			if (empty($response->url)) {
				throw new \Exception('Payment Failed: Empty URL returned');
			}

			$this->log('Redirecting to payment URL: ' . $response->url, $order);
			wp_redirect($response->url);
			exit;
		} catch (\Webimpian\BayarcashSdk\Exceptions\ValidationException $e) {
			$this->log('Validation error: ' . $e->getMessage(), $order);
			$this->log('Validation errors: ' . print_r($e->errors(), true), $order);
			wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
			wp_redirect(wc_get_checkout_url());
			exit;
		} catch (\Exception $e) {
			$this->log('Payment error: ' . $e->getMessage(), $order);
			wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
			wp_redirect(wc_get_checkout_url());
			exit;
		}
	}

	private function sanitize_buyer_name($name): string {
		$name = preg_replace('/[^a-zA-Z0-9\s-]/', '', $name);
		return trim($name) ?: '';
	}

	/**
	 * @param string $phone
	 * @return string
	 */
	private function sanitize_phone_number($phone): string
	{
		$phone = preg_replace('/^\+?6?0?|\D/', '', $phone);
		if (preg_match('/^1\d{8,12}$/', $phone)) {
			return '0' . $phone;
		}
		return '0123456789';
	}

	/**
	 * @throws WC_Data_Exception
	 */
	protected function get_buyer_email_with_fallback(\WC_Order $order, array $settings): string
	{
		$buyer_email = $order->get_billing_email();

		if (empty($buyer_email)) {
			$fallback_email = $settings['email_fallback'] ?? '';
			if (!empty($fallback_email)) {
				$order->set_billing_email($fallback_email);
				$order->save();
				$buyer_email = $fallback_email;

				$this->log("Using fallback email for order {$order->get_id()}: $fallback_email", $order);
			} else {
				$this->log("No valid email found for order {$order->get_id()}", $order);
			}
		}

		return $buyer_email;
	}

	protected function order_contains_subscription($order): bool {
		if (!function_exists('wcs_order_contains_subscription')) {
			return false;
		}
		return wcs_order_contains_subscription($order);
	}

	public function process_callback(): void {
		$response_data = $_POST;

		if (empty($response_data['order_number'])) {
			return;
		}

		$order_number = sanitize_text_field($response_data['order_number']);
		$order = wc_get_order($order_number);

		if (!$order) {
			return;
		}

		$payment_method = $order->get_payment_method();
		$this->log("Starting process_callback for order {$order->get_id()} using {$payment_method}", $order);

		if ($payment_method === 'directdebit-wc') {
			$this->log("Redirecting to DirectDebitGateway for processing", $order);
			$directdebit_gateway = new DirectDebitGateway();
			$directdebit_gateway->process_callback($order);
		} else {
			$this->log("Processing pre-transaction data", $order);
			$this->handle_pre_transaction_data();

			$this->log("Processing FPX callback", $order);
			$this->callback_fpx();
		}

		$this->log("Finished process_callback for order {$order->get_id()}", $order);
	}

	private function handle_pre_transaction_data(): void {
		$response_data = $_POST;

		if (!isset($response_data['record_type']) || $response_data['record_type'] !== 'pre_transaction') {
			return;
		}

		if (!$this->validate_pre_transaction_data($response_data)) {
			return;
		}

		$order_no = $response_data['order_number'];
		$order = wc_get_order($order_no);

		if (!$order) {
			return;
		}

		$transaction_exchange_no = $response_data['transaction_id'];
		$exchange_reference_number = $response_data['exchange_reference_number'];

		$this->log_transaction_details($order, $transaction_exchange_no, $exchange_reference_number);

		if (!$this->check_exchange_no_can_be_add($order, $transaction_exchange_no)) {
			$this->log("Can't proceed to store transaction id in post meta for current order status.", $order);
			return;
		}

		$order->update_status('pending');
		$this->store_post_meta_transaction_exchange_no($order_no, $transaction_exchange_no);
		$this->log("Order status set to 'pending' and Transaction Exchange No. stored in wp_post meta.", $order);
	}

	private function validate_pre_transaction_data($data): bool {
		return !empty($data['transaction_id']) && !empty($data['order_number']) && !empty($data['exchange_reference_number']);
	}

	private function log_transaction_details($order, $transaction_exchange_no, $exchange_reference_number): void {
		$this->log("Pre-transaction data received for order {$order->get_id()}", $order);
		$this->log("Transaction ID: $transaction_exchange_no", $order);
		$this->log("Exchange Reference Number: $exchange_reference_number", $order);
	}

	private function callback_fpx(): void {
		$response_data = $_POST;

		if (!$this->is_response_hit_plugin_callback_url()) {
			return;
		}

		if (empty($response_data['checksum'])) {
			return;
		}

		if (!$this->validate_callback_data($response_data)) {
			wp_die('Invalid request', 'Error', ['response' => 403]);
		}

		$order_no = $response_data['order_number'];
		$order = wc_get_order($order_no);

		if (!$order) {
			wp_die('Invalid Order Number', 'Error', ['response' => 403]);
		}

		$payment_method = $order->get_payment_method();
		$payment_data = $this->get_payment_settings($payment_method);
		$settings = $payment_data['settings'];

		$this->initialize_bayarcash_sdk($settings);

		if (!$this->verify_transaction_callback($response_data, $settings, $order)) {
			wp_die('Data verification failed', 'Error', ['response' => 403]);
		}

		$status = $response_data['status'];
		$this->log("FPX callback received. Status: $status", $order);

		$this->process_transaction_response($response_data, $settings, $order);

		$receipt_url = $order->get_checkout_order_received_url();
		$this->redirect($receipt_url);
	}

	private function validate_callback_data($data): bool {
		return !empty($data['order_number']) && isset($data['transaction_id']) && isset($data['exchange_reference_number']);
	}

	protected function initialize_bayarcash_sdk($settings): void {
		$bearer_token = $settings['bearer_token'] ?? '';
		$this->bayarcashSdk = new Bayarcash($bearer_token);
		if (isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes') {
			$this->bayarcashSdk->useSandbox();
		}
	}

	protected function verify_transaction_callback($response_data, $settings, $order): bool {
		$api_secret_key = $settings['api_secret_key'] ?? '';
		$validResponse = $this->bayarcashSdk->verifyTransactionCallbackData($response_data, $api_secret_key);

		if (!$validResponse) {
			$this->log('Invalid checksum for order', $order);
			return false;
		}

		return true;
	}

	private function process_transaction_response($response_data, $settings, $order): void {
		try {
			$data_request         = new DataRequest();
			$transaction_response = $data_request->bayarcash_requery(
				$response_data['transaction_id'],
				$settings['bearer_token'] ?? '',
				isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes'
			);

			$data_store = new DataStore();
			$data_store->update_payment_fpx($transaction_response);

			$this->log('Payment status updated', $order);
		} catch (\Exception $e) {
			$this->log('Error querying transaction: ' . $e->getMessage(), $order);
		}
	}

	private function redirect($redirect): void {
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

	protected function get_payment_settings($payment_method): array {
		if (!isset($this->payment_methods_config[$payment_method])) {
			return [
				'settings' => [],
				'gateway_number' => null
			];
		}

		return [
			'settings' => get_option("woocommerce_{$payment_method}_settings", []),
			'gateway_number' => $this->payment_methods_config[$payment_method]['gateway_number']
		];
	}

	private function check_exchange_no_can_be_add($order, $transaction_exchange_no): bool {
		$is_abnormal_exchange_no = $this->check_abnormal_exchange_no($order, $transaction_exchange_no);
		$is_order_already_paid   = $this->check_order_already_paid($order);

		if ($is_abnormal_exchange_no || $is_order_already_paid) {
			return false;
		}

		return true;
	}

	private function store_post_meta_transaction_exchange_no(string $order_no, string $transaction_exchange_no): void {
		if ($this->is_hpos_enabled()) {
			// Use HPOS approach
			$order = wc_get_order($order_no);
			if ($order) {
				$order->update_meta_data('bayarcash_wc_transaction_id', $transaction_exchange_no);
				$order->save();
			}
		} else {
			// Use traditional post meta approach
			add_post_meta($order_no, 'bayarcash_wc_transaction_id', $transaction_exchange_no);
		}
	}

	private function is_hpos_enabled(): bool {
		return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private function check_order_already_paid($order): bool {
		$order_status  = strtolower($order->get_status());
		$order_is_paid = $this->get_all_order_status_paid_details();

		return \in_array($order_status, $order_is_paid);
	}

	private function check_abnormal_exchange_no($order, $transaction_exchange_no): bool {
		$is_pending = 'pending' === strtolower($order->get_status());

		if (!$is_pending) {
			return false;
		}

		$current_time = new \WC_DateTime();
		$current_time->setTimeZone(new \DateTimeZone('Asia/Kuala_Lumpur'));

		return $order->get_date_created()->format('Y-m-d H:i:s') === $current_time->format('Y-m-d H:i:s');
	}

	private function is_wc_order_status_manager_plugin_active(): bool {
		$plugin = 'woocommerce-order-status-manager/woocommerce-order-status-manager.php';

		return \in_array($plugin, (array) get_option('active_plugins', []));
	}

	private function get_all_order_status_paid_details()
	{
		if (!$this->is_wc_order_status_manager_plugin_active()) {
			return ['processing', 'completed'];
		}

		$this->log('WC Order Status Manager Plugin is activated, use _is_paid wc_order_status definition from the plugin');

		global $wpdb;

		$site_prefix    = $wpdb->prefix;
		$order_statuses = $wpdb->get_results(
			"
            SELECT {$site_prefix}posts.post_title,
                   {$site_prefix}posts.post_name,
                   {$site_prefix}postmeta.meta_key,
                   {$site_prefix}postmeta.meta_value
            FROM   {$site_prefix}posts
                   INNER JOIN {$site_prefix}postmeta
                       ON {$site_prefix}posts.id = {$site_prefix}postmeta.post_id
            WHERE  {$site_prefix}posts.post_type = 'wc_order_status'
                   AND {$site_prefix}postmeta.meta_key = '_is_paid'
                   AND {$site_prefix}postmeta.meta_value = 'yes';
            "
		);

		return array_map(
			function ($order_status) {
				return $order_status->post_name;
			},
			$order_statuses
		);
	}

	private function is_response_hit_plugin_callback_url(): bool {
		$request_uri = esc_url_raw($_SERVER['REQUEST_URI']);

		return str_contains( $request_uri, 'wc-api=bayarcash_callback' );
	}

	public function log($messages, $order = null, $is_force = false): void {
		$payment_method = $this->id;
		if ($order instanceof \WC_Order) {
			$payment_method = $order->get_payment_method();
		}

		$payment_data = $this->get_payment_settings($payment_method);
		$settings = $payment_data['settings'];

		$is_debug = isset( $settings['debug_mode'] ) && $settings['debug_mode'] === 'yes';

		if (!$is_debug && !$is_force) {
			return;
		}

		if (!class_exists('WC_Logger')) {
			return;
		}

		static $loggers = [];

		if (!isset($loggers[$payment_method])) {
			$loggers[$payment_method] = new \WC_Logger();
		}

		$log_title = $this->get_log_title($payment_method);

		$messages = "[" . date('Y-m-d H:i:s') . "] " . trim($messages) . "\n";
		$loggers[$payment_method]->add($log_title, $messages);
	}

	private function get_log_title($payment_method): string {
		return $this->payment_methods_config[$payment_method]['log_title'] ?? 'bayarcash_unknown';
	}

	public function needs_setup(): bool {
		return empty($this->get_option('portal_key')) || empty($this->get_option('bearer_token'));
	}
}