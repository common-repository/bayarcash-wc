<?php
namespace Bayarcash\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

\defined('ABSPATH') || exit;

class CronEvent
{
	private $pt;
	private array $supported_payment_methods = ['bayarcash-wc', 'duitnow-wc', 'linecredit-wc'];

	public function __construct($pt)
	{
		$this->pt = $pt;
	}

	public function register(): void {
		add_filter(
			'cron_schedules',
			function ($schedules) {
				$schedules['bayarcash_wc_schedule'] = [
					'interval' => 5 * MINUTE_IN_SECONDS,
					'display'  => esc_html__('Every 5 Minutes', 'bayarcash-wc'),
				];
				return $schedules;
			},
			\PHP_INT_MAX
		);
		add_action('bayarcash_wc_checkpayment', [$this, 'check_payment']);
		if (!wp_next_scheduled('bayarcash_wc_checkpayment')) {
			wp_schedule_event(time(), 'bayarcash_wc_schedule', 'bayarcash_wc_checkpayment');
		}
	}

	public function unregister(): void {
		wp_clear_scheduled_hook('bayarcash_wc_checkpayment');
	}

	public function check_payment(): void {
		if (!$this->pt->is_woocommerce_activated()) {
			return;
		}

		foreach ($this->supported_payment_methods as $payment_method) {
			$option_name = 'woocommerce_' . $payment_method . '_settings';
			$serialized_settings = get_option($option_name);

			if (empty($serialized_settings)) {
				bayarcash_debug_log("Settings not found for $payment_method. Skipping payment check.");
				continue;
			}

			$settings = maybe_unserialize($serialized_settings);

			if (!is_array($settings)) {
				bayarcash_debug_log("Invalid settings format for $payment_method. Skipping payment check.");
				continue;
			}

			$bearer_token = $settings['bearer_token'] ?? '';
			if (empty($bearer_token)) {
				bayarcash_debug_log("Bearer token is not set for $payment_method. Skipping payment check.");
				continue;
			}

			$sandbox_mode = isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes';
			if ($sandbox_mode) {
				bayarcash_debug_log("Sandbox mode is enabled for $payment_method.");
			}

			$order_query = [
				'status'         => ['pending'],
				'payment_method' => $payment_method,
				'limit'          => 30,
			];

			if ($this->is_hpos_enabled()) {
				$orders = wc_get_orders($order_query);
			} else {
				$orders = wc_get_orders($order_query);
			}

			if (empty($orders)) {
				bayarcash_debug_log("No pending orders found for payment method: $payment_method");
				continue;
			}

			foreach ($orders as $order) {
				$transaction_id = $this->get_order_meta($order, 'bayarcash_wc_transaction_id');
				if (empty($transaction_id)) {
					bayarcash_debug_log(sprintf('No transaction ID found for order %s with payment method %s', $order->get_id(), $payment_method));
					continue;
				}
				bayarcash_debug_log(sprintf('Requerying transaction ID %s for order %s with payment method %s', $transaction_id, $order->get_id(), $payment_method));

				try {
					$data_request         = new DataRequest();
					$transaction_response = $data_request->bayarcash_requery($transaction_id, $bearer_token, $sandbox_mode);
					bayarcash_debug_log('Transaction requery response: ' . print_r($transaction_response, true));
					bayarcash_data_store()->update_payment_fpx($transaction_response);
					bayarcash_debug_log('Payment status updated for transaction ID: ' . $transaction_id);
				} catch (\Exception $e) {
					bayarcash_debug_log('Error requerying transaction: ' . $e->getMessage());
				}
			}
		}
	}

	private function is_hpos_enabled(): bool {
		return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private function get_order_meta($order, $key)
	{
		if ($this->is_hpos_enabled()) {
			return $order->get_meta($key);
		} else {
			return get_post_meta($order->get_id(), $key, true);
		}
	}
}