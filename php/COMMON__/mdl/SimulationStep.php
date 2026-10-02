<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SimulationStep extends Mdl
{
	
	public const string table = "stat";
	
	protected $fieldConf = [
		'simulation_id' => [ //TODO relation
			'type' => Schema::DT_INT,
			'nullable' => false,
		],

		'time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
		],
		'order_type' => [ // sell / buy
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
		],
		'amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
		],
		'amount_money' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
		],
		'couterpart' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
		],
		'counterpart_money' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
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
