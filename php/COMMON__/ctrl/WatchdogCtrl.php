<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\svc\Watchdog;


class WatchdogCtrl extends PrivateCtrl
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


	public static function watchdogRunGET (Base $f3, $url, $controler) : void
	{
		WatchdogCliCtrl::watchdogRun();
		$f3->reroute("@watchdog");
	}
	
	
	public static function watchdogKillGET (Base $f3, $url, $controler) : void
	{
		WatchdogCliCtrl::watchdogKill();
		$f3->reroute("@watchdog");
	}
	

	
	public static function watchdogGET (Base $f3, $url, $controler) : void
	{
		$global_watchdog = new Watchdog ("global");
		$f3->set ("global_watchdog", $global_watchdog);
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"watchdog",
			"title"		=>	"Watch dog",
			"breadcrumbs" => static::breadcrumbs(),
		];
		
		self::renderPage($page);
	}
	
}
