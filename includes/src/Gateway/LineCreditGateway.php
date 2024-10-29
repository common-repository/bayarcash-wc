<?php
namespace Bayarcash\WooCommerce;

class LineCreditGateway extends Gateway {
	public function __construct() {
		parent::__construct('linecredit');
		$this->supports = ['products'];
	}

	protected function get_payment_titles(): array {
		return [
			'title' => 'Credit Card',
			'method_title' => 'Bayarcash Credit Card Account'
		];
	}

    protected function get_payment_descriptions(): array {
        return [
            'description' => 'Pay with Visa/Mastercard credit card account issued by Malaysia local banks.',
            'method_description' => 'Allow customers to pay with Visa/Mastercard credit card account via FPX.',
        ];
    }
	
	protected function set_icon(): void {
		$settings = get_option('woocommerce_linecredit-wc_settings', []);
		$checkout_logo = $settings['checkout_logo'] ?? '1';

		if ($checkout_logo === '2') {
			$icon_filename = 'visa-mastercard-all.png';
		} else {
			$icon_filename = 'visa-mastercard.png';
		}

		$icon_url = $this->url . 'includes/admin/img/linecredit/' . $icon_filename;

		$this->icon = apply_filters('woocommerce_' . $this->id . '_icon', $icon_url);
	}
}