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

use Nawawi\Utils\Base64Encryption;

\defined('ABSPATH') || exit;

function bayarcash_plugin_meta()
{
	static $meta = null;

	if (empty($meta)) {
		$meta = get_plugin_data(BAYARCASH_WC['FILE'], false);
	}

	return $meta;
}

function bayarcash_version()
{
	return bayarcash_plugin_meta()['Version'];
}

function bayarcash_request_data()
{
	static $inst = null;
	if (null === $inst) {
		$inst = new DataRequest();
	}

	return $inst;
}

function bayarcash_data_store()
{
	static $inst = null;
	if (null === $inst) {
		$inst = new DataStore();
	}

	return $inst;
}

function bayarcash_encryption()
{
	static $inst = null;
	if (null === $inst) {
		$inst = new Base64Encryption();
	}

	return $inst;
}

function bayarcash_return_token_set($data, $key, $type = 'fpx')
{
	$str = $data.'|'.substr(md5($data), 0, 12).'|'.$type;

	return bayarcash_encryption()->encrypt($str, $key);
}

function bayarcash_return_token_get($data, $key, $type)
{
	$str = bayarcash_encryption()->decrypt($data, $key);
	if ($str === $data || false === strpos($str, '|'.$type)) {
		return false;
	}

	$str_a = explode('|', $str);
	if ($str_a[2] !== $type) {
		return false;
	}

	return (object) [
		'data'    => $str_a[0],
		'data_id' => $str_a[1],
		'type'    => $str_a[2],
	];
}

function bayarcash_strip_whitespace($string)
{
	return preg_replace('@[\s\n\r\t]+@s', '', $string);
}

function bayarcash_has_fpx_transaction_status($status, $match)
{
	$lists = [
		0 => 'new',
		1 => 'pending',
		2 => 'unsuccessful',
		3 => 'successful',
		4 => 'cancelled',
		5 => 'failed'
	];

	$match = strtolower($match);
	return isset($lists[$status]) && $lists[$status] === $match;
}

function bayarcash_gateway_config()
{
	if (!\function_exists('is_woocommerce')) {
		return false;
	}

	$payment_gateway = WC()->payment_gateways->payment_gateways();

	if (!empty($payment_gateway) && !empty($payment_gateway['bayarcash-wc'])) {
		return $payment_gateway['bayarcash-wc'];
	}

	return false;
}

function bayarcash_endpoint()
{
	$config = bayarcash_gateway_config();

	if (!$config || empty($config->settings['sandbox_mode'])) {
		return BAYARCASH_WC['ENDPOINT']['PUBLIC'];
	}

	return 'yes' === $config->settings['sandbox_mode'] ? BAYARCASH_WC['ENDPOINT']['PRIVATE'] : BAYARCASH_WC['ENDPOINT']['PUBLIC'];
}

function bayarcash_note_text($data)
{
	$note = '';
	foreach ($data as $k => $v) {
		if ('' === trim($v)) {
			continue;
		}

		$k = str_replace('_', ' ', str_replace('fpx_data', 'FPX_data', $k));
		$k = ucwords($k);
		$note .= $k.': '.$v.'<br>';
	}

	return rtrim(trim($note), '<br>');
}

function bayarcash_debug_log($text)
{

	if (!\function_exists('wc_get_logger')) {
		return;
	}

	$logger  = wc_get_logger();
	$context = ['source' => 'bayarcash-wc'];
	$logger->debug($text, $context);
}

