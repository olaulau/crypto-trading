<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class Stat extends Mdl
{
	
	public const string table = "stat";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'candle_size' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'name' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			// 'index' => true,
		],
		
		'open_time' => [
			'type'		=> Schema::DT_TIMESTAMP,
			'nullable'	=> false,
		],
		'open' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
			// 'index' => true,
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
		$sql = "
			DROP INDEX IF EXISTS uniq__symbol__candle_size__open_time__name ON " . Stat::table . ";
			ALTER TABLE " . Stat::table . "
				ADD CONSTRAINT uniq__symbol__candle_size__open_time__name UNIQUE (symbol, candle_size, open_time, name); ";
		$db->exec($sql);
	}

}
