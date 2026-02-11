<?php
namespace COMMON__\svc;

use COMMON__\mdl\KeyValue;
use COMMON__\mdl\OrderList;
use DateTime;


trait BinanceSpotApiOrder
{
	
	public static function get_order_lists_from_api () : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->allOrderList();
		$data = $response->getData();
		$res = Binance::responseData_to_table($data);
		return $res ["items"];
	}
	
	private static function store_order_lists_into_db (array $data) : void
	{
		foreach ($data as $row) {
			$elt = new OrderList;
			$elt->load (["orderListId = ?", $row ["orderListId"]], []);
			$elt->copyfrom ($row);
			$elt->save ();
		}
	}

	
	private static function get_order_lists_from_db () : array
	{
		return OrderList::getAllFast ("transactionTime");
	}
	
	
	public static function get_order_lists_cached () : array
	{
		$cache_class = "BinanceSpotApi";
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
			$data = static::get_order_lists_from_api ();

			# store them into db
			static::store_order_lists_into_db ($data);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			# get actual data
			$data = static::get_order_lists_from_db ();
		}
		
		return $data;
	}
	
	
	
	
	
	
	
	public static function get_orders (string $symbol) : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->allOrders($symbol);
		$data = $response->getData();
		$res = Binance::responseData_to_table($data);
		return $res ["items"];
	}
	
}
