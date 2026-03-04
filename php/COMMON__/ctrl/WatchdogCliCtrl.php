<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\svc\Stuff;
use COMMON__\svc\Watchdog;
use DateTime;


class WatchdogCliCtrl extends Ctrl
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
		$w->runCached(); #TODO use $f3->alias("") for command;
	}
	
	public static function watchdogKill () : void
	{
		$w = new Watchdog ("global");
		$w->kill();
	}
	
	public static function watchdogGlobalScript () : void
	{
		CliCtrl::prepareCli ();

		$f3 = Base::instance();
		
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdogScript ()" . PHP_EOL;
		
		$watchdog_global = new Watchdog ("global");
		
		# while true loop
		while (1 === 1) {
			# store last update into db
			$watchdog_global->markAsUpdated ();
			
			#TODO do stuff
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : loop" . PHP_EOL;

			$watchdog_uds = new Watchdog ("uds", 60, "php index.php " . $f3->alias("wsUds") . " >> tmp/log/ws_uds.log 2>&1");
			$watchdog_uds->runCached(); #TODO test !
			#TODO add to watchdog page
			
			# sleep 5
			sleep (5);
		}
	}
	
}
