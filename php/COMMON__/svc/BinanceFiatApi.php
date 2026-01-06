<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Fiat\Api\FiatRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use Binance\Common\ApiException;
use Cache;
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


	private static function get_all_trades_from_api () : array
	{
		$f3 = Base::instance();
		
		$start = datetime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00");
		$now = new DateTimeImmutable ();
		
		# get data and add "transactionType" field
		$deposits = static::get_deposit_withdraw_history_large (static::transaction_types ["deposit"], $start, $now);
		foreach ($deposits as &$deposit) {
			$deposit ["transactionType"] = static::transaction_types ["deposit"];
		}
		$withdraws = static::get_deposit_withdraw_history_large (static::transaction_types ["withdraw"], $start, $now);
		foreach ($withdraws as &$withdraw) {
			$withdraw ["transactionType"] = static::transaction_types ["withdraw"];
		}
		
		# merge and sort
		$res = array_merge ($deposits, $withdraws);
		$sort = array_column ($res, "createTime");
		array_multisort ($sort, SORT_ASC, SORT_NUMERIC, $res);
		return $res;
	}
	
	private static function store_trades_into_db (array $trades) : void
	{
		foreach ($trades as $trade) {
			$ft = new FiatTrade;
			$ft->load (["orderNo = ?", $trade ["orderNo"]], []);
			$ft->copyfrom ($trade);
			$ft->save ();
		}
	}
	
	private static function get_all_trades_from_db () : array
	{
		$f3 = Base::instance();
		
		$ft_wrapper = new FiatTrade;
		$trades = $ft_wrapper->getAll("createTime");
		return $trades->castAll();
	}
	
	public static function get_all_trades () : array
	{
		$cache = Cache::instance();
		$cache_class = "BinanceFiatApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}";
		$cache_ttl = 60 * 60;
		
		if ($cache->exists($cache_key) === false) {
			$data = static::get_all_trades_from_api ();
			static::store_trades_into_db ($data);
			
			$cache->set($cache_key, null, $cache_ttl);
		}
		else {
			$data = static::get_all_trades_from_db ();
		}
		return $data;
	}
	
}
