<?php
namespace COMMON__\svc;


class Accounting
{
	
	private static $symbols;
	private $accounts = [];
	
	public function __construct ()
	{
		static::$symbols = BinanceSpotApiCached::get_all_symbols();
	}
	
	
	public function exists_account (string $asset) : bool
	{
		return isset($this->accounts [$asset]);
	}
	
	public function create_account (string $asset) : void
	{
		$this->accounts [$asset] = new Account ($asset);
	}
	
	public function get_account (string $asset) : ?Account
	{
		return $this->accounts [$asset] ?? null;
	}
	
	public function add_to_account(string $asset, float $amount)
	{
		$this->get_account($asset)->add($amount);
	}
	
	public function get_account_quantity (string $asset)
	{
		return $this->get_account($asset) ->get_quantity();
	}
	
	
	public function execute_trade (array $trade) : void
	{
		$symbol = $trade ["symbol"];
		
		// $symbols = BinanceSpotApiCached::get_all_symbols();
		if(!isset(static::$symbols [$symbol])) {
			echo "$symbol from trade doesn't seem to exist <br/>" . PHP_EOL;
			return; #TODO find why !
		}
		$symbol_infos = static::$symbols [$symbol];
		$base_asset = $symbol_infos ["baseAsset"];
		if (!$this->exists_account($base_asset)) {
			$this->create_account($base_asset);
		}
		$quote_asset = $symbol_infos ["quoteAsset"];
		if (!$this->exists_account($quote_asset)) {
			$this->create_account($quote_asset);
		}
		
		if ($trade ["isBuyer"] === true) {
			$this->add_to_account($base_asset, $trade ["qty"]);
			$this->add_to_account($quote_asset, -$trade ["quoteQty"]);
		}
		else {
			$this->add_to_account($base_asset, -$trade ["qty"]);
			$this->add_to_account($quote_asset, $trade ["quoteQty"]);
		}
	}
	
}
