<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;
use ErrorException;


class Simulation extends Mdl
{
	
	public const string table = "simulation";
	
	protected $fieldConf = [
		'simulation_steps' => [
			'has-many'	=> [SimulationStep::class, 'simulation_id']
		],

		'symbol' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> '',
		],
		'open_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
			'default'	=> '1970-01-01 00:00:01',
		],
		'close_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
			'default'	=> '1970-01-01 00:00:01',
		],

		'execution_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
			'default'	=> '1970-01-01 00:00:01',
		],
		'description' => [
			'type'		=> Schema::DT_TEXT,
			'nullable'	=> false,
		],

		//TODO start money, end money, RoI
		
	];
	
	
	public static function setup ($db = null, $table = null, $fields = null) : void
	{
		parent::setup (); # auto create table
		
		# init 
		$f3 = Base::instance ();
		$db = $f3->get("db");
		/** @var SQL $db */

		# add indexes
		// $sql = "";
		// $db->exec($sql);
	}


	public function RoI () : float
	{
		$simulation_step_wrapper = new SimulationStep;
		$simulation_steps = $simulation_step_wrapper->find (["simulation_id = ?", $this->id], ["order" => "id ASC"]);
		if (empty ($simulation_steps)) {
			throw new ErrorException("simulation has no step");
		}

		$start_step = $simulation_steps [0];
		$start_base = $start_step->base_amount;
		$end_step = $simulation_steps [$simulation_steps->count() - 1];
		$end_base = $end_step->base_amount;

		$roi = ($end_base - $start_base) / $start_base;
		return $roi;
	}
}
