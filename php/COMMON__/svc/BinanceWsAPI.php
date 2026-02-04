<?php
namespace COMMON__\svc;

use Base;
use COMMON__\mdl\Kline;
use DateTime;
use DB\SQL;
use ErrorException;
use RuntimeException;
use Throwable;
use WebSocket\Client;


class BinanceWsAPI
{

	private static function createListenKey (array $binance_conf): string
	{
		$url = $binance_conf ["rest_url"] . '/api/v3/userDataStream';
		$apiKey = $binance_conf ["key"];
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
			throw new RuntimeException ('Impossible de créer listenKey');
		}
		return $data['listenKey'];
	}


	private static function keepAliveListenKey (array $binance_conf, string $listenKey): void
	{
		$url = $binance_conf ["rest_url"] . "/api/v3/userDataStream?listenKey=$listenKey";
		$ch = curl_init($url);
		$apiKey = $binance_conf ["key"];
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ["X-MBX-APIKEY: $apiKey"],
		]);
		curl_exec($ch);
		curl_close($ch);
	}
	

	/**
	 * get my trades
	 */
	public static function userDataStream ()
	{
		$binance_conf = Binance::get_conf ();

		// --- setup WS ---
		$listenKey = static::createListenKey ($binance_conf);
		$url = $binance_conf ["ws_url"] . "/$listenKey";
			
		$keepAliveInterval = 30 * 60; // 30 min
		$lastKeepAlive = time();
		
		while (1 === 1) {
			echo "[OK] ListenKey créé : $listenKey\n";
			echo "[OK] WS : $url\n";
			try {
				$ws = new Client ($url, ['timeout' => 1]); // timeout court pour check keep-alive

				while (1 === 1) {
					// --- keep-alive
					if (time() - $lastKeepAlive >= $keepAliveInterval) {
						static::keepAliveListenKey ($binance_conf, $listenKey);
						$lastKeepAlive = time();
						echo "[KEEPALIVE]\n";
					}

					// --- lire WS
					$msg = $ws->receive(); // timeout = 5s max
					$data = json_decode($msg, true);
					if (!$data || !isset($data['e'])) continue;

					// --- TES TRADES
					if ($data['e'] === 'executionReport' &&
						in_array ($data['X'], ['FILLED', 'PARTIALLY_FILLED'])) {

						$symbol = $data ['s'];
						$side   = $data ['S'];
						$price  = $data ['L'];
						$qty    = $data ['l'];
						$time   = date ('H:i:s', $data['T']/1000);

						echo "[$time] $symbol $side $qty @ $price\n";

						// --- INSERT DB ici si besoin
					}
				}
			}
			catch (Throwable $e) {
				echo $e::class ." " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
				echo "[WS ERROR] {$e->getMessage()}\n";
				echo "[RECONNECT] dans 5s...\n";
				sleep(5);

				// --- recréer listenKey si nécessaire
				$listenKey = static::createListenKey ($binance_conf);
				$url = $binance_conf ["ws_url"] . "/$listenKey";
			}
		}
	}
	
	
	public static function miniTicker ()
	{
		$f3 = Base::instance();
		$db = $f3->get ("db"); /** @var SQL $db */

		$binance_conf = Binance::get_conf ();
		$url = $binance_conf ["ws_url"] . "/!miniTicker@arr";
		$ws = new Client ($url);

		while (1 === 1) {
			# read message
			$msg = $ws->receive();
			$tickers = json_decode($msg, true);

			$start = microtime(true);
			$db->begin();
			echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] ";
			foreach ($tickers as $ticker) {
				if ($ticker ["e"] !== "24hrMiniTicker") {
					throw new ErrorException ("WS miniTicker : unhandled message type : {$ticker ["e"]}");
				}

				# insert into DB
				$kline = new Kline;
				$kline->symbol = $ticker ["s"];
				$kline->candle_size = "1s";
				$kline->open_time = Binance::timestamp_to_datetime ($ticker ["E"]);
				$kline->open = 0;
				$kline->high = 0;
				$kline->low = 0;
				$kline->close = 0;
				$kline->volume = 0;
				$kline->close_time = Binance::timestamp_to_datetime ($ticker ["E"]);
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
						var_dump($ticker);
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
		}
	}


	#TODO refactor WS

}
