<?php
namespace robske_110\SL;

use robske_110\SSS\SignServerStats;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class StatusList extends PluginBase{
		
	const SSS_API_VERSION = "1.0.0";
		
	/** @var StatusGetTask */
	private $statusGetTask;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(($sss = $this->getSSS()) !== NULL){
			if(!$sss->isCompatible(self::SSS_API_VERSION)){
				$newOld = version_compare(self::SSS_API_VERSION, SignServerStats::SSS_API_VERSION, ">") ? "old" : "new";
				$this->getLogger()->critical("Your version of SignServerStats is too ".$newOld." for this plugin.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("This plugin needs SignServerStats. And I couldn't find it :/ (Also, why did PM not prevent me from loading?)");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->statusGetTask = new StatusGetTask($this);
		$this->db = new Config($this->getDataFolder()."StatusListDB.yml", Config::YAML, []); //TODO:betterDB
		foreach($this->db->getAll() as $statusListServer){
			$this->addStatusServer($statusListServer[0], $statusListServer[1], $sss, false);
		}
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->statusGetTask, 20);
	}
		
	public function getSSS(){ //:?SignServerStats
		if(($sss = $this->getServer()->getPluginManager()->getPlugin("SignServerStats")) instanceof SignServerStats){
			return $sss;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return NULL;
		}
	}
		
	public function getStatusGetTask(): StatusGetTask{
		return $this->statusGetTask;
	}
	
	public function addStatusServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->statusGetTask->addStatusServer($hostname, $port)){
			$sss->addServer($hostname, $port);
			if($save){
				$listServers = $this->db->getAll();
				$listServers[$hostname."@".$port] = [$hostname, $port];
				$this->db->setAll($listServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	public function remStatusServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->statusGetTask->remStatusServer($hostname, $port)){
			$sss->removeServer($hostname, $port);
			if($save){
				$listServers = $this->db->getAll();
				unset($listServers[$hostname."@".$port]);
				$this->db->setAll($listServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		switch($command->getName()){
			case "statuslist add":
			case "statuslist rem":
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
			case "statuslist show": //I personally hate the "pages" approach, MCPE and almost all terminals/ssh/rcon clients have scrollbars.
					$sender->sendMessage(TF::GREEN."All StatusList servers:");
					$listServers = $this->statusGetTask->getStatusServers();
					$onlineCnt = 0;
					$offlineCnt = 0;
					foreach($listServers as $listServer){
						if($listServer[2] === true){
							$onlineCnt++;
						}elseif($listServer[2] === false){
							$offlineCnt++;
						}
						$sender->sendMessage(
							TF::DARK_GRAY.$listServer[0].TF::GRAY.":".TF::DARK_GRAY.$listServer[1].TF::GRAY." | ".
							($listServer[2] === true ? TF::GREEN."ONLINE" : ($listServer[2] === false ? TF::DARK_RED."OFFLINE" : TF::GRAY."LOADING"))
						);
					}
					
					if(($statusServerRefreshTick = $this->statusGetTask->getStatusServerRefreshTick()) === -1){
						$refreshText = TF::GRAY."NEVER";
					}else{
						$refreshText = round(($this->getServer()->getTick() - $statusServerRefreshTick) / 20, 1)."s ago";
					}
					$sender->sendMessage(TF::GREEN."Total: ".count($listServers)." Online: ".$onlineCnt." Offline: ".$offlineCnt." Last Refresh: ".$refreshText);
				return true;
			break;
		}
		switch($command->getName()){
			case "statuslist add":
				if($this->addStatusServer($hostname, $port, $sss)){
					$sender->sendMessage(TF::GREEN."Successfully added the server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN." to the statuslist.");
				}else{
					$sender->sendMessage(TF::DARK_RED."The server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED." is already on the statuslist!");
				}
				return true;
			break;
			case "statuslist rem":
				if($this->remStatusServer($hostname, $port, $sss)){
					$sender->sendMessage(TF::GREEN."Successfully removed the server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN." from the statuslist.");
				}else{
					$sender->sendMessage(TF::DARK_RED."The server ".TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED." is not on the statuslist!");
				}
				return true;
			break;
		}
		return false;
	}
}