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
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\tile\Sign;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\PluginTask;

/* _____ _____ _____ 
  / ____/ ____/ ____|
 | (___| (___| (___  
  \___ \\___ \\___ \ 
  ____) |___) |___) |
 |_____/_____/_____/ 
*/
class SignServerStats extends PluginBase{
	private $listener;
	private $doCheckServers = [];
	private $debug = false;
	private $asyncTaskIsRunning = false;
	private $server;
	private $doRefreshSigns = [];
	private $asyncTaskMODTs;
	private $asyncTaskPlayers;
	private $asyncTaskIsOnline;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->server = $this->getServer();
		$this->db = new Config($this->main->getDataFolder() . "SignServerStatsDB.yml", Config::YAML, array()); //TODO:betterDB
		$this->SignServerStatsCfg = new Config($this->main->getDataFolder() . "SSSconfig.yml", Config::YAML, array());
		if($this->SignServerStatsCfg->get("ConfigVersion") != 2){
			$this->SignServerStatsCfg->set('SSSAsyncTaskCall', 200);
			$this->SignServerStatsCfg->set('always-start-async-task', false);
			$this->SignServerStatsCfg->set('debug', false);
			$this->SignServerStatsCfg->set('ConfigVersion', 2);
		}
		$this->SignServerStatsCfg->save();
		if($this->SignServerStatsCfg->get('debug')){
			$this->debug = true;
		}
		$this->listener = new SSSListener($this);
		$this->server->getPluginManager()->registerEvents($this, $this->listener);
		$this->recalcdRSvar();
		$this->server->getScheduler()->scheduleRepeatingTask(new SSSAsyncTaskCaller($this->main, $this), $this->SignServerStatsCfg->get("SSSAsyncTaskCaller"));
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
		if($currTick - $scheduleTime >= $this->SignServerStatsCfg->get('SSSAsyncTaskCall')){
			$this->startAsyncTask($currTick);
		}
	}
	
	public function isAllowedToStartAsyncTask(): bool{
		return $this->SignServerStatsCfg->get('always-start-async-task') ? true : !$this->asyncTaskIsRunning;
	}
	
	public function getOnlineServers(): array{
		return $this->asyncTaskIsOnline;
	}
	
	public function getMODTs(): array{
		return $this->asyncMODTs;
	}
	
	public function getPlayerData(): array{
		return $this->asyncTaskPlayers;
	}
	
	public function debugEnabled(): bool{
		return $this->debug;
	}
	
	public function isAdmin(Player $player): bool{
		return true;
	}
	
	public function doSignRefresh(){
		foreach($this->doRefreshSigns as $signData){
			$pos = $signData[0];
			$ip = $signData[1];
			if($this->server->loadLevel($pos[3])){
				$signTile = $this->server->getLevelByName($pos[3])->getTile(new Vector3($pos[0], $pos[1], $pos[2], $pos[3]));
				if($signTile instanceof Sign){
					$lines = $this->calcSign($ip);
					$signTile->setText($lines[0],$lines[1],$lines[2],$lines[3]);
				}else{
					$this->server->broadcast("r001_SIGN_NOT_FOUND_AT(".$pos[0]."/"$pos[1]."/".$pos[2]." in "$pos[3].")", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
			}else{
				$this->server->broadcast("r002_COULD_NOT_FIND_LEVEL_FOR_SIGN_AT(".$pos[0]."/"$pos[1]."/".$pos[2]." in "$pos[3].")", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
			}
		}
	}
	
	public function doesSignExist(Vector3 $pos, $levelName): bool{
		$foundSign = false;
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		foreach($this->doRefreshSigns as $key => $signData){
			$pos = $signData[0];
			if($deParsedPos == [$pos[0], $pos[1], $pos[2], $pos[3]]){
				$foundSign = true;
			}
		}
		return $foundSign;
	}
	
	public function addSign($ip, $port, Vector3 $pos, $levelName){
		$currentSignOffset = count($this->db->getAll());
		$parsedIP = [$ip, $port];
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		$this->db->set($currentSignOffset, [$deParsedPos, $parsedIP]);
		$this->db->save();
	}
	
	public function removeSign(Vector3 $pos, $levelName): bool{
		$foundSign = false;
		$deParsedPos = [$pos->x, $pos->y, $pos->z, $levelName];
		foreach($this->doRefreshSigns as $key => $signData){
			$pos = $signData[0];
			if($deParsedPos == [$pos[0], $pos[1], $pos[2], $pos[3]]){
				$this->db->remove($key);
				$this->db->save();
				$currentSignArray = $this->db->getAll();
				$finalSignArray = array_values($currentSignArray);
				$this->db->setAll($finalSignArray);
				$this->db->save();
				$foundSign = true;
			}
		}
		return $foundSign;
	}
	
	public function recalcdRSvar(){ //TODO:doMorePreParsingAtThisFunction
		$this->doRefreshSigns = $this->db->getAll();
		foreach($this->doRefreshSigns as $signData){
			$refreshSignIP = $signData[1];
			$currentdCSoffset = count($this->doCheckServers);
			$this->doCheckServers[$currentdCSoffset] = [[$refreshSignIP[0], $refreshSignIP[1]], true];
		}
	}
	
	public function calcSign($ip): array{
		$realIP = $ip[0];
		$port = $ip[1];
		if(isset($this->asyncTaskIsOnline[$realIP.$port])){
			$isOnline = $this->asyncTaskIsOnline[$realIP.$port];
			if($isOnline){
				$MODT = $this->asyncTaskMODTs[$realIP.$port];
				$playerData = $this->asyncTaskPlayers[$realIP.$port];
				$currentPlayers = $playerData[0];
				$maxPlayers = $playerData[1];
				$lines[0] = $MODT;
				$lines[1] = "IP: ".TF::GREEN.$realIP;
				$lines[2] = "Port: ".TF::DARK_GREEN.$port;
				$lines[3] = TF::DARK_GREEN.$currentPlayers.TF::WHITE."/".TF::GOLD.$maxPlayers;
			}else{
				$lines[0] = TF::DARK_RED."Offline";
				$lines[1] = "IP: ".TF::GREEN.$realIP;
				$lines[2] = "Port: ".TF::DARK_GREEN.$port;
				$lines[3] = "-"." / "."-";
			}
		}else{ //If this happens a new Sign has been added and the AsyncTask hasn't returned the data for it yet!
			$lines[0] = TF::GOLD."Loading...";
			$lines[1] = "IP: ".TF::GREEN.$realIP;
			$lines[2] = "Port: ".TF::DARK_GREEN.$port;
			$lines[3] = "-"." / "."-";
		}
		return $lines;
	}
	
}

class SSSListener implements Listener{
	private $main;
	private $server;
	
	public function __construct(SignServerStats $main){
		$this->main = $main;
		$this->server = $main->getServer();
	}
	
	public function onBreak(BlockBreakEvent $event){ 
		$block = $event->getBlock();
		$pos = new Vector3($block->getX(), $block->getY(), $block->getZ());
		$levelName = $event->getPlayer()->getLevel()->getFolderName();
		if($this->main->doesSignExist($pos, $levelName)){
			if($this->main->isAdmin($event->getPlayer())){ 
				if($this->main->removeSign($pos, $levelName)){
					$event->getPlayer()->sendMessage("[SSS] Sign sucessfully deleted!");
				}else{
					$this->server->broadcast("CRITICAL/r005_FAIL::removeSign [Additional Info: removeSign() has returned false]", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
				$this->main->recalcdRSvar();
			}else{
				$event->getPlayer()->sendMessage("[SSS] No, you are not allowed to do that!");
				$event->setCancelled();
			}
		}
	}
	
	public function signUpdate(SignChangeEvent $event){
		$sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
		if(!($sign instanceof Sign)){
			return true;
		}
		$sign = $event->getLines();
		if($sign[0]=='[SSS]'){
			if($this->main->isAdmin($event->getPlayer())){
				if(!empty($sign[1])){
					if(!empty($sign[2])){
						$pos = new Vector3($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
						$levelName = $event->getBlock()->getLevel()->getFolderName();
						$this->main->addSign($sign[1], $sign[2], $pos, $levelName);
						$this->main->recalcdRSvar();
						$event->getPlayer()->sendMessage("[SSS] The ServerStats Sign for the IP '".$sign[1]."' Port '".$sign[2]."' is set up correctly!");
					}else{
						$event->getPlayer()->sendMessage("[SSS] PORT_MISSING (LINE3)");
						$this->server->broadcast("r003_PORT_MISSING", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
						$event->setLine(0,"[BROKEN]");
						return false;
					}
				}else{
					$event->getPlayer()->sendMessage("[SSS] IP_MISSING (LINE2)");
					$this->server->broadcast("r004_IP_MISSING", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
					$event->setLine(0,"[BROKEN]");
					return false;
				}
			}else{
				$event->getPlayer()->sendMessage("[SSS] No, you are not allowed to do that!");
				$event->setLine(0,"[BLOCKED]");
				return false;
			}
		}
		return true;
	}
}

class SSSAsyncTaskCaller extends PluginTask{
	public function __construct(rootPlugin $main, SignServerStats $parent){
		parent::__construct($main);
		$this->SSS = $parent;
	}
	
	public function onRun($currentTick){
		if($this->SSS->isAllowedToStartAsyncTask()){
			$this->SSS->startAsyncTask($currentTick);
		}
	}
}

/** @todo Local complex */
class SSSAsyncTask extends AsyncTask{
  private $serverFINALdata;
  private $doCheckServer;
  
  private $startTick;
  
  public function __construct(array $doCheckServers, bool $debug, int $startTick){
	  $this->doCheckServer = $doCheckServers;
	  $this->debug = $debug;
	  $this->startTick = $startTick;
  }
  
  private function doQuery(int $ip, int $port): array{
  	  if($this->debug){
  	  	echo("doQuery:\n");
  	  }
      $sock = @fsockopen("udp://".$ip,$port);
      if(!$sock){return [-1, NULL];}
      socket_set_timeout($sock, 0, 500000);
      if(!@fwrite($sock, "\xFE\xFD\x09\x10\x20\x30\x40\xFF\xFF\xFF\x01")){return [0, NULL];}
      $challenge = fread($sock, 1400);
      if(!$challenge){return [0, NULL];}
      $challenge = substr(preg_replace("/[^0-9\-]/si", "", $challenge ), 1);
      $query = sprintf(
          "\xFE\xFD\x00\x10\x20\x30\x40%c%c%c%c\xFF\xFF\xFF\x01",
          ($challenge >> 24),
          ($challenge >> 16),
          ($challenge >> 8),
          ($challenge >> 0)
          );
      if(!@fwrite($sock, $query)){return [0, NULL];}
      $response = array();
      for($x = 0; $x < 2; $x++){
          $response[] = @fread($sock,2048);
      }
	  if($this->debug){
	      var_dump($response);
      }
      $response = implode($response);
      $response = substr($response,16);
      $response = explode("\0",$response);
	  if($this->debug){
	  	  var_dump($response);
	  }
      array_pop($response);
      array_pop($response);
      array_pop($response);
      array_pop($response);
      $return = [];
      $type = 0;
	  if($this->debug){
		  var_dump($response);
      }
      foreach ($response as $key){
          if ($type == 0) $val = $key;
          if ($type == 1) $return[$val] = $key;
          $type == 0 ? $type = 1 : $type = 0;
	  }	
  	  return [1, $return]; 
  	  if($this->debug){
	  	echo("DoQueryEnd\n");
  	  }
  }
  
  public function onRun(){
  	  if($this->debug){
	  	echo("DoCheckServer:\n");
		  var_dump($this->doCheckServer);
  	  }
	  foreach($this->doCheckServer as $server){
		  $doCheck = $server[1];
		  if($doCheck){
			  $ip = $server[0];
			  $deParsedIP = $ip[0];
			  $port = $ip[1];
			  $return = $this->doQuery($deParsedIP, $port);
			  $returnState = $return[0];
			  $queryResult = $return[1];
			  $serverData = [];
			  if($this->debug){
			    echo("returnState:\n");
			  	var_dump($returnState);
			  }
			  switch($returnState){
				  case -1;
					  $serverData[2] = false;
			  	  break;
				  case 0:
				  	  $serverData[2] = false;
				  break;
				  case 1:
				      $serverData[0] = [$queryResult['numplayers'], $queryResult['maxplayers']];
				      $serverData[1] = $queryResult['hostname'];
					  $serverData[2] = true;
			  }
			  $serverFINALdata[$deParsedIP.$port] = $serverData;
			  $this->setResult($serverFINALdata);
		  }
	  }
	  if($this->debug){
	  	echo("\n");
	  }
  }
  
  public function onCompletion(Server $server){
	  $server->getPluginManager()->getPlugin("SignServerStats")->getAPI()->asyncTaskCallBack($this->getResult(), $this->startTick);
  }
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!