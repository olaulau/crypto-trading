<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class CapitalConfig extends Mdl
{
	
	public const string table = "capital_config";
	
	protected $fieldConf = [
		'coin' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'unique' => true,
		],
		'depositAllEnable' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'withdrawAllEnable' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'name' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'locked' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'freeze' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'withdrawing' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'ipoing' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'ipoable' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'storage' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'isLegalMoney' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'trading' => [
			'type' => Schema::DT_BOOL,
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
