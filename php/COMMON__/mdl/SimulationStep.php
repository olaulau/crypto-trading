<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SimulationStep extends Mdl
{
	
	public const string table = "simulation_step";
	
	protected $fieldConf = [
		'simulation_id' => [
			'belongs-to-one'	=> Simulation::class
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
		'base_amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'base_currency' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> '',
		],
		'quote_amount' => [
			'type'		=> Schema::DT_FLOAT,
			'nullable'	=> false,
			'default'	=> 0,
		],
		'quote_currency' => [
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
		static::dropForeignKeyIfExists ("simulation_step_fk_simulation");
		$sql = "
			DROP INDEX IF EXISTS `simulation_step_fk_simulation` ON `simulation_step`;
			ALTER TABLE `simulation_step`
				ADD CONSTRAINT `simulation_step_fk_simulation` FOREIGN KEY (`simulation_id`) REFERENCES `simulation` (`id`);
			";
		$db->exec($sql);
	}

}
