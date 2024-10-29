<?php
namespace Bayarcash\WooCommerce;

class DuitNowBoostPayFlex extends Gateway {
	public function __construct() {
		parent::__construct('duitnowboost');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Boost PayFlex',
			'method_title' => 'Bayarcash BNPL by Boost'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Stretch your payments up to 9 months BNPL from Boost. Shariah compliant certified.',
            'method_description' => 'Allow customers to pay with Boost PayFlex.',
        ];
    }

	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnowboost-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'duitnowboost-all.png';
		} else {
			$icon_filename = 'boost-payflex.png';
		}

		$icon_url = $this->url . 'includes/admin/img/boost/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}