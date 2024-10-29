<?php
namespace Bayarcash\WooCommerce;

class FpxGateway extends Gateway {
	public function __construct() {
		parent::__construct('bayarcash');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Online Banking',
			'method_title' => 'Bayarcash FPX'
		];
	}

	protected function get_payment_descriptions(): array {
		return [
			'description' => 'Pay with online banking Maybank2u, CIMB Clicks, Bank Islam GO and more banks from Malaysia via FPX.',
			'method_description' => 'Allow customers to pay with FPX online banking.',
		];
	}

	protected function set_icon(): void {
		$settings = get_option('woocommerce_bayarcash-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '1') {
			$icon_filename = 'fpx-online-banking.png';
		} elseif ($checkout_logo === '2') {
			$icon_filename = 'fpx-all.png';
		} else {
			$icon_filename = 'fpx-online-banking.png';
		}

		$icon_url = $this->url . 'includes/admin/img/fpx/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}