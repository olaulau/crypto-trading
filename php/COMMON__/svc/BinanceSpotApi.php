<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use ErrorException;

class BinanceSpotApi
{
	
	public static function get_spot_api () : SpotRestApi
	{
		$f3 = Base::instance();
		
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$spot_api = new SpotRestApi($configurationBuilder->build());
		
		return $spot_api;
	}
	
	
	public static function get_trades (string $crypto_pair) : array
	{
		$spot_api = static::get_spot_api();
		$response = $spot_api->myTrades($crypto_pair);
		$row = $response->getData(); /** @var MyTradesResponse $row */
		$items = $row->getItems(); /** @var MyTradesResponseInner[] $items */

		$data = [];
		foreach ($items as $item) {
			$row = [];
			foreach ($item->attributeMap() as $attribute) {
				$attribute = ucfirst ($attribute);
				$getter_method_name = "get$attribute";
				$row [$attribute] = $item->$getter_method_name();
			}
			$data [] = $row;
		}
		return $data;
	}
	
	
	public static function get_trades_grouped (string $crypto_pair) : array
	{
		$data = static::get_trades($crypto_pair);
		$data = Stuff::array_group_by($data, "OrderListId");
		return $data;
	}
	
}
