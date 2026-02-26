<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class AssetDividend extends Mdl
{
	
	public const string table = "asset_dividend";
	
	protected $fieldConf = [
		'id2' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
			'index' => true,
		],
		'tranId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'asset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'amount' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'divTime' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'enInfo' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'direction' => [
			'type' => Schema::DT_TINYINT,
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
