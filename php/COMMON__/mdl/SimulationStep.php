<?php
namespace COMMON__\mdl;

use Base;
use COMMON__\svc\Stuff;
use DB\SQL;
use DB\SQL\Schema;
use ErrorException;


class SimulationStep extends Mdl
{
	
	public const string table = "simulation_step";
	
	protected $fieldConf = [
		'simulation_id' => [
			'belongs-to-one'	=> Simulation::class,
		],
		'kline_id' => [
			'belongs-to-one'	=> Kline::class,
		],

		'type' => [ // start / sell / buy / end
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'base_amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'quote_amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'description' => [
			'type'		=> Schema::DT_TEXT,
		],

	];
	
	
	public static function setup ($db = null, $table = null, $fields = null) : void
	{
		parent::setup (); # auto create table
		
		# init 
		$f3 = Base::instance ();
		$db = $f3->get("db");
		/** @var SQL $db */

		# add indexes
		static::dropForeignKeyIfExists ("simulation_step_fk_simulation");
		$sql = "
			DROP INDEX IF EXISTS `simulation_step_fk_simulation` ON `simulation_step`;
			ALTER TABLE `simulation_step`
				ADD CONSTRAINT `simulation_step_fk_simulation` FOREIGN KEY (`simulation_id`) REFERENCES `simulation` (`id`);
			";
		$db->exec($sql);

		static::dropForeignKeyIfExists ("simulation_step_fk_kline");
		$sql = "
		DROP INDEX IF EXISTS `simulation_step_fk_kline` ON `simulation_step`;
		ALTER TABLE `simulation_step`
			ADD CONSTRAINT `simulation_step_fk_kline` FOREIGN KEY (`kline_id`) REFERENCES `kline` (`id`);
		";
		$db->exec($sql);
	}
	
	
	public function previous () : SimulationStep
	{
		$simulation_step_wrapper = new SimulationStep;
		$time_formated = $this->kline_id->open_time->format(Stuff::datetime_sql_format);
		$simulation_step_wrapper->has ("kline_id", ["open_time < ?", $time_formated]);
		$res = $simulation_step_wrapper->find (["simulation_id = ?", $this->simulation_id->id], ["order" => "@kline_id.open_time DESC", "limit" => 1]);
		return $res[0];
	}

	
	public function margin () : float
	{
		$previous = $this->previous();
		if ($this->type === "buy") {
			$before = $previous->base_amount;
			$after = $this->base_amount;
		}
		elseif ($this->type === "sell") {
			$before = $previous->quote_amount;
			$after = $this->quote_amount;
		}
		else {
			throw new ErrorException ("can't calculate margin for step other than buy or sell");
		}
		return ($after - $before) / $before;
	}
	
	
	public function is_transaction () : bool
	{
		return in_array ($this->type, ["buy", "sell"]);
	}
	
}
