<?php
namespace COMMON__\svc;

use Binance\Client\Spot\Model\Symbols;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\SpotExchangeSymbol;
use DateTime;


trait BinanceSpotApiExchangeInfos
{
	
	
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
			$elt = new SpotExchangeSymbol;
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
	
}
