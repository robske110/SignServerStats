<?php
namespace robske_110\WO;

use robske_110\SSS\SignServerStats;
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
		$this->db = new Config($this->getDataFolder()."WarnOfflineDB.yml", Config::YAML, []); //TODO:betterDB
		foreach($this->db->getAll() as $warnOfflineServer){
			$this->addWatchServer($warnOfflineServer[0], $warnOfflineServer[1], $sss, false);
		}
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
		foreach($this->getServer()->getOnlinePlayers() as $player){
			if($player->hasPermission("WarnOffline.notify")){
				$player->sendMessage($msg);
			}
		}
	}
		
	public function addWatchServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->warnTask->addWatchServer($hostname, $port)){
			$sss->addServer($hostname, $port);
			if($save){
				$watchServers = $this->db->getAll();
				$watchServers[$hostname."@".$port] = [$hostname, $port];
				$this->db->setAll($watchServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	public function remWatchServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->warnTask->remWatchServer($hostname, $port)){
			$sss->removeServer($hostname, $port);
			if($save){
				$watchServers = $this->db->getAll();
				unset($watchServers[$hostname."@".$port]);
				$this->db->setAll($watchServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
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
				if(($sss = $this->getSSS()) === null){
					$sender->sendMessage("Error. Check console.");
					return true;
				}
			break;
			case "listWatchServers": //I personally hate the "pages" approach, MCPE and almost all terminals/ssh/rcon clients have scrollbars.
					$sender->sendMessage(TF::GREEN."Full list of WatchServers:");
					$watchServers = $this->warnTask->getWatchServers();
					$onlineCnt = 0;
					$offlineCnt = 0;
					foreach($watchServers as $watchServer){
						if($watchServer[2] === true){
							$onlineCnt++;
						}elseif($watchServer[2] === false){
							$offlineCnt++;
						}
						$sender->sendMessage(
							TF::DARK_GRAY.$watchServer[0].TF::GRAY.":".TF::DARK_GRAY.$watchServer[1].TF::GRAY." | ".
							($watchServer[2] === true ? TF::GREEN."ONLINE" : ($watchServer[2] === false ? TF::DARK_RED."OFFLINE" : TF::GRAY."LOADING"))
						);
					}
					$sender->sendMessage(TF::GREEN."Total: ".count($watchServers)." Online: ".$onlineCnt." Offline: ".$offlineCnt);
				return true;
			break;
		}
		switch($command->getName()){
			case "addWatchServer":
				if($this->addWatchServer($hostname, $port, $sss)){
					$sender->sendMessage(TF::GREEN."Successfully added the server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN." to the watchlist.");
				}else{
					$sender->sendMessage(TF::DARK_RED."The server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED." is already on the watchlist!");
				}
				return true;
			break;
			case "remWatchServer":
				if($this->remWatchServer($hostname, $port, $sss)){
					$sender->sendMessage(TF::GREEN."Successfully removed the server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN." from the watchlist.");
				}else{
					$sender->sendMessage(TF::DARK_RED."The server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED." is not on the watchlist!");
				}
				return true;
			break;
		}
		return false;
	}
}