<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\AssetDividend;
use COMMON__\mdl\Balance;
use COMMON__\mdl\CapitalConfig;
use COMMON__\mdl\ConvertTrade;
use COMMON__\mdl\FiatTrade;
use COMMON__\mdl\KeyValue;
use COMMON__\mdl\Kline;
use COMMON__\mdl\Order;
use COMMON__\mdl\OrderList;
use COMMON__\mdl\Simulation;
use COMMON__\mdl\SimulationStep;
use COMMON__\mdl\SpotExchangeSymbol;
use COMMON__\mdl\SpotTrade;
use COMMON__\mdl\Stat;
use COMMON__\svc\Binance;
use COMMON__\svc\Buffer;
use COMMON__\svc\Stuff;
use DateTime;
use DateTimeZone;
use DB\SQL;
use ErrorException;
use Exception;
use Throwable;


class IndexCtrl extends PrivateCtrl
{
	
	public final static $binance_data_directory = __DIR__ . "/../../../data/binance/";
	public final static $symbol = "ETHEUR";
	public final static $small_candle_size = "15m";
	public final static $sql_read_limit = 10000;
	public final static $start_sql = "2025-01-01 00:00:00";
	public final static $end_sql = "2025-01-31 23:59:59";
	// public final static $end_sql = "2025-12-31 23:59:59";

