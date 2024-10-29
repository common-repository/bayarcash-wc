<?php
namespace Bayarcash\WooCommerce;

class DuitNowSPayLater extends Gateway {
	public function __construct() {
		parent::__construct('duitnowshopee');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'SPayLater',
			'method_title' => 'Bayarcash BNPL by Shopee'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Flexible BNPL payment up to 12-month instalments from Shopee. Shariah compliant certified.',
            'method_description' => 'Allow customers to pay with SPayLater by Shopee.',
        ];
    }

	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnowshopee-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'spaylater-all.png';
		} else {
			$icon_filename = 'spaylater.png';
		}

		$icon_url = $this->url . 'includes/admin/img/spaylater/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}