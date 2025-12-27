<?php

namespace COMMON__\ctrl;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use COMMON__\mdl\Kline;
use COMMON__\svc\Binance;
use COMMON__\svc\BinanceSpotApi;
use COMMON__\svc\Stuff;
use ErrorException;


class BinanceCtrl extends Ctrl
{



	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}


	public static function afterRoute ()
	{
		parent::afterRoute();
	}


	public static function breadcrumbs ()
	{
		$res = [];
		return $res;
	}



	public static function stuffGET (Base $f3, $url, $controler)
	{
		// Construction de la config
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$spot_api = new SpotRestApi($configurationBuilder->build());

		$response = $spot_api->time();
		var_dump($response->getData());

		// $response = $spot_api->exchangeInfo();
		// var_dump($response->getData());

		// $response = $spot_api->allOrders(IndexCtrl::$crypto_pair);
		// var_dump($response->getData());

		// $response = $spot_api->allOrderList();
		// var_dump($response->getData());

		// $response = $spot_api->avgPrice(IndexCtrl::$crypto_pair);
		// var_dump($response->getData());

		// $response = $spot_api->getOpenOrders();
		// var_dump($response->getData());

		// $response = $spot_api->getAccount(true);
		// var_dump($response->getData());

		// $response = $spot_api->klines(IndexCtrl::$crypto_pair, ); ///////////////////
		// var_dump($response->getData());

		die;
	}


	public static function tradesGET (Base $f3, $url, $controler)
	{
		$data = BinanceSpotApi::get_trades_grouped(IndexCtrl::$crypto_pair);
		$f3->set("data", $data);

		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"binance/trades",
			"title"		=>	"Trades " . IndexCtrl::$crypto_pair,
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
	
	public static function testGET (Base $f3, $url, $controler)
	{
		# config
		// $crypto_pair = IndexCtrl::$crypto_pair;
		$base_asset = "SKY";
		$quote_asset = "USDC";
		$crypto_pair = "{$base_asset}{$quote_asset}";
		
		$quote_dust_threashold = 10; # if we have less that threshold €/$ of remaining asset, reset stats (to cancel lost quote)
		
		# get trades
		$data = BinanceSpotApi::get_trades ($crypto_pair);
		
		# init
		$res = [
			$crypto_pair => [
				"entry" => [
					"total_quantity" => 0,
					"total_cost" => 0
				],
				"exit" => [
					"total_quantity" => 0,
					"total_cost" => 0
				],
			]
		];
		
		# treat trades
		foreach ($data as $trade) {
			// var_dump($trade);
			if ($trade ["Symbol"] !== $crypto_pair) {
				throw new ErrorException("wrong symbol found in trade : {$trade ["Symbol"]}");
			}
			echo Binance::timestamp_to_datetime($trade ["Time"]) -> format(Stuff::datetime_sql_format) . " <br/>" . PHP_EOL;
			
			if ($trade ["IsBuyer"] === true) {
				echo "- BUY {$trade ["Qty"]} {$base_asset} @ {$trade ["Price"]} = {$trade ["QuoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res [$crypto_pair] ["entry"] ["total_quantity"] += $trade ["Qty"];
				$res [$crypto_pair] ["entry"] ["total_cost"] += $trade ["QuoteQty"];
				$res [$crypto_pair] ["exit"] ["total_quantity"] = max($res [$crypto_pair] ["exit"] ["total_quantity"] - $trade ["Qty"], 0);
				$res [$crypto_pair] ["exit"] ["total_cost"] = max($res [$crypto_pair] ["exit"] ["total_cost"] - $trade ["QuoteQty"], 0);
			}
			else {
				echo "- SELL {$trade ["Qty"]} {$base_asset} @ {$trade ["Price"]} = {$trade ["QuoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res [$crypto_pair] ["entry"] ["total_quantity"] = max($res [$crypto_pair] ["entry"] ["total_quantity"] - $trade ["Qty"], 0);
				$res [$crypto_pair] ["entry"] ["total_cost"] = max($res [$crypto_pair] ["entry"] ["total_cost"] - $trade ["QuoteQty"], 0);
				$res [$crypto_pair] ["exit"] ["total_quantity"] += $trade ["Qty"];
				$res [$crypto_pair] ["exit"] ["total_cost"] += $trade ["QuoteQty"];
			}
			
			$remaining_entry_quote = $res [$crypto_pair] ["entry"] ["total_quantity"] * $trade ["Price"];
			if ($res [$crypto_pair] ["entry"] ["total_quantity"] != 0) {
				$entry_avg = $res [$crypto_pair] ["entry"] ["total_cost"] / $res [$crypto_pair] ["entry"] ["total_quantity"];
			}
			else {
				$entry_avg = 0;
			}
			
			$remaining_exit_quote = $res [$crypto_pair] ["exit"] ["total_quantity"] * $trade ["Price"];
			if ($res [$crypto_pair] ["exit"] ["total_quantity"] != 0) {
				$exit_avg = $res [$crypto_pair] ["exit"] ["total_cost"] / $res [$crypto_pair] ["exit"] ["total_quantity"];
			}
			else {
				$exit_avg = 0;
			}
			
			echo " entry => " . $res [$crypto_pair] ["entry"] ["total_quantity"] . " {$base_asset} = {$remaining_entry_quote} {$quote_asset} <=> " . $res [$crypto_pair] ["entry"] ["total_cost"] . " {$quote_asset} @ {$entry_avg} <br/>" . PHP_EOL;
			if ($remaining_entry_quote > 0 && $remaining_entry_quote < $quote_dust_threashold) {
				$res [$crypto_pair] ["entry"] ["total_quantity"] = 0;
				$res [$crypto_pair] ["entry"] ["total_cost"] = 0;
				echo " entry dust reset <br/>" . PHP_EOL;
			}
			
			echo " exit => " . $res [$crypto_pair] ["exit"] ["total_quantity"] . " {$base_asset} = {$remaining_exit_quote} {$quote_asset} <=> " . $res [$crypto_pair] ["exit"] ["total_cost"] . " {$quote_asset} @ {$exit_avg} <br/>" . PHP_EOL;
			if ($remaining_exit_quote > 0 && $remaining_exit_quote < $quote_dust_threashold) {
				$res [$crypto_pair] ["exit"] ["total_quantity"] = 0;
				$res [$crypto_pair] ["exit"] ["total_cost"] = 0;
				echo " exit dust reset <br/>" . PHP_EOL;
			}
			
			
			echo "<br/>" . PHP_EOL;
		}
		
		echo "==> entry (buy) avg = {$entry_avg} <br/>" . PHP_EOL;
		echo "==> exit (sell) avg = {$exit_avg} <br/>" . PHP_EOL;
		
		/*
		=> average_entry_price
		total_quantity
		total_cost
		symbol -> base_asset + quote_asset (ETHEUR -> ETH + EUR)
			isBuyer = sens de la transaction
		commission :
			commissionAsset == base_asset -> impacte la quantité
			commissionAsset == quote_asset -> impacte le coput
		=> average_exit_price même algo
		*/
		
		
		die;
		$f3->set("data", $data);

		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"binance/trades",
			"title"		=>	"Trades " . $crypto_pair,
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}

}
