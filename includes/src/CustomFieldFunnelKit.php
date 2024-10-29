<?php

namespace Bayarcash\WooCommerce;

use WC_Subscriptions_Cart;

class CustomFieldFunnelKit {

	private $hook_run = false;
	private $identification_number_field;
	private $identification_type_field;

	public function __construct() {
		add_action('wfacp_after_template_found', [$this, 'init_fields']);
	}

	public function init_fields(): void {
		$this->identification_number_field = array(
			'label'       => __('Identification Number', 'bayarcash-wc'),
			'type'        => 'text',
			'field_type'  => 'shipping',
			'placeholder' => __('Identification Number', 'bayarcash-wc'),
			'required'    => true,
			'class'       => ['wfacp-col-full'],
			'clear'       => true,
			'id'          => 'bayarcash_identification_id',
		);

		$this->identification_type_field = array(
			'label'       => __('Identification Type', 'bayarcash-wc'),
			'type'        => 'select',
			'field_type'  => 'shipping',
			'required'    => true,
			'class'       => ['wfacp-col-full'],
			'clear'       => true,
			'id'          => 'bayarcash_identification_type',
			'options'     => [
				'1' => 'New IC Number',
				'2' => 'Old IC Number',
				'3' => 'Passport Number',
				'4' => 'Business Registration',
			],
			'custom_attributes' => ['style' => 'padding: 14px 17px; font-size: 16px;'],
		);

		add_filter('wfacp_get_checkout_fields', array($this, 'wfacp_get_checkout_fields'), 8);
		add_filter('wfacp_get_fieldsets', array($this, 'wfacp_get_fieldsets'), 7);
	}

	public function wfacp_get_checkout_fields($fields) {
		if (!$this->cart_contains_subscription()) {
			return $fields;
		}

		if (is_array($fields) && count($fields) > 0) {
			$temp   = wfacp_template();
			$status = $temp->get_shipping_billing_index();

			if (!isset($fields['shipping']['bayarcash_identification_id']) || !isset($fields['shipping']['bayarcash_identification_type'])) {
				if ($status === 'shipping') {
					$this->identification_number_field['class'] = ['wfacp-col-full', 'wfacp_shipping_field_hide', 'wfacp_shipping_fields'];
					$this->identification_type_field['class'] = ['wfacp-col-full', 'wfacp_shipping_field_hide', 'wfacp_shipping_fields'];
				} else {
					$this->identification_number_field['class'] = ['wfacp-col-full'];
					$this->identification_type_field['class'] = ['wfacp-col-full'];
				}

				$fields['shipping']['bayarcash_identification_type'] = $this->identification_type_field;
				$fields['shipping']['bayarcash_identification_id'] = $this->identification_number_field;
			}
		}

		return $fields;
	}

	public function wfacp_get_fieldsets($section): array {
		if (!$this->cart_contains_subscription()) {
			return $section;
		}

		if (false === $this->hook_run) {
			$section['single_step'][0]['fields'][] = $this->identification_type_field;
			$section['single_step'][0]['fields'][] = $this->identification_number_field;
		}

		return $section;
	}

	private function cart_contains_subscription(): bool {
		return class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription();
	}
}