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
  private $serverFINALdata;
  private $doCheckServer;
  
  private $startTick;
  
  public function __construct(array $doCheckServers, bool $debug, int $startTick){
	  $this->doCheckServer = $doCheckServers;
	  $this->debug = $debug;
	  $this->startTick = $startTick;
  }
  
  private function doQuery(string $ip, int $port): array{
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
			  $adressArray = $server[0];
			  $ip = $adressArray[0];
			  $port = $adressArray[1];
			  $return = $this->doQuery($ip, $port);
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
			  $serverFINALdata[$ip."@".$port] = $serverData;
		  }
	  }
	  $this->setResult($serverFINALdata);
	  if($this->debug){
	  	echo("\n");
	  }
  }
  
  public function onCompletion(Server $server){
	  if($server->getPluginManager()->getPlugin("SignServerStats") instanceof SignServerStats){
		  $server->getPluginManager()->getPlugin("SignServerStats")->asyncTaskCallBack($this->getResult(), $this->startTick);
	  }else{
		  echo("Warning: Async Task started by SignServerStats could not find SignServerStats; aborting");
	  }
  }
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!