<?php
namespace COMMON__\ctrl;

use Base;
use Binance\API;
use COMMON__\mdl\Kline;
use COMMON__\svc\Binance;
use COMMON__\svc\Stuff;
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
				$dt = Binance::timestamp_to_datetime($ticker["eventTime"]);
				$kline = new Kline;
				$kline->copyfrom($ticker);
				$kline->candle_size = "1s";
				$kline->open_time = $dt;
				$kline->close = 0;
				$kline->close_time = $dt;
				$kline->quote_asset_volume = $ticker["quoteVolume"];
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
		
		#TODO purge after 1h max
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
	
}
