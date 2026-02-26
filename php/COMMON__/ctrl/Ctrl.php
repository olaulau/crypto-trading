<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\KeyValue;
use DB\SQL;


abstract class Ctrl extends MiniCtrl
{
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
		
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		if (!empty ($db)) {
			# check DB content
			$sql = "
				SELECT	1
				FROM	information_schema.tables
				WHERE	table_schema = ?
				AND		table_name = ?
				LIMIT	1;
			";
			$params = [
				$f3->get("database.name"),
				KeyValue::table,
			];
			$res = $db->exec ($sql, $params);
			$alias = $f3->get("ALIAS");
			if ($alias !== "dbSetup") {
				if (empty($res [0] [1]) || $res [0] [1] !== 1) {
					$f3->reroute("@dbSetup");
					die;
				}
			}
		}
	}
	
	
	public static function afterRoute ()
	{
		
	}
	
}
