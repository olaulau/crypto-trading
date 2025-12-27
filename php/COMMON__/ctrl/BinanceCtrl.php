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
					"quantity"	=> 0,
					"cost"		=> 0,
					"quote"		=> 0,
					"avg"		=> 0,
				],
				"exit" => [
					"quantity"	=> 0,
					"cost"		=> 0,
					"quote"		=> 0,
					"avg"		=> 0,
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
				$res [$crypto_pair] ["entry"] ["quantity"] += $trade ["Qty"];
				$res [$crypto_pair] ["entry"] ["cost"] += $trade ["QuoteQty"];
				$res [$crypto_pair] ["exit"] ["quantity"] = max($res [$crypto_pair] ["exit"] ["quantity"] - $trade ["Qty"], 0);
				$res [$crypto_pair] ["exit"] ["cost"] = max($res [$crypto_pair] ["exit"] ["cost"] - $trade ["QuoteQty"], 0);
			}
			else {
				echo "- SELL {$trade ["Qty"]} {$base_asset} @ {$trade ["Price"]} = {$trade ["QuoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
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
			
			echo " entry => " . $res [$crypto_pair] ["entry"] ["quantity"] . " {$base_asset} = {$res [$crypto_pair] ["entry"] ["quote"]} {$quote_asset} <=> "
				 . $res [$crypto_pair] ["entry"] ["cost"] . " {$quote_asset} @ {$res [$crypto_pair] ["entry"] ["avg"]} <br/>" . PHP_EOL;
			if ($res [$crypto_pair] ["entry"] ["quote"] > 0 && $res [$crypto_pair] ["entry"] ["quote"] < $quote_dust_threashold) {
				$res [$crypto_pair] ["entry"] ["quantity"] = 0;
				$res [$crypto_pair] ["entry"] ["cost"] = 0;
				echo " entry dust reset <br/>" . PHP_EOL;
			}
			
			echo " exit => " . $res [$crypto_pair] ["exit"] ["quantity"] . " {$base_asset} = {$res [$crypto_pair] ["exit"] ["quote"]} {$quote_asset} <=> "
				 . $res [$crypto_pair] ["exit"] ["cost"] . " {$quote_asset} @ {$res [$crypto_pair] ["exit"] ["avg"]} <br/>" . PHP_EOL;
			if ($res [$crypto_pair] ["exit"] ["quote"] > 0 && $res [$crypto_pair] ["exit"] ["quote"] < $quote_dust_threashold) {
				$res [$crypto_pair] ["exit"] ["quantity"] = 0;
				$res [$crypto_pair] ["exit"] ["cost"] = 0;
				echo " exit dust reset <br/>" . PHP_EOL;
			}
			
			echo "<br/>" . PHP_EOL;
		}
		
		echo "==> entry (buy) avg = {$res [$crypto_pair] ["entry"] ["avg"]} <br/>" . PHP_EOL;
		echo "==> exit (sell) avg = {$res [$crypto_pair] ["exit"] ["avg"]} <br/>" . PHP_EOL;
		
		/*
		=> average_entry_price
		quantity
		cost
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
