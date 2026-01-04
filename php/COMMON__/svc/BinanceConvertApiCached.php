<?php
namespace COMMON__\svc;

use Cache;
use DateTimeInterface;


class BinanceConvertApiCached
{
	
	#TODO find account start to be able to remove those parameters
	public static function get_trade_history_large (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceConvertApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__" . $start->format(Stuff::date_sql_format) . "__" . $end->format(Stuff::date_sql_format);
		$cache_ttl = 5 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceConvertApi::$cache_function($start, $end);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		
		return $data;
	}
	
}
