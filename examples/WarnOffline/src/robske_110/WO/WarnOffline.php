<?php
namespace robske_110\WO;

use robske_110\SL\StatusList;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class WarnOffline extends PluginBase{
		
	const API_VERSION = "1.0.0";
		
	/** @var WarnTask */
	private $warnTask;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(($sss = $this->getSL()) === NULL){
			$this->getLogger()->critical("This plugin needs SignServerStats. And I couldn't find it :/ (Also, why did PM not prevent me from loading?)");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->warnTask = new WarnTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->warnTask, 20);
	}
		
	public function getSL(){ //:?StatusList
		if(($sss = $this->getServer()->getPluginManager()->getPlugin("StatusList")) instanceof StatusList){
			return $sss;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return NULL;
		}
	}
	
	public function notifyMsg(string $msg){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			if($player->hasPermission("WarnOffline.notify")){
				$player->sendMessage($msg);
			}
		}
		$this->getLogger()->warning($msg);
	}
}