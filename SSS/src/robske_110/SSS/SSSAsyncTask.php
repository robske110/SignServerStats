<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\SSS;

use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;

class SSSAsyncTask extends AsyncTask{
	/** @var array */
	private $doCheckServer;
  
	/* @var bool */
	private $debug;
	/** @var $startTick */
	private $startTick;
	/** @var float */
	private $timeout;
  
	public function __construct(array $doCheckServers, bool $debug, float $timeout, int $startTick){
		$this->doCheckServer = $doCheckServers;
		$this->debug = $debug;
		$this->timeout = $timeout;
		$this->startTick = $startTick;
	}
	
	/**
	 * @param string $ip
	 * @param int $port
	 * @param array $timeout
	 *
	 * @return array
	 */
	private function doQuery(string $ip, int $port, array $timeout): array{
		$sock = @fsockopen("udp://".$ip, $port);
		if(!$sock){return [-1, null];}
		socket_set_timeout($sock, $timeout[0], $timeout[1]);
		if(!@fwrite($sock, "\xFE\xFD\x09\x10\x20\x30\x40\xFF\xFF\xFF\x01")){return [0, null];}
		$challenge = fread($sock, 1400);
		if(!$challenge){return [0, null];}
		$challenge = substr(preg_replace("/[^0-9\-]/si", "", $challenge ), 1);
		$query = sprintf(
			"\xFE\xFD\x00\x10\x20\x30\x40%c%c%c%c\xFF\xFF\xFF\x01",
			($challenge >> 24),
			($challenge >> 16),
			($challenge >> 8),
			($challenge >> 0)
		);
		if(!@fwrite($sock, $query)){return [0, null];}
		$response = array();
		for($x = 0; $x < 2; $x++){
			$response[] = @fread($sock, 2048);
		}
		$response = implode($response);
		$response = substr($response, 16);
		$response = explode("\0", $response);
		for($x = 0; $x < 2; $x++){
			array_pop($response);
		}
		$return = [];
		$type = true;
		$players = false;
		foreach($response as $key){
			if(strpos($key, "player_") !== false){
				$players = true;
				continue;
			}
			if($players){
				if($key !== ""){
					$return["players"][] = $key;
				}
				continue;
			}
			if($type) $val = $key;
			if(!$type) $return[$val] = $key;
			$type = !$type;
		}
		$requiredKeys = ['numplayers', 'maxplayers', 'hostname'];
		foreach($requiredKeys as $requiredKey){
			if(!isset($return[$requiredKey])){
				return [-1, null];
			}
		}
		return [1, $return];
	}
  
	public function onRun(){
		if($this->debug){
			echo("DoCheckServer:\n");
			var_dump($this->doCheckServer);
		}
		$timeout = explode(".", (string) $this->timeout);
		$serverFINALdata = [];
		foreach($this->doCheckServer as $server){
			if($this->hasCancelledRun()){
				$this->setResult([]);
				return;
			}
			$ip = $server[0];
			$port = $server[1];
			$return = $this->doQuery($ip, $port, $timeout);
			$queryResult = $return[1];
			$serverData = [];
			if($this->debug){
				echo("returnState:\n");
				var_dump($return[0]);
				echo("queryResult:\n");
				var_dump($queryResult);
			}
			switch($return[0]){
				case -1;
				$serverData[0] = false;
				break;
				case 0:
				$serverData[0] = false;
				break;
				case 1:
				$serverData[0] = true;
				$serverData[1] = [$queryResult['numplayers'], $queryResult['maxplayers']];
				$serverData[2] = $queryResult['hostname'];
				$serverData[3] = $queryResult;
			}
			$serverFINALdata[$ip."@".$port] = $serverData;
		}
		$this->setResult($serverFINALdata);
		if($this->debug){
			echo("\n");
		}
	}
  
	public function onCompletion(Server $server){
	    $sss = $server->getPluginManager()->getPlugin("SignServerStats");
		if($sss instanceof SignServerStats){
			$sss->asyncTaskCallBack($this->getResult(), $this->startTick);
		}else{
			echo("Warning: Async Task started by SignServerStats could not find SignServerStats; aborting");
		}
	}
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!