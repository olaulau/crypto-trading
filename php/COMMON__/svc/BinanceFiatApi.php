<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Fiat\Api\FiatRestApi;
use Binance\Client\Spot\SpotRestApiUtil;
use Binance\Common\ApiException;
use COMMON__\mdl\FiatTrade;
use COMMON__\mdl\KeyValue;
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
	
	public final const fiat_bank = "FIATBANK";
	
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


	private static function get_all_trades_from_api (?int $start_timestamp = null) : array
	{
		if(!empty($start_timestamp)) {
			$start = DateTime::createFromTimestamp($start_timestamp);
		}
		else {
			$start = Binance::get_start_date();
		}
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
		$ft_wrapper = new FiatTrade;
		$trades = $ft_wrapper->getAll("createTime");
		return $trades->castAll();
	}
	
	public static function get_all_trades () : array
	{
		$cache_class = "BinanceFiatApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 60 * 60;
		
		# get actual data
		$db_trades = static::get_all_trades_from_db();
		
		# calculate last_update
		$last_update_o = new KeyValue;
		$last_update_o->load(["key = ?", $cache_key]);
		if($last_update_o->dry()) {
			# use last trade date
			$last_trade = end($db_trades);
			$last_update_dt = null;
			if (!empty($last_trade)) {
				$last_update_dt = DateTime::createFromTimestamp($last_trade ["createTime"]/1000);
			}
		}
		else {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat(Stuff::datetime_sql_format, $last_update_o->value);
		}
		
		# check if we have to query the API to refresh data
		$new_trades = [];
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get new trades
			$new_trades = static::get_all_trades_from_api ($last_update_dt ? $last_update_dt->getTimestamp() : null);
			# store them into db
			static::store_trades_into_db($new_trades);
			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		
		$data = array_merge($db_trades, $new_trades);
		return $data;
	}
	
	
	public static function fiatTrades_to_spotTrades (array $fiat_trades) : array
	{
		$res = [];
		foreach ($fiat_trades as $fiat_trade) {
			if ($fiat_trade ["status"] === "Successful") {
				$base_asset = static::fiat_bank;
				$quote_asset = $fiat_trade ["fiatCurrency"];
				if ($fiat_trade ["transactionType"] === BinanceFiatApi::transaction_types ["deposit"]) {
					$is_buyer = false;
				}
				else {
					$is_buyer = true;
				}
				
				$res [] = [
					'symbol'			=> "{$base_asset}{$quote_asset}",
					'id'				=> null,
					'orderId'			=> $fiat_trade ["orderNo"],
					'orderListId'		=> -1,
					'price'				=> 1,
					'qty'				=> $fiat_trade ["indicatedAmount"],
					'quoteQty'			=> $fiat_trade ["indicatedAmount"],
					'commission'		=> $fiat_trade ["totalFee"], 
					'commissionAsset'	=> $fiat_trade ["fiatCurrency"],
					'time'				=> $fiat_trade ["createTime"],
					'isBuyer'			=> $is_buyer,
					'isMaker'			=> false,
					'isBestMatch'		=> true,
				];
			}
		}
		return $res;
	}
	
}
