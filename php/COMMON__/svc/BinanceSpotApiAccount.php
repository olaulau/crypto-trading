<?php
namespace COMMON__\svc;

use COMMON__\mdl\Balance;
use COMMON__\mdl\KeyValue;
use DateTime;


trait BinanceSpotApiAccount
{
	
	public static function get_account_from_api () : array
	{
		$spot_api = BinanceSpotApi::get_api ();
		$response = $spot_api->getAccount (true);
		$data = $response->getData();
		$res = Binance::responseData_to_table ($data);
		return $res;
	}
	
	
	public static function get_account_balances_from_api () : array
	{
		$account = static::get_account_from_api ();
		$balances = $account ["balances"];
		$res = Stuff::array_group_by ($balances, "asset", false);
		return $res;
	}
	
	
	private static function store_balances_into_db (array $balances) : void
	{
		$now = new DateTime;
		foreach ($balances as $balance) {
			$elt = new Balance ();
			$elt->load (["asset = ?", $balance ["asset"]], []);
			$elt->copyfrom ($balance);
			$elt->lastUpdated = $now;
			$elt->save ();
		}
	}

	private static function get_all_balances_from_db () : array
	{
		$res = Balance::getAllFast ("asset");
		$res = array_combine (array_column($res, "asset"), $res);
		return $res;
	}
	
	public static function get_all_balances_cached () : array
	{
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 60 * 60;
		
		# calculate last_update
		$last_update_o = new KeyValue ();
		$last_update_o->load (["key = ?", $cache_key]);
		if(!$last_update_o->dry()) {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# check if we have to query the API to refresh data
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get data
			$res = static::get_account_balances_from_api ();

			# store them into db
			static::store_balances_into_db ($res);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			$res = static::get_all_balances_from_db ();
		}
		
		return $res;
	}
	
	
	public static function get_all_balances_consolidated () : array
	{
		$balances = static::get_all_balances_cached ();
		
		$res = [];
		foreach ($balances as $balance) {
			$sum = $balance ["free"] + $balance ["locked"];
			if ($sum > 0) {
				$res [$balance ["asset"]] = $sum;
			}
		}
		
		return $res;
	}
	
}
