<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\SSS;

use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;

/* _____ _____ _____ 
  / ____/ ____/ ____|
 | (___| (___| (___  
  \___ \\___ \\___ \ 
  ____) |___) |___) |
 |_____/_____/_____/ 
*/
class SignServerStats extends PluginBase{
	private $listener;
	private $signServerStatsCfg;
	private $db;
	private $server;
	private $doCheckServers = [];
	private $debug = false;
	private $asyncTaskIsRunning = false;
	private $doRefreshSigns = [];
	private $asyncTaskMODTs = [];
	private $asyncTaskPlayers = [];
	private $asyncTaskIsOnline = [];
	
	const API_VERSION = "1.0.0";
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->server = $this->getServer();
		$this->db = new Config($this->getDataFolder() . "SignServerStatsDB.yml", Config::YAML, array()); //TODO:betterDB
		$this->signServerStatsCfg = new Config($this->getDataFolder() . "SSSconfig.yml", Config::YAML, array());
		if($this->signServerStatsCfg->get("ConfigVersion") != 2){
			$this->signServerStatsCfg->set('SSSAsyncTaskCall', 200);
			$this->signServerStatsCfg->set('always-start-async-task', false);
			$this->signServerStatsCfg->set('debug', false);
			$this->signServerStatsCfg->set('ConfigVersion', 2);
		}
		$this->signServerStatsCfg->save();
		if($this->signServerStatsCfg->get('debug')){
			$this->debug = true;
		}
		$this->listener = new SSSListener($this);
		$this->server->getPluginManager()->registerEvents($this->listener, $this);
		$this->recalcdRSvar();
		$this->server->getScheduler()->scheduleRepeatingTask(new SSSAsyncTaskCaller($this), $this->signServerStatsCfg->get("SSSAsyncTaskCall"));
	}
	
	public function startAsyncTask($currTick){
		$this->asyncTaskIsRunning = true;
		$this->server->getScheduler()->scheduleAsyncTask(new SSSAsyncTask($this->doCheckServers, $this->debug, $currTick));
	}
	
	public function asyncTaskCallBack($data, $scheduleTime){
		$this->asyncTaskIsRunning = false;
		if(!is_array($data)){
			return;
		}
		if($this->debug){
			echo("AsyncTaskResponse:\n");
			var_dump($data);
		}
		foreach($data as $serverID => $serverData){
			$this->asyncTaskIsOnline[$serverID] = $serverData[2];
			if($serverData[2]){
				$this->asyncTaskMODTs[$serverID] = $serverData[1];
				$this->asyncTaskPlayers[$serverID] = $serverData[0];
			}
		}
		$this->doSignRefresh();
		$currTick = $this->server->getTick();
		if($currTick - $scheduleTime >= $this->signServerStatsCfg->get('SSSAsyncTaskCall')){
			$this->startAsyncTask($currTick);
		}
	}
	
	public function isAllowedToStartAsyncTask(): bool{
		return $this->signServerStatsCfg->get('always-start-async-task') ? true : !$this->asyncTaskIsRunning;
	}
	
	public function isCompatible(string $apiVersion): bool{
		$extensionApiVersion = explode(".", $apiVersion);
		$myApiVersion = explode(".", self::API_VERSION);
		if($extensionApiVersion[0] !== $myApiVersion[0]){
			return false;
		}
		if($extensionApiVersion[1] > $myApiVersion[1]){
			return false;
		}
		return true;
	}
	
	public function getServerOnline(): array{
		return $this->asyncTaskIsOnline;
	}
	
	public function getMODTs(): array{
		return $this->asyncTaskMODTs;
	}
	
	public function getPlayerData(): array{
		return $this->asyncTaskPlayers;
	}
	
	public function debugEnabled(): bool{
		return $this->debug;
	}
	
	public function isAdmin(Player $player): bool{
		return $player->hasPermission("SSS.signs");
	}
	
	public function doSignRefresh(){
		foreach($this->doRefreshSigns as $signData){
			$pos = $signData[0];
			$adress = $signData[1];
			if($this->server->loadLevel($pos[3])){
				$signTile = $this->server->getLevelByName($pos[3])->getTile(new Vector3($pos[0], $pos[1], $pos[2], $pos[3]));
				if($signTile instanceof Sign){
					$lines = $this->calcSign($adress);
					$signTile->setText($lines[0],$lines[1],$lines[2],$lines[3]);
				}else{
					$this->server->broadcast("r001_SIGN_NOT_FOUND_AT(".$pos[0]."/".$pos[1]."/".$pos[2]." in ".$pos[3].")", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
			}else{
				$this->server->broadcast("r002_COULD_NOT_FIND_LEVEL_FOR_SIGN_AT(".$pos[0]."/".$pos[1]."/".$pos[2]." in ".$pos[3].")", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
			}
		}
	}
	
	public function doesSignExist(Vector3 $pos, string $levelName, int &$index = 0): bool{
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		foreach($this->doRefreshSigns as $key => $signData){
			$pos = $signData[0];
			if($deParsedPos == [$pos[0], $pos[1], $pos[2], $pos[3]]){
				$index = $key;
				return true;
			}
		}
		return false;
	}
	
	public function addSign(string $ip, int $port, Vector3 $pos, string $levelName){
		$index = 0;
		if($this->doesSignExist($pos, $levelName, $index)){
			$currentSignOffset = $index;
		}else{
			$currentSignOffset = count($this->db->getAll());
		}
		$this->db->set($currentSignOffset, [[$pos->x, $pos->y, $pos->z, $levelName], [$ip, $port]]);
		$this->db->save(true);
		$this->doRefreshSigns = $this->db->getAll();
	}
	
	public function removeSign(Vector3 $pos, $levelName): bool{
		$foundSign = false;
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		foreach($this->doRefreshSigns as $key => $signData){
			$pos = $signData[0];
			if($deParsedPos == [$pos[0], $pos[1], $pos[2], $pos[3]]){
				$foundSign = true;
				$this->db->remove($key);
				$signArray = $this->db->getAll();
				$signArray = array_values($signArray);
				$this->doRefreshSigns = $signArray;
				$this->db->setAll($signArray);
				$this->db->save(true);
				$this->removeServer($signData[1][0], $signData[1][1]);
			}
		}
		return $foundSign;
	}
	
	public function addServer(string $ip, int $port, bool $active = true){
		$this->doCheckServers[$ip.$port] = [[$ip, $port], $active];
	}
	
	public function removeServer(string $ip, int $port): bool{
		if(isset($this->doCheckServers[$ip.$port])){
			unset($this->doCheckServers[$ip.$port]);
			return true;
		}
		return false;
	}
	
	public function recalcdRSvar(){
		foreach($this->doRefreshSigns as $signData){
			$refreshSignIP = $signData[1];
			$this->addServer($refreshSignIP[0], $refreshSignIP[1]);
		}
	}
	
	/**
	  * @internal
	  */
	public function calcSign(array $adress): array{
		$ip = $adress[0];
		$port = $adress[1];
		if(isset($this->asyncTaskIsOnline[$ip.$port])){
			$isOnline = $this->asyncTaskIsOnline[$ip.$port];
			if($isOnline){
				$MODT = $this->asyncTaskMODTs[$ip.$port];
				$playerData = $this->asyncTaskPlayers[$ip.$port];
				$currentPlayers = $playerData[0];
				$maxPlayers = $playerData[1];
				$lines[0] = $MODT;
				$lines[1] = "IP: ".TF::GREEN.$ip;
				$lines[2] = "Port: ".TF::DARK_GREEN.$port;
				$lines[3] = TF::DARK_GREEN.$currentPlayers.TF::WHITE."/".TF::GOLD.$maxPlayers;
			}else{
				$lines[0] = TF::DARK_RED."Offline";
				$lines[1] = "IP: ".TF::GREEN.$ip;
				$lines[2] = "Port: ".TF::DARK_GREEN.$port;
				$lines[3] = "-"." / "."-";
			}
		}else{ //If this happens a new Sign has been added and the AsyncTask hasn't returned the data for it yet!
			$lines[0] = TF::GOLD."Loading...";
			$lines[1] = "IP: ".TF::GREEN.$ip;
			$lines[2] = "Port: ".TF::DARK_GREEN.$port;
			$lines[3] = "-"." / "."-";
		}
		return $lines;
	}
	
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!