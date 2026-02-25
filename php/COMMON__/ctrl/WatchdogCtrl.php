<?php
namespace COMMON__\ctrl;

use Base;
use Cache;
use COMMON__\mdl\KeyValue;
use COMMON__\svc\Process;
use COMMON__\svc\Stuff;
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
		$cache_class = "WatchdogCtrl";
		$cache_function = "watchdog";
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 5;

		# use FS cache to avoid frequent call costs
		$cache = new Cache;
		if ($cache->exists($cache_key)) {
			return;
		}
		else {
			$cache->set($cache_key, (new DateTime)->format(Stuff::datetime_sql_format));
		}

		# query DB to check last update
		$cache_ttl = 60;
		$last_update_o = new KeyValue ();
		$last_update_o->load (["key = ?", $cache_key]);
		if (!$last_update_o->dry()) {
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			static::watchdogScript();
			return;
		}

		# check if we have a PID
		$cache_key = "{$cache_class}__{$cache_function}__pid";
		$pid_o = new KeyValue ();
		$pid_o->load (["key = ?", $cache_key]);
		if ($pid_o->dry()) {
			static::watchdogScript();
			return;
		}
		
		# check if the PID is still running
		$pid = $pid_o->value;
		$p = new Process ();
		$p->setPid ($pid);
		if ($p->status() === false) {
			static::watchdogScript();
			return;
		}

		#TODO can be running but hanged, so we have to kill it and re run
	}

	private static function watchdog () : void
	{
		$cache_class = "WatchdogCtrl";
		$cache_function = "watchdog";
		$cache_key = "{$cache_class}__{$cache_function}__pid";

		# launch the script
		$p = new Process ("php index.php watchdog script");
		$pid = $p->getPid ();

		# store its pid into db
		$pid_o = new KeyValue ();
		$pid_o->load (["key = ?", $cache_key]);
		$pid_o->key = $cache_key;
		$pid_o->value = $pid;
		$pid_o->save ();
	}


	public static function watchdogScript () : void
	{
		$cache_class = "WatchdogCtrl";
		$cache_function = "watchdog";
		$cache_key = "{$cache_class}__{$cache_function}__last_update";

		# while true loop
		while (1 === 1) {
			# store last update into db
			$last_update_o = new KeyValue ();
			$last_update_o->load (["key = ?", $cache_key]);
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save ();
			
			#TODO do stuff

			# sleep 5
			sleep (5);
		}
	}
	
}
