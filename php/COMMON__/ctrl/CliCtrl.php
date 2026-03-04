<?php
namespace COMMON__\ctrl;

use Base;
use Cache;
use COMMON__\mdl\Kline;
use COMMON__\svc\BinanceWsAPI;
use COMMON__\svc\Stuff;
use DateInterval;
use DateTime;
use DB\SQL;


class CliCtrl extends Ctrl
{
	
	public static function beforeRoute () : void
	{
		parent::beforeRoute();
	}


	public static function afterRoute () : void
	{
		parent::afterRoute();
	}
	
	
	public static function prepareCli () : void
	{
		# ignore deprecated
		error_reporting (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

		# empty buffers
		while (ob_get_level () > 0) {
			ob_end_flush ();
		}
		
		# long run
		set_time_limit(0);
		
		# get exception instead of errors
		set_error_handler(function ($severity, $message, $file, $line) {
			if (!(error_reporting() & $severity)) {
				return false;
			}
			throw new \ErrorException($message, 0, $severity, $file, $line);
		});
	}


	public static function test (Base $f3, $url, $controler) : void
	{
		
		die;
	}


	public static function miniTicker (Base $f3, $url, $controler) : void
	{
		static::prepareCli ();
		BinanceWsAPI::miniTicker ();
	}
	
	
	public static function ticker24h (Base $f3, $url, $controler) : void
	{
		$symbol = $f3->get("PARAMS.symbol");
		if (empty($symbol)) {
			die("empty symbol param");
		}
		
		static::prepareCli ();
		BinanceWsAPI::ticker24h($symbol);
	}
	
	
	public static function bookTicker (Base $f3, $url, $controler) : void
	{
		$symbol = $f3->get("PARAMS.symbol");
		if (empty($symbol)) {
			die("empty symbol param");
		}
		
		static::prepareCli ();
		BinanceWsAPI::bookTicker ($symbol);
	}
	
	
	public static function userDataStream (Base $f3, $url, $controler) : void
	{
		static::prepareCli ();
		BinanceWsAPI::userDataStream ();
	}
	
	
	public static function cron (Base $f3, $url, $controler) : void
	{
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : cron controller" . PHP_EOL;
		static::cronMinutely ();
	}

	private static function cronMinutely () : void
	{
		$cache = Cache::instance();
		$cache_class = "CliCtrl";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 60;
		
		if ($cache->exists($cache_key) === false) {
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : cron minutely" . PHP_EOL;
			static::purgeKline1s ();
			$cache->set($cache_key, true, $cache_ttl);
		}
	}

	private static function purgeKline1s () : void
	{
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : purgeKline1s" . PHP_EOL;
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */

		$sql = "
			DELETE FROM " . Kline::table . "
			WHERE candle_size = ?
			AND open_time < ?
		";
		$params = [
			"1s",
			(new DateTime)->sub(new DateInterval("PT1H"))->format(Stuff::datetime_sql_format) ,
		];
		$db->exec($sql, $params);
	}
	
}
