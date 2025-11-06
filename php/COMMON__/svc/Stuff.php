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
	
}
