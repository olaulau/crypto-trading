# crypto-trading

## description
Binance crypto trading companion


## features
- dashboard
	- list actual crypto assets
	- convert price into refecence money
	- show actual asset price
	- show new orders, including TP/SL
	- show how much we injected from the bank
	- show dividendes
	- total
- data
	- mass import of downloaded kline files
	- global and user data from REST API
	- live global and user data from WebSocket
  

## requirements
- Linux host (some process functions may only work on this OS)
- web server with .htaccess support, URL rewriting (Apache 2.4 recommended)
- PHP >= 8.4 (8.5 compat on the way)
- MySQL DB (recommended recent MariaDB)


## install
```
git clone https://github.com/olaulau/crypto-trading
cd crypto-trading
composer install
npm i
cp conf/user.dist.ini user.ini
vim user.ini
	fill-in values
```


## setup
```
crontab -e
	*   *   *   *   *   cd crypto-trading/ || exit 1; { /usr/bin/date; /usr/bin/php index.php cli cron; } >> tmp/log/cron.log 2>&1
screen -S crypto-trading_ws_miniTicker
	php index.php ws miniTicker |& tee -a tmp/log/ws_miniTicker.log
screen -S crypto-trading_ws_bookTicker
	php index.php ws bookTicker ETHUSDC |& tee -a tmp/log/ws_bookTicker_ethusdc.log
screen -S crypto-trading_ws_uds
	php index.php ws uds |& tee -a tmp/log/ws_uds.log
```


## config
default password is ```admin```, don't forget to change it (see ```user[.dist].ini```)


## dependencies
- fat-free framework
	- f3-cortex ORM
- bootstrap
- jquery
- select2
- chart.js
- font awesome
- binance/binance-connector-php
- textalk/websocket



## TODO
- graph
	- values
	- SMA
	- buyings & sellings

- store simulation results

- manual simulation
	- graph with only values
	- click to buy / sell (date AJAX)

- calculate many indicators
- better algorithm



## NEXT
- accounting
	- calculate avg cost
		- historical value : GET /api/v3/klines?symbol=XXX&interval=1m&startTime=T&endTime=T



- forcer l'actualisation périodique des trades pour les symboles / assets pertinents
	- balance > 0
	- trades passés


- trades update
	- route CLI pour forcer l'update des symbols sans trades => cron daily


- new architecture
	- backend : auto fill DB (WS + periodic REST)
		- page to force DB fill (easy dev)
		- script to get data from prod
		- launch and watch WS scripts
	- frontend : only query DB, never use cache methods
	

- balances
	- put all functions into Balance class
	- set lastUpdate in WS UDS balance update too


- cron / ws
	- process handler
	- auto run cron & WS if needed



cron minutly	->
						watchdog : infinite loop checking if cron is running	->		cron : checek subscripts are running
webcron			->	
