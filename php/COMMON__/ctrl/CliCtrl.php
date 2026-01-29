<?php
namespace COMMON__\ctrl;

use Base;
use Binance\API;
use Cache;
use COMMON__\mdl\Kline;
use COMMON__\svc\Binance;
use COMMON__\svc\Stuff;
use DateInterval;
use DateTime;
use DB\SQL;
use Throwable;


class CliCtrl extends Ctrl
{
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}


	public static function afterRoute ()
	{
		parent::afterRoute();
	}


	public static function testGET (Base $f3, $url, $controler) : void
	{
		
		
		die;
	}
	
	
	public static function wsMiniTicker (Base $f3, $url, $controler) : void
	{
		# ignore deprecated
		error_reporting (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

		# empty buffers
		while (ob_get_level () > 0) {
			ob_end_flush ();
		}

		# initiate
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		$binance_conf = $f3->get("binance");
		
		# get prices
		$api = new API ($binance_conf ["key"], $binance_conf ["secret"]);
		$api->miniTicker(function ($api, $tickers) use ($db) {
			$start = microtime(true);
			
			$db->begin();
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] ";
			foreach ($tickers as $ticker) {
				$dt = Binance::timestamp_to_datetime ($ticker["eventTime"]);
				$kline = new Kline;
				
				// $kline->copyfrom($ticker);
				$kline->symbol = $ticker ["symbol"];
				$kline->candle_size = "1s";
				$kline->open_time = $dt;
				$kline->open = 0;
				$kline->high = 0;
				$kline->low = 0;
				$kline->close = $ticker ["close"];
				$kline->volume = 0;
				$kline->close_time = $dt;
				$kline->quote_asset_volume = 0;
				$kline->number_of_trades = 0;
				$kline->taker_buy_base_asset_volume = 0;
				$kline->taker_buy_quote_asset_volume = 0;
				$kline->ignore = 0;
				
				try {
					$kline->save();
				}
				catch (Throwable $th) {
					if (str_contains($th->getMessage(), "uniq__symbol__candle_size__open_time")) {
						echo " [duplicate key ignore] ";
					}
					else {
						throw $th;
					}
				}
				echo ".";
			}
			$db->commit();
			
			$end = microtime(true);
			$duration = $end - $start;
			$duration = $duration * 1000;
			$duration = number_format($duration, 3, ",", " ");
			echo " : " . count($tickers) . " tickers in {$duration} ms";
			echo PHP_EOL;
		});
	}
	
	
	public static function wsMiniTickerLoop (Base $f3, $url, $controler) : void
	{
		# empty buffers
		while (ob_get_level () > 0) {
			ob_end_flush ();
		}
		
		# prepare
		$route = $f3->alias("cliWsMiniTicker");
		$cmd = "php index.php {$route} 2>&1";
		
		# run in loop
		while (true) {
			echo PHP_EOL;
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : START LOOP" . PHP_EOL; 
			passthru($cmd, $result_code);
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : script exited with code : $result_code" . PHP_EOL;
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : END LOOP" . PHP_EOL; 
			echo PHP_EOL;
			sleep (1);
		}
	}

	public static function cronGET (Base $f3, $url, $controler) : void
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
