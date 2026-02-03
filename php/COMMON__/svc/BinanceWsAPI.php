<?php
namespace COMMON__\svc;

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
		$binance_conf = Binance::get_conf ();
		$url = $binance_conf ["ws_url"] . "/!miniTicker@arr";
			
		$ws = new Client ($url);

		while (1 === 1) {
			// --- lire WS
			$msg = $ws->receive();
			$data = json_decode($msg, true);

			var_dump($data); #TODO insert into DB
		}
	}

	#TODO refactor WS

}
