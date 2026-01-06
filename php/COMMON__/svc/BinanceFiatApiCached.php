<?php
namespace COMMON__\svc;

use Cache;
use DateTimeInterface;


class BinanceFiatApiCached
{
	
	
	private static function get_deposit_withdraw_history_large (int $transaction_type, DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceFiatApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__{$transaction_type}__{$start->format(Stuff::date_sql_format)}__{$end->format(Stuff::date_sql_format)}";
		$cache_ttl = 60 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceFiatApi::$cache_function ($transaction_type, $start, $end);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	public static function get_deposit_history_large (DateTimeInterface $start, DateTimeInterface $end) : array {
		return static::get_deposit_withdraw_history_large (0, $start, $end);
	}
	
	public static function get_withdraw_history_large (DateTimeInterface $start, DateTimeInterface $end) : array {
		return static::get_deposit_withdraw_history_large (1, $start, $end);
	}
	
	#TODO fiatTrades_to_spotTrades
	
}
