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
			$this->getLogger()->critical("This plugin needs SignServerStats. And I couldn't find it :/ (Also, why did PM not prevent me from loading?)");
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
	
	public function notifyMsg(string $msg){
		foreach($this->getServer->getPlayers() as $player){
			if($player->hasPermission("WarnOffline.notify")){
				$player->sendMessage($msg);
			}
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
			case "listWatchServers": //I personally hate the "pages" approach, MCPE and almost all terminals/ssh/rcon clients have scrollbars.
					$sender->sendMessage("Full list of WatchServers:");
					$watchServers = $this->warnTask->getWatchServers();
					$onlineCnt = 0;
					$offlineCnt = 0;
					foreach($watchServers as $watchServer){
						if($watchServer[2] === true){
							$onlineCnt++;
						}elseif($watchServer[2] === false){
							$offlineCnt++;
						}
						$sender->sendMessage(TF::DARK_GRAY.$watchServer[0].TF::GRAY.":".TF::DARK_GRAY.$watchServer[1]." | ".($watchServer[2] ? TF::GREEN."ONLINE" : TF::DARK_RED."OFFLINE"));
					}
					$sender->sendMessage("Total: ".count($watchServers)."Online: ".$onlineCnt."Offline: ".$offlineCnt);
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