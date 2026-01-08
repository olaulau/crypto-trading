<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class ConvertTrade extends Mdl
{
	
	public const table = "convert_trade";
	
	protected $fieldConf = [
		'quoteId' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'unique' => true,
		],
		'orderId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'orderStatus' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'fromAsset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'toAsset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'toAmount' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'ratio' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'inverseRatio' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'createTime' => [
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
