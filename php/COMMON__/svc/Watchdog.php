<?php
namespace COMMON__\svc;

use COMMON__\mdl\KeyValue;
use COMMON__\svc\Process;
use COMMON__\svc\Stuff;
use DateTime;
use DateTimeInterface;


class Watchdog
{
	
	private string $pid_cache_key;
	private string $lastUpdate_cache_key;
	
	public function __construct (private string $name, private int $cache_ttl=60, private string $command="")
	{
		$this->pid_cache_key = "watchdog__{$this->name}__pid";
		$this->lastUpdate_cache_key = "watchdog__{$this->name}__lastUpdate";
	}



	public function isRunning () : bool
	{
		# query DB to check last update
		$last_update = KeyValue::getValue ($this->lastUpdate_cache_key);
		if (!empty ($last_update)) {
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update);
		}
		if (empty ($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $this->cache_ttl) {
			return false;
		}

		# check if we have a PID
		$pid = KeyValue::getValue ($this->pid_cache_key);
		if (empty ($pid)) {
			return false;
		}
		
		# check if the PID is still running
		$p = new Process;
		$p->setPid ($pid);
		if ($p->status() === false) {
			return false;
		}

		#TODO can be running but hanged, so we have to kill it and re run

		return true;
	}
	
	
	public function runCached () : void
	{
		if ($this->isRunning () === false) {
			$this->kill (); # may be hanged
			$this->launch ();
		}
	}


	public function kill () : void
	{
		echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] : watchdog {$this->name} kill ()" . PHP_EOL;
		
		# check if we have a PID
		$pid = KeyValue::getValue($this->pid_cache_key);
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
		KeyValue::clearValue ($this->pid_cache_key);
	}


	private function launch () : void
	{
		echo "[" . (new DateTime)->format (Stuff::datetime_sql_format) . "] : watchdog {$this->name} launch ()" . PHP_EOL;
		
		# launch the script
		$p = new Process ($this->command);
		
		# store its pid into db
		$pid = $p->getPid ();
		KeyValue::setValue ($this->pid_cache_key, $pid);
	}
	
	
	public function markAsUpdated () : void
	{
		KeyValue::setValue($this->lastUpdate_cache_key, (new DateTime)->format(Stuff::datetime_sql_format));
	}


	public function getLastUpdate () : ?DateTimeInterface
	{
		$last_update = KeyValue::getValue ($this->lastUpdate_cache_key);
		if (!empty ($last_update)) {
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update);
			return $last_update_dt;
		}
		else {
			return null;
		}
	}

}
