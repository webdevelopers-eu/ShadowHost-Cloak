<?php

namespace ShadowHost\Cloak;
use \Exception as Exception;

/**
 * Generic parent class for proxy servers pool that uses .json file as
 * DB storage. It is meant to be extended.
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-05-25 18:59:19 UTC
 * @access     public
 */
abstract class PoolFileStorage implements PoolIfc {

    /**
     * If proxy was penalized and failScore reaches this value it will be permanently removed from the pool.
     * @access protected
     * @var float 0=unlimited
     */
    protected $maxFailScore=3;

    /**
     * File path to config file.
     *
     * @access private
     * @var string
     */
    private $configFile;

    /**
     * Last select proxy key. Used for round-robin logic.
     *
     * @access private
     * @var int
     */
    private $poolIdx=-1;

    /**
     * List of servers
     *
     * @access protected
     * @var array ([ip, port, type], ...)
     */
    protected $pool=array();

    /**
     * Various meta data.
     * Classes that extend this may use it for their
     * own data.
     *
     * @access protected
     * @var array
     */
    protected $meta=array();

    /**
     * Open file handle after call to self::open()
     *
     * @access private
     * @var resource
     */
    private $f;

    /**
     * Constructor.
     *
     * @access public
     * @param string $configFile path to .json config file. If it does not exist it will be created and fresh list of proxies will be downloaded.
     * @return void
     */
    public function __construct($configFile) {
        $this->configFile=$configFile;

        if (file_exists($this->configFile)) {
            $this->open(false);
            $this->read();
            $this->close();
        } else {
            touch($this->configFile);
            $this->open(true);
            $this->write();
            $this->close();
            $this->update();
        }
    }

    /**
     * Choose proxy and return its info.
     *
     * @access public
     * @return array (ip, port, type [, failScore])
     */
    public function get() {
        if (!count($this->pool)) {
            $this->update();
            if (!count($this->pool)) {
                throw new Exception(get_class($this).": Failed to replenish the PROXY pool!", 5021);
            }
        }

        if ($this->poolIdx == -1) { // Random pointer init
            $this->poolIdx=rand(0, count($this->pool) - 1);
        }

        if ($this->poolIdx >= count($this->pool)) {
            $this->poolIdx=0;
        }


        $list=array_slice($this->pool, $this->poolIdx++, 1);

        return current($list);
        // return $this->pool[array_rand($this->pool)]; - we use round-robing now
    }

    /**
     * Penalize the server for being unusable.
     *
     * @access public
     * @param \ShadowHost\Cloak\Server $server
     * @param float $failScore how much to penalize the $server. If total penalty reaches $this->maxFailScore $server will be removed from the pool. If $failScore===false then it will reset score to 0 - use $this->resetFailScore() to do that.
     * @return int -1 if record was removed, int current failScore
     */
    public function addFailScore(Server $server, $failScore) {
        $key=(string) $server;

        if (!@$this->pool[$key]) { // Already removed
            return -1;
        }

        if (is_numeric($failScore) && !$failScore) {
            return $this->pool[$key]->failScore;
        }

        $this->open(true);
        $this->read();

        if (is_object(@$this->pool[$key])) {
            if ($failScore === false) {
                $this->pool[$key]->failScore=0;
            } else {
                @$this->pool[$key]->failScore+=$failScore;
            }

            // echo ((string) $this->pool[$key])." fail score ".@$this->pool[$key]->failScore."\n";

            if ($this->maxFailScore && $this->pool[$key]->failScore >= $this->maxFailScore) {
                unset($this->pool[$key]);
            }

            $this->write();
        }

        $this->close();
        return isset($this->pool[$key]) ? $this->pool[$key]->failScore : -1;
    }

    /**
     * Reset the failScore of the Server to 0.
     *
     * Alias for $this->addFailScore($server, 0);
     *
     * @access public
     * @param \ShadowHost\Cloak\Server $server
     * @return int -1 if record was removed, int current failScore
     */
    public function resetFailScore(Server $server) {
        return $this->addFailScore($server, false);
    }

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
    public function autoUpdate($countLimit=3, $timeLimit='-1 day') {
        if (count($this->pool) > $countLimit) {
            return false;
        }

        if ($this->getLastUpdate() > strtotime($timeLimit)) {
            return false;
        }

        $this->update();
        return true;
    }

    /**
     * Open config file for reading and lock it.
     *
     * @access protected
     * @param bool $forWriting true to open file as 'r+' and obtain exclusive lock otherwise as 'r' and shared lock.
     * @return mixed false if file c
     */
    protected function open($forWriting) {
        // echo __METHOD__." write=$forWriting\n";
        $this->f=@fopen($this->configFile, $forWriting ? 'r+' : 'r');

        if (!$this->f) {
            throw new Exception("Cannot open ShadowHost Cloak proxy config file in '$mode' mode: $this->configFile", 5028);
        }

        if (!flock($this->f, $forWriting ? LOCK_EX : LOCK_SH)) {
            throw new Exception("Cannot obtain lock on ShadowHost Cloak proxy config file: $this->configFile", 5022);
        }

        return $this->f;
    }

    /**
     * Close file opened using self::open() and unlock it.
     *
     * @access private
     * @param resource $f
     * @return void
     */
    protected function close() {
        // echo __METHOD__."\n";
        fflush($this->f);
        flock($this->f, LOCK_UN);
        fclose($this->f);
        unset($this->f);
    }

    /**
     * Load data from proxy configuration file.
     *
     * @throws \Exception on reading/deserialization problems.
     * @access private
     * @return bool true on success, false when config not found
     */
    protected function read() {
        // echo __METHOD__."\n";
        fseek($this->f, 0);

        $contents='';
        while (!feof($this->f)) {
            $contents.=fread($this->f, 8192);
        }
        $data=json_decode($contents, true);

        if (!is_array($data)) {
            throw new Exception("Cannot decode data from ShadowHost Cloak proxy config file: $this->configFile (file size $size bytes, ".strlen($contents)." characters read): $contents", 5023);
        }

        $this->pool=array();
        foreach(@$data['pool'] ?: array() as $info) {
            $server=new Server($info);
            $this->pool[(string) $server]=$server;
        }
        $this->meta=@$data['meta'] ?: array();
    }

    /**
     * Write current data back to config file.
     *
     * @access private
     * @return int number of written bytes
     */
    protected function write() {
        // echo __METHOD__."\n";
        fseek($this->f, 0);
        ftruncate($this->f, 0);
        $this->meta['modified']=time();

        if ($ret=fwrite($this->f, json_encode($this, @JSON_PRETTY_PRINT | @JSON_UNESCAPED_SLASHES | @JSON_UNESCAPED_UNICODE)) === false) {
            throw new Exception("Error writting to ShadowHost Cloak proxy config file: $this->configFile", 5026);
        }

        return $ret;
    }

    /**
     * Magic function for json_serialize()
     *
     * @access public
     * @return array of data to be serialized
     */
    public function jsonSerialize() {
        return array(
            'pool' => $this->pool ?: array(),
            'meta' => $this->meta ?: array()
        );
    }
}
