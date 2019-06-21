<?php

namespace ShadowHost\Cloak;

/**
 * Represents info about Proxy Server.
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-06-01 12:33:04 UTC
 * @access     public
 */
class Server implements \JsonSerializable {

    public $ip;
    public $port;

    /**
     * How many times did server malfunction
     * @access public
     * @var int
     */
    public $failScore=0;

    /**
     * Proxy type - not used yet.
     * @access public
     * @var string
     */
    public $type='HTTP';


    /**
     * Constructor.
     *
     * @access public
     * @param array $data array(ip, port, type, failScore)
     * @return void
     */
    public function __construct($data=null) {
        if (is_array($data)) {
            foreach($data as $k => $v) {
                $this->$k=$v;
            }
        }
    }

    public function jsonSerialize() {
        return array(
            'ip' => $this->ip,
            'port' => (int) $this->port,
            'failScore' => (float) $this->failScore,
            'type' => $this->type
        );
    }

    public function __toString() {
        return $this->ip.':'.$this->port;
    }
}
