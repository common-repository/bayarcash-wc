<?php

namespace Bayarcash\WooCommerce;

class CustomProductText
{
	private array $payment_methods;
	private string $plugin_url;

	public function __construct()
	{
		$this->payment_methods = [
			'duitnowshopee' => [
				'installments' => 12,
				'image' => 'includes/admin/img/spaylater/spaylater.png',
				'alt' => 'SPayLater'
			],
			'duitnowboost' => [
				'installments' => 9,
				'image' => 'includes/admin/img/boost/boost-payflex.png',
				'alt' => 'Boost PayFlex'
			]
			// Add more payment methods here as needed
		];

		$this->plugin_url = plugin_dir_url(dirname(__FILE__, 2));
		$this->init_payment_methods();

		add_filter('woocommerce_loop_add_to_cart_link', [$this, 'modify_add_to_cart_button'], 10, 3);
		add_action('woocommerce_single_product_summary', [$this, 'add_payment_info_to_single_product'], 11);
		add_action('wp_footer', [$this, 'add_custom_css']);
	}

	private function init_payment_methods(): void
	{
		foreach ($this->payment_methods as $method => &$data) {
			$data['enabled'] = $this->is_payment_method_enabled($method . '-wc');
			$data['logo_enabled'] = $this->is_logo_enabled($method . '-wc');
		}
	}

	private function is_payment_method_enabled(string $method): bool
	{
		$settings = get_option("woocommerce_{$method}_settings", []);
		return isset($settings['enabled']) && $settings['enabled'] === 'yes';
	}

	private function is_logo_enabled(string $method): bool
	{
		$settings = get_option("woocommerce_{$method}_settings", []);
		return isset($settings['enable_logo_on_catalog']) && $settings['enable_logo_on_catalog'] === 'yes';
	}

	public function modify_add_to_cart_button($add_to_cart_html, $product, $args): string
	{
		$before = $this->get_payment_info_html($product->get_price());
		return $before . $add_to_cart_html;
	}

	public function add_payment_info_to_single_product(): void
	{
		global $product;
		if ($product) {
			echo $this->get_payment_info_html($product->get_price());
		}
	}

	private function get_payment_info_html(float $price): string
	{
		$html = '';
		foreach ($this->payment_methods as $method => $data) {
			if ($data['enabled'] && $data['logo_enabled']) {
				$installment_price = number_format($price / $data['installments'], 2);
				$image_url = $this->plugin_url . $data['image'];
				$html .= "<div class='shop-badge full-width'>
                            or {$data['installments']} payment of <strong>RM {$installment_price}</strong> <span>with</span>
                            <img src='{$image_url}' alt='{$data['alt']}' class='payment-icon'>
                          </div>";
			}
		}
		return $html;
	}

	public function add_custom_css(): void
	{
		echo '<style>
            .shop-badge.full-width {
                align-items: center;
                width: 100%;
                padding: 0;
                font-size: 0.7em;
                color: #333;
                margin-bottom: 10px;
            }
            .single-product .product .summary .shop-badge.full-width {
                font-size: 1em;
                margin-bottom: 20px;
            }
            .shop-badge .payment-icon {
                height: 20px;
                margin-left: 5px;
                vertical-align: middle;
            }
            .single-product .product .summary .shop-badge .payment-icon {
                height: 24px;
            }
            .woocommerce-loop-product__link {
                display: flex;
                flex-direction: column;
            }
            .woocommerce-loop-product__link .shop-badge.full-width {
                order: -1;
            }
        </style>';
	}
}