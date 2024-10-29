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

class DataRequest
{
	private $last_error_message = null;
	private $last_headers       = null;

	public function get_last_error_message()
	{
		return $this->last_error_message;
	}

	public function get_last_headers()
	{
		return $this->last_headers;
	}

	public function request_terminate($data, $endpoint_url)
	{
		$args = [
			'body' => $data,
		];

		return $this->send($args, $endpoint_url);
	}

	/**
	 * @throws \Exception
	 */
	function bayarcash_requery($transaction_id, $bearer_token, $sandbox_mode = false)
	{
		$base_url = $sandbox_mode ? 'https://console.bayarcash-sandbox.com' : 'https://console.bayar.cash';
		$url = $base_url . '/api/v2/transactions/' . $transaction_id;
		bayarcash_debug_log('Requerying transaction: ' . $url);

		$args = [
			'method'  => 'GET',
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $bearer_token
			]
		];

		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			throw new \Exception('WordPress HTTP Error: ' . $response->get_error_message());
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			throw new \Exception('HTTP Error: Received response code ' . $response_code);
		}

		$body = wp_remote_retrieve_body($response);
		$decoded_response = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \Exception('JSON Decoding Error: ' . json_last_error_msg());
		}

		return $decoded_response;
	}
}
