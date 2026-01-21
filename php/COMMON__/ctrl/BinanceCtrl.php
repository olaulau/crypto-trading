<?php

namespace COMMON__\ctrl;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use COMMON__\svc\Accounting;
use COMMON__\svc\Binance;
use COMMON__\svc\BinanceConvertApi;
use COMMON__\svc\BinanceSpotApi;
use COMMON__\svc\BinanceSpotApiCached;
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
			throw new ErrorException("no binance api key / secret provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$spot_api = new SpotRestApi($configurationBuilder->build());

		# server time
		$response = $spot_api->time();
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# exchange infos
		// $data = BinanceSpotApi::get_exchange_infos(["ETHEUR", "BNBUSDC"], false);
		// var_dump($data);
		
		# all symbols
		// $data = BinanceSpotApi::get_symbols_cached();
		// var_dump($data);

		# orders
		// $response = $spot_api->allOrders(IndexCtrl::$crypto_pair);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# order lists
		// $data = BinanceSpotApi::get_order_lists();
		// var_dump($data);
		
		# average price (last 5 minutes)
		// $response = $spot_api->avgPrice(IndexCtrl::$crypto_pair);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);
		
		# ticker price
		// $data = BinanceSpotApi::get_ticker_price(["ETHEUR", "BNBEUR"]);
		// var_dump($data);

		# open orders
		// $response = $spot_api->getOpenOrders();
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# account info
		# container -> balances = my assets (base)
		// $data = BinanceSpotApi::get_account();
		// var_dump($data);
		
		# klines
		// $response = $spot_api->klines(IndexCtrl::$crypto_pair, "1d");
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);
		
		# convert trades
		// $trade_history = BinanceConvertApi::get_trade_history_large(Binance::get_start_date(), new DateTime);
		// var_dump($trade_history);
		
		die;
	}


	public static function tradesGET (Base $f3, $url, $controler)
	{
		// $symbol = IndexCtrl::$crypto_pair;
		$symbol = "DOGEEUR"; ///////////////////
		$data = BinanceSpotApi::get_trades_grouped($symbol);
		$f3->set("data", $data);

		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"binance/trades",
			"title"		=>	"Trades {$symbol}",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
	
	public static function testGET (Base $f3, $url, $controler)
	{
		
		
		die;
	}
	
	
	public static function dashboardGET (Base $f3, $url, $controler)
	{
		$balances = BinanceSpotApiCached::get_account_balances_consolidated ();
		$f3->set("balances", $balances);
		
		$symbols = BinanceSpotApi::get_all_symbols_cached();
		$balances_assets = array_keys ($balances);
		$tickers_query = [];
		$assets_paths = [];
		foreach ($balances_assets as $asset) {
			if ($asset !== Binance::reference_asset) {
				$assets_paths [$asset] = Binance::find_symbol_path_for_assets($asset, Binance::reference_asset, $symbols);
				foreach ($assets_paths [$asset] as $path) {
					$tickers_query [] = $path ["symbol"];
				}
			}
		}
		$tickers = BinanceSpotApiCached::get_ticker_prices ($tickers_query); #TODO query also assets with past trades but no balance
		
		$assets_reference_price = [];
		foreach ($assets_paths as $asset => $path) {
			$price = 1;
			foreach ($path as $step) {
				if ($step ["direction"] === "normal") {
					$price *= $tickers [$step ["symbol"]] ["price"];
				}
				else {
					$price /= $tickers [$step ["symbol"]] ["price"];
				}
			}
			$assets_reference_price [$asset] = $price;
		}
		$assets_reference_price [Binance::reference_asset] = 1;
		$f3->set("assets_reference_price", $assets_reference_price);
		
		$balance_reference_qty = [];
		foreach ($balances_assets as $asset) {
			$balance_reference_qty [$asset] = $balances [$asset] * $assets_reference_price [$asset];
		}
		$f3->set("balance_reference_qty", $balance_reference_qty);
		
		$trades = Binance::get_all_trades ();
		$accounting = new Accounting;
		$accounting->execute_trades($trades);
		$f3->set("accounting", $accounting);
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"binance/dashboard",
			"title"		=>	"Dashboard",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
}
