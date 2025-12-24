<?php

namespace COMMON__\ctrl;

use Base;
use Binance\Client\Spot\Api\SpotRestApi;
use Binance\Client\Spot\Model\MyTradesResponse;
use Binance\Client\Spot\Model\MyTradesResponseInner;
use Binance\Client\Spot\SpotRestApiUtil;
use ErrorException;


class BinanceCtrl extends Ctrl
{



	public static function beforeRoute()
	{
		parent::beforeRoute();
	}


	public static function afterRoute()
	{
		parent::afterRoute();
	}


	public static function breadcrumbs()
	{
		$res = [];
		return $res;
	}



	public static function binanceGET(Base $f3, $url, $controler)
	{
		// Construction de la config
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$spot_api = new SpotRestApi($configurationBuilder->build());

		// $response = $spot_api->time();
		// var_dump($response->getData());

		// $response = $spot_api->exchangeInfo();
		// var_dump($response->getData());

		// $response = $spot_api->allOrders("ETHEUR");
		// var_dump($response->getData());

		// $response = $spot_api->allOrderList();
		// var_dump($response->getData());

		// $response = $spot_api->avgPrice("ETHEUR");
		// var_dump($response->getData());

		// $response = $spot_api->getOpenOrders();
		// var_dump($response->getData());

		// $response = $spot_api->getAccount(true);
		// var_dump($response->getData());

		// $response = $spot_api->klines("ETHEUR", ); ///////////////////
		// var_dump($response->getData());

		$response = $spot_api->myTrades("ETHEUR");
		$data = $response->getData(); /** @var MyTradesResponse $data */
		$items = $data->getItems(); /** @var MyTradesResponseInner[] $items */
		
		?>
		<table style="border: 1px solid black;">
			<?php
			$attributes = [];
			foreach ($items as $item) {
				if (empty($attributes)) {
					$attributes = $item->attributeMap();
					static::display_table_row($attributes);
				}
				
				$values = [];
				foreach ($attributes as $attribute) {
					$attribute = ucfirst($attribute);
					$getter_method_name = "get$attribute";
					$values [$attribute] = $item->$getter_method_name();
				}
				static::display_table_row($values);
			}
			?>
		</table>
		<?php

		die;
	}


	public static function display_table_row(array $row)
	{
		?>
		<tr style="border: 1px solid black;">
			<?php
			foreach ($row as $value) {
			?>
				<td style="border: 1px solid black;"><?= $value ?></td>
			<?php
			}
			?>
		</tr>
		<?php
	}
}
