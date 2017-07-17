<?php
namespace robske_110\WO;

use robske_110\SPC\ScriptPluginCommands;
use robske_110\SSS\SignServerStats;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class WarnOffline extends PluginBase{
		
	const API_VERSION = "1.0.0";
		
	/** @var WarnTask */
	private $warnTask;
	
	public function onEnable(){
		if(($sss = $this->getSSS()) !== NULL){
			if(!$sss->isCompatible(self::API_VERSION)){
				$newOld = version_compare(self::API_VERSION, SignServerStats::API_VERSION, ">") ? "old" : "new";
				$this->getLogger()->critical("Your version of SignServerStats is too ".$newOld." for this plugin.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("This plugin needs SignServerStats. And I couldn't find it :/");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->warnTask = new WarnTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->warnTask, 20);
	}
		
	public function getSSS(){
		if(($sss = $this->getServer()->getPluginManager()->getPlugin("SignServerStats")) instanceof SignServerStats){
			return $sss;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return NULL;
		}
	}
		
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		switch($command->getName()){
			case "addWatchServer":
			case "remWatchServer":
				if(isset($args[0])){
					$hostname = $args[0];
					$port = 19132;
					if(isset($args[1])){
						if(is_numeric($args[1])){
							$port = $args[1];
						}else{
							return false;
						}
					}
				}else{
					return false;
				}
			break;
			case "listWatchServers":
				//@TODO
				return true;
			break;
		}
		switch($command->getName()){
			case "addWatchServer":
				if($this->warnTask->addWatchServer($hostname, $port)){
					$sender->sendMessage("Successfully added the server ".$hostname.":".$port." to the watchlist.");
				}else{
					$sender->sendMessage("The server ".$hostname.":".$port." is already on the watchlist!");
				}
				return true;
			break;
			case "remWatchServer":
				if($this->warnTask->addWatchServer($hostname, $port)){
					$sender->sendMessage("Successfully removed the server ".$hostname.":".$port." from the watchlist.");
				}else{
					$sender->sendMessage("The server ".$hostname.":".$port." is not on the watchlist!");
				}
				return true;
			break;
		}
		return false;
	}
}