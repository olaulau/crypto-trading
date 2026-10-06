<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SimulationStep extends Mdl
{
	
	public const string table = "simulation_step";
	
	protected $fieldConf = [
		'simulation_id' => [ //TODO relation
			'type'		=> Schema::DT_INT,
			'nullable'	=> false,
			'default'	=> 0,
		],

		'time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
			'default'	=> '1970-01-01 00:00:01',
		],
		'order_type' => [ // sell / buy
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'amount_money' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> '',
		],
		'couterpart' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'counterpart_money' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> '',
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
		// $sql = "";
		// $db->exec($sql);
	}

}
