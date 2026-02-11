<?php
namespace COMMON__\mdl;

use DB\SQL\Schema;


class OrderList extends Mdl
{
	
	public const table = "order_list";
	
	protected $fieldConf = [
		'orderListId' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'contingencyType' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'listStatusType' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'listOrderStatus' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'listClientOrderId' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'transactionTime' => [
			'type' => Schema::DT_BIGINT,
			'nullable' => false,
		],
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
	];
	
}
