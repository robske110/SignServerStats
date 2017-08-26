<?php
namespace robske_110\SL;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as TF;

class StatusGetTask extends PluginTask{
	/** @var StatusList */
	private $plugin;
	/** @var array */
	private $listServers = []; //[(string) hostname, (int) port, ?bool online]
	/** @var int */
	private $dataRefreshTick = -1;
	
	public function __construct(StatusList $plugin){
		parent::__construct($plugin);
		$this->plugin = $plugin;
	}
	
	public function addStatusServer(string $hostname, int $port): bool{
		if(!isset($this->listServers[$hostname."@".$port])){
			$this->listServers[$hostname."@".$port] = [$hostname, $port, null];
			return true;
		}else{
			return false;
		}
	}
	
	public function remStatusServer(string $hostname, int $port): bool{
		if(isset($this->listServers[$hostname."@".$port])){
			unset($this->listServers[$hostname."@".$port]);
			return true;
		}else{
			return false;
		}
	}
	
	public function getStatusServers(): array{
		return $this->listServers;
	}
	
	public function getStatusServerRefreshTick(): int{
		return $this->dataRefreshTick;
	}
	
	public function onRun(int $currentTick){
		if(($sss = $this->plugin->getSSS()) === null){
			return true;
		}
		$serverOnlineArray = $sss->getServerOnline();
		$this->dataRefreshTick = $sss->getLastRefreshTick();
		foreach($this->listServers as $index => $listServer){
			if(isset($serverOnlineArray[$index])){
		    	$this->listServers[$index][2] = $serverOnlineArray[$index];
			}else{
				$this->listServers[$index][2] = null;
			}
		}
	}
}