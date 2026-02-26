<?php
namespace COMMON__\ctrl;

use Cache;
use COMMON__\mdl\KeyValue;
use COMMON__\svc\Process;
use COMMON__\svc\Stuff;
use DateTime;


class WatchdogCtrl extends Ctrl
{
	
	private const string pid_cache_key = "WatchdogCtrl__watchdogLauncher__pid";
	private const string lastUpdate_cache_key = "WatchdogCtrl__watchdogLauncher__lastUpdate";
	
	
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
		// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdogCached ()" . PHP_EOL;
		
		$cache_ttl = 5;

		# use FS cache to avoid frequent call costs
		$cache = new Cache;
		if ($cache->exists(static::lastUpdate_cache_key)) {
			// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : frequent call avoid" . PHP_EOL;
			return;
		}
		else {
			$cache->set(static::lastUpdate_cache_key, (new DateTime)->format(Stuff::datetime_sql_format));
		}

		# query DB to check last update
		$cache_ttl = 60;
		$last_update = KeyValue::getValue(static::lastUpdate_cache_key);
		
		if (!empty($last_update)) {
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update);
		}
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : last update expired : " . PHP_EOL;
			// var_dump($last_update_dt ?? null);
			static::watchdogLauncher();
			return;
		}

		# check if we have a PID
		$pid = KeyValue::getValue(static::pid_cache_key);
		if (empty($pid)) {
			// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : no pid in DB" . PHP_EOL;
			static::watchdogLauncher();
			return;
		}
		
		# check if the PID is still running
		$p = new Process;
		$p->setPid ($pid);
		if ($p->status() === false) {
			// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : PID not running" . PHP_EOL;
			static::watchdogLauncher();
			return;
		}
		
		// echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdog seems to be running fine" . PHP_EOL;

		#TODO can be running but hanged, so we have to kill it and re run
	}
	
	
	public static function watchdogKill () : void
	{
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdogKill ()" . PHP_EOL;
		
		# check if we have a PID
		$pid = KeyValue::getValue(static::pid_cache_key);
		if (empty($pid)) {
			echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] : no pid in DB" . PHP_EOL;
			return;
		}
		
		# kill the PID
		$p = new Process ();
		$p->setPid ($pid);
		$res = $p->stop();
		echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] : killed : " . PHP_EOL;
		var_dump($res);
		
		# remove the PID
		KeyValue::clearValue (static::pid_cache_key);
	}

	private static function watchdogLauncher () : void
	{
		echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] : watchdogLauncher ()" . PHP_EOL;
		
		# launch the script
		$p = new Process ("php index.php watchdog script >> tmp/log/watchdog.log 2>&1");
		#TODO log output doesn't work
		$pid = $p->getPid ();

		# store its pid into db
		KeyValue::setValue(static::pid_cache_key, $pid);
	}


	public static function watchdogScript () : void
	{
		CliCtrl::prepareCli ();
		
		echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : watchdogScript ()" . PHP_EOL;
		
		# while true loop
		while (1 === 1) {
			# store last update into db
			KeyValue::setValue(static::lastUpdate_cache_key, (new DateTime)->format(Stuff::datetime_sql_format));
			
			#TODO do stuff
			echo "[" . (new DateTime)->format(Stuff::datetime_sql_format) . "] : loop" . PHP_EOL;
			
			# sleep 5
			sleep (5);
		}
	}
	
}
