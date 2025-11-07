<?php
namespace COMMON__\ctrl;

use COMMON__\svc\Stuff;
use DateTimeImmutable;

class IndexCtrl extends Ctrl
{

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
	
	public static function indexGET (\Base $f3, $url, $controler)
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
	
	
	public static function testGET (\Base $f3, $url, $controler)
	{
		
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"test",
			"title"		=>	"Test",
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
		$data_filename = "data/binance_ETH-EUR_15m_2024-11-01_2025-111-01.sqlite"; # 2024-11-01 - 2025-11-01
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
				// echo "({$timestamp_formated}) : {$value} ~ {$value_} [ {$low} - {$high} ] <br/>" . PHP_EOL;
				
				if($ETH > 0) { # I own crypto
					if ($value_ > ($reference_value * (1 + $sell_min_margin/100))) { # value raised a lot
						if ($value_ < ($high * (1 - $sell_floor_margin / 100))) { # seems like we floored
							$EUR = $ETH * $value;
							$EUR_formated = Stuff::format_float_significative($EUR, 6);
							$ETH = 0;
							$low = $high = $reference_value = $value;
							$last_sell_assets = $sell_assets_history [array_key_last($sell_assets_history)];
							$delta_pct = ($EUR - $last_sell_assets) / $last_sell_assets * 100;
							$delta_pct_formated = stuff::percent_format($delta_pct);
							$sell_assets_history [] = $EUR;
							echo '<div class="text-end">' . "[{$timestamp_formated}] ({$value_formated}) : selling --> {$EUR_formated} € ({$delta_pct_formated}) </div>" . PHP_EOL;
						}
					}
				}
				
				if($EUR > 0) { # I own euros
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
