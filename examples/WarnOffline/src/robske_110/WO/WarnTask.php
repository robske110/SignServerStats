<?php
namespace robske_110\WO;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as TF;

class WarnTask extends PluginTask{
	private $plugin;
	private $watchServers = []; //[(string) hostname, (int) port, ?bool online]
	
	public function __construct(WarnOffline $plugin){
		parent::__construct($plugin);
		$this->plugin = $plugin;
	}
	
	public function addWatchServer(string $hostname, int $port): bool{
		if(!isset($this->watchServers[$hostname."@".$port])){
			$this->watchServers[$hostname."@".$port] = [$hostname, $port, null];
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
	
	public function getWatchServers(): array{
		return $this->watchServers;
	}
	
	public function onRun(int $currentTick){
		if(($sss = $this->plugin->getSSS()) === null){
			return true;
		}
		$serverOnlineArray = $sss->getServerOnline();
		foreach($this->watchServers as $index => $watchServer){
			if(isset($serverOnlineArray[$index])){
			    if($serverOnlineArray[$index]){
		    		$this->watchServers[$index][2] = true;
			    }else{
					if($this->watchServers[$index][2] !== false){
						$this->plugin->notifyMsg(TF::DARK_GRAY."Server ".$watchServer[0].TF::GRAY.":".TF::DARK_GRAY.$watchServer[1]." went ".TF::DARK_RED."OFFLINE");
					}
					$this->watchServers[$index][2] = false;
			    }
			}else{
				$this->watchServers[$index][2] = null;
			}
		}
	}
}