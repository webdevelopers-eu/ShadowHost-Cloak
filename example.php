<?php

require('load.php');

// List of proxy servers
$pool=new \ShadowHost\Cloak\PoolXicidaili(__DIR__."/proxy-servers.json");
// $pool=\ShadowHost\Cloak\PoolStatic(__DIR__."/my-list.json");

// Download fresh proxies if needed.
$pool->autoUpdate();

$proxy=new \ShadowHost\Cloak\Proxy($pool);
// $proxy=new \ShadowHost\Cloak\Proxy(); // no $pool to bypass proxy and make direct requests
$proxy->debugPrint=true; // print processing info
$proxy->timeout=5; // default 60s

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
