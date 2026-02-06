<?php
namespace COMMON__\svc;

use ArrayAccess;
use Base;
use Binance\Common\Dtos\ModelInterface;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use ErrorException;
use ReflectionObject;


class Binance
{
	
	public final const reference_asset = "EUR";
	public final const pivot_asset = "USDC";
	
	public final const reference_dust_threashold = 10;
	
	public final const recv_window = 20000;
	
	public final const kline_format = [
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
	
	public final const candles = [
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
			return $timestamp / 1000000;
		}
		elseif(strlen($timestamp) === 13) {
			return $timestamp / 1000;
		}
		elseif(strlen($timestamp) === 0) {
			return $timestamp;
		}
		else {
			throw new ErrorException("unknown timestamp format : {$timestamp}");
		}
	}
	
	public static function timestamp_to_datetime (int $timestamp) : DateTimeInterface
	{
		$timestamp = static::to_real_timestamp($timestamp);
		$dt = DateTime::createFromFormat("U.u", number_format($timestamp, 6, ".", ""));
		$dtz = new DateTimeZone ("Europe/Paris");
		$dt->setTimezone($dtz);
		return $dt;
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
	
	
	public static function get_trades_sorted (string $symbol)
	{
		$f3 = Base::instance();
		
		$spot_trades = BinanceSpotApi::get_trades_cached ($symbol);
		
		$convert_trades = BinanceConvertApi::get_trade_history_large_for_symbol ($symbol);
		
		$convert_trades = BinanceConvertApi::conversionTrades_to_spotTrades($convert_trades);
		$all_trades = array_merge($spot_trades, $convert_trades);
		
		$sort = array_column($all_trades, "time");
		array_multisort($sort, SORT_ASC, SORT_NUMERIC, $all_trades);
		return $all_trades;
	}
	
	
	public static function get_all_trades () : array
	{
		$spot_trades = BinanceSpotApi::get_all_trades();
		
		$convert_trades = BinanceConvertApi::get_all_trades ();
		$convert_trades = BinanceConvertApi::conversionTrades_to_spotTrades($convert_trades);
		
		$fiat_trades = BinanceFiatApi::get_all_trades();
		$fiat_trades = BinanceFiatApi::fiatTrades_to_spotTrades($fiat_trades);

		$dividendes_trades = BinanceRestApi::getAssetDividend ();
		$dividendes_trades = BinanceRestApi::assetDividend_to_spotTrades($dividendes_trades);
		
		$all_trades = array_merge ($spot_trades, $convert_trades, $fiat_trades, $dividendes_trades);
		$sort = array_column ($all_trades, "time");
		array_multisort ($sort, SORT_ASC, SORT_NUMERIC, $all_trades);
		
		return $all_trades;
	}
	
	
	public static function get_start_date () : DateTimeInterface
	{
		$f3 = Base::instance();
		return DateTime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00");
	}
	
	
	private static function find_symbol_for_assets (string $start, string $end, ?array $symbols=null) : ?array
	{
		if ($start === $end) {
			throw new ErrorException("start is same as end");
		}
		
		if(empty($symbols)) {
			$symbols = BinanceSpotApi::get_all_symbols_cached();
		}
		$symbols_str = array_keys ($symbols);
		
		# direct
		$symbol = "{$start}{$end}";
		if (in_array($symbol, $symbols_str) ) {
			return [
				"direction"	=>	"normal",
				"symbol"	=> $symbols [$symbol] ["symbol"],
			];
		}
		
		# direct opposite
		$symbol = "{$end}{$start}";
		if (in_array($symbol, $symbols_str)) {
			return [
				"direction"	=>	"opposite",
				"symbol"	=> $symbols [$symbol] ["symbol"],
			];
		}
		
		return null;
	}
	
	public static function find_symbol_path_for_assets (string $start, string $end, ?array $symbols=null) : array
	{
		if ($start === $end) {
			throw new ErrorException ("start is same as end");
		}
		
		# direct (and opposite)
		$symbol = static::find_symbol_for_assets ($start, $end, $symbols);
		if (!empty($symbol)) {
			return [$symbol];
		}
		
		# try to pass through USDC
		$symbol1 = static::find_symbol_for_assets ($start, "USDC", $symbols);
		$symbol2 = static::find_symbol_for_assets ("USDC", $end, $symbols);
		if (!empty($symbol1) && !empty($symbol2)) {
			return [$symbol1, $symbol2];
		}
		
		# more complex case, need real path search
		throw new ErrorException("complex case not implemented");
	}

	
	public static function get_conf () : array
	{
		$f3 = Base::instance();
		
		$env = $f3->get("binance.env");
		$binance_conf = $f3->get("binance.envs.$env");
		return $binance_conf;
	}
}
