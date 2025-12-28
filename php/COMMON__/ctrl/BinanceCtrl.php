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
		// $response = $spot_api->time();
		// var_dump($response->getData());

		# infos about symbols
		// $response = $spot_api->exchangeInfo(null, ["ETHEUR", "BNBEUR"], null, false);
		// var_dump($response->getData());

		# orders
		// $response = $spot_api->allOrders(IndexCtrl::$crypto_pair);
		// var_dump($response->getData());

		# order lists
		// $response = $spot_api->allOrderList();
		// var_dump($response->getData());

		# symbol current price
		// $response = $spot_api->avgPrice(IndexCtrl::$crypto_pair);
		// var_dump($response->getData());

		# open orders
		// $response = $spot_api->getOpenOrders();
		// var_dump($response->getData());

		# account info
		# container -> balances = my assets (base)
		// $response = $spot_api->getAccount(true);
		// var_dump($response->getData());

		# klines
		// $response = $spot_api->klines(IndexCtrl::$crypto_pair, "1d");
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
		$res = BinanceSpotApi::get_used_symbols_from_order_lists();
		var_dump($res);
		
		die;
	}

}
