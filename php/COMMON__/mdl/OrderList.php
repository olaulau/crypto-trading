<?php
namespace COMMON__\mdl;

use DB\SQL\Schema;

/*
      'orderListId' => int 15369264296
      'contingencyType' => string 'OTO' (length=3)
      'listStatusType' => string 'ALL_DONE' (length=8)
      'listOrderStatus' => string 'ALL_DONE' (length=8)
      'listClientOrderId' => string 'web_6c46b7ae675440efb9bf8910dddc9fa4' (length=36)
      'transactionTime' => int 1761053423810
      'symbol' => string 'ETHEUR' (length=6)
      'orders' => 
        array (size=3)
		*/


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
