<?php
namespace robske_110\WO;

use pocketmine\scheduler\PluginTask;

class DisplayTask extends PluginTask{
	private $plugin;
	private $watchServers = [];
	
	public function __construct(WarnOffline $plugin){
		parent::__construct($plugin);
		$this->plugin = $plugin;
	}
	
	public function addWatchServer(string $hostname, int $port): bool{
		if(!isset($this->watchServers[$hostname."@".$port])){
			$this->watchServers[$hostname."@".$port] = [$hostname, $port];
			return true;
		}else{
			return false;
		}
	}
	
	public function remWatchServer(string $hostname, int $port): bool{
		if(isset($this->watchServers[$hostname."@".$port])){
			unset($this->watchServers[$hostname."@".$port]);
			return true;
		}else{
			return false;
		}
	}
	
	public function onRun(int $currentTick){
	}
}