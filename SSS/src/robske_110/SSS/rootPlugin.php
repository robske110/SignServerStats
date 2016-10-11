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

class rootPlugin extends PluginBase{
	private $sss;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->sss = new SignServerStats($this);
	}

	public function getAPI(){
		return $this->sss;
	}
}
/* _____ _____ _____ 
  / ____/ ____/ ____|
 | (___| (___| (___  
  \___ \\___ \\___ \ 
  ____) |___) |___) |
 |_____/_____/_____/ 
*/
class SignServerStats implements Listener{
	public $doRefreshSigns = [];
	public $doCheckServers = [];
	public $debug = false;
	private $asyncTaskMODTs;
	private $asyncTaskPlayers;
	private $asyncTaskIsOnline;

	public function __construct($main){
		$this->main = $main;
		$this->server = $main->getServer();
		$this->server->getPluginManager()->registerEvents($this, $this->main);
		$this->db = new Config($this->main->getDataFolder() . "SignServerStatsDB.yml", Config::YAML, array()); //TODO:betterDB
		$this->SignServerStatsCfg = new Config($this->main->getDataFolder() . "SSSconfig.yml", Config::YAML, array());
		if($this->SignServerStatsCfg->get("ConfigVersion") != 1){
			$this->SignServerStatsCfg->set('SSSSignRefreshTask', 40);
			$this->SignServerStatsCfg->set('SSSAsyncTaskCaller', 200);
			$this->SignServerStatsCfg->set('debug', true);
			$this->SignServerStatsCfg->set('ConfigVersion', 1);
		}
		$this->SignServerStatsCfg->save();
		if($this->SignServerStatsCfg->get('debug')){
			$this->debug = true;
		}
		$this->server->getScheduler()->scheduleRepeatingTask(new SSSSignRefreshTask($this->main, $this), $this->SignServerStatsCfg->get("SSSSignRefreshTask"));
		$this->server->getScheduler()->scheduleRepeatingTask(new SSSAsyncTaskCaller($this->main, $this), $this->SignServerStatsCfg->get("SSSAsyncTaskCaller"));
		$this->recalcdRSvar();
	}
	
	public function asyncTaskCallBack($data){
		if($this->debug){
			#echo("AsyncTaskResponse:\n");
			#var_dump($data);
		}
		if(!isset($data)){
			return;
		}
		foreach($data as $serverID => $serverData){
			$this->asyncTaskIsOnline[$serverID] = $serverData[2];
			if($serverData[2]){
				$this->asyncTaskMODTs[$serverID] = $serverData[1];
				$this->asyncTaskPlayers[$serverID] = $serverData[0];
			}
		}
	}
	
	public function doesSignExist(Vector3 $pos, $levelName){
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
	
	public function removeSign(Vector3 $pos, $levelName){
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
	
	public function calcSign($ip){
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
	
	public function onBreak(BlockBreakEvent $event){ 
		$Block = $event->getBlock();
		$pos = new Vector3($Block->getX(), $Block->getY(), $Block->getZ());
		$levelName = $event->getPlayer()->getLevel()->getName();
		if($this->doesSignExist($pos, $levelName)){
			if($event->getPlayer()->isOp()){ 
				if($this->removeSign($pos, $levelName)){
					$event->getPlayer()->sendMessage("[SSS] Sign sucessfully deleted!");
				}else{
					$this->server->broadcastMessage("CRITICAL/r008_FAIL::removeSign [Additional Info: removeSign() has returned false]");
				}
				$this->recalcdRSvar();
			}else{
				$event->getPlayer()->sendMessage("[Cuboss] No, you are not allowed to do that!");
				$this->server->broadcastMessage("r007_Perm_Blocked");
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
			if($event->getPlayer()->isOp()){
				if(!empty($sign[1])){
					if(!empty($sign[2])){
						$pos = new Vector3($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
						$level = $event->getBlock()->getLevel()->getName();
						$this->addSign($sign[1], $sign[2], $pos, $level);
						$this->recalcdRSvar();
						$event->getPlayer()->sendMessage("[SSS] The ServerStats Sign for the IP '".$sign[1]."' Port '".$sign[2]."' is set up correctly!");
					}else{
						$event->getPlayer()->sendMessage("[SSS] PORT_MISSING (LINE3)");
						$this->server->broadcastMessage("r006_PORT_MISSING");
						$event->setLine(0,"[BROKEN]");
						return false;
					}
				}else{
					$event->getPlayer()->sendMessage("[SSS] IP_MISSING (LINE2)");
					$this->server->broadcastMessage("r005_IP_MISSING");
					$event->setLine(0,"[BROKEN]");
					return false;
				}
			}else{
				$event->getPlayer()->sendMessage("[SSS] No, you are not allowed to do that!");
				$this->server->broadcastMessage("r004_Perm_Blocked");
				$event->setLine(0,"[BLOCKED]");
				return false;
			}
		}
		return true;
	}
}

class SSSSignRefreshTask extends PluginTask{
	public function __construct(rootPlugin $main, $parent){
		parent::__construct($main);
		$this->server = $parent->server;
		$this->SSS = $parent;
	}
	
	public function onRun($currentTick){
		foreach($this->SSS->doRefreshSigns as $signData){
			$pos = $signData[0];
			$ip = $signData[1];
			if($this->server->loadLevel($pos[3])){
				$signTile = $this->server->getLevelByName($pos[3])->getTile(new Vector3($pos[0], $pos[1], $pos[2], $pos[3]));
				if($signTile instanceof Sign){
					$lines = $this->SSS->calcSign($ip);
					$signTile->setText($lines[0],$lines[1],$lines[2],$lines[3]);
				}else{
					$this->server->broadcastMessage("r001_TILE_IS_NOT_SIGN");
				}
			}else{
				$this->server->broadcastMessage("r002_UNKNOWN_LEVEL");
			}
		}
	}
}

class SSSAsyncTaskCaller extends PluginTask{
	public function __construct(rootPlugin $main, $parent){
		parent::__construct($main);
		$this->server = $parent->server;
		$this->SSS = $parent;
	}
	
	public function onRun($currentTick){
		$this->server->SSS = $this->SSS;
		$this->server->getScheduler()->scheduleAsyncTask(new SSSAsyncTask($this->SSS->doCheckServers, $this->SSS->debug));
	}
}

class SSSAsyncTask extends AsyncTask{
  private $serverFINALdata;
  private $doCheckServer;
  
  public function __construct($doCheckServers, $debug){
	  $this->doCheckServer = $doCheckServers;
	  $this->debug = $debug;
  }
  
  private function doQuery($ip, $port){
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
	  $server->SSS->asyncTaskCallBack($this->getResult());
  }
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!