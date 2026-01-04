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
			if ($data === []) {
				$cache_ttl = 60 * 60; # long cache for symbols without any trade
			}
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	private static function get_all_trades () : array
	{
		$symbols = static::get_possible_symbols();
		
		$res = [];
		foreach ($symbols as $symbol) {
			$trades = static::get_trades($symbol);
			$res = array_merge($res, $trades);
		}
		
		$sort = array_column($res, "time");
		array_multisort($sort, SORT_ASC, SORT_NUMERIC, $res);
		return $res;
	}
	
	public static function get_all_trades_cached () : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApiCached";
		$cache_function = "get_all_trades";
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 1 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApiCached::$cache_function();
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_possible_symbols () : array
	{
		$balances = static::get_account_balances_consolidated();
		$balances_assets = array_keys ($balances);
		
		$all_symbols = BinanceSpotApiCached::get_all_symbols();
		$symbols = [];
		foreach ($balances_assets as $asset) {
			$tmp = static::get_symbols_with_asset ($asset, $all_symbols);
			$symbols = array_merge($symbols, $tmp);
		}
		sort($symbols);
		return array_unique($symbols);
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
	
	
	public static function get_used_symbols_from_order_lists () : array #TODO stop using this
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
	
	
	public static function get_symbols_with_asset (string $asset, array $all_symbols=[]) : array
	{
		if (empty ($all_symbols)) {
			$all_symbols = BinanceSpotApiCached::get_all_symbols();
		}
		
		$res = [];
		foreach ($all_symbols as $symbol) {
			if ($symbol ["baseAsset"] === $asset || $symbol ["quoteAsset"] === $asset) {
				$res [] = $symbol ["symbol"];
			}
		}
		return $res;
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
