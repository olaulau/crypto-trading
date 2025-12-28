<?php
namespace COMMON__\ctrl;

use Base;

class AuthCtrl extends Ctrl
{
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}
	
	
	public static function afterRoute ()
	{
		parent::afterRoute();
	}
	
	
	public static function breadcrumbs ()
	{
		$res = [];
		return $res;
	}
	
	
	public static function loginGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"simple",
			"name"		=>	"login",
			"title"		=>	"Login",
			"breadcrumbs" => static::breadcrumbs(),
		];
		self::renderPage($page);
	}
	
	
	public static function loginPOST (Base $f3, $url, $controler)
	{
		$login = $f3->get("POST.login");
		$password = $f3->get("POST.password");
		$res = false;
		if($login === $f3->get("auth.login")) {
			$res = password_verify($password, $f3->get("auth.password_bcrypt"));
		}
		if(!$res) {
			sleep(3);
			$f3->reroute("@login");
		}
		else {
			$f3->set("SESSION.auth.login", $login);
			$f3->reroute("@homepage");
		}
	}
	
	
	public static function logoutGET (Base $f3, $url, $controler)
	{
		$f3->clear("SESSION.auth.login");
		$f3->reroute("@homepage");
	}
	
}
