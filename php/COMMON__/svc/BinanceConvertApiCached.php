<?php
namespace COMMON__\svc;

use Cache;
use DateTimeInterface;


class BinanceConvertApiCached
{
	
	public static function get_trade_history_large (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$cache = Cache::instance();
		$cache_key = "BinanceConvertApi__get_trade_history_large__" . $start->format(Stuff::date_sql_format) . "__" . $end->format(Stuff::date_sql_format);
		$cache_ttl = 5 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = BinanceConvertApi::get_trade_history_large($start, $end);
			$cache->set($cache_key, $data, $cache_ttl);
		}
		else {
			$data = $cache->get($cache_key);
		}
		
		return $data;
	}
	
	/*
	public static function get_trade_history_large_for_symbol (DateTimeInterface $start, DateTimeInterface $end, string $symbol) : array
	{
		$trades = static::get_trade_history_large($start, $end);
		$res = [];
		foreach ($trades as $trade) {
			$trade_symbol = $trade ["fromAsset"] . $trade ["toAsset"];
			if ($trade_symbol === $symbol) {
				$res [] = $trade;
			}
		}
		return $res;
	}
	
	
	public static function conversionTrades_to_spotTrades (array $convertion_trades) : array
	{
		$res = [];
		foreach ($convertion_trades as $convertion_trade) {
			if ($convertion_trade ["toAsset"] === "EUR") {
				$base_asset = $convertion_trade ["fromAsset"];
				$quote_asset = $convertion_trade ["toAsset"];
				$is_buyer = false;
			}
			else {
				$base_asset = $convertion_trade ["toAsset"];
				$quote_asset = $convertion_trade ["fromAsset"];
				$is_buyer = true;
			}
			$symbol = "{$base_asset}{$quote_asset}";
			
			$res [] = [
				'symbol'			=> $symbol, #TODO may not be a valid pair (especially converting from a crypto to another one)
				'id'				=> $convertion_trade ["quoteId"],
				'orderId'			=> $convertion_trade ["orderId"],
				'orderListId'		=> -1,
				'price'				=> $convertion_trade ["ratio"],
				'qty'				=> $convertion_trade ["fromAmount"],
				'quoteQty'			=> $convertion_trade ["toAmount"],
				'commission'		=> 0, 
				'commissionAsset'	=> 'EUR',
				'time'				=> $convertion_trade ["createTime"],
				'isBuyer'			=> $is_buyer,
				'isMaker'			=> false,
				'isBestMatch'		=> true,
			];
		} 
		return $res;
	}
	*/	
}
