<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\svc\Accounting;
use COMMON__\svc\Binance;
use COMMON__\svc\BinanceRestApi;
use COMMON__\svc\BinanceFiatApi;
use COMMON__\svc\BinanceSpotApi;
use COMMON__\svc\BinanceSpotApiCached;
use COMMON__\svc\BreadCrumb;
use COMMON__\svc\Process;




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
		// $spot_api = BinanceSpotApi::get_api();

		# server time
		// $response = $spot_api->time();
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);

		# exchange infos
		// $data = BinanceSpotApi::get_exchange_infos(["ETHEUR", "BNBUSDC"], false);
		// var_dump($data);
		
		# all symbols
		// $data = BinanceSpotApi::get_symbols_cached();
		// var_dump($data);

		# orders
		// $data = BinanceSpotApi::get_orders("ETHUSDC");
		// var_dump($data);

		# order lists
		// $data = BinanceSpotApi::get_order_lists();
		// var_dump($data);
		
		# average price (last 5 minutes)
		// $response = $spot_api->avgPrice(IndexCtrl::$symbol);
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
		// $response = $spot_api->klines(IndexCtrl::$symbol, "1d");
		// $data = Binance::responseData_to_table($response->getData());
		// var_dump($data);
		
		# convert trades
		// $trade_history = BinanceConvertApi::get_trade_history_large(Binance::get_start_date(), new DateTime);
		// var_dump($trade_history);


		// $binance_conf = Binance::get_conf();
		// $builder = SimpleEarnRestApiUtil::getConfigurationBuilder();
		// $builder->apiKey($binance_conf["key"])
		// 		->secretKey($binance_conf["secret"]);
		// $simpleEarnApi = new SimpleEarnRestApi($builder->build());
		
		# Subscriptions
		// $data = $simpleEarnApi->getFlexibleSubscriptionRecord();
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		

		# Rewards (intérêts)
		// $data = $simpleEarnApi->getFlexibleRewardsHistory(
		// 	"ALL" # ALL, BONUS, INTEREST
		// );
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		

		# Rachats
		// $data = $simpleEarnApi->getFlexibleRedemptionRecord();
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		
		
		# LOCKED
		
		
		# Souscriptions
		// $data = $simpleEarnApi->getLockedSubscriptionRecord();
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		
		
		# Rachats / maturité
		// $data = $simpleEarnApi->getLockedRedemptionRecord();
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		
		
		# Rewards
		// $data = $simpleEarnApi->getLockedRewardsHistory();
		// $data = Binance::responseData_to_table($data);
		// var_dump($data ["data"] ["rows"]);
		
		die;
	}


	public static function testGET (Base $f3, $url, $controler) : void
	{
		$p = new Process ("sleep 3");
		$pid = $p->getPid();
		var_dump($pid);

		$p = new Process();
		$p->setPid($pid);
		var_dump($p->status());
		$p->stop();
		var_dump($p->status());
		
		die;
	}
	
	
	public static function dashboardGET (Base $f3, $url, $controler) : void
	{
		$balances = BinanceSpotApi::get_account_balances_consolidated ();
		$f3->set("balances", $balances);
		
		$capital_configs = BinanceRestApi::get_capital_configs_cached ();
		$f3->set("capital_configs", $capital_configs);
		
		$known_assets = BinanceSpotApi::get_known_assets ();
		$balances_assets = array_keys ($balances);
		$assets = array_unique (array_merge ($known_assets, $balances_assets));
		
		$symbols = BinanceSpotApi::get_all_symbols_cached();
		$tickers_query = [];
		$assets_paths = [];
		foreach ($assets as $baseAsset) {
			if ($baseAsset !== Binance::reference_asset) {
				$assets_paths [$baseAsset] = Binance::find_symbol_path_for_assets ($baseAsset, Binance::reference_asset, $symbols); #TODO put in DB (long cache)
				foreach ($assets_paths [$baseAsset] as $path) {
					$tickers_query [] = $path ["symbol"];
				}
			}
		}
		$tickers_query = array_unique ($tickers_query);
		$tickers = BinanceSpotApiCached::get_ticker_prices ($tickers_query);
		
		$assets_reference_price = [];
		foreach ($assets_paths as $baseAsset => $path) {
			$price = 1;
			foreach ($path as $step) {
				if ($step ["direction"] === "normal") {
					$price *= $tickers [$step ["symbol"]] ["price"];
				}
				else {
					$price /= $tickers [$step ["symbol"]] ["price"];
				}
			}
			$assets_reference_price [$baseAsset] = $price; #TODO put in FS cache (small duration)
		}
		$assets_reference_price [Binance::reference_asset] = 1;
		$f3->set("assets_reference_price", $assets_reference_price);
		
		$balance_reference_qty = [];
		foreach ($balances_assets as $baseAsset) {
			$balance_reference_qty [$baseAsset] = $balances [$baseAsset] * $assets_reference_price [$baseAsset];
		}
		arsort($balance_reference_qty);
		$f3->set("balance_reference_qty", $balance_reference_qty);
		
		$trades = Binance::get_all_trades ();
		$accounting = new Accounting ($assets_reference_price);
		$accounting->execute_trades ($trades);
		$f3->set("accounting", $accounting);
		
		$accounting_assets = $accounting->get_accounts_assets ();
		$balance_assets = array_keys ($balance_reference_qty);
		$all_assets_to_display = array_unique (array_merge ($accounting_assets, $balance_assets));
		$top_assets = [BinanceFiatApi::fiat_asset, Binance::reference_asset, Binance::pivot_asset];
		$assets_groups_to_display = [];
		if ($f3->get("binance.env")  === "prod") {
			$assets_groups_to_display ["external"]		= [BinanceFiatApi::fiat_asset, BinanceRestApi::dividendes_asset];
			$assets_groups_to_display ["liquid"]		= [Binance::reference_asset, Binance::pivot_asset];
			$assets_groups_to_display ["balance"]		= array_diff ($balance_assets, $top_assets);
			$assets_groups_to_display ["remaining"]		= array_diff ($all_assets_to_display, $top_assets, $balance_assets);
		}
		else {
			$assets_groups_to_display ["balance"]		= $balance_assets;
			$assets_groups_to_display ["remaining"]		= array_diff ($all_assets_to_display, $balance_assets);
			
		}
		$f3->set("assets_groups_to_display", $assets_groups_to_display);
		
		# try to find stop loss pending orders
		BinanceSpotApi::get_all_orders (); # fetch orders if needed
		$pending_orders = BinanceSpotApi::get_pending_orders_from_db ();
		$pending_orders_by_asset = [];
		foreach ($pending_orders as $order) {
			$symbol_str = $order ["symbol"];
			$symbol = $symbols [$symbol_str];
			$baseAsset = $symbol ["baseAsset"];
			$quoteAsset = $symbol ["quoteAsset"];
			
			$price = $assets_reference_price [$baseAsset];
			$order ["reference_quantity"] = $order ["origQty"] * $price;
			
			$convert_symbol = Binance::find_symbol_for_assets($quoteAsset, Binance::reference_asset, $symbols);
			$price = $tickers [$convert_symbol ["symbol"]] ["price"];
			if ($convert_symbol ["direction"] === "opposite") {
				$price = 1 / $price;
			}
			$order ["reference_stop"] = $order ["price"] * $price;
			
			$pending_orders_by_asset [$baseAsset] [] = $order;
		}
		$f3->set("pending_orders_by_asset", $pending_orders_by_asset);
		
		$breadcrumbs = static::breadcrumbs();
		$breadcrumbs [] = new BreadCrumb ("Dashboard", $f3->get("BASE").$f3->alias("binanceDashboard"), "Dashboard");
		$page = [
			"module"		=> "COMMON__",
			"layout"		=> "default",
			"name"			=> "binance/dashboard",
			"title"			=> "Dashboard",
			"breadcrumbs"	=> $breadcrumbs,
		];
		self::renderPage($page);
	}
	
}
