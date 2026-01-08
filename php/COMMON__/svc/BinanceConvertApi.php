<?php
namespace COMMON__\svc;

use Base;
use Binance\Client\Convert\Api\ConvertRestApi;
use Binance\Client\Convert\ConvertRestApiUtil;
use COMMON__\mdl\ConvertTrade;
use COMMON__\mdl\KeyValue;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ErrorException;


class BinanceConvertApi
{
	
	public static function get_api () : ConvertRestApi
	{
		$f3 = Base::instance();
		
		$binance_key = $f3->get("binance.key");
		$binance_secret = $f3->get("binance.secret");
		if (empty($binance_key) || empty($binance_secret)) {
			throw new ErrorException("no binance api key provided");
		}
		
		$configurationBuilder = ConvertRestApiUtil::getConfigurationBuilder();
		$configurationBuilder->apiKey($binance_key)->secretKey($binance_secret);
		$convert_api = new ConvertRestApi($configurationBuilder->build());
		
		return $convert_api;
	}
	
	
	public static function get_trade_history (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$diff = $start->diff($end);
		if($diff->days > 30) {
			throw new ErrorException("convert trade history can't query more that 30 days");
		}
		
		$api = static::get_api();
		$trade_history_response = $api->getConvertTradeHistory ($start->getTimestamp()*1000, $end->getTimestamp()*1000);
		$res = Binance::responseData_to_table($trade_history_response);
		return $res ["data"] ["list"];
	}
	
	
	public static function get_trade_history_large (DateTimeInterface $start, DateTimeInterface $end) : array
	{
		$current_start = DateTime::createFromInterface ($start);
		
		$diff = $current_start->diff($end);
		$res = [];
		while ($diff->invert === 0) {
			$current_end = clone $current_start;
			$current_end->add(new Dateinterval("P29D"));
			$trade_history = static::get_trade_history ($current_start, $current_end);
			$res = array_merge($res, $trade_history);
			
			$current_start->add(new DateInterval("P29D"));
			$diff = $current_start->diff($end);
		}
		
		return $res;
	}


	public static function get_all_trades_from_api (?DateTimeInterface $start = null) : array
	{
		$f3 = Base::instance();

		if (empty($start)) {
			$start = DateTime::createFromFormat(Stuff::datetime_sql_format, $f3->get("binance.start_date") . " 00:00:00");
		}
		$now = new DateTimeImmutable();

		$trades = static::get_trade_history_large($start, $now);
		
		return $trades;
	}
	
	
	public static function get_trade_history_large_for_symbol (string $symbol) : array
	{
		$trades = static::get_all_trades();
		$res = [];
		foreach ($trades as $trade) {
			$trade_symbol = $trade ["fromAsset"] . $trade ["toAsset"];
			if ($trade_symbol === $symbol) {
				$res [] = $trade;
			}
		}
		return $res;
	}


	private static function store_trades_into_db (array $trades) : void
	{
		foreach ($trades as $trade) {
			$ft = new ConvertTrade();
			$ft->load (["quoteId = ?", $trade ["quoteId"]], []);
			$ft->copyfrom ($trade);
			$ft->save ();
		}
	}
	
	private static function get_all_trades_from_db () : array
	{
		$ft_wrapper = new ConvertTrade;
		$trades = $ft_wrapper->getAll("createTime");
		return $trades->castAll();
	}
	
	public static function get_all_trades () : array
	{
		$cache_class = "BinanceConvertApi";
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
			static::store_trades_into_db ($new_trades);
			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		
		$data = array_merge($db_trades, $new_trades);
		return $data;
	}
	
	
	public static function conversionTrades_to_spotTrades (array $convertion_trades) : array
	{
		$res = [];
		foreach ($convertion_trades as $convertion_trade) {
			if ($convertion_trade ["toAsset"] === Binance::reference_asset) { #TODO maybe no one of the assets is EUR
				$base_asset = $convertion_trade ["fromAsset"];
				$quote_asset = $convertion_trade ["toAsset"];
				$is_buyer = false;
			}
			else {
				$base_asset = $convertion_trade ["toAsset"]; #TODO so maybe this pair doesn't exist, but the opposite does
				$quote_asset = $convertion_trade ["fromAsset"];
				$is_buyer = true;
			}
			$symbol = "{$base_asset}{$quote_asset}";
			
			$res [] = [
				'symbol'			=> $symbol, #TODO may not be a valid pair (especially converting from a crypto to another one)
				'id'				=> $convertion_trade ["quoteId"],
				'orderId'			=> $convertion_trade ["orderId"],
				'orderListId'		=> -1,
				'price'				=> $convertion_trade ["ratio"],
				'qty'				=> $convertion_trade ["fromAmount"],
				'quoteQty'			=> $convertion_trade ["toAmount"],
				'commission'		=> 0, 
				'commissionAsset'	=> 'EUR',
				'time'				=> $convertion_trade ["createTime"],
				'isBuyer'			=> $is_buyer,
				'isMaker'			=> false,
				'isBestMatch'		=> true,
			];
		} 
		return $res;
	}

}
