<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\svc\Stuff;
use COMMON__\svc\Watchdog;
use DateTime;


class WatchdogCtrl extends Ctrl
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


	public static function watchdogRun () : void
	{
		$w = new Watchdog ("global", 60, "php index.php watchdog global script >> tmp/log/watchdog.log 2>&1");
		$w->runCached();
	}
	
	public static function watchdogRunGET (Base $f3, $url, $controler) : void
	{
		static::watchdogRun();
		$f3->reroute("@watchdog");
	}
	
	
	public static function watchdogKill () : void
	{
		$w = new Watchdog ("global");
		$w->kill();
	}
	
	public static function watchdogKillGET (Base $f3, $url, $controler) : void
	{
		static::watchdogKill();
		$f3->reroute("@watchdog");
	}
	

	public static function watchdogGlobalScript () : void
	{
		CliCtrl::prepareCli ();
		
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdogScript ()" . PHP_EOL;
		
		$w = new Watchdog ("global");
		
		# while true loop
		while (1 === 1) {
			# store last update into db
			$w->markAsUpdated ();
			
			#TODO do stuff
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : loop" . PHP_EOL;
			
			# sleep 5
			sleep (5);
		}
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
