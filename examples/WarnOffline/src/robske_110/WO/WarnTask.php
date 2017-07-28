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
	
	public function getStatusListServers(): array{
		return $this->plugin->getSL()->getStatusGetTask()->getWatchServers();
	}
	
	public function onRun(int $currentTick){
		if(($sss = $this->plugin->getSSS()) === null){
			return true;
		}
		$statusListServers = $this->getStatusListServers();
		foreach($this->watchServers as $index => $watchServer){
			if(isset($statusListServers[$index])){
			    if($statusListServers[$index]){
		    		$this->watchServers[$index][2] = true;
			    }else{
					if($this->watchServers[$index][2] !== false){
						$this->plugin->notifyMsg(TF::DARK_GRAY."Server ".$watchServer[0].TF::GRAY.":".TF::DARK_GRAY.$watchServer[1]." went ".TF::DARK_RED."OFFLINE");
					}
					$this->watchServers[$index][2] = false;
			    }
			}else{
				unset($this->watchServers[$index]);
			}
		}
	}
}