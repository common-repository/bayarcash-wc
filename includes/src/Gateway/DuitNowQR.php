<?php
namespace Bayarcash\WooCommerce;

class DuitNowQR extends Gateway {
	public function __construct() {
		parent::__construct('duitnowqr');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'DuitNow QR',
			'method_title' => 'Bayarcash DuitNow QR',
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Pay with Malaysia online banking & e-wallet via DuitNow QR.',
            'method_description' => 'Allow customers to pay with DuitNow QR.',
        ];
    }

	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnowqr-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'duitnowqr-all.png';
		} else {
			$icon_filename = 'duitnow-qr.png';
		}

		$icon_url = $this->url . 'includes/admin/img/duitnowqr/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}