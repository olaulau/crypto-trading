<?php
namespace COMMON__\svc;

use ArrayAccess;
use Base;
use Binance\Common\Dtos\ModelInterface;
use DateTime;
use DateTimeInterface;
use ErrorException;
use ReflectionObject;


class Binance
{
	
	public final const reference_asset = "EUR";
	
	public final const quote_dust_threashold = 10;
	
	public final static $kline_format = [
		"open_time",
		"open",
		"high",
		"low",
		"close",
		"volume",
		"close_time",
		"quote_asset_volume",
		"number_of_trades",
		"taker_buy_base_asset_volume",
		"taker_buy_quote_asset_volume",
		"ignore",
	];
	
	public final static $candles = [
		"1s"	 => 1,
		"1m"	 => 60,
		"3m"	 => 3 * 60,
		"5m"	 => 5 * 60,
		"15m"	 => 15 * 60,
		"30m"	 => 30 * 60,
		"1h"	 => 60 * 60,
		"2h"	 => 2 * 60 * 60,
		"3h"	 => 3 * 60 * 60,
		"6h"	 => 6 * 60 * 60,
		"12h"	 => 12 * 60 * 60,
		"1d"	 => 24 * 60 * 60,
		// "3d"	 => 3 * 24 * 60 * 60,
		// "1w"	 => 7 * 24 * 60 * 60,
		// "1mo"	 => 30 * 24 * 60 * 60,
		"7d"	 => 7 * 24 * 60 * 60,
		"28d"	 => 28 * 24 * 60 * 60,
		"84d"	 => 84 * 24 * 60 * 60,
		// "1y"	 => 365 * 24 * 60 * 60,
	];
	
	
	
	public static function to_real_timestamp (int $timestamp) : float
	{
		if(strlen($timestamp) === 16) {
			$timestamp = $timestamp / 1000000;
		}
		elseif(strlen($timestamp) === 13) {
			$timestamp = $timestamp / 1000;
		}
		elseif(strlen($timestamp) === 0) {
			# do nothing
		}
		else {
			throw new ErrorException("unknown timestamp format : {$timestamp}");
		}
		return $timestamp;
	}
	
