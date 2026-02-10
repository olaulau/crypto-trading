<?php
namespace COMMON__\svc;


class BinanceRestApi
{
	
	use BinanceRestApiCapitalConfigs;

	public final const dividendes_asset = "DIVIDENDES";

	
	/**
	 * generic rest query
	 */
	public static function query (string $path) : array
	{
		$binance_conf = Binance::get_conf();

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


	public static function getAssetDividend () : array
	{
		// 'type' => 'BNB_VAULT', // ou LAUNCHPOOL
		$path = '/sapi/v1/asset/assetDividend';
		return static::query($path) ["rows"];
	}


	public static function assetDividend_to_spotTrades (array $asset_dividendes) : array
	{
		$res = [];
		foreach ($asset_dividendes as $dividende) {
			$base_asset = static::dividendes_asset;
			$quote_asset = $dividende ["asset"];
			if ($dividende ["direction"] === 1) {
				$is_buyer = 0;
			}
			else {
				$is_buyer = 1;
			}
			
			$res [] = [
				'symbol'			=> "{$base_asset}{$quote_asset}",
				'id'				=> $dividende ["tranId"],
				'orderId'			=> $dividende ["id"],
				'orderListId'		=> -1,
				'price'				=> 1,
				'qty'				=> $dividende ["amount"], #TODO which coin are they all, USDC ?
				'quoteQty'			=> $dividende ["amount"], 
				'commission'		=> 0, 
				'commissionAsset'	=> "EUR", #TODO use constant
				'time'				=> $dividende ["divTime"],
				'isBuyer'			=> $is_buyer,
				'isMaker'			=> 0,
				'isBestMatch'		=> 1,
			];
		}
		return $res;
	}


	#TODO crypto deposit & withdraw
	/*
	Dépôt BTC / USDT	/sapi/v1/capital/deposit/hisrec
	Retrait crypto	/sapi/v1/capital/withdraw/history
	*/
	
}
