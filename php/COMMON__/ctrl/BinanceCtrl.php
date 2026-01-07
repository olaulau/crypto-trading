<?php

namespace COMMON__\ctrl;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use COMMON__\svc\Accounting;
use COMMON__\svc\Binance;
use COMMON__\svc\BinanceFiatApi;
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
		$data = Binance::responseData_to_table($response->getData());
		var_dump($data);

		# exchange infos
		// $data = BinanceSpotApi::get_exchange_infos(["ETHEUR", "BNBUSDC"], false);
		// var_dump($data);
		
		# all symbols
		// $data = BinanceSpotApi::get_all_symbols();
		// var_dump($data);

		# orders
		// $response = $spot_api->allOrders(IndexCtrl::$crypto_pair);
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# order lists
		// $data = BinanceSpotApi::get_order_lists();
		// var_dump($data);
		
		# trades stats
		// $data = Binance::get_trades_stats("ETHEUR");
		// var_dump($data);
		
		# used symbols
		// $data = BinanceSpotApi::get_used_symbols_from_order_lists();
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
		// $trade_history = BinanceConvertApi::get_trade_history_large(DateTime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00"), new DateTime);
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
		$fiat_trades = BinanceFiatApi::get_all_trades();
		$fiat_trades = BinanceFiatApi::fiatTrades_to_spotTrades($fiat_trades);
		$accounting = new Accounting();
		$accounting->execute_trades($fiat_trades);
		var_dump($accounting);
		
		die;
	}
	
	
	public static function dashboardGET (Base $f3, $url, $controler)
	{
		$balances = BinanceSpotApiCached::get_account_balances_consolidated();
		// $balance_assets = array_keys($balances);
		#TODO integrate this as source, so that we don't miss EUR
		$used_symbols = BinanceSpotApiCached::get_used_symbols_from_order_lists(); #TODO pas fiable du tout !
		
		$symbols_infos = BinanceSpotApiCached::get_all_symbols();
		$symbols_infos_indexed = Stuff::array_group_by($symbols_infos, "symbol", false);
		
		$ticker_prices = BinanceSpotApiCached::get_ticker_price($used_symbols);
		
		$trades_stats = [];
		foreach ($used_symbols as $symbol) {
			$symbol_infos = $symbols_infos_indexed [$symbol];
			$base_asset = $symbol_infos ["baseAsset"];
			$quote_asset = $symbol_infos ["quoteAsset"];
			
			$balance = $balances [$base_asset] ?? null;
			$quote_balance = isset($balances [$base_asset]) ? ($balances [$base_asset] * $ticker_prices [$symbol] ["price"]) : null;
			
			if ($quote_balance > Binance::quote_dust_threashold) {
				$trade_stats = Binance::get_trades_stats($base_asset, $quote_asset);
				$trades_stats [$symbol] = $trade_stats;
			}
			$trades_stats [$symbol] ["base_asset"] = $base_asset;
			$trades_stats [$symbol] ["quote_asset"] = $quote_asset;
			$trades_stats [$symbol] ["price"] = $ticker_prices [$symbol] ["price"];
			$trades_stats [$symbol] ["balance"] = $balance;
			$trades_stats [$symbol] ["quote_balance"] = $quote_balance;
		}
		
		$sort = array_column($trades_stats, "quote_balance");
		array_multisort($sort, SORT_DESC, SORT_NUMERIC, $trades_stats);
		
		$f3->set("trades_stats", $trades_stats);

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
