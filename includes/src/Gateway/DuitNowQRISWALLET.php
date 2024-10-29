<?php
namespace Bayarcash\WooCommerce;

class DuitNowQRISWALLET extends Gateway {
	public function __construct() {
		parent::__construct('duitnowqriswallet');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Indonesia e-Wallet',
			'method_title' => 'Bayarcash QRIS e-Wallet'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Seamlessly scan & pay with e-wallet using QRIS - Indonesia national QR code payment.',
            'method_description' => 'Allow Indonesia customers to pay with QRIS e-wallet.',
        ];
    }

	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnowqriswallet-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'qris-ewallet-all.png';
		} else {
			$icon_filename = 'qris-ewallet.png';
		}

		$icon_url = $this->url . 'includes/admin/img/qris/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}