<?php
namespace COMMON__\mdl;


class Kline extends Mdl
{
	
	public const table = "kline";
	
	protected $fieldConf = [
		'open_date' => [
			'type' => 'DATETIME',
			'nullable' => false,
		],
		'price' => [
			'type' => 'FLOAT',
			'nullable' => false,
		],
	];
	
}
