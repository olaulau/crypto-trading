<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class KeyValue extends Mdl
{
	
	public const table = "key_value";
	
	protected $fieldConf = [
		'key' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'value' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
	];
	
	
	public static function setup ($db = null, $table = null, $fields = null)
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
