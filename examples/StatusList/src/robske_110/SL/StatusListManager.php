<?php
namespace robske_110\SL;

class StatusListManager{
	/** @var StatusList */
	private $plugin;
	/** @var array */
	private $listServers = []; //[string hostname, int port, ?bool online, ?array playerCount]
	/** @var int */
	private $dataRefreshTick = -1;
	
	public function __construct(StatusList $plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * Adds a server to the StatusList, but does not check if it is already registered to SSS neither save it to disk.
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function addStatusServer(string $hostname, int $port): bool{
		if(!isset($this->listServers[$hostname."@".$port])){
			$this->listServers[$hostname."@".$port] = [$hostname, $port, null];
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Adds a server to the StatusList, but does not check if it has been registered to SSS neither remove it to disk.
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function remStatusServer(string $hostname, int $port): bool{
		if(isset($this->listServers[$hostname."@".$port])){
			unset($this->listServers[$hostname."@".$port]);
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Gets the Servers on the StatusList and performs an update, if available, on them beforehand
	 *
	 * @return array
	 */
	public function getStatusServers(): array{
		if(!$this->update()){
			$this->plugin->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
		}
		return $this->listServers;
	}
	
	/**
	 * Returns the Tick in which the data for the servers were started to be generated.
	 *
	 * @return int
	 */
	public function getStatusServerRefreshTick(): int{
		return $this->dataRefreshTick;
	}
	
	public function update(): bool{
		if(($sss = $this->plugin->getSSS()) === null){
			return false;
		}
		if(($lastRefreshTick = $sss->getLastRefreshTick()) < $this->dataRefreshTick){
			return true;
		}
		$serverOnlineArray = $sss->getServerOnline();
		$playerOnlineArray = $sss->getPlayerData();
		foreach($this->listServers as $index => $listServer){
			if(isset($serverOnlineArray[$index])){
		    	$this->listServers[$index][2] = $serverOnlineArray[$index];
				if(isset($playerOnlineArray[$index])){
					$this->listServers[$index][3] = $playerOnlineArray[$index];
				}else{
					$this->listServers[$index][3] = null;
				}
			}else{
				$this->listServers[$index][2] = null;
				$this->listServers[$index][3] = null;
			}
		}
		$this->dataRefreshTick = $lastRefreshTick;
		return true;
	}
}