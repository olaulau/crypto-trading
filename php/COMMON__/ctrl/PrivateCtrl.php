<?php
namespace COMMON__\ctrl;

use Base;

class PrivateCtrl extends Ctrl
{
	
	public static function beforeRoute ()
	{
		$f3 = Base::instance();
		parent::beforeRoute();
		$auth_login = $f3->get("SESSION.auth.login");
		if(empty($auth_login) || $auth_login !== $f3->get("auth.login")) {
			# force reroute to login if not connected
			$f3->reroute("@login");
		}
	}
	
	
	public static function afterRoute ()
	{
		parent::afterRoute();
	}
	
}