	public final static $stats = [
		//TODO use index in code, and remove "name" property
		"SMA4" => [
			"type"		=> "SMA",
			"size"		=> 4,
			"name"		=> "SMA4",
		],
		"SMA10" => [
			"type"		=> "SMA",
			"size"		=> 10,
			"name"		=> "SMA10",
		],
		"SMA25" => [
			"type"		=> "SMA",
			"size"		=> 25,
			"name"		=> "SMA25",
		],
		"SMA100" => [
			"type"		=> "SMA",
			"size"		=> 100,
			"name"		=> "SMA100",
		],
	];
	

	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}
	
	
	public static function afterRoute ()
	{
		parent::afterRoute();
	}

	
	public static function breadcrumbs ()
	{
		$res = [];
		return $res;
	}
	
	public static function indexGET (Base $f3, $url, $controler)
	{
		# run watchdog
		WatchdogCliCtrl::watchdogRun (); #TODO can produce some space / display
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"index",
			"title"		=>	"Accueil",
			"breadcrumbs" => static::breadcrumbs(),
		];
		
		self::renderPage($page);
	}


	
	public static function dbCleanGET (Base $f3, $url, $controler)
	{
		# cleanup
		AssetDividend::drop_table();
		Balance::drop_table();
		CapitalConfig::drop_table();
		ConvertTrade::drop_table();
		FiatTrade::drop_table();
		KeyValue::drop_table();
		
		SimulationStep::drop_table();
		Simulation::drop_table();
		Kline::drop_table();
		
		Order::drop_table();
		OrderList::drop_table();
		SpotExchangeSymbol::drop_table();
		SpotTrade::drop_table();
		
		echo "Ok.";
	}

	public static function dbSetupGET (Base $f3, $url, $controler)
	{
		# create DB struct
		AssetDividend::setup();
		Balance::setup();
		CapitalConfig::setup();
		ConvertTrade::setup();
		FiatTrade::setup();
		KeyValue::setup();
		Kline::setup();
		Order::setup();
		OrderList::setup();
		Simulation::setup();
		SimulationStep::setup();
		SpotExchangeSymbol::setup();
		SpotTrade::setup();
		Stat::setup();
		
		echo "Ok.";
	}
	
	
	public static function downloadGET (Base $f3, $url, $controler)
	{
		// https://data.binance.vision/?prefix=data/spot/monthly/klines/ETHEUR/15m/
		$base_path = "https://data.binance.vision/data/spot/monthly/klines/ETHEUR/15m/";
		$start_year = 2020; #TODO list file on server and detect a valid start period ?
		$start_dt = new DateTime ("first day of January {$start_year}", new DateTimeZone ("Europe/Paris"));
		$end_dt = new DateTime ("last day of previous month", new DateTimeZone ("Europe/Paris"));
		
		while (!$start_dt->diff ($end_dt)->invert) {
			$month = $start_dt->format ("Y-m");
			$filename = static::$symbol."-".static::$small_candle_size."-{$month}"; //TODO .zip
			$url = "{$base_path}{$filename}.zip";
			$dest = static::$binance_data_directory . $filename; #TODO test var refacto
			
			if (!file_exists ("{$dest}.csv")) {
				if (!file_exists ("{$dest}.zip")) {
					try {
						echo "downloading {$url} <br/>" . PHP_EOL;
						Stuff::download_to_disk ($url, "{$dest}.zip");
					}
					catch (Exception $ex) {
						echo "download failed : " . $ex->getMessage() . " <br/>" . PHP_EOL;
						# clean near zero byte badly downloaded files
						@unlink ("{$dest}.zip");
					}
				}
				
				if (file_exists ("{$dest}.zip")) {
					#TODO verify CHECKSUM
					echo "extracting {$filename}.zip <br/>" . PHP_EOL;
					exec("cd " . escapeshellarg (static::$binance_data_directory) . "; unzip " . escapeshellarg ("{$dest}.zip") . " 2>&1", $output, $result_code);
					// var_dump($result_code, $output); die;
					if ($result_code !== 0) {
						echo "extract failed : <br/>" . PHP_EOL;
						var_dump ($output);
						@unlink ("{$dest}.csv");
					}
					else {
						# remove zip file
						@unlink ("{$dest}.zip");
					}
				}
			}
			
			$start_dt->modify ("+1 month");
		}
		
		echo "end. <br>" . PHP_EOL;
		exit;
	}
	
	
	public static function importGET (Base $f3, $url, $controler)
	{
		# init
		set_time_limit (0);
		$db = $f3->get ("db"); /** @var SQL $db */
		
		# lookup for CSV files
		$files = glob (static::$binance_data_directory . "/*.csv");
		asort ($files);

		$start_dt = new DateTime (static::$start_sql);
		$end_dt = new DateTime (static::$end_sql);
		
		foreach ($files as $file) {
			$start_time = microtime (true);
			
			# extract infos from filename
			$filebase = basename ($file);
			$regex = '/([[:alpha:]]+)-([[:digit:]]+[[:alpha:]])-([[:digit:]]{4})-([[:digit:]]{2})\.csv/'; // ETHEUR-15m-2020-01.csv
			$res = preg_match ($regex, $filebase, $matches);
			if ($res !== 1) {
				echo "import filename regex match error <br/>" . PHP_EOL;
				continue;
			}
			list (,$symbol, $candle_size, $year, $month) = $matches;

			# check date is in range
			$file_dt = new DateTime("{$year}-{$month}-01 00:00:00");
			if (($file_dt->getTimestamp() - $start_dt->getTimestamp()) < 0 || ($end_dt->getTimestamp() - $file_dt->getTimestamp()) < 0) {
				// echo "file is out of date range <br/>" . PHP_EOL;
				continue;
			};
			
			//TODO check data not already loaded

			# open CSV file
			echo "{$filebase} <br/>" . PHP_EOL;
			$fh = fopen ($file, "r");
			
			# read CSV rows
			$cpt = 0;
			$db->begin();
			while (false !== ($row = fgetcsv($fh, null, ",", '"', '\\'))) {
				$row = array_combine (Binance::kline_format, $row);
				
				# write into DB
				$kline = new Kline;
				$kline->copyfrom ($row);
				$kline->symbol = $symbol;
				$kline->candle_size = $candle_size;
				$kline->open_time = gmdate ('Y-m-d H:i:s', floor(Binance::to_real_timestamp($row ["open_time"]))); // UTC
				$kline->close_time = gmdate ('Y-m-d H:i:s', floor(Binance::to_real_timestamp($row ["close_time"])));
				try {
					$kline->save();
				}
				catch (Throwable $t) {
					echo $t->getMessage() . "<br/>" . PHP_EOL;
				}
				$cpt ++;
			}
			$db->commit();

			$end_time = microtime (true);
			$duration = ($end_time - $start_time) * 1000;
			$duration_txt = number_format ($duration, 2, ",", " ");
			$rate = $cpt / $duration * 1000;
			$rate_txt = number_format ($rate, 2, ",", " ");
			echo "{$cpt} rows in {$duration_txt} ms ({$rate_txt} / s) <br/>" . PHP_EOL;
			echo "<br/>" . PHP_EOL;
		}
		exit;
	}
	
	
	public static function candlesGET (Base $f3, $url, $controler)
	{
		ini_set ('max_execution_time', 0);

		foreach (Binance::candles as $big_candle_size => $big_candle_duration) {
			echo "<b>{$big_candle_size} = {$big_candle_duration}s</b> <br/>" . PHP_EOL;
			$candles_available = self::candles_available (static::$symbol, static::$start_sql, static::$end_sql);
			if (in_array ($big_candle_size, $candles_available)) {
				echo "candle already available <br/>" . PHP_EOL;
			}
			else {
				# choose biggest compatible candle
				$small_candle_size = null;
				$big_candle_duration = Binance::candles [$big_candle_size];;
				foreach ($candles_available as $candle) {
					if ($big_candle_size === $candle) { //TODO shouldn't come here, ERROR !
						echo "$candle $candle_duration is same <br/>" . PHP_EOL;
						continue; // don't try to recalculate ourself
					}
					$candle_duration = Binance::candles [$candle];
					$quotient = $big_candle_duration / $candle_duration;
					$reste = $big_candle_duration % $candle_duration;
					// echo "$candle $candle_duration $quotient $reste <br/>" . PHP_EOL;
					if ($quotient > 1 && $reste === 0) {
						$small_candle_size = $candle;
					}
				}
				
				if (empty($small_candle_size)) {
					echo "ERROR : no candle suitable for calculation of " . static::$symbol . " {$big_candle_size} <br/>" . PHP_EOL;
				}
				else {
					self::candle_calculate (static::$symbol, static::$start_sql, static::$end_sql, $small_candle_size, $big_candle_size);
				}
			}
			echo "<br/>" . PHP_EOL;
		}
		exit;
	}

	private static function candle_calculate (string $symbol, string $start, string $end, string $small_candle_size, string $big_candle_size)
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */

		# start reading data
		echo "computing {$symbol} candles from {$small_candle_size} to {$big_candle_size} ... <br/>" . PHP_EOL;
		$candle_seconds = Binance::candles [$big_candle_size];
		$offset = 0;
		$kline_wrapper = new Kline;

		$cpt_small_candle = $cpt_big_candle = 0;
		$start_time = microtime (true);
		while ($kline_wrapper->load(
			["symbol = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", $symbol, $small_candle_size, $start, $end],
			["order" => "open_time ASC", "limit" => static::$sql_read_limit, "offset" => $offset])) {
			$db->begin();
			do {
				if (empty ($big_candle)) { # first candle
					# clone first candle
					$big_candle = new Kline();
					$kline_casted = $kline_wrapper->cast();
					$kline_casted ["open_time"] = gmdate ('Y-m-d H:i:s', floor ($kline_casted ["open_time"]->getTimestamp())); // UTC
					$kline_casted ["close_time"] = gmdate ('Y-m-d H:i:s', floor ($kline_casted ["close_time"]->getTimestamp()));
					unset ($kline_casted ["_id"]);
					$big_candle->copyfrom ($kline_casted);
					$big_candle->candle_size = $big_candle_size;
				}
				else { # Nth candle
					$big_candle->aggregateWith ($kline_wrapper);

					$close_time = $kline_wrapper->close_time; /** @var DateTime $close_time */
					$close_ts = $close_time->getTimestamp();
					if ((($close_ts+1) % $candle_seconds) === 0) { ////////////////////////////////
						try {
							$big_candle->save();
						}
						catch (Throwable $t) {
							echo $t->getMessage() . " <br/>" . PHP_EOL;
						}
						$big_candle = null;
						$cpt_big_candle ++;
					}
				}
				$cpt_small_candle ++;
			}
			while ($kline_wrapper->next());
			$db->commit();
			
			$offset += static::$sql_read_limit;
			$kline_wrapper->reset();
		}
		
		$end_time = microtime (true);
		$duration = ($end_time - $start_time) * 1000;
		$duration_txt = number_format ($duration, 2, ",", " ");
		$rate = $cpt_big_candle / $duration * 1000;
		$rate_txt = number_format ($rate, 2, ",", " ");
		echo "{$cpt_big_candle} rows in {$duration_txt} ms ({$rate_txt} / s) <br/>" . PHP_EOL;
		echo "<br/>" . PHP_EOL;
	}

	public static function candles_available (string $symbol, string $start, string $end) : array
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		$sql = "
			SELECT	DISTINCT (candle_size)
			FROM	" . Kline::table . "
			WHERE	symbol = ?
			AND		open_time >= ?
			AND		close_time <= ?
		";
		$params = [$symbol, $start, $end];
		$data = $db->exec($sql, $params);
		$res = array_column($data, "candle_size");
		$res = array_intersect(array_keys(Binance::candles), $res); // sort $res by size ASC, like in Binance::candles
		return $res;
		
	}
	
	private static function candles_aggregate (Buffer $candles, string $big_candle_size) : ?Kline // TODO soon useless ?
	{
		if ($candles->empty()) {
			return null;
		}

		$res = new Kline;
		$res->symbol = static::$symbol;
		$res->candle_size = $big_candle_size;
		
		$first_candle = $candles->first();
		$last_candle = $candles->last();
		$res->open_time = $first_candle -> open_time;
		$res->open = $first_candle -> open;
		$res->close = $last_candle -> close;
		$res->close_time = $last_candle -> close_time;
		$res->ignore = 0;
		
		$res->high = $first_candle->high;
		$res->low = $first_candle->low;
		$res->volume = 0;
		$res->quote_asset_volume = 0;
		$res->number_of_trades = 0;
		$res->taker_buy_base_asset_volume = 0;
		$res->taker_buy_quote_asset_volume = 0;
		foreach ($candles->get_data() as $candle) {
			$res->high = max ($candle->high, $res->high);
			$res->low = min ($candle->low, $res->low);
			$res->volume += $candle->volume;
			$res->quote_asset_volume += $candle->quote_asset_volume;
			$res->number_of_trades += $candle->number_of_trades;
			$res->taker_buy_base_asset_volume += $candle->taker_buy_base_asset_volume;
			$res->taker_buy_quote_asset_volume += $candle->taker_buy_quote_asset_volume;
		}
		
		return $res;
	}
	
	
	public static function statisticsCalculateGET (Base $f3, $url, $controler)
	{
		$db = $f3->get("db"); /** @var SQL $db */
		ini_set ('max_execution_time', 0);
		
		# cleanup
		echo "cleaning up statistics for " . static::$symbol . " ... <br/>" . PHP_EOL;
		$sql = "
			DELETE FROM " . Stat::table . "
			WHERE	symbol = ?
		";
		$params = [static::$symbol];
		$res = $db->exec($sql, $params);

		$candles_available = self::candles_available (static::$symbol, static::$start_sql, static::$end_sql);
		if (empty ($candles_available)) {
			throw new ErrorException ("no candles available");
		}
		
		# calculate stat for smallest candle
		$small_candle = $candles_available [0];
		echo "calculating " . static::$symbol . " statistics from {$small_candle} candles ...<br/>" . PHP_EOL;
		$offset = 0;
		$kline_wrapper = new Kline;
		$stats_windows = [];
		$start_time = microtime (true);
		$cpt = 0;

		while ($kline_wrapper->load (
			["symbol = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", static::$symbol, $small_candle, static::$start_sql, static::$end_sql],
			["order" => "open_time ASC", "limit" => static::$sql_read_limit, "offset" => $offset])) {
			$db->begin();
			do {
				foreach (self::$stats as $stat_conf) {
					if (empty ($stats_windows [$stat_conf ["name"]])) {
						$stats_windows [$stat_conf ["name"]] = [];
					}
					$stat_window = &$stats_windows [$stat_conf ["name"]];
					
					if (count ($stat_window) >= $stat_conf ["size"]) {
						array_shift($stat_window);
					}
					array_push ($stat_window, $kline_wrapper ["open"]);
					$stat_value = array_sum ($stat_window) / count ($stat_window);
	
					$kline_casted = $kline_wrapper->cast();
					$kline_casted ["open_time"] = gmdate ('Y-m-d H:i:s', floor ($kline_casted ["open_time"]->getTimestamp())); // UTC
					unset ($kline_casted ["_id"]);
					$stat = new Stat;
					$stat->copyfrom ($kline_casted);
					$stat->name = $stat_conf ["name"];
					$stat->open = $stat_value;
					$stat->save();
					$cpt ++;
				}
			}
			while ($kline_wrapper->next());
			$db->commit();
			
			$offset += static::$sql_read_limit;
			$kline_wrapper->reset();
		}
		
		$end_time = microtime (true);
		$duration = ($end_time - $start_time) * 1000;
		$duration_txt = number_format ($duration, 2, ",", " ");
		$rate = $cpt / $duration * 1000;
		$rate_txt = number_format ($rate, 2, ",", " ");
		echo "{$cpt} rows in {$duration_txt} ms ({$rate_txt} / s) <br/>" . PHP_EOL;
		echo "<br/>" . PHP_EOL;
		
		// calculate stats for bigger candles based on previously calculated stat on smallest candle
		foreach ($candles_available as $big_candle) { //TODO we could also no start each from small candle ...
			if ($big_candle === $small_candle) {
				continue; # don't recalculate small candle
			}
			self::substat_calculate (static::$symbol, static::$start_sql, static::$end_sql, $small_candle, $big_candle);
		}
		exit;
	}
	
	private static function substat_calculate (string $symbol, string $start, string $end, string $small_candle_size, string $big_candle_size)
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */

		# start reading data
		echo "derivating {$symbol} stats from {$small_candle_size} to {$big_candle_size} ... <br/>" . PHP_EOL;
		$big_candle_duration = Binance::candles [$big_candle_size];
		$offset = 0;
		$stat_wrapper = new Stat;
		$start_time = microtime (true);
		$cpt = 0;

		while ($stat_wrapper->load(
			["symbol = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", $symbol, $small_candle_size, $start, $end],
			["order" => "open_time ASC", "limit" => static::$sql_read_limit, "offset" => $offset])) {
			$db->begin();
			do {
				$stat_casted = $stat_wrapper->cast();
				if ($stat_casted ["open_time"]->getTimestamp() % $big_candle_duration === 0) { # only on big candle timing
					# clone candle
					$big_candle = new Stat();
					$stat_casted ["open_time"] = gmdate ('Y-m-d H:i:s', floor ($stat_casted ["open_time"]->getTimestamp())); // UTC
					unset ($stat_casted ["_id"]);
					$big_candle->copyfrom ($stat_casted);
					$big_candle->candle_size = $big_candle_size;
					
					try {
						$big_candle->save();
					}
					catch (Throwable $t) {
						echo $t->getMessage() . " <br/>" . PHP_EOL;
					}
					$cpt ++;
				}
			}
			while ($stat_wrapper->next());
			$db->commit();
			
			$offset += static::$sql_read_limit;
			$stat_wrapper->reset();
		}
		
		$end_time = microtime (true);
		$duration = ($end_time - $start_time) * 1000;
		$duration_txt = number_format ($duration, 2, ",", " ");
		$rate = $cpt / $duration * 1000;
		$rate_txt = number_format ($rate, 2, ",", " ");
		echo "{$cpt} rows in {$duration_txt} ms ({$rate_txt} / s) <br/>" . PHP_EOL;
		echo "<br/>" . PHP_EOL;
	}
	
	
	public static function pricesAJAX (Base $f3, $url, $controler) #TODO useless ?
	{
		# init
		$db = $f3->get("db");
		/** @var SQL $db */
		
		# config
		$max_result = 1000;

		# query to select optimal candle size (< max_result)
		$sql = "
			SELECT		candle_size, COUNT(*) as nb
			FROM		kline
			WHERE		symbol = ?
			AND			? <= open_time
			AND			open_time <= ?
			GROUP BY	candle_size
			HAVING		nb <= ?
			ORDER BY	nb DESC
			LIMIT		1
		";
		$args = [
			static::$symbol,
			static::$start_sql,
			static::$end_sql,
			$max_result,
		];
		$data = $db->exec($sql, $args);
		
		if (count($data) === 0) {
			# query to select optimal candle size (> max_result)
			$sql = "
				SELECT		candle_size, COUNT(*) as nb
				FROM		kline
				WHERE		symbol = ?
				AND			? <= open_time
				AND			open_time <= ?
				GROUP BY	candle_size
				HAVING		nb > ?
				ORDER BY	nb ASC
				LIMIT		1
			";
			$args = [
				static::$symbol,
				static::$start_sql,
				static::$end_sql,
				$max_result,
			];
			$data = $db->exec($sql, $args);
		}

		if (count($data) === 0) {
			throw new ErrorException("no kline data for this crypto pair during this period");
		}
		$candle_size = $data [0] ["candle_size"];
		
		# query to load data
		$sql = "
			SELECT	open_time AS x, open AS y
			FROM	kline
			WHERE	symbol = ?
			AND		candle_size = ?
			AND		? <= open_time
			AND		open_time <= ?
		";
		$args = [
			static::$symbol,
			$candle_size,
			static::$start_sql,
			static::$end_sql,
		];
		$data = $db->exec($sql, $args);
		
		# return result as json
		header('Content-Type: application/json');
		echo json_encode ($data);
		exit;
	}

	public static function testGET (Base $f3, $url, $controler)
	{
		# test if 15m candles are continous in DB (false as I suspected, due to mysql not storing timezone in datetime)
		$close_dt = null;
		$kline_wrapper = new Kline;
		$kline_wrapper->load(
			["symbol = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", "ETHEUR", "15m", "2025-01-01 00:00:00", "2025-12-31 23:59:59"],
			["order" => "open_time ASC"]);
		do {
			if (!empty($close_dt)) {
				$open_dt = $kline_wrapper->open_time;
				if ($close_dt->getTimestamp() + 1 !== $open_dt->getTimestamp()) {
					echo "not contiguous : " . $close_dt->format(Stuff::datetime_sql_format) . " - " . $open_dt->format(Stuff::datetime_sql_format) .
						" => " . $close_dt->getTimestamp() . " - " . $open_dt->getTimestamp() . "<br/>" . PHP_EOL;
					$close_dt = null;
					continue;
				}
			}
			$close_dt = $kline_wrapper->close_time;
		}
		while ($kline_wrapper->next());

		die;

		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"test",
			"title"		=>	"test",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}

}
