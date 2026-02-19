<?php
namespace COMMON__\svc;

use Base;
use COMMON__\mdl\Balance;
use COMMON__\mdl\Kline;
use DateTime;
use DB\SQL;
use ErrorException;
use RuntimeException;
use Throwable;
use WebSocket\Client;
use WebSocket\TimeoutException;


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
	}
	

	/**
	 * get user data update on events :  
	 * orderLists, orders, trades, balance
	 */
	public static function userDataStream ()
	{
		$binance_conf = Binance::get_conf ();

		# setup WS
		$listenKey = static::createListenKey ($binance_conf);
		echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] created listen key : {$listenKey}" . PHP_EOL;
			
		$keepAliveInterval = 30 * 60; // 30 min (max 60 min)
		$lastKeepAlive = time();
		
		while (1 === 1) {
			try {
				$url = $binance_conf ["ws_url"] . "/$listenKey";
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] WS : {$url}" . PHP_EOL;
				$ws = new Client ($url, ['timeout' => 5 * 60]); # timeout court pour check keep-alive

				while (1 === 1) {
					#  keep-alive
					if (time() - $lastKeepAlive >= $keepAliveInterval) {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] keep alive" . PHP_EOL;
						static::keepAliveListenKey ($binance_conf, $listenKey);
						$lastKeepAlive = time();
					}

					# read WS
					$msg = $ws->receive(); # with timeout
					$data = json_decode($msg, true);
					if (!$data || !isset($data['e'])) {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] empty data" . PHP_EOL;
						continue;
					}

					# messages
					if ($data ['e'] === 'executionReport') { # order status update ~= 1 trade (with id ?)
						$order_data = static::executionReport_to_order ($data);
						// var_dump($order_data);
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] order update : {$order_data ["orderId"]}" . PHP_EOL;
						BinanceSpotApi::store_orders_into_db([$order_data]);
						
						if (in_array ($order_data ["status"], ['FILLED', 'PARTIALLY_FILLED'])) {
							$trade_data = static::executionReport_to_trade($data);
							// var_dump($trade_data);
							echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] new trade : {$trade_data ["orderId"]}" . PHP_EOL;
							BinanceSpotApiTrade::store_trades_into_db([$trade_data]);
						}
					}
					elseif ($data ["e"] === "outboundAccountPosition") {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] balance update " . PHP_EOL;
						$timestamp = $data ["E"];
						$lastUpdate = Binance::timestamp_to_datetime($timestamp);
						foreach ($data ["B"] as $row) {
							$balance = new Balance();
							$balance->load(["asset = ?", $row ["a"]], []);
							$balance->asset = $row ["a"];
							$balance->lastUpdated = $lastUpdate;
							$balance->free = $row ["f"];
							$balance->locked = $row ["l"];
							$balance->save();
						}
					}
					elseif ($data ["e"] === "balanceUpdate") {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] balanceUpdate : " . PHP_EOL;
						var_dump($data);
						throw new ErrorException("not implemented : WS UDS balanceUpdate");
						/*
						$timestamp = $data ["T"];
						$lastUpdate = Binance::timestamp_to_datetime($timestamp);
						$balance = new Balance ();
						$balance->load (["asset = ?", "asset"], []);
						$balance->asset = $row ["a"];
						$balance->lastUpdated = $lastUpdate;
						$balance->free = $row ["f"];
						$balance->locked = $row ["l"];
						$balance->save ();
						*/
						#TODO apply delta :
						/*
						 "d": {                      // delta object
							"f": "50.00000000",       // change dans la quantité libre (free)
							"l": "0.00000000"         // change dans la quantité bloquée (locked)
						}
						$balances[$asset]['free']   = bcadd($balances[$asset]['free'], $delta['f'], 8);
						$balances[$asset]['locked'] = bcadd($balances[$asset]['locked'], $delta['l'], 8);
						*/
					}
					elseif ($data ["e"] === "listenKeyExpired") {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] listen key expired" . PHP_EOL;
						# recreate listenKey
						$listenKey = static::createListenKey ($binance_conf);
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] created listen key : {$listenKey}" . PHP_EOL;
					}
					else {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] other data : " . PHP_EOL;
						var_dump($data);
						throw new ErrorException("WS UDS : unknown data type");
					}
				}
			}
			catch (TimeoutException $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] timeout" . PHP_EOL;
			}
			catch (Throwable $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] exception : " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
				echo "reconnect in 5s ..." . PHP_EOL;
				sleep(5);

				# recreate listenKey
				$listenKey = static::createListenKey ($binance_conf);
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] created listen key : {$listenKey}" . PHP_EOL;
			}
		}
	}
	
