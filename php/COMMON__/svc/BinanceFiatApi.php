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

	public final const transaction_types = [
		"deposit"	=> 0,
		"withdraw"	=> 1,
	];
	
	public final const max_history_days = 30;

	
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
		if($diff->days > static::max_history_days) {
			throw new ErrorException("fiat deposit history can't query more that " . static::max_history_days . " days");
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
			$current_end->add(new DateInterval("P" . static::max_history_days-1 . "D"));
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
			
			$current_start->add(new DateInterval("P" . static::max_history_days-1 . "D"));
			$diff = $current_start->diff($end);
			if ($diff->invert === 0) {
				sleep(5); # fiat API throttling is very aggresive, this prevents more waiting
			}
		}
		return $res;
	}


	public static function store_trades_into_db (int $transactionType, array $trades) : void
	{
		foreach ($trades as $trade) {
			$ft = new FiatTrade();
			$ft->load (["orderNo = ?", $trade ["orderNo"]], []);
			$ft->copyfrom($trade);
			$ft->transactionType = $transactionType;
			$ft->save();
		}
	}
	
	
	public static function get_all_trades_cached () : array
	{
		$f3 = Base::instance();
		
		$now = new DateTimeImmutable ();
		$start = datetime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00");
		
		// $deposits = static::get_deposit_withdraw_history_large (static::transaction_types ["deposit"], $start, $now);
		// $withdraws = static::get_deposit_withdraw_history_large (static::transaction_types ["withdraw"], $start, $now);
		
		$deposits = BinanceFiatApiCached::get_deposit_history_large ($start, $now);
		var_dump($deposits[0]);
		
		$ft_wrapper = new FiatTrade;
		$fiat_trades = $ft_wrapper->find([""], []);
		var_dump($fiat_trades->castAll()[0]);
		
		
		
		die;
		return []; ///////////////
	}
	
}
