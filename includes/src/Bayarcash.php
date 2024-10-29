<?php
/**
 * Bayarcash WooCommerce.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

namespace Bayarcash\WooCommerce;

\defined('ABSPATH') || exit;

final class Bayarcash
{
	public $file;
	public $slug;
	public $hook;
	public $path;
	public $page;
	public $url;

	public function __construct()
	{
		$this->slug = BAYARCASH_WC['SLUG'];
		$this->hook = BAYARCASH_WC['HOOK'];
		$this->path = BAYARCASH_WC['PATH'];
		$this->url  = BAYARCASH_WC['URL'];
	}

	private function register_locale(): void {
		add_action(
			'plugins_loaded',
			function () {
				load_plugin_textdomain(
					$this->slug,
					false,
					$this->path.'/languages/'
				);
			},
			0
		);
	}

	public function register_admin_hooks(): void {
		add_filter(
			'plugin_action_links_'.$this->hook,
			function ($links) {
				$page = 'admin.php?page=wc-settings&tab=checkout&section='.$this->slug;
				$new  = [
					'bayarcash-wc-settings' => sprintf('<a href="%s">%s</a>', admin_url($page), esc_html__('Settings', 'bayarcash-wc')),
				];

				return array_merge($new, $links);
			}
		);

		add_filter(
			'plugin_row_meta',
			function ($links, $file) {
				if ($file == $this->hook) {
					$row_meta = array(
						'docs' => '<a href="' . esc_url('https://docs.bayarcash.com/') . '" target="_blank">Docs</a>',
						'api_docs' => '<a href="' . esc_url('https://api.webimpian.support/bayarcash') . '" target="_blank">API docs</a>',
						'register_account' => '<a href="' . esc_url('https://bayarcash.com/register/') . '" target="_blank">Register Account</a>',
					);

					return array_merge($links, $row_meta);
				}
				return $links;
			},
			10,
			2
		);

		add_filter('woocommerce_payment_gateways', function ($gateways) {
			$gateways[] = new FpxGateway();
			$gateways[] = new DuitNowGateway();
			$gateways[] = new LineCreditGateway();
			$gateways[] = new DuitNowQR();
			$gateways[] = new DuitNowSPayLater();
			$gateways[] = new DuitNowBoostPayFlex();
			$gateways[] = new DuitNowQRIS();
			$gateways[] = new DuitNowQRISWALLET();

			// Check if WooCommerce Subscriptions is active
			if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Core_Plugin')) {
				$gateways[] = new DirectDebitGateway();
			}

			return $gateways;
		});

		add_action(
			'plugins_loaded',
			function () {
				if ($this->is_woocommerce_activated()) {
					require_once __DIR__.'/Gateway.php';
				}

				if (current_user_can(apply_filters('capability', 'manage_options'))) {
					add_action('all_admin_notices', [$this, 'callback_compatibility'], \PHP_INT_MAX);
				}
			}
		);

		add_action(
			'admin_enqueue_scripts',
			function ($hook) {
				if (!$this->is_woocommerce_activated()) {
					return;
				}

				$version = bayarcash_version().'c'.date('Ymdh');

				wp_enqueue_script('vuejs', $this->url.'includes/admin/js/vuejs.js', [], '3.4.33', false);
				wp_enqueue_script('axios', $this->url.'includes/admin/js/axios.min.js', [], '0.21.1', true);
				wp_enqueue_script('lodash', $this->url.'includes/admin/js/lodash.min.js', [], '0.21.1', true);

				wp_enqueue_script($this->slug.'-script', $this->url.'includes/admin/bayarcash-wc-script.js', ['jquery'], $version, false);
				wp_enqueue_style($this->slug.'-css', $this->url.'includes/admin/bayarcash-wc-style.css', null, $version);
			}
		);

		// Enqueue styles for frontend checkout page
		add_action(
			'wp_enqueue_scripts',
			function () {
				if (!$this->is_woocommerce_activated() || !is_checkout()) {
					return;
				}

				$version = bayarcash_version().'c'.date('Ymdh');

				wp_enqueue_style($this->slug.'-checkout-css', $this->url.'includes/admin/checkout.css', null, $version);
			}
		);

		add_action('wp_ajax_get_bayarcash_settings', [$this, 'get_bayarcash_settings']);
	}

	public function get_bayarcash_settings(): void {
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized access');
		}

		$method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
		$option_name = "woocommerce_{$method}_settings";
		$settings = get_option($option_name, array());

		wp_send_json(json_encode($settings));
	}

	private function is_woocommerce_subscriptions_active(): bool {
		return class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Core_Plugin');
	}

	public function is_woocommerce_activated(): bool {
		return class_exists('WooCommerce', false) && class_exists('WC_Payment_Gateway', false);
	}

	public function callback_compatibility(): void {
		if (!$this->is_woocommerce_activated()) {
			$html = '<div id="bayarcash-notice" class="notice notice-error is-dismissible">';
			$html .= '<p>'.esc_html__('Bayarcash require WooCommerce plugin. Please install and activate.', 'bayarcash-wc').'</p>';
			$html .= '</div>';
			echo wp_kses_post($html);
		}
	}

	private function migrate_old_config(): void {
		add_action('shutdown', function () {
			// Get the option_value of old version bayarcash
			$bayarcash_settings = get_option('woocommerce_bayarcash_settings');

			if (!empty($bayarcash_settings)
			    && !empty($bayarcash_settings['enabled'])
			    && !empty($bayarcash_settings['bearer_token'])
			    && !empty($bayarcash_settings['portal_key'])) {
				$new_wc_settings = get_option('woocommerce_'.$this->slug.'_settings');

				if (!empty($new_wc_settings) || !empty($new_wc_settings['enabled'])
				    || !empty($new_wc_settings['bearer_token'])
				    || !empty($new_wc_settings['portal_key'])) {
					return;
				}

				// Create the default option_value for "refactoring" version
				$new_wc_settings = [
					'enabled'          => $bayarcash_settings['enabled'],
					'title'            => 'Bayarcash',
					'description'      => 'Pay with online banking Maybank2u, CIMB Clicks, Bank Islam GO and more banks from Malaysia.',
					'bearer_token'     => $bayarcash_settings['bearer_token'],
					'portal_key'       => $bayarcash_settings['portal_key'],
					'sandbox_mode'     => 'no',
					'debug_mode'       => 'no',
					'place_order_text' => 'Pay with Bayarcash',
				];

				// Add the new option to wp_options
				add_option('woocommerce_bayarcash-wc_settings', $new_wc_settings);
			}
		}, \PHP_INT_MAX);
	}

	private function deactivate_cleanup(): void {
		$this->unregister_cronjob();
	}

	public function activate(): void {
		// Unregister any remaining ones and register it on the self::register() method
		$this->unregister_cronjob();
	}

	public function deactivate(): void {
		$this->unregister_cronjob();
	}

	public static function uninstall(): void {
		( new self() )->deactivate_cleanup();
	}

	public function register_plugin_hooks()
	{
		register_activation_hook($this->hook, [$this, 'activate']);
		register_deactivation_hook($this->hook, [$this, 'deactivate']);
		register_uninstall_hook($this->hook, [__CLASS__, 'uninstall']);
	}

	public function register_cronjob(): void {
		( new CronEvent($this) )->register();
	}

	public function unregister_cronjob(): void {
		( new CronEvent($this) )->unregister();
	}

	public function register(): void {
		$this->register_locale();
		$this->migrate_old_config();
		$this->register_admin_hooks();
		$this->register_cronjob();
		$this->register_subscription_cancellation_hooks();
		$this->init_features();
	}

	private function init_features(): void
	{
		new BayarcashCheckoutFee();
		new OrderCancellationPrevention();
		new CustomFieldFunnelKit();
		new CustomProductText();
	}

	private function register_subscription_cancellation_hooks(): void {
		add_action('wp_enqueue_scripts', [$this, 'localize_subscription_cancellation_script']);
		add_action('wp_ajax_cancel_direct_debit_subscription', [$this, 'handle_cancel_direct_debit_subscription']);
		add_filter('wcs_view_subscription_actions', [$this, 'customize_subscription_actions'], 10, 2);
		add_action('wp_footer', [$this, 'add_subscription_cancellation_script']);
	}

	public function localize_subscription_cancellation_script(): void {
		if (is_account_page()) {
			wp_localize_script('jquery', 'bayarcash_ajax', array(
				'nonce' => wp_create_nonce('cancel-direct-debit-subscription'),
				'ajax_url' => admin_url('admin-ajax.php')
			));
		}
	}

	public function add_subscription_cancellation_script(): void {
		if (is_account_page()) {
			?>
			<script>
                jQuery(document).ready(function($) {
                    $(document).on('click', 'a.button.cancel.wcs_block_ui_on_click', function(e) {
                        e.preventDefault();

                        var subscriptionId = new URLSearchParams($(this).attr('href').split('?')[1]).get('subscription_id');

                        if (!subscriptionId) {
                            alert('Unable to identify the subscription. Please try again or contact support.');
                            window.location.reload(); // Force refresh if subscription ID is not found
                            return;
                        }

                        if (confirm('Are you sure you want to cancel this subscription?')) {
                            $.ajax({
                                url: bayarcash_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'cancel_direct_debit_subscription',
                                    subscription_id: subscriptionId,
                                    security: bayarcash_ajax.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        window.location.href = response.data.redirect_url;
                                    } else {
                                        alert(response.data.message);
                                        window.location.reload(); // Force refresh on error
                                    }
                                },
                                error: function() {
                                    alert('An error occurred. Please try again.');
                                    window.location.reload(); // Force refresh on AJAX error
                                }
                            });
                        } else {
                            window.location.reload(); // Force refresh if user clicks "Cancel" in the confirmation dialog
                        }
                    });
                });
			</script>
			<?php
		}
	}

	public function handle_cancel_direct_debit_subscription(): void {
		check_ajax_referer('cancel-direct-debit-subscription', 'security');

		if (!isset($_POST['subscription_id'])) {
			wp_send_json_error(array('message' => 'Subscription ID is missing.'));
		}

		$subscription_id = intval($_POST['subscription_id']);
		$subscription = wcs_get_subscription($subscription_id);

		if (!$subscription) {
			wp_send_json_error(array('message' => 'Invalid subscription.'));
		}

		$gateway = new DirectDebitGateway();
		$gateway->cancel_subscription($subscription);
	}

	public function customize_subscription_actions($actions, $subscription): array {
		// Check if this is a Direct Debit subscription
		if ($subscription->get_payment_method() === 'directdebit-wc') {
			// Modify the cancel action if it exists
			if (isset($actions['cancel'])) {
				// Change the label to "Terminate Direct Debit"
				$actions['cancel']['name'] = __('Terminate Direct Debit', 'bayarcash-wc');

				// Add a custom class for our JavaScript to target
				if (!isset($actions['cancel']['class'])) {
					$actions['cancel']['class'] = '';
				}
				$actions['cancel']['class'] .= ' terminate-direct-debit';
				$actions['cancel']['class'] = trim($actions['cancel']['class']);
			}
		}

		return $actions;
	}
}