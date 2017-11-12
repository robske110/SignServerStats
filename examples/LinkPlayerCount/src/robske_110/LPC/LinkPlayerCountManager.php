<?php
namespace robske_110\LPC;

use pocketmine\event\Listener;
use pocketmine\event\server\QueryRegenerateEvent;
use robske_110\SSS\event\SSSasyncUpdateEvent;

class LinkPlayerCountManager implements Listener{
	/** @var LinkPlayerCount */
	private $plugin;
	/** @var array */
	private $servers = []; //[string hostname, int port]
	/** @var int */
	private $playerCount;
	/** @var int */
	private $dataRefreshTick = -1;
	
	public function __construct(LinkPlayerCount $plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * Adds a server, but does not check if it is already registered to SSS neither save it to disk.
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function addServer(string $hostname, int $port): bool{
		if(!isset($this->servers[$hostname."@".$port])){
			$this->servers[$hostname."@".$port] = [$hostname, $port, null];
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Adds a server, but does not check if it has been registered to SSS neither remove it to disk.
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function remServer(string $hostname, int $port): bool{
		if(isset($this->servers[$hostname."@".$port])){
			unset($this->servers[$hostname."@".$port]);
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Returns the Tick in which the data for the servers were started to be generated.
	 *
	 * @return int
	 */
	public function getLastRefreshTick(): int{
		return $this->dataRefreshTick;
	}
	
	public function onQueryRegenerate(QueryRegenerateEvent $event){
		$event->setPlayerCount($this->playerCount);
	}
	
	public function onSSSasyncUpdate(SSSasyncUpdateEvent $event){
		$sss = $event->getSSS();
		if(($lastRefreshTick = $event->getCurrUpdate()) < $this->dataRefreshTick){
			return;
		}
		$serverOnlineArray = $sss->getServerOnline();
		$playerOnlineArray = $sss->getPlayerData();
		$currPlayerCount = count($this->plugin->getServer()->getOnlinePlayers());
		foreach($this->servers as $index => $server){
			if(isset($serverOnlineArray[$index])){
				if($serverOnlineArray[$index]){
					if(isset($playerOnlineArray[$index])){
						$currPlayerCount += $playerOnlineArray[$index][0];
					}
				}
			}
		}
		$this->playerCount = $currPlayerCount;
		$this->dataRefreshTick = $lastRefreshTick;
	}
}