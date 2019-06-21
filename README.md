ShadowHost Cloak
================

Anonymously process multiple asynchronous HTTP requests simultaneously using automatically updated list of proxy servers.

Example:

```php
require('load.php');

// List of proxy servers
$pool=new \ShadowHost\Cloak\PoolXicidaili(__DIR__."/proxy-servers.json");
$pool->autoUpdate(); // Download fresh proxies if needed.

$proxy=new \ShadowHost\Cloak\Proxy($pool);
// $proxy=new \ShadowHost\Cloak\Proxy(); // no $pool to bypass proxy and make direct requests

// Optional settings
$proxy->debugPrint=true; // print processing info
$proxy->timeout=5; // default 60

// Queue multiple requests
$requests=array();
$requests[]=$proxy->createRequest("GET", "http://www.example.com/");
$requests[]=$proxy->createRequest("GET", "http://www.example.com/2");
$requests[]=$proxy->createRequest("GET", "http://www.example.com/3");

$proxy->execWait();

// Display results
foreach($requests as $req) {
	echo "----------------------------------\n";
	echo "URL: $req->url\n";
	echo "Proxy: $req->proxy\n";
	echo "Proxy status: ".@$req->proxyHeaders['@code']." ".@$req->proxyHeaders['@message']."\n";
	echo "Response status: ".$req->responseHeaders['@code']." ".$req->responseHeaders['@message']."\n";
	echo "Response length: ".strlen($req->responseBody)."\n";
	echo "\n";
	echo $req->responseBody;
}
```

Requirements
------------

* PHP 5
* PHP CURL extension support
* Internet connection ;-)

Features
--------

* Automatically tries to find working proxy server.
* Automatically updates reliability score in .json file and removes permanently unreliable proxy servers from the list.
* Able to execute many requests simultaneously.
* Very easy API.
* Easy to implement other types of proxy server lists that use other online listings.

Notes
-----

* Initial start may be slow - update of proxy server list may take several minutes.
* Proxies downloaded from Chinese https://xicidaili.com/ are very unreliable so it may take time to find working server and process request. Feel free to implement more reliable list.

API Overview
------------

`namespace \ShadowHost\Cloak;`
* **interface PoolIfc** `poolifc.php` - all Pools must implement this Interface
* **abstract class PoolFileStorage** `poolfilestorage.php` - parent class for pools that use `.json` file as DB storage.
* **class PoolNoProxy** `poolnoproxy.php` - special pool with only one special proxy server that is empty. It causes \ShadowHost\Cloak\Proxy to bypass proxy and make direct requests.
* **class PoolStatic** `poolstatic.php` - pool that uses read-only `.json` file that will not be modified. E.g. neither list updates nor reliability score updates will be saved to this file. Use if you have hand-crafterd list of proxies you want to always use.
* **class PoolXicidaili** `poolxicidaili.php` - pool that downloads list of proxies from Chinese site https://xicidaili.com/
* **class Proxy** `proxy.php` - object responsible for executing requests.
* **class Request** `request.php` - represents one request.
* **class Server** `server.php` - represents one proxy server.
