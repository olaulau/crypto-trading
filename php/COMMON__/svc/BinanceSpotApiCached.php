<?php
namespace COMMON__\svc;

use Cache;


class BinanceSpotApiCached
{
	
	public static function get_trades (string $symbol) : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__{$symbol}";
		$cache_ttl = 2 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function($symbol);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_account_balances_consolidated () : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 1 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function();
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_used_symbols_from_order_lists () : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 1 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function();
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_all_symbols () : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 24 * 60 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function();
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_ticker_price (array $used_symbols) : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__" . serialize($used_symbols);
		$cache_ttl = 15;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function($used_symbols);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
}
