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
		$up_pct = 3;
		$down_pct = 2;
		$start_ETH = 1;
		$start_EUR = 0;
		
		# start variables
		$ETH = $start_ETH;
		$EUR = $start_EUR;
		$row = $data [0];
		$timestamp = $row ["timestamp"];
		$timestamp_formated = DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
		$value = $data [0] ["candle"] ["close"];
		$reference_value = $value; # value of my last crypto movement
		$value_formated = number_format($value, 6, ",", " ");
		$start_total = $start_ETH * $value + $start_EUR;
		$converted = $ETH * $value;
		$converted_formated = number_format($converted, 6, ",", " ");
		echo "[{$timestamp_formated}] {$value_formated} simulation start <br/>" . PHP_EOL;
		echo "{$ETH} ETH => {$converted_formated} € <br/>" . PHP_EOL;
		echo " --- <br/>" . PHP_EOL;
		
		# simulation
		# rules : sell 100% when it raises by 4%, buy 100% when it drops 2%
		foreach ($data as $row) {
			$timestamp = $row ["timestamp"];
			$timestamp_formated = DateTimeImmutable::createFromTimestamp($timestamp)->format("Y-m-d H:i:s");
			$value = $row ["candle"] ["close"];
			$value_formated = number_format($value, 6, ",", " ");
			
			if($ETH > 0) { # I own crypto
				if ($value > ($reference_value * (1 + $up_pct/100))) { # value raised a lot => sell
					$EUR = $ETH * $value;
					$EUR_formated = number_format($EUR, 2, ",", " ");
					$ETH = 0;
					$reference_value = $value;
					echo "[{$timestamp_formated}] {$value_formated} : +{$up_pct}%, vente => {$EUR_formated} € <br/>" . PHP_EOL;
				}
			}
			
			if($EUR > 0) { # I don't own crypto
				if ($value < ($reference_value * (1 - $down_pct/100))) { # value dropped a lot => buy
					$ETH = $EUR / $value;
					$ETH_formated = number_format($ETH, 6, ",", " ");
					$EUR = 0;
					$reference_value = $value;
					echo "[{$timestamp_formated}] {$value_formated} : -{$down_pct}%, achat => {$ETH_formated} ETH <br/>" . PHP_EOL;
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
		$ROI = ($end_total - $start_total) / $start_total; # Return On Investment
		$ROI_formated = number_format($ROI * 100, 2, ",", " ");
		echo " => ROI = {$ROI_formated} % <br/>" . PHP_EOL;
		
		
		
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
