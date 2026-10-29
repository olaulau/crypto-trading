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
		'roi' => [
			'type'		=> Schema::DT_DECIMAL,
			'nullable'	=> true,
		],
		'volume' => [
			'type'		=> Schema::DT_DECIMAL,
			'nullable'	=> true,
		],
		//TODO start money, end money
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


	public function get_roi () : float
	{
		$simulation_steps = $this->simulation_steps;
		if (empty($simulation_steps) || $simulation_steps->count () === 0) {
			throw new ErrorException ("simulation has no step");
		}

		$start_step = $simulation_steps [0];
		$start_base = $start_step->quote_amount;
		$end_step = $simulation_steps [$simulation_steps->count() - 1];
		$end_base = $end_step->quote_amount;
		
		$roi = ($end_base - $start_base) / $start_base;
		return $roi;
	}
	
	
	public function get_volume () : float
	{
		$simulation_steps = $this->simulation_steps;
		if ($simulation_steps->count () === 0) {
			throw new ErrorException ("simulation has no step");
		}

		$res = 0.0;
		foreach ($simulation_steps as $simulation_step) {
			if ($simulation_step->is_transaction()) {
				$res += $simulation_step->quote_amount;
			}
		}
		return $res;
	}
	
	
	public function erase ($filter=null) : void
	{
		$simulation_step_wrapper = new SimulationStep;
		$simulation_steps = $simulation_step_wrapper->find (["simulation_id = ?", $this->id], []);
		foreach ($simulation_steps as $simulation_step) {
			$simulation_step->erase ();
		}
		parent::erase();
	}
}
