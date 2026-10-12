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
	
	
	public static function simulateGET (Base $f3, $url, $controler)
	{
		$page = [
			"module"	=>	"COMMON__",
			"layout"	=>	"default",
			"name"		=>	"simulate",
			"title"		=>	"Simulate",
			"breadcrumbs" => static::breadcrumbs(),
		];
		static::renderPage($page);
	}
	
	public static function trading_simulate () : void
	{
		# init
		set_time_limit (0);
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */

		# config
		$sell_min_margin = 2;
		$sell_floor_margin = 1;
		$buy_min_margin = 2;
		$buy_floor_margin = 1;
		$start_ETH = 0;
		$start_EUR = 1000;
		$sma_name = static::$sma_name;

		$sma_conf = IndexCtrl::$stats [$sma_name]; #TODO useless ?

		$now = new DateTimeImmutable;

		$simulation = new Simulation;
		$simulation->symbol =			IndexCtrl::$symbol;
		$simulation->open_time =		IndexCtrl::$start_sql;
		$simulation->close_time =		IndexCtrl::$end_sql;
		$simulation->execution_time =	$now->format(Stuff::datetime_sql_format);
		$simulation->description =		trim("
			simple margin algorythm
			candle size = " . IndexCtrl::$small_candle_size . "
			smoothing stat = {$sma_name}
			sell min margin : {$sell_min_margin}% ({$sell_floor_margin}% floor)
			buy min margin : {$buy_min_margin}% ({$buy_floor_margin}% floor)
		"); //TODO
		$simulation->save();

		# start reading data
		$offset = 0;
		$kline_wrapper = new Kline;
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
			OFFSET	?";
		$params = [IndexCtrl::$symbol, IndexCtrl::$small_candle_size, IndexCtrl::$start_sql, IndexCtrl::$end_sql, $sma_name, IndexCtrl::$sql_read_limit, $offset];
		while ($data = $db->exec ($sql, $params)) {
			foreach ($data as $kline_wrapper) {
				$dt_formated = $kline_wrapper ["open_time"];
				$price = $kline_wrapper ["open"];
				$price_formated = Stuff::format_float_significative ($price, 6);
				$simulation_step = new SimulationStep;
				$simulation_step->simulation_id = $simulation;
				$simulation_step->kline_id = $kline_wrapper ["id"];
				
				if ($offset === 0) { # start variables
					$ETH = $start_ETH;
					$EUR = $start_EUR;
					
					$reference_price = $price; # value of my last crypto movement #TODO remove and use $sell_assets_history & $buy_assets_history
					$high = $price; # highest value since last action
					$low = $price; # lowest value since last action
					$start_total = $start_ETH * $price + $start_EUR;
					
					echo "[{$dt_formated}] ({$price_formated}) simulation start <br/>" . PHP_EOL;
					echo "<ul>" . PHP_EOL;
					if ($ETH > 0) {
						$ETH_converted = $ETH * $price;
						$sell_assets_history = [$ETH_converted];
						$buy_assets_history = [$ETH];
						$ETH_converted_formated = Stuff::format_float_significative ($ETH_converted, 6);
						echo "<li>{$ETH} ETH = {$ETH_converted_formated} € </li>" . PHP_EOL;

						$simulation_step->time =				$kline_wrapper ["open_time"];
						$simulation_step->order_type =			"buy";
						$simulation_step->base_amount =			$ETH;
						$simulation_step->base_currency =		"ETH";
						$simulation_step->quote_amount =		0;
						$simulation_step->quote_currency =		"EUR";
						$simulation_step->save();
					}
					if ($EUR > 0) {
						$EUR_converted = $EUR / $price;
						$sell_assets_history = [$EUR];
						$buy_assets_history = [$EUR_converted];
						$EUR_converted_formated = Stuff::format_float_significative ($EUR_converted, 6);
						echo "<li>{$EUR} € = {$EUR_converted_formated} ETH </li>" . PHP_EOL;

						$simulation_step->time =				$kline_wrapper ["open_time"];
						$simulation_step->order_type =			"buy";
						$simulation_step->base_amount =			$EUR;
						$simulation_step->base_currency =		"EUR";
						$simulation_step->quote_amount =		0;
						$simulation_step->quote_currency =		"EUR";
						$simulation_step->save();
					}
					echo "</ul>" . PHP_EOL;
					echo " <br/>" . PHP_EOL;
					echo " <hr>" . PHP_EOL;
					echo " <br/>" . PHP_EOL;
				}
				
				$SMA_price = $kline_wrapper [$sma_name];
				$price_smoothed = $SMA_price; # we use SMA as smoothed price
				
				$high = max ($price_smoothed, $high);
				$low = min ($price_smoothed, $low);
				
				if ($ETH > 0) { # I own crypto
					if ($price_smoothed > ($reference_price * (1 + $sell_min_margin/100))) { # price raised a lot
						if ($price_smoothed < ($high * (1 - $sell_floor_margin / 100))) { # seems like we floored
							// if ($price > ($reference_price * (1 + $sell_min_margin/100))) { # also check current price
								?>
								<div class="text-end">
								<?php
								// echo "reference = $reference_price <br/>" . PHP_EOL;
								// echo "value_ = $value_ <br/>" . PHP_EOL;
								// echo "value = $value <br/>" . PHP_EOL;
								$EUR = $ETH * $price;
								$EUR_formated = Stuff::format_float_significative($EUR, 6);
								$quote_amount = $ETH;
								$ETH = 0;
								$low = $high = $reference_price = $price;
								$last_sell_assets = $sell_assets_history [array_key_last($sell_assets_history)];
								$delta_pct = ($EUR - $last_sell_assets) / $last_sell_assets * 100;
								$delta_pct_formated = Stuff::format_percent($delta_pct);
								$sell_assets_history [] = $EUR;
								echo "[{$dt_formated}] ({$price_formated}) : selling --> {$EUR_formated} € ({$delta_pct_formated})" . PHP_EOL;
								?>
								</div>
								<?php
								$simulation_step->time =				$kline_wrapper ["open_time"];
								$simulation_step->order_type =			"sell";
								$simulation_step->base_amount =			$EUR;
								$simulation_step->base_currency =		"EUR";
								$simulation_step->quote_amount =		$quote_amount;
								$simulation_step->quote_currency =		"ETH";
								$simulation_step->save();
							// }
						}
					}
				}
				
				if ($EUR > 0) { # I own euros
					if ($price_smoothed < ($reference_price * (1 - $buy_min_margin / 100))) { # value dropped a lot
						if ($price_smoothed > ($low * (1 + $buy_floor_margin / 100))) { # seems like we floored
							$ETH = $EUR / $price;
							$ETH_formated = Stuff::format_float_significative($ETH, 6);
							$quote_amount = $EUR;
							$EUR = 0;
							$low = $high = $reference_price = $price;
							$last_buy_assets = $buy_assets_history [array_key_last ($buy_assets_history)];
							$delta_pct = ($ETH - $last_buy_assets) / $last_buy_assets * 100;
							$delta_pct_formated = Stuff::format_percent($delta_pct);
							$buy_assets_history [] = $ETH;
							echo "[{$dt_formated}] ({$price_formated}) buying --> {$ETH_formated} ETH ({$delta_pct_formated}) <br/>" . PHP_EOL;
							
							$simulation_step->time =				$kline_wrapper ["open_time"];
							$simulation_step->order_type =			"buy";
							$simulation_step->base_amount =			$ETH;
							$simulation_step->base_currency =		"ETH";
							$simulation_step->quote_amount =		$quote_amount;
							$simulation_step->quote_currency =		"EUR";
							$simulation_step->save();
						}
					}
				}
				$last_kline = $kline_wrapper;
				$offset ++;
				$params = [IndexCtrl::$symbol, IndexCtrl::$small_candle_size, IndexCtrl::$start_sql, IndexCtrl::$end_sql, $sma_name, IndexCtrl::$sql_read_limit, $offset];
			}
		}
		
		
		# stats
		if (empty ($last_kline)) {
			echo "no data loaded. </br>" . PHP_EOL;
			exit;
		}
		echo " <br/>" . PHP_EOL;
		echo " <hr/>" . PHP_EOL;
		echo " <br/>" . PHP_EOL;
		$dt_formated = $last_kline ["open_time"];
		echo "[{$dt_formated}] ({$price_formated}) simulation end <br/>" . PHP_EOL;
		echo "<ul>" . PHP_EOL;
		if ($ETH > 0) {
			$ETH_formated = Stuff::format_float_significative ($ETH, 6);
			$ETH_converted = $ETH * $price;
			$ETH_converted_formated = Stuff::format_float_significative ($ETH_converted, 6);
			echo "<li>{$ETH_formated} ETH @ {$price_formated} => {$ETH_converted_formated} € </li/>" . PHP_EOL;
		}
		if ($EUR > 0) {
			$EUR_formated = Stuff::format_float_significative ($EUR, 6);
			echo "<li>{$EUR_formated} € </li>" . PHP_EOL;
		}
		echo "</ul>" . PHP_EOL;
		//TODO calculate and store simulation result
		echo '<a href="' . $f3->get("BASE") . $f3->alias("chart", [], ["simulation_id" => $simulation->id]) . '">chart</a> <br/> <br/>' . PHP_EOL;
		
		$end_total = $ETH * $price + $EUR;
		$PaL = ($end_total - $start_total); # Profit and Loss
		$PaL_formated = Stuff::format_EUR ($PaL);
		$RoI = $PaL / $start_total; # Return on Investment
		$RoI_formated = Stuff::format_percent ($RoI * 100, 2);
		echo "<b>==> ROI = {$RoI_formated} ({$PaL_formated})</b> <br/>" . PHP_EOL;
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

		$klines_data = [];
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
		foreach (IndexCtrl::$stats as $stat_conf) {
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
			if (empty ($stats)) {
				throw new ErrorException("no stat retrieved for {$stat_conf["name"]} with candle {$candle_name}");
			}
			
			$stats_data [$stat_conf ["name"]] = [];
			foreach ($stats as $stat) {
				$stats_data [$stat_conf ["name"]] [] = [
					"x" => new DateTime ($stat ["open_time"])->getTimestamp() * 1000,
					"y" => $stat ["open"],
				];
			}
		}
		
		$i = 0;
		foreach (IndexCtrl::$stats as $stat_name => $stat_conf) {
			$nb_stats = count (IndexCtrl::$stats);
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
		
		// simulation
		if (!empty ($simulation_id)) {
			$simulation_data = [];
			$simulation_step_wrapper = new SimulationStep;
			$simulation_steps = $simulation_step_wrapper->find(["simulation_id = ? AND ? <= time AND time <= ?", $simulation_id, $start_sql, $end_sql], ["order" => "time ASC"]);
			foreach ($simulation_steps as $simulation_step) {
				$kline = $simulation_step->kline_id;
				$simulation_data [] = 
					[
						"x"		=> $simulation_step->time->getTimestamp() * 1000,
						"y"		=> $kline->open,
						"label" => "{$simulation_step->order_type} : {$simulation_step->quote_amount} {$simulation_step->quote_currency} -> {$simulation_step->base_amount} {$simulation_step->base_currency}",
						"color"	=> $simulation_step->order_type === "buy" ? "#00AA55" : "#DD0000",
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
