<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Fiat\Api\FiatRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ErrorException;
use Exception;

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


	public static function get_deposit_history (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$diff = $start->diff($end);
		if($diff->days > 30) {
			throw new ErrorException("fiat deposit history can't query more that 30 days");
		}
		
		$api = static::get_api();
		$response = $api->getFiatDepositWithdrawHistory (0, $start->getTimestamp()*1000, $end->getTimestamp()*1000);
		$res = Binance::responseData_to_table($response->getData()->getData());
		return $res;
	}
	
	public static function get_deposit_history_large (DateTimeInterface $start, DateTimeInterface $end) : array
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
				// echo "querying with " . $current_start->format(Stuff::date_sql_format) . " (" . $current_start->getTimestamp() . ") / " . $current_end->format(Stuff::date_sql_format) . " (" . $current_end->getTimestamp() . ") <br/>" . PHP_EOL;
				try {
					$history = static::get_deposit_history ($current_start, $current_end);
					// var_dump($history);
					$query_done = true;
					$res = array_merge($res, $history);
					
					$current_start->add(new DateInterval("P29D"));
					$diff = $current_start->diff($end);
					sleep(5); # fiat API throttling is very aggresive, this prevents more waiting
				}
				catch (Exception $ex) {
					// echo "EXCEPTION : " . get_class($ex) . " {$ex->getCode()} {$ex->getMessage()}";
					sleep(10); # more waiting in case of throttling
				}
			}
		}
		return $res;
	}
	
}
