<?php

namespace COMMON__\ctrl;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\Symbols;
use Binance\Client\Spot\SpotRestApiUtil;
use COMMON__\mdl\Kline;
use COMMON__\svc\Binance;
use COMMON__\svc\BinanceSpotApi;
use COMMON__\svc\Stuff;
use ErrorException;


class BinanceCtrl extends PrivateCtrl
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

		# server time
		$response = $spot_api->time();
		$data = Binance::responseData_to_table($response->getData());
		var_dump($data);

		# infos about symbols
		// $response = $spot_api->exchangeInfo(null, ["ETHEUR", "BNBEUR"], null, false);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# orders
		// $response = $spot_api->allOrders(IndexCtrl::$crypto_pair);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# order lists
		// $data = BinanceSpotApi::get_order_lists();
		// var_dump($data);
		
		# trades stats
		// $data = BinanceSpotApi::get_trades_stats("ETH", "EUR");
		// var_dump($data);
		
		# used symbols
		// $data = BinanceSpotApi::get_used_symbols_from_order_lists();
		// var_dump($data);

		# symbol current price
		// $response = $spot_api->avgPrice(IndexCtrl::$crypto_pair);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# open orders
		// $response = $spot_api->getOpenOrders();
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# account info
		# container -> balances = my assets (base)
		// $data = Binance::get_account();
		// var_dump($data);

		# klines
		// $response = $spot_api->klines(IndexCtrl::$crypto_pair, "1d");
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);
		
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
		$res = BinanceSpotApi::get_account();
		var_dump($res);
		
		die;
	}

}
