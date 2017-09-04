<?php
namespace robske_110\WO;

use robske_110\SL\StatusList;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class WarnOffline extends PluginBase{
		
	const SL_API_VERSION = "1.0.0";
		
	/** @var WarnTask */
	private $warnTask;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(($sl = $this->getSL()) !== null){
			if(!$sl->isCompatible(self::SL_API_VERSION)){
				$newOld = version_compare(self::SL_API_VERSION, StatusList::API_VERSION, ">") ? "old" : "new";
				$this->getLogger()->critical("Your version of StatusList is too ".$newOld." for this plugin.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("StatusList is required for this plugin. PM ignored my dependencies!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->warnTask = new WarnTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->warnTask, 20);
	}
	
	public function getSL(): ?StatusList{
		if(($sl = $this->getServer()->getPluginManager()->getPlugin("StatusList")) instanceof StatusList){
			return $sl;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return null;
		}
	}
	
	public function notifyMsg(string $msg, bool $warn = true){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			if($player->hasPermission("WarnOffline.notify")){
				$player->sendMessage(TF::WHITE."[WarnOffline] ".$msg);
			}
		}
		if($warn){
			$this->getLogger()->warning($msg);
		}else{
			$this->getLogger()->notice($msg);
		}
	}
}