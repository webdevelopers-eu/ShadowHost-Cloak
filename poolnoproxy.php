<?php

namespace ShadowHost\Cloak;

/**
 * Pool with only one special Server in the pool that has empty values which
 * causes \ShadowHost\Cloak\Proxy to bypass proxy and use direct requests.
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-06-21T15:01:43+0200
 * @access     public
 */
class PoolNoProxy implements PoolIfc {

    private $proxy;

    public function __construct() {
        $this->proxy=new Server(array('ip' => false, 'port' => false, 'failScore' => 0, 'type' => false));
    }

    public function get() {
        return $this->proxy;
    }

    public function addFailScore(Server $server, $failScore) {
        return 0;
    }

    public function resetFailScore(Server $server) {
        return 0;
    }

    public function getLastUpdate() {
        return time();
    }

    public function update() {
    }

    public function autoUpdate($countLimit=null, $timeLimit=null) {
    }

    public function jsonSerialize() {
        return array('pool' => array($this->proxy));
    }
}
