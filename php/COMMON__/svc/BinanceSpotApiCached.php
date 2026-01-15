<?php
namespace COMMON__\svc;

use Binance\Client\Spot\Api\SpotRestApi;
use Cache;


class BinanceSpotApiCached
{

	#TODO remove in favor of BinanceSpotApi::get_trades_cached($symbol)
	public static function get_trades (string $symbol, ?SpotRestApi $spot_api = null) : array
	{
		if (empty($spot_api)) {
			$spot_api = BinanceSpotApi::get_api();
		}
		
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__{$symbol}";
		$cache_ttl = 2 * 60;
		$cache_ttl = 15 * 60; /////////////////////////TODO dev remove
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApi::$cache_function($symbol, $spot_api);
			if ($data === []) {
				$cache_ttl = 60 * 60; # long cache for symbols without any trade
				$cache_ttl = 3 * 60 * 60; /////////////////////////TODO dev remove
			}
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	public static function get_account_balances_consolidated () : array #TODO keep
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
	
	public static function get_symbols_from_balance () : array #TODO keep
	{
		$balances = static::get_account_balances_consolidated();
		$balances_assets = array_keys ($balances);
		
		$all_symbols = BinanceSpotApi::get_symbols_cached();
		$symbols = [];
		foreach ($balances_assets as $asset) {
			$tmp = static::get_symbols_with_asset ($asset, $all_symbols);
			$symbols = array_merge($symbols, $tmp);
		}
		sort($symbols);
		return array_unique($symbols);
	}
	
	
	public static function get_symbols_from_order_lists () : array #TODO keep
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


	#TODO code get_possible_symbols with balance + get_symbols_from_order_lists + symbols from orders in cache
	
	
	public static function get_symbols_with_asset (string $asset, array $all_symbols=[]) : array #TODO keep
	{
		if (empty ($all_symbols)) {
			$all_symbols = BinanceSpotApi::get_symbols_cached();
		}
		
		$res = [];
		foreach ($all_symbols as $symbol) {
			if ($symbol ["baseAsset"] === $asset || $symbol ["quoteAsset"] === $asset) {
				$res [] = $symbol ["symbol"];
			}
		}
		return $res;
	}
	
	
	public static function get_ticker_price (array $used_symbols) : array #TODO keep
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__" . base64_encode(json_encode($used_symbols));
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
	
	
	private static function get_all_assets_from_symbols () : array #TODO keep
	{
		$symbols = BinanceSpotApi::get_symbols_cached();
		$base_assets = array_column($symbols, "baseAsset");
		$quote_assets = array_column($symbols, "quoteAsset");
		$assets = array_merge ($base_assets, $quote_assets);
		sort($assets);
		$assets = array_unique($assets);
		return $assets;
	}
	
	public static function get_all_assets_from_symbols_cached () : array #TODO keep
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApiCached";
		$cache_function = "get_all_assets_from_symbols";
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 24 * 60 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApiCached::$cache_function ();
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
	
	/**
	 * guess base and quote assets from symbol
	 * usefull for symbols not listed by exchangeinfo anymore (delisted pair)
	 */
	private static function guess_symbol_assets (string $symbol) : ?array #TODO keep
	{
		$assets = static::get_all_assets_from_symbols_cached ();
		
		foreach ($assets as $base_asset) {
			foreach ($assets as $quote_asset) {
				$tested_symbol = "{$base_asset}{$quote_asset}";
				if ($tested_symbol === $symbol) {
					return ["base_asset" => $base_asset, "quote_asset" => $quote_asset];
				}
			}
		}
		return null;
	}
	
	/**
	 * same as guess_symbol_assets but cached
	 */
	public static function guess_symbol_assets_cached (string $symbol) : ?array #TODO keep
	{
		$cache = Cache::instance();
		$cache_class = "BinanceSpotApiCached";
		$cache_function = "guess_symbol_assets";
		$cache_key = "{$cache_class}__{$cache_function}__{$symbol}";
		$cache_ttl = 24 * 60 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceSpotApiCached::$cache_function($symbol);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		return $data;
	}
	
}
