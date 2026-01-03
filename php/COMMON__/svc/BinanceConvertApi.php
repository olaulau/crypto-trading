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
		$trade_history_response = $convert_api->getConvertTradeHistory($start->getTimestamp()*1000, $end->getTimestamp()*1000);
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
			$trade_history = static::get_trade_history($current_start, $current_end);
			$res = array_merge($res, $trade_history);
			
			$current_start->add(new DateInterval("P29D"));
			$diff = $current_start->diff($end);
		}
		
		return $res;
	}
		
}
