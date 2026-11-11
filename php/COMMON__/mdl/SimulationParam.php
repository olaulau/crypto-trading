<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SimulationParam extends Mdl
{
	
	public const string table = "simulation_param";
	
	protected $fieldConf = [
		'simulation_id' => [
			'belongs-to-one'	=> Simulation::class,
		],

		'name' => [
			'type'		=> Schema::DT_VARCHAR128,
			'nullable'	=> false,
			'default'	=> '',
		],
		'val' => [
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
		static::dropForeignKeyIfExists ("simulation_param_fk_simulation");
		$sql = "
			DROP INDEX IF EXISTS `simulation_param_fk_simulation` ON " . static::table . ";
			ALTER TABLE " . static::table . "
				ADD CONSTRAINT `simulation_param_fk_simulation` FOREIGN KEY (`simulation_id`) REFERENCES `simulation` (`id`);
			";
		$db->exec($sql);
		
		$sql = "
			DROP INDEX IF EXISTS uniq__simulation_id__name ON " . static::table . ";
			ALTER TABLE " . static::table . "
				ADD CONSTRAINT uniq__simulation_id__name UNIQUE (simulation_id, name); ";
		$db->exec($sql);
	}
	
}
