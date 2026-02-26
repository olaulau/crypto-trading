<?php
namespace COMMON__\mdl;

use Base;
use DB\SQL;
use DB\SQL\Schema;


class KeyValue extends Mdl
{
	
	public const string table = "key_value";
	
	protected $fieldConf = [
		'key' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
			'index' => true,
			'unique' => true,
		],
		'value' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
	];
	
	
	public static function setup ($db = null, $table = null, $fields = null) : void
	{
		parent::setup (); # auto create table
		
		# init 
		$f3 = Base::instance ();
		$db = $f3->get("db");
		/** @var SQL $db */

		# add indexes
		// $sql = "";
		// $db->exec($sql);
	}
	
	
	public static function getValue (string $key) : ?string
	{
		$kv = static::findOneBy("key", $key);
		return $kv->value ?? null;
	}
	
	
	public static function setValue (string $key, string $value) : void
	{
		$kv = new KeyValue;
		$kv->load(["key = ?", $key]);
		$kv->key = $key;
		$kv->value = $value;
		$kv->save ();
	}
	
	
	public static function clearValue (string $key) : void
	{
		$kv = new KeyValue;
		$kv->load(["key = ?", $key]);
		if(!$kv->dry()) {
			$kv->erase();
		}
	}
	
}
