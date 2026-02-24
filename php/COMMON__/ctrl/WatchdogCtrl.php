<?php
namespace COMMON__\ctrl;

use Base;


class WatchdogCtrl extends Ctrl
{
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}


	public static function afterRoute ()
	{
		parent::afterRoute();
	}


	public static function watchdogGET (Base $f3, $url, $controler) : void
	{
		static::watchdogCached ();
	}

	public static function watchdogCached () : void
	{
		# use FS cache to avoid frequent call costs

		# query DB cache to see if we have a pid

		# check if the PID is still running

		# check is the PId is working (lastUpdate recent)

		# the watch script if needed
	}
	
}
