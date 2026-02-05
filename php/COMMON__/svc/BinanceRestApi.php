<?php
namespace COMMON__\svc;

use COMMON__\mdl\CapitalConfig;
use COMMON__\mdl\KeyValue;
use DateTime;


class BinanceRestApi
{
	
	#TODO refactore custom rest query send

	public static function get_capital_configs_from_api () : array
	{
		$query = http_build_query([
			'timestamp' => (int)(microtime(true) * 1000),
			'recvWindow' => 5000
		]);
		$binance_conf = Binance::get_conf();
		$signature = hash_hmac('sha256', $query, $binance_conf ["secret"]);
		$url = $binance_conf ["rest_url"] . '/sapi/v1/capital/config/getall' . '?' . $query . '&signature=' . $signature;
		
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_HTTPHEADER => [
				'X-MBX-APIKEY: ' . $binance_conf ["key"],
			],
			CURLOPT_RETURNTRANSFER => true
		]);
		
		$response = curl_exec ($ch);
		$data = json_decode ($response, true);
		return $data;
	}

	private static function store_capital_configs_into_db (array $configs) : void
	{
		foreach ($configs as $config) {
			$cc = new CapitalConfig;
			$cc->load (["coin = ?", $config ["coin"]], []);
			$cc->copyfrom ($config);
			$cc->save ();
		}
	}

	private static function get_capital_configs_from_db () : array
	{
		return CapitalConfig::getAllFast ("coin");
	}
	
	public static function get_capital_configs_cached () : array
	{
		$cache_class = "BinanceCustomApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 24 * 60 * 60;
		
		# calculate last_update
		$last_update_o = new KeyValue;
		$last_update_o->load (["key = ?", $cache_key]);
		if(!$last_update_o->dry()) {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# check if we have to query the API to refresh data
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get trades
			$configs = static::get_capital_configs_from_api ();

			# store them into db
			static::store_capital_configs_into_db ($configs);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			# get actual data
			$configs = static::get_capital_configs_from_db ();
		}
		
		$configs = array_combine (array_column ($configs, "coin"),  $configs);
		return $configs;
	}
	
}
