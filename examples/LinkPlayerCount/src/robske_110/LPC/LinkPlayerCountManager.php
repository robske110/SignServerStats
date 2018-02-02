<?php
namespace robske_110\LPC;

use pocketmine\event\Listener;
use pocketmine\event\server\QueryRegenerateEvent;
use robske_110\SSS\event\SSSasyncUpdateEvent;

class LinkPlayerCountManager implements Listener{
	/** @var LinkPlayerCount */
	private $plugin;
	/** @var bool */
	private $combineMaxSlots;
	
	/** @var array */
	private $servers = []; //[string hostname, int port, bool hasWarned]
	/** @var int|null */
	private $playerCount = null;
	/** @var int|null */
	private $playerMax = null;
	/** @var int */
	private $dataRefreshTick = -1;
	
	public function __construct(LinkPlayerCount $plugin, bool $combineMaxSlots){
		$this->plugin = $plugin;
		$this->combineMaxSlots = $combineMaxSlots;
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
			$this->servers[$hostname."@".$port] = [$hostname, $port, false];
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
		if($this->playerCount !== null){
			$event->setPlayerCount($this->playerCount);
		}
		if($this->playerMax !== null){
			$event->setMaxPlayerCount($this->playerMax);
		}
	}
	
	public function onSSSasyncUpdate(SSSasyncUpdateEvent $event){
		$sss = $event->getSSS();
		if(($lastRefreshTick = $event->getCurrUpdate()) < $this->dataRefreshTick){
			return;
		}
		$serverOnlineArray = $sss->getServerOnline();
		$playerOnlineArray = $sss->getPlayerData();
		$currPlayerCount = count($this->plugin->getServer()->getOnlinePlayers());
		if($this->combineMaxSlots){
			$currPlayerMax = $this->plugin->getServer()->getMaxPlayers();
		}
		foreach($this->servers as $index => $server){
			if(isset($serverOnlineArray[$index])){
				if($serverOnlineArray[$index]){
					if(isset($playerOnlineArray[$index])){
						$currPlayerCount += $playerOnlineArray[$index][0];
						if($this->combineMaxSlots){
							$currPlayerMax += $playerOnlineArray[$index][1];
						}
					}
					if(!$this->servers[$index][2]){
						if(!isset($sss->getFullData()[$index]["plugins"])){
							continue;
						}
						if(preg_match("/LinkPlayerCount/", $sss->getFullData()[$index]["plugins"])){
							$this->plugin->getLogger()->critical("THE SERVER ".$index." ALSO HAS THIS PLUGIN INSTALLED. PLEASE MAKE SURE THAT THERE IS NO CIRCULAR REFERENCE!");
							$this->plugin->getLogger()->notice("Having two servers combine the playercounts of each other will result in a infinitely increasing playercount! For more information please check the README.");
							$this->servers[$index][2] = true;
						}
					}
				}
			}
		}
		$this->playerCount = $currPlayerCount;
		if($this->combineMaxSlots){
			$this->playerMax = $currPlayerMax;
		}
		$this->dataRefreshTick = $lastRefreshTick;
	}
}