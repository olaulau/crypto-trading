<?php
namespace COMMON__\mdl;

use Base;
use DateTime;
use DB\SQL;
use DB\SQL\Schema;


class Kline extends Mdl
{
	
	public const string table = "kline";
	
	protected $fieldConf = [
		'symbol' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		'candle_size' => [
			'type' => Schema::DT_VARCHAR128,
			'nullable' => false,
		],
		
		'open_time' => [
			'type'		=> Schema::DT_DATETIME,
			'nullable'	=> false,
		],
		'open' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
			// 'index' => true,
		],
		
		'high' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'low' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'close' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'volume' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'close_time' => [
			'type' => Schema::DT_DATETIME,
			'nullable' => false,
		],
		'quote_asset_volume' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'number_of_trades' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'taker_buy_base_asset_volume' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'taker_buy_quote_asset_volume' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
		'ignore' => [
			'type' => Schema::DT_FLOAT,
			'nullable' => false,
		],
	];
	
	
	public static function setup ($db = null, $table = null, $fields = null) 
	{
		parent::setup (); # auto create table
		
		# init 
		$f3 = Base::instance ();
		$db = $f3->get("db");
		/** @var SQL $db */

		# add indexes
		$sql = "
			DROP INDEX IF EXISTS uniq__symbol__candle_size__open_time ON " . Kline::table . ";
			ALTER TABLE " . Kline::table . "
				ADD CONSTRAINT uniq__symbol__candle_size__open_time UNIQUE (symbol, candle_size, open_time); ";
		$db->exec($sql);
	}
	
	
	public function aggregateWith (Kline $next) : void
	{
		$this_close_ts = (new DateTime($this->close_time))->getTimestamp();
		$next_open_ts = (new DateTime($next->open_time))->getTimestamp();
		if ($this_close_ts + 1 !== $next_open_ts) {
			echo "impossible kline aggregate : dates are not contiguous <br/>" . PHP_EOL;
			die;
		}
		
		// $this->open_time = $first_candle -> open_time;
		// $this->open = $first_candle -> open;
		$this->close = $next -> close;
		$this->close_time = $next -> close_time;
		// $this->ignore = 0;
		$this->high = max ($next->high, $this->high);
		$this->low = min ($next->low, $this->low);
		$this->volume += $next->volume;
		$this->quote_asset_volume += $next->quote_asset_volume;
		$this->number_of_trades += $next->number_of_trades;
		$this->taker_buy_base_asset_volume += $next->taker_buy_base_asset_volume;
		$this->taker_buy_quote_asset_volume += $next->taker_buy_quote_asset_volume;
	}
	
	
	public function getDuration () : int
	{
		return (new DateTime($this->close_time))->getTimestamp() - (new DateTime($this->open_time))->getTimestamp() + 1;
	}
	
}
