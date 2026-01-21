<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SpotTrade extends Mdl
{
	
	public const table = "spot_trade";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
		],
		'id' => [
			'type' => Schema::DT_INT,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'orderId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'orderListId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'price' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'qty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'quoteQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'commission' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'commissionAsset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'time' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'isBuyer' => [
			'type' => Schema::DT_INT1,
			'nullable' => false,
		],
		'isMaker' => [
			'type' => Schema::DT_INT1,
			'nullable' => false,
		],
		'isBestMatch' => [
			'type' => Schema::DT_INT1,
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
