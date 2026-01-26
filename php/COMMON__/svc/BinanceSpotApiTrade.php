<?php
namespace COMMON__\svc;

use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Common\Dtos\ApiResponse;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\SpotTrade;
use DateTime;


trait BinanceSpotApiTrade
{
	
	public static function get_trades_from_api (string $symbol, ?SpotRestApi $spot_api = null) : array
	{
		if (empty($spot_api)) {
			$spot_api = static::get_api();
		}
		$response = $spot_api->myTrades($symbol, null, null, null, null, null, Binance::recv_window); /** @var ApiResponse $response */
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
		$cache_ttl_short = 5 * 60;
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
			
			# fix boolean support
			foreach ($trades as &$trade) {
				static::api_trade_to_db_trade($trade);
			}

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
	 * convert boolean to tiny int
	 */
	public static function api_trade_to_db_trade (array &$trade) : void
	{
		$trade ["isBuyer"] = $trade ["isBuyer"] === true ? 1 : 0;
		$trade ["isMaker"] = $trade ["isMaker"] === true ? 1 : 0;
		$trade ["isBestMatch"] = $trade ["isBestMatch"] === true ? 1 : 0;
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
				$symbols = BinanceSpotApi::get_known_symbols ();
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
	
	
	
	/**
	 * query all trades from DB at once (fast)
	 */
	public static function get_all_trades_from_db () : array
	{
		return SpotTrade::getAllFast("time");
	}
	
	
	public static function get_trades_grouped (string $symbol) : array
	{
		$data = static::get_trades_from_api($symbol);
		$data = Stuff::array_group_by($data, "orderListId");
		return $data;
	}
	
}
