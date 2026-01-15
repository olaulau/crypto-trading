<?php
namespace COMMON__\svc;


class Accounting
{
	
	private static $symbols;
	private $accounts = [];
	
	public function __construct ()
	{
		static::$symbols = BinanceSpotApi::get_all_symbols_cached();
		static::$symbols [BinanceFiatApi::fiat_bank . Binance::reference_asset] = [
			"baseAsset"		=> BinanceFiatApi::fiat_bank,
			"quoteAsset"	=> Binance::reference_asset,
		];
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

		if(!isset(static::$symbols [$symbol])) { # symbol not listed by binance anymore, but we need to handle it anyway
			$assets = BinanceSpotApiCached::guess_symbol_assets_cached ($symbol);
			if (empty($assets)) {
				echo "$symbol not in current symbols and couldn't be guessed <br/>" . PHP_EOL;
				return; #TODO find why !
			}
			list("base_asset" => $base_asset, "quote_asset" => $quote_asset) = $assets;
		}
		else {
			$symbol_infos = static::$symbols [$symbol];
			$base_asset = $symbol_infos ["baseAsset"];
			$quote_asset = $symbol_infos ["quoteAsset"];
		}
		
		if (!$this->exists_account($base_asset)) {
			$this->create_account($base_asset);
		}
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
	
	public function execute_trades(array $trades)
	{
		foreach ($trades as $trade) {
			$this->execute_trade($trade);
		}
	}
	
}
