<?php
namespace COMMON__\svc;

use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\GetAccountResponse;
use Binance\Client\Spot\Model\Symbols;
use Binance\Client\Spot\Model\TickerPriceResponse;
use Binance\Client\Spot\SpotRestApiUtil;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\SpotExchangeSymbol;
use DateTime;
use ErrorException;


class BinanceSpotApi
{
	use BinanceSpotApiTrade, BinanceSpotApiOrder;
	
	public static function get_api () : SpotRestApi
	{
		$binance_conf = Binance::get_conf ();
		$binance_key = $binance_conf ["key"];
		$binance_secret = $binance_conf ["secret"];
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$configurationBuilder->url($binance_conf ["rest_url"]);
		$api = new SpotRestApi($configurationBuilder->build());

		return $api;
	}
	
	
	public static function get_known_symbols (bool $with_balance = false) : array
	{
		if ($with_balance) {
			$symbols = BinanceSpotApiCached::get_symbols_from_balance (); # too much symbols;
		}
		else {
			$symbols = [];
		}
		$order_lists_symbols = BinanceSpotApiCached::get_symbols_from_order_lists ();
		$spot_trades_symbols = BinanceSpotApi::get_symbols_from_trades ();
		
		$symbols = array_merge ($symbols, $order_lists_symbols, $spot_trades_symbols);
		sort ($symbols);
		$symbols = array_unique ($symbols);
		return $symbols;
	}


	public static function get_known_assets () : array
	{
		$assets = [];
		$symbols = static::get_known_symbols();
		foreach ($symbols as $symbol) {
			$symbol_assets = BinanceSpotApiCached::guess_symbol_assets_cached($symbol);
			$assets [] = $symbol_assets ["base_asset"];
			$assets [] = $symbol_assets ["quote_asset"];
		}
		return array_unique($assets);
	}
	
	
	public static function get_symbols_from_order_lists () : array
	{
		$items = static::get_order_lists_from_api();
		$symbols = array_column($items, "symbol");
		$symbols = array_unique($symbols);
		sort($symbols);
		return $symbols;
	}
	
	
	public static function get_symbols_from_trades () : array
	{
		$trades = static::get_all_trades_from_db ();
		$symbols = array_column($trades, "symbol");
		$symbols = array_unique($symbols);
		sort($symbols);
		return $symbols;
	}
	
	
	public static function get_account () : array
	{
		$spot_api = static::get_api();
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
		$spot_api = static::get_api();
		$s = empty($symbols) ? null : new Symbols ($symbols);
		$response = $spot_api->exchangeInfo(null, $s, null, false);
		$data = Binance::responseData_to_table($response->getData());
		if($keep_symbols_filters === false) {
			foreach ($data ["symbols"] as &$symbol) {
				unset ($symbol ["filters"]);
			}
		}
		return $data;
	}
	
	public static function get_all_symbols_from_api () : array
	{
		$exchange_infos = static::get_exchange_infos ([], false);
		$symbols = $exchange_infos ["symbols"];
		$symbols = array_combine (array_column($symbols, "symbol"), $symbols);
		return $symbols;
	}

	private static function store_symbols_into_db (array $symbols) : void
	{
		foreach ($symbols as $symbol) {
			$elt = new SpotExchangeSymbol();
			$elt->load (["symbol = ?", $symbol ["symbol"]], []);
			$elt->copyfrom ($symbol);
			$elt->save ();
		}
	}

	private static function get_all_symbols_from_db () : array
	{
		$symbols = SpotExchangeSymbol::getAllFast ("symbol");
		$symbols = array_combine (array_column($symbols, "symbol"), $symbols);
		return $symbols;
	}
	
	public static function get_all_symbols_cached () : array
	{
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 60 * 60;
		
		# calculate last_update
		$last_update_o = new KeyValue();
		$last_update_o->load (["key = ?", $cache_key]);
		if(!$last_update_o->dry()) {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# check if we have to query the API to refresh data
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get symbols
			$symbols = static::get_all_symbols_from_api ();

			# store them into db
			static::store_symbols_into_db ($symbols);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			$symbols = static::get_all_symbols_from_db ();
		}
		
		return $symbols;
	}
	
	
	/**
	 * get actual price for each symbols
	 * @param array $symbols
	 * @return array
	 */
	public static function get_ticker_prices (array $symbols) : array
	{
		$symbols = array_unique($symbols);
		sort($symbols);
		
		$spot_api = static::get_api();
		$response = $spot_api->tickerPrice (null, new Symbols ($symbols));
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response2 = $data->getTickerPriceResponse2();
		$res = Binance::responseData_to_table ($response2);
		$res = Stuff::array_group_by($res ["items"], "symbol", false);
		return $res;
	}
	
	/**
	 * same for only one symbol
	 */
	public static function get_ticker_price (string $symbol) : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->tickerPrice ($symbol);
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response1 = $data->getTickerPriceResponse1();
		$res = Binance::responseData_to_table ($response1);
		return $res;
	}
	
}
