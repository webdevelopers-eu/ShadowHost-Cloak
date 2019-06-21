ShadowHost Cloak
================

Anonymous asynchronous PHP proxy that processes multiple HTTP requests simultaneously throw automatically downloaded list of proxy servers.

Example:

```php
require('load.php');

// List of proxy servers
$pool=new \ShadowHost\Cloak\PoolXicidaili(__DIR__."/proxy-servers.json");
$pool->autoUpdate(); // Download fresh proxies if needed.

$proxy=new \ShadowHost\Cloak\Proxy($pool);
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
