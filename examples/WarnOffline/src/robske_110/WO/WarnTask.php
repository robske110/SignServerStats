<?php
namespace robske_110\WO;

use pocketmine\event\Listener;
use robske_110\SSS\event\SSSasyncUpdateEvent;
use pocketmine\utils\TextFormat as TF;

class WarnNotifier implements Listener{
	private $plugin;
	private $watchServers = []; //[string $index => ?bool $online]
	
	public function __construct(WarnOffline $plugin){
		$this->plugin = $plugin;
	}
	
	public function onSSSasyncUpdate(SSSasyncUpdateEvent $event){
		if(($sl = $this->plugin->getSL()) === null){
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
			return;
		}
		
		$statusListServers = $sl->getStatusListManager()->getStatusServers();
		
		foreach($statusListServers as $index => $statusListServer){
			if(!isset($this->watchServers[$index])){
				$this->watchServers[$index] = null;
			}
		}
		foreach($this->watchServers as $index => $watchServer){
			if(!isset($statusListServers[$index])){
				unset($this->watchServers[$index]);
			}
		}
		
		foreach($statusListServers as $index => $statusListServer){
			if($statusListServer[2]){
				if($this->watchServers[$index] === false){
					$this->plugin->notifyMsg(
						TF::AQUA."Server ".$statusListServer[0].TF::GRAY.":".TF::AQUA.$statusListServer[1].
						" went back ".TF::GREEN."ONLINE", false
					);
				}
		   		$this->watchServers[$index] = true;
			}elseif($statusListServer[2] !== null){
				if($this->watchServers[$index] !== false){
					$this->plugin->notifyMsg(
						TF::YELLOW."Server ".$statusListServer[0].TF::GRAY.":".TF::YELLOW.$statusListServer[1].
						" went ".TF::DARK_RED."OFFLINE"
					);
				}
				$this->watchServers[$index] = false;
		    }
		}
	}
}