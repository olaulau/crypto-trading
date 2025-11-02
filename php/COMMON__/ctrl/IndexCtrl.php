<?php
namespace COMMON__\ctrl;


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
		$data_filename = "ExchangeHistoryDataCollector_1762037021.737952.data";
		$data_full_path = __DIR__ . "/../../.." . "/" . $data_filename;
		$db = new \DB\SQL("sqlite:" . $data_full_path);
		
		$sql = "SELECT * FROM ohlcv";
		$data = $db->exec($sql);
		// var_dump($data);
		
		foreach($data as $row) {
			// var_dump($row);
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
			// var_dump($candle);
			$value = $candle ["close"];
			var_dump($value);
		}
		
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