/*
array(4) {
  'e' =>
  string(23) "outboundAccountPosition"
  'E' =>
  int(1771197761104)
  'u' =>
  int(1771197761103)
  'B' =>
  array(3) {
    [0] =>
    array(3) {
      'a' =>
      string(3) "ETH"
      'f' =>
      string(10) "0.01288440"
      'l' =>
      string(10) "0.00000000"
    }
    [1] =>
    array(3) {
      'a' =>
      string(3) "BNB"
      'f' =>
      string(10) "0.00000000"
      'l' =>
      string(10) "0.00000000"
    }
    [2] =>
    array(3) {
      'a' =>
      string(4) "USDT"
      'f' =>
      string(13) "4973.95836050"
      'l' =>
      string(10) "0.00000000"
    }
  }
}
*/

/*
[2026-02-16 02:15:01] other data :
/var/www/crypto.nas.laulau.ovh/php/COMMON__/svc/BinanceWsAPI.php:109:
array(3) {
  'e' =>
  string(16) "listenKeyExpired"
  'E' =>
  int(1771204501407)
  'listenKey' =>
  string(60) "Y1J60rcjq1ipsoeqMhyW6vocUZEObX4D5NJtlkA05z0kGvQifkwvUgz4Q1q6"
}
[2026-02-16 02:15:01] keep alive
*/

