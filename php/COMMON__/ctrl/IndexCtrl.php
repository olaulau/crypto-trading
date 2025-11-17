<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\Kline;
use COMMON__\svc\Stuff;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use DB\SQL;
use ErrorException;

class IndexCtrl extends Ctrl
{
	
	public final static $binance_data_directory = __DIR__ . "/../../../data/binance/";
	
	public final static $kline_format = [
		"open_time",
		"open",
		"high",
		"low",
		"close",
		"volume",
		"close_time",
		"quote_asset_volume",
		"number_of_trades",
		"taker_buy_base_asset_volume",
		"taker_buy_quote_asset_volume",
		"ignore",
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
		$base_path = "https://data.binance.vision/data/spot/monthly/klines/ETHEUR/15m/";
		$start_year = 2020; #TODO list file on server and detect a valid start period ?
		$tick = "15m";
		$pair_str = "ETHEUR";
		$date = new DateTime("first day of January {$start_year}", new DateTimeZone("Europe/Paris"));
		$now = new DateTimeImmutable();
		while (!$date->diff($now)->invert) {
			$month = $date->format("Y-m");
			$filename = "{$pair_str}-{$tick}-{$month}.zip";
			$url = $base_path . $filename;
			
			$dest = static::$binance_data_directory . $filename; #TODO test var refacto
			
			# download and extract
			Stuff::download_to_disk ($url, $dest);
			exec("cd " . escapeshellarg(static::$binance_data_directory) . "; unzip " . escapeshellarg($dest) . " 2>&1", $output, $result_code);
			// var_dump($result_code, $output); die;
			
			$date->modify("+1 month");
		}
		die; #TODO display result
		
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"download",
			"title"		=>	"Download",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
	
	public static function importGET (Base $f3, $url, $controler)
	{
		# init
		$db = $f3->get("db"); /** @var SQL $db */
		set_time_limit(0);
		
		# cleanup
		$sql = "DELETE FROM kline";
		$db->exec($sql);
		
		# lookup for CSV files
		$files = glob(static::$binance_data_directory . "/*.csv");
		asort($files);
		
		foreach ($files as $file) {
			# open CSV file
			echo basename($file) . "<br/>" . PHP_EOL;
			$fh = fopen($file, "r");
			
			# read CSV rows
			$db->begin();
			while (false !== ($row = fgetcsv($fh, null, ",", '"', '\\'))) {
				$row = array_combine(static::$kline_format, $row);
				
				# convert timestamp
				$timestamp = $row ["open_time"];
				if(strlen($timestamp) === 16) {
					$timestamp = $timestamp / 1000000;
				}
				elseif(strlen($timestamp) === 13) {
					$timestamp = $timestamp / 1000;
				}
				elseif(strlen($timestamp) === 0) {
					# do nothing
				}
				else {
					throw new ErrorException("unknown timestamp format : {$timestamp}");
				}
				$d = DateTime::createFromFormat("U", $timestamp);
				
				# write into DB
				$kline = new Kline;
				$kline->open_date = $d->format("Y-m-d h:i:s");
				$kline->price = $row ["open"];
				$kline->save();
			}
			$db->commit();
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
		$start_ETH = 0;
		$start_EUR = 2322.99;
		$SMA_window_size = 100;
		$sqlite_read_limit = 10000;
		
		# connect to sqlite data file
		$data_filename = "data/octobot/binance_ETH-EUR_15m_2024-11-01_2025-111-01.sqlite"; # 2024-11-01 - 2025-11-01
		$data_full_path = __DIR__ . "/../../.." . "/" . $data_filename;
		$db = new \DB\SQL("sqlite:" . $data_full_path);
		
		# start reading data
		$offset = 0;
		$SMA_window = [];
		$ohlcv_wrapper = new \DB\SQL\Mapper($db, 'ohlcv');
		while ($ohlcv_wrapper->load(NULL, ["limit" => $sqlite_read_limit, "offset" => $offset])) {
			if ($offset === 0) {
				# start variables
				$ETH = $start_ETH;
				$EUR = $start_EUR;
				$timestamp = $ohlcv_wrapper ["timestamp"];
				$timestamp_formated = Stuff::timestamp_to_date_formated($timestamp);
				$candle = Stuff::extract_candle_infos($ohlcv_wrapper);
				$value = $candle ["close"];
				$value_formated = Stuff::format_float_significative($value, 6);
				$reference_value = $value; # value of my last crypto movement #TODO remove and use $sell_assets_history & $buy_assets_history
				$high = $value; # highest value since last action
				$low = $value; # lowest value since last action
				$start_total = $start_ETH * $value + $start_EUR;
				
				echo "[{$timestamp_formated}] ({$value_formated}) simulation start <br/>" . PHP_EOL;
				echo "<ul>" . PHP_EOL;
				if($ETH > 0) {
					$ETH_converted = $ETH * $value;
					$sell_assets_history = [$ETH_converted];
					$buy_assets_history = [$ETH];
					$ETH_converted_formated = Stuff::format_float_significative($ETH_converted, 6);
					echo "<li>{$ETH} ETH = {$ETH_converted_formated} € </li>" . PHP_EOL;
				}
				if($EUR > 0) {
					$EUR_converted = $EUR / $value;
					$sell_assets_history = [$EUR];
					$buy_assets_history = [$EUR_converted];
					$EUR_converted_formated = Stuff::format_float_significative($EUR_converted, 6);
					echo "<li>{$EUR} € = {$EUR_converted_formated} ETH </li>" . PHP_EOL;
				}
				echo "</ul>" . PHP_EOL;
				echo " <br/>" . PHP_EOL;
				echo " <hr>" . PHP_EOL;
				echo " <br/>" . PHP_EOL;
				$ohlcv_wrapper->next();
			}
			
			do {
				# simulation
				$timestamp = $ohlcv_wrapper ["timestamp"];
				$timestamp_formated = Stuff::timestamp_to_date_formated($timestamp);
				$candle = Stuff::extract_candle_infos($ohlcv_wrapper);
				$value = $candle ["close"];
				$value_formated = Stuff::format_float_significative($value, 6);
				
				if (count($SMA_window) >= $SMA_window_size) { # window is full
					array_shift($SMA_window);
				}
				array_push($SMA_window, $value);
				$SMA_value = array_sum($SMA_window) / count($SMA_window);
				$value_ = $SMA_value;
				
				$high = max($value_, $high);
				$low = min($value_, $low);
				
				if ($ETH > 0) { # I own crypto
					if ($value_ > ($reference_value * (1 + $sell_min_margin/100))) { # value raised a lot
						if ($value_ < ($high * (1 - $sell_floor_margin / 100))) { # seems like we floored
							// if ($value > ($reference_value * (1 + $sell_min_margin/100))) { # also check current value
								?>
								<div class="text-end">
								<?php
								// echo "reference = $reference_value <br/>" . PHP_EOL;
								// echo "value_ = $value_ <br/>" . PHP_EOL;
								// echo "value = $value <br/>" . PHP_EOL;
								$EUR = $ETH * $value;
								$EUR_formated = Stuff::format_float_significative($EUR, 6);
								$ETH = 0;
								$low = $high = $reference_value = $value;
								$last_sell_assets = $sell_assets_history [array_key_last($sell_assets_history)];
								$delta_pct = ($EUR - $last_sell_assets) / $last_sell_assets * 100;
								$delta_pct_formated = stuff::percent_format($delta_pct);
								$sell_assets_history [] = $EUR;
								echo "[{$timestamp_formated}] ({$value_formated}) : selling --> {$EUR_formated} € ({$delta_pct_formated})" . PHP_EOL;
								?>
								</div>
								<?php
							// }
						}
					}
				}
				
				if ($EUR > 0) { # I own euros
					if ($value_ < ($reference_value * (1 - $buy_min_margin/100))) { # value dropped a lot
						if ($value_ > ($low * (1 + $buy_floor_margin / 100))) { # seems like we floored
							$ETH = $EUR / $value;
							$ETH_formated = Stuff::format_float_significative($ETH, 6);
							$EUR = 0;
							$low = $high = $reference_value = $value;
							$last_buy_assets = $buy_assets_history [array_key_last($buy_assets_history)];
							$delta_pct = ($ETH - $last_buy_assets) / $last_buy_assets * 100;
							$delta_pct_formated = stuff::percent_format($delta_pct);
							$buy_assets_history [] = $ETH;
							echo "[{$timestamp_formated}] ({$value_formated}) buying --> {$ETH_formated} ETH ({$delta_pct_formated}) <br/>" . PHP_EOL;
						}
					}
				}
				$last_ohlcv = clone $ohlcv_wrapper;
			}
			while ($ohlcv_wrapper->next());
			
			$offset += $sqlite_read_limit;
			$ohlcv_wrapper->reset();
		}
		#TODO extract and store in mysql once, before permit backtest of big file such as yearly spot
		
		
		# stats
		echo " <br/>" . PHP_EOL;
		echo " <hr/>" . PHP_EOL;
		echo " <br/>" . PHP_EOL;
		$timestamp = $last_ohlcv ["timestamp"];
		$timestamp_formated = Stuff::timestamp_to_date_formated($timestamp);
		echo "[{$timestamp_formated}] ({$value_formated}) simulation end <br/>" . PHP_EOL;
		echo "<ul>" . PHP_EOL;
		if ($ETH > 0) {
			$ETH_formated = Stuff::format_float_significative($ETH, 6);
			$ETH_converted = $ETH * $value;
			$ETH_converted_formated = Stuff::format_float_significative($ETH_converted, 6);
			echo "<li>{$ETH_formated} ETH @ {$value_formated} => {$ETH_converted_formated} € </li/>" . PHP_EOL;
		}
		if ($EUR > 0) {
			$EUR_formated = Stuff::format_float_significative($EUR, 6);
			echo "<li>{$EUR_formated} € </li>" . PHP_EOL;
		}
		echo "</ul>" . PHP_EOL;
		
		$end_total = $ETH * $value + $EUR;
		$PandL = ($end_total - $start_total); # Profit and Loss
		$PandL_formated = Stuff::format_float_significative($PandL, 6, true);
		$ROI = $PandL / $start_total; # Return On Investment
		$ROI_formated = Stuff::percent_format($ROI * 100, 2);
		echo "<b>==> ROI = {$ROI_formated} ({$PandL_formated} €)</b> <br/>" . PHP_EOL;
	}
	
}
