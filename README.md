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

- manque un index unique sur crypto_pair / candle_size / open_time


# NEXT
- manque balance SKY sur spot get account balance :

	Teste l’API Capital (SAPI) :
	GET /sapi/v1/capital/config/getall
	👉 C’est LA clé.

	Tu devrais voir une entrée du type :
	{
	  "coin": "SKY",
	  "free": "1878",
	  "locked": "0",
	  "name": "Sky",
	  "networkList": [...]
	}

	⚠️ Cette API :
	n’est PAS /api/v3
	nécessite permissions Wallet
	est souvent oubliée
	
	=> les orders (stop loss, take profit) pour le SKY n'apparaissent pas non plus
	(c'est pour ca que ce sont les seuls pour lesquels on recoit des emails)



- le calcul trade entry avg pour DOGE donne du reste alors que je suis à 0 depuis longtemps
	DOGE acheté le 21/10, converti en euros le 26/10 : n'apparait pas dans les trades & orders spot

	✅ L’API à utiliser : Convert History
	Endpoint officiel
	GET /sapi/v1/convert/tradeFlow

(anciennement convert/orderHistory selon versions)

Exemple de réponse
{
  "list": [
    {
      "orderId": "123456789",
      "fromAsset": "SKY",
      "fromAmount": "1878",
      "toAsset": "USDC",
      "toAmount": "101.23",
      "price": "0.0539",
      "status": "SUCCESS",
      "createTime": 1712345678901
    }
  ]
}



- dashboard : ajouter les order stop loss & take profit en cours pour chaque symbol



- ajouter la balance FIAT (EUR)


- aller voir du côté de earn, y'a un peu de USDC dessus aussi
