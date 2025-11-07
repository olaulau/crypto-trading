<?php
namespace COMMON__\svc;

use DB\SQL\Mapper;


class Stuff
{
	
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
	
	
	public static function float_parts (float $num): array {
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
	
	
	public static function format_float (float $value, int $significative_numbers)
	{
		list("exposant" => $exposant) = self::float_parts($value);
		$decimals = $significative_numbers - $exposant - 1;
		return number_format($value, $decimals, ",", " ");
	}
	
}
