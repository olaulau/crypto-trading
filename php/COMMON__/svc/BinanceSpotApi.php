<?php
namespace COMMON__\svc;

use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\Symbols;
use Binance\Client\Spot\Model\TickerPriceResponse;
use Binance\Client\Spot\SpotRestApiUtil;
use ErrorException;


class BinanceSpotApi
{
	use BinanceSpotApiTrade, BinanceSpotApiOrder, BinanceSpotApiAccount, BinanceSpotApiExchangeInfos;
	
	public static function get_api () : SpotRestApi
	{
		$binance_conf = Binance::get_conf ();
		$binance_key = $binance_conf ["key"];
		$binance_secret = $binance_conf ["secret"];
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$configurationBuilder->url($binance_conf ["rest_url"]);
		$api = new SpotRestApi($configurationBuilder->build());

		return $api;
	}
	
	
	public static function get_known_symbols (bool $with_balance = false) : array
	{
		if ($with_balance) {
			$symbols = BinanceSpotApiCached::get_symbols_from_balance (); # too much symbols;
		}
		else {
			$symbols = [];
		}
		$order_lists_symbols = BinanceSpotApiCached::get_symbols_from_order_lists ();
		$spot_trades_symbols = BinanceSpotApi::get_symbols_from_trades ();
		
		$symbols = array_merge ($symbols, $order_lists_symbols, $spot_trades_symbols);
		sort ($symbols);
		$symbols = array_unique ($symbols);
		return $symbols;
	}


	public static function get_known_assets () : array
	{
		$assets = [];
		$symbols = static::get_known_symbols();
		foreach ($symbols as $symbol) {
			$symbol_assets = BinanceSpotApiCached::guess_symbol_assets_cached($symbol);
			$assets [] = $symbol_assets ["base_asset"];
			$assets [] = $symbol_assets ["quote_asset"];
		}
		return array_unique($assets);
	}
	
	
	public static function get_symbols_from_order_lists () : array
	{
		$items = static::get_order_lists_from_api();
		$symbols = array_column($items, "symbol");
		$symbols = array_unique($symbols);
		sort($symbols);
		return $symbols;
	}
	
	
	public static function get_symbols_from_trades () : array
	{
		$trades = static::get_all_trades_from_db ();
		$symbols = array_column($trades, "symbol");
		$symbols = array_unique($symbols);
		sort($symbols);
		return $symbols;
	}
	
	
	/**
	 * get actual price for each symbols
	 * @param array $symbols
	 * @return array
	 */
	public static function get_ticker_prices (array $symbols) : array
	{
		$symbols = array_unique($symbols);
		sort($symbols);
		
		$spot_api = static::get_api();
		$response = $spot_api->tickerPrice (null, new Symbols ($symbols));
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response2 = $data->getTickerPriceResponse2();
		$res = Binance::responseData_to_table ($response2);
		$res = Stuff::array_group_by($res ["items"], "symbol", false);
		return $res;
	}
	
	/**
	 * same for only one symbol
	 */
	public static function get_ticker_price (string $symbol) : array
	{
		$spot_api = static::get_api();
		$response = $spot_api->tickerPrice ($symbol);
		$data = $response->getData(); /** @var TickerPriceResponse $data */
		$response1 = $data->getTickerPriceResponse1();
		$res = Binance::responseData_to_table ($response1);
		return $res;
	}
	
}
