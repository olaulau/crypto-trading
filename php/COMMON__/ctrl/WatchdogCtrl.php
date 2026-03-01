<?php
namespace COMMON__\ctrl;

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


	public static function watchdogCached () : void
	{
		$w = new Watchdog ("global", 60, "php index.php watchdog global script >> tmp/log/watchdog.log 2>&1");
		$w->runCached();
	}
	
	
	public static function watchdogKill () : void
	{
		$w = new Watchdog ("global");
		$w->kill();
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
	
}
