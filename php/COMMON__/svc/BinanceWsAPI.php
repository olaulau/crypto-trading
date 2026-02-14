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

		# setup WS
		$listenKey = static::createListenKey ($binance_conf);
		$url = $binance_conf ["ws_url"] . "/$listenKey";
			
		$keepAliveInterval = 30 * 60; // 30 min
		$lastKeepAlive = time();
		
		while (1 === 1) {
			echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] created listen key : {$listenKey}" . PHP_EOL;
			try {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] WS : {$url}" . PHP_EOL;
				$ws = new Client ($url, ['timeout' => 10 * 60 * 60]); # timeout court pour check keep-alive

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
					if ($data['e'] === 'executionReport' && # order status update ~= 1 trade (with id ?)
						in_array ($data['X'], ['FILLED', 'PARTIALLY_FILLED'])) {

						$symbol = $data ['s'];
						$side   = $data ['S'];
						$price  = $data ['L'];
						$qty    = $data ['l'];
						$time   = date ('H:i:s', $data['T']/1000);

						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] order data : ($time) $symbol $side $qty @ $price" . PHP_EOL;
						
						#TODO get trade data via REST : get_order_trades_from_api
						#TODO insert DB
					}
					else {
						echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] other data : " . PHP_EOL;
						var_dump($data);
						/*
						array(34) {
  'e' =>
  string(15) "executionReport"
  'E' =>
  int(1770905271799)
  's' =>
  string(7) "ETHUSDT"
  'c' =>
  string(36) "web_fc7aad33303e44c5b6c8392dd213d90a"
  'S' =>
  string(3) "BUY"
  'o' =>
  string(5) "LIMIT"
  'f' =>
  string(3) "GTC"
  'q' =>
  string(10) "0.00500000"
  'p' =>
  string(13) "1000.00000000"
  'P' =>
  string(10) "0.00000000"
  'F' =>
  string(10) "0.00000000"
  'g' =>
  int(-1)
  'C' =>
  string(0) ""
  'x' =>
  string(3) "NEW"
  'X' =>
  string(3) "NEW"
  'r' =>
  string(4) "NONE"
  'i' =>
  int(3110296719)
  'l' =>
  string(10) "0.00000000"
  'z' =>
  string(10) "0.00000000"
  'L' =>
  string(10) "0.00000000"
  'n' =>
  string(1) "0"
  'N' =>
  NULL
  'T' =>
  int(1770905271799)
  't' =>
  int(-1)
  'I' =>
  int(7100556378)
  'w' =>
  bool(true)
  'm' =>
  bool(false)
  'M' =>
  bool(false)
  'O' =>
  int(1770905271799)
  'Z' =>
  string(10) "0.00000000"
  'Y' =>
  string(10) "0.00000000"
  'Q' =>
  string(10) "0.00000000"
  'W' =>
  int(1770905271799)
  'V' =>
  string(12) "EXPIRE_MAKER"
}
*/

/*
array(4) {
  'e' =>
  string(23) "outboundAccountPosition"
  'E' =>
  int(1770905271799)
  'u' =>
  int(1770905271799)
  'B' =>
  array(3) {
    [0] =>
    array(3) {
      'a' =>
      string(3) "ETH"
      'f' =>
      string(10) "0.00000000"
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
      string(13) "4985.00000000"
      'l' =>
      string(11) "15.00000000"
    }
  }
}
*/
					}
					
					#TODO other data type :
					# outboundAccountPosition : complete balance snapshot
					# balanceUpdate : small balance update
				}
			}
			catch (TimeoutException $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] timeout" . PHP_EOL;
				# recreate listenKey
				$listenKey = static::createListenKey ($binance_conf);
				$url = $binance_conf ["ws_url"] . "/$listenKey";
			}
			catch (Throwable $e) {
				echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] exception : " . $e->getCode() . " : " . $e->getMessage() . PHP_EOL;
				echo "reconnect in 5s ..." . PHP_EOL;
				sleep(5);

				# recreate listenKey
				$listenKey = static::createListenKey ($binance_conf);
				$url = $binance_conf ["ws_url"] . "/$listenKey";
			}
			#TODO recreate listenKey only if needed ?
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

}
