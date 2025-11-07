<?php
namespace COMMON__\svc;

use DateTimeImmutable;
use DB\SQL\Mapper;


class Stuff
{
	
	public static function timestamp_to_date_formated (int $timestamp) : string
	{
		return DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
	}
	
	public static function extract_candle_infos (Mapper $ohlcv) : array
	{
		$candle = $ohlcv ["candle"];
		$candle = json_decode($candle);
		$candle = [
			"timestamp"	=> $candle [0],
			"open"		=> $candle [1],
			"high"		=> $candle [2],
			"low"		=> $candle [3],
			"close"		=> $candle [4],
			"volume"	=> $candle [5],
		];
		return $candle;
	}
	
	
	public static function float_parts (float $num): array
	{
		// Formatage en notation scientifique (ex: "1.234000e+03")
		$sci = sprintf('%.15e', $num);
	
		// Extraction par regex : mantisse et exposant
		if (preg_match('/^([+-]?[0-9]*\.?[0-9]+)e([+-]?[0-9]+)$/i', $sci, $m)) {
			return [
				'mantisse' => (float)$m[1],
				'exposant' => (int)$m[2],
			];
		}
		return [ 'mantisse' => null, 'exposant' => null ];
	}
	
	
	public static function number_format_french ($value, $decimals, bool $force_sign=false)
	{
		return (($force_sign && $value > 0) ? "+ " : "") . number_format($value, $decimals, ",", " ");
	}
	
	
	public static function format_float_significative (float $value, int $significative_numbers, bool $force_sign=false)
	{
		list("exposant" => $exposant) = self::float_parts ($value);
		$decimals = $significative_numbers - $exposant - 1;
		return self::number_format_french ($value, $decimals, $force_sign);
	}
	
	
	public static function percent_format ($value)
	{
		return self::number_format_french ($value, 2, true) . " %";
	}
	
}
