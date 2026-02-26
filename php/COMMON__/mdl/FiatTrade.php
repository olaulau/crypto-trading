<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class FiatTrade extends Mdl
{
	
	public const string table = "fiat_trade";
	
	protected $fieldConf = [
		'transactionType' => [
			'type' => Schema::DT_INT1,
			'nullable' => false,
			'index' => true,
		],
		'orderNo' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'fiatCurrency' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'indicatedAmount' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'amount' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'totalFee' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'method' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'status' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'createTime' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'updateTime' => [
			'type' => Schema::DT_BIGINT,
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
