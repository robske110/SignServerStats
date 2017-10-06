<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\SSS;

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
    /** @var SSSListener */
	private $listener;

	/** @var Config */
	private $signServerStatsCfg;
	/** @var Config */
	private $db;

	/** @var Server */
	private $server;

	/** @var float */
	private $timeout;
	/** @var array */
	private $doCheckServers = [];
	/** @var bool */
	private $debug = false;
	/** @var bool */
	private $asyncTaskIsRunning = false;
	/** @var int */
	private $lastRefreshTick = -1;
	/** @var array */
	private $doRefreshSigns = [];
	/** @var array */
	private $asyncTaskMODTs = [];
	/** @var array */
	private $asyncTaskPlayers = [];
	/** @var array */
	private $asyncTaskIsOnline = [];
	
	const API_VERSION = "1.1.0";
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->server = $this->getServer();
		$this->db = new Config($this->getDataFolder() . "SignServerStatsDB.yml", Config::YAML, []); //TODO:betterDB
		$this->signServerStatsCfg = new Config($this->getDataFolder() . "SSSconfig.yml", Config::YAML, []);
		if($this->signServerStatsCfg->get("ConfigVersion") != 3){
			$this->signServerStatsCfg->set('async-task-call-ticks', 200);
			$this->signServerStatsCfg->set('always-start-async-task', false);
			$this->signServerStatsCfg->set('server-query-timeout-sec', 2.5);
			$this->signServerStatsCfg->set('debug', false);
			$this->signServerStatsCfg->set('ConfigVersion', 3);
		}
		$this->signServerStatsCfg->save();
		if($this->signServerStatsCfg->get('debug')){
			$this->debug = true;
		}
		$this->listener = new SSSListener($this);
		$this->server->getPluginManager()->registerEvents($this->listener, $this);
		$this->doRefreshSigns = $this->db->getAll();
		$this->recalcdRSvar();
		$this->timeout = $this->signServerStatsCfg->get('server-query-timeout-sec');
		$this->server->getScheduler()->scheduleRepeatingTask(
			new SSSAsyncTaskCaller($this), $this->signServerStatsCfg->get("async-task-call-ticks")
		);
	}
	
	/**
	 * This is for extension plugins to test if they are compatible with the version
	 * of PP installed. Extensions should be disabled/disable any interfaces with this plugin if this returns false.
	 *
	 * @param string $apiVersion The API version your plugin was last tested on.
	 *
	 * @return bool Indicates whether your plugin is compatible.
	 */
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
	
	/**
	 * Returns the Tick in which the oldest data was refreshed.
	 *
	 * @return int
	 */
	public function getLastRefreshTick(): int{
		return $this->lastRefreshTick;
	}
	
	/**
	 * @return array [string $serverID => bool $isOnline]
	 */
	public function getServerOnline(): array{
		return $this->asyncTaskIsOnline;
	}
	
	/**
	 * @return array [string $serverID => string $modt]
	 */
	public function getMODTs(): array{
		return $this->asyncTaskMODTs;
	}
	
	/**
	 * @return array [string $serverID => [int $numplayers, int $maxplayers]]
	 */
	public function getPlayerData(): array{
		return $this->asyncTaskPlayers;
	}
	
	/**
	 * @return array [int $signID => [[int $x, int $y, int $z, string $levelName], [string $ip, int $port]]]
	 */
	public function getSignList(): array{
		return $this->doRefreshSigns;
	}
	
	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isAdmin(Player $player): bool{
		return $player->hasPermission("SSS.signs");
	}
	
	/**
	 * @param Vector3  $pos
	 * @param string   $levelName
	 * @param int|null $index Supply a variable with content null to get the index of the sign in doRefreshSigns
	 *
	 * @return bool doesExist
	 */
	public function doesSignExist(Vector3 $pos, string $levelName, ?int &$index = null): bool{
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		foreach($this->doRefreshSigns as $key => $signData){
			if($deParsedPos == $signData[0]){
				$index = $key;
				return true;
			}
		}
		return false;
	}
	
	/**
	 * @param string  $ip
	 * @param int     $port
	 * @param Vector3 $pos
	 * @param string $levelName
	 */
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
	
	/**
	 * @param Vector3 $pos
	 * @param string  $levelName
	 *
	 * @return bool Success
	 */
	public function removeSign(Vector3 $pos, string $levelName): bool{
		return $this->internalRemoveSign($pos, $levelName);
	}
	
	/**
	 * @param string $ip
	 * @param int $port
	 *
	 * @return bool Success (if false is returned server is already added)
	 */
	public function addServer(string $ip, int $port): bool{
		if(isset($this->doCheckServers[$ip."@".$port])){
			return false;
		}
		$this->doCheckServers[$ip."@".$port] = [$ip, $port];
		return true;
	}
	
	/**
	 * @param string $ip
	 * @param int $port
	 *
	 * @return bool Success
	 */
	public function removeServer(string $ip, int $port): bool{
		if(isset($this->doCheckServers[$ip."@".$port])){
			unset($this->doCheckServers[$ip."@".$port]);
			$this->recalcdRSvar(); //Do not allow removing servers if still required for a sign.
			return true;
		}
		return false;
	}
	
	/**
	 * @internal
	 *
	 * WARNING: Do not use this function. Use @link{this->removeSign}!
	 *
	 * @param Vector3  $pos
	 * @param string   $levelName
	 * @param int|null $index
	 *
	 * @return bool $foundSign
	 */
	public function internalRemoveSign(Vector3 $pos, string $levelName, ?int $index = null): bool{
		if($index === null){
			$foundSign = $this->doesSignExist($pos, $levelName, $index);
		}else{
			$foundSign = true;
		}
		if($foundSign){
			$signData = $this->doRefreshSigns[$index];
			$signArray = $this->db->getAll();
			unset($signArray[$index]);
			$signArray = array_values($signArray);
			$this->doRefreshSigns = $signArray;
			$this->db->setAll($signArray);
			$this->db->save(true);
			$this->removeServer($signData[1][0], $signData[1][1]);
		}
		return $foundSign;
	}
	
	/**
	 * @internal
	 *
	 * @return bool
	 */
	public function debugEnabled(): bool{
		return $this->debug;
	}
	
	/**
	 * @internal
	 *
	 * @param $currTick
	 */
	public function startAsyncTask($currTick){
		$this->asyncTaskIsRunning = true;
		$this->server->getScheduler()->scheduleAsyncTask(new SSSAsyncTask($this->doCheckServers, $this->debug, $this->timeout, $currTick));
	}
	
	/**
	 * @internal
	 *
	 * @param $data
	 * @param $scheduleTime
	 */
	public function asyncTaskCallBack($data, $scheduleTime){
		$this->asyncTaskIsRunning = false;
		if($this->debug){
			$this->getLogger()->debug("AsyncTaskResponse:");
			var_dump($data);
		}
		$this->asyncTaskMODTs = [];
		$this->asyncTaskPlayers = [];
		$this->asyncTaskIsOnline = [];
		if(empty($data)){
			return;
		}
		foreach($data as $serverID => $serverData){
			$this->asyncTaskIsOnline[$serverID] = $serverData[2];
			if($serverData[2]){
				$this->asyncTaskMODTs[$serverID] = $serverData[1];
				$this->asyncTaskPlayers[$serverID] = $serverData[0];
			}
		}
		$this->doSignRefresh();
		$this->server->getPluginManager()->callEvent(new SSSasyncUpdateEvent($this, $this->lastRefreshTick, $scheduleTime));
		$this->lastRefreshTick = $scheduleTime;
		
		$currTick = $this->server->getTick();
		if($currTick - $scheduleTime >= $this->signServerStatsCfg->get('SSSAsyncTaskCall')){
			$this->startAsyncTask($currTick);
		}
	}
	
	/**
	 * @internal
	 */
	public function doSignRefresh(){
		foreach($this->doRefreshSigns as $signData){
			$pos = $signData[0];
			$address = $signData[1];
			if($this->server->loadLevel($pos[3])){
				$signTile = $this->server->getLevelByName($pos[3])->getTile(new Vector3($pos[0], $pos[1], $pos[2]));
				if($signTile instanceof Sign){
					$lines = $this->calcSign($address);
					$signTile->setText($lines[0],$lines[1],$lines[2],$lines[3]);
				}else{
					$this->server->broadcast(
						TF::RED."[SSS] r001 Could not find the sign at (".$pos[0]."/".$pos[1]."/".$pos[2]." in ".$pos[3].")",
						Server::BROADCAST_CHANNEL_ADMINISTRATIVE
					);
				}
			}else{
				$this->server->broadcast(
					TF::RED."[SSS] r002 Could not find the level for the sign at (".$pos[0]."/".$pos[1]."/".$pos[2]." in ".$pos[3].")",
					Server::BROADCAST_CHANNEL_ADMINISTRATIVE
				);
			}
		}
	}
	
	/**
	 * @internal
	 *
	 * @return bool
	 */
	public function isAllowedToStartAsyncTask(): bool{
		return $this->signServerStatsCfg->get('always-start-async-task') ? true : !$this->asyncTaskIsRunning;
	}
	
	/**
	 * @internal
	 */
	public function recalcdRSvar(){
		foreach($this->doRefreshSigns as $signData){
			$refreshSignIP = $signData[1];
			$this->addServer($refreshSignIP[0], $refreshSignIP[1]);
		}
	}
	
	/**
	 * @internal
	 *
	 * @param array $address
	 *
	 * @return array $lines
	 */
	public function calcSign(array $address): array{
		$ip = $address[0];
		$port = $address[1];
		if(isset($this->asyncTaskIsOnline[$ip."@".$port])){
			$isOnline = $this->asyncTaskIsOnline[$ip."@".$port];
			if($isOnline){
				$MODT = $this->asyncTaskMODTs[$ip."@".$port];
				$playerData = $this->asyncTaskPlayers[$ip."@".$port];
				$currentPlayers = $playerData[0];
				$maxPlayers = $playerData[1];
				$lines[0] = $MODT ?? TF::DARK_RED."ERROR";
				$lines[1] = "IP: ".TF::GREEN.$ip;
				$lines[2] = "Port: ".TF::DARK_GREEN.$port;
				$lines[3] = TF::DARK_GREEN.($currentPlayers ?? "-").TF::WHITE."/".TF::GOLD.($maxPlayers ?? "-");
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