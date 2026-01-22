<?php
namespace COMMON__\svc;

use Base;


class BinanceCustomApi
{

	public static function get_capital_config () : array
	{
		$f3 = Base::instance();
		
		$query = http_build_query([
			'timestamp' => (int)(microtime(true) * 1000),
			'recvWindow' => 5000
		]);
		$signature = hash_hmac('sha256', $query, $f3->get("binance.secret"));
		$url = 'https://api.binance.com/sapi/v1/capital/config/getall' . '?' . $query . '&signature=' . $signature;
		
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_HTTPHEADER => [
				'X-MBX-APIKEY: ' . $f3->get("binance.key")
			],
			CURLOPT_RETURNTRANSFER => true
		]);
		
		$response = curl_exec ($ch);
		$data = json_decode ($response, true);
		return $data;
	}
	
}
