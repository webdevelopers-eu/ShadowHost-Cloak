<?php

namespace ShadowHost\Cloak;

/**
 * Maintains list of open Chinese proxy servers
 * downloaded from https://www.xicidaili.com/wn
 *
 * @module     ShadowHost Cloak
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @copyright  2019 Daniel Sevcik
 * @since      2019-05-25 18:59:19 UTC
 * @access     public
 */
class PoolXicidaili extends PoolFileStorage {
    /**
     * Constructor.
     *
     * @access public
     * @param string $configFile path to .json config file. If it does not exist it will be created and fresh list of proxies will be downloaded.
     * @return void
     */
    public function __construct($configFile) {
        parent::__construct($configFile);
    }


    /**
     * Returns stamp when this pool was updated last time.
     *
     * @access public
     * @return int unix timestamp or 0 if never
     */
    public function getLastUpdate() {
        return @$this->meta['xicidaili']['newest'] ?: 0;
    }

    /**
     * Download new list of proxy servers unconditionally.
     *
     * Prefere using conditional $this->autoUpdate() instead.
     *
     * @access public
     * @return void
     */
    public function update() {
        $this->open(true);
        $this->read();
        $upToStamp=@$this->meta['xicidaili']['newest'] ?: strtotime("-1 month");
        $page=1;
        $maxPage=256;
        $doc=new \DOMDocument;

        while($page < $maxPage && @$doc->loadHTMLFile('https://www.xicidaili.com/wn/'.$page++, LIBXML_NOERROR | LIBXML_NONET | LIBXML_NOWARNING)) {
            // echo "Parsed page ".($page - 1)."\n";
            $xp=new \DOMXPath($doc);

            foreach($xp->query('//table[@id="ip_list"]//tr[position()!=1]') as $rowNode) {
                $server=new Server;
                $server->ip=$xp->evaluate('string(td[2])', $rowNode);
                $server->port=$xp->evaluate('string(td[3])', $rowNode);
                $server->type=$xp->evaluate('string(td[6])', $rowNode);

                $stamp=strtotime($xp->evaluate('string(td[10])', $rowNode));

                if ($stamp < $upToStamp) { // older then needed
                    break 2;
                }

                $this->pool[(string) $server]=$server;
                $this->meta['xicidaili']['newest']=max(array(@$this->meta['meta']['xicidaili']['newest'], $stamp));
            }
        }
        $this->write();
        $this->close();
    }
}
