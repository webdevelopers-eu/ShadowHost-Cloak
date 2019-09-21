<?php

namespace ShadowHost\Cloak;

/**
 * Example:
 *
 * $proxy=new \ShadowHost\Cloak\Proxy(new \ShadowHost\Cloak\PoolXicidaili("proxies.json));
 *
 * $request1=new \ShadowHost\Cloak\Request("GET", "http://www.example.com/?proxy-test-1");
 * $request2=new \ShadowHost\Cloak\Request("GET", "http://www.example.com/?proxy-test-2");
 *
 * $proxy->addRequest($request1);
 * $proxy->addRequest($request2);
 *
 * $proxy->execWait();
 *
 * print_r($request1->responseHeaders);
 * echo substr($request1->responseBody, 0, 512);
 *
 * print_r($request2->responseHeaders);
 * echo substr($request2->responseBody, 0, 512);
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-05-25 18:59:19 UTC
 * @access     public
 */
class Proxy {

    /**
     * If set to true it prints basic processing info to the output.
     *
     * $proxy->debugPrint=true;
     *
     * @access public
     * @var bool false=silent, true=print processing info
     */
    public $debugPrint=false;

    /**
     * Config array that defines behavior when
     * given HTTP status is returned.
     *
     * If status has no config option the option from
     * "default" config is used.
     *
     * If specific action is found it is first merged with "default"
     * and then applied.
     *
     * @access public
     * @var array
     */
    public $httpStatusActions=array(
        "default" => array(
            "failScore" => 0, // Penalize server - see \ShadowHost\Cloak\PoolIfc::addFailScore()
            "retry" => false, // Try again another proxy server when this error is received
            "resetFailScore" => true // Reset failScore to 0
        ),
        400 => array( // bad request
            "failScore" => 1,
            "retry" => true,
            "resetFailScore" => false
        ),
        406 => array( // not acceptable - bad ACCEPT header
            "failScore" => 0.2,
            "retry" => true,
            "resetFailScore" => false
        ),
        505 => array( // HTTP Version Not Supported
            "failScore" => 0.2,
            "retry" => true,
            "resetFailScore" => false
        ),
    );

    /**
     * Config array that defines behavior when
     * given curl status is returned.
     *
     * If status has no config option the option from
     * "default" config is used.
     *
     * If specific action is found it is first merged with "default"
     * and then applied.
     *
     * @access public
     * @var array
     */
    public $curlStatusActions=array(
        "default" => array(
            "failScore" => 1, // Penalize server - see \ShadowHost\Cloak\PoolIfc::addFailScore()
            "retry" => true, // Try again another proxy server when this error is received
            "resetFailScore" => false // Reset failScore to 0
        ),
        // No error
        0 => array(
            "failScore" => 0,
            "retry" => false,
            "resetFailScore" => true
        ),
        // Couldn't connect to server
        7 => array(
            "failScore" => 1.5,
        ),
        // Timeout was reached
        28 => array(
            "failScore" => 0.5,
        ),
        // SSL connect error
        35 => array(
            "failScore" => 0.2,
        ),
        // Server returned nothing (no headers, no data)
        52 => array(
            "failScore" => 0.5,
        ),
        // Failure when receiving data from the peer
        // this is shown for 503 Too Many Requests proxy error
        56 => array(
            "failScore" => 0.2,
        ),
    );

    /**
     * List of requests
     * @access private
     * @var array of \ShadowHost\Cloak\Request objects
     */
    private $queue=array();

    /**
     * Connection timeout.
     *
     * Sets CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT to this value.
     *
     * @access public
     * @var int seconds
     */
    public $timeout=60;

    /**
     * List of proxy servers.
     *
     * @access public
     * @var \ShadowHost\Cloak\PoolIfc
     */
    public $pool;

    /**
     * Constructor.
     *
     * @access public
     * @param \ShadowHost\Cloak\PoolIfc $pool Object implementing PoolIfc to access list of proxy servers. Use null or PoolNoProxy object for direct requests.
     * @return void
     */
    function __construct(PoolIfc $pool=null) {
        $this->pool=$pool ?: new PoolNoProxy;
    }

    /**
     * Create new \ShadowHost\Cloak\Request object and
     * queue it for processing.
     *
     * @see \ShadowHost\Cloak\Proxy::execWait()
     * @see \ShadowHost\Cloak\Proxy::queue()
     * @access public
     * @param string $method Optional HTTP request method.
     * @param string $url Optional HTTP URL to make request to.
     * @param mixed $data Optional data for POST requests.
     * @param string $userAgent Optional user-agent string.
     * @return \ShadowHost\Cloak\Request
     */
    public function createRequest($method=null, $url=null, $data=null, $userAgent=null) {
        return $this->queue(new Request($method, $url, $data, $userAgent));
    }

    /**
     * Add new request object to the list for later
     * execution using $this->execWait()
     *
     * @access public
     * @param \ShadowHost\Cloak\Request $req
     * @return \ShadowHost\Cloak\Request
     */
    public function queue(Request $req) {
        return $this->queue[]=$req;
    }

