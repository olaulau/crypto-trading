<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\GetAccountResponse;
use Binance\Client\Spot\SpotRestApiUtil;
use Binance\Common\Dtos\ApiResponse;
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
	
	
	public static function get_trades (string $symbol, ?SpotRestApi $spot_api = null) : array
	{
		if (empty($spot_api)) {
			$spot_api = static::get_spot_api();
		}
		$response = $spot_api->myTrades($symbol); /** @var ApiResponse $response */
		$data = Binance::responseData_to_table($response->getData());
		return $data ["items"];
	}
	
	
	public static function get_trades_grouped (string $symbol) : array
	{
		$data = static::get_trades($symbol);
		$data = Stuff::array_group_by($data, "orderListId");
		return $data;
	}
	
	
	
	public static function get_order_lists () : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->allOrderList();
		$items = $response->getData()->getItems();
		return $items;
	}
	
	public static function get_used_symbols_from_order_lists () : array
	{
		$items = static::get_order_lists();
		$symbols = [];
		foreach ($items as $item) { /** @var AllOrderListResponseInner $item */
			$symbol = $item->getSymbol();
			if (!in_array($symbol, $symbols)) {
				$symbols [] = $symbol;
			}
		}
		return $symbols;
	}
	
	
	public static function get_orders () : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->allOrders(""); #TODO doesn't work
		$items = $response->getData()->getItems();
		return $items;
	}
	
	public static function get_used_symbols_from_orders () : array
	{
		$items = static::get_orders(); #TODO so this doesn't really exists neither
		$symbols = [];
		foreach ($items as $item) { /** @var AllOrderListResponseInner $item */
			$symbol = $item->getSymbol();
			if (!in_array($symbol, $symbols)) {
				$symbols [] = $symbol;
			}
		}
		return $symbols;
	}
	
	
	public static function get_account () : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->getAccount(true);
		$data = $response->getData(); /** @var GetAccountResponse $data */
		$res = Binance::responseData_to_table($data);
		return $res;
	}
	
	public static function get_account_balances () : array
	{
		$account = static::get_account();
		$balances = $account ["balances"];
		$res = Stuff::array_group_by($balances, "asset", false);
		return $res;
	}
	
	public static function get_account_balances_consolidated () : array
	{
		$account = static::get_account();
		$balances = $account ["balances"];
		$res = [];
		foreach ($balances as $balance) {
			$res [$balance ["asset"]] = $balance ["free"] + $balance ["locked"];
		}
		return $res;
	}
	
	
	public static function get_exchange_infos (array $symbols, bool $keep_symbols_filters = true) : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->exchangeInfo(null, $symbols, null, false);
		$data = Binance::responseData_to_table($response->getData());
		if($keep_symbols_filters === false) {
			foreach ($data ["symbols"] as &$symbol) {
				unset ($symbol ["filters"]);
			}
		}
		return $data;
	}
	
	public static function get_all_symbols () : array
	{
		$exchange_infos = static::get_exchange_infos([], false);
		$symbols = $exchange_infos ["symbols"];
		$symbols = array_combine (array_column($symbols, "symbol"), $symbols);
		return $symbols;
	}
	
	
	public static function get_ticker_price (array $symbols) : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->tickerPrice (null, $symbols);
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response2 = $data->getTickerPriceResponse2();
		$res = Binance::responseData_to_table ($response2);
		$res = Stuff::array_group_by($res ["items"], "symbol", false);
		return $res;
	}
	
}
