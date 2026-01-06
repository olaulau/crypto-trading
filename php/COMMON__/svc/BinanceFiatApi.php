<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Fiat\Api\FiatRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use Binance\Common\ApiException;
use COMMON__\mdl\FiatTrade;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ErrorException;


class BinanceFiatApi
{
	
	public static function get_api () : FiatRestApi
	{
		$f3 = Base::instance();
		
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}

		$configurationBuilder = SpotRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$api = new FiatRestApi ($configurationBuilder->build());
		
		return $api;
	}


	public static function get_deposit_withdraw_history (int $transaction_type, DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$diff = $start->diff($end);
		if($diff->days > 30) {
			throw new ErrorException("fiat deposit history can't query more that 30 days");
		}
		
		$api = static::get_api();
		$response = $api->getFiatDepositWithdrawHistory ($transaction_type, $start->getTimestamp()*1000, $end->getTimestamp()*1000);
		$res = Binance::responseData_to_table($response->getData()->getData());
		return $res;
	}
	
	
	public static function get_deposit_withdraw_history_large (int $transaction_type, DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$now = new DateTimeImmutable();
		$current_start = DateTime::createFromInterface ($start);
		
		$diff = $current_start->diff($end);
		$res = [];
		while ($diff->invert === 0) {
			$current_end = clone $current_start;
			$current_end->add(new DateInterval("P29D"));
			if ($current_end->diff($now)->invert === 1) {
				$current_end = $now;
			}
			
			$query_done = false;
			while ($query_done === false) {
				try {
					$history = static::get_deposit_withdraw_history ($transaction_type, $current_start, $current_end);
					$query_done = true;
					$res = array_merge($res, $history);
					
					
				}
				catch (ApiException $ex) {
					if (str_contains($ex->getMessage(), "Too many requests; current request has limited.")) {
						sleep(10); # more waiting in case of throttling
					}
					else {
						throw $ex;
					}
				}
			}
			
			$current_start->add(new DateInterval("P29D"));
			$diff = $current_start->diff($end);
			if ($diff->invert === 0) {
				sleep(5); # fiat API throttling is very aggresive, this prevents more waiting
			}
		}
		return $res;
	}


	public static function store_trades_into_db (array $trades) : void
	{
		foreach ($trades as $trade) {
			$ft = new FiatTrade();
			$ft->load (["orderNo = ?", $trade ["orderNo"]], []);
			$ft->copyfrom($trade);
			$ft->save();
		}
	}
	
}
