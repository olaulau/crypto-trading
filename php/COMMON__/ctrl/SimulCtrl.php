<?php
namespace COMMON__\ctrl;

use Base;
use COMMON__\mdl\Kline;
use COMMON__\mdl\Simulation;
use COMMON__\mdl\SimulationStep;
use COMMON__\mdl\Stat;
use COMMON__\svc\Binance;
use COMMON__\svc\Stuff;
use DateTime;
use DateTimeImmutable;
use DB\SQL;
use ErrorException;


class SimulCtrl extends PrivateCtrl
{
	public final static $sma_name = "SMA4";
	
	
	public static function beforeRoute ()
	{
		parent::beforeRoute();
	}
	
	
	public static function afterRoute ()
	{
		parent::afterRoute();
	}

	
	public static function breadcrumbs ()
	{
		$res = [];
		return $res;
	}
	
	
	public static function simulationsGET (Base $f3, $url, $controler)
	{
		$simulations = Simulation::getAll ("execution_time", "DESC");
		$f3->set ("simulations", $simulations);
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"simulations",
			"title"		=>	"Simulations",
			"breadcrumbs" => static::breadcrumbs(),
		];
		static::renderPage ($page);
	}
	
	
	public static function simulationsDeleteGET (Base $f3, $url, $controler)
	{
		$simulations = Simulation::getAll ();
		foreach ($simulations as $simulation) {
			$simulation->erase ();
		}
		$f3->reroute("simulations");
	}
	
	
	public static function simulateGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"simulate",
			"title"		=>	"Simulate",
			"breadcrumbs" => static::breadcrumbs(),
		];
		static::renderPage ($page);
	}
	
	public static function simulatePOST (Base $f3, $url, $controler)
	{
		$fields = [
			"start_base_amount", "start_quote_amount", "sma_name", 
			"sell_min_margin", "sell_floor_margin", "stop_loss_margin", 
			"buy_min_margin", "buy_floor_margin", "re_entry_margin", 
		];
		
		$post = $f3->get("POST");
		foreach ($fields as $field) {
			static::simulate_check_field ($field, $post);
		}
		
		static::simulate_recursive_loop ($fields, $post);
		$f3->reroute (["simulations"]);
	}

	private static function simulate_check_field (string $field, array &$post)
	{
		if (empty ($post ["{$field}_MAX"]) && empty($post ["{$field}_STEPS"])) {
			$post ["{$field}_MAX"] = $post ["{$field}"];
			$post ["{$field}_STEPS"] = 1;
			return;
		}
		if (
			(!empty ($post ["{$field}_MAX"]) && $post ["{$field}_STEPS"] < 2)
			|| (empty ($post ["{$field}_MAX"]) && $post ["{$field}_STEPS"] >= 2)
			|| (!empty ($post ["{$field}_MAX"]) && $post ["{$field}_MAX"] <= $post ["{$field}"])
		) {
			throw new ErrorException("{$field} error");
		}
	}

	private static function simulate_recursive_loop (array $fields, array $post, array $values=[]) : void
	{
		if (empty ($fields)) {
			SimulCtrl::simulation_run ($values ["start_base_amount"], $values ["start_quote_amount"], $values ["sma_name"],
				$values ["sell_min_margin"], $values ["sell_floor_margin"], $values ["stop_loss_margin"],
				$values ["buy_min_margin"], $values ["buy_floor_margin"], $values ["re_entry_margin"]);
			return;
		}
		$field = array_shift ($fields);

		if ($post ["{$field}_STEPS"] > 1) {
			for (${$field."_CPT"} = 0; ${$field."_CPT"} < $post ["{$field}_STEPS"]; ${$field."_CPT"} ++) {
				${$field."_VAL"} = $post [$field];
				${$field."_VAL"} += ${$field."_CPT"} * ($post ["{$field}_MAX"] - $post [$field]) / ($post ["{$field}_STEPS"]-1);
				$values [$field] = ${$field."_VAL"};
				static::simulate_recursive_loop ($fields, $post, $values);
			}
		}
		else {
			$field_values = explode (" ", $post [$field]);
			if (count ($field_values) > 1) {
				foreach ($field_values as $field_value) {
					$values [$field] = $field_value;
					static::simulate_recursive_loop ($fields, $post, $values);
				}
			}
			else {
				$values [$field] = $post [$field];
				static::simulate_recursive_loop ($fields, $post, $values);
			}
		}
	}
	
	private static function simulation_run (float $start_base_amount, float $start_quote_amount, string $sma_name, float $sell_min_margin , float $sell_floor_margin, float $stop_loss_margin, float $buy_min_margin, float $buy_floor_margin, float $re_entry_margin) : void
	{
		# init
		set_time_limit (0);
		$f3 = Base::instance ();
		$db = $f3->get ("db"); /** @var SQL $db */

		$simulation = new Simulation;
		$simulation->symbol =			IndexCtrl::$symbol;
		$simulation->open_time =		IndexCtrl::$start_sql;
		$simulation->close_time =		IndexCtrl::$end_sql;
		$simulation->execution_time =	(new DateTimeImmutable)->format (Stuff::datetime_sql_format);
		$simulation->description =		str_replace ("\t", "", trim("
			simple margin algorithm
			candle size = " . IndexCtrl::$small_candle_size . "
			smoothing stat = {$sma_name}
			sell min margin : {$sell_min_margin}% ({$sell_floor_margin}% floor), SL {$stop_loss_margin}%
			buy min margin : {$buy_min_margin}% ({$buy_floor_margin}% floor), re entry {$re_entry_margin}%
		"));
		$simulation->save ();

		# start reading data
		$offset = 0;
		$kline = new Kline;
		$sql = "
			SELECT	k.*, s.open as $sma_name
			FROM	kline k
			LEFT JOIN stat s ON (k.symbol, k.candle_size, k.open_time) = (s.symbol, s.candle_size, s.open_time)
			WHERE	k.symbol = ?
			AND		k.candle_size = ?
			AND		? <= k.open_time AND k.open_time <= ?
			AND		s.name = ?
			ORDER BY id ASC
			LIMIT	?
			OFFSET	?
		";
		$params = [IndexCtrl::$symbol, IndexCtrl::$small_candle_size, IndexCtrl::$start_sql, IndexCtrl::$end_sql, $sma_name, IndexCtrl::$sql_read_limit, $offset];
		$simulation_steps = []; //todo use !
		
		// $csv_filename = __DIR__ . "/../../../" . "/tmp/simu.csv";
		// $csv_handle = fopen ($csv_filename, "w");
		// $csv_row_data = ["open_time", "ETH", "EUR", "price", "price_smoothed", "reference_price", "high", "low"];
		// fputcsv ($csv_handle, $csv_row_data, ",", "\"", "\\", PHP_EOL); //////////////////////////
		
		while ($klines = $db->exec ($sql, $params)) {
			foreach ($klines as $kline) {
				$price = $kline ["open"];
				$price_formated = Stuff::format_float_significative ($price, 6);
				$simulation_step = new SimulationStep;
				$simulation_step->simulation_id = $simulation;
				$simulation_step->kline_id = $kline ["id"];
				
				$SMA_price = $kline [$sma_name];
				$price_smoothed = $SMA_price; # we use SMA as smoothed price
				
				if ($offset === 0) { # start variables
					$ETH = $start_base_amount;
					$EUR = $start_quote_amount;
					
					$reference_price = $price_smoothed; # value of my last crypto movement #TODO remove and use $simulation_steps
					$high = $price_smoothed; # highest value since last action
					$low = $price_smoothed; # lowest value since last action
					
					$simulation_step->type =					"start";
					if ($ETH > 0) {
						$ETH_converted = $ETH * $price;
						$ETH_converted_formated = Stuff::format_float_significative ($ETH_converted, 6);

						$simulation_step->base_amount =			$ETH;
						$simulation_step->quote_amount =		$ETH_converted;
						$simulation_step->description =			"simulation start : {$ETH} ETH = {$ETH_converted_formated} €";
					}
					if ($EUR > 0) {
						$EUR_converted = $EUR / $price;
						$EUR_converted_formated = Stuff::format_float_significative ($EUR_converted, 6);

						$simulation_step->base_amount =			$EUR_converted;
						$simulation_step->quote_amount =		$EUR;
						$simulation_step->description =			"simulation start : {$EUR} € = {$EUR_converted_formated} ETH";
					}
					$simulation_step->save ();
					$simulation_steps [] = $simulation_step;
				}
				else {
					$high = max ($price_smoothed, $high);
					$low = min ($price_smoothed, $low);
					
					if ($ETH > 0) { # I own crypto
						if ($high > ($reference_price * (1 + $sell_min_margin / 100))) { # price raised a lot
							if ($price_smoothed < ($high * (1 - $sell_floor_margin / 100))) { # seems like we floored
								$EUR = $ETH * $price;
								$EUR_formated = Stuff::format_float_significative ($EUR, 6);
								
								$simulation_step->type =				"sell";
								$simulation_step->base_amount =			$ETH;
								$simulation_step->quote_amount =		$EUR;
								$simulation_step->description =			"({$price_formated}) selling --> {$EUR_formated} €";
								$simulation_step->save ();
								$simulation_steps [] = $simulation_step;
								
								$ETH = 0;
								$low = $high = $reference_price = $price_smoothed;
							}
						}
						elseif ($stop_loss_margin > 0.0 && $price_smoothed < ($reference_price * (1 - $stop_loss_margin / 100))) { # we are loosing money
							# stop loss
							$EUR = $ETH * $price;
							$EUR_formated = Stuff::format_float_significative ($EUR, 6);
							
							$simulation_step->type =				"sell"; //TODO add a reason (stop loss) field ?
							$simulation_step->base_amount =			$ETH;
							$simulation_step->quote_amount =		$EUR;
							$simulation_step->description =			"({$price_formated}) stop loss --> {$EUR_formated} €";
							$simulation_step->save ();
							$simulation_steps [] = $simulation_step;
							
							$ETH = 0;
							$low = $high = $reference_price = $price_smoothed;
						}
					}
					
					else { # I own euros
						if ($low < ($reference_price * (1 - $buy_min_margin / 100))) { # value dropped a lot
							if ($price_smoothed > ($low * (1 + $buy_floor_margin / 100))) { # seems like we floored
								$ETH = $EUR / $price;
								$ETH_formated = Stuff::format_float_significative ($ETH, 6);
								
								$simulation_step->type =				"buy";
								$simulation_step->base_amount =			$ETH;
								$simulation_step->quote_amount =		$EUR;
								$simulation_step->description =			"({$price_formated}) buying --> {$ETH_formated} ETH";
								$simulation_step->save ();
								$simulation_steps [] = $simulation_step;
								
								$EUR = 0;
								$low = $high = $reference_price = $price_smoothed;
							}
						}
						elseif ($re_entry_margin > 0.0 && $price_smoothed > ($reference_price * (1 + $re_entry_margin / 100))) { # we are loosing opportunities
							# re entry
							$ETH = $EUR / $price;
							$ETH_formated = Stuff::format_float_significative ($ETH, 6);
							
							$simulation_step->type =				"buy"; //TODO add a reason (re entry) field ?
							$simulation_step->base_amount =			$ETH;
							$simulation_step->quote_amount =		$EUR;
							$simulation_step->description =			"({$price_formated}) re entry --> {$ETH_formated} ETH";
							$simulation_step->save ();
							$simulation_steps [] = $simulation_step;
							
							$EUR = 0;
							$low = $high = $reference_price = $price_smoothed;
						}
					}
					
					// $csv_row_data = [
					// 	$kline ["open_time"], $ETH, $EUR, $price, $price_smoothed, $reference_price, $high, $low, 
					// 	// ($price_smoothed < ($reference_price * (1 - $buy_min_margin / 100))), 
					// 	// ($price_smoothed > ($low * (1 + $buy_floor_margin / 100))), 
					// ];
					// fputcsv ($csv_handle, $csv_row_data, ",", "\"", "\\", PHP_EOL); ///////////////////////
				}
				
				$offset ++;
				$params = [IndexCtrl::$symbol, IndexCtrl::$small_candle_size, IndexCtrl::$start_sql, IndexCtrl::$end_sql, $sma_name, IndexCtrl::$sql_read_limit, $offset];
			}
		}
		
		// end of simulation state
		$simulation_step = new SimulationStep;
		$simulation_step->simulation_id =		$simulation;
		$simulation_step->kline_id =			$kline ["id"];
		$simulation_step->type =				"end";
		$simulation_step->base_amount =			$ETH;
		$simulation_step->quote_amount =		$EUR;
		
		if ($ETH > 0) {
			$ETH_formated = Stuff::format_float_significative ($ETH, 6);
			$ETH_converted = $ETH * $price;
			$ETH_converted_formated = Stuff::format_float_significative ($ETH_converted, 6);
			$simulation_step->quote_amount =	$ETH_converted;
			$simulation_step->description =		"simulation end : {$ETH_formated} ETH @ {$price_formated} <=> {$ETH_converted_formated} €";
		}
		if ($EUR > 0) {
			$EUR_formated = Stuff::format_float_significative ($EUR, 6);
			$EUR_converted = $EUR / $price;
			$EUR_converted_formated = Stuff::format_float_significative ($EUR_converted, 6);
			$simulation_step->base_amount =		$EUR_converted;
			$simulation_step->description = 	"simulation end : {$EUR_formated} € <=> {$EUR_converted_formated} ETH";
		}
		
		$simulation_step->save ();
		$simulation_steps [] = $simulation_step;
		
		$simulation->roi = $simulation->get_roi();
		$simulation->save ();
	}


	public static function simulationGET (Base $f3, $url, $controler)
	{
		$id = (int) $f3->get ("PARAMS.id");
		if (empty ($id) || !is_int ($id)) {
			throw new ErrorException ("id empty or not integer");
		}

		$simulation = Simulation::findOneBy ("id", $id);
		if (empty ($simulation)) {
			throw new ErrorException ("couldn't find simulation #$id");
		}
		$f3->set("simulation", $simulation);
		
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"simulation",
			"title"		=>	"Simulation #{$simulation->id}",
			"breadcrumbs" => static::breadcrumbs(),
		];
		static::renderPage($page);
	}
	
	
	public static function simulationDeleteGET (Base $f3, $url, $controler)
	{
		$id = (int) $f3->get ("PARAMS.id");
		if (empty ($id) || !is_int ($id)) {
			throw new ErrorException ("id empty or not integer");
		}

		$simulation = Simulation::findOneBy ("id", $id);
		if (empty ($simulation)) {
			throw new ErrorException ("couldn't find simulation #$id");
		}

		$simulation->erase();
		$f3->reroute (["simulations"]);
	}
	
	
	
	public static function chartGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"chart",
			"title"		=>	"Chart",
			"breadcrumbs" => static::breadcrumbs(),
		];
		static::renderPage($page);
	}


	public static function chartDataGET (Base $f3, $url, $controler)
	{
		$db = $f3->get("db"); /** @var SQL $db */
		
		// params
		$symbol = $f3->get("GET.symbol");
		$start_ts = $f3->get("GET.start");
		$start_dt = DateTime::createFromTimestamp (Binance::to_real_timestamp ((int)$start_ts));
		$start_tsms = $start_dt->getTimestamp() * 1000;
		$end_ts = $f3->get("GET.end");
		$end_dt = DateTime::createFromTimestamp (Binance::to_real_timestamp ((int)$end_ts));
		$end_tsms = $end_dt->getTimestamp() * 1000;
		$simulation_id = $f3->get("GET.simulation_id") ?? 0;

		// margin
		$margin_tx = 0.2; // add 20% margin on start and end side
		$x_width = $end_dt->getTimestamp() - $start_dt->getTimestamp();
		$margin_s = round ($x_width * $margin_tx);
		$start_with_margin_dt = (clone $start_dt)->modify ("-$margin_s seconds");
		$end_with_margin_dt = (clone $end_dt)->modify ("+$margin_s seconds");
		$start_sql = $start_with_margin_dt->format(Stuff::datetime_sql_format);
		$end_sql = $end_with_margin_dt->format(Stuff::datetime_sql_format);
		
		// calculate candle size
		$max_candles = 1000;
		$candles_available = IndexCtrl::candles_available ($symbol, $start_sql, $end_sql);
		
		$klines_data = [];
		if (!empty ($candles_available)) {
			foreach ($candles_available as $candle_name) {
				$candle_duration = Binance::candles [$candle_name];
				$candles_count = $x_width / $candle_duration;
				if ($candles_count <= $max_candles) {
					break;
				}
			}
			
			// klines
			$sql = "
				SELECT	open_time, open
				FROM	" . Kline::table . "
				WHERE	symbol = ?
				AND		candle_size = ?
				AND		open_time >= ?
				AND 	open_time <= ?
				AND 	UNIX_TIMESTAMP(open_time) % ? = 0
			";
			$params = [
				$symbol,
				$candle_name,
				$start_sql,
				$end_sql,
				$candle_duration,
			];
			$klines = $db->exec ($sql, $params);
			if (empty ($klines)) {
				throw new ErrorException("no data retrieved");
			}
	
			
			foreach ($klines as $kline) {
				$x = new DateTime ($kline ["open_time"])->getTimestamp() * 1000;
				$y = $kline ["open"];
				$klines_data [] = [
					"x" => $x,
					"y" => $y,
				];
			}
	
			// keypoints
			foreach ($klines as $kline) {
				$x = new DateTime ($kline ["open_time"])->getTimestamp() * 1000;
				if ($start_tsms <= $x && $x <= $end_tsms) {
					$y = $kline ["open"];
					if (empty($min_x)) { // first value
						$min_x = $max_x = $x;
						$min_y = $max_y = $y;
					}
					else {
						if ($y < $min_y) {
							$min_y = $y;
							$min_x = $x;
						}
						if ($y > $max_y) {
							$max_y = $y;
							$max_x = $x;
						}
					}
				}
			}
			
			$keyPoints = [];
			if (!empty ($klines_data)) {
				$keyPoints = [
					[
						"x"		=> $min_x,
						"y"		=> $min_y,
						"label" => "min",
						"color"	=> "orange",
					],
					[
						"x"		=> $max_x,
						"y"		=> $max_y,
						"label"	=> "max",
						"color" => "yellow",
					]
				];
			}
	
			$datasets = 
			[
				[
					"label"						=> 'ETHEUR',
					"data"						=> $klines_data,
					"borderColor" 				=> "#55F",
					"backgroundColor" 			=> "#55F",
					"borderWidth" 				=> 2,
					"pointRadius"				=> 0, // ❌ pas de points
					"pointHoverRadius" 			=> 0, // ❌ même au survol
					"tension" 					=> 0.3, // ✅ lissage (0 → lignes droites)
					"cubicInterpolationMode" 	=> 'monotone' // ✅ lissage propre (finance-friendly)
				],
				[
					"label" 					=> 'Points clés',
					"data" 						=> $keyPoints,
					"type" 						=> 'scatter', // important pour avoir seulement des points
					"pointRadius" 				=> 6, // taille des points
					"pointBackgroundColor" 		=> 'red',
					"showLine" 					=> false, // pas de ligne
				],
			];
			
			// stats
			$nb_stats = count (IndexCtrl::$stats);
			$i = 0;
			foreach (IndexCtrl::$stats as $stat_name => $stat_conf) {
				$sql = "
					SELECT	open_time, open
					FROM	" . Stat::table . "
					WHERE	symbol = ?
					AND		candle_size = ?
					AND		open_time >= ?
					AND 	open_time <= ?
					AND 	UNIX_TIMESTAMP(open_time) % ? = 0
					AND		name = ?
				";
				$params = [
					$symbol,
					$candle_name,
					$start_sql,
					$end_sql,
					$candle_duration,
					$stat_conf ["name"],
				];
				$stats = $db->exec ($sql, $params);
				if (! empty ($stats)) {
					$stats_data [$stat_name] = [];
					foreach ($stats as $stat) {
						$stats_data [$stat_name] [] = [
							"x" => new DateTime ($stat ["open_time"])->getTimestamp() * 1000,
							"y" => $stat ["open"],
						];
					}
				
					$alpha = 1 - ($i * (1 / $nb_stats));
					$datasets [] =
						[
							"label"				=> $stat_name,
							"data"				=> $stats_data [$stat_name],
							"hidden"			=> ($stat_name !== static::$sma_name),
							"borderColor"		=> "rgba(0, 175, 0, {$alpha})",
							"backgroundColor" 	=> "rgba(0, 175, 0, {$alpha})",
							"borderWidth"		=> 2,
							"pointRadius"		=> 0, // ❌ pas de points
							"pointHoverRadius"	=> 0, // ❌ même au survol
							"tension"			=> 0.3, // ✅ lissage (0 → lignes droites)
							"cubicInterpolationMode" => 'monotone' // ✅ lissage propre (finance-friendly)
						];
					$i ++;
				}
			}
		}
		
		// simulation
		if (!empty ($simulation_id)) {
			$simulation_data = [];
			$simulation_step_wrapper = new SimulationStep;
			$simulation_step_wrapper->has("kline_id", ["? <= open_time AND open_time <= ?", $start_sql, $end_sql]);
			$simulation_steps = $simulation_step_wrapper->find(["simulation_id = ?", $simulation_id], ["order" => "id ASC"]);
			foreach ($simulation_steps as $simulation_step) {
				$kline = $simulation_step->kline_id;
				$color = "black";
				switch ($simulation_step->type) {
					case "buy":
						$color = "#00AA55";
						break;
					case "sell":
						$color = "#DD0000";
						break;
					default:
						
				}
				$simulation_data [] = 
					[
						"x"		=> $simulation_step->kline_id->open_time->getTimestamp() * 1000,
						"y"		=> $kline->open,
						"label" => $simulation_step->description,
						"color"	=> $color,
					]
				;
			}
			$simulation_dataset = 
			[
				"label" 				=> 'simulation',
				"data" 					=> $simulation_data,
				"type" 					=> 'scatter', // important pour avoir seulement des points
				"pointRadius" 			=> 6, // taille des points
				"pointBackgroundColor" 	=> 'blue',
				"showLine" 				=> false, // pas de ligne
			];
			$datasets [] = $simulation_dataset;
		}

		header ('Content-Type: application/json; charset=utf-8');
		echo json_encode ($datasets);
		exit;
	}
	
}
