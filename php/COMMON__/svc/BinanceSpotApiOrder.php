<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\Order;
use COMMON__\mdl\OrderList;
use DateTime;
use DB\SQL;

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
			# get orderLists
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
	
	
	
	public static function get_orders_from_api (string $symbol, SpotRestApi $spot_api) : array
	{
		if (empty($spot_api)) {
			$spot_api = BinanceSpotApi::get_api();
		}
		$response = $spot_api->allOrders($symbol);
		$data = $response->getData();
		$res = Binance::responseData_to_table($data);
		return $res ["items"];
	}
	
	public static function store_orders_into_db (array $data) : void
	{
		foreach ($data as $row) {
			$elt = new Order;
			$elt->load (["orderId = ?", $row ["orderId"]], []);
			$elt->copyfrom ($row);
			$elt->save ();
		}
	}

	
	public static function get_orders_from_db (string $symbol) : array
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		$sql = "
			SELECT *
			FROM `" . Order::table . "`
			WHERE symbol = ?
			ORDER BY time ASC
		";
		$params = [$symbol];
		$res = $db->exec($sql, $params);
		
		return $res;
	}
	
	
	public static function get_orders_cached (string $symbol, SpotRestApi $spot_api) : array
	{
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__{$symbol}__last_update";
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
			# get orders
			$data = static::get_orders_from_api ($symbol, $spot_api);

			# store them into db
			static::store_orders_into_db ($data);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			# get actual data
			$data = static::get_orders_from_db ($symbol);
		}
		
		return $data;
	}
	
	
	
	/**
	 * query all orders, query depends of cache ttl :
	 * - very short : get_all_orders_from_db
	 * - short : only query known symbols
	 * - long : query all (including those without any trade)
	 */
	public static function get_all_orders () : array
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
				$orders = BinanceSpotApi::get_all_orders_from_db ();
				return $orders;
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
			$orders = BinanceSpotApi::get_orders_cached ($symbol, $spot_api);
			$res = array_merge ($res, $orders);
		}
		
		$sort = array_column ($res, "time");
		array_multisort ($sort, SORT_ASC, SORT_NUMERIC, $res);
		
		# store last update
		$last_update_o->key = $cache_key;
		$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
		$last_update_o->save();
		
		return $res;
	} #TODO use in dashboard, to show TP/SL
	
	
	
	/**
	 * query all orders from DB at once (fast)
	 */
	public static function get_all_orders_from_db () : array
	{
		return Order::getAllFast("time");
	}
	
	
	public static function get_orders_grouped (string $symbol) : array
	{
		$data = static::get_orders_from_api($symbol);
		$data = Stuff::array_group_by($data, "orderListId");
		return $data;
	}
	
	
	#TODO force refresh of orders from api if needed
	public static function get_pending_orders_from_db ()
	{
		$order_wrapper = new Order;
		$data = $order_wrapper->find(["status = ?", "NEW"], []);
		return $data->castAll();
	}
	
}