    /**
     * Execute all prepared requests. Wait for all to finishe then return.
     *
     * @access public
     * @return void
     */
    public function execWait() {
        $queue=$this->queue;
        $this->queue=array();
        $mh=curl_multi_init();

        // curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 8);
        // curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, 8);
        // curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 8);

        foreach($queue as $req) {
            $this->prepareRequest($req);
            curl_multi_add_handle($mh, $req->curlHandle);
        }

        do {
            $loop=false;
            $status=curl_multi_exec($mh, $active);
            if ($active) {
                $change=curl_multi_select($mh); // Wait a short time for more activity
            }

            while (false !== $msg=curl_multi_info_read($mh, $messageCount)) {
                foreach($queue as $req) {
                    if ($req->curlHandle !== $msg['handle']) continue;

                    $this->updateRequest($req, $msg);
                    curl_multi_remove_handle($mh, $req->curlHandle);

                    if (!$retry=$this->applyActions($req->curlStatus, $this->curlStatusActions, $req->proxy)) { // CURL status actions
                        $retry=$this->applyActions(@$req->responseHeaders['@code'], $this->httpStatusActions, $req->proxy); // HTTP status actions
                    }
                    $loop=$loop || $retry;

                    if ($this->debugPrint) {
                        echo
                            ($retry ? 'RETRY' : 'FINISHED').', '.
                            "${msg['handle']}, ACTIVE: $active, ".($status ? "CURL MULTI STATUS: $status, " : '').
                            'PROXY: '.$req->proxy.', STATUS: #'.$msg['result'].' '.curl_strerror($msg['result']).', '.$req->url."\n";
                    }

                    // Set new $req->proxy and re-try
                    if ($retry) {
                        $this->prepareRequest($req);
                        curl_multi_add_handle($mh, $req->curlHandle);
                    }
                }
            }
        } while ($loop || ($active && $status == CURLM_OK));

        curl_multi_close($mh);
    }

    /**
     * Apply actions based on return status code (HTTP or CURL).
     *
     * @access private
     * @param int $code status code
     * @param array $actionsList either $this->curlStatusActions or $this->httpStatusActions
     * @return bool true to retry request with another proxy, false to don't retry
     */
    private function applyActions($code, $actionsList, Server $proxy) {
        $code=(int) $code;
        $action=array_merge($actionsList['default'], @$actionsList[$code] ?: array());

        if ($action['resetFailScore']) {
            $this->pool->resetFailScore($proxy);
        } else {
            $this->pool->addFailScore($proxy, $action['failScore']);
        }

        return $action['retry'];
    }

    /**
     * Make CURL resource.
     *
     * It will alter $req object and it will set $req->curlHandle
     * resource and $req->proxy if $this->pool is available.
     *
     * @access public
     * @param \ShadowHost\Cloak\Request $req
     * @return resource made using curl_init() and configured according to this object's settings
     */
    public function prepareRequest(Request $req) {
        $req->proxy=$this->pool->get();
        if (!$req->proxy) {
            throw new Exception("ShadowHost Cloak proxy pool is empty!", 404);
        }

        $req->curlHandle=curl_init();

        curl_setopt($req->curlHandle, CURLOPT_URL, $req->url);
        curl_setopt($req->curlHandle, CURLOPT_HEADER, 0);
        curl_setopt($req->curlHandle, CURLOPT_USERAGENT, $req->userAgent);
        curl_setopt($req->curlHandle, CURLOPT_NOBODY, false);
        curl_setopt($req->curlHandle, CURLOPT_HEADER, true);
        curl_setopt($req->curlHandle, CURLOPT_FAILONERROR, false);
        curl_setopt($req->curlHandle, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($req->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req->curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($req->curlHandle, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($req->curlHandle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($req->curlHandle, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        // curl_setopt($req->curlHandle, CURLOPT_REFERER, 'http://myreferer.com');

        if ($req->method == 'POST') {
            curl_setopt($req->curlHandle, CURLOPT_POST, 1);
            curl_setopt($req->curlHandle, CURLOPT_SAFE_UPLOAD, false); // required as of PHP 5.6.0
            curl_setopt($req->curlHandle, CURLOPT_POSTFIELDS, $req->data);
        }

        if ($req->proxy->ip !== false || $req->proxy->port !== false) {
            curl_setopt($req->curlHandle, CURLOPT_HTTPPROXYTUNNEL, 0);
            curl_setopt($req->curlHandle, CURLOPT_PROXY, $req->proxy->ip);
            curl_setopt($req->curlHandle, CURLOPT_PROXYPORT, $req->proxy->port);
        }

        return $req->curlHandle;
    }

    /**
     * Copy properties on \ShadowHost\Cloak\Request object from
     * CURL message returned by curl_multi_info_read() method.
     *
     * @access private
     * @param \ShadowHost\Cloak\Request $req
     * @param array $msg returned by curl_multi_info_read()
     * @return void
     */
    private function updateRequest(Request $req, $msg) {
        $req->curlStatus=$msg['result'];
        $req->curlMessage=curl_strerror($req->curlStatus);
        $req->responseRaw=curl_multi_getcontent($req->curlHandle);
    }
}
