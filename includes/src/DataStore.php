<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

namespace Bayarcash\WooCommerce;

class DataStore
{
	public function update_payment_fpx($transaction_data)
	{
		//bayarcash_debug_log('Updating payment for order: ' . print_r($transaction_data, true));

		// Check if the data is nested inside a 'data' key
		if (isset($transaction_data['data'])) {
			$transaction_data = $transaction_data['data'];
		}

		// Update these checks to use array access and the correct keys
		if (!isset($transaction_data['order_number']) || !isset($transaction_data['status'])) {
			bayarcash_debug_log('Missing order_number or status');
			return;
		}

		$order = $this->get_order($transaction_data['order_number']);
		if (!$order) {
			bayarcash_debug_log('Order not found: ' . $transaction_data['order_number']);
			return;
		}

		bayarcash_debug_log('Order status before update: ' . $order->get_status());

		if (bayarcash_has_fpx_transaction_status($transaction_data['status'], 'successful')) {
			bayarcash_debug_log('Transaction successful, updating order');

			$this->delete_order_meta($order, 'bayarcash_wc_transaction_id');

			$message = 'Bayarcash Payment successful<br>';
			$order_note = $this->get_order_note($message, $transaction_data);

			if (!$this->check_duplicate_order_note($order, $order_note)) {
				$this->add_order_note($order, $order_note);
				bayarcash_debug_log('Order note added');
			} else {
				bayarcash_debug_log('Duplicate order note, not added');
			}

			$order->payment_complete($transaction_data['id']);
			WC()->cart->empty_cart();
			bayarcash_debug_log('Order marked as complete');
		}
		elseif (!bayarcash_has_fpx_transaction_status($transaction_data['status'], 'successful') && $order->needs_payment()) {
			if (bayarcash_has_fpx_transaction_status($transaction_data['status'], 'pending')) {
				bayarcash_debug_log('Transaction pending, keeping order status as pending');
			}
			elseif (bayarcash_has_fpx_transaction_status($transaction_data['status'], 'new')) {
				bayarcash_debug_log('Transaction is new, not changing order status');
				// Do nothing to keep the current status
			}
			else {
				bayarcash_debug_log('Transaction failed, updating order');

				$this->delete_order_meta($order, 'bayarcash_wc_transaction_id');

				$message = 'Bayarcash Payment failed<br>';
				$order_note = $this->get_order_note($message, $transaction_data);

				if (!$this->check_duplicate_order_note($order, $order_note)) {
					$this->add_order_note($order, $order_note);
					bayarcash_debug_log('Order note added');
				} else {
					bayarcash_debug_log('Duplicate order note, not added');
				}

				$order->update_status('failed');
				bayarcash_debug_log('Order marked as failed');
			}
		}

		bayarcash_debug_log('Order status after update: ' . $order->get_status());
	}

	private function get_order_note(string $message, $transaction_data): string
	{
		$transaction_url = 'https://console.bayar.cash/transactions?ref_no=' . $transaction_data['exchange_reference_number'];
		$transaction_date = date('j F Y', strtotime($transaction_data['datetime']));
		$transaction_time = date('h:i:s A', strtotime($transaction_data['datetime']));

		$status_description = $this->get_status_description($transaction_data['status']);

		$note = $message . "\n";
		$note .= 'Order Number: ' . $transaction_data['order_number'] . '<br>';
		$note .= 'Transaction ID: ' . $transaction_data['id'] . '<br>';
		$note .= 'Date: ' . $transaction_date . '<br>';
		$note .= 'Time: ' . $transaction_time . '<br>';
		$note .= 'Exchange Number: <a href="' . $transaction_url . '" target="new" rel="noopener">' . $transaction_data['exchange_reference_number'] . '</a><br>';
		$note .= 'Buyer Name: ' . $transaction_data['payer_name'] . '<br>';
		$note .= 'Buyer Email: ' . $transaction_data['payer_email'] . '<br>';
		$note .= 'Status: ' . $status_description . '<br>';
		$note .= 'Status Description: ' . $transaction_data['status_description'] . '<br>';

		return $note;
	}

	private function get_status_description($status): string {
		$descriptions = ['New', 'Pending', 'Unsuccessful', 'Successful', 'Cancelled', 'Failed'];
		return $descriptions[ $status ] ?? 'unknown';
	}

	private function check_duplicate_order_note($order, string $new_order_note): bool
	{
		$previous_order_notes = $this->get_order_notes($order);

		if (empty($previous_order_notes)) {
			return false;
		}

		$previous_order_notes_content = array_map(function ($order_note) {
			return $order_note->content;
		}, $previous_order_notes);

		return in_array($new_order_note, $previous_order_notes_content);
	}

	private function get_order($order_id)
	{
		return wc_get_order($order_id);
	}

	private function delete_order_meta($order, $key)
	{
		if ($this->is_hpos_enabled()) {
			$order->delete_meta_data($key);
			$order->save();
		} else {
			delete_post_meta($order->get_id(), $key);
		}
	}

	private function add_order_note($order, $note)
	{
		if ($this->is_hpos_enabled()) {
			$order->add_order_note($note);
			$order->save();
		} else {
			$order->add_order_note($note);
		}
	}

	private function get_order_notes($order): array {
		return wc_get_order_notes(['order_id' => $order->get_id()]);
	}

	private function is_hpos_enabled(): bool {
		return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}