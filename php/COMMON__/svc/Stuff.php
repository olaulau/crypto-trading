<?php
namespace COMMON__\svc;

use DateTimeImmutable;
use DB\SQL\Mapper;
use ErrorException;


class Stuff
{
	
	public const datetime_sql_format = "Y-m-d H:i:s";
	public const datetime_french_format = "d/m/Y H:i:s";
	
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
	
	
	public static function EUR_format ($value)
	{
		return self::number_format_french ($value, 2, true) . " €";
	}
	
	
	public static function download_to_disk ($url, $destination)
	{
		$ch = curl_init($url);
		$fp = fopen($destination, 'w+');

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);           // no timeout
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // verify SSL
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
		
		curl_setopt($ch, CURLOPT_HEADER, false); // ne pas inclure dans le corps
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header_line) use (&$headers) {
			$len = strlen($header_line);
			$header_line = trim($header_line);
			if ($header_line) {
				$headers[] = $header_line;
			}
			return $len;
		});

		curl_exec($ch);
		if (curl_errno($ch)) {
			throw new ErrorException("download : curl error #" . curl_errno($ch) . " " . curl_error($ch));
		}
		// var_dump($headers); die;
		if (str_starts_with (explode(" ", $headers[0]) [1], "4")) {
			throw new ErrorException("download : 4xx HTTP status");
		}

		curl_close($ch);
		fclose($fp);
	}
	
	
	public static function array_group_by (array $array, string $column, bool $stack=true) : array
	{
		$res = [];
		foreach ($array as $row) {
			$key = $row [$column];
			if ($stack) {
				$res [$key] [] = $row;
			}
			else {
				$res [$key] = $row;
			}
		}
		return $res;
	}
	
}
