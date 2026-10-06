<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class Simulation extends Mdl
{
	
	public const string table = "simulation";
	
	protected $fieldConf = [
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

}
