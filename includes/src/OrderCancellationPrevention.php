<?php
namespace Bayarcash\WooCommerce;

defined('ABSPATH') || exit;

class OrderCancellationPrevention {
	public function __construct() {
		add_action('woocommerce_order_action_wc_mark_cancelled', [$this, 'preventMarkCancelledForDirectDebit'], 10, 1);
		add_action('woocommerce_order_action_mark_cancelled', [$this, 'preventMarkCancelledForDirectDebit'], 10, 1);
		add_filter('wc_order_statuses', [$this, 'removeCancelledStatusForDirectDebit'], 10, 2);
		add_filter('woocommerce_bulk_action_ids', [$this, 'preventBulkCancelDirectDebit'], 10, 2);
		add_action('admin_notices', [$this, 'displayDirectDebitCancelPreventionNotice']);
	}

	public function preventMarkCancelledForDirectDebit($order): void {
		if ($order->get_payment_method() !== 'directdebit-wc') {
			return;
		}
		$order->add_order_note(__('Attempted to cancel Direct Debit order. Cancellation prevented.', 'bayarcash-wc'));
		wp_die(__('Direct Debit orders cannot be cancelled.', 'bayarcash-wc'));
	}

	public function removeCancelledStatusForDirectDebit($order_statuses): array {
		if (!$this->isOrderEditPage()) {
			return $order_statuses;
		}

		$order = $this->getCurrentOrder();
		if ($order && $order->get_payment_method() === 'directdebit-wc') {
			unset($order_statuses['wc-cancelled']);
		}
		return $order_statuses;
	}

	public function preventBulkCancelDirectDebit(array $order_ids, string $action): array {
		if ($action !== 'mark_cancelled') {
			return $order_ids;
		}

		return array_filter($order_ids, function($order_id) {
			$order = wc_get_order($order_id);
			if ($order && $order->get_payment_method() === 'directdebit-wc') {
				$order->add_order_note(__('Attempted to bulk cancel Direct Debit order. Cancellation prevented.', 'bayarcash-wc'));
				return false;
			}
			return true;
		});
	}

	public function displayDirectDebitCancelPreventionNotice(): void {
		global $pagenow, $typenow;
		if ($pagenow === 'edit.php' && $typenow === 'shop_order' && isset($_REQUEST['bulk_action']) && $_REQUEST['bulk_action'] === 'mark_cancelled') {
			echo '<div class="notice notice-warning is-dismissible"><p>' . __('Cancellation of Direct Debit orders was prevented.', 'bayarcash-wc') . '</p></div>';
		}
	}

	private function isOrderEditPage(): bool {
		if (!is_admin()) {
			return false;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		return $screen && in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true);
	}

	private function getCurrentOrder(): ?\WC_Order {
		global $post;
		$order = null;

		if (isset($post->ID)) {
			$order = wc_get_order($post->ID);
		} elseif (isset($_GET['id'])) {
			$order = wc_get_order($_GET['id']);
		}

		return $order instanceof \WC_Order ? $order : null;
	}
}