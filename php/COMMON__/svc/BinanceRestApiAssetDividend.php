<?php
namespace COMMON__\svc;

use COMMON__\mdl\AssetDividend;
use COMMON__\mdl\KeyValue;
use DateTime;


trait BinanceRestApiAssetDividend
{
	
	public static function get_asset_dividend_from_api () : array
	{
		// 'type' => 'BNB_VAULT', // ou LAUNCHPOOL
		$path = '/sapi/v1/asset/assetDividend';
		return static::query($path) ["rows"];
	}


	private static function store_asset_dividend_into_db (array $data) : void
	{
		foreach ($data as $row) {
			$elt = new AssetDividend;
			$elt->load (["id2 = ?", $row ["id"]], []);
			$id = $elt->id; # keep old PK
			$elt->copyfrom ($row);
			$elt->id2 = $row ["id"]; # shift id to id2 with larger column type
			$elt->id = $id; # let id be auto incremented
			$elt->save ();
		}
	}

	
	private static function get_asset_dividend_from_db () : array
	{
		return AssetDividend::getAllFast ("divTime");
	}
	
	
	public static function get_asset_dividend_cached () : array
	{
		$cache_class = "BinanceRestApi";
		$cache_function = __FUNCTION__;
		$cache_key = "{$cache_class}__{$cache_function}__last_update";
		$cache_ttl = 24 * 60 * 60;
		
		# calculate last_update
		$last_update_o = new KeyValue;
		$last_update_o->load (["key = ?", $cache_key]);
		if(!$last_update_o->dry()) {
			# use saved last update
			$last_update_dt = DateTime::createFromFormat (Stuff::datetime_sql_format, $last_update_o->value);
		}

		# check if we have to query the API to refresh data
		if (empty($last_update_dt) || (time() - $last_update_dt->getTimestamp()) > $cache_ttl) {
			# get trades
			$data = static::get_asset_dividend_from_api ();

			# store them into db
			static::store_asset_dividend_into_db ($data);

			# store last update
			$last_update_o->key = $cache_key;
			$last_update_o->value = (new DateTime)->format(Stuff::datetime_sql_format);
			$last_update_o->save();
		}
		else {
			# get actual data
			$data = static::get_asset_dividend_from_db ();
		}
		
		return $data;
	}
	
	
	public static function assetDividend_to_spotTrades (array $asset_dividendes) : array
	{
		$res = [];
		foreach ($asset_dividendes as $dividende) {
			$base_asset = BinanceRestApi::dividendes_asset;
			$quote_asset = $dividende ["asset"];
			if ($dividende ["direction"] === 1) {
				$is_buyer = 0;
			}
			else {
				$is_buyer = 1;
			}
			
			$res [] = [
				'symbol'			=> "{$base_asset}{$quote_asset}",
				'id'				=> $dividende ["tranId"],
				'orderId'			=> $dividende ["id"],
				'orderListId'		=> -1,
				'price'				=> 1,
				'qty'				=> $dividende ["amount"], #TODO which coin are they all, USDC ?
				'quoteQty'			=> $dividende ["amount"], 
				'commission'		=> 0, 
				'commissionAsset'	=> "EUR", #TODO use constant
				'time'				=> $dividende ["divTime"],
				'isBuyer'			=> $is_buyer,
				'isMaker'			=> 0,
				'isBestMatch'		=> 1,
			];
		}
		return $res;
	}

}
