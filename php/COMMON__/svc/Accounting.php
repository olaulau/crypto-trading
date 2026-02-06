<?php
namespace COMMON__\svc;

use ErrorException;


class Accounting
{
	
	private static array $symbols;
	private array $accounts = [];
	private self $fees;
	private array $assets_reference_price;
	
	public function __construct (array $assets_reference_price, string $type = "normal")
	{
		$this->assets_reference_price = $assets_reference_price;
		static::$symbols = BinanceSpotApi::get_all_symbols_cached();
		static::$symbols [BinanceFiatApi::fiat_asset . Binance::reference_asset] = [ # FIATEUR
			"baseAsset"		=> BinanceFiatApi::fiat_asset,
			"quoteAsset"	=> Binance::reference_asset,
		];
		static::$symbols [BinanceRestApi::dividendes_asset . Binance::pivot_asset] = [ # DIVIDENDESUSDC
			"baseAsset"		=> BinanceRestApi::dividendes_asset,
			"quoteAsset"	=> Binance::pivot_asset,
		];
		if ($type === "normal") {
			$this->fees = new Accounting ($assets_reference_price, "fees");
		}
	}
	
	
	public function exists_account (string $asset) : bool
	{
		return isset($this->accounts [$asset]);
	}
	
	public function create_account (string $asset) : void
	{
		$this->accounts [$asset] = new Account ($this->assets_reference_price, $asset);
	}
	
	public function add_to_account(string $asset, float $amount)
	{
		if (!$this->exists_account($asset)) {
			$this->create_account($asset);
		}
		
		$this->get_account($asset)->add($amount);
	}
	
	public function get_accounts_assets () : array
	{
		return array_keys ($this->accounts);
	}
	
	public function get_account (string $asset) : ?Account
	{
		return $this->accounts [$asset] ?? null;
	}
	
	public function get_account_quantity (string $asset)
	{
		return $this->get_account ($asset)->get_quantity ();
	}
	
	
	private function execute_trade (array $trade) : void
	{
		$symbol = $trade ["symbol"];

		if (!isset(static::$symbols [$symbol])) { # symbol not listed by binance anymore, but we need to handle it anyway
			$assets = BinanceSpotApiCached::guess_symbol_assets_cached ($symbol);
			if (empty($assets)) {
				throw new ErrorException ("$symbol not in current symbols and couldn't be guessed"); #TODO find why !
			}
			list("base_asset" => $base_asset, "quote_asset" => $quote_asset) = $assets;
		}
		else {
			$symbol_infos = static::$symbols [$symbol];
			$base_asset = $symbol_infos ["baseAsset"];
			$quote_asset = $symbol_infos ["quoteAsset"];
		}
		
		# quantities
		if ($trade ["isBuyer"] === 1) {
			$this->add_to_account ($base_asset, $trade ["qty"]);
			$this->add_to_account ($quote_asset, -$trade ["quoteQty"]);
		}
		else {
			$this->add_to_account ($base_asset, -$trade ["qty"]);
			$this->add_to_account ($quote_asset, $trade ["quoteQty"]);
		}
		
		# commissions
		$this->add_to_account ($trade ["commissionAsset"], -$trade ["commission"]);
		$this->fees->add_to_account ($trade ["commissionAsset"], $trade ["commission"]);
		
		#TODO calculate avg cost
	}
	
	public function execute_trades (array $trades)
	{
		foreach ($trades as $trade) {
			$this->execute_trade($trade);
		}
	}
	
	
	public function get_reference_total ()
	{
		$total = 0;
		foreach ($this->accounts as $account) { /** @var Account $account */
			$total += $account->get_reference_price();
		}
		return $total;
	}
	
	
	public function get_reference_fees ()
	{
		return $this->fees->get_reference_total();
	}
	
}
