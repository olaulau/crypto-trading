<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use ErrorException;


class BinanceSpotApi
{
	
	public static function get_spot_api () : SpotRestApi
	{
		$f3 = Base::instance();
		
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$spot_api = new SpotRestApi($configurationBuilder->build());
		
		return $spot_api;
	}
	
	
	public static function get_trades (string $crypto_pair) : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->myTrades($crypto_pair);
		$row = $response->getData(); /** @var MyTradesResponse $row */
		$items = $row->getItems(); /** @var MyTradesResponseInner[] $items */

		$data = [];
		foreach ($items as $item) {
			$row = [];
			foreach ($item->attributeMap() as $attribute) {
				$attribute = ucfirst ($attribute);
				$getter_method_name = "get$attribute";
				$row [$attribute] = $item->$getter_method_name();
			}
			$data [] = $row;
		}
		return $data;
	}
	
	
	public static function get_trades_grouped (string $crypto_pair) : array
	{
		$data = static::get_trades($crypto_pair);
		$data = Stuff::array_group_by($data, "OrderListId");
		return $data;
	}
	
	
	public static function get_trades_stats (string $base_asset, $quote_asset)
	{
		# config
		$crypto_pair = "{$base_asset}{$quote_asset}";
		$quote_dust_threashold = 10; # if we have less that threshold €/$ of remaining asset, reset stats (to cancel lost quote)
		
		# get trades
		$data = BinanceSpotApi::get_trades ($crypto_pair);
		
		# init
		$res = [
			$crypto_pair => [
				"entry" => [
					"quantity"	=> 0,
					"cost"		=> 0,
					"quote"		=> 0,
					"avg"		=> 0,
					"last"		=> 0,
				],
				"exit" => [
					"quantity"	=> 0,
					"cost"		=> 0,
					"quote"		=> 0,
					"avg"		=> 0,
					"last"		=> 0,
				],
			]
		];
		
		# treat trades
		foreach ($data as $trade) {
			// var_dump($trade);
			if ($trade ["Symbol"] !== $crypto_pair) {
				throw new ErrorException("wrong symbol found in trade : {$trade ["Symbol"]}");
			}
			// echo Binance::timestamp_to_datetime($trade ["Time"]) -> format(Stuff::datetime_sql_format) . " <br/>" . PHP_EOL;
			
			if ($trade ["IsBuyer"] === true) {
				// echo "- BUY {$trade ["Qty"]} {$base_asset} @ {$trade ["Price"]} = {$trade ["QuoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res [$crypto_pair] ["entry"] ["last"] = $trade ["Time"];
				$res [$crypto_pair] ["entry"] ["quantity"] += $trade ["Qty"];
				$res [$crypto_pair] ["entry"] ["cost"] += $trade ["QuoteQty"];
				$res [$crypto_pair] ["exit"] ["quantity"] = max($res [$crypto_pair] ["exit"] ["quantity"] - $trade ["Qty"], 0);
				$res [$crypto_pair] ["exit"] ["cost"] = max($res [$crypto_pair] ["exit"] ["cost"] - $trade ["QuoteQty"], 0);
			}
			else {
				// echo "- SELL {$trade ["Qty"]} {$base_asset} @ {$trade ["Price"]} = {$trade ["QuoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res [$crypto_pair] ["exit"] ["last"] = $trade ["Time"];
				$res [$crypto_pair] ["entry"] ["quantity"] = max($res [$crypto_pair] ["entry"] ["quantity"] - $trade ["Qty"], 0);
				$res [$crypto_pair] ["entry"] ["cost"] = max($res [$crypto_pair] ["entry"] ["cost"] - $trade ["QuoteQty"], 0);
				$res [$crypto_pair] ["exit"] ["quantity"] += $trade ["Qty"];
				$res [$crypto_pair] ["exit"] ["cost"] += $trade ["QuoteQty"];
			}
			
			$res [$crypto_pair] ["entry"] ["quote"] = $res [$crypto_pair] ["entry"] ["quantity"] * $trade ["Price"];
			if ($res [$crypto_pair] ["entry"] ["quantity"] != 0) {
				$res [$crypto_pair] ["entry"] ["avg"] = $res [$crypto_pair] ["entry"] ["cost"] / $res [$crypto_pair] ["entry"] ["quantity"];
			}
			else {
				$res [$crypto_pair] ["entry"] ["avg"] = 0;
			}
			
			$res [$crypto_pair] ["exit"] ["quote"] = $res [$crypto_pair] ["exit"] ["quantity"] * $trade ["Price"];
			if ($res [$crypto_pair] ["exit"] ["quantity"] != 0) {
				$res [$crypto_pair] ["exit"] ["avg"] = $res [$crypto_pair] ["exit"] ["cost"] / $res [$crypto_pair] ["exit"] ["quantity"];
			}
			else {
				$res [$crypto_pair] ["exit"] ["avg"] = 0;
			}
			
			// echo " entry => " . $res [$crypto_pair] ["entry"] ["quantity"] . " {$base_asset} = {$res [$crypto_pair] ["entry"] ["quote"]} {$quote_asset} <=> "
			// 	 . $res [$crypto_pair] ["entry"] ["cost"] . " {$quote_asset} @ {$res [$crypto_pair] ["entry"] ["avg"]} <br/>" . PHP_EOL;
			if ($res [$crypto_pair] ["entry"] ["quote"] > 0 && $res [$crypto_pair] ["entry"] ["quote"] < $quote_dust_threashold) {
				$res [$crypto_pair] ["entry"] ["quantity"] = 0;
				$res [$crypto_pair] ["entry"] ["cost"] = 0;
				// echo " entry dust reset <br/>" . PHP_EOL;
			}
			
			// echo " exit => " . $res [$crypto_pair] ["exit"] ["quantity"] . " {$base_asset} = {$res [$crypto_pair] ["exit"] ["quote"]} {$quote_asset} <=> "
			// 	 . $res [$crypto_pair] ["exit"] ["cost"] . " {$quote_asset} @ {$res [$crypto_pair] ["exit"] ["avg"]} <br/>" . PHP_EOL;
			if ($res [$crypto_pair] ["exit"] ["quote"] > 0 && $res [$crypto_pair] ["exit"] ["quote"] < $quote_dust_threashold) {
				$res [$crypto_pair] ["exit"] ["quantity"] = 0;
				$res [$crypto_pair] ["exit"] ["cost"] = 0;
				// echo " exit dust reset <br/>" . PHP_EOL;
			}
			
			// echo "<br/>" . PHP_EOL;
		}
		
		// echo "==> entry (buy) avg = {$res [$crypto_pair] ["entry"] ["avg"]} <br/>" . PHP_EOL;
		// echo "==> exit (sell) avg = {$res [$crypto_pair] ["exit"] ["avg"]} <br/>" . PHP_EOL;
		
		return $res;
	}
	
}