/*
[2026-02-16 01:31:16] other data :
/var/www/crypto.nas.laulau.ovh/php/COMMON__/svc/BinanceWsAPI.php:109:
array(11) {
  'e' =>
  string(10) "listStatus"
  'E' =>
  int(1771201876078)
  's' =>
  string(7) "ETHUSDC"
  'g' =>
  int(20065027320)
  'c' =>
  string(3) "OCO"
  'l' =>
  string(12) "EXEC_STARTED"
  'L' =>
  string(9) "EXECUTING"
  'r' =>
  string(4) "NONE"
  'C' =>
  string(36) "web_08947207969b44398f60aa4e6f86bf26"
  'T' =>
  int(1771201876078)
  'O' =>
  array(2) {
    [0] =>
    array(3) {
      's' =>
      string(7) "ETHUSDC"
      'i' =>
      int(9203132403)
      'c' =>
      string(36) "web_3366497f6293431dbdd6af04f22a3cdb"
    }
    [1] =>
    array(3) {
     's' =>
      string(7) "ETHUSDC"
      'i' =>
      int(9203132404)
      'c' =>
      string(22) "CHT2X6YrK28MYN7k8HbpmI"
    }
  }
}
*/

	
	private static function executionReport_to_order (array $data) : array
	{
		return [
			'symbol' => $data['s'] ?? null,
			'orderId' => $data['i'] ?? null,
			'orderListId' => $data['g'] ?? -1,
			'clientOrderId' => $data['c'] ?? null,
			'price' => $data['p'] ?? null,
			'origQty' => $data['q'] ?? null,
			'executedQty' => $data['z'] ?? null,
			'cummulativeQuoteQty' => $data['z'] !== null && $data['L'] !== null 
									 ? bcmul($data['z'], $data['L'], 8)
									 : '0', #TODO cumuler les trades partiels
			'status' => $data['X'] ?? null,
			'timeInForce' => $data['f'] ?? null,
			'type' => $data['o'] ?? null,
			'side' => $data['S'] ?? null,
			'stopPrice' => $data['sp'] ?? null,
			'icebergQty' => $data['Q'] ?? null,
			'time' => $data['E'] ?? null,
			'updateTime' => $data['T'] ?? null,
			'isWorking' => isset($data['w']) ? (int)$data['w'] : 1,
			'origQuoteOrderQty' => $data['Z'] ?? null,
			'workingTime' => $data['W'] ?? null,
			'selfTradePreventionMode' => $data['STPM'] ?? null, # only for futures
		];
	}
	
	
	private static function executionReport_to_trade (array $data) : array
	{
		if (($data['x'] ?? '') !== 'TRADE' || !isset($data['t']) || $data['t'] < 0) {
			throw new ErrorException("should notconvert executionReport to trade if conditions are not met");
		}
	
		return [
			'id'=> $data['t'],
			'symbol' => $data['s'] ?? null,
			'orderId' => $data['i'] ?? null,
			'orderListId' => $data['g'] ?? -1, // OCO ou batch, sinon -1
			'price' => $data['L'] ?? null, // last executed price
			'qty' => $data['l'] ?? null,   // last executed quantity
			'quoteQty' => isset($data['l'], $data['L']) ? bcmul($data['l'], $data['L'], 8) : '0',
			'commission' => $data['n'] ?? '0',
			'commissionAsset' => $data['N'] ?? null,
			'time' => $data['T'] ?? null,
			'isBuyer' => isset($data['S']) && strtoupper($data['S']) === 'BUY' ? 1 : 0,
			'isMaker' => isset($data['m']) ? (int)$data['m'] : 0,
			'isBestMatch' => 1, // Spot = toujours best match
		];
	}
	
	
	/**
	 * get symbols prices every seconds, store them into DB
	 */
	public static function miniTicker ()
	{
		$f3 = Base::instance();
		$db = $f3->get ("db"); /** @var SQL $db */

		$binance_conf = Binance::get_conf ();
		$url = $binance_conf ["ws_url"] . "/!miniTicker@arr";
		$ws = new Client ($url, ['timeout' => 60]); #TODO same timeout for all WS

		while (1 === 1) {
			try {
				# read message
				$msg = $ws->receive();
				$tickers = json_decode($msg, true);

				if (!empty($tickers) && is_array($tickers) && count($tickers) > 0) {
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
							echo ".";
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
			catch (TimeoutException $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] timeout" . PHP_EOL;
			}
			catch (Throwable $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] unknown exception " . $e::class . " : " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
			}
			sleep(5);
		}
	}
	
	
	/**
	 * get best bid & ask
	 */
	public static function bookTicker (string $symbol)
	{
		$f3 = Base::instance();
		
		#TODO check symbol exists
		$symbol = strtolower($symbol);

		$binance_conf = Binance::get_conf ();
		$url = $binance_conf ["ws_url"] . "/{$symbol}@bookTicker";
		$ws = new Client ($url);

		while (1 === 1) {
			try {
				# read message
				$msg = $ws->receive();
				$tickers = json_decode($msg, true);
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] book ticker : " . PHP_EOL;
				var_dump($tickers);
				echo PHP_EOL;
				// break;
				////////////////////////////
				/*
				array(6) {
					'u' =>
					int(946796400)
					's' =>
					string(7) "ETHUSDC"
					'b' =>
					string(13) "1967.67000000"
					'B' =>
					string(10) "5.41260000"
					'a' =>
					string(13) "1967.68000000"
					'A' =>
					string(10) "7.87790000"
				}

				*/


				/*
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
					
					echo ".";
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
				*/
			}
			catch (TimeoutException $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] timeout" . PHP_EOL;
			}
			catch (Throwable $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] unknown exception " . $e::class . " : " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
			}
			sleep(5);
		}
	}
	
}
