<?php
namespace Bayarcash\WooCommerce;

class DuitNowQRIS extends Gateway {
	public function __construct() {
		parent::__construct('duitnowqris');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Indonesia Online Banking',
			'method_title' => 'Bayarcash QRIS Online Banking'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Seamlessly scan & pay with online banking using QRIS - Indonesia national QR code payment.',
            'method_description' => 'Allow Indonesia customers to pay with QRIS online banking.',
        ];
    }

	protected function set_icon(): void {
		$settings = get_option('woocommerce_duitnowqris-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'qris-online-banking-all.png';
		} else {
			$icon_filename = 'qris-online-banking.png';
		}

		$icon_url = $this->url . 'includes/admin/img/qris/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}