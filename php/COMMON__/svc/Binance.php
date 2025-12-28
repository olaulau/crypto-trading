<?php
namespace COMMON__\svc;

use ArrayAccess;
use Binance\Common\Dtos\ModelInterface;
use DateTime;
use DateTimeInterface;
use ErrorException;
use ReflectionObject;


class Binance
{
	
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
		"4h"	 => 4 * 60 * 60,
		"6h"	 => 6 * 60 * 60,
		"8h"	 => 8 * 60 * 60,
		"12h"	 => 12 * 60 * 60,
		"1d"	 => 24 * 60 * 60,
		// "3d"	 => 3 * 24 * 60 * 60,
		// "1w"	 => 7 * 24 * 60 * 60,
		// "1mo"	 => 30 * 24 * 60 * 60,
		"7d"	 => 3 * 24 * 60 * 60,
		"30d"	 => 3 * 24 * 60 * 60,
		"60d"	 => 3 * 24 * 60 * 60,
		"90d"	 => 3 * 24 * 60 * 60,
		"1y"	 => 3 * 24 * 60 * 60,
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
				if(is_object($elem) || is_array($elem)) {
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
	
}
