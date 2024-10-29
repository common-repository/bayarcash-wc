<?php
namespace Bayarcash\WooCommerce;

class DuitNowGateway extends Gateway {
	public function __construct() {
		parent::__construct('duitnow');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Online Banking',
			'method_title' => 'Bayarcash DuitNow OBW'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Pay with online banking Maybank2u, CIMB Clicks, Bank Islam GO and more banks from Malaysia via DuitNow.',
            'method_description' => 'Allow customers to pay with DuitNow Online Banking/Wallets.',
        ];
    }
	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnow-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'dobw-all.png';
		} else {
			$icon_filename = 'dobw.png';
		}

		$icon_url = $this->url . 'includes/admin/img/dobw/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}