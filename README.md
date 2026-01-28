# install
git clone
composer install
cp conf/user.dist.ini user.ini
vim user.ini


# config
default password is 'admin', don't forget to change it



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
- better algorythm



# NEXT
- dashboard
	- refaire completement lavec la nouvelle compta (Accounting)
	- ajouter les infos stop loss & take profit en cours pour chaque symbol




- aller voir du côté de earn, y'a un peu de USDC dessus aussi




- pb calcul ETH : un trade DASHETH n'est pas récupéré (04/11)
getusedsymbols n'est pas fiable (basé sur orderlists, donc exclue les orders non groupés avec orderList=-1)
aucun moyen n'existe pour récupérer TOUS les orders via l'API, ni les symbols utilisés
il faut partir de la balance, et trouver tous les symboles associés, et récupérer les trades de chaque symbole
[on pourra optimiser ca à l'aide des websockets, pour alléger le live]
la contrepartie de la vente d'ETH en DASH est complexe, il faut passer par USDC pour arriver en EUR
il faut se basser sur les assets, pas les symbols
faire la compta globale de tous les trades de tous les symbols
impacter les 2 parties à chaque trade
- calculer les commissions
- pour calculer la contrepartie en reference_asset, il faudra parfois convertir. obtenir le taux historique via GET /api/v3/klines?symbol=XXX&interval=1m&startTime=T&endTime=T



- récupérer les trades en live avec un WS
- forcer l'actualisation périodique des trades pour les symboles / assets pertinents
	- balance > 0
	- trades passés
- mettre les trades spot en BdD, idem convert


- trades update
	- route CLI pour forcer l'update des symbols sans trades
	- doc install : crontab vers cette rotue tous les jours


