<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class Simulation extends Mdl
{
	
	public const string table = "stat";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'open_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
		],
		'close_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
		],

		'execution_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
		],
		'description' => [
			'type' => Schema::DT_TEXT,
			'nullable' => false,
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

}
