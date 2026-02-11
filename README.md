# install
git clone
composer install
cp conf/user.dist.ini user.ini
vim user.ini
	fill-in values
crontab -e
	*   *   *   *   *   cd crypto-trading/ || exit 1; { /usr/bin/date; /usr/bin/php index.php cli cron; } >> tmp/log/cron.log 2>&1
screen -S crypto-trading_ws_tickers
	cd crypto-trading/
	php index.php ws tickers 2>&1 |& tee -a tmp/log/ws_tickers.log




# config
default password is 'admin', don't forget to change it (see user[.dist].ini)



# TODO
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



# NEXT
- accounting
	- calculate avg cost
		- historical value : GET /api/v3/klines?symbol=XXX&interval=1m&startTime=T&endTime=T



- récupérer les trades en live avec un WS
	=> tester en demo
- forcer l'actualisation périodique des trades pour les symboles / assets pertinents
	- balance > 0
	- trades passés


- trades update
	- route CLI pour forcer l'update des symbols sans trades => cron daily



- orders
	- WS to get new orders live


- new architecture
	- backend : auto fill DB (WS + periodic REST)
		- page to force DB fill (easy dev)
		- script to get data from prod
		- launch and watch WS scripts
	- frontend : only query DB, never use cache methods
	

