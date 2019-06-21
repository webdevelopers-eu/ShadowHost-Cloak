<?php

namespace ShadowHost\Cloak;

/**
 * Generic Pool interface.
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-06-21T14:45:04+0200
 * @access     public
 */
interface PoolIfc extends \JsonSerializable {

    /**
     * Choose proxy and return its info.
     *
     * @access public
     * @return array (ip, port, type [, failScore])
     */
    public function get();

    /**
     * Penalize the server for being unusable.
     *
     * @access public
     * @param \ShadowHost\Cloak\Server $server
     * @param float $failScore how much to penalize the $server. If total penalty reaches $this->maxFailScore $server will be removed from the pool. If $failScore===false then it will reset score to 0 - use $this->resetFailScore() to do that.
     * @return int -1 if record was removed, int current failScore
     */
    public function addFailScore(Server $server, $failScore);

    /**
     * Reset the failScore of the Server to 0.
     *
     * Alias for $this->addFailScore($server, 0);
     *
     * @access public
     * @param \ShadowHost\Cloak\Server $server
     * @return float 0
     */
    public function resetFailScore(Server $server);

    /**
     * Returns stamp when this pool was updated last time.
     *
     * @access public
     * @return int unix timestamp or 0 if never
     */
    public function getLastUpdate();

    /**
     * If amount of proxies in the list decreases bellow or equal to
     * $countLimit and newest proxy in the list is older then
     * $timeLimit then run update of proxies automatically.
     *
     * @access private
     * @param int $countLimit update only if amount of proxies reaches this or less
     * @param string $timeLimit strtotime() compatible string. Update only if freshest proxy in the list is older the this.
     * @return bool true if update was executed otherwise false
     */
    public function autoUpdate($countLimit=3, $timeLimit='-1 day');

    /**
     * Download new list of proxy servers unconditionally.
     *
     * Prefere using conditional $this->autoUpdate() instead.
     *
     * @access public
     * @return void
     */
    public function update();

}
