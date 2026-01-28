<?php
namespace COMMON__\ctrl;

use Base;
use Binance\API;


class CliCtrl extends Ctrl
{
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}


	public static function afterRoute ()
	{
		parent::afterRoute();
	}


	public static function testGET (Base $f3, $url, $controler) : void
	{
		# ignore deprecated
		error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

		# empty buffers
		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		# initiate
		$f3 = Base::instance();
		$binance_conf = $f3->get("binance");

		# get prices
		$api = new API ($binance_conf ["key"], $binance_conf ["secret"]);

		$api->miniTicker(function($api, $ticker) {
			print_r($ticker);
		});

		die;
	}
	
}
