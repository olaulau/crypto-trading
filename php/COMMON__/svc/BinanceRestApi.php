<?php
namespace COMMON__\svc;


class BinanceRestApi
{
	
	use BinanceRestApiCapitalConfigs, BinanceRestApiAssetDividend;

	public final const dividendes_asset = "DIVIDENDES";

	
	/**
	 * generic rest query
	 */
	public static function query (string $path, ?string $env=null) : array
	{
		$binance_conf = Binance::get_conf($env);

		$query = http_build_query([
			'timestamp' => (int)(microtime(true) * 1000),
			'recvWindow' => 5000
		]);
		$signature = hash_hmac('sha256', $query, $binance_conf ["secret"]);
		$url = $binance_conf ["rest_url"] . $path . '?' . $query . '&signature=' . $signature;
		
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_HTTPHEADER => [
				'X-MBX-APIKEY: ' . $binance_conf ["key"],
			],
			CURLOPT_RETURNTRANSFER => true
		]);
		
		$response = curl_exec ($ch);
		$data = json_decode ($response, true);
		return $data;
	}


	#TODO crypto deposit & withdraw
	/*
	Dépôt BTC / USDT	/sapi/v1/capital/deposit/hisrec
	Retrait crypto	/sapi/v1/capital/withdraw/history
	*/
	
}
