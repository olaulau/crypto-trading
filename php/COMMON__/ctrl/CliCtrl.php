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
use RuntimeException;
use Throwable;
use WebSocket\Client;


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
		error_reporting(E_ALL & ~E_DEPRECATED);
		
		# empty buffers
		while (ob_get_level () > 0) {
			ob_end_flush ();
		}

		$API_KEY = $f3->get("binance.key");
		$TESTNET = true; // false = mainnet

		// --- fonctions REST ---
		function createListenKey(string $apiKey, bool $testnet = false): string {
			$url = $testnet
				? 'https://testnet.binance.vision/api/v3/userDataStream'
				: 'https://api.binance.com/api/v3/userDataStream';
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => ["X-MBX-APIKEY: $apiKey"],
			]);
			$res = curl_exec($ch);
			$data = json_decode($res, true);
			curl_close($ch);
			if (!isset($data['listenKey'])) {
				var_dump($data);
				throw new RuntimeException('Impossible de créer listenKey');
			}
			return $data['listenKey'];
		}

		function keepAliveListenKey(string $apiKey, string $listenKey, bool $testnet = false): void {
			$url = ($testnet ? 'https://testnet.binance.vision/api/v3/userDataStream' : 'https://api.binance.com/api/v3/userDataStream')
				. "?listenKey=$listenKey";
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => ["X-MBX-APIKEY: $apiKey"],
			]);
			curl_exec($ch);
			curl_close($ch);
		}

		// --- setup WS ---
		$listenKey = createListenKey($API_KEY, $TESTNET);
		$wsUrl = $TESTNET
			? "wss://stream.testnet.binance.vision/ws/$listenKey"
			: "wss://stream.binance.com:9443/ws/$listenKey";

			
		$keepAliveInterval = 30 * 60; // 30 min
		$lastKeepAlive = time();
		
		while (true) {
			echo "[OK] ListenKey créé : $listenKey\n";
			echo "[OK] WS : $wsUrl\n";
			try {
				$ws = new Client($wsUrl, ['timeout' => 1]); // timeout court pour check keep-alive

				while (true) {
					// --- keep-alive
					if (time() - $lastKeepAlive >= $keepAliveInterval) {
						keepAliveListenKey($API_KEY, $listenKey, $TESTNET);
						$lastKeepAlive = time();
						echo "[KEEPALIVE]\n";
					}

					// --- lire WS
					$msg = $ws->receive(); // timeout = 5s max
					$data = json_decode($msg, true);
					if (!$data || !isset($data['e'])) continue;

					// --- TES TRADES
					if ($data['e'] === 'executionReport' &&
						in_array($data['X'], ['FILLED', 'PARTIALLY_FILLED'])) {

						$symbol = $data['s'];
						$side   = $data['S'];
						$price  = $data['L'];
						$qty    = $data['l'];
						$time   = date('H:i:s', $data['T']/1000);

						echo "[$time] $symbol $side $qty @ $price\n";

						// --- INSERT DB ici si besoin
					}
				}

			} catch (Throwable $e) {
				echo $e::class ." " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
				echo "[WS ERROR] {$e->getMessage()}\n";
				echo "[RECONNECT] dans 5s...\n";
				sleep(5);

				// --- recréer listenKey si nécessaire
				$listenKey = createListenKey($API_KEY, $TESTNET);
				$wsUrl = $TESTNET
					? "wss://stream.testnet.binance.vision/ws/$listenKey"
					: "wss://stream.binance.com:9443/ws/$listenKey";
			}
		}

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
