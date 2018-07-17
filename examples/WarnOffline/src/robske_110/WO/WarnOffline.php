<?php
namespace robske_110\WO;

use robske_110\SL\StatusList;
use robske_110\SSS\SignServerStats;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class WarnOffline extends PluginBase{
	
	const SL_API_VERSION = "1.0.0";
	const SSS_API_VERSION = "1.1.0";
		
	/** @var WarnNotifier */
	private $warnNotifier;
	
	public function onEnable(){
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
		if(!$this->getServer()->getPluginManager()->getPlugin("SignServerStats")->isCompatible(self::SSS_API_VERSION)){
			$newOld = version_compare(self::SSS_API_VERSION, SignServerStats::API_VERSION, ">") ? "old" : "new";
			$this->getLogger()->critical("Your version of SignServerStats is too ".$newOld." for this plugin.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->warnNotifier = new WarnNotifier($this);
		$this->getServer()->getPluginManager()->registerEvents($this->warnNotifier, $this);
	}
	
    /**
     * @return null|StatusList
     */
	public function getSL(): ?StatusList{
		if(($sl = $this->getServer()->getPluginManager()->getPlugin("StatusList")) instanceof StatusList){
			return $sl;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return null;
		}
	}
	
    /**
     * @param string $msg
     * @param bool   $warn Whether to put out a warning or a notice to the console
     */
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