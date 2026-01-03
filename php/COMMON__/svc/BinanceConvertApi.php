<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Convert\Api\ConvertRestApi;
use Binance\Client\Convert\ConvertRestApiUtil;
use DateInterval;
use DateTime;
use DateTimeInterface;
use ErrorException;


class BinanceConvertApi
{
	
	public static function get_convert_api () : ConvertRestApi
	{
		$f3 = Base::instance();
		
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}
		
		$configurationBuilder = ConvertRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$convert_api = new ConvertRestApi($configurationBuilder->build());
		
		return $convert_api;
	}
	
	
	public static function get_trade_history (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$diff = $start->diff($end);
		if($diff->days > 30) {
			throw new ErrorException("convert trade history can't query more that 30 days");
		}
		
		$convert_api = BinanceConvertApi::get_convert_api();
		$trade_history_response = $convert_api->getConvertTradeHistory ($start->getTimestamp()*1000, $end->getTimestamp()*1000);
		$res = Binance::responseData_to_table($trade_history_response);
		return $res ["data"] ["list"];
	}
	
	
	public static function get_trade_history_large (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$current_start = DateTime::createFromInterface ($start);
		
		$diff = $current_start->diff($end);
		$res = [];
		while ($diff->invert === 0) {
			$current_end = clone $current_start;
			$current_end->add(new Dateinterval("P29D"));
			$trade_history = static::get_trade_history ($current_start, $current_end);
			$res = array_merge($res, $trade_history);
			
			$current_start->add(new DateInterval("P29D"));
			$diff = $current_start->diff($end);
		}
		
		return $res;
	}
	
	
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
	
	
	
		/*
		
    array (size=13)
      'symbol' => string 'DOGEEUR' (length=7)
      'id' => int 34052421
      'orderId' => int 1249907506
      'orderListId' => int -1
      'price' => string '0.12144000' (length=10)
      'qty' => string '157.00000000' (length=12)
      'quoteQty' => string '19.06608000' (length=11)
      'commission' => string '0.01811278' (length=10)
      'commissionAsset' => string 'EUR' (length=3)
      'time' => int 1767440343749
      'isBuyer' => boolean false
      'isMaker' => boolean false
      'isBestMatch' => boolean true

    array (size=10)
      'quoteId' => string 'edd0292137c6442ead5c8413a584afb8' (length=32)
      'orderId' => int 2119578317765251804
      'orderStatus' => string 'SUCCESS' (length=7)
      'fromAsset' => string 'ETH' (length=3)
      'fromAmount' => string '0.0268731' (length=9)
      'toAsset' => string 'EUR' (length=3)
      'toAmount' => string '93.95735156' (length=11)
      'ratio' => string '3496.33' (length=7)
      'inverseRatio' => string '0.000286014' (length=11)
      'createTime' => int 1761487392869
	  
	  */
		
}