	public static function timestamp_to_datetime (int $timestamp) : DateTimeInterface
	{
		$timestamp = static::to_real_timestamp($timestamp);
		$res = DateTime::createFromFormat("U.u", $timestamp); #TODO timezone europe/paris ?
		return $res;
	}
	
	
	public static function responseData_to_table (mixed $data) : mixed
	{
		$res = [];
		if ($data instanceof ModelInterface) {
			$attributes = $data->attributeMap();
			foreach ($attributes as $attribute) {
				$method_name = "get" . ucfirst($attribute);
				$elem = $data->$method_name();
				if (is_object($elem) || is_array($elem)) {
					$res [$attribute] = static::responseData_to_table($elem);
				}
				else {
					$res [$attribute] = $elem;
				}
			}
		}
		elseif ($data instanceof ArrayAccess) {
			throw new ErrorException("not implemented : ArrayAccess");
		}
		elseif (is_object($data)) {
			$ref = new ReflectionObject ($data);
			foreach ($ref->getProperties() as $prop) {
				$elem = $prop->getValue($data);
				$res [$prop->getName()] = static::responseData_to_table($elem);
			}
		}
		elseif (is_array($data)) {
			foreach ($data as $key => $elem) {
				$res [$key] = static::responseData_to_table($elem);
			}
		}
		else {
			$res = $data;
		}
		return $res;
	}
	
	
	public static function get_all_trades_sorted (string $symbol)
	{
		$f3 = Base::instance();
		
		$spot_trades = BinanceSpotApi::get_trades_cached ($symbol);
		
		$convert_trades = BinanceConvertApi::get_trade_history_large_for_symbol (DateTime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00"), new DateTime, $symbol);
		
		$convert_trades = BinanceConvertApi::conversionTrades_to_spotTrades($convert_trades);
		$all_trades = array_merge($spot_trades, $convert_trades);
		
		$sort = array_column($all_trades, "time");
		array_multisort($sort, SORT_ASC, SORT_NUMERIC, $all_trades);
		return $all_trades;
	}
	
	
	public static function get_trades_stats (string $base_asset, string $quote_asset) #TODO remove in favor of Accounting
	{
		# get trades
		$symbol = "{$base_asset}{$quote_asset}";
		$all_trades = static::get_all_trades_sorted ($symbol);
		
		# init
		$res = [
			"entry" => [
				"quantity"	=> 0,
				"cost"		=> 0,
				"quote"		=> 0,
				"avg"		=> 0,
				"last"		=> 0,
			],
			"exit" => [
				"quantity"	=> 0,
				"cost"		=> 0,
				"quote"		=> 0,
				"avg"		=> 0,
				"last"		=> 0,
			],
		];
		
		# treat trades
		foreach ($all_trades as $trade) {
			// var_dump($trade);
			if ($trade ["symbol"] !== $symbol) {
				throw new ErrorException("wrong symbol found in trade : {$trade ["symbol"]}");
			}
			// echo Binance::timestamp_to_datetime ($trade ["time"]) -> format(Stuff::datetime_sql_format) . " <br/>" . PHP_EOL;
			
			if ($trade ["isBuyer"] === true) {
				// echo "- BUY {$trade ["qty"]} {$base_asset} @ {$trade ["price"]} = {$trade ["quoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res ["entry"] ["last"] = $trade ["time"];
				$res ["entry"] ["quantity"] += $trade ["qty"];
				$res ["entry"] ["cost"] += $trade ["quoteQty"];
				$res ["exit"] ["quantity"] = max( $res ["exit"] ["quantity"] - $trade ["qty"], 0);
				$res ["exit"] ["cost"] = max( $res ["exit"] ["cost"] - $trade ["quoteQty"], 0);
			}
			else {
				// echo "- SELL {$trade ["qty"]} {$base_asset} @ {$trade ["price"]} = {$trade ["quoteQty"]} {$quote_asset} <br/>" . PHP_EOL;
				$res ["exit"] ["last"] = $trade ["time"];
				$res ["entry"] ["quantity"] = max( $res ["entry"] ["quantity"] - $trade ["qty"], 0);
				$res ["entry"] ["cost"] = max( $res ["entry"] ["cost"] - $trade ["quoteQty"], 0);
				$res ["exit"] ["quantity"] += $trade ["qty"];
				$res ["exit"] ["cost"] += $trade ["quoteQty"];
			}
			$res ["entry"] ["quote"] = $res ["entry"] ["quantity"] * $trade ["price"];
			$res ["exit"] ["quote"] = $res ["exit"] ["quantity"] * $trade ["price"];
			
			# dust reset
			if ($res ["entry"] ["quote"] > 0 && $res ["entry"] ["quote"] < static::quote_dust_threashold) {
				// echo " entry dust reset <br/>" . PHP_EOL;
				$res ["entry"] ["quantity"] = 0;
				$res ["entry"] ["cost"] = 0;
				$res ["entry"] ["quote"] = 0;
			}
			if ($res ["exit"] ["quote"] > 0 && $res ["exit"] ["quote"] < static::quote_dust_threashold) {
				// echo " exit dust reset <br/>" . PHP_EOL;
				$res ["exit"] ["quantity"] = 0;
				$res ["exit"] ["cost"] = 0;
				$res ["exit"] ["quote"] = 0;
			}
			
			# avg compute
			if ($res ["entry"] ["quantity"] != 0) {
				$res ["entry"] ["avg"] =  $res ["entry"] ["cost"] /  $res ["entry"] ["quantity"];
			}
			else {
				$res ["entry"] ["avg"] = 0;
			}
			if ($res ["exit"] ["quantity"] != 0) {
				$res ["exit"] ["avg"] =  $res ["exit"] ["cost"] /  $res ["exit"] ["quantity"];
			}
			else {
				$res ["exit"] ["avg"] = 0;
			}
			
			// echo " entry => {$res ["entry"] ["quantity"]} {$base_asset} = {$res ["entry"] ["quote"]} {$quote_asset} <=> "
			// 	 .  "{$res ["entry"] ["cost"]} {$quote_asset} @ {$res ["entry"] ["avg"]} <br/>" . PHP_EOL;
			// echo " exit => {$res ["exit"] ["quantity"]} {$base_asset} = {$res ["exit"] ["quote"]} {$quote_asset} <=> "
			// 	.  "{$res ["exit"] ["cost"]} {$quote_asset} @ {$res ["exit"] ["avg"]} <br/>" . PHP_EOL;
			// echo "<br/>" . PHP_EOL;
		}
		
		// echo "==> entry (buy) avg = {$res ["entry"] ["avg"]} <br/>" . PHP_EOL;
		// echo "==> exit (sell) avg = {$res ["exit"] ["avg"]} <br/>" . PHP_EOL;
		return $res;
	}
	
	
	public static function get_all_trades () : array
	{
		$f3 = Base::instance();
		
		$spot_trades = BinanceSpotApi::get_all_trades(); #TODO
		$convert_trades = BinanceConvertApi::get_all_trades ();
		$convert_trades = BinanceConvertApi::conversionTrades_to_spotTrades($convert_trades);
		$fiat_trades = BinanceFiatApi::get_all_trades();
		$fiat_trades = BinanceFiatApi::fiatTrades_to_spotTrades($fiat_trades);
		$all_trades = array_merge($spot_trades, $convert_trades, $fiat_trades);
		
		$sort = array_column($all_trades, "time");
		array_multisort($sort, SORT_ASC, SORT_NUMERIC, $all_trades);
		return $all_trades;
	}
	
}
