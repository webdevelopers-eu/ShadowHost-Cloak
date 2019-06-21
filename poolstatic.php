<?php

namespace ShadowHost\Cloak;

/**
 * Static servers from .json file that will not be
 * removed.
 *
 * Reads proxy servers from read-only .json file and does not update it.
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-06-20T15:58:20+0200
 * @access     public
 */
class PoolStatic extends PoolFileStorage {
    /**
     * Unlimited maxFailScore.
     * @access protected
     * @var float 0=unlimited
     */
    protected $maxFailScore=0;

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

}
