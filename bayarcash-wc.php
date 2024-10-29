<?php
/**
 * Bayarcash WooCommerce.
 *
 * @author  Web Impian
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

/*
 * @wordpress-plugin
 * Plugin Name:         Bayarcash WC
 * Plugin URI:          https://bayarcash.com/
 * Version:             4.2.5
 * Description:         Accept payment from Malaysia. Bayarcash support FPX, Direct Debit, DuitNow OBW & DuitNow QR payment channels.
 * Author:              Web Impian
 * Author URI:          https://bayarcash.com/
 * Requires at least:   5.6
 * Tested up to:        6.6.1
 * Requires PHP:        7.4
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:         bayarcash-wc
 * Domain Path:         /languages
 * Requires Plugins: woocommerce
 */

namespace Bayarcash\WooCommerce;

\defined('ABSPATH') && !\defined('BAYARCASH_WC') || exit;

\define(
    'BAYARCASH_WC',
    [
        'SLUG'     => 'bayarcash-wc',
        'FILE'     => __FILE__,
        'HOOK'     => plugin_basename(__FILE__),
        'PATH'     => realpath(plugin_dir_path(__FILE__)),
        'URL'      => trailingslashit(plugin_dir_url(__FILE__)),
    ]
);

require __DIR__.'/includes/load.php';
(new Bayarcash())->register();
