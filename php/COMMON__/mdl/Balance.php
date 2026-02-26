<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class Balance extends Mdl
{
	
	public const string table = "balance";
	
	protected $fieldConf = [
		'asset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'lastUpdated' => [
			'type' => Schema::DT_DATETIME,
			'nullable' => false,
		],
		'free' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'locked' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
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
