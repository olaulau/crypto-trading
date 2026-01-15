<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\AllOrderListResponseInner;
use Binance\Client\Spot\Model\GetAccountResponse;
use Binance\Client\Spot\Model\TickerPriceResponse;
use Binance\Client\Spot\SpotRestApiUtil;
use Binance\Common\Dtos\ApiResponse;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\SpotExchangeSymbol;
use COMMON__\mdl\SpotTrade;
use DateTime;
use ErrorException;


class BinanceSpotApi
{
	
	public static function get_api () : SpotRestApi
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
	
	
	public static function get_trades_from_api (string $symbol, ?SpotRestApi $spot_api = null) : array
	{
		if (empty($spot_api)) {
			$spot_api = static::get_api();
		}
		$response = $spot_api->myTrades($symbol); /** @var ApiResponse $response */
		$data = Binance::responseData_to_table($response->getData());
		return $data ["items"];
	}

	private static function store_trades_into_db (array $trades) : void
	{
		foreach ($trades as $trade) {
			$ft = new SpotTrade();
			$ft->load (["id = ?", $trade ["id"]], []);
			$ft->copyfrom ($trade);
			$ft->save ();
		}
	}

	private static function get_trades_from_db (string $symbol) : array
	{
		$ft_wrapper = new SpotTrade();
		$trades = $ft_wrapper->find(["symbol = ?", $symbol], ["order" => "time ASC"]);
		return $trades->castAll();
	}
	
	public static function get_trades_cached (string $symbol, ?SpotRestApi $spot_api=null) : array
	{
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__{$symbol}__last_update";
		$cache_ttl_short = 60 * 60;
		$cache_ttl_long = 24 * 60 * 60;
		
		# get actual data
		$trades = static::get_trades_from_db ($symbol);
		
		# calculate last_update
		$last_update_o = new KeyValue();
		$last_update_o->load (["key = ?", $cache_key]);
		if($last_update_o->dry()) {
			# use last trade date
			$last_trade = end ($trades);
			$last_update_dt = null;
			if (!empty($last_trade)) {
				$last_update_dt = DateTime::createFromTimestamp ($last_trade ["createTime"]/1000);
			}
		}
		else {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# symbols with no past trades may not be queried so often
		$cache_ttl = $cache_ttl_short;
		if (empty($trades)) {
			$cache_ttl = $cache_ttl_long;
		}

		# check if we have to query the API to refresh data
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get trades
			$trades = static::get_trades_from_api ($symbol, $spot_api);

			# store them into db
			static::store_trades_into_db ($trades);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		
		return $trades;
	}


	/**
	 * query all trades, query depends of cache ttl :
	 * - very short : get_all_trades_from_db
	 * - short : only query known symbols
	 * - long : query all (including those without any trade)
	 */
	public static function get_all_trades () : array
	{
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl_short = 15;
		$cache_ttl_long = 24 * 60 * 60;
		
		# calculate last_update
		$last_update_o = new KeyValue();
		$last_update_o->load (["key = ?", $cache_key]);
		if(!$last_update_o->dry()) {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# check if we have to query the API to refresh data
		if (!empty($last_update_dt)) {
			if ((time() - $last_update_dt->getTimestamp()) < $cache_ttl_short) {
				# get direct from db
				$trades = BinanceSpotApi::get_all_trades_from_db ();
				return $trades;
			}
			elseif ((time() - $last_update_dt->getTimestamp()) < $cache_ttl_long) {
				$symbols = static::get_known_symbols ();
			}
			else { # > $cache_ttl_long
				$symbols = BinanceSpotApi::get_all_symbols_cached ();
				$symbols = array_keys($symbols);
			}
		}
		else {
			$symbols = BinanceSpotApi::get_all_symbols_cached ();
			$symbols = array_keys($symbols);
		}
		
		# query
		$spot_api = BinanceSpotApi::get_api ();
		$res = [];
		foreach ($symbols as $symbol) {
			$trades = BinanceSpotApi::get_trades_cached ($symbol, $spot_api);
			$res = array_merge ($res, $trades);
		}
		
		$sort = array_column ($res, "time");
		array_multisort ($sort, SORT_ASC, SORT_NUMERIC, $res);
		
		# store last update
		$last_update_o->key = $cache_key;
		$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
		$last_update_o->save();
		
		return $res;
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
	
	
	/**
	 * query all trades from DB at once (fast)
	 */
	public static function get_all_trades_from_db () : array
	{
		return SpotTrade::getAll("time")->castAll();
	}
	
	
	public static function get_trades_grouped (string $symbol) : array
	{
		$data = static::get_trades_from_api($symbol);
		$data = Stuff::array_group_by($data, "orderListId");
		return $data;
	}
	
	
	public static function get_order_lists () : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->allOrderList();
		$items = $response->getData()->getItems();
		return $items;
	}
	
	public static function get_symbols_from_order_lists () : array
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
	
	
	public static function get_symbols_from_trades () : array
	{
		$trades = static::get_all_trades_from_db ();
		$symbols = array_column($trades, "symbol");
		sort($symbols);
		$symbols = array_unique($symbols);
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
		$response = $spot_api->exchangeInfo(null, $symbols, null, false);
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
		$symbols = SpotExchangeSymbol::getAll_fast ();
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
	public static function get_ticker_price (array $symbols) : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->tickerPrice (null, $symbols);
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response2 = $data->getTickerPriceResponse2();
		$res = Binance::responseData_to_table ($response2);
		$res = Stuff::array_group_by($res ["items"], "symbol", false);
		return $res;
	}
	
}
