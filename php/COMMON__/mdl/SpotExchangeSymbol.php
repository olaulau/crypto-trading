<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class SpotExchangeSymbol extends Mdl
{
	
	public const string table = "spot_exchange_symbol";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'status' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'baseAsset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'baseAssetPrecision' => [
			'type' => Schema::DT_INT,
			'nullable' => false,
		],
		'quoteAsset' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'quotePrecision' => [
			'type' => Schema::DT_INT,
			'nullable' => false,
		],
		'quoteAssetPrecision' => [
			'type' => Schema::DT_INT,
			'nullable' => false,
		],
		'baseCommissionPrecision' => [
			'type' => Schema::DT_INT,
			'nullable' => false,
		],
		'quoteCommissionPrecision' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		// orderTypes array
		'icebergAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'ocoAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'otoAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'quoteOrderQtyMarketAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'allowTrailingStop' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'cancelReplaceAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'amendAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'pegInstructionsAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'isSpotTradingAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'isMarginTradingAllowed' => [
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		// permissions
		// permissionSets
		'defaultSelfTradePreventionMode' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		// allowedSelfTradePreventionModes
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
