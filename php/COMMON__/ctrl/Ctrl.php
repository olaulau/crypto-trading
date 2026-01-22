<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\KeyValue;
use COMMON__\svc\CSRF;
use DB\SQL;
use Log;
use PDO;
use Session;
use View;


abstract class Ctrl
{
	
	public static function beforeRoute ()
	{
		# check npm
		if (! file_exists(__DIR__ . "/../../../node_modules/")) {
			die("no '/node_modules/' directory : please run 'npm install' before using this web app");
		}
		
		# check config
		if (! file_exists(__DIR__ . "/../../../conf/user.ini")) {
			die("no '/conf/user.ini' file : please run 'cp conf/user.dist.ini conf/user.ini' and fill-in values");
		}
		$f3 = Base::instance();
		$db = $f3->get("db");
		if (empty($db ["name"]) || empty($db ["user"]) || empty($db ["password"])) {
			die("'db' conf in 'conf/user.ini' is missing : please fill-in values");
		}
		$binance = $f3->get("binance");
		if (empty($binance ["key"]) || empty($binance ["secret"])) {
			die("'binance' conf in 'conf/user.ini' is missing : please fill-in values");
		}
		
		// extend display of xdebug's var_dump
		ini_set("xdebug.var_display_max_children", 1024);
		ini_set("xdebug.var_display_max_data", 2048);
		ini_set("xdebug.var_display_max_depth", 10);
		
		// exposes $f3 var in views
		$f3->set("f3", $f3);
		
		// initialise logger
		$log = new Log('logs.log');
		$f3->set("log", $log);
		
		// connect DB
		if(!empty($f3->get("db.user"))) {
			$db = new SQL(
				$f3->get("db.type").":host=".$f3->get("db.host").";port=".$f3->get("db.port").";dbname=".$f3->get("db.name"),
				$f3->get("db.user"), $f3->get("db.password"),
				[
					PDO::ATTR_PERSISTENT => true,
				]
			);
			$db->log(false);
			
			# check DB
			$sql = "
				SELECT	1
				FROM	information_schema.tables
				WHERE	table_schema = ?
				AND		table_name = ?
				LIMIT	1;
			";
			$params = [
				$f3->get("db.name"),
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
			
			$f3->set("db", $db);
		}
		
		
		
		// initialise session (ignores suspect session : change in IP / useragent)
		$session = new Session(
			function(Session $session, $id) // onsuspeect
			{
				return true;
			}
		);
		$f3->set("session", $session);
		
		// CSRF
		$f3->CSRF = $f3->get("SESSION.CSRF");
		if(empty($f3->CSRF)) {
			CSRF::newToken();
		}
		
		// calculate project root
		$f3->set("PROJECT_ROOT", realpath(__DIR__ . "/../../../")); //TODO really usefull ?
	}
	
	
	public static function afterRoute ()
	{
		
	}
	
	
	public static function renderPage ($page)
	{
		$f3 = Base::instance();
		$f3->set("page", $page);
		
		$view = new View();
		echo $view->render("COMMON__/view/layout/" . $page["layout"] . "/index.phtml");
	}
	
	
	private static function pathDepth ($page)
	{
		$nb = substr_count($page["name"], "/");
		return $nb;
	}

	public static function relativePath ($page)
	{
		return str_repeat("../", self::pathDepth($page));
	}
	
	
	//TODO usefull ?
	public static function refreshPage ()
	{
		$f3 = Base::instance();
		$referer = $f3->get("SERVER.HTTP_REFERER");
		if(!empty($referer))
		{
			$f3->reroute($referer);
		}
		else
		{
			$f3->reroute();
		}
	}
	
	
	public static function renderAjax($data)
	{
		header("content-type:application/json");
		echo json_encode($data);
		die;
	}
	
}
