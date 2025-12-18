<?php
namespace COMMON__\mdl;


class Kline extends Mdl
{
	
	public const table = "kline";
	
	protected $fieldConf = [
		'crypto_pair' => [
			'type' => 'VARCHAR128',
			'nullable' => false,
			'index' => true,
		],
		'candle_size' => [
			'type' => 'VARCHAR128',
			'nullable' => false,
			'index' => true,
		],
		
		'open_time' => [
			'type' => 'DATETIME',
			'nullable' => false,
			'index' => true,
		],
		'open' => [
			'type' => 'FLOAT',
			'nullable' => false,
			'index' => true,
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
	
}
