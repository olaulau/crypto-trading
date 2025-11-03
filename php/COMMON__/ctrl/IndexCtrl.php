<?php
namespace COMMON__\ctrl;

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
		# connect to sqlite data file
		$data_filename = "ExchangeHistoryDataCollector_1762037021.737952.data";
		$data_full_path = __DIR__ . "/../../.." . "/" . $data_filename;
		$db = new \DB\SQL("sqlite:" . $data_full_path);
		
		# read ohlcv data
		$sql = "SELECT * FROM ohlcv";
		$data = $db->exec($sql);
		
		# extract infos
		foreach ($data as &$row) {
			$candle = $row ["candle"];
			$candle = json_decode($candle);
			$candle = [
				"timestamp"	=> $candle [0],
				"open"		=> $candle [1],
				"high"		=> $candle [2],
				"low"		=> $candle [3],
				"close"		=> $candle [4],
				"volume"	=> $candle [5],
			];
			$row ["candle"] = $candle;
		}
		// var_dump($data);
		
		# config
		$sell_min_margin = 4;
		$sell_floor_margin = 2;
		$buy_min_margin = 4;
		$buy_floor_margin = 2;
		$start_ETH = 1;
		$start_EUR = 0;
		
		# start variables
		$ETH = $start_ETH;
		$EUR = $start_EUR;
		$row = $data [0];
		$timestamp = $row ["timestamp"];
		$timestamp_formated = DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
		$value = $data [0] ["candle"] ["close"];
		$value_formated = number_format($value, 6, ",", " ");
		$reference_value = $value; # value of my last crypto movement
		$high = $value; # highest value since last action
		$low = $value; # lowest value since last action
		$start_total = $start_ETH * $value + $start_EUR;
		$converted = $ETH * $value;
		$converted_formated = number_format($converted, 6, ",", " ");
		
		echo "[{$timestamp_formated}] {$value_formated} simulation start <br/>" . PHP_EOL;
		echo "{$ETH} ETH => {$converted_formated} € <br/>" . PHP_EOL;
		echo " --- <br/>" . PHP_EOL;
		
		# Simple Moving Average
		$SMA_window_size = 50;
		$SMA_window = [];
		
		# simulation
		# rules : sell 100% when it raises by 4%, buy 100% when it drops 2%
		foreach ($data as $row) {
			$timestamp = $row ["timestamp"];
			$timestamp_formated = DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
			$value = $row ["candle"] ["close"];
			$value_formated = number_format($value, 6, ",", " ");
			
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
						$EUR_formated = number_format($EUR, 2, ",", " ");
						$ETH = 0;
						$low = $high = $reference_value = $value;
						echo "[{$timestamp_formated}] {$value_formated} : selling => {$EUR_formated} € <br/>" . PHP_EOL;
					}
				}
			}
			
			if($EUR > 0) { # I don't own crypto
				if ($value_ < ($reference_value * (1 - $buy_min_margin/100))) { # value dropped a lot
					if ($value_ > ($low * (1 + $buy_floor_margin / 100))) { # seems like we floored
						$ETH = $EUR / $value;
						$ETH_formated = number_format($ETH, 6, ",", " ");
						$EUR = 0;
						$low = $high = $reference_value = $value;
						echo "[{$timestamp_formated}] {$value_formated} : buying => {$ETH_formated} ETH <br/>" . PHP_EOL;
					}
				}
			}
		}
		
		# stats
		echo " --- <br/>" . PHP_EOL;
		$timestamp = $row ["timestamp"];
		$timestamp_formated = DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
		echo "[{$timestamp_formated}] simulation end <br/>" . PHP_EOL;
		$ETH_formated = number_format($ETH, 6, ",", " ");
		$converted = $ETH * $value;
		$converted_formated = number_format($converted, 6, ",", " ");
		echo "{$ETH_formated} ETH @ {$value_formated} => {$converted_formated}  <br/>" . PHP_EOL;
		$EUR_formated = number_format($EUR, 2, ",", " ");
		echo "{$EUR_formated} € <br/>" . PHP_EOL;
		
		$end_total = $ETH * $value + $EUR;
		$PandL = ($end_total - $start_total); # Profit and Loss
		$ROI = $PandL / $start_total; # Return On Investment
		$ROI_formated = number_format($ROI * 100, 2, ",", " ");
		echo " => ROI = {$ROI_formated} % ({$PandL} €) <br/>" . PHP_EOL;
		
		
		
		die;
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"test",
			"title"		=>	"Test",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
}
