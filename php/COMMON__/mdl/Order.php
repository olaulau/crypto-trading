<?php
namespace COMMON__\mdl;

use DB\SQL\Schema;


class Order extends Mdl
{
	
	public const string table = "order";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'orderId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'orderListId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
			'index' => true,
		],
		'clientOrderId' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'price' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'origQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'executedQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'cummulativeQuoteQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'status' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'timeInForce' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'type' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'side' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'stopPrice' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'icebergQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'time' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'updateTime' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'isWorking' => [ // boolean
			'type' => Schema::DT_BOOL,
			'nullable' => false,
		],
		'origQuoteOrderQty' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'workingTime' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'selfTradePreventionMode' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
	];
	
}
