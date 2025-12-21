<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\Kline;
use COMMON__\svc\Binance;
use COMMON__\svc\Buffer;
use COMMON__\svc\Stuff;
use DateTime;
use DateTimeZone;
use DB\SQL;
use Exception;


class IndexCtrl extends Ctrl
{
	
	public final static $binance_data_directory = __DIR__ . "/../../../data/binance/";
	public final static $crypto_pair = "ETHEUR";
	public final static $candle_size = "15m";
	public final static $sql_read_limit = 10000;
	

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
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"index",
			"title"		=>	"Accueil",
			"breadcrumbs" => static::breadcrumbs(),
		];
		
		self::renderPage($page);
	}
	
	
	public static function downloadGET (Base $f3, $url, $controler)
	{
		// https://data.binance.vision/?prefix=data/spot/monthly/klines/ETHEUR/15m/
		$base_path = "https://data.binance.vision/data/spot/monthly/klines/ETHEUR/15m/";
		$start_year = 2020; #TODO list file on server and detect a valid start period ?
		$date = new DateTime("first day of January {$start_year}", new DateTimeZone("Europe/Paris"));
		$max = new DateTime("last day of previous month", new DateTimeZone("Europe/Paris"));
		
		while (!$date->diff($max)->invert) {
			$month = $date->format("Y-m");
			$filename = static::$crypto_pair."-".static::$candle_size."-{$month}";
			$url = "$base_path$filename.zip";
			$dest = static::$binance_data_directory . $filename; #TODO test var refacto
			
			if (!file_exists("$dest.csv")) {
				if (!file_exists("$dest.zip")) {
					try {
						echo "downloading $url <br/>" . PHP_EOL;
						Stuff::download_to_disk ($url, "$dest.zip");
					}
					catch (Exception $ex) {
						echo "download failed : " . $ex->getMessage() . " <br/>" . PHP_EOL;
						# clean near zero byte badly downloaded files
						@unlink("$dest.zip");
					}
				}
				
				if (file_exists("$dest.zip")) {
					#TODO verify CHECKSUM
					echo "extracting $filename <br/>" . PHP_EOL;
					exec("cd " . escapeshellarg(static::$binance_data_directory) . "; unzip " . escapeshellarg("$dest.zip") . " 2>&1", $output, $result_code);
					// var_dump($result_code, $output); die;
					if ($result_code !== 0) {
						echo "extract failed : <br/>" . PHP_EOL;
						var_dump($output);
						@unlink("$dest.csv");
					}
					else {
						# remove zip file
						@unlink("$dest.zip");
					}
				}
			}
			
			$date->modify("+1 month");
		}
		
		exit;
	}
	
	
	public static function importGET (Base $f3, $url, $controler)
	{
		# init
		set_time_limit(0);
		$db = $f3->get("db"); /** @var SQL $db */
		
		# cleanup
		$sql = "DROP TABLE IF EXISTS " . Kline::table;
		$db->exec($sql);
		
		# create DB struct
		Kline::setup();
		
		# lookup for CSV files
		$files = glob(static::$binance_data_directory . "/*.csv");
		asort($files);
		
		foreach ($files as $file) {
			// $start_time = microtime(true);
			# open CSV file
			echo basename($file) . "<br/>" . PHP_EOL;
			$fh = fopen($file, "r");
			
			# read CSV rows
			$db->begin();
			while (false !== ($row = fgetcsv($fh, null, ",", '"', '\\'))) {
				$row = array_combine(Binance::$kline_format, $row);
				
				# write into DB
				$kline = new Kline;
				$kline->copyfrom($row);
				$kline->crypto_pair = static::$crypto_pair;
				$kline->candle_size = static::$candle_size;
				$kline->open_time = Binance::timestamp_to_datetime($row ["open_time"])->format("Y-m-d H:i:s");
				$kline->close_time = Binance::timestamp_to_datetime($row ["close_time"])->format("Y-m-d H:i:s");
				$kline->save();
			}
			$db->commit();
			// $end_time = microtime(true);
			// $duration = ($end_time - $start_time) * 1000;
			// echo number_format($duration, 2, ",", " ") . " ms <br/>" . PHP_EOL;
		}
		
		exit;
	}
	
	
	public static function simulateGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"simulate",
			"title"		=>	"Simulate",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
	public static function trading_simulate () : void
	{
		# config
		$sell_min_margin = 4;
		$sell_floor_margin = 1;
		
		$buy_min_margin = 3;
		$buy_floor_margin = 2;
		
		$start_ETH = 1;
		$start_EUR = 0;
		
		$price_window_size = 100;
		
		$date_start = "2024-01-01 00:00:00";
		$date_end = "2024-12-31 23:59:59";
		
		# start reading data
		$offset = 0;
		$price_window = [];
		$kline_wrapper = new Kline;
		while ($kline_wrapper->load(["crypto_pair = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", static::$crypto_pair, static::$candle_size, $date_start, $date_end],
		["limit" => static::$sql_read_limit, "offset" => $offset])) {
			if ($offset === 0) {
				# start variables
				$ETH = $start_ETH;
				$EUR = $start_EUR;
				$timestamp_formated = $kline_wrapper ["open_time"];
				$price = $kline_wrapper ["open"];
				$price_formated = Stuff::format_float_significative($price, 6);
				$reference_price = $price; # value of my last crypto movement #TODO remove and use $sell_assets_history & $buy_assets_history
				$high = $price; # highest value since last action
				$low = $price; # lowest value since last action
				$start_total = $start_ETH * $price + $start_EUR;
				
				echo "[{$timestamp_formated}] ({$price_formated}) simulation start <br/>" . PHP_EOL;
				echo "<ul>" . PHP_EOL;
				if($ETH > 0) {
					$ETH_converted = $ETH * $price;
					$sell_assets_history = [$ETH_converted];
					$buy_assets_history = [$ETH];
					$ETH_converted_formated = Stuff::format_float_significative($ETH_converted, 6);
					echo "<li>{$ETH} ETH = {$ETH_converted_formated} € </li>" . PHP_EOL;
				}
				if($EUR > 0) {
					$EUR_converted = $EUR / $price;
					$sell_assets_history = [$EUR];
					$buy_assets_history = [$EUR_converted];
					$EUR_converted_formated = Stuff::format_float_significative($EUR_converted, 6);
					echo "<li>{$EUR} € = {$EUR_converted_formated} ETH </li>" . PHP_EOL;
				}
				echo "</ul>" . PHP_EOL;
				echo " <br/>" . PHP_EOL;
				echo " <hr>" . PHP_EOL;
				echo " <br/>" . PHP_EOL;
				$kline_wrapper->next();
			}
			
			do {
				# simulation
				$timestamp_formated = $kline_wrapper ["open_time"];
				$price = $kline_wrapper ["open"];
				$price_formated = Stuff::format_float_significative($price, 6);
				
				if (count($price_window) >= $price_window_size) { # window is full
					array_shift($price_window);
				}
				array_push($price_window, $price);
				$SMA_price = array_sum($price_window) / count($price_window);
				$price_ = $SMA_price; # we use SMA as smoothed price
				
				$high = max($price_, $high);
				$low = min($price_, $low);
				
				if ($ETH > 0) { # I own crypto
					if ($price_ > ($reference_price * (1 + $sell_min_margin/100))) { # price raised a lot
						if ($price_ < ($high * (1 - $sell_floor_margin / 100))) { # seems like we floored
							// if ($price > ($reference_price * (1 + $sell_min_margin/100))) { # also check current price
								?>
								<div class="text-end">
								<?php
								// echo "reference = $reference_price <br/>" . PHP_EOL;
								// echo "value_ = $value_ <br/>" . PHP_EOL;
								// echo "value = $value <br/>" . PHP_EOL;
								$EUR = $ETH * $price;
								$EUR_formated = Stuff::format_float_significative($EUR, 6);
								$ETH = 0;
								$low = $high = $reference_price = $price;
								$last_sell_assets = $sell_assets_history [array_key_last($sell_assets_history)];
								$delta_pct = ($EUR - $last_sell_assets) / $last_sell_assets * 100;
								$delta_pct_formated = stuff::percent_format($delta_pct);
								$sell_assets_history [] = $EUR;
								echo "[{$timestamp_formated}] ({$price_formated}) : selling --> {$EUR_formated} € ({$delta_pct_formated})" . PHP_EOL;
								?>
								</div>
								<?php
							// }
						}
					}
				}
				
				if ($EUR > 0) { # I own euros
					if ($price_ < ($reference_price * (1 - $buy_min_margin/100))) { # value dropped a lot
						if ($price_ > ($low * (1 + $buy_floor_margin / 100))) { # seems like we floored
							$ETH = $EUR / $price;
							$ETH_formated = Stuff::format_float_significative($ETH, 6);
							$EUR = 0;
							$low = $high = $reference_price = $price;
							$last_buy_assets = $buy_assets_history [array_key_last($buy_assets_history)];
							$delta_pct = ($ETH - $last_buy_assets) / $last_buy_assets * 100;
							$delta_pct_formated = stuff::percent_format($delta_pct);
							$buy_assets_history [] = $ETH;
							echo "[{$timestamp_formated}] ({$price_formated}) buying --> {$ETH_formated} ETH ({$delta_pct_formated}) <br/>" . PHP_EOL;
						}
					}
				}
				$last_kline = clone $kline_wrapper;
			}
			while ($kline_wrapper->next());
			
			$offset += static::$sql_read_limit;
			$kline_wrapper->reset();
		}
		
		
		# stats
		if(empty($last_kline)) {
			echo "no data loaded. </br>" . PHP_EOL;
			exit;
		}
		echo " <br/>" . PHP_EOL;
		echo " <hr/>" . PHP_EOL;
		echo " <br/>" . PHP_EOL;
		$timestamp_formated = $last_kline ["open_time"];
		echo "[{$timestamp_formated}] ({$price_formated}) simulation end <br/>" . PHP_EOL;
		echo "<ul>" . PHP_EOL;
		if ($ETH > 0) {
			$ETH_formated = Stuff::format_float_significative($ETH, 6);
			$ETH_converted = $ETH * $price;
			$ETH_converted_formated = Stuff::format_float_significative($ETH_converted, 6);
			echo "<li>{$ETH_formated} ETH @ {$price_formated} => {$ETH_converted_formated} € </li/>" . PHP_EOL;
		}
		if ($EUR > 0) {
			$EUR_formated = Stuff::format_float_significative($EUR, 6);
			echo "<li>{$EUR_formated} € </li>" . PHP_EOL;
		}
		echo "</ul>" . PHP_EOL;
		
		$end_total = $ETH * $price + $EUR;
		$PandL = ($end_total - $start_total); # Profit and Loss
		$PandL_formated = Stuff::EUR_format($PandL);
		$ROI = $PandL / $start_total; # Return On Investment
		$ROI_formated = Stuff::percent_format($ROI * 100, 2);
		echo "<b>==> ROI = {$ROI_formated} ({$PandL_formated})</b> <br/>" . PHP_EOL;
	}
	
	
	
	public static function candlesGET (Base $f3, $url, $controler)
	{
		# config
		$date_start = "2020-01-01 00:00:00";
		$date_end = "2025-12-31 23:59:59";
		$candle_size = "4h";
		
		# start reading data
		$candle_seconds = Binance::$candles [$candle_size];
		$buffer_size = $candle_seconds / Binance::$candles [static::$candle_size];
		$buffer = new Buffer ($buffer_size);
		$offset = 0;
		$kline_wrapper = new Kline;
		while ($kline_wrapper->load(["crypto_pair = ? AND candle_size = ? AND ? <= open_time AND open_time <= ?", static::$crypto_pair, static::$candle_size, $date_start, $date_end],
		["limit" => static::$sql_read_limit, "offset" => $offset])) {
			do {
				$open_time = $kline_wrapper->open_time; /** @var DateTime $open_time */
				$timestamp = $open_time->getTimestamp();
				if (($timestamp % $candle_seconds) === 0) {
					# create big candle
					$big_candle = static::candles_aggregate($buffer, $candle_size);
					$big_candle->save();
					$buffer->clear();
				}
				$buffer->push($kline_wrapper);
			}
			while ($kline_wrapper->next());
			
			$offset += static::$sql_read_limit;
			$kline_wrapper->reset();
		}
	}
	
	private static function candles_aggregate (Buffer $candles, string $big_candle_size) : Kline
	{
		$res = new Kline;
		$res->crypto_pair = static::$crypto_pair;
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
	
	
	public static function chartGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"chart",
			"title"		=>	"Chart",
			"breadcrumbs" => static::breadcrumbs(),
		];
		
		self::renderPage($page);
	}
	
}
