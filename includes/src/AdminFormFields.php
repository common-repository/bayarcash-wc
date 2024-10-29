<?php
namespace Bayarcash\WooCommerce;

class AdminFormFields {
	public static function get_form_fields($payment_method, $titles): array {
		$fields = [
			'enabled' => [
				'title'       => esc_html__('Enable/Disable', $payment_method . '-wc'),
				'type'        => 'checkbox',
				'label'       => esc_html__('Enable ' . ucfirst($titles['method_title']), $payment_method . '-wc'),
				'description' => esc_html__('Enable ' . ucfirst($titles['method_title']), $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'sandbox_mode' => [
				'title'       => esc_html__('Sandbox Mode', $payment_method . '-wc'),
				'type'        => 'checkbox',
				'label'       => esc_html__('Enable sandbox mode', $payment_method . '-wc'),
				'description' => esc_html__('Enable sandbox mode', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title' => [
				'title'       => esc_html__('Title', $payment_method . '-wc'),
				'type'        => 'text',
				'description' => esc_html__('This is the title the user sees during checkout.', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => $titles['title'],
			],
			'description' => [
				'title'       => esc_html__('Description', $payment_method . '-wc'),
				'type'        => 'textarea',
				'description' => esc_html__('This is the description the user sees during checkout.', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => esc_html__('Pay with online banking Maybank2u, CIMB Clicks, Bank Islam GO and more banks from Malaysia.'),
			],
			'credentials' => [
				'title'       => esc_html__('Credentials', $payment_method . '-wc'),
				'type'        => 'title',
				'description' => esc_html__('Options to set Personal Access Token (PAT), Portal Key, and API Secret Key.', $payment_method . '-wc'),
			],
			'bearer_token' => [
				'title'       => esc_html__('Personal Access Token (PAT)', $payment_method . '-wc'),
				'type'        => 'textarea',
				'placeholder' => esc_html__('Fill in your PAT here', $payment_method . '-wc'),
				'description' => esc_html__('Fill in your PAT here', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => '',
			],
			'portal_key' => [
				'title'       => esc_html__('Portal Key', $payment_method . '-wc'),
				'type'        => 'select',
				'options'     => [], // This will be populated dynamically by JavaScript
				'description' => esc_html__('Select your portal key', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => '',
			],
			'api_secret_key' => [
				'title'       => esc_html__('API Secret Key', $payment_method . '-wc'),
				'type'        => 'text',
				'description' => esc_html__('This is your Bayarcash API Secret Key', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => '',
				'custom_attributes' => [
					'data-additional-info' => esc_html__('Please generate on console https://console.bayar.cash/profile', $payment_method . '-wc'),
				],
			],
			'miscellaneous' => [
				'title'       => esc_html__('Miscellaneous', $payment_method . '-wc'),
				'type'        => 'title',
				'description' => esc_html__('Additional options to customize the payment experience and debugging.', $payment_method . '-wc'),
			],
			'enabled_country' => [
				'title'       => esc_html__('Country Restrictions', $payment_method . '-wc'),
				'type'        => 'select',
				'options'     => [
					'ALL' => esc_html__('Off', $payment_method . '-wc'),
					'MY' => esc_html__('Malaysia', $payment_method . '-wc'),
					'ID' => esc_html__('Indonesia', $payment_method . '-wc'),
				],
				'description' => esc_html__('Limit this payment method to specific countries.', $payment_method . '-wc'),
				'desc_tip'    => false,
				'default'     => 'ALL',
			],
			'email_fallback' => [
				'title'       => esc_html__('Email Fallback', $payment_method . '-wc'),
				'type'        => 'email',
				'default'     => get_option('admin_email'),
				'placeholder' => esc_html__('Enter Valid Email', $payment_method . '-wc'),
				'description' => esc_html__('When email address is not requested from the customer, use this email address.', $payment_method . '-wc'),
			],
			'debug_mode' => [
				'title'       => 'Debug Mode',
				'type'        => 'checkbox',
				'label'       => esc_html__('Enable debug mode', $payment_method . '-wc'),
				'default'     => '0',
				'desc_tip'    => true,
				'description' => esc_html__('Logs additional information. <br>Log file path: Your admin panel -> WooCommerce -> System Status -> Logs', $payment_method . '-wc'),
			],
//			'checkout_logo' => [
//				'title'       => esc_html__('Checkout Logo', $payment_method . '-wc'),
//				'type'        => 'select',
//				'options'     => [
//					'1' => esc_html__('Minimal', $payment_method . '-wc'),
//					'2' => esc_html__('Show All Bank', $payment_method . '-wc'),
//				],
//				'description' => esc_html__('Select the style of the checkout logo', $payment_method . '-wc'),
//				'desc_tip'    => true,
//				'default'     => '1',
//			],
			'place_order_text' => [
				'title'       => esc_html__('Place Order Text', $payment_method . '-wc'),
				'type'        => 'text',
				'description' => esc_html__('This is the text for place order button.', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => esc_html__('Pay with ' . ucfirst($titles['title']), $payment_method . '-wc'),
				'placeholder' => esc_html__('This is the text for place order button', $payment_method . '-wc'),
			],
		];

		// DuitNow QR Setting under Miscellaneous
		if (in_array($payment_method, ['duitnowshopee', 'duitnowboost'])) {
			$fields['duitnow_qr_setting'] = [
				'title'       => esc_html__('DuitNow QR Setting', $payment_method . '-wc'),
				'type'        => 'title',
				'description' => esc_html__('Settings specific to DuitNow Shopee and DuitNow Boost', $payment_method . '-wc'),
			];
			$fields['enable_logo_on_catalog'] = [
				'title'       => esc_html__('Enable Logo on Catalog', $payment_method . '-wc'),
				'type'        => 'checkbox',
				'label'       => esc_html__('Display the logo on the product catalog', $payment_method . '-wc'),
				'description' => esc_html__('Enable this to show the payment method logo on the product catalog pages.', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => 'no',
			];
		}

		return array_merge($fields, [
			'additional_charges' => [
				'title'       => esc_html__('Additional Charges', $payment_method . '-wc'),
				'type'        => 'title',
				'description' => esc_html__('Options to add additional charges after checkout.', $payment_method . '-wc'),
			],
			'enable_additional_charges' => [
				'title'       => esc_html__('Enable Additional Charges', $payment_method . '-wc'),
				'type'        => 'checkbox',
				'label'       => esc_html__('Enable additional charges after checkout', $payment_method . '-wc'),
				'description' => esc_html__('Check this to enable additional charges', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'additional_charge_type' => [
				'title'       => esc_html__('Charge Type', $payment_method . '-wc'),
				'type'        => 'select',
				'options'     => [
					'fixed'     => esc_html__('Fixed Fee', $payment_method . '-wc'),
					'percentage' => esc_html__('Percentage', $payment_method . '-wc'),
					'both'      => esc_html__('Both', $payment_method . '-wc'),
				],
				'description' => esc_html__('Select the type of additional charge', $payment_method . '-wc'),
				'desc_tip'    => true,
				'default'     => 'fixed',
			],
			'additional_charge_amount' => [
				'title'       => esc_html__('Charge Amount', $payment_method . '-wc'),
				'type'        => 'text',
				'description' => esc_html__('Enter the amount for the additional charge. For fixed fee, enter the amount in RM. For percentage, enter the percentage value (e.g., 8 for 8%)', $payment_method . '-wc'),
				'default'     => '1',
			],
			'additional_charge_percentage' => [
				'title'       => esc_html__('Additional Percentage Charge (%)', $payment_method . '-wc'),
				'type'        => 'text',
				'description' => esc_html__('Enter the percentage value for the additional charge (e.g., 8 for 8%). This is used when charge type is set to "Both" only.', $payment_method . '-wc'),
				'default'     => '0',
				'custom_attributes' => [
					'data-show-if-additional_charge_type' => 'both',
				],
			],
		]);
	}

	public static function custom_order_button_text($gateway_id, $place_order_text)
	{
		$chosen_payment_method = WC()->session->get('chosen_payment_method');

		if ($chosen_payment_method == $gateway_id) {
			return $place_order_text;
		}

		return null;
	}
}