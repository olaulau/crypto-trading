<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;


class Kline extends Mdl
{
	
	public const table = "kline";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => 'VARCHAR128',
			'nullable' => false,
			// 'index' => true,
		],
		'candle_size' => [
			'type' => 'VARCHAR128',
			'nullable' => false,
			// 'index' => true,
		],
		
		'open_time' => [
			'type' => 'DATETIME',
			'nullable' => false,
			// 'index' => true,
		],
		'open' => [
			'type' => 'FLOAT',
			'nullable' => false,
			// 'index' => true,
		],
		
		'high' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'low' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'close' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'volume' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'close_time' => [
			'type' => 'DATETIME',
			'nullable' => false,
		],
		'quote_asset_volume' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'number_of_trades' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'taker_buy_base_asset_volume' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'taker_buy_quote_asset_volume' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
		'ignore' => [
			'type' => 'FLOAT',
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
		$sql = "
			DROP INDEX IF EXISTS uniq__symbol__candle_size__open_time ON " . Kline::table . ";
			ALTER TABLE " . Kline::table . "
				ADD CONSTRAINT uniq__symbol__candle_size__open_time UNIQUE (symbol, candle_size, open_time); ";
		$db->exec($sql);
	}
	
}
